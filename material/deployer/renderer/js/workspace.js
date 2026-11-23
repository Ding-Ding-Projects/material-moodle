'use strict';
// Movable tabs, splits and docking, built on dockview-core and mounted underneath the existing pages.
// Every page (Server, Tunnel, Logs, Settings, About) is one dockview panel whose content is the same
// page the rail always showed. The pure rules (saved-layout checks, tab search, shortcuts) live in
// workspace-core.js so they can be unit tested.
(function () {
  const M = window.MMD;
  const { state, api, h, t } = M;
  const W = window.MMDWorkspaceCore;
  const DV = window['dockview-core'];

  const contents = new Map(); // page id -> content renderer
  const tabs = new Map(); // page id -> tab renderer
  const flags = new Map(); // page id -> { pinned, locked }
  let dock = null;
  let saveTimer = 0;
  let restoring = false;
  let narrow = { active: false, snapshot: null };
  let searchEl = null;

  const flagsOf = (id) => flags.get(id) || { pinned: false, locked: false };
  const isSticky = (id) => { const f = flagsOf(id); return f.pinned || f.locked; };
  const panelOf = (id) => dock?.getPanel(id);
  const titleOf = (id) => t('nav.' + id);

  // ---------- content ----------

  function renderPageInto(id, animate) {
    const c = contents.get(id);
    if (!c || !M.pages[id]) return;
    const scroll = c.element.scrollTop;
    const page = h('section', { class: 'page' + (animate ? ' page-enter' : ''), 'data-page': id, 'aria-label': titleOf(id) });
    M.pages[id](page);
    c.element.replaceChildren(page);
    c.element.scrollTop = animate ? 0 : scroll;
  }

  class PageContent {
    constructor() { this.element = h('div', { class: 'ws-page' }); }
    init(p) {
      this.id = p.params.page;
      contents.set(this.id, this);
      if (p.params.pinned || p.params.locked) flags.set(this.id, { pinned: !!p.params.pinned, locked: !!p.params.locked });
      renderPageInto(this.id, false);
    }
    onShow() { if (this.element.isConnected) renderPageInto(this.id, true); }
    dispose() { if (contents.get(this.id) === this) contents.delete(this.id); }
  }

  // ---------- tab ----------

  class PageTab {
    constructor() {
      this.element = h('div', { class: 'ws-tab' });
    }
    init(p) {
      this.id = p.params.page;
      this.panelApi = p.api;
      tabs.set(this.id, this);
      this.build(p.title);
      this.sub = p.api.onDidTitleChange((e) => { this.label.textContent = e.title; this.refresh(); });
      // Capture phase on the tab itself, so it runs before dockview's own drag and context handlers.
      this.element.addEventListener('dragstart', (e) => {
        if (flagsOf(this.id).locked) { e.preventDefault(); e.stopPropagation(); }
      }, true);
      this.element.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        e.stopPropagation();
        openTabMenu(this.id, e.clientX, e.clientY);
      });
      this.element.addEventListener('auxclick', (e) => {
        if (e.button === 1) { e.preventDefault(); closePage(this.id); }
      });
    }
    build(title) {
      this.label = h('span', { class: 'ws-tab-title', text: title });
      this.pin = h('span', { class: 'ws-tab-flag', 'data-flag': 'pin', title: t('ws.pinned') }, M.icon('pin', 14));
      this.lock = h('span', { class: 'ws-tab-flag', 'data-flag': 'lock', title: t('ws.locked') }, M.icon('lock', 14));
      this.close = h('button', { type: 'button', class: 'ws-tab-close', tabindex: '-1' },
        M.icon('close', 14), h('span', { class: 'ws-tab-close-text', text: t('ws.close') }));
      this.close.addEventListener('click', (e) => { e.stopPropagation(); closePage(this.id); });
      // Keep a click on the close button from starting a drag or re-activating through the tab.
      this.close.addEventListener('pointerdown', (e) => e.stopPropagation());
      this.element.replaceChildren(h('span', { class: 'ws-tab-icon' }, M.icon(this.id, 16)), this.label, this.pin, this.lock, this.close);
      this.refresh();
    }
    refresh() {
      const f = flagsOf(this.id);
      this.element.classList.toggle('is-pinned', f.pinned);
      this.element.classList.toggle('is-locked', f.locked);
      this.pin.hidden = !f.pinned;
      this.lock.hidden = !f.locked;
      const sticky = f.pinned || f.locked;
      this.close.disabled = sticky;
      this.close.hidden = sticky;
      this.close.setAttribute('aria-label', t('ws.closeTab', { name: this.label.textContent }));
      this.close.title = t('ws.close') + ' (' + W.SHORTCUTS.close + ')';
    }
    dispose() { this.sub?.dispose(); if (tabs.get(this.id) === this) tabs.delete(this.id); }
  }

  // ---------- header actions ----------

  class RightActions {
    constructor() { this.element = h('div', { class: 'ws-actions' }); }
    init() {
      const search = h('button', { type: 'button', class: 'ws-action', title: t('ws.search') + ' (' + W.SHORTCUTS.search + ')' },
        M.icon('search', 16), h('span', { class: 'ws-action-text', text: t('ws.searchShort') }));
      search.addEventListener('click', () => openTabSearch(search));
      const layout = h('button', { type: 'button', class: 'ws-action', 'aria-haspopup': 'menu', title: t('ws.layout') },
        M.icon('layout', 16), h('span', { class: 'ws-action-text', text: t('ws.layout') }));
      layout.addEventListener('click', () => {
        const r = layout.getBoundingClientRect();
        showMenu(r.right - 220, r.bottom + 4, [
          { label: t('ws.search'), key: W.SHORTCUTS.search, run: () => openTabSearch(layout) },
          { label: t('ws.openAll'), run: openAll },
          'sep',
          { label: t('ws.reset'), run: resetLayout },
        ], layout);
      });
      this.element.replaceChildren(search, layout);
    }
    dispose() {}
  }

  class Watermark {
    constructor() { this.element = h('div', { class: 'ws-empty' }); }
    init() {
      const open = h('button', { type: 'button', class: 'btn btn-tonal' }, M.icon('search', 18), h('span', { text: t('ws.search') }));
      open.addEventListener('click', () => openTabSearch(open));
      this.element.replaceChildren(h('p', { class: 'lead', text: t('ws.empty') }), open);
    }
  }

  // ---------- menus ----------

  function showMenu(x, y, items, returnFocus) {
    const menu = M.$('#ctxmenu');
    menu.replaceChildren(...items.map((it) => it === 'sep'
      ? h('div', { class: 'ctx-sep', role: 'separator' })
      : h('button', { type: 'button', role: 'menuitem', class: 'ctx-item', disabled: !!it.disabled, tabindex: '-1', onclick: () => { M.closeContextMenu(); it.run(); } },
        h('span', { class: 'grow', text: it.label }), it.key ? h('kbd', { text: it.key }) : null)));
    menu.hidden = false;
    const r = menu.getBoundingClientRect();
    menu.style.left = Math.max(8, Math.min(x, window.innerWidth - r.width - 8)) + 'px';
    menu.style.top = Math.max(8, Math.min(y, window.innerHeight - r.height - 8)) + 'px';
    menu.querySelector('.ctx-item:not([disabled])')?.focus();
    M.setContextReturn?.(returnFocus);
  }

  function tabGroupOf(panel) {
    try { return dock.getTabGroupForPanel({ groupId: panel.group.id, panelId: panel.id }) || null; } catch { return null; }
  }

  function openTabMenu(id, x, y) {
    const panel = panelOf(id);
    if (!panel) return;
    const f = flagsOf(id);
    const group = panel.group;
    const others = group.panels.filter((p) => p.id !== id && !isSticky(p.id)).length;
    const model = W.tabMenuModel({
      pinned: f.pinned, locked: f.locked, otherClosableCount: others,
      inTabGroup: !!tabGroupOf(panel), panelsInGroup: group.panels.length,
    });
    const run = {
      close: () => closePage(id),
      closeOthers: () => group.panels.filter((p) => p.id !== id).forEach((p) => closePage(p.id, true)),
      pin: () => setFlag(id, 'pinned', !f.pinned),
      lock: () => setFlag(id, 'locked', !f.locked),
      group: () => groupPanel(id),
      ungroup: () => { dock.removePanelFromTabGroup({ groupId: group.id, panelId: id }); },
      renameGroup: () => renameGroup(id, x, y),
      colourGroup: () => colourGroup(id, x, y),
      splitRight: () => splitPanel(id, 'right'),
      splitDown: () => splitPanel(id, 'below'),
      search: () => openTabSearch(tabs.get(id)?.element),
      reset: resetLayout,
    };
    showMenu(x, y, model.map((it) => it.separator ? 'sep' : {
      label: t(it.labelKey), key: it.shortcut, disabled: it.disabled, run: run[it.id],
    }), tabs.get(id)?.element);
  }

  // ---------- small popovers (group name, group colour) ----------

  function popover(x, y, content, onClose) {
    const pop = h('div', { class: 'ws-pop', role: 'dialog' }, content);
    const close = () => { pop.remove(); document.removeEventListener('pointerdown', away, true); onClose?.(); };
    const away = (e) => { if (!pop.contains(e.target)) close(); };
    pop.addEventListener('keydown', (e) => { if (e.key === 'Escape') { e.stopPropagation(); close(); } });
    document.body.append(pop);
    const r = pop.getBoundingClientRect();
    pop.style.left = Math.max(8, Math.min(x, window.innerWidth - r.width - 8)) + 'px';
    pop.style.top = Math.max(8, Math.min(y, window.innerHeight - r.height - 8)) + 'px';
    setTimeout(() => document.addEventListener('pointerdown', away, true), 0);
    return close;
  }

  function groupPanel(id) {
    const panel = panelOf(id);
    if (!panel) return;
    const n = dock.getTabGroups({ groupId: panel.group.id }).length + 1;
    const palette = dock.tabGroupColors;
    const tg = dock.createTabGroup({ groupId: panel.group.id, label: t('ws.groupDefault', { n }), color: palette.length ? palette[(n - 1) % palette.length].id : undefined });
    dock.addPanelToTabGroup({ groupId: panel.group.id, tabGroupId: tg.id, panelId: id });
    const r = (tabs.get(id)?.element || document.body).getBoundingClientRect();
    renameGroup(id, r.left, r.bottom + 4);
  }

  function renameGroup(id, x, y) {
    const panel = panelOf(id);
    const tg = panel && tabGroupOf(panel);
    if (!tg) return;
    const input = h('input', { type: 'text', class: 'ws-pop-input', value: tg.label, maxlength: String(W.MAX_GROUP_LABEL), 'aria-label': t('ws.groupName') });
    const done = h('button', { type: 'button', class: 'btn btn-tonal btn-sm', text: t('ws.done') });
    let close = () => {};
    const commit = () => { tg.setLabel(W.cleanGroupLabel(input.value)); close(); };
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); commit(); } });
    done.addEventListener('click', commit);
    close = popover(x, y, [h('label', { class: 'ws-pop-label', text: t('ws.groupName') }), input, done]);
    input.focus();
    input.select();
  }

  function colourGroup(id, x, y) {
    const panel = panelOf(id);
    const tg = panel && tabGroupOf(panel);
    if (!tg) return;
    const swatches = dock.tabGroupColors.map((c) => {
      const b = h('button', { type: 'button', class: 'ws-swatch' + (tg.color === c.id ? ' on' : ''), title: c.label || c.id, 'aria-label': c.label || c.id, 'aria-pressed': String(tg.color === c.id) });
      b.style.background = c.value;
      b.addEventListener('click', () => { tg.setColor(c.id); close(); });
      return b;
    });
    const close = popover(x, y, [h('div', { class: 'ws-pop-label', text: t('ws.groupColour') }), h('div', { class: 'ws-swatches' }, swatches)]);
    swatches.find((s) => s.classList.contains('on'))?.focus() || swatches[0]?.focus();
  }

  // ---------- pin, lock, close ----------

  function setFlag(id, key, value) {
    const panel = panelOf(id);
    if (!panel) return;
    flags.set(id, { ...flagsOf(id), [key]: value });
    const f = flagsOf(id);
    panel.update({ params: { page: id, pinned: f.pinned, locked: f.locked } });
    if (key === 'pinned' && value) {
      // Pinned tabs sit before the unpinned ones in their group.
      const pinnedBefore = panel.group.panels.filter((p) => p.id !== id && flagsOf(p.id).pinned).length;
      try { panel.api.moveTo({ group: panel.group, index: pinnedBefore }); } catch { /* order is cosmetic */ }
    }
    tabs.get(id)?.refresh();
    scheduleSave();
    M.toast(t(value ? (key === 'pinned' ? 'ws.toast.pinned' : 'ws.toast.locked') : (key === 'pinned' ? 'ws.toast.unpinned' : 'ws.toast.unlocked'), { name: titleOf(id) }), { timeout: 2200 });
  }

  function closePage(id, quiet) {
    const panel = panelOf(id);
    if (!panel) return false;
    if (isSticky(id)) {
      if (!quiet) M.toast(t('ws.cannotClose', { name: titleOf(id) }), { kind: 'info', timeout: 3200 });
      return false;
    }
    panel.api.close();
    return true;
  }

  function splitPanel(id, direction) {
    const panel = panelOf(id);
    if (!panel || panel.group.panels.length < 2) return;
    panel.api.moveTo({ group: panel.group, position: direction === 'below' ? 'bottom' : 'right' });
  }

  // ---------- tab search ----------

  function searchItems() {
    return M.PAGE_ORDER.map((id) => {
      const panel = panelOf(id);
      const tg = panel ? tabGroupOf(panel) : null;
      const f = flagsOf(id);
      return { id, title: titleOf(id), open: !!panel, pinned: f.pinned, locked: f.locked, groupLabel: tg?.label || '' };
    });
  }

  function closeTabSearch() {
    if (!searchEl) return;
    const back = searchEl.returnTo;
    searchEl.remove();
    searchEl = null;
    if (back?.isConnected) back.focus?.();
  }

  function openTabSearch(returnTo) {
    closeTabSearch();
    let results = [];
    let index = 0;
    const input = h('input', { type: 'search', class: 'search-input', placeholder: t('ws.searchPlaceholder'), autocomplete: 'off', spellcheck: 'false', 'aria-label': t('ws.search'), role: 'combobox', 'aria-expanded': 'true', 'aria-controls': 'ws-search-list' });
    const list = h('ul', { class: 'ws-search-list', id: 'ws-search-list', role: 'listbox', 'aria-label': t('ws.search') });
    const status = h('p', { class: 'muted ws-search-status', 'aria-live': 'polite' });
    const choose = (item) => { closeTabSearch(); M.go(item.id); };
    const draw = () => {
      results = W.searchTabs(searchItems(), input.value);
      index = Math.min(index, Math.max(results.length - 1, 0));
      list.replaceChildren(...results.map((r, i) => h('li', {
        class: 'ws-search-item' + (i === index ? ' active' : ''), role: 'option', id: 'ws-opt-' + r.id, 'aria-selected': String(i === index), onclick: () => choose(r),
      }, M.icon(r.id, 18),
      h('span', { class: 'grow', text: r.title }),
      r.groupLabel ? h('span', { class: 'ws-badge', text: r.groupLabel }) : null,
      r.pinned ? h('span', { class: 'ws-badge', text: t('ws.pinned') }) : null,
      r.locked ? h('span', { class: 'ws-badge', text: t('ws.locked') }) : null,
      h('span', { class: 'ws-badge' + (r.open ? '' : ' closed'), text: r.open ? t('ws.open') : t('ws.reopen') }))));
      input.setAttribute('aria-activedescendant', results[index] ? 'ws-opt-' + results[index].id : '');
      status.textContent = results.length ? '' : t('ws.noMatch');
    };
    input.addEventListener('input', () => { index = 0; draw(); });
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closeTabSearch(); }
      else if (e.key === 'ArrowDown') { e.preventDefault(); index = Math.min(index + 1, results.length - 1); draw(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); index = Math.max(index - 1, 0); draw(); }
      else if (e.key === 'Enter' && results[index]) { e.preventDefault(); choose(results[index]); }
    });
    const card = h('div', { class: 'ws-search-card' },
      h('label', { class: 'search-field' }, M.icon('search', 18), input), list, status);
    const backdrop = h('div', { class: 'ws-search-backdrop' });
    backdrop.addEventListener('pointerdown', closeTabSearch);
    searchEl = h('div', { class: 'ws-search', role: 'dialog', 'aria-modal': 'true', 'aria-label': t('ws.search') }, backdrop, card);
    searchEl.returnTo = returnTo;
    document.body.append(searchEl);
    draw();
    input.focus();
  }

  // ---------- layout: default, save, restore, reset ----------

  function addPage(id, position) {
    return dock.addPanel({
      id, component: W.PAGE_COMPONENT, tabComponent: W.TAB_COMPONENT, title: titleOf(id),
      params: { page: id, pinned: flagsOf(id).pinned, locked: flagsOf(id).locked }, position,
    });
  }

  function buildDefault() {
    for (const id of M.PAGE_ORDER) addPage(id);
    const wanted = M.pages[state.settings.lastPage] ? state.settings.lastPage : 'server';
    panelOf(wanted)?.api.setActive();
  }

  function applySavedOrDefault() {
    restoring = true;
    try {
      const layout = W.chooseLayout(state.settings.workspace, M.PAGE_ORDER);
      let ok = false;
      if (layout) {
        try { dock.fromJSON(layout); ok = dock.panels.length > 0; } catch { ok = false; }
        if (!ok) { try { dock.clear(); } catch { /* start over below */ } }
      }
      if (!ok) { flags.clear(); buildDefault(); }
    } finally {
      restoring = false;
    }
  }

  function save() {
    if (!dock || restoring || narrow.active) return;
    const wrapped = W.wrapLayout(dock.toJSON());
    state.settings.workspace = wrapped;
    api.settings.set({ workspace: wrapped });
  }

  function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(save, 400);
  }

  async function resetLayout() {
    restoring = true;
    try {
      narrow = { active: false, snapshot: null };
      flags.clear();
      dock.clear();
      buildDefault();
    } finally {
      restoring = false;
    }
    state.settings.workspace = null;
    await api.settings.set({ workspace: null });
    syncActive();
    checkNarrow();
    M.toast(t('ws.resetDone'), { kind: 'ok', timeout: 2500 });
  }

  function openAll() {
    for (const id of M.PAGE_ORDER) if (!panelOf(id)) addPage(id);
    scheduleSave();
  }

  // ---------- narrow windows: one group below 900 px ----------

  function checkNarrow() {
    if (!dock) return;
    const isNarrow = W.isNarrow(window.innerWidth);
    if (isNarrow && !narrow.active) {
      narrow = { active: true, snapshot: dock.toJSON() };
      restoring = true;
      try {
        const target = (dock.activePanel || dock.panels[0])?.group;
        for (const p of [...dock.panels]) if (target && p.group !== target) p.api.moveTo({ group: target });
      } finally { restoring = false; }
      document.documentElement.classList.add('ws-narrow');
    } else if (!isNarrow && narrow.active) {
      const { snapshot } = narrow;
      const nowIds = dock.panels.map((p) => p.id);
      const activeId = dock.activePanel?.id;
      narrow = { active: false, snapshot: null };
      document.documentElement.classList.remove('ws-narrow');
      restoring = true;
      try {
        const checked = W.validateSavedLayout({ version: W.LAYOUT_VERSION, layout: snapshot }, M.PAGE_ORDER);
        if (checked.ok) {
          const before = Object.keys(snapshot.panels);
          dock.fromJSON(checked.layout);
          const diff = W.reconcileOpenPanels(before, nowIds);
          for (const id of diff.close) panelOf(id)?.api.close();
          for (const id of diff.add) if (!panelOf(id)) addPage(id);
          for (const p of dock.panels) {
            const f = flagsOf(p.id);
            p.update({ params: { page: p.id, pinned: f.pinned, locked: f.locked } });
          }
          if (activeId) panelOf(activeId)?.api.setActive();
        }
      } finally { restoring = false; }
      scheduleSave();
    }
  }

  // ---------- keyboard ----------

  function overlayOpen() {
    return !M.$('#onboarding').hidden || !M.$('#palette').hidden || !!searchEl;
  }

  function onKey(e) {
    if (!dock || overlayOpen()) return;
    const cmd = W.matchShortcut(e);
    if (!cmd) return;
    const panel = dock.activePanel;
    if (cmd === 'search') { e.preventDefault(); openTabSearch(document.activeElement); return; }
    if (!panel) return;
    e.preventDefault();
    if (cmd === 'nextTab' || cmd === 'prevTab') {
      const list = panel.group.panels;
      const next = list[W.cycleIndex(list.findIndex((p) => p.id === panel.id), list.length, cmd === 'nextTab' ? 1 : -1)];
      next?.api.setActive();
    } else if (cmd.startsWith('group')) {
      const dir = cmd.slice(5).toLowerCase();
      const target = dock.adjacentGroupInDirection(panel.group, dir);
      const next = target?.activePanel || target?.panels?.[0];
      if (next) { next.api.setActive(); next.focus?.(); }
    } else if (cmd === 'close') closePage(panel.id);
    else if (cmd === 'pin') setFlag(panel.id, 'pinned', !flagsOf(panel.id).pinned);
    else if (cmd === 'lock') setFlag(panel.id, 'locked', !flagsOf(panel.id).locked);
  }

  // ---------- sync with the app ----------

  function syncActive() {
    const id = dock?.activePanel?.id;
    if (!id || restoring || id === state.page) { M.renderRail(); return; }
    state.page = id;
    api.settings.set({ lastPage: id });
    M.renderRail();
  }

  function mount() {
    const host = M.$('#page-host');
    const root = h('div', { class: 'ws-root' });
    host.replaceChildren(root);
    dock = DV.createDockview(root, {
      theme: { name: 'material', className: 'dockview-theme-material', gap: 8, tabAnimation: 'default' },
      dndStrategy: 'html5',
      disableFloatingGroups: true,
      singleTabMode: 'default',
      createComponent: () => new PageContent(),
      createTabComponent: () => new PageTab(),
      createRightHeaderActionComponent: () => new RightActions(),
      createWatermarkComponent: () => new Watermark(),
      noPanelsOverlay: 'watermark',
    });
    dock.onDidActivePanelChange(() => syncActive());
    dock.onDidLayoutChange(() => scheduleSave());
    dock.onDidRemovePanel(() => { if (!restoring) syncActive(); });
    document.addEventListener('keydown', onKey, true);
    let resizeTimer = 0;
    window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(checkNarrow, 120); });
    applySavedOrDefault();
    syncActive();
    checkNarrow();
  }

  M.workspace = {
    mount,
    get api() { return dock; },
    renderAll(animate) {
      for (const id of contents.keys()) {
        renderPageInto(id, !!animate && id === state.page);
        panelOf(id)?.api.setTitle(titleOf(id));
        tabs.get(id)?.refresh();
      }
    },
    /** Open (or reopen) a page's tab and make it the active one. */
    show(id) {
      if (!dock || !M.pages[id]) return;
      let panel = panelOf(id);
      if (!panel) panel = addPage(id);
      const wasActive = dock.activePanel?.id === id;
      panel.api.setActive();
      state.page = id;
      if (wasActive) renderPageInto(id, false);
    },
    isOpen: (id) => !!panelOf(id),
    isVisible: (id) => !!contents.get(id)?.element.isConnected,
    resetLayout,
    openTabSearch,
    save,
    flags: flagsOf,
  };
})();
