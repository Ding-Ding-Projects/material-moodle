'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const env = require('../src/lib/envfile');

const base = {
  host: '203.0.113.24', httpPort: '8080', siteName: "St Mary's College", adminUser: 'admin',
  adminPassword: 'Str0ng!Pass#word', adminEmail: 'admin@example.org', seed: '#8b5000',
};

test('buildEnv writes every variable the stack needs', () => {
  const text = env.buildEnv(base);
  const o = env.envToObject(text);
  assert.equal(o.MOODLE_SITE_NAME, "St Mary's College");
  assert.equal(o.MOODLE_SITE_SHORTNAME, 'SMC');
  assert.equal(o.MOODLE_ADMIN_PASSWORD, 'Str0ng!Pass#word');
  assert.equal(o.MOODLE_WWWROOT, 'http://203.0.113.24:8080');
  assert.equal(o.MOODLE_PORT, '8080');
  assert.equal(o.MATERIAL_SEED_COLOR, '#8B5000');
  assert.match(o.MOODLE_DB_PASSWORD, /^[A-Za-z0-9]{32}$/);
  assert.ok(o.MOODLE_TRUSTED_HOSTS.split(',').includes('203.0.113.24'));
  assert.ok(o.MOODLE_TRUSTED_HOSTS.split(',').includes('*.trycloudflare.com'));
  assert.equal(o.CLOUDFLARE_TUNNEL_TOKEN, '');
});

test('buildEnv quotes values so Compose does not interpolate or strip them', () => {
  const text = env.buildEnv(base);
  assert.match(text, /^MOODLE_ADMIN_PASSWORD='Str0ng!Pass#word'$/m);
  assert.match(text, /^MATERIAL_SEED_COLOR='#8B5000'$/m);
  assert.match(text, /^MOODLE_SITE_NAME="St Mary's College"$/m);
  assert.match(text, /^MOODLE_PORT=8080$/m);
});

test('buildEnv keeps the database password and tunnel settings of an existing deployment', () => {
  const first = env.buildEnv(base);
  const withTunnel = env.setEnvValues(first, { MOODLE_WWWROOT: 'https://learn.example.org', CLOUDFLARE_TUNNEL_TOKEN: 'abc', COMPOSE_PROFILES: 'tunnel' });
  const again = env.envToObject(env.buildEnv({ ...base, siteName: 'Renamed' }, withTunnel));
  assert.equal(again.MOODLE_DB_PASSWORD, env.envToObject(first).MOODLE_DB_PASSWORD);
  assert.equal(again.MOODLE_WWWROOT, 'https://learn.example.org');
  assert.equal(again.CLOUDFLARE_TUNNEL_TOKEN, 'abc');
  assert.equal(again.COMPOSE_PROFILES, 'tunnel');
  assert.equal(again.MOODLE_SITE_NAME, 'Renamed');
});

test('buildEnv follows a changed HTTP port when no tunnel address is set', () => {
  const first = env.buildEnv(base);
  const again = env.envToObject(env.buildEnv({ ...base, httpPort: '80' }, first));
  assert.equal(again.MOODLE_WWWROOT, 'http://203.0.113.24');
  assert.equal(again.MOODLE_PORT, '80');
});

test('runtime limits preserve existing choices and validate the effective upload pair before deployment', () => {
  const first = env.buildEnv({ ...base, phpLimits: { upload_max_filesize: '64M', post_max_size: '128M' } });
  const next = env.envToObject(env.buildEnv({ ...base, phpLimits: { post_max_size: '', memory_limit: '768M' } }, first));
  assert.equal(next.MATERIAL_PHP_UPLOAD_MAX_FILESIZE, '64M');
  assert.equal(next.MATERIAL_PHP_POST_MAX_SIZE, '128M');
  assert.equal(next.MATERIAL_PHP_MEMORY_LIMIT, '768M');
  assert.throws(() => env.buildEnv({ ...base, phpLimits: { post_max_size: '32M' } }, first), /request body/);
  assert.throws(() => env.buildEnv({ ...base, phpLimits: { memory_limit: 'lots' } }), /memory_limit/);
});

test('buildEnv rejects missing or invalid input', () => {
  assert.throws(() => env.buildEnv({ ...base, adminPassword: '' }), /admin password/);
  assert.throws(() => env.buildEnv({ ...base, seed: 'orange' }), /seed colour/);
  assert.throws(() => env.buildEnv({ ...base, httpPort: '70000' }), /HTTP port/);
});

test('formatEnvValue and parseEnvValue round-trip awkward values', () => {
  for (const v of ['plain', '', 'with space', 'hash # inside', "it's", 'dollar $HOME', 'a=b,c:d', '#8B5000', 'x"y']) {
    const line = 'K=' + env.formatEnvValue(v);
    assert.equal(env.envToObject(line).K, v, line);
  }
  assert.throws(() => env.formatEnvValue('bad\nline'), /line breaks/);
  assert.throws(() => env.formatEnvValue(`it's $x`), /single quote/);
});

test('setEnvValues updates, appends and removes keys while keeping comments', () => {
  const src = '# comment\nA=1\nB=two words # trailing\n\nC=3\n';
  const out = env.setEnvValues(src, { A: '9', C: null, D: 'new' });
  assert.equal(out, "# comment\nA=9\nB='two words'\n\nD=new\n");
  assert.throws(() => env.setEnvValues('', { 'bad-key': 'x' }), /Invalid variable name/);
});

test('generated passwords are strong and random', () => {
  const a = env.generateAdminPassword();
  assert.equal(a.length, 18);
  assert.match(a, /[A-Z]/); assert.match(a, /[a-z]/); assert.match(a, /\d/); assert.match(a, /[^A-Za-z0-9]/);
  assert.doesNotMatch(a, /['"$\\`]/);
  assert.notEqual(env.generateDbPassword(), env.generateDbPassword());
});

test('addProfile adds a compose profile once', () => {
  assert.equal(env.addProfile('', 'quicktunnel'), 'quicktunnel');
  assert.equal(env.addProfile('tunnel', 'quicktunnel'), 'tunnel,quicktunnel');
  assert.equal(env.addProfile('quicktunnel', 'quicktunnel'), 'quicktunnel');
});
