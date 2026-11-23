# Material Moodle handoff

## Finishing pass, 2026-11-23

- Built: the slim top bar (`scss/topbar.scss`), the bento launcher, the sign-in fix (`scss/signin.scss`,
  `templates/core/login_panel.mustache`), the page restyle (`scss/redesign.scss`), the dockview course workspace
  (`amd/src/workspace.js`, `classes/hook_workspace.php`, `scss/dockview.scss`) and the deployer workspace
  (`material/deployer`). Boost's `$navbar-height` is 72px. See `docs/course-workspace.md`.
- Verified on a Docker test site: sign-in and ten pages without page errors or sideways scrolling at 1440 and 390
  pixels; launcher and course workspace tests in Chromium and WebKit; the TinyMCE 8.10.2 editor saving bold text;
  the golden coin dialog with a dim sum photo.
- Later the same day: the deployer ran in a real Electron 39.8.10 window from source on a hidden desktop (tabs,
  close, reload, tab search, no page errors); dark mode was measured on eleven pages (`tests/e2e/dark-mode.mjs`,
  no text below 3:1; the admin search icon was too dark and is fixed); the four page states were built
  (`scss/states.scss`, `amd/src/states.js`, `docs/page-states.md`, `tests/e2e/states.mjs`). The deployer's
  `node_modules/electron` had no binary; it was extracted from the local Electron download cache.
- `npm run dist` builds `Material Moodle Deployer Setup 1.0.0.exe` (98 MB) and the portable
  `Material Moodle Deployer 1.0.0.exe` (90 MB), unsigned; the built app in `dist/win-unpacked` passed the same
  workspace checks on a hidden desktop. The installer was built but not installed on this computer.
- Not verified: some page selectors the restyle could not confirm from templates
  (timeline items, dashboard block columns, admin category tiles).
- The private dockview fork `Ding-Ding-Projects/dockview` now carries the tab features as its own material layer
  (built in a separate session); its remaining items are in that fork's roadmap.
- Test sites on plain `http://localhost` need `cookiesecure` off. WebKit reports Moodle core requests cut short by
  navigation as page errors; the tests ignore exactly that pattern.

## Redesign and launcher, 2026-11-23

- The design lives in `design/canvas` at the repository root: `DESIGN-SPEC.md` (colours, type, shapes, shell),
  `FIX-BRIEF.md` (review rules) and 32 boards in `project/`. Shared components `Rail`, `TopBar` and `PhoneNav` are
  imported by every board, so the shell cannot drift. Boards were rendered locally and reviewed twice.
- In the theme: the default seed is now `#3D5BD9` with reference roles from the spec (`classes/palette.php`);
  `db/upgrade.php` moves sites still on `#8B5000` to it. The navigation rail (`classes/rail.php`,
  `templates/rail.mustache`, `scss/rail.scss`) is added through the top-of-body hook and marks `<html data-mm-rail>`.
  The features launcher (`classes/features.php`, `features.php`, `templates/features_*.mustache`, `amd/src/features.js`).
- TinyMCE is 8.10.2 in `public/lib/editor/tiny/js/tinymce`; the editor itself has not been exercised since.
- Verified on a throwaway Docker test site (theme copied into the container, upgrade run): rail position and active item
  at 1440 and 390 pixels, no page errors, no sideways scrolling; launcher browser test in Chromium. Not verified: WebKit,
  the real pages after the full redesign (not built yet), the editor.
- Note for test sites on plain `http://localhost`: `cookiesecure` must be off or sign-in silently fails.
- Remaining work is listed under "Redesign, 2026-11-23" in `ROADMAP.md`.

## Shared control design

