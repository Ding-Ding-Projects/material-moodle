# Bathroom pass

Students sign themselves out of the room and back in from the course page. The classroom's Home Assistant is told
each time, through two separate webhooks. The plugin is `local_bathroompass`.

## A break is never refused

Students get a number of passes per period (5 per quarter by default). When the passes are used up, the button still
works. The card says plainly that using the bathroom is a human right, that the student will never be refused, and
that there is no grade penalty, ever. This plugin never changes a grade.

The only effect of breaks past the allowance is on due date extension coin flips (when `local_dueextensions` is
installed): each extra break in the current period turns 5% of the student's heads chance into a golden chance. Tails
stays at 50% and heads never drops below 25%. A golden result still grants the extension and shows a dim sum. The
card shows the current split.

## What students see

- **Normal:** passes left this period (for example "3 of 5 left this quarter") and **Leave for the bathroom**.
- **Out of the room:** a live timer, "Your teacher can see that you stepped out", whether this break uses a pass, and
  **I'm back**. Returning shows how long the break took.
- **Out of passes:** the human-right message and the coin split, with the button still enabled.

## Teacher settings

Open **More > Bathroom pass** in a course.

| Setting | Default | Notes |
| --- | --- | --- |
| Passes per student | 5 | Or unlimited |
| Period | Quarter | Week, month, quarter, half year or whole course, in each student's time zone |
| Golden chance per extra break | 5% | 0 to 25; heads never drops below 25% |
| When a student leaves | (empty) | Home Assistant webhook URL |
| When a student comes back | (empty) | A separate webhook URL |
| Include the student's first name | Off | Sent in the webhook payload only when on |

Each webhook has **Send test**, which posts `{"event":"test"}` and shows the HTTP status. The **Now and history** tab
lists who is out of the room now, with **Mark as back**, and every break with its duration, whether it was past the
allowance and each webhook's delivery status.

## Webhooks

Both are JSON `POST` requests with a 4-second timeout:

```json
{"event": "left", "course": "BIO101", "courseid": 2, "time": 1795415571, "over_allowance": false, "student": "Mei"}
{"event": "back", "course": "BIO101", "courseid": 2, "time": 1795415573, "over_allowance": false, "student": "Mei", "duration_seconds": 2}
```

`student` is present only when the teacher turns on first names. In Home Assistant, create two automations with a
**Webhook** trigger and paste their URLs (`http://<home-assistant>:8123/api/webhook/<id>`) into the two fields.

Moodle blocks requests to private network addresses by default, and Home Assistant usually lives on the local network.
A site administrator can allow it under **Site administration > Plugins > Local plugins > Bathroom pass > Allow local
network webhooks**. A failed webhook never blocks a break; it is recorded and shown to the teacher.

## Capabilities

| Capability | Default roles |
| --- | --- |
| `local/bathroompass:use` | Student |
| `local/bathroompass:manage` | Editing teacher, manager |
| `local/bathroompass:configurewebhooks` | Editing teacher, manager (configuration risk) |
| `local/bathroompass:viewbreaks` | Teacher, editing teacher, manager |

## Privacy

Breaks (times, duration, whether past the allowance) are stored per student and covered by the privacy provider. The
webhooks send the course short name and times, plus the first name only when the teacher turns that on.

## How to verify

On 23 November 2026, on a Docker test site with one pass per quarter and both webhooks pointed at a local receiver:
the first break used the pass, the second was allowed past the allowance with the human-right message, the receiver got
four JSON posts on the two separate URLs with the right `over_allowance` flags, names and duration, and the extension
coin then showed heads 45%, golden 5%, tails 50%. A forced golden flip granted the extension and picked a dish whose
public photo loaded.
