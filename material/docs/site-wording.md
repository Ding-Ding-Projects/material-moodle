# Site wording

**Status:** Available. Plugin: `local_materialtools`, page `/local/materialtools/wording.php`
(Site administration > Users > Material tools > Site wording).

## What it does

Call teachers, students and assignments whatever your school calls them. The names apply on every page, for
everyone, signed in or not, including every assignment that is posted. Leave a field empty to keep the standard
name.

| Field | What changes |
| --- | --- |
| Teacher | The site-wide name of the `editingteacher` role. |
| Non-editing teacher | The site-wide name of the `teacher` role. |
| Student | The site-wide name of the `student` role. |
| Assignment (one) | The `assign` strings `modulename` and `pluginname` in the current language. |
| Assignment (many) | The `assign` string `modulenameplural` in the current language. |

Role names are stored in the `role` table (up to 255 characters) and access caches are cleared. Assignment names
are written as a local language override file, `<langlocalroot>/<lang>_local/assign.php` (by default under
`moodledata/lang`), which is the same file Moodle's language customisation tool uses; other overrides in that file
are kept. Emptying both assignment fields removes the file. String caches and course caches are reset so every page
uses the new names at once.

Course teachers can still rename roles for one course under Course settings > Role renaming.

## Limits

- Assignment names are saved for the language of the person saving the form. Repeat in each installed language.
- The page renames the activity type for the whole site. To give one assignment its own type name, see
  [Per-assignment type labels](assignment-type-labels.md).
- Requires `moodle/site:config`.

## When something goes wrong

If the new assignment name does not appear, check that `moodledata/lang/<lang>_local` is writable by the web
server, then purge caches.

## How to verify

Set Student to "Learner" and Assignment to "Task"/"Tasks", save, then open a course: the participants page shows
"Learner" and the activity chooser shows "Task".

## Related articles

- [Custom vocabulary pack](custom-vocabulary-pack.md)
- [Per-assignment type labels](assignment-type-labels.md)
