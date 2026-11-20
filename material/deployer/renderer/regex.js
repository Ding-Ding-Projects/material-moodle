'use strict';
// Visual regex builder: block definitions, insertion at the caret, compiling and match finding.
// Loaded as a plain script in the renderer and with require() in the unit tests.
(function (root) {
  const BLOCKS = [
    { id: 'start', token: '^', label: 'Starts with', sample: '^' },
    { id: 'end', token: '$', label: 'Ends with', sample: '$' },
    { id: 'any', token: '.*', label: 'Anything', sample: '.*' },
    { id: 'digit', token: '\\d', label: 'Digit', sample: '\\d' },
    { id: 'letter', token: '[A-Za-z]', label: 'Letter', sample: '[A-Za-z]' },
    { id: 'edge', token: '\\b', label: 'Word edge', sample: '\\b' },
    { id: 'space', token: '\\s', label: 'Space', sample: '\\s' },
    { id: 'more', token: '+', label: 'One or more', sample: '+' },
    { id: 'optional', token: '?', label: 'Optional', sample: '?' },
    { id: 'either', token: '|', label: 'Either', sample: '|' },
  ];

  function blockById(id) {
    return BLOCKS.find((b) => b.id === id) || null;
  }

  /**
   * Insert a block's token into a query at the selection. "Starts with" always goes to the very
   * beginning and "Ends with" to the very end, so they stay valid anchors wherever the caret is.
   * Returns { value, caret } with the caret placed after the inserted token.
   */
  function insertBlock(value, selStart, selEnd, id) {
    const block = blockById(id);
    if (!block) throw new Error('Unknown regex block: ' + id);
    const v = String(value || '');
    let start = Math.max(0, Math.min(selStart ?? v.length, v.length));
    let end = Math.max(start, Math.min(selEnd ?? start, v.length));
    if (id === 'start') {
      if (v.startsWith('^')) return { value: v, caret: 1 };
      return { value: '^' + v, caret: 1 };
    }
    if (id === 'end') {
      if (v.endsWith('$') && !v.endsWith('\\$')) return { value: v, caret: v.length };
      return { value: v + '$', caret: v.length + 1 };
    }
    // Quantifiers wrap a selected multi-character run in a group so they apply to all of it.
    if ((id === 'more' || id === 'optional') && end - start === 1) {
      return { value: v.slice(0, end) + block.token + v.slice(end), caret: end + block.token.length };
    }
    if ((id === 'more' || id === 'optional') && end - start > 1) {
      const inner = v.slice(start, end);
      const next = v.slice(0, start) + '(?:' + inner + ')' + block.token + v.slice(end);
      return { value: next, caret: start + inner.length + 4 + block.token.length };
    }
    const next = v.slice(0, start) + block.token + v.slice(end);
    return { value: next, caret: start + block.token.length };
  }

  function escapeRegex(s) {
    return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  /**
   * Compile a query. In plain mode the text is matched literally; in regex mode it is a pattern.
   * Returns { ok, re, error, empty }.
   */
  function compile(query, { regex = false, ignoreCase = true } = {}) {
    const q = String(query || '');
    if (!q) return { ok: true, re: null, empty: true, error: null };
    const flags = 'g' + (ignoreCase ? 'i' : '');
    try {
      return { ok: true, re: new RegExp(regex ? q : escapeRegex(q), flags), empty: false, error: null };
    } catch (e) {
      return { ok: false, re: null, empty: false, error: String(e.message || e).replace(/^Invalid regular expression: /, '') };
    }
  }

  /** All match ranges [start, end) in text. Zero-length matches are skipped safely. */
  function findMatches(text, re, limit = 500) {
    const out = [];
    if (!re) return out;
    const s = String(text);
    const r = new RegExp(re.source, re.flags.includes('g') ? re.flags : re.flags + 'g');
    let m;
    while ((m = r.exec(s)) && out.length < limit) {
      if (m[0].length === 0) { r.lastIndex++; continue; }
      out.push([m.index, m.index + m[0].length]);
    }
    return out;
  }

  /** True if the text matches the compiled query (or the query is empty). */
  function test(text, compiled) {
    if (!compiled || compiled.empty) return true;
    if (!compiled.ok) return false;
    compiled.re.lastIndex = 0;
    const r = compiled.re.test(String(text));
    compiled.re.lastIndex = 0;
    return r;
  }

  /** Split text into [{ text, hit }] segments for highlighting. */
  function segments(text, ranges) {
    const s = String(text);
    const out = [];
    let pos = 0;
    for (const [a, b] of ranges) {
      if (a > pos) out.push({ text: s.slice(pos, a), hit: false });
      out.push({ text: s.slice(a, b), hit: true });
      pos = b;
    }
    if (pos < s.length) out.push({ text: s.slice(pos), hit: false });
    return out;
  }

  const api = { BLOCKS, blockById, insertBlock, escapeRegex, compile, findMatches, test, segments };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.MMDRegex = api;
})(typeof globalThis !== 'undefined' ? globalThis : this);
