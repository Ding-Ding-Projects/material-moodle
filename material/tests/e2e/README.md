# End-to-end checks

`display-menu.mjs` checks all nine toolbar choices with Chromium and WebKit at four viewport sizes.
Set `MOODLE_URL` and `MOODLE_STORAGE_STATE` to a test deployment and an authenticated test-account
Playwright storage-state file. Keep that file outside the repository.

Browser checks against a running stack (see `material/docker`). They use Playwright with Chromium.

```sh
npm i playwright && npx playwright install chromium
export MOODLE_URL=http://localhost:8080 MOODLE_ADMIN_PASSWORD='your admin password'
node material/tests/e2e/passkey.mjs       # adds a passkey with a virtual authenticator, signs out, signs in with it
node material/tests/e2e/onboarding.mjs    # walks the setup wizard
node material/tests/e2e/screenshots.mjs   # refreshes material/screenshots
```
