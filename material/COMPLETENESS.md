# Material Moodle completeness inventory

Checked against the maintainer's required-feature catalogue (121 ids in its flat id list) on 2026-11-20, from the files present in
the working copy. Nothing here is a claim that the work is finished: the product is **not complete**.

**Rules used**

- **Done** means implemented in this repository and inspectable in the files listed. It does not mean verified in a
  running build unless the row says so; no item has screenshot or automated interaction evidence yet.
- **Partial** means some of the feature exists; the "Missing" column lists the rest.
- **Missing** means nothing for this feature exists in the Material overlay. Upstream Moodle behaviour is mentioned
  where it touches the feature but never counts as Done.
- **Not applicable** is used only where the catalogue's own scope excludes this product, with the reason.
- Something that is only designed or described counts as Missing or Partial, never Done.
- Ids marked † are plain-English aliases of the catalogue id.

**Surfaces of this product**

1. The Material Moodle web app: `theme_material` (`T` = `public/theme/material`), `auth_passkey`
   (`P` = `public/auth/passkey`) and `local_materialtools` (`L` = `public/local/materialtools`).
2. The Docker stack (`material/docker`).
3. The desktop deployer (`material/deployer`, Electron, in progress).
4. The project site (`material/site`, in progress).
5. The repository and its release pipeline (`.github/workflows/material-*.yml`, `material/release`, `material/scripts`).

The catalogue is written for every app, site and repository the maintainer owns, so it includes features (file
converter, game server manager and so on) that are far from a learning platform. They are still owed unless waived
by the maintainer; no waivers are recorded yet.

## Summary

| Status | Count |
| --- | ---: |
| Done | 5 |
| Partial | 28 |
| Missing | 73 |
| Not applicable | 15 |
| **Total** | **121** |

## Language, voice and personalization

| # | Id | Feature | Applies | Status | Evidence | Missing |
| ---: | --- | --- | --- | --- | --- | --- |
| 1 | `job-experience-document-label` | Job Experience Document product label | No: only for job-related apps; this is a learning platform. | Not applicable | | |
| 2 | `language` | English, playful Hong Kong Cantonese and bilingual language modes | Yes: web app, deployer, site. | Missing | Only English strings: `T/lang/en`, `P/lang/en`, `L/lang/en`. | Cantonese language packs for the three plugins, bilingual mode, deployer and site localization. |
| 3 | `funny-emoji` | Funny-level sliders and emoji switch | Yes. | Missing | | Per-language playfulness sliders wired to all copy, emoji switch. |
| 4 | `personal-vocabulary` | Personal vocabulary JSON upload | Yes. | Partial | Site-level custom vocabulary pack with strict validation and audience control: `L/classes/vocabulary.php`, `L/classes/strict_json.php`, `L/vocabulary.php`, `L/amd/src/vocabulary.js`. | Upload is per site by a manager, not per person in their own settings; no settings-search or palette entry; deployer and site have none. |
| 5 | `vocabulary-drop-apply` | Drop a vocabulary JSON anywhere to apply it | Yes. | Partial | Managers can drop a pack on any page; full-window headline and celebration: `L/amd/src/vocabulary.js`, `L/classes/hook_listener.php`. | Ordinary signed-in people cannot drop a personal pack; deployer has no drop target. |
| 6 | `school-mode` | Shared School mode switch | Yes. | Missing | | |
| 7 | `narration` | Spoken narrator with voice pickers | Yes. | Missing | | |
| 8 | `action-sound-effects` | Action sounds with a Settings switch | Yes. | Missing | | |
| 9 | `scheduled-settings` | Scheduled and external settings sources | Yes. | Missing | | Schedules for theme, seed colour, fonts, motion and language. |
| 10 | `display-name` | Renaming the app | Yes. | Partial | Upstream site full and short name, set at install from `MOODLE_SITE_NAME` (`material/docker/entrypoint.sh`). | Not a Material feature; deployer has no rename; no reset-to-shipped-name action. |
| 11 | `adhd-modes` | Focus and low-stimulation modes | Yes. | Missing | | |
| 12 | `dim-sum` | Dim sum surprise at startup | Yes. | Missing | | |
| 13 | `dim-sum-photo-source` | Dim sum photos only from the public catalogue | Yes (prohibition on local copies). | Done | No dim sum photos are tracked in the overlay; release code names are text only (`material/release/dim-sum-names.json`). | |

## Appearance and layout

