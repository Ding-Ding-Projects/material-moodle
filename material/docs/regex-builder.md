# Regex builder on search boxes

**Status:** Available in the command palette. On the page's own search boxes: **In progress**.
Plugin: `theme_material`, modules `theme_material/palette` and `theme_material/searchbars`.

## In the command palette (available)

The [command palette](command-palette.md) has a **Regex builder** section under its search field:

- **Regex** switch: when on, the text is a JavaScript regular expression; when off, it is a plain substring search.
- **Ignore case** switch (on by default).
- **Clear** empties the field.
- **Pattern blocks**, each a button that appends a piece of pattern: Starts with `^`, Ends with `$`, Anything
  `.*`, Digit `\d`, Letter `[A-Za-z]`, Word edge `\b`, Space `\s`, One or more `+`, Optional `?`, Either `|`,
  Group start `(` and Group end `)`.
- **Tokens**: while Regex is on, the current pattern is split into removable tokens. Selecting a token removes that
  piece of the pattern.
- A live status shows the match count, or "Pattern is incomplete" while the pattern does not compile. An invalid
  pattern lists no results instead of failing.

Both switches are remembered in the browser. The block buttons are hidden when Regex is off or when the
**Regex builder** setting is off.

## On search boxes (in progress)

`amd/src/searchbars.js` attaches a small `.*` button beside every search field on a page (`input[type=search]`,
Moodle's simple search forms, and fields named `search`, `q` or `query`). It opens a pop-over with the same Regex
and Ignore case switches and the blocks above. In regex mode the visible lists and tables in the main region
(table rows, list items, activities, course cards, forum posts) are filtered live, and pressing Enter still runs
Moodle's own search with the text as typed. New search boxes added later are picked up automatically.

This module is written but **not active yet**: it is not loaded by `theme_material/main`, it has no `amd/build`
output, and the setting description already mentions the page filter boxes. Remaining work is listed in
[`ROADMAP.md`](../ROADMAP.md).

## Settings

| Setting | Name | Default |
| --- | --- | --- |
| Regex builder | `theme_material | regexbuilder` | on |

## Limits

- The builder is a small set of blocks, not a full regular expression workbench: there is no replacement, no
  explanation of a pattern, no flavour conversion and no history.
- Patterns are JavaScript regular expressions evaluated in the browser against short labels, so a pathological
  pattern can only slow the person's own tab.

## How to verify

1. Open the palette, turn Regex on, select **Starts with** and type a course's first letters: only courses starting
   with them remain.
2. Type `(` alone: the status reads "Pattern is incomplete" and no results are shown.
3. Select a token: that part of the pattern is removed.

## Related articles

- [Command palette](command-palette.md)
- [Right-click menu](right-click-menu.md)
