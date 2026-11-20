# Material Moodle: notes for agents

This repository is a fork of Moodle 6 with a Material 3 layer on top. Upstream Moodle files stay as they are;
everything this fork adds lives in a small, separate overlay so upstream merges stay simple.

## Where the Material additions live

| Path | What it is |
| --- | --- |
| `public/theme/material` | `theme_material`, a Boost child theme. One seed colour generates the light and dark palette (`classes/palette.php`). It adds motion settings, the Ctrl+Shift+F command palette with a regex builder, copy buttons on code, celebrations and the Material sign-in page. Browser code is in `amd/src`. |
| `public/auth/passkey` | `auth_passkey`, passkey (WebAuthn) sign-in, the per-user passkey manager and the optional "add a passkey" step after a new account's first sign-in. |
| `public/local/materialtools` | `local_materialtools`: Add a student (with a credentials message for Discord), the custom vocabulary pack, site wording (role and assignment names), per-activity type labels and the first-run configuration CLI. |
| `material/docker` | Dockerfile, Compose stack (PostgreSQL, Moodle, cron, optional Cloudflare quick or named tunnel) and the container entrypoint. |
| `material/deployer` | Desktop deployer (Electron) that deploys the stack to an SSH server and sets up a Cloudflare tunnel (quick link, guided sign-in for your own domain, or a token). `npm test` runs its tests. |
| `material/site` | Source of the public project site (GitHub Pages). Rebuild with `node material/site/scripts/build.mjs`. |
| `material/design` | Design references for the site and the theme. Not created yet. |
| `material/docs` | One Markdown article per feature; start at `material/docs/README.md`. |
| `material/scripts` | Build and verification scripts (see below). |
| `material/release` | Release notes generator, version metadata and release code names. |
| `material/ROADMAP.md`, `material/HANDOFF.md`, `material/COMPLETENESS.md` | Remaining work, current state and the feature completeness inventory. |

## Building the browser modules

The plugins' AMD modules are hand-written in `amd/src`. Moodle loads `amd/build`, so rebuild after every change:

```sh
material/scripts/build-amd.sh
```

The script runs `node --check` on each source file and copies it to `amd/build/<name>.min.js` with an explicit
module name. It does not need Moodle's grunt toolchain. Commit `amd/src` and `amd/build` together.

## Running the Docker stack

```sh
cd material/docker
cp .env.example .env        # set MOODLE_ADMIN_PASSWORD and MOODLE_DB_PASSWORD at least
docker compose up -d --build
```

Moodle answers on `http://localhost:8080` (change `MOODLE_PORT` and `MOODLE_WWWROOT` together). The first start
installs Moodle and applies the Material defaults, which takes a few minutes; the log prints
`MATERIAL_MOODLE_INSTALLED` when it is done. Add `--profile quicktunnel` for a temporary public
`trycloudflare.com` address (set `MOODLE_WWWROOT=auto`), or `--profile tunnel` with `CLOUDFLARE_TUNNEL_TOKEN` for
a named tunnel. See `material/docs/docker-stack.md` and `material/docs/cloudflare-tunnels.md`.

## Checks before publishing

```sh
node material/scripts/verify-public-vocabulary.mjs   # must pass before any commit, release note or site change
node material/scripts/line-counts.mjs                # line count table for release notes
```

## Agent conversation vocabulary

Agents working in this repository use the shared private conversation vocabulary defined in
the maintainer's canonical agent instructions for every reply, including chat prose, visible
reasoning, status lines, and task or session titles. Read those instructions and apply them
in full. They are deliberately not restated here, because this file is part of this
repository's public record.

That vocabulary never appears in anything published. Keep it out of commit messages, branch
names, code, code comments, test names, documentation, this file, issues, pull requests,
discussions, release notes, published sites, logs, and every other file in this repository.
Published writing uses ordinary professional English, and exact technical identifiers always
keep their literal spelling. The single documented public-safe exception is named in those
canonical instructions; do not infer any other.

Scan any text bound for a public surface against that vocabulary before publishing it. A
reviewer cannot tell a correct release note from a leaking one by reading it, so the scan is
a step, not a habit.
