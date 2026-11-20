'use strict';
// Shared renderer core: DOM helper, translations, app state, theme and motion, toasts, navigation.
(function () {
  const api = window.mmd;
  const M = (window.MMD = {});
  M.api = api;

  M.state = {
    settings: null,
    page: 'server',
    history: [],
    server: {
      host: '', port: '22', user: '', auth: 'key', keyPath: '', passphrase: '', password: '', sudoPassword: '',
      siteName: 'Material Moodle', adminUser: 'admin', adminEmail: '', adminPassword: '', seed: '#8B5000', httpPort: '8080',
    },
    test: null,
    testing: false,
    jobs: {},
    running: null,
    loginUrl: null,
    tunnelMode: 'quick',
    namedMode: 'guided',
    hostname: '',
    token: '',
    tokenHost: '',
    logs: [],
    notifications: [],
    unread: 0,
    systemDark: window.matchMedia('(prefers-color-scheme: dark)').matches,
    reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
  };

  // ---------- DOM ----------
  /** Create an element. Props: class, text, html is not supported on purpose; on* handlers; everything else is an attribute. */
  M.h = function h(tag, props, ...children) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(props || {})) {
      if (v === undefined || v === null || v === false) continue;
      if (k === 'class') el.className = v;
      else if (k === 'text') el.textContent = v;
      else if (k === 'dataset') Object.assign(el.dataset, v);
      else if (k === 'value') el.value = v;
      else if (k === 'checked') el.checked = !!v;
      else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2).toLowerCase(), v);
      else el.setAttribute(k, v === true ? '' : String(v));
    }
    for (const c of children.flat(Infinity)) {
      if (c === null || c === undefined || c === false) continue;
      el.append(c instanceof Node ? c : document.createTextNode(String(c)));
    }
    return el;
  };

  const SVG_NS = 'http://www.w3.org/2000/svg';
  const ICONS = {
    server: '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/>',
    tunnel: '<path d="M7 18a5 5 0 1 1 1-9.9A6 6 0 0 1 19 10a4 4 0 0 1-1 8Z"/>',
    logs: '<path d="M4 6h16M4 12h16M4 18h10"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z"/>',
    about: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/>',
    check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    warn: '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4M12 17h.01"/>',
    search: '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
    bell: '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4l2-2Z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
    min: '<path d="M6 12h12"/>',
    max: '<rect x="6" y="6" width="12" height="12" rx="2"/>',
    restore: '<rect x="8" y="8" width="11" height="11" rx="2"/><path d="M5 15V7a2 2 0 0 1 2-2h8"/>',
    close: '<path d="m6 6 12 12M18 6 6 18"/>',
    copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
    open: '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/>',
    key: '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3"/>',
    folder: '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
    dice: '<rect x="4" y="4" width="16" height="16" rx="4"/><path d="M9 9h.01M15 15h.01M15 9h.01M9 15h.01M12 12h.01"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    save: '<path d="M5 3h11l3 3v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2Z"/><path d="M8 3v5h7M8 21v-7h8v7"/>',
    trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
    regex: '<path d="M17 3v10M12.7 5.5l8.6 5M12.7 10.5l8.6-5"/><rect x="3" y="15" width="6" height="6" rx="1"/>',
    rocket: '<path d="M5 15c-1.5 1.5-2 5-2 5s3.5-.5 5-2"/><path d="M9 15 6 12l3-6c3-4 8-4 12-4 0 4 0 9-4 12l-6 3Z"/><circle cx="15" cy="9" r="1.6"/>',
    globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18"/>',
    link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    stop: '<rect x="6" y="6" width="12" height="12" rx="2"/>',
    retry: '<path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5"/><path d="M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
  };
  M.icon = function icon(name, size = 20) {
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('width', size);
    svg.setAttribute('height', size);
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    // Static, trusted path data from the table above.
    const tmp = new DOMParser().parseFromString('<svg xmlns="' + SVG_NS + '">' + (ICONS[name] || '') + '</svg>', 'image/svg+xml');
    for (const n of [...tmp.documentElement.childNodes]) svg.append(document.importNode(n, true));
    return svg;
  };

  M.$ = (sel, root = document) => root.querySelector(sel);
  M.$$ = (sel, root = document) => [...root.querySelectorAll(sel)];

  // ---------- translations ----------
  M.t = function t(key, vars) {
    const L = window.MMD_LOCALES || {};
    const lang = M.state.settings?.language || 'en';
    const fill = (s) => String(s ?? key).replace(/\{(\w+)\}/g, (_, k) => (vars && k in vars ? vars[k] : '{' + k + '}'));
    const en = L.en?.[key];
    const yue = L.yue?.[key] ?? en;
    if (lang === 'yue') return fill(yue ?? key);
    if (lang === 'bi' && yue && yue !== en) return fill(en) + ' · ' + fill(yue);
    return fill(en ?? key);
  };

  // ---------- settings, theme and motion ----------
  M.isDark = () => {
    const th = M.state.settings?.theme || 'system';
    return th === 'dark' || (th === 'system' && M.state.systemDark);
  };

  M.effectiveMotion = () => {
    const m = M.state.settings?.motion || 'expressive';
    if (M.state.reducedMotion) return 'off';
    return m;
  };

  M.applyLook = () => {
    window.MMDTheme.apply(M.state.settings?.seed, M.isDark());
    const root = document.documentElement;
    root.classList.remove('motion-expressive', 'motion-calm', 'motion-off');
    root.classList.add('motion-' + M.effectiveMotion());
    root.lang = M.state.settings?.language === 'yue' ? 'zh-Hant-HK' : 'en';
  };

  M.updateSettings = async (patch) => {
    Object.assign(M.state.settings, patch);
    await api.settings.set(patch);
    M.applyLook();
  };

  M.toggleTheme = async () => {
    await M.updateSettings({ theme: M.isDark() ? 'light' : 'dark' });
    M.toast(M.t(M.isDark() ? 'toast.dark' : 'toast.light'));
    M.rerender();
  };

  // ---------- notifications ----------
  M.toast = function toast(message, { kind = 'info', action, actionLabel, timeout = 5000 } = {}) {
    const item = { id: Date.now() + Math.random(), message, kind, time: new Date() };
    M.state.notifications.unshift(item);
    M.state.notifications.length = Math.min(M.state.notifications.length, 100);
    M.state.unread++;
    M.renderBell?.();
    const host = M.$('#toasts');
    const el = M.h('div', { class: 'toast toast-' + kind, role: kind === 'error' ? 'alert' : 'status' },
      M.h('span', { class: 'toast-dot', 'aria-hidden': 'true' }),
      M.h('span', { class: 'toast-text', text: message }),
      action ? M.h('button', { class: 'btn btn-text btn-sm', type: 'button', text: actionLabel, onclick: () => { action(); dismiss(); } }) : null,
      M.h('button', { class: 'icon-btn btn-sm', type: 'button', 'aria-label': M.t('common.dismiss'), onclick: () => dismiss() }, M.icon('close', 16)));
    let gone = false;
    const dismiss = () => {
      if (gone) return;
      gone = true;
      el.classList.add('leaving');
      setTimeout(() => el.remove(), M.effectiveMotion() === 'off' ? 0 : 260);
    };
    host.append(el);
    while (host.children.length > 4) host.firstElementChild.remove();
    if (timeout) setTimeout(dismiss, timeout);
    return dismiss;
  };

  // ---------- clipboard ----------
  M.copy = async (text, label) => {
    await api.clipboard.write(String(text));
    M.toast(label || M.t('toast.copied'), { kind: 'ok', timeout: 2200 });
  };

  /** A code block with a copy button. */
  M.codeBlock = (text, { cls = '', label } = {}) => {
    const code = M.h('code', { class: 'code-text', text });
    const btn = M.h('button', { type: 'button', class: 'btn btn-tonal btn-sm code-copy', 'data-shortcut': 'copy' }, M.icon('copy', 16), M.h('span', { text: M.t('common.copy') }));
    btn.addEventListener('click', async () => {
      await M.copy(code.textContent);
      btn.lastChild.textContent = M.t('common.copied');
      setTimeout(() => { btn.lastChild.textContent = M.t('common.copy'); }, 1500);
    });
    return M.h('div', { class: 'code-block ' + cls, 'aria-label': label || null }, code, btn);
  };

  // ---------- info tooltips ----------
  M.infoButton = (textKey) => {
    const id = 'info-' + Math.random().toString(36).slice(2, 8);
    const tip = M.h('div', { class: 'info-tip', id, role: 'note', hidden: true, text: M.t(textKey) });
    const btn = M.h('button', { type: 'button', class: 'icon-btn info-btn', 'aria-label': M.t('common.info'), 'aria-expanded': 'false', 'aria-controls': id }, M.icon('info', 18));
    btn.addEventListener('click', () => {
      const open = tip.hidden;
      tip.hidden = !open;
      btn.setAttribute('aria-expanded', String(open));
    });
    return [btn, tip];
  };

  // ---------- navigation ----------
  M.pages = {};
  M.PAGE_ORDER = ['server', 'tunnel', 'logs', 'settings', 'about'];

  M.go = (page, { push = true, focus } = {}) => {
    if (!M.pages[page]) return;
    if (push && page !== M.state.page) M.state.history.push(M.state.page);
    const changed = page !== M.state.page;
    M.state.page = page;
    api.settings.set({ lastPage: page });
    M.renderRail();
    M.renderPage(changed);
    if (focus) {
      requestAnimationFrame(() => {
        const el = document.getElementById(focus);
        if (el) {
          el.scrollIntoView({ block: 'center', behavior: M.effectiveMotion() === 'off' ? 'auto' : 'smooth' });
          const target = el.matches('input, select, textarea, button, [tabindex]') ? el
            : el.querySelector('[aria-checked="true"], input, select, button, [tabindex]') || el;
          target.focus({ preventScroll: true });
          const row = el.closest('.setting, .field') || el;
          row.classList.remove('flash-target');
          void row.offsetWidth;
          row.classList.add('flash-target');
        }
      });
    }
  };

  M.back = () => {
    const prev = M.state.history.pop();
    if (prev) M.go(prev, { push: false });
  };

  M.renderRail = () => {
    const rail = M.$('#rail');
    rail.replaceChildren(...M.PAGE_ORDER.map((p, i) => {
      const active = p === M.state.page;
      return M.h('button', {
        type: 'button', class: 'rail-item' + (active ? ' active' : ''), 'aria-current': active ? 'page' : null,
        title: M.t('nav.' + p) + ' (Ctrl+' + (i + 1) + ')', onclick: () => M.go(p),
      }, M.h('span', { class: 'rail-pill' }, M.icon(p)), M.h('span', { class: 'rail-label', text: M.t('nav.' + p) }));
    }));
  };

  M.renderPage = (animate) => {
    const host = M.$('#page-host');
    const page = M.h('section', { class: 'page' + (animate ? ' page-enter' : ''), 'data-page': M.state.page, 'aria-label': M.t('nav.' + M.state.page) });
    M.pages[M.state.page](page);
    host.replaceChildren(page);
    if (animate) host.scrollTop = 0;
  };

  /** Re-render the current page in place (keeps focus where possible). */
  M.rerender = () => {
    const active = document.activeElement;
    const id = active?.id;
    const sel = id && 'selectionStart' in active ? [active.selectionStart, active.selectionEnd] : null;
    const host = M.$('#page-host');
    const scroll = host.scrollTop;
    M.renderChrome();
    M.renderRail();
    M.renderPage(false);
    host.scrollTop = scroll;
    if (id) {
      const el = document.getElementById(id);
      if (el) {
        el.focus({ preventScroll: true });
        if (sel && 'setSelectionRange' in el) try { el.setSelectionRange(sel[0], sel[1]); } catch { /* not a text field */ }
      }
    }
  };

  // ---------- ripple ----------
  document.addEventListener('pointerdown', (e) => {
    const target = e.target.closest('.btn, .rail-item, .icon-btn, .choice-card, .seg button, .block-chip, .palette-item');
    if (!target || M.effectiveMotion() === 'off') return;
    const r = target.getBoundingClientRect();
    const size = Math.max(r.width, r.height) * 2;
    const ripple = M.h('span', { class: 'ripple', 'aria-hidden': 'true' });
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = e.clientX - r.left - size / 2 + 'px';
    ripple.style.top = e.clientY - r.top - size / 2 + 'px';
    target.append(ripple);
    setTimeout(() => ripple.remove(), 650);
  });

  M.fmtTime = (t) => new Date(t).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
})();
