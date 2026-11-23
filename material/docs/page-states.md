# Page states: empty, done, not found and offline

Every page can end up in one of four states. The theme styles each one to match the design board
`design/canvas/project/States.dc.html`, using only the generated colour roles, so the seed colour and dark mode
both apply.

| State | Where it comes from | What it looks like |
| --- | --- | --- |
| Empty | Moodle's own empty messages (for example "You're not enrolled in any courses" or an empty timeline) | A rounded card on the low surface colour with Moodle's illustration |
| Done | Moodle's success messages and pop-up notifications | Success-coloured message boxes and dark snackbars |
| Not found and errors | Moodle's "404 Error: File not found" page and its error box | A state card with a coloured band and shapes, the message and a full-round Continue button |
| Offline | The browser losing its connection (`theme/material/amd/src/states.js`) | A card at the bottom of the window with Try again; "You are back online." when the connection returns |

## Offline

Moodle cannot save anything while the browser is offline, so the offline card says that plainly instead of promising
to sync later. **Try again** asks the server for the current page; only a real answer closes the card. When the
browser reports that the connection is back, the card closes on its own. The card never covers the whole page, so
nothing typed into a form is lost while waiting.

Motion follows the person's motion setting and the browser's reduced-motion preference.

## How to verify

- `material/tests/e2e/states.mjs` checks the not found page, an error box, the offline card (including a failed Try
  again) and the back-online note, in light and dark, at 1440 and 390 pixels.
- `material/tests/e2e/dark-mode.mjs` measures the text contrast of every visible element on eleven main pages in dark
  mode and fails below 3:1.

Both passed on 23 November 2026 on a Docker test site.