| # | Id | Feature | Applies | Status | Evidence | Missing |
| ---: | --- | --- | --- | --- | --- | --- |
| 14 | `material-design` | Full Material 3 Expressive conformance | Yes. | Partial | Seed palette and restyle of core components: `T/classes/palette.php`, `T/scss/material.scss`, `T/lib.php`. | Many core Moodle templates keep Bootstrap markup and styling; no component registry; deployer UI and site not built. |
| 15 | `workflow-navigation` | Discrete destinations, working window chrome | Yes. | Missing | Moodle's own navigation only. | Frameless Material title bar and controls for the deployer; audit that every navigation element works. |
| 16 | `motion` | Material motion with complete reduced-motion path | Yes. | Partial | `T/scss/material.scss` (motion section), `T/classes/hook_listener.php` (`data-md-motion`), `T/amd/src/main.js`, `T/amd/src/celebrate.js`. | Per-person motion preference, scheduling, proof on every animated surface. |
| 17 | `live-charts` | Live-updating charts | No: the Material additions show no live-updating charts. | Not applicable | | Reassess if the deployer adds live progress charts. |
| 18 | `update-coming-soon` | "Update coming soon" with build progress | Yes (deployer, site). | Missing | | |
| 19 | `appearance-editor` | Per-element appearance editor | Yes. | Missing | Site-wide raw SCSS only (`T/settings.php`). | |
| 20 | `color-picker` | Infinite colour picker and translator | Yes. | Missing | Seed colour uses Moodle's standard colour setting (`T/settings.php`). | Continuous picker, colour-space translator, contrast display. |
| 21 | `logo-customization` | Logo presets and custom upload | Yes. | Missing | `T/lib.php` serves a `logo` file area but no setting uses it. | Presets, upload with validation, reset. |
| 22 | `overlay-panels` | Bounded, resizable overlays and panels | Yes. | Partial | Palette and context menu stay inside the viewport: `T/amd/src/palette.js`, `T/amd/src/contextmenu.js`. | Resizing and dragging, audit of Moodle's own popovers. |
| 23 | `collapse-filters` | Collapsible filters and statistics | Yes. | Missing | | |
| 24 | `accessibility-sizing` | Accessibility tools menu, no clipping | Yes. | Missing | Reduced motion and focus rings are respected (`T/scss/material.scss`). | Accessibility menu (text size, contrast, larger targets, read aloud), clipping checks. |
| 25 | `functional-ui` | Every control works, truthful empty states | Yes. | Partial | Empty states exist (passkey manager, vocabulary page: `P/templates/manage.mustache`, `L/templates/vocabulary.mustache`). | The theme setting text promises page filter boxes that are not active (`T/lang/en/theme_material.php`, `regexbuilder_desc`); no interaction tests. |

## Navigation, search and commands

| # | Id | Feature | Applies | Status | Evidence | Missing |
| ---: | --- | --- | --- | --- | --- | --- |
| 26 | `onboarding-wizard` | Practical first-run setup wizard | Yes. | Partial | Student first sign-in flow with progress (`P/welcome.php`, `P/templates/welcome.mustache`, `P/lib.php`); scripted defaults (`L/cli/configure.php`). | An in-app administrator setup wizard with Back/Next, save and resume; deployer wizard. |
| 27 | `extension-support` | Extension support with manifest, protocol and consent | Yes. | Missing | Moodle's plugin system is upstream and does not meet the contract. | |
| 28 | `tabs` | Browser-style tabs, every tab open by default | Yes. | Missing | | |
| 29 | `regex-search` | Regex builder on every search surface | Yes. | Partial | Builder in the command palette (`T/amd/src/palette.js`); search-box builder written but inactive (`T/amd/src/searchbars.js`, no build output, not loaded). | Activate and build `searchbars.js`; replacement, explanation, flavours, history; deployer and site search. |
| 30 | `command-palette` | Ctrl+Shift+F command palette | Yes. | Partial | `T/amd/src/palette.js`, `T/classes/hook_listener.php`. | Every setting, page, article and control as results; live controls in rows; teleport and highlight of the exact element; deployer and site palette. |
| 31 | `context-menus` | Context menu on every element | Yes. | Partial | Written but inactive: `T/amd/src/contextmenu.js`. | Load and build it, add its strings, filter field with regex builder, Edit appearance and Lock entries, touch and keyboard openers. |
| 32 | `menu-shortcuts` | Shortcuts shown in menus | Yes. | Partial | Shortcut labels in `T/amd/src/contextmenu.js` (inactive). | Derive labels from the key handlers; platform notation; activation. |
| 33 | `rich-controls` | Real controls wherever a value is shown | Yes. | Missing | | |
| 34 | `guided-forms` | Guided forms with suggestions and inline validation | Yes. | Partial | Add a student suggests usernames, generates passwords, validates inline: `L/classes/form/newstudent.php`, `L/classes/student_creator.php`. | Guided wizards above controls; other forms. |
| 35 | `info-icon-guidance` | Info button above each control | Yes. | Missing | One Moodle help icon on the username field (`L/classes/form/newstudent.php`). | |
| 36 | `settings-explanations` | Settings explain themselves, with provenance | Yes. | Partial | Every setting has a description string (`T/lang/en`, `P/lang/en`, `L/lang/en`). | Value provenance line (saved value versus default), coverage test. |
| 37 | `settings-quick-add` | Quick add beside Settings pointers | Yes. | Missing | The passkey manager points to an administrator without a quick action (`P/templates/manage.mustache`). | |
| 38 | `blank-editors` | Presets for blank editors | Yes. | Missing | | Presets for the vocabulary pack and credentials message. |

