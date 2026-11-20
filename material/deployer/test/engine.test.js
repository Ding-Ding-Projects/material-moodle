'use strict';
// End-to-end engine tests: real SSH protocol (in-process server), real shell, fake docker CLI.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { startServer } = require('./fixtures/ssh-server');
const { Engine } = require('../src/lib/engine');
const { SshSession } = require('../src/lib/ssh');
const { envToObject } = require('../src/lib/envfile');

const PASSWORD = 'pa$$ word';

async function setup(extraEnv = {}) {
  const home = fs.mkdtempSync(path.join(os.tmpdir(), 'mmd-home-'));
  const bin = path.join(home, '.fakebin');
  fs.mkdirSync(bin);
  fs.copyFileSync(path.join(__dirname, 'fixtures', 'fake-docker.sh'), path.join(bin, 'docker'));
  fs.chmodSync(path.join(bin, 'docker'), 0o755);
  fs.writeFileSync(path.join(bin, 'curl'), '#!/bin/sh\nexit 0\n', { mode: 0o755 });
  if (process.getuid && process.getuid() !== 0) {
    // On a real server the helper runs as root inside a container. A test runner that is not root records the
    // ownership change instead of making it.
    fs.writeFileSync(path.join(bin, 'chown'), '#!/bin/sh\necho "chown $*" >> "$HOME/chown-calls.log"\nexit 0\n',
      { mode: 0o755 });
  }
  const log = path.join(home, 'docker-calls.log');
  fs.writeFileSync(log, '');
  const env = { HOME: home, PATH: bin + ':' + process.env.PATH, FAKE_DOCKER_LOG: log, ...extraEnv };
  const { server, port } = await startServer({ password: PASSWORD, env });
  const hosts = {};
  const engine = new Engine({
    stackDir: path.join(__dirname, '..', 'stack'),
    knownHosts: { get: (id) => hosts[id], set: (id, fp) => { hosts[id] = fp; } },
  });
  const cfg = {
    host: '127.0.0.1', port: String(port), user: 'tester', auth: 'password', password: PASSWORD,
    siteName: 'Test Academy', adminUser: 'admin', adminEmail: 'admin@example.org', adminPassword: 'Adm1n!Secret', seed: '#006A6A', httpPort: '8080',
  };
  const done = () => { engine.dispose(); server.close(); fs.rmSync(home, { recursive: true, force: true }); };
  return { home, engine, cfg, hosts, calls: () => fs.readFileSync(log, 'utf8'), done };
}

test.before(() => {
  require('node:child_process').execFileSync(process.execPath, [path.join(__dirname, '..', 'scripts', 'sync-stack.js')]);
});

test('connection test reports the server and remembers its key', async () => {
  const s = await setup();
  try {
    const r = await s.engine.test(s.cfg);
    assert.equal(r.docker, true);
    assert.equal(r.dockerVersion, '28.5.1');
    assert.equal(r.deployed, false);
    assert.match(r.fingerprint, /^SHA256:/);
    assert.equal(s.hosts['127.0.0.1:' + s.cfg.port], r.fingerprint);
  } finally { s.done(); }
});

test('a changed server key stops the connection', async () => {
  const s = await setup();
  try {
    s.hosts['127.0.0.1:' + s.cfg.port] = 'SHA256:somethingElse';
    await assert.rejects(new SshSession({ get: (id) => s.hosts[id], set() {} }).connect(s.cfg), (e) => e.code === 'HOST_KEY_CHANGED');
  } finally { s.done(); }
});

test('exec feeds stdin, reports exit codes and streams lines', async () => {
  const s = await setup();
  try {
    const session = new SshSession({ get() {}, set() {} });
    await session.connect(s.cfg);
    const lines = [];
    const r = await session.exec('read x; echo "got $x"; echo err >&2; exit 3', { stdin: 'hello\n', onLine: (l) => lines.push(l) });
    assert.equal(r.code, 3);
    assert.equal(r.stdout, 'got hello\n');
    assert.deepEqual(lines.sort(), ['err', 'got hello']);
    session.close();
  } finally { s.done(); }
});

test('deploy runs every step, uploads the stack and never logs secrets', async () => {
  const s = await setup();
  try {
    s.engine.setServer(s.cfg);
    const job = await s.engine.run('deploy', 0);
    assert.equal(job.status, 'done', JSON.stringify(job.error));
    assert.ok(job.steps.every((x) => x.status === 'done'));
    const dir = path.join(s.home, 'material-moodle');
    assert.equal(fs.readFileSync(path.join(dir, 'docker-compose.yml'), 'utf8'), fs.readFileSync(path.join(__dirname, '..', 'stack', 'docker-compose.yml'), 'utf8'));
    const envText = fs.readFileSync(path.join(dir, '.env'), 'utf8');
    const e = envToObject(envText);
    assert.equal(e.MOODLE_SITE_NAME, 'Test Academy');
    assert.equal(e.MATERIAL_SEED_COLOR, '#006A6A');
    assert.equal((fs.statSync(path.join(dir, '.env')).mode & 0o777).toString(8), '600');
    assert.ok(fs.existsSync(path.join(dir, 'cloudflared')));
    assert.match(s.calls(), /compose -f docker-compose.yml pull moodle/);
    assert.match(s.calls(), /compose -f docker-compose.yml up -d --no-build/);
    assert.equal(job.result.url, 'http://127.0.0.1:8080');
    const logText = s.engine.logs.map((l) => l.text).join('\n');
    for (const secret of [PASSWORD, 'Adm1n!Secret', e.MOODLE_DB_PASSWORD]) assert.ok(!logText.includes(secret), 'secret leaked into the log');

    // Redeploying keeps the database password.
    const again = await s.engine.run('deploy', 0);
    assert.equal(again.status, 'done');
    assert.equal(envToObject(fs.readFileSync(path.join(dir, '.env'), 'utf8')).MOODLE_DB_PASSWORD, e.MOODLE_DB_PASSWORD);
  } finally { s.done(); }
});

