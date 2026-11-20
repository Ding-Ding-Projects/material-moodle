# Command palette

**Status:** Available. Plugin: `theme_material`, module `theme_material/palette`.

## What it does

Press **Ctrl+Shift+F** (Cmd+Shift+F on a Mac), or select the **Search** button the theme adds to the top bar, to
open a search dialog that finds:

- **Courses** the signed-in person is enrolled in, fetched once per page with the web service
  `core_course_get_enrolled_courses_by_timeline_classification`.
- **Pages** linked from the current page's navigation: primary and secondary navigation, course index, page header,
  user menu, drawers, breadcrumbs, the "more" menu and the footer.
- **Search all courses for "…"**, which opens Moodle's course search with the typed text.
- **Search site settings for "…"**, which opens the administration search. This row only appears for people with
  `moodle/site:config`.

The palette also carries the [regex builder](regex-builder.md). Up to 60 matches are listed; the matching part of
each label is highlighted.

## Keyboard

| Key | Action |
| --- | --- |
| Ctrl+Shift+F | Open, or close when open |
| Up / Down | Move through results |
| Enter (in the field) | Open the highlighted result |
| Escape | Close and return focus to where it was |
| Tab / Shift+Tab | Move between controls; focus stays inside the dialog |

The dialog is a modal `role="dialog"`; the field is a `combobox` that controls a `listbox`, and the active result
is announced through `aria-activedescendant`. The match count is in a polite live region.

## Settings

| Setting | Name | Default |
| --- | --- | --- |
| Command palette | `theme_material | commandpalette` | on |

The **Regex** and **Ignore case** switches in the palette are remembered per browser in `localStorage`
(`theme_material_regex`, `theme_material_ignoreCase`).

## Limits

- It does not yet list every site setting, every activity in a course, or appearance controls, and selecting a
  result navigates to the page rather than scrolling to and highlighting a single element.
- Signed-out visitors only get page links and the course search.

## When something goes wrong

- **No courses listed.** The course list comes from a web service call; if it fails the palette quietly shows page
  links only.
- **"Pattern is incomplete".** Regex mode is on and the text is not yet a valid pattern. Finish the pattern, remove
  the last block, or turn Regex off.

## Security and privacy

Results are built from links already on the page and from the person's own enrolments, so the palette never shows
anything the person could not already open. Nothing is sent anywhere except the standard Moodle web service call.

## How to verify

1. Sign in, press Ctrl+Shift+F, type part of a course name: the course is listed and Enter opens it.
2. As an administrator, type `theme`: the last row searches site settings.
3. Press Escape: focus returns to the element that had it.

## Related articles

- [Regex builder on search boxes](regex-builder.md)
- [Right-click menu](right-click-menu.md)
