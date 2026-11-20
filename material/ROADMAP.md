# Material Moodle roadmap

A box is ticked only when the item is implemented and inspectable in the repository. Derived from
[`COMPLETENESS.md`](COMPLETENESS.md); the ids in brackets are its row ids.

## Phase 1: core plugins (web app)

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
- [ ] Activate the search-box regex builder: load `searchbars.js` from `main.js`, build it, verify on course and user lists (`regex-search`)
- [ ] Activate the right-click menu: load and build `contextmenu.js`, add the `ctx*` strings, let `Palette.open()` accept a starting query, add a setting and keyboard/touch openers (`context-menus`, `menu-shortcuts`)
- [ ] Per-assignment type labels: editing UI, display on course and activity pages, "Post a new Dropbox" buttons, `quickposts` setting, `db/upgrade.php` and version bump, privacy coverage for personal titles, backup and restore
- [ ] Command palette: every setting, activity and article as results, teleport and highlight (`command-palette`)
- [ ] Administrator first-run setup wizard in the app (`onboarding-wizard`)
- [ ] Administrator view to remove another person's passkeys
- [ ] Automated tests: PHPUnit for vocabulary validation, strict JSON, student creation, passkey manager; Behat or browser tests for the sign-in and palette flows

## Phase 2: deployer and site

- [ ] Desktop deployer user interface, tests, bundled stack files and icon (`progress-recovery`, `app-icon`, `bundled-dependencies`)
- [ ] Guided named-tunnel sign-in verified end to end
- [ ] Switch the Windows deployer installer to Squirrel.Windows (`squirrel-installer`) and add automatic updates (`auto-updates`)
- [ ] Project site with one article per feature, mobile layout, verified download button, PDF edition (`landing-site`, `feature-articles`, `mobile-friendly-site`, `installer-download-button`, `site-pdf-edition`)
- [ ] `material/design` folder with design references and parity checks (`design-folder`, `design-reference-parity`)
- [ ] Rename the site CSS classes that fail the public vocabulary scan (`material/site/assets/css/site.css`)

## Phase 3: release and repository records

- [ ] First successful run of `material-image.yml` and `material-release.yml` with real installers (`release-workflow`)
- [ ] Release notes proven to carry timing and line counts (`release-timing`, `release-line-counts`)
- [ ] Dim sum code names from the public catalogue with photo links (`dim-sum-code-names`, `release-dim-sum-photo`)
- [ ] Project README rewritten for Material Moodle with folded sections and a time estimate (`tabbed-readme`, `human-time-estimate`)
- [ ] Sanitized instruction mirror in README and `AGENTS.md` (`sanitized-instruction-copy`)
- [ ] Vocabulary hash lock on builds and pushes (`vocabulary-hash-lock`)
- [ ] Root `build.bat`, `build-installer.bat`, dependency fetcher and fresh build command (`build-entrypoints`, `fresh-build-run-command`, `dependency-fetcher`)
- [ ] Font licence files for Inter, Atkinson Hyperlegible Next and Roboto Mono, plus a fetch script with checksums (`vendored-fonts`)
- [ ] Postman collection for the three HTTP endpoints (`postman-collections`)
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
