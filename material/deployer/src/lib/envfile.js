'use strict';
// Reading and writing the stack's .env file. Docker Compose interpolates "$" in unquoted and
// double-quoted values, so anything unusual is written in single quotes, which are literal.
const crypto = require('node:crypto');

const KEY_RE = /^[A-Z_][A-Z0-9_]*$/;
const BARE_RE = /^[A-Za-z0-9_.,:/@+=*-]*$/;

function formatEnvValue(value) {
  const s = String(value ?? '');
  if (/[\r\n\0]/.test(s)) throw new Error('Values cannot contain line breaks.');
  if (BARE_RE.test(s)) return s;
  if (!s.includes("'")) return "'" + s + "'";
  if (!/["$\\`]/.test(s)) return '"' + s + '"';
  throw new Error('A value cannot contain both a single quote and any of " $ \\ `.');
}

function parseEnvValue(raw) {
  let v = raw.trim();
  if (v.startsWith("'")) {
    const end = v.indexOf("'", 1);
    return end === -1 ? v.slice(1) : v.slice(1, end);
  }
  if (v.startsWith('"')) {
    let out = '';
    for (let i = 1; i < v.length; i++) {
      const c = v[i];
      if (c === '\\' && i + 1 < v.length) { const n = v[++i]; out += n === 'n' ? '\n' : n; continue; }
      if (c === '"') break;
      out += c;
    }
    return out;
  }
  const hash = v.search(/\s#/);
  if (hash !== -1) v = v.slice(0, hash);
  return v.trim();
}

/** Parse .env text into an ordered list of entries, keeping comments and blank lines. */
function parseEnv(text) {
  const entries = [];
  for (const line of String(text || '').split(/\r?\n/)) {
    const m = /^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$/.exec(line);
    if (m && !line.trim().startsWith('#')) entries.push({ key: m[1], value: parseEnvValue(m[2]) });
    else entries.push({ raw: line });
  }
  while (entries.length && entries[entries.length - 1].raw === '') entries.pop();
  return entries;
}

function envToObject(text) {
  const obj = {};
  for (const e of parseEnv(text)) if (e.key) obj[e.key] = e.value;
  return obj;
}

/** Return new .env text with the given keys set (null removes a key). Unknown lines are kept. */
function setEnvValues(text, values) {
  const entries = parseEnv(text);
  const pending = new Map(Object.entries(values));
  const out = [];
  for (const e of entries) {
    if (e.key && pending.has(e.key)) {
      const v = pending.get(e.key);
      pending.delete(e.key);
      if (v !== null && v !== undefined) out.push(e.key + '=' + formatEnvValue(v));
      continue;
    }
    out.push(e.key ? e.key + '=' + formatEnvValue(e.value) : e.raw);
  }
  for (const [k, v] of pending) {
    if (!KEY_RE.test(k)) throw new Error('Invalid variable name: ' + k);
    if (v !== null && v !== undefined) out.push(k + '=' + formatEnvValue(v));
  }
  return out.join('\n') + '\n';
}

/** Random string from an alphabet using rejection sampling (no modulo bias). */
function randomString(length, alphabet) {
  const limit = 256 - (256 % alphabet.length);
  let out = '';
  while (out.length < length) {
    for (const b of crypto.randomBytes(length * 2)) {
      if (b < limit) out += alphabet[b % alphabet.length];
      if (out.length === length) break;
    }
  }
  return out;
}

const ALNUM = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

function generateDbPassword() {
  return randomString(32, ALNUM);
}

/** An admin password that satisfies Moodle's default policy: upper, lower, digit and symbol. */
function generateAdminPassword(length = 18) {
  const sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '-_!@%^*+='];
  const chars = sets.map((s) => randomString(1, s));
  chars.push(...randomString(length - sets.length, sets.join('')));
  for (let i = chars.length - 1; i > 0; i--) {
    const j = crypto.randomInt(i + 1);
    [chars[i], chars[j]] = [chars[j], chars[i]];
  }
  return chars.join('');
}

function shortName(siteName) {
  const words = String(siteName || 'Moodle').replace(/[^A-Za-z0-9 ]/g, '').split(/\s+/).filter(Boolean);
  const s = words.length > 1 ? words.map((w) => w[0]).join('').toUpperCase() : (words[0] || 'Moodle');
  return s.slice(0, 20) || 'Moodle';
}

function trustedHosts(host, extra = []) {
  const list = ['localhost', 'localhost:*', '*.trycloudflare.com'];
  for (const h of [host, ...extra]) {
    if (!h) continue;
    for (const v of [h, h + ':*']) if (!list.includes(v)) list.push(v);
  }
  return list.join(',');
}

/**
 * Build the .env for a deployment. When the server already has a .env, the database password,
 * tunnel settings and anything else not managed here are kept, so redeploying never locks Moodle
 * out of its own database.
 */
function buildEnv(opts, existingText = '') {
  const existing = envToObject(existingText);
  const port = Number(opts.httpPort || 8080);
  const values = {
    MOODLE_SITE_NAME: opts.siteName || 'Material Moodle',
    MOODLE_SITE_SHORTNAME: opts.shortName || shortName(opts.siteName),
    MOODLE_ADMIN_USER: opts.adminUser || 'admin',
    MOODLE_ADMIN_PASSWORD: opts.adminPassword,
    MOODLE_ADMIN_EMAIL: opts.adminEmail || 'admin@example.com',
    MOODLE_DB_PASSWORD: existing.MOODLE_DB_PASSWORD || opts.dbPassword || generateDbPassword(),
    MOODLE_WWWROOT: existing.MOODLE_WWWROOT && existing.MOODLE_WWWROOT !== 'http://localhost:8080'
      && !/^http:\/\/[^/]+:\d+$/.test(existing.MOODLE_WWWROOT)
      ? existing.MOODLE_WWWROOT
      : 'http://' + opts.host + (port === 80 ? '' : ':' + port),
    MOODLE_TRUSTED_HOSTS: trustedHosts(opts.host, (existing.MOODLE_TRUSTED_HOSTS || '').split(',').filter(Boolean)),
    MOODLE_PORT: String(port),
    MATERIAL_SEED_COLOR: (opts.seed || '#8B5000').toUpperCase(),
    MATERIAL_ENABLE_PASSKEYS: '1',
    CLOUDFLARE_TUNNEL_TOKEN: existing.CLOUDFLARE_TUNNEL_TOKEN || '',
  };
  if (!values.MOODLE_ADMIN_PASSWORD) throw new Error('An admin password is required.');
  if (!/^#[0-9A-F]{6}$/.test(values.MATERIAL_SEED_COLOR)) throw new Error('The seed colour must look like #8B5000.');
  if (!(port >= 1 && port <= 65535)) throw new Error('The HTTP port must be between 1 and 65535.');
  const header = existingText.trim() ? existingText : '# Written by Material Moodle Deployer. Keep this file private.\n';
  return setEnvValues(header, values);
}

/** Add a compose profile to a COMPOSE_PROFILES list. */
function addProfile(list, profile) {
  const items = String(list || '').split(',').map((s) => s.trim()).filter(Boolean);
  if (!items.includes(profile)) items.push(profile);
  return items.join(',');
}

module.exports = {
  formatEnvValue, parseEnvValue, parseEnv, envToObject, setEnvValues, buildEnv,
  generateDbPassword, generateAdminPassword, randomString, shortName, trustedHosts, addProfile,
};
