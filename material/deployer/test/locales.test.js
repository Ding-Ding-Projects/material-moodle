'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

require('../renderer/locales/en.js');
require('../renderer/locales/yue.js');
const { en, yue } = globalThis.MMD_LOCALES;
const dir = path.join(__dirname, '..', 'renderer', 'js');
const source = fs.readdirSync(dir).map((f) => fs.readFileSync(path.join(dir, f), 'utf8')).join('\n');

test('every literal translation key used by the renderer exists in English and Cantonese', () => {
  const keys = new Set([...source.matchAll(/\bt\('([a-zA-Z]+\.[a-zA-Z0-9_.]+)'/g)].map((m) => m[1]));
  for (const m of source.matchAll(/'((?:val|toast|palette|ctx|celebrate|fix|info|settings|server|tunnel|ob)\.[a-zA-Z0-9_]+)'/g)) keys.add(m[1]);
  assert.ok(keys.size > 100);
  const missingEn = [...keys].filter((k) => !(k in en));
  const missingYue = [...keys].filter((k) => !(k in yue));
  assert.deepEqual(missingEn, []);
  assert.deepEqual(missingYue, []);
});

test('dynamic keys for pages, steps, fixes and blocks exist', () => {
  const R = require('../renderer/regex');
  const { JOBS } = require('../src/lib/engine');
  const dynamic = [
    ...['server', 'tunnel', 'logs', 'settings', 'about'].map((p) => 'nav.' + p),
    ...new Set(Object.values(JOBS).flat().map((s) => 'step.' + s)),
    ...Object.keys(JOBS).map((j) => 'job.' + j),
    ...['pending', 'running', 'done', 'failed'].map((s) => 'status.' + s),
    ...R.BLOCKS.flatMap((b) => ['block.' + b.id, 'block.' + b.id + '.help']),
    ...['welcome', 'look', 'server', 'site', 'ready'].flatMap((s) => ['ob.' + s, 'ob.' + s + 'Title']),
  ];
  const errSrc = fs.readFileSync(path.join(__dirname, '..', 'src', 'lib', 'errors.js'), 'utf8');
  for (const m of errSrc.matchAll(/'([a-z]+)'\]/g)) dynamic.push('fix.' + m[1]);
  for (const m of errSrc.matchAll(/\[('[a-z]+'(?:, '[a-z]+')*)\]/g)) for (const id of m[1].match(/[a-z]+/g)) dynamic.push('fix.' + id);
  for (const k of dynamic) {
    assert.ok(k in en, 'missing in en: ' + k);
    assert.ok(k in yue, 'missing in yue: ' + k);
  }
});

test('both tables have the same keys and the same placeholders', () => {
  assert.deepEqual(Object.keys(yue).sort(), Object.keys(en).sort());
  for (const k of Object.keys(en)) {
    const ph = (s) => [...String(s).matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort().join(',');
    assert.equal(ph(yue[k]), ph(en[k]), k);
  }
});
