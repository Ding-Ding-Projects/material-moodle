'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const sh = require('../src/lib/shell');

const run = (script) => execFileSync('/bin/sh', ['-c', script], { encoding: 'utf8' });

test('shQuote leaves safe words alone and quotes everything else', () => {
  assert.equal(sh.shQuote('docker'), 'docker');
  assert.equal(sh.shQuote('/home/u/material-moodle'), '/home/u/material-moodle');
  assert.equal(sh.shQuote(''), "''");
  assert.equal(sh.shQuote('a b'), "'a b'");
  assert.equal(sh.shQuote("it's"), "'it'\\''s'");
  assert.throws(() => sh.shQuote('a\0b'), /NUL/);
});

test('shQuote survives a real shell for hostile input', () => {
  const nasty = ["it's", '$(touch /tmp/pwned)', '`id`', 'a;b|c&d', '"quoted"', 'new\nline', '*', '~', '\\', '$HOME', "'; rm -rf / #"];
  for (const v of nasty) assert.equal(run('printf %s ' + sh.shQuote(v)), v);
  assert.equal(run(sh.shJoin(['printf', '%s|%s', 'a b', "c'd"])), "a b|c'd");
});

test('asRoot wraps scripts for root, sudo with a password and passwordless sudo', () => {
  assert.equal(sh.asRoot('id -u', 'root'), "sh -c 'id -u'");
  assert.equal(sh.asRoot('id -u', 'sudo'), "sudo -S -p '' sh -c 'id -u'");
  assert.equal(sh.asRoot('id -u', 'sudo-n'), "sudo -n sh -c 'id -u'");
  assert.throws(() => sh.asRoot('x', 'nope'), /privilege mode/);
  assert.equal(run(sh.asRoot("printf %s 'a b'", 'root')), 'a b');
});

test('writeFileScript writes exact content without using stdin', () => {
  const fs = require('node:fs');
  const os = require('node:os');
  const path = require('node:path');
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'mmd-shell-'));
  const file = path.join(dir, 'conf file.yml');
  const content = "tunnel: x\nquote: 'y' $HOME `id`\n";
  run(sh.writeFileScript(file, content, '644'));
  assert.equal(fs.readFileSync(file, 'utf8'), content);
  assert.equal((fs.statSync(file).mode & 0o777).toString(8), '644');
  fs.rmSync(dir, { recursive: true });
});

test('composeScript adds override files only when they exist', () => {
  const fs = require('node:fs');
  const os = require('node:os');
  const path = require('node:path');
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'mmd compose '));
  const fake = 'docker() { printf "%s " "$@"; }; ';
  assert.equal(run(fake + sh.composeScript(dir, ['up', '-d'])).trim(), 'compose -f docker-compose.yml up -d');
  fs.writeFileSync(path.join(dir, 'namedtunnel.override.yml'), '');
  assert.equal(run(fake + sh.composeScript(dir, ['logs', '--format', '{{.Service}} x'])).trim(),
    'compose -f docker-compose.yml -f namedtunnel.override.yml logs --format {{.Service}} x');
  fs.rmSync(dir, { recursive: true });
});
