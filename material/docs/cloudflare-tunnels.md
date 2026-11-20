# Cloudflare tunnels

**Status:** Quick tunnels and named tunnels with a token: available in the Docker stack. Named tunnels with
guided sign-in: **In progress** in the [desktop deployer](desktop-deployer.md).

A Cloudflare tunnel publishes the site over HTTPS without opening a port on your router or server. The
`cloudflared` container connects out to Cloudflare and forwards visitors to `http://moodle:80` inside the Compose
network.

## Quick tunnel (temporary address)

Good for trying the site from another device. No Cloudflare account is needed.

```sh
# in material/docker/.env
MOODLE_WWWROOT=auto
docker compose --profile quicktunnel up -d
docker compose logs quicktunnel | grep -o 'https://[a-z0-9-]*\.trycloudflare\.com'
```

Open the printed address. Because `MOODLE_WWWROOT=auto` accepts `*.trycloudflare.com` (see
[Docker stack](docker-stack.md)), Moodle uses whichever quick tunnel address the visitor arrived on, and
`http://localhost:8080` keeps working on the host.

Limits: the address changes every time the `quicktunnel` container restarts, Cloudflare gives no uptime guarantee
for quick tunnels, and passkeys registered on one address do not work on the next. Do not use a quick tunnel for a
site people rely on.

## Named tunnel (fixed address)

A named tunnel serves your own host name, for example `moodle.example.org`, from a domain in your Cloudflare
account.

### With a token (available)

1. In the Cloudflare dashboard, create a tunnel (Networks > Tunnels) of the `cloudflared` type and copy its token.
2. Add a public host name for the tunnel with service `http://moodle:80`.
3. In `material/docker/.env` set:

   ```sh
   CLOUDFLARE_TUNNEL_TOKEN=<token>
   MOODLE_WWWROOT=https://moodle.example.org
   ```

4. `docker compose --profile tunnel up -d`.

The token is passed to `cloudflared` as `TUNNEL_TOKEN`. Moodle sees plain HTTP from the tunnel and turns on
`sslproxy` because the public address is HTTPS.

### With guided sign-in (in progress)

The desktop deployer will set up a named tunnel without the dashboard: it runs `cloudflared tunnel login` on the
server, shows the Cloudflare sign-in link (only `cloudflare.com` links are accepted) for you to open and approve,
then creates a tunnel, routes the chosen host name to it in DNS, writes the tunnel's `config.yml` and a Compose
override, restarts the stack and waits until the tunnel reports a connection. It can also accept a pasted token
instead of signing in. Progress is shown as steps (connect, prepare, sign in, create, route, configure, restart,
connected) that can be retried individually.

## Security

- The tunnel token and the credentials file created by `cloudflared tunnel login` give control of the tunnel.
  Keep them in `.env` or the server's private files, never in the repository or a support message.
- Only the `moodle` service is exposed through the tunnel. PostgreSQL stays on the private Compose network.
- With `MOODLE_WWWROOT=auto`, only host names matching `MOODLE_TRUSTED_HOSTS` are accepted as the site address,
  which prevents a forged `Host` header from changing links Moodle generates.

## How to verify

`docker compose logs tunnel` shows registered connections; opening the host name shows the Material sign-in page
over HTTPS with a valid certificate.

## Related articles

- [Docker stack](docker-stack.md)
- [Desktop deployer](desktop-deployer.md)
- [Passkey sign-in](passkey-sign-in.md)
