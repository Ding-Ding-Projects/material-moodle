# Docker stack

**Status:** Available. Files: `material/docker` (Dockerfile, `docker-compose.yml`, `entrypoint.sh`, `config.php`,
`php.ini`, `apache.conf`, `.env.example`).

## What it runs

The image workflow builds and exercises Linux AMD64 and ARM64 on their respective native hosts.
It assembles the platform manifests under a `sha-<full-commit>` tag and checks both architectures
before advancing the distribution tags. Compose selects the platform matching the server.

| Service | Image | Purpose |
| --- | --- | --- |
| `db` | `postgres:17` | Database, with a health check. Data in the `db` volume. |
| `moodle` | `ghcr.io/ding-ding-projects/material-moodle:latest`, or built from this repository | Apache and PHP 8.3 serving Moodle on port 80 inside the container. Data in the `moodledata` volume. |
| `cron` | same image | Runs `admin/cli/cron.php` every minute once Moodle is installed. |
| `quicktunnel` | `cloudflare/cloudflared` | Profile `quicktunnel`: a temporary public `trycloudflare.com` address. |
| `tunnel` | `cloudflare/cloudflared` | Profile `tunnel`: a named Cloudflare tunnel using `CLOUDFLARE_TUNNEL_TOKEN`. |

The image is built from the repository root: `docker build -f material/docker/Dockerfile -t material-moodle .`
It includes the PostgreSQL, zip, gd (FreeType, JPEG, WebP), intl, soap, exif and opcache extensions and
Ghostscript for PDF annotation.

## Starting it

```sh
cd material/docker
cp .env.example .env
# Edit .env: at least MOODLE_DB_PASSWORD. Leave MOODLE_ADMIN_PASSWORD empty to choose the administrator in the browser.
docker compose up -d            # uses the published image
docker compose up -d --build    # builds from this checkout instead
docker compose logs -f moodle   # wait for MATERIAL_MOODLE_INSTALLED
```

Then open `http://localhost:8080`. With `MOODLE_ADMIN_PASSWORD` empty, every page shows **Set up your site** until
someone fills it in: the first person to finish it chooses the administrator username, password, name and email,
is signed in, and the page locks. Do this straight away on a new site. With `MOODLE_ADMIN_PASSWORD` set, sign in
with `MOODLE_ADMIN_USER` and that password instead.

## What the entrypoint does

1. Waits up to three minutes for PostgreSQL.
2. **First start** (no `mdl_config` table): if `MOODLE_ADMIN_PASSWORD` is empty, generates a random throwaway
   password and marks the site as waiting for first-visit setup; then runs
   `admin/cli/install_database.php` with the site name, short name and administrator from `.env`, then runs
   `local/materialtools/cli/configure.php --first-run`, which selects the Material theme, turns on Boost colour
   modes, applies `MATERIAL_SEED_COLOR` if it is a `#RRGGBB` colour, enables passkey sign-in unless
   `MATERIAL_ENABLE_PASSKEYS=0`, sets the vocabulary audience to "Selected students and cohorts", and purges caches.
   It prints `MATERIAL_MOODLE_INSTALLED`.
3. **Later starts:** runs `admin/cli/upgrade.php --non-interactive`. Settings changed in the web interface are not
   overwritten.
4. Starts Apache in the foreground.

The `cron` service waits until Moodle is installed, then loops every 60 seconds.

The web health check requests `/login/index.php`, with a 10-minute start period for the first install.
The scheduled-task worker reports an active CLI child or a successful pass within the last three minutes.
A failed pass stays unhealthy until the worker starts a new attempt.

## Environment variables

| Variable | Default | Meaning |
| --- | --- | --- |
| `MOODLE_SITE_NAME`, `MOODLE_SITE_SHORTNAME` | Material Moodle, Moodle | Used at first install. |
| `MOODLE_ADMIN_USER`, `MOODLE_ADMIN_PASSWORD`, `MOODLE_ADMIN_EMAIL` | admin, empty, admin@example.com | First administrator. Empty password: the first visitor chooses the administrator on the setup page. |
| `MOODLE_DB_PASSWORD` | none (required by Compose) | Database password. `MOODLE_DB_NAME`, `MOODLE_DB_USER`, `MOODLE_DB_HOST`, `MOODLE_DB_PORT` are also read. |
| `MOODLE_WWWROOT` | `http://localhost:8080` | Public address, or `auto` (see below). |
| `MOODLE_TRUSTED_HOSTS` | `localhost,localhost:*,*.trycloudflare.com` | Host patterns accepted when `MOODLE_WWWROOT=auto`. |
| `MOODLE_FALLBACK_WWWROOT` | `http://localhost:8080` | Used by `auto` when the request host is not trusted. |
| `MOODLE_PORT` | 8080 | Host port. |
| `MOODLE_IMAGE` | the published image | Image for `moodle` and `cron`. |
| `MATERIAL_SEED_COLOR` | `#8B5000` | Seed colour applied at first start. |
| `MATERIAL_ENABLE_PASSKEYS` | 1 | Enable passkey sign-in at first start. |
| `CLOUDFLARE_TUNNEL_TOKEN` | empty | Named tunnel token for the `tunnel` profile. |

### `MOODLE_WWWROOT=auto`

Moodle needs one fixed address. With `auto`, `config.php` uses the address the visitor used, but only when the
request's host matches one of `MOODLE_TRUSTED_HOSTS` and contains only host-name characters; otherwise it uses
`MOODLE_FALLBACK_WWWROOT`. The scheme is HTTPS when the proxy sends `X-Forwarded-Proto: https` or the host is a
`trycloudflare.com` address. When the address is HTTPS but the container receives plain HTTP, `sslproxy` is turned
on because TLS ends at the tunnel or proxy.

## Updating

Pull the new image (`docker compose pull && docker compose up -d`) or rebuild from a newer checkout. The entrypoint
runs Moodle's upgrade on start. Back up both volumes before upgrading.

## When something goes wrong

- **Someone else finished the setup page first.** Stop the stack, remove the volumes (`docker compose down -v`) and
  start again, or reset the administrator password with `admin/cli/reset_password.php` inside the container.
- **"Database did not become ready."** Check `docker compose logs db` and the database password.
- **Redirect loops or "Incorrect access detected".** `MOODLE_WWWROOT` does not match the address you use. Set it to
  the exact address, or use `auto` with a matching `MOODLE_TRUSTED_HOSTS` entry.
- **The upgrade fails on start.** The entrypoint continues and serves the site so you can read the error in the
  web interface; check `docker compose logs moodle`.

## Security

- `.env` holds passwords and the tunnel token. Keep it out of version control (it is ignored by `.dockerignore`);
  the example file contains placeholders only.
- PostgreSQL is not published on a host port.
- Change the example passwords before the first start; the administrator password is only read at first install.

## How to verify

`docker compose ps` shows `db` healthy and `moodle` healthy after the first install; `curl -I
http://localhost:8080/login/index.php` returns 200 and the page uses the Material sign-in layout. The
`Material image` workflow (`.github/workflows/material-image.yml`) runs the same check in CI before publishing the
image.

## Related articles

- [Cloudflare tunnels](cloudflare-tunnels.md)
- [Desktop deployer](desktop-deployer.md)
- [Administration settings reference](administration-settings.md)
