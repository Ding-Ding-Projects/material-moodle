# Material Moodle roadmap

- [x] Apply shared controls to course forms, settings, navigation and the rich-text editor; exercise the course and settings workflows on phone and desktop widths.

A box is ticked only when the item is implemented and inspectable in the repository. Derived from
[`COMPLETENESS.md`](COMPLETENESS.md); the ids in brackets are its row ids.

## Phase 1: core plugins (web app)

- [ ] Verify account-specific text size, line spacing and motion on the full accessibility and language matrix.
- [ ] Complete the configuration GUI inventory. The SSH deployer now offers the nine PHP resource and cache
  directives shipped with the container. Database migration, host networking and remaining hosting configuration
  need separate controls and recovery checks.

- [x] Material theme with seed-colour palette, fonts, roundness and density
- [x] Motion levels with reduced-motion handling
- [x] Ctrl+Shift+F command palette with regex builder blocks
- [x] Copy buttons on code
- [x] Celebrations on success and error messages
- [x] Material sign-in page
- [x] Passkey sign-in, passkey manager, first sign-in passkey step
- [x] Add a student with Discord credentials message
- [x] Custom vocabulary pack (strict validation, audience, drop to apply)
- [x] Site wording for roles and assignments
- [x] Docker stack with quick and named (token) Cloudflare tunnels
- [x] Activate the search-box regex builder: load `searchbars.js` from `main.js`, build it, verify on course and user lists (`regex-search`)
- [x] Activate the right-click menu: load and build `contextmenu.js`, add the `ctx*` strings, let `Palette.open()` accept a starting query, add a setting and keyboard/touch openers (`context-menus`, `menu-shortcuts`)
- [x] Per-assignment type labels: editing UI, display on course and activity pages, "Post a new Dropbox" buttons, `quickposts` setting, privacy coverage for personal titles, backup and restore
- [ ] Command palette: every setting, activity and article as results, teleport and highlight (`command-palette`)
- [x] Administrator first-run setup wizard in the app (`onboarding-wizard`)
- [x] Administrator view to remove another person's passkeys
- [ ] Automated tests: PHPUnit for vocabulary validation, strict JSON, student creation and the passkey manager are written (`tests/`); run them in CI, and add browser tests for the sign-in and palette flows

## Phase 2: deployer and site

- [ ] Desktop deployer user interface, tests, bundled stack files and icon (`progress-recovery`, `app-icon`, `bundled-dependencies`)
- [ ] Guided named-tunnel sign-in verified end to end
- [ ] Switch the Windows deployer installer to Squirrel.Windows (`squirrel-installer`) and add automatic updates (`auto-updates`)
- [ ] Project site with one article per feature, mobile layout, verified download button, PDF edition (`landing-site`, `feature-articles`, `mobile-friendly-site`, `installer-download-button`, `site-pdf-edition`)
- [x] Record the interactive demo and display-settings comparison under `material/design`.
- [ ] Extend design comparisons beyond display settings to all applicable screens, languages and scales.
- [ ] Rename the site CSS classes that fail the public vocabulary scan (`material/site/assets/css/site.css`)

- [x] Server image (appliance): `.ova`, `.qcow2`, PC and Raspberry Pi images built and boot-tested in CI and attached to releases (`material-appliance.yml`)

## Phase 3: release and repository records

- [x] First successful run of `material-image.yml` and `material-release.yml` with real installers (`release-workflow`)
- [ ] Release notes proven to carry timing and line counts (`release-timing`, `release-line-counts`)
- [ ] Dim sum code names from the public catalogue with photo links (`dim-sum-code-names`, `release-dim-sum-photo`)
- [ ] Project README rewritten for Material Moodle with folded sections and a time estimate (`tabbed-readme`, `human-time-estimate`)
- [ ] Sanitized instruction mirror in README and `AGENTS.md` (`sanitized-instruction-copy`)
- [ ] Vocabulary hash lock on builds and pushes (`vocabulary-hash-lock`)
- [ ] Root `build.bat`, `build-installer.bat`, dependency fetcher and fresh build command (`build-entrypoints`, `fresh-build-run-command`, `dependency-fetcher`)
- [ ] Font fetch script with checksums (`vendored-fonts`); licence files for every font are in place
- [x] Postman collection for the plugin endpoints (`postman-collections`)
- [ ] Screenshots of every surface, a screen recording and a social preview image (`product-evidence`, `screen-recording`, `shared-link-embed`)
- [ ] `CLOSEOUT_PROMPT.md`, GitHub issue handoff, project board, wiki, operational skill (`closeout-prompt`, `handoff-record`, `project-board`, `wiki-site-sync`, `operational-skill`)
- [x] `AGENTS.md` with the vocabulary discipline block
- [x] One Markdown article per feature in `material/docs`
- [x] Public vocabulary scanner and line counter scripts

## Phase 4: catalogue features not yet started

Each is owed unless the maintainer waives it by name; see `COMPLETENESS.md` for details.

- [ ] Language modes (English, Cantonese, bilingual) and playfulness sliders (`language`, `funny-emoji`)
- [ ] School mode, narration, action sounds, scheduled settings, focus modes, dim sum surprise (`school-mode`, `narration`, `action-sound-effects`, `scheduled-settings`, `adhd-modes`, `dim-sum`)
- [ ] Full Material 3 conformance audit of core Moodle pages (`material-design`)
- [ ] Appearance editor, colour picker and translator, logo customization (`appearance-editor`, `color-picker`, `logo-customization`)
- [ ] Accessibility tools menu and clipping checks (`accessibility-sizing`, `clipping-matrix`)
- [ ] Tabs, collapsible filters, rich controls, info buttons, quick add, presets (`tabs`, `collapse-filters`, `rich-controls`, `info-icon-guidance`, `settings-quick-add`, `blank-editors`)
- [ ] Two-key confirmation, element locks, support desk, unlock ladder, authenticator, QR pairing (`super-confirmation`, `element-locks`, `support-tickets`, `unlock-ladder`, `authenticator`, `qr-pairing`)
- [ ] Local history, exports, bulk actions, changelog viewer, offline docs, notification centre (`local-history`, `exports`, `bulk-actions`, `changelog`, `offline-docs`, `notifications`)
- [ ] Built-in tools: file converter, Base64, type generator, virtual disks, Minecraft server and world tools, Ollama, external editor, drawing (`file-converter`, `base64-tools`, `quicktype-studio`, `virtual-disk-manager`, `minecraft-server-manager`, `minecraft-world-generator`, `ollama-suite`, `external-editor`, `drawesome-drawing`)
- [ ] Extension support (`extension-support`)
- [ ] Version provenance on the first screen, status hub, update-coming-soon (`front-provenance`, `status-hub`, `update-coming-soon`)