Theme version `2026112203` connects Moodle's component colours with the same seed palette used by
course forms and settings. The editor inherits its base content stylesheet and adds the active surface
and ink colours in its iframe. See the [comparison](design/README.md#shared-moodle-controls) and
`material/tests/e2e/control-surfaces.mjs` for scope, screenshots and browser checks.

## Toolbar and interactive demo reference

Use `material/site/demo.html` as the selected visual source and `material/design/README.md`
for reference and runtime evidence. The toolbar panel includes colour, motion and panel language.
An isolated installation verified all nine controls at four widths in Chromium and WebKit.
The updated default palette also uses the demo's colour roles. See the design comparison
for measured differences and the remaining full-screen coverage.

## Configuration and feature inventory, 2026-11-22

The stock public source was compared with upstream revision `2d4bafe8548a6d6c01494c862ec3d9749ba6ea84`.
All 67,073 baseline public paths and all 23 activity/resource modules were retained. Component discovery
found 411 bundled components and five additions. Inclusion alone does not exercise their workflows.
The updated inventory covers 121 feature IDs and records incomplete work explicitly.
The desktop deployment form now manages nine PHP runtime options. The web app adds five core operations
settings and per-account reading preferences. See [administration settings](docs/administration-settings.md).

An isolated ARM64 deployment of `9fb783b640e025b2193a8abf1549fda86e6b15ba` accepted a change to ZIP backups
and a 15-minute enrolment sync interval through the actual settings form and retained both on reload.
Reading preferences persisted at 125% (computed root size 20 px), motion off and relaxed spacing,
then returned to site defaults. The two screenshot files in the README show the synthetic test account
in Chromium at a 1240 by 900 viewport, light theme. Runtime PHP reported the fixture limits of 768 MiB
memory, 64 MiB per upload and 128 MiB per request. The deployer Linux suite reported 49 passes and one
skipped Compose check in the test environment.

The configuration directory lists 199 distinct property names in this version of `config-dist.php`.
In the staged installation, 23 had a matching editable core setting, one had a host override and
175 had no matching native core field. These counts vary with the installation. The directory links
to existing controls and does not grant new permissions or turn arbitrary configuration into editable PHP.

The desktop deployer can also find an already running Compose stack from its service labels, read the
nine configured PHP limits and apply selected changes without a full deployment. An isolated ARM64
stack verified an actual change to 768 MiB of memory and 64 MiB per upload, rejection of a conflicting
request limit, and rollback after a deliberately interrupted readback. A no-change apply on the
existing installation preserved both container IDs. Recovery copies remain on the host with owner-only access.

## Earlier handoff, 2026-11-20

## Current state

- Base: Moodle 6 (upstream commit `2d4bafe85`). Commit `c8a646269` on `main` adds the three plugins, the Docker
  stack and `.dockerignore`. Still uncommitted: `AGENTS.md`, `material/docs`, `material/ROADMAP.md`,
  `material/HANDOFF.md`, `material/COMPLETENESS.md`, the two new scripts in `material/scripts`, the deployer,
  the site, `material/release` and both `.github/workflows/material-*.yml` files.
- Working plugins (by code review, not by a running site): Material theme, command palette with regex blocks, copy
  buttons, celebrations, sign-in page, passkey sign-in and manager, first sign-in passkey step, Add a student,
  custom vocabulary pack, site wording, first-run configuration CLI, Docker stack.
- Written but not active: `T/amd/src/searchbars.js` (regex builder on page search boxes) and
  `T/amd/src/contextmenu.js` (right-click menu). Neither is loaded by `main.js` nor has an `amd/build` file; the
  context menu's strings are missing.
- Being built by other contributors at the time of writing: per-assignment type labels (`L/classes/labels.php`,
  `L/db/install.xml`, data layer only), the desktop deployer (`material/deployer/src`), the project site
  (`material/site`) and the release pipeline (`material/release`, `material-release.yml`).
- Completeness against the maintainer's feature catalogue: 5 Done, 28 Partial, 73 Missing, 15 Not applicable out
  of 121 (`COMPLETENESS.md`).

`T`, `P` and `L` stand for `public/theme/material`, `public/auth/passkey` and `public/local/materialtools`.

## Build

```sh
material/scripts/build-amd.sh            # rebuild amd/build from amd/src for the three plugins
```

## Run

```sh
cd material/docker && cp .env.example .env   # set MOODLE_ADMIN_PASSWORD and MOODLE_DB_PASSWORD
docker compose up -d --build
docker compose logs -f moodle                # wait for MATERIAL_MOODLE_INSTALLED, then open http://localhost:8080
```

## Test and check

```sh
node material/scripts/verify-public-vocabulary.mjs   # public vocabulary scan
node material/scripts/line-counts.mjs                # line counts for release notes
```

There are no PHPUnit, Behat or JavaScript tests for the Material plugins yet. The `Material image` workflow
smoke-tests the container (install, HTTP 200 on the sign-in page, Material layout present) but has not run yet.

## Verification evidence from this session

- `verify-public-vocabulary.mjs`: red on a test dictionary (findings reported, exit 1); against the real dictionary
  it reports 2 findings, both in `material/site/assets/css/site.css` (two lines using one CSS class name), which is
  outside the files this session wrote. Everything this session wrote passes.
- `line-counts.mjs`: runs and prints the table.
- Not run: the Docker stack, any browser check, any workflow.

## Open items

1. Fix the two scan findings in `material/site/assets/css/site.css` (rename the class) before any commit.
2. Activate and build `searchbars.js` and `contextmenu.js`, or remove the page-filter promise from the
   `regexbuilder_desc` string until they ship.
3. Per-assignment labels need UI, a `db/upgrade.php` step with a version bump (installed sites will not get the
   new tables otherwise) and privacy provider coverage for personal titles.
4. Add licence files for the bundled Inter, Atkinson Hyperlegible Next and Roboto Mono fonts.
5. Deployer: Windows target is NSIS/portable while the catalogue requires Squirrel.Windows; `build/icon.png` is
   missing.
6. Root `README.md` is still upstream Moodle's.
7. Everything else: see `ROADMAP.md`.
