'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const tun = require('../src/lib/tunnel');

const QUICK_LOG = `quicktunnel-1  | 2026-11-20T10:00:00Z INF Thank you for trying Cloudflare Tunnel.
quicktunnel-1  | 2026-11-20T10:00:01Z INF Requesting new quick Tunnel on trycloudflare.com...
quicktunnel-1  | 2026-11-20T10:00:03Z INF +--------------------------------------------------------------------------------------------+
quicktunnel-1  | 2026-11-20T10:00:03Z INF |  Your quick Tunnel has been created! Visit it at (it may take some time to be reachable):  |
quicktunnel-1  | 2026-11-20T10:00:03Z INF |  https://bright-otter-lake-sample.trycloudflare.com                                        |
quicktunnel-1  | 2026-11-20T10:00:03Z INF +--------------------------------------------------------------------------------------------+`;

test('parseQuickTunnelUrl finds the address in cloudflared output', () => {
  assert.equal(tun.parseQuickTunnelUrl(QUICK_LOG), 'https://bright-otter-lake-sample.trycloudflare.com');
});

test('parseQuickTunnelUrl returns the newest address after a restart and ignores the API host', () => {
  const log = QUICK_LOG + '\nINF Requesting https://api.trycloudflare.com/tunnel\nINF |  https://second-wave-run.trycloudflare.com  |';
  assert.equal(tun.parseQuickTunnelUrl(log), 'https://second-wave-run.trycloudflare.com');
});

test('parseQuickTunnelUrl returns null without an address', () => {
  assert.equal(tun.parseQuickTunnelUrl('INF Starting tunnel\nERR failed to request quick Tunnel'), null);
  assert.equal(tun.parseQuickTunnelUrl(''), null);
  assert.equal(tun.parseQuickTunnelUrl('http://insecure.trycloudflare.com'), null);
});

test('parseLoginUrl extracts the Cloudflare sign-in link', () => {
  const out = `Please open the following URL and log in with your Cloudflare account:

https://dash.cloudflare.com/argotunnel?aud=&callback=https%3A%2F%2Flogin.cloudflareaccess.org%2FAbC123-_xyz%3D

Leave cloudflared running to download the cert automatically.`;
  assert.equal(tun.parseLoginUrl(out), 'https://dash.cloudflare.com/argotunnel?aud=&callback=https%3A%2F%2Flogin.cloudflareaccess.org%2FAbC123-_xyz%3D');
});

test('parseLoginUrl rejects anything that is not the Cloudflare dashboard', () => {
  assert.equal(tun.parseLoginUrl('https://evil.example/argotunnel?x=1'), null);
  assert.equal(tun.parseLoginUrl('http://dash.cloudflare.com/argotunnel?x=1'), null);
  assert.equal(tun.parseLoginUrl('no link here'), null);
  assert.equal(tun.isSafeLoginUrl('https://dash.cloudflare.com.evil.example/argotunnel?x'), false);
});

test('parseCreatedTunnelId and parseTunnelListId read tunnel ids', () => {
  const id = '6ff42ae2-765d-4adf-8112-31c55c1551ef';
  assert.equal(tun.parseCreatedTunnelId('Tunnel credentials written to /home/nonroot/.cloudflared/' + id + '.json.\nCreated tunnel material-moodle with id ' + id), id);
  assert.equal(tun.parseCreatedTunnelId('failed to create tunnel: tunnel with name already exists'), null);
  const list = JSON.stringify([{ id: 'aaaaaaaa-0000-0000-0000-000000000000', name: 'other' }, { id: id.toUpperCase(), name: 'material-moodle' }]);
  assert.equal(tun.parseTunnelListId('2026 INF something\n' + list), id);
  assert.equal(tun.parseTunnelListId('not json'), null);
});

test('buildConfigYml writes the tunnel, credentials and ingress rules', () => {
  const id = '6ff42ae2-765d-4adf-8112-31c55c1551ef';
  const yml = tun.buildConfigYml({ tunnelId: id, hostname: 'Learn.Example.org' });
  assert.equal(yml, [
    '# Written by Material Moodle Deployer.',
    'tunnel: ' + id,
    'credentials-file: /home/nonroot/.cloudflared/' + id + '.json',
    'ingress:',
    '  - hostname: learn.example.org',
    '    service: http://moodle:80',
    '  - service: http_status:404',
    '',
  ].join('\n'));
  assert.throws(() => tun.buildConfigYml({ tunnelId: 'nope', hostname: 'learn.example.org' }), /tunnel id/);
  assert.throws(() => tun.buildConfigYml({ tunnelId: id, hostname: 'bad host\nservice: x' }), /full address/);
});

test('isValidHostname accepts real host names only', () => {
  for (const h of ['learn.example.org', 'a.b.co', 'moodle-1.school.edu.hk']) assert.ok(tun.isValidHostname(h), h);
  for (const h of ['localhost', '-bad.example.org', 'bad_.example.org', '1.2.3.4', 'a..b', 'x'.repeat(64) + '.org', '']) assert.ok(!tun.isValidHostname(h), h);
});

test('normaliseTunnelToken accepts a bare token or a pasted install command', () => {
  const token = 'eyJhIjoiMTIzNDU2Nzg5MCIsInQiOiJhYmNkZWYiLCJzIjoiWlhoaGJYQnNaUT09In0=';
  assert.equal(tun.normaliseTunnelToken(token), token);
  assert.equal(tun.normaliseTunnelToken('  sudo cloudflared service install ' + token + ' '), token);
  assert.equal(tun.normaliseTunnelToken('cloudflared tunnel run --token ' + token), token);
  assert.equal(tun.normaliseTunnelToken('short'), null);
  assert.equal(tun.normaliseTunnelToken('token with; rm -rf /'), null);
});

test('override files define the named tunnel service and the source build', () => {
  const o = tun.buildNamedTunnelOverride();
  assert.match(o, /namedtunnel:/);
  assert.match(o, /"--config", "\/home\/nonroot\/.cloudflared\/config.yml", "run"/);
  assert.match(o, /- \.\/cloudflared:\/home\/nonroot\/.cloudflared/);
  const b = tun.buildBuildOverride('/home/ubuntu/material-moodle/src');
  assert.match(b, /context: "\/home\/ubuntu\/material-moodle\/src"/);
  assert.match(b, /dockerfile: material\/docker\/Dockerfile/);
});
