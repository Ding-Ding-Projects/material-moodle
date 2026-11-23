# Material Moodle redesign: shared design spec

Every board in `design/canvas/project/` follows this spec so the pages read as one product.
Direction: **Material 3 Expressive, 2026.** Confident display type, big soft shapes, tonal surfaces instead of
borders and shadows, a navigation rail, a floating pill search, bento layouts, and springy but short motion.
No gradient washes, no left-border cards, no emoji, no drop-shadow stacks.

## Colour roles (light / dark)

| Role | Light | Dark |
| --- | --- | --- |
| primary | `#3D5BD9` | `#B8C3FF` |
| onPrimary | `#FFFFFF` | `#002388` |
| primaryContainer | `#DDE1FF` | `#2440BF` |
| onPrimaryContainer | `#001454` | `#DDE1FF` |
| secondaryContainer | `#DEE1F9` | `#42455A` |
| onSecondaryContainer | `#171B2C` | `#DEE1F9` |
| tertiary | `#A23F6A` | `#FFB0CB` |
| tertiaryContainer | `#FFD9E3` | `#842653` |
| onTertiaryContainer | `#3E0021` | `#FFD9E3` |
| success | `#1F7A4D` | `#7CDBA6` |
| successContainer | `#B7F1CF` | `#005234` |
| warningContainer | `#FFE08A` | `#5B4300` |
| error | `#BA1A1A` | `#FFB4AB` |
| errorContainer | `#FFDAD6` | `#93000A` |
| surface (page) | `#FBF8FF` | `#131318` |
| surfaceContainerLow | `#F5F2FB` | `#1B1B21` |
| surfaceContainer | `#EFEDF6` | `#1F1F25` |
| surfaceContainerHigh | `#E9E7F0` | `#2A292F` |
| surfaceContainerHighest | `#E3E1EA` | `#34343A` |
| onSurface | `#1B1B21` | `#E4E1E9` |
| onSurfaceVariant | `#45464F` | `#C6C5D0` |
| outline | `#767680` | `#90909A` |
| outlineVariant | `#C6C5D0` | `#45464F` |

Text on every fill uses its `on…` partner. Body text never goes below 4.5:1.

## Type

Load in `<helmet>`:
`<link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wdth,wght@8..144,25..151,100..1000&family=Roboto+Mono:wght@400;600&display=swap" rel="stylesheet">`

| Style | Settings |
| --- | --- |
| Display | Roboto Flex, `font-weight:800; font-stretch:118%; font-size:56px; line-height:1.02; letter-spacing:-0.02em` |
| Headline | Roboto Flex, `font-weight:750; font-stretch:112%; font-size:32px; line-height:1.1` |
| Title | `font-weight:650; font-size:20px; line-height:1.3` |
| Body | `font-weight:400; font-size:16px; line-height:1.55` |
| Label | `font-weight:600; font-size:14px; letter-spacing:.01em` |
| Overline | `font-weight:700; font-size:12px; letter-spacing:.08em; text-transform:uppercase` |
| Mono | Roboto Mono for codes, times and paths |

Phone boards scale Display to 40px and Headline to 26px.

## Shape and spacing

- Corners: chips 10px, fields 16px, buttons fully round (999px), cards 28px, hero panels 36px, sheets 32px top.
- Spacing scale: 4, 8, 12, 16, 24, 32, 48, 64. Page gutter 32px desktop, 16px phone.
- Touch targets at least 48px tall. Icon buttons 48px circles.
- Surfaces separate by tone, not borders. An outline (1px outlineVariant) only on inputs and on secondary cards.
- One shadow only, for floating things (menus, search, FAB): `0 8px 28px rgba(27,27,33,.14)`.

## App shell (desktop, 1440 wide)

Every signed-in desktop board uses the same shell:

1. **Navigation rail**, left, 96px wide, full height, background surfaceContainerLow. Top: a 48px rounded-square
   logo tile (primary fill, white mortarboard icon). Then a 56px **FAB-style "New" button**
   (primaryContainer, 16px corners). Then destinations stacked with 12px gap, each an icon in a 56×32 pill
   (active pill = secondaryContainer) with a 12px label under it: **Home, Courses, Calendar, Grades, Messages,
   Everything**. Bottom: Settings icon and the account avatar (40px circle, tertiaryContainer, initials).