test('a failed step reports fixes and retry continues from that step', async () => {
  const s = await setup({ FAKE_UP_FAIL_ONCE: path.join(os.tmpdir(), 'mmd-up-fail-' + process.pid + '-' + Date.now()) });
  try {
    s.engine.setServer(s.cfg);
    const failed = await s.engine.run('deploy', 0);
    assert.equal(failed.status, 'failed');
    assert.equal(failed.error.stepId, 'start');
    assert.equal(failed.error.step, 4);
    assert.equal(failed.error.fixes[0], 'port');
    assert.deepEqual(failed.steps.map((x) => x.status), ['done', 'done', 'done', 'done', 'failed', 'pending']);
    const before = s.calls().split('\n').length;
    const retried = await s.engine.run('deploy', 4);
    assert.equal(retried.status, 'done');
    const newCalls = s.calls().split('\n').slice(before - 1).join('\n');
    assert.doesNotMatch(newCalls, /pull/, 'retry must not repeat earlier steps');
  } finally { s.done(); }
});

test('the image is built from source when pulling fails', async () => {
  const s = await setup({ FAKE_PULL_FAIL: '1' });
  try {
    // git clone is replaced by a local fake so the test does not need the network.
    fs.writeFileSync(path.join(s.home, '.fakebin', 'git'), '#!/bin/sh\nfor last; do :; done\nmkdir -p "$last/.git"\n', { mode: 0o755 });
    s.engine.setServer(s.cfg);
    const job = await s.engine.run('deploy', 0);
    assert.equal(job.status, 'done', JSON.stringify(job.error));
    const override = fs.readFileSync(path.join(s.home, 'material-moodle', 'build.override.yml'), 'utf8');
    assert.match(override, new RegExp('context: "' + s.home + '/material-moodle/src"'));
    assert.match(s.calls(), /-f build.override.yml build moodle/);
  } finally { s.done(); }
});

test('tunnel jobs need a deployment first', async () => {
  const s = await setup();
  try {
    s.engine.setServer(s.cfg);
    const job = await s.engine.run('quick', 0);
    assert.equal(job.status, 'failed');
    assert.equal(job.error.fixes[0], 'deployfirst');
  } finally { s.done(); }
});

test('quick link, guided named tunnel and token tunnel all complete', async () => {
  const s = await setup();
  try {
    s.engine.setServer(s.cfg);
    assert.equal((await s.engine.run('deploy', 0)).status, 'done');
    const dir = path.join(s.home, 'material-moodle');

    const quick = await s.engine.run('quick', 0);
    assert.equal(quick.status, 'done', JSON.stringify(quick.error));
    assert.equal(quick.result.url, 'https://fake-quick-link.trycloudflare.com');
    let e = envToObject(fs.readFileSync(path.join(dir, '.env'), 'utf8'));
    assert.equal(e.MOODLE_WWWROOT, 'auto');
    assert.equal(e.COMPOSE_PROFILES, 'quicktunnel');

    const urls = [];
    s.engine.on('login-url', (u) => urls.push(u));
    const named = await s.engine.run('named', 0, { hostname: 'learn.example.org' });
    assert.equal(named.status, 'done', JSON.stringify(named.error));
    assert.equal(urls[0], 'https://dash.cloudflare.com/argotunnel?aud=&callback=https%3A%2F%2Flogin.cloudflareaccess.org%2Ffake');
    const config = fs.readFileSync(path.join(dir, 'cloudflared', 'config.yml'), 'utf8');
    assert.match(config, /tunnel: 6ff42ae2-765d-4adf-8112-31c55c1551ef/);
    assert.match(config, /hostname: learn.example.org/);
    assert.ok(fs.existsSync(path.join(dir, 'namedtunnel.override.yml')));
    if (process.getuid && process.getuid() === 0) {
      assert.equal(fs.statSync(path.join(dir, 'cloudflared')).uid, 65532);
    } else {
      assert.match(fs.readFileSync(path.join(dir, '..', 'chown-calls.log'), 'utf8'), /chown 65532:65532/);
    }
    e = envToObject(fs.readFileSync(path.join(dir, '.env'), 'utf8'));
    assert.equal(e.MOODLE_WWWROOT, 'https://learn.example.org');
    assert.ok(e.MOODLE_TRUSTED_HOSTS.split(',').includes('learn.example.org'));
    assert.match(s.calls(), /tunnel route dns --overwrite-dns material-moodle learn.example.org/);
    assert.match(s.calls(), /-f namedtunnel.override.yml up -d --no-build --force-recreate moodle cron/);

    // Signing in again is skipped when cert.pem exists.
    const second = await s.engine.run('named', 0, { hostname: 'learn.example.org' });
    assert.equal(second.result.loginSkipped, true);

    const token = 'eyJhIjoiMTIzNDU2Nzg5MCIsInQiOiJhYmNkZWYiLCJzIjoiWlhoaGJYQnNaUT09In0=';
    const tok = await s.engine.run('token', 0, { token, hostname: '' });
    assert.equal(tok.status, 'done', JSON.stringify(tok.error));
    e = envToObject(fs.readFileSync(path.join(dir, '.env'), 'utf8'));
    assert.equal(e.CLOUDFLARE_TUNNEL_TOKEN, token);
    assert.equal(e.COMPOSE_PROFILES, 'quicktunnel,tunnel');
    assert.ok(!s.engine.logs.some((l) => l.text.includes(token)), 'token leaked into the log');
  } finally { s.done(); }
});
