# Due date extensions

Students can move an assignment's due date themselves a few times per period, under rules the teacher sets.
The plugin is `local_dueextensions`, with `gradepenalty_dueextension` for wheel deductions.

## What students see

On an assignment page, a **Due date extension** card shows how many extensions are left (for example
"2 of 3 left this month"), the course's rule, the current due date and a **Use an extension** button. The button
opens a dialog that runs the rule's animation (die, coin or wheel) and then shows the result: the days added, the new
due date, any grade deduction and how many extensions are left. The result is decided by the site with a secure random
source **before** the animation starts; the animation only reveals it.

Extensions are used before the due date. After the due date has passed, after submitting, or when none are left, the
card explains why the button is not there and when the next extension arrives.

## Rules teachers set

Open **More > Due date extensions** in a course.

| Setting | Default | Notes |
| --- | --- | --- |
| Extensions per student | 3 | Up to 100, or Unlimited |
| Period | Month | Week, Month or Whole course, in each student's own time zone |
| How an extension is decided | Instant | Instant, Dice, Coin or Wheel |
| Instant: days added | 2 | 1 to 60 |
| Dice | 6 sides | 4, 6, 8, 12 or 20 sides; the days equal the side rolled |
| Coin: days added on heads | 3 | Tails denies the extension |
| Wheel segments | 1/0, 2/0, 3/5, 5/10, 7/15, denied | Days and a grade deduction in percent per segment; 0 days means denied; 2 to 12 segments |
| A denied attempt uses one extension | On | See below |

### Denied attempts

- **On:** a denied coin or wheel result uses one extension and the assignment is graded as usual.
- **Off:** a denied result keeps the extension for a later assignment, and **this assignment is marked 0**
  (for a scale, the lowest scale item). The dialog tells the student this before they try.

### The coin and the golden chance

The coin is 50% heads and 50% tails. When the bathroom pass is installed, each break a student takes after using up
their passes moves part of the heads chance into a **golden** chance (5% per extra break by default). Tails always
stays at 50%, and heads never drops below 25%. A golden result grants the extension with the normal days and shows the
student a dim sum from the public dim sum catalog. The card shows the current split.

### Wheel deductions

A wheel segment's deduction is applied through Moodle's grade penalty system by `gradepenalty_dueextension`. It only
takes effect when grade penalties are enabled on the site, for assignments, and on that assignment.

## The log and revoking

The **Log** tab lists every attempt with the student, assignment, rule, result, days, deduction and time. **Revoke**
puts the due date back to what it was before that extension (only when no later extension has moved it since), restores
the grade if that attempt marked the assignment 0, and stops the attempt counting towards the allowance.

## Capabilities

| Capability | Default roles |
| --- | --- |
| `local/dueextensions:use` | Student |
| `local/dueextensions:manage` | Editing teacher, manager |
| `local/dueextensions:viewlog` | Teacher, editing teacher, manager |

## Security and privacy

- The browser never chooses the outcome; a per-student lock stops double requests.
- Students' extension dates are written through the assignment's own user flags and calendar event, so Moodle shows the
  extended date everywhere it normally does.
- The site fetches the public dim sum catalog index (no user data is sent) and caches it for a day. If it cannot be
  reached, the golden result still grants the extension and shows the dish name without a photo.
- Each attempt is stored with its outcome; the privacy provider exports and deletes it.

## How to verify

On a test site with a student and an assignment due in the future: with Dice, use an extension and check the due date
moved by the rolled number and the allowance dropped by one; with Wheel, check a granted segment's days and deduction;
with Coin and "A denied attempt uses one extension" off, check that tails sets the assignment grade to 0 and Revoke
restores it. All four were checked on 23 November 2026 on a Docker test site in Chromium.