2. **Top bar**, 72px tall, no background (sits on surface): page title on the left in Title style, a
   **floating pill search** in the centre (max 560px, 56px tall, surfaceContainerHigh, search icon, placeholder
   "Search courses, people and settings", a `Ctrl K` keycap on the right), and on the right three 48px icon
   buttons: display settings (sliders), notifications (bell with a small error-coloured dot) and help.
3. **Content** fills the rest with a 32px gutter and a `max-width:1180px` column.

Phone boards (390×844): a 64px top bar (title + avatar), content, and a **bottom navigation bar** 80px tall,
surfaceContainer, 5 destinations (Home, Courses, Calendar, Messages, Everything) with the active one in a
64×32 secondaryContainer pill. No rail on phones.

## Components (recipes)

- **Filled button**: primary fill, onPrimary text, 48px tall, 0 24px padding, 999px radius, Label type.
- **Tonal button**: secondaryContainer fill, onSecondaryContainer text, same size.
- **Outlined button**: transparent, 1px outline, onSurface text.
- **Text field**: 56px tall, 16px radius, surfaceContainerHighest fill, no border, label above in Label type,
  2px primary bottom indicator only when focused. Leading icon sits in its own 24px slot with 16px padding so it
  never overlaps text.
- **Chip**: 36px tall, 10px radius, 1px outline; selected = secondaryContainer fill, no outline, check icon.
- **Segmented button**: 48px tall full-round group, 1px outline between segments, selected segment
  secondaryContainer with a check.
- **Card**: surfaceContainerLow, 28px radius, 24px padding. Course cards have a 140px tall image area that
  uses a flat tonal colour block with a large simple geometric shape (circle or rounded square) in a second
  tone; never a stock photo.
- **Progress**: linear 8px tall full-round track (surfaceContainerHighest) with primary indicator; circular
  rings 4–6px stroke.
- **List row**: 72px tall, 16px padding, leading 40px tonal icon tile (12px radius), title + supporting text,
  trailing meta in Label onSurfaceVariant.
- **Badge/status pill**: 24px tall, 8px radius, container colour + on-colour text (Due soon = warningContainer,
  Submitted = successContainer, Overdue = errorContainer).
- **Icons**: inline stroke SVG, 24 grid, `stroke-width:1.9`, round caps and joins, `aria-hidden="true"`.

## Sample data (use these, consistently)

- Learner: **Mei Chan** (initials MC). Admin boards: **Admin User** (AU). Site name: **Riverside College**.
- Courses: **Introduction to Biology** (BIO101, 68% complete), **World History** (HIS120, 42%), **Creative Writing**
  (ENG210, 85%), **Statistics with Python** (STA230, 15%).
- Upcoming: "Cell structure lab report" due Tue 14:00 (BIO101), "Essay draft: the Silk Road" due Thu 23:59
  (HIS120), "Quiz 3: probability" Fri 10:00 (STA230).
- Times always 24-hour.

## Board rules

- Fixed size boards: desktop 1440×900, phone 390×844. Root `<div>` fixed at that size, `overflow:hidden`.
- Every board declares the same three Tweaks in `data-props`:
  `{"accent":{"editor":"color","default":"#3D5BD9","options":["#3D5BD9","#A23F6A","#1F7A4D","#8B5000"],"section":"Theme"},"dark":{"editor":"boolean","default":false,"section":"Theme"},"motion":{"editor":"boolean","default":true,"section":"Theme"}}`
  plus `$preview`. `accent` replaces **primary** (light) everywhere it is used; dark mode uses the dark table.
  Put the two palettes in `renderVals()` and bind colours as `{{c.primary}}` etc.
- Motion: one helmet keyframe for entrance (`opacity 0→1, translateY 12px→0, 420ms cubic-bezier(.05,.7,.1,1)`),
  staggered by `animation-delay` on cards; a root class `mm-still` turns all animation off when `motion` is false.
- Real `<button>`, `<a href>`, `<input>` with `<label>`; icon-only buttons have `aria-label`.
- At least one working interaction per board (tabs switch, filter chips filter, a toggle toggles), with
  `is_interactive` set on the canvas.
