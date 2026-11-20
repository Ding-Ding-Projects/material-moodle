'use strict';
// Validates the generated .env and override files with the real Docker Compose parser when it is installed.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync, spawnSync } = require('node:child_process');
const env = require('../src/lib/envfile');
const tun = require('../src/lib/tunnel');

const hasCompose = spawnSync('docker', ['compose', 'version'], { encoding: 'utf8' }).status === 0;

test('Docker Compose accepts the generated .env and override files', { skip: !hasCompose && 'docker compose is not installed' }, () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'mmd-compose-'));
  try {
    fs.copyFileSync(path.join(__dirname, '..', '..', 'docker', 'docker-compose.yml'), path.join(dir, 'docker-compose.yml'));
    let text = env.buildEnv({ host: 'learn.example.org', httpPort: '8081', siteName: "O'Neil School", adminPassword: 'A1!b$c#d e', adminEmail: 'a@example.org', seed: '#8B5000' });
    text = env.setEnvValues(text, { COMPOSE_PROFILES: 'quicktunnel', CLOUDFLARE_TUNNEL_TOKEN: 'tok$en' });
    fs.writeFileSync(path.join(dir, '.env'), text);
    fs.writeFileSync(path.join(dir, 'build.override.yml'), tun.buildBuildOverride(path.join(dir, 'src')));
    fs.writeFileSync(path.join(dir, 'namedtunnel.override.yml'), tun.buildNamedTunnelOverride());
    const out = execFileSync('docker', ['compose', '-f', 'docker-compose.yml', '-f', 'build.override.yml', '-f', 'namedtunnel.override.yml', 'config', '--format', 'json'],
      { cwd: dir, encoding: 'utf8', env: Object.fromEntries(Object.entries(process.env).filter(([k]) => k !== 'COMPOSE_PROFILES')) });
    const cfg = JSON.parse(out);
    assert.ok(cfg.services.quicktunnel, 'COMPOSE_PROFILES from .env enables the quick tunnel');
    assert.ok(cfg.services.namedtunnel);
    assert.deepEqual(cfg.services.namedtunnel.command, ['tunnel', '--no-autoupdate', '--config', '/home/nonroot/.cloudflared/config.yml', 'run']);
    assert.equal(cfg.services.namedtunnel.volumes[0].source, path.join(dir, 'cloudflared'));
    assert.equal(cfg.services.moodle.build.context, path.join(dir, 'src'));
    // `compose config` re-escapes literal dollar signs as $$ in its output so it can be parsed again.
    const literal = (v) => String(v).replace(/\$\$/g, '$');
    assert.equal(literal(cfg.services.moodle.environment.MOODLE_ADMIN_PASSWORD), 'A1!b$c#d e');
    assert.equal(cfg.services.moodle.environment.MOODLE_SITE_NAME, "O'Neil School");
    assert.equal(cfg.services.moodle.environment.MATERIAL_SEED_COLOR, '#8B5000');
    assert.equal(cfg.services.db.environment.POSTGRES_PASSWORD, env.envToObject(text).MOODLE_DB_PASSWORD);
    assert.equal(cfg.services.moodle.ports[0].published, '8081');
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
});
