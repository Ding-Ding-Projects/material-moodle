# Desktop deployer

**Status:** In progress. Folder: `material/deployer` (Electron, package name `material-moodle-deployer`).

## Intended behaviour

Material Moodle Deployer is a desktop app for Windows, macOS and Linux that puts the [Docker stack](docker-stack.md)
on any Linux server reachable over SSH and publishes it through a [Cloudflare tunnel](cloudflare-tunnels.md),
without using a terminal.

1. **Connect.** Enter the server address, user name and a password or private key. The server's host key
   fingerprint is shown and must be accepted the first time; later connections refuse a changed key.
2. **Deploy.** The deployer checks for Docker and Docker Compose, uploads the stack files, writes `.env` with
   generated database and administrator passwords (or values you enter), pulls or builds the image, starts the
   stack and waits up to 15 minutes for Moodle to answer.
3. **Publish.** Choose a quick tunnel (shows the temporary `trycloudflare.com` address), a named tunnel with guided
   Cloudflare sign-in, or a named tunnel from a pasted token.

Each job is a list of steps with live status and logs, and a failed step can be retried without starting over.
Failures are explained in plain language with the likely cause. Server details and preferences are saved; secret
values are kept in the operating system's protected storage.

## What exists today

- `package.json` with the Electron and `ssh2` dependencies, `npm start`, `npm test` (Node's built-in test runner)
  and `npm run dist` (electron-builder, targets NSIS and portable on Windows, DMG and ZIP on macOS, AppImage and DEB
  on Linux).
- `src/main.js`, `src/preload.js` and `src/lib/` (deployment engine with the deploy, quick tunnel, named tunnel and
  token jobs; SSH session with host key checking; `.env` generation; tunnel output parsing and configuration;
  error classification; settings storage). These are being written now and are not verified.

## What is missing

- The renderer (user interface), tests, the app icon and the stack files bundled into the app.
- A verified build of the installers, and update delivery.
- Documentation screenshots.

The release workflow (`.github/workflows/material-release.yml`) builds the installers without code signing once the
app exists.

## Related articles

- [Docker stack](docker-stack.md)
- [Cloudflare tunnels](cloudflare-tunnels.md)
