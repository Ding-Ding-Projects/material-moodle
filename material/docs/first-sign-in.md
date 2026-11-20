# First sign-in flow

**Status:** Available for accounts created with [Add a student](add-a-student.md). Plugins: `local_materialtools`,
`auth_passkey` (`welcome.php`, `lib.php`).

## What it does

A student created with the Add a student tool goes through three steps the first time they sign in:

1. **New password.** The account is created with `auth_forcepasswordchange`, so Moodle asks for a new password
   immediately after the first sign-in with the temporary one.
2. **Passkey (optional).** On the next page that requires sign-in, `auth_passkey_after_require_login` redirects once
   to `/auth/passkey/welcome.php`: "Faster sign-in next time?" with a progress row (New password done, Passkey
   current, Done), an **Add a passkey** button and **Not now**.
3. **Done.** Adding a passkey or choosing Not now clears the offer and returns the person to the page they were
   going to (or the dashboard).

The offer is a user preference, `auth_passkey_offer`. It is set to 1 when the account is created, only if
`auth_passkey` is installed and **Offer a passkey to new accounts** is on. It is cleared when a passkey is added
(anywhere) or when the person chooses Not now. The redirect never happens during AJAX or CLI requests, while
another user is "logged in as" this person, while the password change is still pending, or on the passkey pages
themselves.

## Settings

| Setting | Name | Default |
| --- | --- | --- |
| Offer a passkey to new accounts | `auth_passkey | offeronfirstlogin` | on |

## Limits

- Accounts created any other way (manual Add a new user, upload, self-registration) do not get the passkey offer.
- This is a first sign-in flow for students, not a site setup wizard. A guided first-run setup for administrators
  does not exist yet; the Docker image applies defaults instead (see [Docker stack](docker-stack.md)).

## How to verify

Create a student with Add a student, sign in as them with the temporary password: Moodle asks for a new password,
then the passkey step appears; **Not now** leads to the dashboard and the step does not return.

## Related articles

- [Add a student and Discord credentials](add-a-student.md)
- [Passkey manager](passkey-manager.md)
