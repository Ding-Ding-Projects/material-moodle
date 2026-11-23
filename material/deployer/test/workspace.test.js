'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const W = require('../renderer/workspace-core');
const { extractCss } = require('../scripts/sync-vendor');

const IDS = ['server', 'tunnel', 'logs', 'settings', 'about'];
const panel = (id) => ({ id, contentComponent: 'page', tabComponent: 'tab', title: id, params: { page: id } });
const leaf = (views, active = views[0]) => ({ type: 'leaf', size: 400, data: { id: views.join('-'), views, activeView: active } });
function layout(groups, extra = {}) {
  const ids = groups.flat();
  return {
    version: W.LAYOUT_VERSION,
    savedAt: 1,
    layout: {
      grid: { root: { type: 'branch', size: 600, data: groups.map((g) => leaf(g)) }, width: 1000, height: 600, orientation: 'HORIZONTAL' },
      panels: Object.fromEntries(ids.map((id) => [id, panel(id)])),
      ...extra,
    },
  };
}

test('a good saved layout is restored, including one with closed tabs', () => {
  assert.notEqual(W.chooseLayout(layout([IDS]), IDS), null);
  assert.notEqual(W.chooseLayout(layout([['server', 'logs'], ['settings']]), IDS), null);
});

test('every kind of bad saved value falls back to the default layout', () => {
  const bad = [
    undefined, null, 'text', 42, [], {},
    { ...layout([IDS]), version: 99 },
    { version: 1, layout: null },
    { version: 1, layout: { grid: {}, panels: {} } },
    layout([['server', 'unknown-page']]),
    layout([IDS], { floatingGroups: [{}] }),
    layout([IDS], { popoutGroups: [{}] }),
  ];
  const wrongComponent = layout([IDS]);
  wrongComponent.layout.panels.server.contentComponent = 'other';
  bad.push(wrongComponent);
  const duplicate = layout([['server'], ['server']]);
  duplicate.layout.panels = { server: panel('server') };
  bad.push(duplicate);
  const mismatch = layout([['server', 'tunnel']]);
  delete mismatch.layout.panels.tunnel;
  bad.push(mismatch);
  const noGrid = layout([IDS]);
  noGrid.layout.grid.orientation = 'SIDEWAYS';
  bad.push(noGrid);
  const badActive = layout([['server', 'tunnel']]);
  badActive.layout.grid.root.data[0].data.activeView = 'logs';
  bad.push(badActive);
  for (const value of bad) assert.equal(W.chooseLayout(value, IDS), null, String(JSON.stringify(value)).slice(0, 80));
});

test('a deeply nested hostile grid is refused instead of recursing forever', () => {
  let node = leaf(['server']);
  for (let i = 0; i < 100; i++) node = { type: 'branch', size: 1, data: [node] };
  const value = layout([['server']]);
  value.layout.grid.root = node;
  assert.deepEqual(W.validateSavedLayout(value, IDS), { ok: false, reason: 'grid' });
});

test('wrapLayout output round-trips through validation', () => {
  const inner = layout([IDS]).layout;
  assert.equal(W.chooseLayout(W.wrapLayout(inner, 5), IDS), inner);
});

const ITEMS = [
  { id: 'server', title: 'Server', open: true },
  { id: 'tunnel', title: 'Tunnel', open: false },
  { id: 'logs', title: 'Logs', open: true, groupLabel: 'Ops' },
  { id: 'settings', title: 'Settings', open: false, pinned: true },
  { id: 'about', title: 'About', open: true },
];

test('tab search lists open tabs first for an empty query and finds closed tabs too', () => {
  assert.deepEqual(W.searchTabs(ITEMS, '').map((i) => i.id), ['server', 'logs', 'about', 'tunnel', 'settings']);
  assert.deepEqual(W.searchTabs(ITEMS, 'tun').map((i) => i.id), ['tunnel']);
  assert.equal(W.searchTabs(ITEMS, 'tunnel')[0].open, false);
});

