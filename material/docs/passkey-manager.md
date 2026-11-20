# Passkey manager

**Status:** Available. Plugin: `auth_passkey`, page `/auth/passkey/manage.php`.

## What it does

Every signed-in person (not guests) can manage their own passkeys. The page is linked as **Passkeys** from their
Preferences page.

- **Add a passkey**: optionally type a name (for example "My phone"), then follow the device prompt. The device is
  told which passkeys the account already has, so the same authenticator is not registered twice. Discoverable
  credentials are requested so the passkey works without a username.
- Each passkey shows its name, the date it was added and when it was last used ("Never" until first use).
- **Rename** changes the name (empty names are ignored; up to 255 characters).
- **Remove** asks for confirmation first, then deletes the passkey. Removal only touches the person's own passkeys.

If passkey sign-in is not enabled for the site, the page says so: passkeys can still be added but cannot be used
to sign in until an administrator enables the plugin.

## Limits

- People can only manage their own passkeys. There is no administrator view of another person's passkeys yet; to
  remove someone's passkeys, delete the rows for that user in `auth_passkey_creds` or use the privacy data deletion
  tools.
- No email or notification is sent when a passkey is added or removed.

## Security and privacy

All changes need the session key. Rename and delete are limited to rows with the person's own user id.

## How to verify

Open Preferences > Passkeys, add a passkey, rename it, sign out and in with it (the "Last used" time changes), then
remove it and confirm that it no longer signs you in.

## Related articles

- [Passkey sign-in](passkey-sign-in.md)
- [First sign-in flow](first-sign-in.md)
