'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { classifyError } = require('../src/lib/errors');

test('classifyError maps common failures to fixes, then adds step defaults', () => {
  assert.deepEqual(classifyError('connect', 'All configured authentication methods failed'), ['auth', 'checkdetails']);
  assert.equal(classifyError('connect', 'connect ECONNREFUSED 1.2.3.4:22')[0], 'refused');
  assert.equal(classifyError('start', 'Bind for 0.0.0.0:8080 failed: port is already allocated')[0], 'port');
  assert.equal(classifyError('connect', '', 'HOST_KEY_CHANGED')[0], 'hostkey');
  assert.ok(classifyError('unknown', 'nothing known').includes('retry'));
  assert.ok(classifyError('health', 'x').length <= 3);
});
