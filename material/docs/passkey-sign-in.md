# Passkey sign-in

**Status:** Available. Plugin: `auth_passkey` (`public/auth/passkey`).

## What it does

People sign in with a passkey (fingerprint, face, screen lock or security key) that they added to their own
account. The plugin never owns accounts: it adds a sign-in route to accounts of any authentication method, so a
manual account keeps its password as a fallback.

On the standard sign-in page (`login-index`) a hook adds a large **Sign in with a passkey** button above the
username form, with "or use your username" below it. Browsers without WebAuthn support do not get the button. If
the inline button cannot be shown, the identity provider list links to `/auth/passkey/login.php`, a page with only
the passkey button and a link back to the password form.

## How a sign-in works

1. The browser asks `auth/passkey/ajax.php?action=loginoptions` (with the session key) for options. The server
   creates a random challenge and keeps it in the session for this one attempt.
2. The browser asks the device for a discoverable credential for this site, with no account list, so the person
   does not type a username.
3. The browser posts the result to `action=login`. The server looks the credential up by the SHA-256 of its id,
   verifies the signature, origin, challenge and user verification with the bundled `lbuchs/webauthn` library,
   stores the new signature counter and last-used time, then checks the account.
4. The account must exist and must not be deleted, suspended, set to `nologin` or the guest account, and its
   authentication method must be enabled (manual accounts are always accepted). Moodle's normal
   `complete_user_login` runs, the concurrent session limit is applied, and the browser goes to the page the person
   wanted.

The relying party id is the host name of `$CFG->wwwroot`; the name shown by the device is the **Name shown by the
device** setting or the site name.

## Enabling it

1. Site administration > Plugins > Authentication > Manage authentication: enable **Passkeys**. It does not need to
   be first in the list. The Docker first run enables it unless `MATERIAL_ENABLE_PASSKEYS=0`.
2. People add passkeys on their own [passkey manager](passkey-manager.md) page.

## Settings

Site administration > Plugins > Authentication > Passkeys:

| Setting | Name | Default |
| --- | --- | --- |
| Name shown by the device | `auth_passkey | rpname` | empty (site name) |
| User verification | `auth_passkey | userverification` | `required`. `preferred` also accepts security keys without a PIN. |
| Prompt timeout | `auth_passkey | timeout` | 120 s (60, 120 or 300; clamped to 30 to 600) |
| Offer a passkey to new accounts | `auth_passkey | offeronfirstlogin` | on, see [First sign-in flow](first-sign-in.md) |

## When something goes wrong

| Message | Cause |
| --- | --- |
| "The passkey prompt was closed. Try again when you are ready." | The person cancelled or the prompt timed out. |
| "This passkey is not registered on this site." | The credential is unknown, for example it was removed or added on another address. |
| "The sign-in request expired. Try again." | No challenge in the session (expired session or a second tab). |
| "This account cannot sign in right now." | Account suspended, deleted, `nologin`, or its authentication method is disabled. |
| "Passkey sign-in is turned off on this site." | The plugin is not enabled. |
| "This browser cannot use passkeys." | No WebAuthn support. |

Passkeys belong to one host name. Moving the site to a new address, or using a Cloudflare quick tunnel whose random
address changes on every restart, makes existing passkeys unusable on the new address. Use a fixed address (a
named tunnel or your own domain) before people add passkeys.

## Security and privacy

- Every action requires Moodle's session key; challenges are single-use and kept server-side.
- Only the public key, credential id, signature counter, authenticator model id (AAGUID), transports, name and
  dates are stored, in `auth_passkey_creds`. Biometric data never leaves the device.
- The user handle sent to devices is a SHA-256 of the site identifier and the user id, not the user id itself.
- The privacy provider exports and deletes a person's passkeys and declares the passkey offer preference.
- `user_login()` always returns false, so this plugin can never be used to sign in with a password.

## How to verify

1. Enable the plugin, add a passkey as a test user, sign out.
2. On the sign-in page select **Sign in with a passkey** and complete the device prompt: you are signed in and the
   passkey's "Last used" time updates.
3. Suspend the test user and try again: "This account cannot sign in right now."

## Related articles

- [Passkey manager](passkey-manager.md)
- [First sign-in flow](first-sign-in.md)
- [Cloudflare tunnels](cloudflare-tunnels.md)
