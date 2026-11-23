# Features launcher and All features page

Moodle's top bar shows only a few destinations. Calendar, grades, badges, private files, messages, the content
bank, learning plans, reports and the administration tools exist, but people had to know where to look. The
features launcher lists every Moodle feature the signed-in person can open, in one place.

## What it does

- A grid button in the top bar opens a panel grouped into **This course** (only inside a course), **Learning**,
  **Progress**, **Talk**, **Content**, **Account** and **Administration**.
- The search box finds a feature by name or description. The `.*` switch treats the text as a regular expression,
  the same way as the command palette. An incomplete pattern is reported and every feature stays listed.
- Group chips narrow the list. Arrow keys move between features, Home and End jump to the ends, and Escape closes
  the panel. On phones the panel becomes a sheet of full-width rows.
- **Open the full page** goes to `/theme/material/features.php`, which shows each feature with what it does and
  where it lives, a group rail, and a switch to hide features the site has turned off.
- The command palette (Ctrl+Shift+F) now also finds every launcher entry.

## Which features appear

Each entry is checked when the page is built:

| Group | Feature | Shown when |
| --- | --- | --- |
| This course | Participants, Gradebook, Course reports, Question banks, Course badges, Course reuse | Inside a course, with the matching course capability |
| Learning | Dashboard, My courses, Calendar, Find a course, All courses | Signed in (Dashboard only when the site has it enabled) |
| Progress | Grades, Badges, Learning plans, Reports | Badges need `moodle/badges:manageownbadges`; Reports need permission to view custom reports |
| Talk | Messages, Notifications, Blogs, Notes, Tags | Blogs need `moodle/blog:view` |
| Content | Private files, Content bank | `moodle/user:manageownfiles`, `moodle/contentbank:access` |
| Account | Profile, Preferences, Passkeys, Data requests, Contact site support | Passkeys when passkey sign-in is enabled; support when the site offers it |
| Administration | Site administration, Users, Add a student, Manage courses, Logs, Plugins, Site wording | The matching administration capability or page access |

Features a site setting switches off (badges, blogs, notes, tags, messaging, custom reports, competencies) stay
listed as **Off for this site**. People with `moodle/site:config` also see a link to that exact setting.

## Settings

There are no settings. The list follows capabilities and site switches, so it changes with roles and permissions.

## What can go wrong

- A feature provided by a plugin that is not installed is not listed.
- Capabilities are checked when the page is built. A permission change shows after the next page load.
- A third-party plugin's own pages are not listed; they remain in Moodle's navigation.

## Security and privacy

The launcher only lists links. Opening a feature still goes through that page's own sign-in and capability checks.
Guests and signed-out visitors see no launcher, and the page requires sign-in. Search runs in the browser and is
not sent anywhere. Patterns are limited to 200 characters.

## How to verify

`material/tests/e2e/features-launcher.mjs` signs in as a disposable administrator and checks, in Chromium and
WebKit: the panel at 320, 390 and 1440 pixels wide stays inside the viewport, search takes focus, the expected
features are listed, touch targets are at least 44 pixels, plain and pattern search, the incomplete-pattern
message, group chips, arrow-key movement, the full page, its live count and the absence of sideways scrolling.

The design is in `material/design/canvas` (desktop panel, phone sheet and full page).
