# Material Moodle release pipeline

Two GitHub Actions workflows build and publish Material Moodle. Both run on every push to `main` and can be
started by hand from the Actions tab (`workflow_dispatch`). Moodle's own workflows are not changed.

## Container image: `.github/workflows/material-image.yml`

Runs on pushes to `main` that touch `public/**`, `lib/**`, `material/docker/**` or `composer.*`.

1. **Build and smoke test**: builds `material/docker/Dockerfile` from the repository root with Buildx (GitHub
   Actions cache) and loads it locally as `material-moodle:smoke-test`. It writes `material/docker/.env` from
   `.env.example` with random passwords and `MOODLE_IMAGE` set to the local tag, then runs
   `docker compose -f material/docker/docker-compose.yml up -d --no-build db moodle`. The job waits up to
   30 minutes for `MATERIAL_MOODLE_INSTALLED` in the Moodle logs, requires HTTP 200 from `/login/index.php`
   and checks that the page contains `material-login` or the `theme_material` styles. The stack and its volumes
   are always removed at the end; the logs are printed if anything fails.
2. **Push**: only after the smoke test passes, rebuilds from the same cache and pushes to
   `ghcr.io/ding-ding-projects/material-moodle` with the tags `latest` (from `main`), `sha-<commit>` and, when the
   workflow is started by hand with the `release_tag` input, that release tag. The job has `packages: write`.

To tag an image for a release, run the workflow by hand and enter the release tag (for example
`material-v1.0.0-42`). The workflow checks out that tag before building.

## Release: `.github/workflows/material-release.yml`

| Job | Runs on | What it does |
| --- | --- | --- |
| `start` | Ubuntu | Records the start time (UTC) and works out the version, tag and code name with `meta.mjs`. |
| `checks` | Ubuntu | `php -l` on every PHP file of `theme_material`, `auth_passkey` and `local_materialtools`; `node --check` on their `amd/src`; runs `material/scripts/build-amd.sh` and fails if `amd/build` changes; `node material/scripts/verify-public-vocabulary.mjs`; `npm ci && npm test` in `material/deployer`. |
| `deployer` | Windows, macOS, Linux | `npm ci && npm run dist` in `material/deployer` and uploads the installers (`.exe`, `.dmg`, `.zip`, `.AppImage`, `.deb`). |
| `bundle` | Ubuntu | Builds `material-moodle-stack-<version>.zip` (compose file, `.env.example`, README) and the plugin zips. |
| `release` | Ubuntu | Collects all files, generates the notes and creates the GitHub release. Has `contents: write`. |

### Tag and code name

- The version is `$plugin->release` in `public/theme/material/version.php`.
- The tag is `material-v<version>-<run number>`, which is unique for every workflow run. If a whole run is
  re-run, later attempts add `-attempt<n>` so the tag stays unique. The release job also refuses to overwrite an
  existing release.
- The code name is `names[run number % 40]` from `dim-sum-names.json`, so the same run number always gets the
  same dim sum dish.

### Plugin zips

Each zip contains a single top-level folder named after the plugin, as Moodle's plugin installer expects:

| File | Folder | Install into |
| --- | --- | --- |
| `theme_material.zip` | `material/` | `public/theme/` |
| `auth_passkey.zip` | `passkey/` | `public/auth/` |
| `local_materialtools.zip` | `materialtools/` | `public/local/` |

### No code signing

The deployer installers are built without code signing. The deployer job sets
`CSC_IDENTITY_AUTO_DISCOVERY=false`, clears every signing and notarization variable, and passes
`--config.mac.identity=null` on macOS. The release notes state that the installers are unsigned and explain the
operating system warnings users will see. Do not add signing secrets to this workflow.

### Release notes

`notes.mjs` writes the notes. They contain the code name, what is included and the attached files, the unsigned
installer notice, install steps for the deployer, the Docker stack and the plugins, the line-count table from
`material/scripts/line-counts.mjs` (reported as unavailable if the script is missing or fails), and the
end-to-end workflow timing: the start time from the `start` job, the completion time taken in the `release`
job just before publishing, and the duration between them, all in UTC. The notes are also shown in the run
summary.

## Running the scripts locally

```sh
node material/release/meta.mjs --run-number 42
node material/release/notes.mjs --version 1.0.0 --tag material-v1.0.0-42 --codename "Char Siu Bao" \
  --started-at 2026-11-20T10:00:00Z --completed-at 2026-11-20T10:35:00Z \
  --assets-dir path/to/files --line-counts line-counts.md
```

`notes.mjs` prints to standard output unless `--output <file>` is given. `--completed-at` defaults to now.

## Files

- `dim-sum-names.json`: the 40 code names.
- `meta.mjs`: version, tag and code name for a run number.
- `notes.mjs`: release notes generator.
