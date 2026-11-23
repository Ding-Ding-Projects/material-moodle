'use strict';
// Pure workspace logic with no DOM and no dockview calls: saved-layout validation, tab search,
// the narrow-window merge, tab context menu model and shortcut matching.
// Loaded as a plain script in the renderer and with require() in the unit tests.
(function (root) {
  const LAYOUT_VERSION = 1;
  const NARROW_WIDTH = 900;
  const PAGE_COMPONENT = 'page';
  const TAB_COMPONENT = 'tab';
  const MAX_GROUP_LABEL = 40;

  const SHORTCUTS = {
    close: 'Ctrl+W',
    pin: 'Ctrl+Alt+P',
    lock: 'Ctrl+Alt+L',
    search: 'Ctrl+Alt+F',
    nextTab: 'Ctrl+Tab',
    prevTab: 'Ctrl+Shift+Tab',
    groupLeft: 'Ctrl+Alt+Left',
    groupRight: 'Ctrl+Alt+Right',
    groupUp: 'Ctrl+Alt+Up',
    groupDown: 'Ctrl+Alt+Down',
  };

  const isObject = (v) => v !== null && typeof v === 'object' && !Array.isArray(v);
  const isNumber = (v) => typeof v === 'number' && Number.isFinite(v);

  /** True when the window is narrow enough that every tab belongs in one group. */
  function isNarrow(width) {
    return isNumber(width) && width > 0 && width < NARROW_WIDTH;
  }

  /** Wrap a dockview toJSON() result for storage. */
  function wrapLayout(layout, now) {
    return { version: LAYOUT_VERSION, savedAt: isNumber(now) ? now : Date.now(), layout };
  }

  // A grid branch holds children, a leaf holds a group with its panel ids. Returns the panel ids found,
  // or null when the shape is wrong. Depth is capped so a hostile or corrupt file cannot recurse forever.
  function collectLeafPanelIds(node, depth, out) {
    if (!isObject(node) || depth > 24) return false;
    if (node.type === 'branch') {
      if (!Array.isArray(node.data) || node.data.length === 0) return false;
      return node.data.every((child) => collectLeafPanelIds(child, depth + 1, out));
    }
    if (node.type === 'leaf') {
      const data = node.data;
      if (!isObject(data) || !Array.isArray(data.views) || data.views.length === 0) return false;
      if (!data.views.every((id) => typeof id === 'string')) return false;
      if (typeof data.activeView === 'string' && !data.views.includes(data.activeView)) return false;
      out.push(...data.views);
      return true;
    }
    return false;
  }

  /**
   * Decide whether a stored value is safe to hand to dockview's fromJSON.
   * Returns { ok: true, layout } or { ok: false, reason }. Never throws.
   * Panels that are known but absent from the layout are fine: they were closed and stay reopenable.
   */
  function validateSavedLayout(saved, knownPanelIds) {
    try {
      const known = new Set(knownPanelIds);
      if (!isObject(saved)) return { ok: false, reason: 'missing' };
      if (saved.version !== LAYOUT_VERSION) return { ok: false, reason: 'version' };
      const layout = saved.layout;
      if (!isObject(layout) || !isObject(layout.grid) || !isObject(layout.panels)) return { ok: false, reason: 'shape' };
      const grid = layout.grid;
      if (!isNumber(grid.width) || !isNumber(grid.height) || !isObject(grid.root)) return { ok: false, reason: 'grid' };
      if (grid.orientation !== 'HORIZONTAL' && grid.orientation !== 'VERTICAL') return { ok: false, reason: 'grid' };
      const inGrid = [];
      if (!collectLeafPanelIds(grid.root, 0, inGrid)) return { ok: false, reason: 'grid' };
      const panelKeys = Object.keys(layout.panels);
      if (panelKeys.length === 0 || panelKeys.length > known.size) return { ok: false, reason: 'panels' };
      if (new Set(inGrid).size !== inGrid.length) return { ok: false, reason: 'duplicate' };
      for (const id of panelKeys) {
        const panel = layout.panels[id];
        if (!known.has(id)) return { ok: false, reason: 'unknown-panel' };
        if (!isObject(panel) || panel.id !== id || panel.contentComponent !== PAGE_COMPONENT || (panel.tabComponent !== undefined && panel.tabComponent !== TAB_COMPONENT)) return { ok: false, reason: 'panel' };
      }
      if (inGrid.length !== panelKeys.length || !inGrid.every((id) => panelKeys.includes(id))) return { ok: false, reason: 'mismatch' };
      // Floating and popout groups are not used by this app; refuse them rather than restore windows that cannot be reached.
      if (Array.isArray(layout.floatingGroups) && layout.floatingGroups.length) return { ok: false, reason: 'floating' };
      if (Array.isArray(layout.popoutGroups) && layout.popoutGroups.length) return { ok: false, reason: 'popout' };
      return { ok: true, layout };
    } catch {
      return { ok: false, reason: 'error' };
    }
  }

  /** Pick the layout to restore: the saved one when valid, otherwise null (the caller builds the default). */
  function chooseLayout(saved, knownPanelIds) {
    const checked = validateSavedLayout(saved, knownPanelIds);
    return checked.ok ? checked.layout : null;
  }

  // ---------- tab search ----------

  const fold = (s) => String(s ?? '').normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase();

  function scoreTitle(title, query) {
    const t = fold(title);
    if (t === query) return 100;
    if (t.startsWith(query)) return 80;
    if (t.split(/[\s\-_/]+/).some((w) => w.startsWith(query))) return 60;
    if (t.includes(query)) return 40;
    // In-order character match, so "stg" finds "Settings".
    let i = 0;
    for (const ch of t) if (ch === query[i]) i++;
    return i === query.length ? 15 : 0;
  }

  /**
   * Search tabs. items: [{ id, title, open, pinned, locked, groupLabel }].
   * Every item is searchable, open or closed, so a closed tab can be found and reopened.
   * An empty query lists everything, open tabs first, in their given order.
   */
  function searchTabs(items, query) {
    const q = fold(query).trim();
    const rows = items.map((item, index) => ({ item, index, score: 0 }));
    if (!q) {
      return rows.sort((a, b) => Number(b.item.open) - Number(a.item.open) || a.index - b.index).map((r) => r.item);
    }
    for (const r of rows) {
      const own = scoreTitle(r.item.title, q);
      const viaId = r.item.id && fold(r.item.id).includes(q) ? 20 : 0;
      const viaGroup = r.item.groupLabel && fold(r.item.groupLabel).includes(q) ? 30 : 0;
      r.score = Math.max(own, viaId, viaGroup);
    }
    return rows
      .filter((r) => r.score > 0)
      .sort((a, b) => b.score - a.score || Number(b.item.open) - Number(a.item.open) || a.index - b.index)
      .map((r) => r.item);
  }

  // ---------- narrow windows ----------

  /**
   * After a narrow collapse and a later widening, the saved wide layout is restored.
   * Tabs the person closed or reopened in between are applied on top of it.
   * Returns { close: [...ids], add: [...ids] }.
   */
  function reconcileOpenPanels(snapshotIds, currentIds) {
    const before = new Set(snapshotIds);
    const now = new Set(currentIds);
    return {
      close: [...before].filter((id) => !now.has(id)),
      add: [...now].filter((id) => !before.has(id)),
    };
  }

  // ---------- tab context menu model ----------

  /**
   * The ordered items of a tab context menu as data, so the rules (a pinned or locked tab cannot be closed,
   * a lone tab cannot be split) are testable without a DOM. The renderer maps each id to an action.
   */
  function tabMenuModel(state) {
    const sticky = !!(state.pinned || state.locked);
    const items = [
      { id: 'close', labelKey: 'ws.menu.close', shortcut: SHORTCUTS.close, disabled: sticky },
      { id: 'closeOthers', labelKey: 'ws.menu.closeOthers', disabled: !state.otherClosableCount },
      { id: 'sep1', separator: true },
      { id: 'pin', labelKey: state.pinned ? 'ws.menu.unpin' : 'ws.menu.pin', shortcut: SHORTCUTS.pin },
      { id: 'lock', labelKey: state.locked ? 'ws.menu.unlock' : 'ws.menu.lock', shortcut: SHORTCUTS.lock },
      { id: 'sep2', separator: true },
      state.inTabGroup
        ? { id: 'ungroup', labelKey: 'ws.menu.ungroup' }
        : { id: 'group', labelKey: 'ws.menu.group' },
    ];
    if (state.inTabGroup) {
      items.push({ id: 'renameGroup', labelKey: 'ws.menu.renameGroup' });
      items.push({ id: 'colourGroup', labelKey: 'ws.menu.colourGroup' });
    }
    items.push({ id: 'sep3', separator: true });
    items.push({ id: 'splitRight', labelKey: 'ws.menu.splitRight', disabled: state.panelsInGroup < 2 || state.locked });
    items.push({ id: 'splitDown', labelKey: 'ws.menu.splitDown', disabled: state.panelsInGroup < 2 || state.locked });
    items.push({ id: 'sep4', separator: true });
    items.push({ id: 'search', labelKey: 'ws.menu.search', shortcut: SHORTCUTS.search });
    items.push({ id: 'reset', labelKey: 'ws.menu.reset' });
    return items;
  }

  // ---------- keyboard routes ----------

  /** Map a keydown to a workspace command, or null. Pure: pass { key, ctrlKey, metaKey, altKey, shiftKey }. */
  function matchShortcut(e) {
    const ctrl = !!(e.ctrlKey || e.metaKey);
    const k = String(e.key || '').toLowerCase();
    if (ctrl && !e.altKey && k === 'tab') return e.shiftKey ? 'prevTab' : 'nextTab';
    if (ctrl && e.altKey && !e.shiftKey) {
      if (k === 'arrowleft') return 'groupLeft';
      if (k === 'arrowright') return 'groupRight';
      if (k === 'arrowup') return 'groupUp';
      if (k === 'arrowdown') return 'groupDown';
      if (k === 'p') return 'pin';
      if (k === 'l') return 'lock';
      if (k === 'f') return 'search';
    }
    if (ctrl && !e.altKey && !e.shiftKey && k === 'w') return 'close';
    return null;
  }

  /** Cycle to the next or previous index with wrap-around. */
  function cycleIndex(index, length, step) {
    if (!length) return -1;
    return (((index + step) % length) + length) % length;
  }

  function cleanGroupLabel(value) {
    return String(value ?? '').replace(/[\u0000-\u001f\u007f]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, MAX_GROUP_LABEL);
  }

  const api = {
    LAYOUT_VERSION, NARROW_WIDTH, PAGE_COMPONENT, TAB_COMPONENT, MAX_GROUP_LABEL, SHORTCUTS,
    isNarrow, wrapLayout, validateSavedLayout, chooseLayout, searchTabs, reconcileOpenPanels,
    tabMenuModel, matchShortcut, cycleIndex, cleanGroupLabel,
  };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.MMDWorkspaceCore = api;
})(typeof globalThis !== 'undefined' ? globalThis : this);