test('tab search ranks prefix before substring before in-order letters, and matches group names', () => {
  assert.deepEqual(W.searchTabs(ITEMS, 'se').map((i) => i.id).slice(0, 2), ['server', 'settings']);
  assert.equal(W.searchTabs(ITEMS, 'stg')[0].id, 'settings');
  assert.deepEqual(W.searchTabs(ITEMS, 'ops').map((i) => i.id), ['logs']);
  assert.deepEqual(W.searchTabs(ITEMS, 'zzzz'), []);
  assert.deepEqual(W.searchTabs(ITEMS, '  LOGS ').map((i) => i.id), ['logs']);
});

test('narrow windows are those under 900 px', () => {
  assert.equal(W.isNarrow(899), true);
  assert.equal(W.isNarrow(900), false);
  assert.equal(W.isNarrow(0), false);
  assert.deepEqual(W.reconcileOpenPanels(['server', 'logs'], ['logs', 'about']), { close: ['server'], add: ['about'] });
});

test('a pinned or locked tab cannot be closed, and a single tab cannot be split', () => {
  const find = (m, id) => m.find((i) => i.id === id);
  const base = { pinned: false, locked: false, otherClosableCount: 2, inTabGroup: false, panelsInGroup: 3 };
  assert.equal(find(W.tabMenuModel(base), 'close').disabled, false);
  assert.equal(find(W.tabMenuModel({ ...base, pinned: true }), 'close').disabled, true);
  assert.equal(find(W.tabMenuModel({ ...base, locked: true }), 'close').disabled, true);
  assert.equal(find(W.tabMenuModel({ ...base, panelsInGroup: 1 }), 'splitRight').disabled, true);
  assert.equal(find(W.tabMenuModel({ ...base, pinned: true }), 'pin').labelKey, 'ws.menu.unpin');
  assert.ok(find(W.tabMenuModel({ ...base, inTabGroup: true }), 'renameGroup'));
  assert.equal(find(W.tabMenuModel(base), 'renameGroup'), undefined);
  assert.equal(find(W.tabMenuModel(base), 'close').shortcut, 'Ctrl+W');
});

test('keyboard routes map to commands', () => {
  const k = (key, mods = {}) => W.matchShortcut({ key, ...mods });
  assert.equal(k('Tab', { ctrlKey: true }), 'nextTab');
  assert.equal(k('Tab', { ctrlKey: true, shiftKey: true }), 'prevTab');
  assert.equal(k('ArrowLeft', { ctrlKey: true, altKey: true }), 'groupLeft');
  assert.equal(k('ArrowDown', { ctrlKey: true, altKey: true }), 'groupDown');
  assert.equal(k('w', { ctrlKey: true }), 'close');
  assert.equal(k('p', { ctrlKey: true, altKey: true }), 'pin');
  assert.equal(k('f', { ctrlKey: true, altKey: true }), 'search');
  assert.equal(k('f', { ctrlKey: true, shiftKey: true }), null);
  assert.equal(k('ArrowLeft', { altKey: true }), null);
  assert.equal(W.cycleIndex(0, 3, -1), 2);
  assert.equal(W.cycleIndex(2, 3, 1), 0);
  assert.equal(W.cycleIndex(0, 0, 1), -1);
});

test('group names are cleaned and limited', () => {
  assert.equal(W.cleanGroupLabel('  Ops \n team  '), 'Ops team');
  assert.equal(W.cleanGroupLabel('x'.repeat(100)).length, W.MAX_GROUP_LABEL);
  assert.equal(W.cleanGroupLabel(null), '');
});

test('the dockview stylesheet is extracted from the package bundle and the vendored files exist', () => {
  const css = extractCss('const s = 1; s.textContent = ".dv-dockview { color: red; }\\n";');
  assert.match(css, /\.dv-dockview/);
  assert.throws(() => extractCss('nothing here'));
  const vendor = path.join(__dirname, '..', 'renderer', 'vendor');
  for (const f of ['dockview-core.js', 'dockview.css', 'LICENSE-dockview.txt']) assert.ok(fs.existsSync(path.join(vendor, f)), f);
  const pkg = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'package.json'), 'utf8'));
  assert.match(pkg.dependencies['dockview-core'], /^\d+\.\d+\.\d+$/, 'pinned to an exact version');
});
