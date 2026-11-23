# Review fixes: shared rules for every board

A fresh-eye review of the rendered boards found drift and layout faults. Apply these rules to every board you own,
then the numbered findings listed for your boards.

## Use the shared shell components

Delete each board's hand-built rail, top bar and phone bottom navigation and import the shared components instead.
They live in the same folder: `Rail.dc.html` (96×900), `TopBar.dc.html` (1344×72) and `PhoneNav.dc.html` (390×80).
Read them first. Mount them like this (attributes become props; booleans and colours pass through holes):

```html
<dc-import name="Rail" active="calendar" initials="MC" accent="{{accent}}" dark="{{dark}}" hint-size="96px,900px"></dc-import>
<dc-import name="TopBar" title="Calendar" accent="{{accent}}" dark="{{dark}}" hint-size="1344px,72px"></dc-import>
<dc-import name="PhoneNav" active="calendar" accent="{{accent}}" dark="{{dark}}" hint-size="390px,80px"></dc-import>
```

Return `accent` and `dark` from renderVals() so the Tweaks flow into the components. Rail `active` values: home,
courses, calendar, grades, messages, everything, settings, profile, none. PhoneNav `active`: home, courses, calendar,
messages, everything, none. Admin boards pass `initials="AU"`.

## Desktop geometry (1440×900)

- Rail at x 0–96, full height. Top bar at x 96–1440, y 0–72. Do not draw a page title in the content if it only
  repeats the top bar title; use the content heading for something more specific, or drop it.
- Content column: x 128 to 1408 (32px gutters), content starts at y 96 (24px below the bar).
- Nothing may be cut by the frame bottom. Fit the content inside 900px, or end the visible area on a whole row with a
  48px fade to the surface colour at the bottom of the scrolling region. No dead areas: a pane that ends far above its
  neighbour gets its content balanced, or the layout changes so columns end together.

## Phone geometry (390×844)

- Standard phone top bar, 64px tall, 16px side padding: page title in Title style on the left; on the right at most two
  48px icon buttons (overflow menu last) then nothing else. Put the avatar in the overflow menu sheet, not the bar.
  Detail pages (course, assignment) use a 48px back button before the title instead.
- PhoneNav at y 764–844. Content sits between 64 and 764 and scrolls; nothing is cut by the nav (leave 16px space above
  it, and any sticky action bar sits directly above the nav).
- Horizontally scrolling chip rows get a 32px fade on the right edge so a cut chip reads as scrollable.
- Every interactive target at least 48px tall.

## Everywhere

- One regex toggle per search, drawn as a 40px round icon button inside the right end of the field, labelled
  "Use a regular expression", pressed state = primary.
- Status pills have a fixed minimum width (72px) and centred text so a column of them lines up.
- Off switches: track surfaceContainerHighest with a 2px outline border, thumb in outline colour; on = primary track,
  onPrimary thumb.
- Text on coloured fills uses that fill's on-colour, 4.5:1 or better. Never put text over decorative shapes.
- Keep every Tweak (accent, dark, motion) and every interaction already on the board working.
