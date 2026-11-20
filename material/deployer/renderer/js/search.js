'use strict';
// Search field with the visual regex builder, shared by the Logs page and the command palette.
(function () {
  const M = window.MMD;
  const R = window.MMDRegex;

  /**
   * Build a search bar. opts: { id, placeholder, state: { query, regex, ignoreCase, builderOpen }, onChange(compiled) }
   * Returns { el, input, compiled() }.
   */
  M.searchBar = function searchBar(opts) {
    const st = opts.state;
    const input = M.h('input', {
      id: opts.id, type: 'search', class: 'search-input', value: st.query || '', placeholder: opts.placeholder,
      autocomplete: 'off', spellcheck: 'false', 'aria-describedby': opts.id + '-status',
    });
    const status = M.h('span', { id: opts.id + '-status', class: 'search-status', 'aria-live': 'polite' });
    const compiled = () => R.compile(st.query, { regex: st.regex, ignoreCase: st.ignoreCase });
    const refresh = () => {
      const c = compiled();
      wrap.classList.toggle('invalid', !c.ok);
      input.setAttribute('aria-invalid', c.ok ? 'false' : 'true');
      status.textContent = c.ok ? '' : M.t('search.invalid', { error: c.error });
      opts.onChange(c);
    };
    input.addEventListener('input', () => { st.query = input.value; refresh(); });

    const toggle = (key, labelKey, title) => {
      const b = M.h('button', { type: 'button', class: 'toggle-chip' + (st[key] ? ' on' : ''), 'aria-pressed': String(!!st[key]), title, text: M.t(labelKey) });
      b.addEventListener('click', () => {
        st[key] = !st[key];
        b.classList.toggle('on', st[key]);
        b.setAttribute('aria-pressed', String(st[key]));
        refresh();
      });
      return b;
    };
    const regexToggle = toggle('regex', 'search.regex', 'Alt+R');
    const caseToggle = toggle('ignoreCase', 'search.ignoreCase', 'Alt+C');

    const blocks = M.h('div', { class: 'block-row', role: 'group', 'aria-label': M.t('search.builder') },
      R.BLOCKS.map((b) => M.h('button', {
        type: 'button', class: 'block-chip', title: M.t('block.' + b.id + '.help'),
        onclick: () => {
          const res = R.insertBlock(input.value, input.selectionStart, input.selectionEnd, b.id);
          input.value = res.value;
          st.query = res.value;
          if (!st.regex) {
            st.regex = true;
            regexToggle.classList.add('on');
            regexToggle.setAttribute('aria-pressed', 'true');
          }
          input.focus();
          input.setSelectionRange(res.caret, res.caret);
          refresh();
        },
      }, M.h('span', { class: 'block-name', text: M.t('block.' + b.id) }), M.h('code', { class: 'block-token', text: b.sample }))));
    const builderBtn = M.h('button', {
      type: 'button', class: 'toggle-chip' + (st.builderOpen ? ' on' : ''), 'aria-expanded': String(!!st.builderOpen),
      'aria-controls': opts.id + '-blocks',
    }, M.icon('regex', 16), M.h('span', { text: M.t('search.builder') }));
    blocks.id = opts.id + '-blocks';
    blocks.hidden = !st.builderOpen;
    builderBtn.addEventListener('click', () => {
      st.builderOpen = !st.builderOpen;
      blocks.hidden = !st.builderOpen;
      builderBtn.classList.toggle('on', st.builderOpen);
      builderBtn.setAttribute('aria-expanded', String(st.builderOpen));
    });

    input.addEventListener('keydown', (e) => {
      if (e.altKey && (e.key === 'r' || e.key === 'R')) { e.preventDefault(); regexToggle.click(); }
      if (e.altKey && (e.key === 'c' || e.key === 'C')) { e.preventDefault(); caseToggle.click(); }
    });

    const wrap = M.h('div', { class: 'search-bar' },
      M.h('div', { class: 'search-row' },
        M.h('label', { class: 'search-field' }, M.icon('search', 18), M.h('span', { class: 'sr-only', text: opts.placeholder }), input),
        regexToggle, caseToggle, builderBtn),
      blocks, status);
    queueMicrotask(refresh);
    return { el: wrap, input, compiled };
  };

  /** Append text to a parent with matches wrapped in <mark>. */
  M.highlightInto = (parent, text, compiled) => {
    if (!compiled || compiled.empty || !compiled.ok) { parent.append(text); return 0; }
    const ranges = R.findMatches(text, compiled.re);
    for (const seg of R.segments(text, ranges)) parent.append(seg.hit ? M.h('mark', { text: seg.text }) : seg.text);
    return ranges.length;
  };
})();
