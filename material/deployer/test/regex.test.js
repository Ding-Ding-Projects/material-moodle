'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const R = require('../renderer/regex');

test('there is a block for every builder token', () => {
  assert.deepEqual(R.BLOCKS.map((b) => b.token), ['^', '$', '.*', '\\d', '[A-Za-z]', '\\b', '\\s', '+', '?', '|']);
});

test('insertBlock inserts at the caret and moves the caret after the token', () => {
  assert.deepEqual(R.insertBlock('ab', 1, 1, 'digit'), { value: 'a\\db', caret: 3 });
  assert.deepEqual(R.insertBlock('error', 5, 5, 'any'), { value: 'error.*', caret: 7 });
  assert.deepEqual(R.insertBlock('ab', 0, 2, 'space'), { value: '\\s', caret: 2 });
  assert.deepEqual(R.insertBlock('a', 1, 1, 'either'), { value: 'a|', caret: 2 });
});

test('anchors always go to the start or end, once', () => {
  assert.deepEqual(R.insertBlock('moodle', 3, 3, 'start'), { value: '^moodle', caret: 1 });
  assert.deepEqual(R.insertBlock('^moodle', 3, 3, 'start'), { value: '^moodle', caret: 1 });
  assert.deepEqual(R.insertBlock('moodle', 0, 0, 'end'), { value: 'moodle$', caret: 7 });
  assert.deepEqual(R.insertBlock('moodle$', 0, 0, 'end'), { value: 'moodle$', caret: 7 });
  assert.deepEqual(R.insertBlock('cost\\$', 0, 0, 'end'), { value: 'cost\\$$', caret: 7 });
});

test('quantifiers group a multi-character selection', () => {
  assert.deepEqual(R.insertBlock('xaby', 1, 3, 'more'), { value: 'x(?:ab)+y', caret: 8 });
  assert.deepEqual(R.insertBlock('ab', 1, 2, 'optional'), { value: 'ab?', caret: 3 });
  assert.throws(() => R.insertBlock('', 0, 0, 'nope'), /Unknown regex block/);
});

test('compile handles plain text, regex, case and invalid patterns', () => {
  const plain = R.compile('a.b', { regex: false });
  assert.ok(R.test('xa.bx', plain));
  assert.ok(!R.test('axb', plain));
  const rx = R.compile('a.b', { regex: true });
  assert.ok(R.test('axb', rx));
  assert.ok(R.test('ERROR', R.compile('error', { ignoreCase: true })));
  assert.ok(!R.test('ERROR', R.compile('error', { ignoreCase: false })));
  const bad = R.compile('(unclosed', { regex: true });
  assert.equal(bad.ok, false);
  assert.match(bad.error, /unclosed|Unterminated/i);
  assert.equal(R.test('anything', bad), false);
  assert.ok(R.compile('', {}).empty);
  assert.ok(R.test('anything', R.compile('', {})));
});

test('findMatches and segments highlight every match and skip empty matches', () => {
  const c = R.compile('\\d+', { regex: true });
  const ranges = R.findMatches('port 8080 and 443', c.re);
  assert.deepEqual(ranges, [[5, 9], [14, 17]]);
  assert.deepEqual(R.segments('port 8080 and 443', ranges), [
    { text: 'port ', hit: false }, { text: '8080', hit: true }, { text: ' and ', hit: false }, { text: '443', hit: true },
  ]);
  assert.deepEqual(R.findMatches('abc', R.compile('x*', { regex: true }).re), []);
  // test() must not be affected by the global flag's lastIndex between calls.
  const g = R.compile('moodle', {});
  assert.ok(R.test('moodle up', g));
  assert.ok(R.test('moodle up', g));
});
