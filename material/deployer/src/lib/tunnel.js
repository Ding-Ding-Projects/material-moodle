'use strict';
// Cloudflare tunnel helpers: parsing cloudflared output and generating its configuration files.

const CLOUDFLARED_IMAGE = 'cloudflare/cloudflared:latest';
const CONTAINER_DIR = '/home/nonroot/.cloudflared';
const TUNNEL_NAME = 'material-moodle';

/** Return the most recent https://<name>.trycloudflare.com address found in log text, or null. */
function parseQuickTunnelUrl(text) {
  const re = /https:\/\/([a-z0-9]+(?:-[a-z0-9]+)*)\.trycloudflare\.com\b/gi;
  let last = null;
  let m;
  while ((m = re.exec(String(text || '')))) {
    if (m[1].toLowerCase() === 'api') continue;
    last = 'https://' + m[1].toLowerCase() + '.trycloudflare.com';
  }
  return last;
}

/** Return the Cloudflare sign-in address printed by `cloudflared tunnel login`, or null. */
function parseLoginUrl(text) {
  const m = /https:\/\/dash\.cloudflare\.com\/argotunnel\?[^\s"'<>]+/.exec(String(text || ''));
  if (!m) return null;
  return isSafeLoginUrl(m[0]) ? m[0] : null;
}

function isSafeLoginUrl(url) {
  try {
    const u = new URL(url);
    return u.protocol === 'https:' && u.hostname === 'dash.cloudflare.com' && u.pathname === '/argotunnel';
  } catch {
    return false;
  }
}

const UUID_RE = /[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i;

/** Tunnel id from `cloudflared tunnel create` output ("Created tunnel X with id <uuid>"). */
function parseCreatedTunnelId(text) {
  const m = new RegExp('with id (' + UUID_RE.source + ')', 'i').exec(String(text || ''));
  return m ? m[1].toLowerCase() : null;
}

/** Tunnel id from `cloudflared tunnel list --output json` for the given name. */
function parseTunnelListId(jsonText, name = TUNNEL_NAME) {
  try {
    const start = String(jsonText).indexOf('[');
    const list = JSON.parse(String(jsonText).slice(start));
    const hit = list.find((t) => t && t.name === name);
    return hit && UUID_RE.test(hit.id) ? hit.id.toLowerCase() : null;
  } catch {
    return null;
  }
}

/** True for a fully qualified host name such as learn.example.org. */
function isValidHostname(host) {
  const h = String(host || '');
  if (h.length > 253 || !h.includes('.')) return false;
  return h.split('.').every((p) => /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i.test(p)) && !/^\d+$/.test(h.split('.').pop());
}

/** True for a token copied from the Cloudflare dashboard (base64 text, optionally after "--token"). */
function normaliseTunnelToken(input) {
  let s = String(input || '').trim();
  const m = /(?:^|\s)([A-Za-z0-9+/_=-]{40,})$/.exec(s);
  if (!m) return null;
  s = m[1];
  return s;
}

function buildConfigYml({ tunnelId, hostname, service = 'http://moodle:80' }) {
  if (!UUID_RE.test(tunnelId || '')) throw new Error('A valid tunnel id is required.');
  if (!isValidHostname(hostname)) throw new Error('Enter a full address such as learn.example.org.');
  return [
    '# Written by Material Moodle Deployer.',
    'tunnel: ' + tunnelId,
    'credentials-file: ' + CONTAINER_DIR + '/' + tunnelId + '.json',
    'ingress:',
    '  - hostname: ' + hostname.toLowerCase(),
    '    service: ' + service,
    '  - service: http_status:404',
    '',
  ].join('\n');
}

function buildNamedTunnelOverride() {
  return [
    '# Written by Material Moodle Deployer: runs the named Cloudflare tunnel.',
    'services:',
    '  namedtunnel:',
    '    image: ' + CLOUDFLARED_IMAGE,
    '    restart: unless-stopped',
    '    command: ["tunnel", "--no-autoupdate", "--config", "' + CONTAINER_DIR + '/config.yml", "run"]',
    '    volumes:',
    '      - ./cloudflared:' + CONTAINER_DIR,
    '    depends_on: [moodle]',
    '',
  ].join('\n');
}

function buildBuildOverride(srcDir) {
  return [
    '# Written by Material Moodle Deployer: builds the Moodle image from source when it cannot be pulled.',
    'services:',
    '  moodle:',
    '    build:',
    '      context: ' + JSON.stringify(srcDir),
    '      dockerfile: material/docker/Dockerfile',
    '',
  ].join('\n');
}

module.exports = {
  CLOUDFLARED_IMAGE, CONTAINER_DIR, TUNNEL_NAME,
  parseQuickTunnelUrl, parseLoginUrl, isSafeLoginUrl, parseCreatedTunnelId, parseTunnelListId,
  isValidHostname, normaliseTunnelToken, buildConfigYml, buildNamedTunnelOverride, buildBuildOverride,
};