## Safety, locks and authentication

| # | Id | Feature | Applies | Status | Evidence | Missing |
| ---: | --- | --- | --- | --- | --- | --- |
| 39 | `super-confirmation` | Two-key confirmation for destructive actions | Yes. | Missing | Passkey removal and vocabulary clearing use plain confirmations (`P/manage.php`, `L/vocabulary.php`). | |
| 40 | `element-locks` | Locks on every element | Yes. | Missing | | |
| 41 | `support-tickets` | Support Tickets recovery desk | Yes. | Missing | | |
| 42 | `unlock-ladder` | The unlock ladder | Yes (Moodle can lock accounts out). | Missing | | |
| 43 | `authenticator` | Built-in authenticator | Yes. | Missing | | |
| 44 | `qr-pairing` | QR pairing for one-time-password setup | Yes. | Missing | Moodle's upstream TOTP factor exists but was not reviewed against the contract. | |
| 45 | `honest-monetization` | Free by default, no payment walls | Yes. | Done | GPL-licensed, no purchase or payment surfaces in the overlay (`COPYING.txt`). | |

## Records, history and messages

| # | Id | Feature | Applies | Status | Evidence | Missing |
| ---: | --- | --- | --- | --- | --- | --- |
| 46 | `local-history` | Local version history with undo | Yes. | Missing | | History for vocabulary packs, wording and settings. |
| 47 | `exports` | Export everything in every format | Yes. | Missing | Upstream backups and privacy exports only. | Export of vocabulary pack, wording, labels, passkey list. |
| 48 | `bulk-actions` | Bulk actions everywhere | Yes. | Partial | Several students can be added to the vocabulary audience at once (`L/classes/form/vocabusers.php`). | Multi-select and bulk actions on passkeys, chosen students, labels. |
| 49 | `changelog` | Changelog viewer | Yes. | Missing | | |
| 50 | `offline-docs` | Offline documentation browser in the app | Yes. | Missing | Articles exist as Markdown only (`material/docs`). | |
| 51 | `rendered-provider-text` | Provider text rendered as Markdown | Yes (deployer will show release notes). | Missing | | |
| 52 | `notifications` | Non-blocking notifications and a centre | Yes. | Partial | Success and error moments on Moodle's alerts and toasts (`T/amd/src/celebrate.js`). | Notification centre with history, search and bulk management. |

## Built-in tools

| # | Id | Feature | Applies | Status | Evidence | Missing |
| ---: | --- | --- | --- | --- | --- | --- |
| 53 | `file-converter` | Local file converter | Yes. | Missing | | |
| 54 | `base64-tools` | Base64 toolkit | Yes. | Missing | | |
| 55 | `quicktype-studio` | Type generator and JSON editor | Yes. | Missing | | |
| 56 | `virtual-disk-manager` | Windows virtual disk manager | Yes (deployer on Windows; article on the site). | Missing | | |
| 57 | `minecraft-server-manager` | Minecraft server creator and manager | Yes. | Missing | | |
| 58 | `minecraft-world-generator` | Minecraft world generator | Yes. | Missing | | |
| 59 | `ollama-suite` | Ollama installer and manager | Yes. | Missing | | |
| 60 | `external-editor` | External editor and VS Code hand-off | Yes (deployer owns stack files). | Missing | | |
| 61 | `download-handoff` | Browser-extension download hand-off | No: there is no browser extension. | Not applicable | | |
| 62 | `forge-publishing` | Publishing a repository to a forge | No: the product never publishes repositories. | Not applicable | | |
| 63 | `progress-recovery` | Progress where it started, recovery where it broke | Yes. | Partial | Deployer engine has per-step status and retry (`material/deployer/src/lib/engine.js`, unverified). | User interface, verification. |
| 64 | `drawesome-drawing` | Drawing and annotation | Yes. | Missing | | |

