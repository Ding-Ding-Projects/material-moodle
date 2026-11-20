# Right-click menu

**Status:** In progress. Plugin: `theme_material`, module `theme_material/contextmenu`.

## Intended behaviour

Right-clicking anywhere on a page opens a Material menu with actions for what was clicked, each with its working
keyboard shortcut shown beside it. **Shift + right-click** always opens the browser's own menu, and the browser
menu is kept in text fields, text areas, selects, editable content, the text editors and anything marked
`data-md-native-menu`.

| Clicked on | Actions |
| --- | --- |
| Selected text | Copy (Ctrl+C), Search for "…" (Ctrl+Shift+F, opens the command palette) |
| A link | Open (Enter), Open in new tab (Ctrl+Click), Copy link address |
| An image | Open image in new tab, Copy image address |
| Code (`pre` or `code`) | Copy code |
| Anywhere | Search (Ctrl+Shift+F), Back (Alt+Left, disabled without history), Reload (F5), Copy page address, Print (Ctrl+P), Back to top (Home) |

The menu is a `role="menu"` of `menuitem` buttons, kept inside the window, focused on open; Up and Down move,
Escape or Tab close it and return focus. It closes on an outside click, on scroll and when the window loses focus.

## What is missing

- The module is not loaded by `theme_material/main` and has no `amd/build` output.
- Its labels (`ctxcopy`, `ctxsearchfor`, `ctxopen`, `ctxopentab`, `ctxcopylink`, `ctxopenimage`, `ctxcopyimage`,
  `ctxcopycode`, `ctxback`, `ctxreload`, `ctxcopypage`, `ctxprint`, `ctxtop`, `ctxmenu`) are not yet in the
  language file or passed from the hook.
- "Search for …" calls `Palette.open(selection)`, but the palette's `open()` does not yet accept a starting query.
- There is no setting to turn it off, and no touch or keyboard equivalent (for example Shift+F10) yet.
- Shortcut labels are written by hand rather than derived from the code that handles the keys.

## Related articles

- [Command palette](command-palette.md)
- [Copy buttons](copy-buttons.md)
