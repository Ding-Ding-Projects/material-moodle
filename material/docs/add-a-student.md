# Add a student and Discord credentials

**Status:** Available. Plugin: `local_materialtools`, page `/local/materialtools/newstudent.php`
(Site administration > Users > Material tools > Add a student).

## What it does

One form creates a ready-to-use student account and produces a message to paste into Discord, with the username
and the temporary password each in its own code block so each copies with one tap.

| Field | Behaviour |
| --- | --- |
| First name, Last name | Required. |
| Username | Optional. Empty means `firstname.lastname` in lower case, cleaned to a valid username; a number is added (`2`, `3`, …) when taken. A typed username is checked for validity and uniqueness. |
| Email (optional) | Validated when given. When empty the account gets `<username>@students.invalid` and email is switched off for it, so Moodle does not ask the student to complete their profile. |
| Temporary password | **Generate one for me** or **Type my own**. A typed password must meet the site password policy. |
| Cohort | Optional; the student is added to it. |
| Enrol in course (optional) | Enrols through the course's manual enrolment method, with the role from **Role when enrolling**. |
| Custom vocabulary | Shown when a vocabulary pack is loaded; chooses this student to see it. |

After **Create student** the page shows the credentials: the full name, username and temporary password (shown only
this once), the Discord message with **Copy for Discord**, notes about what happens at first sign-in, and links to
**Add another student** and **View profile**.

The account is a manual account, confirmed, with the site default language and a forced password change. See
[First sign-in flow](first-sign-in.md).

## Generated passwords

- **Two words and a number** (default): for example `Cedar-Otter-4821!`.
- **Random characters**: `generate_password()` with the configured length (at least 10, default 14).

Each candidate is checked against the site password policy; after 20 failed attempts a 16-character random
password is used.

## The credentials message

The template uses the placeholders `{sitename}`, `{siteurl}` (the sign-in page), `{fullname}`, `{firstname}`,
`{username}` and `{password}`. The default is:

````text
**{sitename}** sign-in for {fullname}
{siteurl}
Username:
```
{username}
```
Password:
```
{password}
```
You will choose a new password the first time you sign in. After that you can add a passkey for faster sign-in.
````

## Settings

Site administration > Users > Material tools > Student account settings:

| Setting | Name | Default |
| --- | --- | --- |
| Credentials message | `local_materialtools | discordtemplate` | the template above |
| Temporary password style | `local_materialtools | passwordstyle` | `words` |
| Random password length | `local_materialtools | passwordlength` | 14 |
| Role when enrolling | `local_materialtools | studentrole` | the `student` role |

## Permissions

`local/materialtools:createstudents` (managers by default; carries personal-data and spam risk, cloned from
`moodle/user:create`).

## When something goes wrong

- **The student was not enrolled.** Enrolment only uses an enabled manual enrolment method in that course. If the
  course has none, the account and cohort membership are still created and enrolment is skipped silently. Add
  manual enrolments to the course and enrol the student from the participants page.
- **"Username already exists"** for a typed username: choose another or leave the field empty.

## Security and privacy

The temporary password is shown once and never stored in plain text; the student must change it at first sign-in.
Treat the Discord message as a secret: send it in a direct message, not a public channel.

## How to verify

Create a student without a username or email, copy the Discord message, paste it into Discord and check that both
code blocks copy individually. Sign in as the student: a password change is required.

## Related articles

- [First sign-in flow](first-sign-in.md)
- [Custom vocabulary pack](custom-vocabulary-pack.md)
- [Copy buttons](copy-buttons.md)