## Completeness and evidence

| # | Id | Feature | Applies | Status | Evidence | Missing |
| ---: | --- | --- | --- | --- | --- | --- |
| 65 | `completeness-parity` | Per-surface completeness inventory | Yes. | Partial | This file. | Separate rows per surface; a test that fails when the inventory drifts. |
| 66 | `front-provenance` | Version and updated-at time on the first screen | Yes. | Missing | | |
| 67 | `product-evidence` | Real screenshots of every surface | Yes. | Missing | `material/site/screenshots` is empty. | |
| 68 | `screen-recording` | Committed screen recording | Yes. | Missing | | |
| 69 | `design-reference-parity` | Checked-in design reference parity | Yes, once `material/design` exists. | Missing | `material/design` does not exist. | |
| 70 | `design-folder` | Design surfaces kept in the repository | Yes. | Missing | `material/design` does not exist. | |
| 71 | `clipping-matrix` † | Clipping checks for every built surface | Yes. | Missing | | |
| 72 | `public-screenshot-gallery` † | Public screenshot gallery for private projects | No: it exists for private repositories; this repository is public and carries its own screenshots. | Not applicable | | |

## Project site and shared links

| # | Id | Feature | Applies | Status | Evidence | Missing |
| ---: | --- | --- | --- | --- | --- | --- |
| 73 | `landing-site` | Material project site carrying every feature | Yes. | Partial | Started: `material/site/scripts/build.mjs`, `material/site/assets`. | Pages, deployment, every feature. |
| 74 | `mobile-friendly-site` † | Mobile-friendly site | Yes. | Missing | | |
| 75 | `installer-download-button` | Verified installer download on the site's home page | Yes. | Missing | | |
| 76 | `feature-articles` | One article per feature on the site | Yes. | Missing | Markdown articles exist in `material/docs`, not yet on the site. | |
| 77 | `vendored-fonts` | Complete, scripted font vendoring | Yes. | Partial | Fonts in `T/fonts` and `material/site/assets/fonts`. | Fetch script, SHA-256 manifest, licence files for Inter, Atkinson Hyperlegible Next and Roboto Mono (only Roboto Flex's OFL is present), non-Latin subsets. |
| 78 | `shared-link-embed` | Real product picture when a link is shared | Yes. | Missing | | `social-preview.png` and its script. |
| 79 | `homepage-link` | Site linked from the repository's About field | Yes. | Missing | | |
| 80 | `vocabulary-unlock-boundary` | Custom wording only after authentication | Yes (the vocabulary pack). | Partial | Pack served only to eligible signed-in people and applied client-side: `L/vocab.php`, `L/classes/vocabulary.php` (`eligible()`), `L/classes/hook_listener.php`. | Automated test proving signed-out pages and error text never carry the pack. |
| 81 | `private-site-app` † | Private site delivered as a desktop app | No: for private repositories only. | Not applicable | | |
| 82 | `site-pdf-edition` † | Phone-sized PDF edition of the site | Yes. | Missing | | |

## Status reporting

| # | Id | Feature | Applies | Status | Evidence | Missing |
| ---: | --- | --- | --- | --- | --- | --- |
| 83 | `status-hub` | Status Hub registration and status surface | Yes. | Missing | | |
| 84 | `discord-status-bridge` | Discord status bridge | No: applies to the Status Hub app only. | Not applicable | | |
| 85 | `panic-webhooks` | Verified panic dispatch | No: applies to the Status Hub app only. | Not applicable | | |
| 86 | `tidbyt-displays` | Tidbyt status frames | No: Status Hub only, and suspended. | Not applicable | | |

## Build and install

| # | Id | Feature | Applies | Status | Evidence | Missing |
| ---: | --- | --- | --- | --- | --- | --- |
| 87 | `build-entrypoints` | Root `build.bat` and `build-installer.bat` | Yes. | Missing | | |
| 88 | `fresh-build-run-command` | One fresh Windows build-and-run command | Yes. | Missing | | |
| 89 | `dependency-fetcher` | One-click dependency fetcher | Yes. | Missing | | |
| 90 | `bundled-dependencies` | The app bundles its dependencies | Yes. | Partial | The Docker image bundles PHP, extensions and Moodle's libraries (`material/docker/Dockerfile`). | Deployer must bundle the stack files and work offline; unverified. |
| 91 | `squirrel-installer` | Squirrel.Windows installers | Yes (deployer on Windows). | Missing | Deployer targets NSIS and portable (`material/deployer/package.json`), which conflicts with the contract. | |
| 92 | `self-signing` | Sign only where the format requires it | Yes. | Partial | Release workflow builds unsigned and disables certificate discovery (`.github/workflows/material-release.yml`, `material/release/README.md`). | Not yet run; no verification of `NotSigned` output. |
| 93 | `auto-updates` | Automatic updates with a ready banner | Yes (deployer). | Missing | | |
| 94 | `app-icon` | Original logo and packaged icon | Yes. | Missing | `material/deployer/build` is empty; `package.json` names `build/icon.png`. | |

## Release pipeline

| # | Id | Feature | Applies | Status | Evidence | Missing |
| ---: | --- | --- | --- | --- | --- | --- |
| 95 | `release-workflow` | A real release on every push | Yes. | Partial | `.github/workflows/material-release.yml`, `.github/workflows/material-image.yml` (both new, not yet run). | A successful run with a real installer. |
| 96 | `release-timing` | Workflow timing in release notes | Yes. | Partial | `material/release/notes.mjs` writes start, completion and duration. | Not yet proven on a real release. |
| 97 | `release-line-counts` | Line counts in release notes | Yes. | Partial | `material/scripts/line-counts.mjs`, called by the release workflow. | Not yet proven on a real release; tests category is empty. |
| 98 | `release-dim-sum-photo` | Public dim sum photo reference in releases | Yes. | Missing | | Link to the published photo for each code name. |
| 99 | `dim-sum-code-names` | Dim sum release code names | Yes. | Partial | `material/release/dim-sum-names.json`, `material/release/meta.mjs`. | Names from the public catalogue with English and Traditional Chinese names; one-use-per-project record; shown in the app. |
| 100 | `ci-bootstrap` | CI dependency bootstrap and inventory | Yes. | Missing | | |
| 101 | `runner-selection` | Live runner selection | Yes. | Missing | | Recorded runner inspection; the workflows use hosted runners. |
| 102 | `encrypted-public-builder` | Encrypted public builder | No: for private repositories only. | Not applicable | | |

## Repository records

| # | Id | Feature | Applies | Status | Evidence | Missing |
| ---: | --- | --- | --- | --- | --- | --- |
| 103 | `tabbed-readme` | Compact README with folded sections | Yes. | Missing | `README.md` is still upstream Moodle's. | |
| 104 | `human-time-estimate` | Hand-written time estimate in the README | Yes. | Missing | | |
| 105 | `sanitized-instruction-copy` | Sanitized mirror of the shared instructions | Yes. | Missing | | |
| 106 | `agents-md-vocabulary-block` | Vocabulary discipline block in `AGENTS.md` | Yes. | Done | `AGENTS.md`. | |
| 107 | `vocabulary-hash-lock` | Vocabulary hash lock on builds and pushes | Yes. | Missing | The scanner (`material/scripts/verify-public-vocabulary.mjs`) is a separate check, not a hash lock. | |
| 108 | `roadmap-checklist` | `ROADMAP.md` checklist | Yes. | Done | `material/ROADMAP.md`. | |
| 109 | `feature-docs` | One Markdown file per feature | Yes. | Done | `material/docs/README.md` and 19 articles. | Articles for features not built yet. |
| 110 | `postman-collections` | Postman collections for HTTP APIs | Yes: three endpoints exist. | Missing | Endpoints listed in `material/docs/README.md`. | |
| 111 | `wiki-site-sync` † | Wiki and site kept current | Yes. | Missing | | |
| 112 | `handoff-record` | `HANDOFF.md` and an issue handoff section | Yes. | Partial | `material/HANDOFF.md`. | Handoff section on a GitHub issue. |
| 113 | `closeout-prompt` | Committed `CLOSEOUT_PROMPT.md` | Yes. | Missing | | |
| 114 | `discussion-records` | Discussions as the running record | Yes. | Missing | Cannot be checked from the working copy. | |
| 115 | `project-board` | GitHub Project linked to the repository | Yes. | Missing | Cannot be checked from the working copy. | |
| 116 | `operational-skill` | Repository operational skill | Yes. | Missing | | |
| 117 | `instructions-single-file-editions` † | Single-file editions of the shared instructions | No: only for the instructions repository. | Not applicable | | |
| 118 | `readme-prompt-banner` | README opens with the instructions prompt banner | No: only for the instructions repository. | Not applicable | | |
| 119 | `project-profile` | Project memory folders | No: only for the instructions repository. | Not applicable | | |
| 120 | `roblox-model-catalogue` | Shared Roblox models used in games | No: games only. | Not applicable | | |
| 121 | `roblox-visual-realism` | Roblox visual realism | No: games only. | Not applicable | | |
