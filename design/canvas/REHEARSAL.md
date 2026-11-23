# Redesign canvas: rehearsal and review record

Canvas: 32 boards in `project/` (29 pages plus the shared `Rail`, `TopBar` and `PhoneNav` components), indexed by
`project/canvas.json` into seven category pages with a note beside each board. Spec: `DESIGN-SPEC.md`. Review rules:
`FIX-BRIEF.md`.

## Method

- Design guidance: the Stageshow skill at upstream commit `299140fafd08df55a9c234e610c3f64af3f45531`, installed fresh
  for this pass; it matched the cached copy file for file. `SKILL.md`, `references/review.md`, `references/verify.md`
  and `references/craft.md` were read.
- No gold example was copied. Stageshow's gold examples are marketing illustrations, and these boards are real
  application screens. Its rules that fit were applied: restraint, hierarchy, spacing and alignment, no dead areas,
  nothing cut at an edge without a fade, icon quality, contrast and one consistent shell. Its cursor, film-loop,
  poster-frame and marketing-framing rules do not apply to static application screens and were not used.
- Frames: every board rendered locally with the canvas runtime in headless Chromium at its own size (desktop
  1440×900, phone 390×844, components at their own sizes). Frames are working files and are not kept in the repository.

## Review rounds

1. First fresh-eye review (a reviewer that saw only the frames, the spec and the review guide): 40 findings. The
   largest was shell drift between boards. Fix: the shared `Rail`, `TopBar` and `PhoneNav` components, imported by
   every board, plus per-board fixes for clipped text, cut content, dead areas, contrast, uneven pills and switches.
2. Second fresh-eye review: 18 findings, 17 boards clean. Fixes: fixed rail item heights so spacing is identical on
   every board, fades and padding where content met an edge, visible illustration tones, filled dead areas, one phone
   top-bar treatment (title, search, overflow; back on detail pages), a working search state on every phone board, and
   course progress figures computed from the board's own data.
3. Final render: all 32 boards render with no page errors.

## Not applicable or not checked

- The phone launcher's overflow button sits behind the launcher sheet while that sheet is open, as expected for a
  modal sheet.
- Search fields on boards show the search state and the regex switch; they do not filter page results, except where a
  board's interaction says so (launcher, all features, administration, messages).
- Boards are design references. They are not the implemented theme; see `material/ROADMAP.md` for what is built.

## Tweaks on every board

`accent` (colour with four presets), `dark` (boolean) and `motion` (boolean). The shared components also take `active`
(and `title`, `initials`, `height` where they apply).
