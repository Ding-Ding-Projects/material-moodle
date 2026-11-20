# Copy buttons

**Status:** Available. Plugins: `theme_material` (module `theme_material/copy`) and `local_materialtools`
(module `local_materialtools/copy`, used on the credentials page).

## What it does

- Every `<pre>` block on a page gets a **Copy** button in its top corner. Selecting it copies the block's text
  (without a trailing newline) and the button reads **Copied** for 1.5 seconds.
- Any element with `data-md-copy` becomes a copy button. `data-md-copy="some-id"` copies the text (or a text area's
  value) of the element with that id; an empty `data-md-copy` with `data-md-copy-text="…"` copies that text.
  `data-md-copied-label` overrides the confirmation text.
- Code blocks added to the page later (for example in a forum reply loaded by JavaScript) are decorated
  automatically.
- A block marked `data-md-no-copy` is left alone, as are blocks inside the command palette.

Copying uses the Clipboard API on secure (HTTPS or localhost) pages and falls back to a hidden text area and
`document.execCommand('copy')` elsewhere.

## Settings

| Setting | Name | Default |
| --- | --- | --- |
| Copy buttons on code | `theme_material | copybuttons` | on |

## When something goes wrong

If the browser refuses clipboard access the button simply returns to **Copy**; nothing else changes. On plain HTTP
sites served from another computer, some browsers block both methods.

## How to verify

Post a forum message with a preformatted block, open it, select **Copy** and paste somewhere else.

## Related articles

- [Add a student and Discord credentials](add-a-student.md)
- [Right-click menu](right-click-menu.md)
