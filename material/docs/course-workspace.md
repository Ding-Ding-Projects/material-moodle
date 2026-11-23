# Course workspace

On course pages at 1200 pixels and wider, the course index, the course itself and the course's blocks become panels in
a workspace built on [dockview](https://dockview.dev/) (`dockview-core` 8.9.1, MIT, vendored in
`public/theme/material/amd/src/vendor-dockview.js`). The panels are Moodle's own page elements moved into the
workspace, so the course page's scripts keep working. dockview is added underneath; the look comes from the design
canvas and the theme's colour roles.

## What people can do

- Drag tabs to split, group and rearrange panels.
- Close a tab with its **Close** button (it carries a text label as well as an icon). Pinned tabs cannot be closed.
- **Search tabs** lists every panel, including closed ones, and reopens a closed panel with Enter.
- The tab menu (right-click, or Shift+F10 on a tab) offers Pin, Lock group, Move to new group, Close, Reset layout,
  rename and colour the group, each with its shortcut shown (Ctrl+Alt+P, L, N, W, R and F).
- Ctrl+Alt+Arrow moves between groups; Ctrl+Tab or Ctrl+Alt+PageUp/PageDown moves between tabs.
- **Layout > Reset layout** returns to the default (course index on the left, course in the middle, blocks on the
  right). **Layout > Classic layout** puts Moodle's normal page back, and a bar above the course switches the
  workspace on again.
- Below 1200 pixels the classic page is used, so phones are unaffected.

## Saved layouts

Each person's layout is stored as their own Moodle user preferences: `theme_material_workspace` (`workspace` or
`classic`) and `theme_material_workspace_layout` (the layout JSON, up to 20,000 characters). Only the person themself
can change them. A saved layout that cannot be loaded falls back to the default.

## Limits

- Floating and pop-out groups are off, because Moodle's page elements cannot move into a separate window.
- Pinning is the theme's own state (dockview's free package has no pinned tabs); it blocks closing but does not reorder.
- Browsers usually keep Ctrl+Tab for themselves, so Ctrl+Alt+PageUp/PageDown is the reliable tab route.

## How to verify

`material/tests/e2e/course-workspace.mjs` checks, in Chromium and WebKit: the default panels, that a closed tab stays
closed after a reload, that Search tabs reopens it, Classic layout and back, that no workspace appears below 1200
pixels, and that the page reports no errors. It passed on 23 November 2026 on a Docker test site.
