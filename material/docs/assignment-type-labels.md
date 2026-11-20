# Per-assignment type labels

**Status:** In progress. Plugin: `local_materialtools` (`classes/labels.php`, `db/install.xml`).

## Intended behaviour

Each activity can show its own type name without changing what it is. One assignment can appear as a
**Dropbox**, another as a **Lab report**, while both stay ordinary assignments with the same permissions, grading and
reports. Teachers also get quick buttons such as **Post a new Dropbox** that create an activity of the right module
and give it the label in one step.

- **Type label per activity:** stored per course module (`local_materialtools_cmlabel`: `cmid`, `courseid`,
  `label` up to 100 characters). Shown wherever the activity's type name appears for that activity. An empty label
  removes it and the standard name returns.
- **Quick post buttons:** defined by the setting `local_materialtools | quickposts`, one `Label|modname` per line,
  for example `Dropbox|assign`. When the setting is empty, the default is `Dropbox|assign`. Lines with an invalid
  module name are ignored.
- **Personal titles:** a person can carry a display title such as "Coach" or "Intern" for the whole site or one
  course (`local_materialtools_title`, course 0 for the site; a course title wins over the site title). The role
  itself is unchanged.

Labels and titles never change roles, capabilities or module types; they are display names only.

## What exists today

- `classes/labels.php`: storing and reading activity labels, course label lists, personal titles (site and course)
  and parsing the quick post setting.
- `db/install.xml`: the two tables.

## What is missing

- The editing interface (activity settings field, course page action, title management page).
- Showing labels and titles on course pages, activity headers, the activity chooser and participant lists.
- The quick post buttons on the course page and the `quickposts` administration setting.
- A database upgrade step (`db/upgrade.php`) and version bump, so sites that already have the plugin installed get
  the new tables.
- Privacy provider coverage for personal titles, which are personal data.
- Backup and restore of labels and titles with the course.

## Related articles

- [Site wording](site-wording.md)
- [Custom vocabulary pack](custom-vocabulary-pack.md)
