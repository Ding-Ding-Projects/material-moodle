# Material Moodle Deployer

Desktop app (Electron) that deploys the Material Moodle stack to any SSH server and publishes it through a
Cloudflare tunnel. Run it with `npm start`, test with `npm test`, package with `npm run dist`.

## Workspace: movable tabs, splits and docking

The five pages (Server, Tunnel, Logs, Settings, About) are panels in a [dockview](https://dockview.dev/)
workspace (`dockview-core`, MIT, pinned to an exact version). The rail on the left still works as before and
opens or focuses the matching tab. Each panel mounts the same page the rail always showed, so the pages look
and behave as they did; the tab strip and the splitters are the addition.

- **Tabs and splits.** Drag a tab to another group or to an edge to split. The default layout is one group
  holding all five tabs.
- **Close.** Every tab has a Close button with a text label as well as an icon (Ctrl+W closes the active tab).
- **Search tabs.** The Tabs button in the tab bar (Ctrl+Alt+F) lists every tab, open or closed. Choosing a
  closed one reopens it, so closing a tab never loses it. The Layout menu also has Open every tab.
- **Tab groups.** Right-click a tab and choose Add to new tab group. Groups have a name and a colour (the
  context menu, or the chip's own menu, renames and recolours them).
- **Pinned and locked tabs.** A pinned tab moves to the front of its group and cannot be closed. A locked tab
  cannot be closed or dragged. Both are shown on the tab.
- **Context menu.** Right-click a tab for Close, Close other tabs, Pin, Lock, group actions, Split right or
  below, Search tabs and Reset layout, with their shortcuts shown.
- **Keyboard.** Ctrl+Tab and Ctrl+Shift+Tab move between tabs in a group, Ctrl+Alt+Arrow moves between groups,
  Ctrl+Alt+P pins, Ctrl+Alt+L locks.
- **Saved layout.** The layout is saved to the app settings (`workspace` in `settings.json`) and restored at
  start. A missing, corrupt or unrecognised saved layout falls back to the default layout. Layout, then
  Reset layout, restores the default at any time.
- **Narrow windows.** Below 900 px wide all tabs collapse into one group; the wide layout comes back when the
  window widens. The collapsed state is not saved.
- **Look.** The dockview colour variables are taken from the app's own colour variables in light and dark,
  tabs are 48 px tall, and motion stops when reduced motion is requested or Motion is Off.

Files: `renderer/js/workspace.js` (dockview wiring), `renderer/workspace-core.js` (pure rules, unit tested in
`test/workspace.test.js`), `renderer/workspace.css` (theme). The dockview build and stylesheet are copied from
`node_modules` into `renderer/vendor/` by `npm run sync-vendor` (the window's Content Security Policy allows
only local files, so there is no CDN and no injected style element). Run it again after changing the dockview
version.
