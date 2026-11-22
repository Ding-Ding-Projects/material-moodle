'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const test = require('node:test');

test('the Linux host helper preserves configuration and rolls back a failed readback', {
  skip: process.platform !== 'linux' || spawnSync('python3', ['--version']).status !== 0,
}, () => {
  const r = spawnSync('python3', ['-B', path.join(__dirname, 'running_limits_test.py')], { encoding: 'utf8' });
  assert.equal(r.status, 0, r.stderr);
});
