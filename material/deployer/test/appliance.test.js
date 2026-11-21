'use strict';
const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const http = require('http');
const crypto = require('crypto');
const A = require('../src/lib/appliance');

test('kindOf sorts release files', () => {
  assert.strictEqual(A.kindOf('material-moodle-appliance-x86_64.ova'), 'ova');
  assert.strictEqual(A.kindOf('material-moodle-appliance-x86_64.qcow2'), 'qcow2');
  assert.strictEqual(A.kindOf('material-moodle-appliance-arm64.img.xz'), 'pi');
  assert.strictEqual(A.kindOf('material-moodle-appliance-x86_64.img.xz'), 'usb');
  assert.strictEqual(A.kindOf('SHA256SUMS-appliance'), null);
});

test('parseSums reads sha256sum output', () => {
  const h = 'a'.repeat(64);
  assert.deepStrictEqual(A.parseSums(h + '  out/x.ova\n' + h.toUpperCase() + ' *y.qcow2\nbad line'), { 'x.ova': h, 'y.qcow2': h });
});

test('pickBridge prefers an adapter that is up', () => {
  const out = 'Name:            eth0\nStatus:          Down\n\nName:            wlan0\nStatus:          Up\n';
  assert.strictEqual(A.pickBridge(out), 'wlan0');
  assert.strictEqual(A.pickBridge('Name: eth0\nStatus: Down\n'), 'eth0');
  assert.strictEqual(A.pickBridge(''), null);
});

test('findProgram looks in PATH and the usual install folder', () => {
  const seen = (p) => p === '/usr/lib/virtualbox/VBoxManage';
  assert.strictEqual(A.findProgram('VBoxManage', 'linux', { PATH: '/nowhere' }, seen), '/usr/lib/virtualbox/VBoxManage');
  assert.strictEqual(A.findProgram('VBoxManage', 'linux', { PATH: '' }, () => false), null);
});

test('importVirtualBox picks a free name, bridges and starts', async () => {
  const calls = [];
  const runner = async (file, args) => {
    calls.push(args.join(' '));
    if (args[0] === 'list' && args[1] === 'vms') return '"Material Moodle" {1}\n';
    if (args[0] === 'list') return 'Name: en0\nStatus: Up\n';
    return '';
  };
  const r = await A.importVirtualBox('/tmp/x.ova', { vbox: '/bin/VBoxManage', runner });
  assert.deepStrictEqual(r, { name: 'Material Moodle 2', bridge: 'en0' });
  assert.ok(calls.includes('startvm Material Moodle 2 --type gui'));
});

test('download checks the checksum and deletes a bad file', async (t) => {
  process.env.MMD_APPLIANCE_TEST = '1';
  const body = Buffer.from('appliance bytes');
  const server = http.createServer((req, res) => { res.setHeader('content-length', body.length); res.end(body); });
  await new Promise((r) => server.listen(0, '127.0.0.1', r));
  t.after(() => server.close());
  const url = 'http://127.0.0.1:' + server.address().port + '/x.ova';
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'mmd-'));
  const good = crypto.createHash('sha256').update(body).digest('hex');
  const r = await A.download({ name: 'x.ova', url, size: body.length, sha256: good }, dir);
  assert.strictEqual(fs.readFileSync(r.path, 'utf8'), 'appliance bytes');
  const again = await A.download({ name: 'x.ova', url, size: body.length, sha256: good }, dir);
  assert.strictEqual(again.reused, true);
  await assert.rejects(A.download({ name: 'y.ova', url, size: body.length, sha256: 'b'.repeat(64) }, dir), { code: 'CHECKSUM' });
  assert.ok(!fs.existsSync(path.join(dir, 'y.ova')) && !fs.existsSync(path.join(dir, 'y.ova.part')));
});
