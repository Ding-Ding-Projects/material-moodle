'use strict';
// Title bar, notification centre, celebration, error screen, onboarding wizard, command palette and context menu.
(function () {
  const M = window.MMD;
  const { h, t, icon, state, api } = M;

  // ---------- focus trap for dialogs ----------
  function trap(container, onEscape) {
    const prev = document.activeElement;
    const handler = (e) => {
      if (e.key === 'Escape' && onEscape) { e.preventDefault(); e.stopPropagation(); onEscape(); return; }
      if (e.key !== 'Tab') return;
      const f = M.$$('button:not([disabled]), input:not([disabled]), select, textarea, [tabindex="0"], a[href]', container).filter((el) => el.offsetParent !== null);
      if (!f.length) return;
      const first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    };
    container.addEventListener('keydown', handler);
    return () => { container.removeEventListener('keydown', handler); if (prev && prev.isConnected) prev.focus(); };
  }

  // ---------- title bar ----------
  M.renderChrome = () => {
    const bar = M.$('#titlebar');
    const maxBtn = h('button', { type: 'button', class: 'win-btn', id: 'win-max', 'aria-label': t(M.maximized ? 'win.restore' : 'win.maximize'), onclick: () => api.win.toggleMaximize() }, icon(M.maximized ? 'restore' : 'max', 16));
    bar.replaceChildren(
      h('img', { class: 'brand-mark', src: 'assets/icon.png', alt: '', width: '26', height: '26' }),
      h('span', { class: 'brand-name', text: 'Material Moodle Deployer' }),
      h('span', { class: 'grow drag' }),
      h('button', { type: 'button', class: 'title-search', onclick: () => M.openPalette(), title: 'Ctrl+Shift+F' },
        icon('search', 16), h('span', { text: t('palette.open') }), h('kbd', { text: 'Ctrl+Shift+F' })),
      h('button', { type: 'button', class: 'icon-btn bell', id: 'bell', 'aria-label': t('notif.title'), 'aria-expanded': 'false', 'aria-controls': 'notif-panel', onclick: () => M.toggleNotifications() },
        icon('bell', 18), h('span', { class: 'bell-badge', id: 'bell-badge', hidden: true })),
      h('span', { class: 'version muted', text: 'v' + (M.appInfo?.version || '1.0.0') }),
      h('div', { class: 'win-controls' },
        h('button', { type: 'button', class: 'win-btn', 'aria-label': t('win.minimize'), onclick: () => api.win.minimize() }, icon('min', 16)),
        maxBtn,
        h('button', { type: 'button', class: 'win-btn win-close', 'aria-label': t('win.close'), onclick: () => api.win.close() }, icon('close', 16))));
    M.renderBell();
  };

  M.renderBell = () => {
    const badge = M.$('#bell-badge');
    if (!badge) return;
    badge.hidden = !state.unread;
    badge.textContent = state.unread > 9 ? '9+' : String(state.unread);
    const panel = M.$('#notif-panel');
    if (panel && !panel.hidden) renderNotifList();
  };

  // ---------- notification centre ----------
  function renderNotifList() {
    const panel = M.$('#notif-panel');
    const items = state.notifications;
    panel.replaceChildren(
      h('div', { class: 'row align-center gap-s panel-head' },
        h('h2', { class: 'card-title grow', id: 'notif-title', text: t('notif.title') }),
        h('button', { type: 'button', class: 'btn btn-text btn-sm', text: t('notif.clear'), disabled: !items.length, onclick: () => { state.notifications = []; state.unread = 0; M.renderBell(); renderNotifList(); } }),
        h('button', { type: 'button', class: 'icon-btn', 'aria-label': t('common.close'), onclick: () => M.toggleNotifications(false) }, icon('close', 18))),
      items.length
        ? h('ul', { class: 'notif-list' }, items.map((n) => h('li', { class: 'notif notif-' + n.kind },
          h('span', { class: 'toast-dot', 'aria-hidden': 'true' }),
          h('span', { class: 'grow', text: n.message }),
          h('time', { class: 'muted small', text: M.fmtTime(n.time) }))))
        : h('p', { class: 'muted empty', text: t('notif.empty') }));
  }

  let releaseNotif = null;
  M.toggleNotifications = (force) => {
    const panel = M.$('#notif-panel');
    const open = force ?? panel.hidden;
    panel.hidden = !open;
    M.$('#bell')?.setAttribute('aria-expanded', String(open));
    if (open) {
      state.unread = 0;
      M.renderBell();
      renderNotifList();
      releaseNotif = trap(panel, () => M.toggleNotifications(false));
      panel.querySelector('button')?.focus();
    } else if (releaseNotif) { releaseNotif(); releaseNotif = null; }
  };

  // ---------- celebration ----------
  let releaseCelebrate = null;
  M.celebrate = (job, result) => {
    const host = M.$('#celebrate');
    const titles = { deploy: 'celebrate.deploy', quick: 'celebrate.quick', named: 'celebrate.named', token: 'celebrate.named' };
    const texts = { deploy: 'celebrate.deployText', quick: 'celebrate.quickText', named: 'celebrate.namedText', token: 'celebrate.namedText' };
    const chips = job === 'deploy' ? ['celebrate.chipHealthy', 'celebrate.chipAdmin', 'celebrate.chipTheme'] : ['celebrate.chipTunnel', 'celebrate.chipHttps', 'celebrate.chipHealthy'];
    const url = result.url;
    const confetti = h('div', { class: 'burst', 'aria-hidden': 'true' },
      h('span', { class: 'ring' }), h('span', { class: 'ring ring2' }));
    const n = M.effectiveMotion() === 'expressive' ? 48 : 0;
    const cols = ['var(--p)', 'var(--ok)', 'var(--tc)', 'var(--err)', 'var(--sc)', 'var(--onpc)'];
    for (let i = 0; i < n; i++) {
      const ang = (i / n) * Math.PI * 2 + (i % 3) * 0.2;
      const dist = 260 + (i * 37) % 260;
      const c = h('span', { class: 'conf' });
      c.style.width = 8 + (i % 4) * 3 + 'px';
      c.style.height = (i % 2 ? 18 : 10) + 'px';
      c.style.borderRadius = i % 3 === 0 ? '50%' : '3px';
      c.style.background = cols[i % cols.length];
      c.style.setProperty('--dx', Math.round(Math.cos(ang) * dist) + 'px');
      c.style.setProperty('--dy', Math.round(Math.sin(ang) * dist + 160) + 'px');
      c.style.setProperty('--r', (i * 97) % 720 + 'deg');
      c.style.animationDelay = (i % 6) * 0.05 + 's';
      confetti.append(c);
    }
    const close = () => {
      host.classList.add('leaving');
      setTimeout(() => { host.hidden = true; host.classList.remove('leaving'); host.replaceChildren(); }, M.effectiveMotion() === 'off' ? 0 : 250);
      if (releaseCelebrate) { releaseCelebrate(); releaseCelebrate = null; }
    };
    const replay = () => { M.celebrate(job, result); };
    host.replaceChildren(confetti,
      h('div', { class: 'celebrate-body', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'celebrate-title' },
        h('div', { class: 'badge' }, icon('check', 72)),
        h('h1', { class: 'celebrate-title', id: 'celebrate-title', text: t(titles[job]) }),
        h('p', { class: 'rise celebrate-text', text: t(texts[job]) }),
        url ? h('div', { class: 'rise' }, M.codeBlock(url, { cls: 'url-block' })) : null,
        h('div', { class: 'rise row wrap gap-s center' }, chips.map((k) => h('span', { class: 'chip chip-surface', text: t(k) }))),
        h('div', { class: 'rise row wrap gap-m center' },
          url ? h('button', { type: 'button', class: 'btn btn-filled btn-xl', id: 'celebrate-open', onclick: () => api.shell.open(url) }, icon('open', 20), h('span', { text: t('celebrate.open') })) : null,
          job === 'deploy' ? h('button', { type: 'button', class: 'btn btn-outline-on btn-xl', onclick: () => { close(); M.go('tunnel'); } }, icon('globe', 20), h('span', { text: t('celebrate.toTunnel') })) : null,
          h('button', { type: 'button', class: 'btn btn-outline-on btn-xl', onclick: replay, text: t('celebrate.replay') }),
          h('button', { type: 'button', class: 'btn btn-text-on btn-xl', id: 'celebrate-close', onclick: close, text: t('common.close') }))));
    host.hidden = false;
    if (releaseCelebrate) releaseCelebrate();
    releaseCelebrate = trap(host, close);
    requestAnimationFrame(() => (M.$('#celebrate-open') || M.$('#celebrate-close'))?.focus());
    M.toast(t(titles[job]), { kind: 'ok' });
  };

  // ---------- error screen ----------
  let releaseError = null;
  M.hideError = () => {
    const host = M.$('#poke');
    if (host.hidden) return;
    host.hidden = true;
    host.replaceChildren();
    if (releaseError) { releaseError(); releaseError = null; }
  };

  M.showError = (job, err) => {
    const host = M.$('#poke');
    const stepNo = err.step + 1;
    const jobs = { deploy: 'job.deploy', quick: 'job.quick', named: 'job.named', token: 'job.token' };
    const tunnelJob = job === 'named' || job === 'token';
    host.replaceChildren(
      h('span', { class: 'flash', 'aria-hidden': 'true' }),
      h('div', { class: 'poke-body', role: 'alertdialog', 'aria-modal': 'true', 'aria-labelledby': 'poke-title', 'aria-describedby': 'poke-desc' },
        h('div', { class: 'row wrap gap-l align-center' },
          h('div', { class: 'poke-badge shake' }, icon('warn', 60)),
          h('div', { class: 'grow' },
            h('p', { class: 'poke-kicker', text: t('error.stopped', { job: t(jobs[job]), n: stepNo, total: err.total }) }),
            h('h1', { class: 'poke-title crack', id: 'poke-title', text: t('step.' + err.stepId) + ': ' + err.message }))),
        h('p', { class: 'rise poke-desc', id: 'poke-desc', text: t(stepNo > 1 ? 'error.kept' : 'error.nothing') }),
        h('section', { class: 'rise card poke-card' },
          h('h2', { class: 'card-title', text: t('error.what') }),
          M.codeBlock((err.message + (err.detail ? '\n\n' + err.detail : '')).trim(), { cls: 'inverse' }),
          h('h2', { class: 'card-title', text: t('error.fix') }),
          h('ol', { class: 'fix-list' }, err.fixes.map((f) => h('li', { text: t('fix.' + f) })))),
        h('div', { class: 'rise row wrap gap-s' },
          h('button', { type: 'button', class: 'btn btn-filled btn-xl', id: 'poke-retry', onclick: () => { M.hideError(); M.runJob(job, err.step, state.jobs[job]?.params || {}); } }, icon('retry', 20), h('span', { text: t('error.retry', { n: stepNo }) })),
          tunnelJob ? h('button', { type: 'button', class: 'btn btn-outline btn-xl', onclick: () => { M.hideError(); state.tunnelMode = 'quick'; M.go('tunnel'); } , text: t('error.useQuick') }) : null,
          h('button', { type: 'button', class: 'btn btn-text btn-xl', onclick: () => M.saveLog(false) }, icon('save', 20), h('span', { text: t('error.saveLog') })),
          h('button', { type: 'button', class: 'btn btn-text btn-xl', onclick: () => { M.hideError(); M.go('logs'); }, text: t('error.openLogs') }),
          h('button', { type: 'button', class: 'btn btn-text btn-xl', onclick: M.hideError, text: t('common.close') }))));
    host.hidden = false;
    if (releaseError) releaseError();
    releaseError = trap(host, M.hideError);
    requestAnimationFrame(() => M.$('#poke-retry')?.focus());
    M.toast(t('error.toast', { step: t('step.' + err.stepId) }), { kind: 'error' });
  };

  // ---------- onboarding wizard ----------
  const OB_STEPS = [
    { id: 'welcome', optional: false },
    { id: 'look', optional: true },
    { id: 'server', optional: true },
    { id: 'site', optional: true },
    { id: 'ready', optional: false },
  ];
  let ob = null;
  let releaseOb = null;

  M.openOnboarding = (restart) => {
    const saved = state.settings.onboarding || {};
    ob = { step: restart ? 0 : Math.min(saved.step || 0, OB_STEPS.length - 1), data: { ...(saved.data || {}) }, skipped: [] };
    if (ob.data.server) Object.assign(state.server, ob.data.server);
    M.$('#onboarding').hidden = false;
    renderOnboarding('none');
    releaseOb = trap(M.$('#onboarding'), () => saveOnboarding(true));
  };

  async function saveOnboarding(close) {
    const pick = (({ host, port, user, auth, keyPath, siteName, adminUser, adminEmail, httpPort }) => ({ host, port, user, auth, keyPath, siteName, adminUser, adminEmail, httpPort }))(state.server);
    ob.data.server = pick;
    state.settings.onboarding = { done: false, step: ob.step, data: ob.data };
    await api.settings.set({ onboarding: state.settings.onboarding, lastServer: state.server });
    if (close) {
      closeOnboarding();
      M.toast(t('ob.saved'), { kind: 'info' });
    }
  }

  function closeOnboarding() {
    M.$('#onboarding').hidden = true;
    M.$('#onboarding').replaceChildren();
    if (releaseOb) { releaseOb(); releaseOb = null; }
  }

  async function finishOnboarding() {
    state.settings.onboarding = { done: true, step: 0, data: {} };
    await api.settings.set({ onboarding: state.settings.onboarding, lastServer: state.server });
    closeOnboarding();
    M.go('server');
    M.toast(t('ob.done'), { kind: 'ok' });
    requestAnimationFrame(() => document.getElementById(state.server.host ? 'srv-test' : 'srv-host')?.focus());
  }

  function obInput(id, key, labelKey, opts = {}) {
    const el = h('input', { id, class: 'input' + (opts.mono ? ' mono' : ''), type: opts.type || 'text', value: state.server[key] || '', placeholder: opts.placeholder || '', autocomplete: 'off', spellcheck: 'false' });
    el.addEventListener('input', () => { state.server[key] = el.value; });
    return h('div', { class: 'field' }, h('label', { class: 'field-label', for: id, text: t(labelKey) }), el);
  }

  function renderOnboarding(dir) {
    const host = M.$('#onboarding');
    const step = OB_STEPS[ob.step];
    const S = state.settings;
    const set = async (patch) => { await M.updateSettings(patch); renderOnboarding('none'); M.rerender(); };
    let body;
    if (step.id === 'welcome') {
      body = [h('p', { class: 'lead', text: t('ob.welcomeText') }),
        h('div', { class: 'field' }, h('span', { class: 'field-label', text: t('settings.language') }),
          M.segmented('ob-lang', [['en', 'settings.english'], ['yue', 'settings.cantonese'], ['bi', 'settings.bilingual']], S.language, (v) => set({ language: v }), 'settings.language'))];
    } else if (step.id === 'look') {
      body = [h('p', { class: 'lead', text: t('ob.lookText') }),
        h('div', { class: 'field' }, h('span', { class: 'field-label', text: t('settings.theme') }),
          M.segmented('ob-theme', [['system', 'settings.system'], ['light', 'settings.light'], ['dark', 'settings.dark']], S.theme, (v) => set({ theme: v }), 'settings.theme')),
        h('div', { class: 'field' }, h('span', { class: 'field-label', text: t('settings.seed') }),
          h('div', { class: 'row wrap gap-s' }, window.MMDTheme.SWATCHES.map((c) => {
            const b = h('button', { type: 'button', class: 'swatch' + (c === S.seed ? ' on' : ''), 'aria-label': c, 'aria-pressed': String(c === S.seed), onclick: () => set({ seed: c }) });
            b.style.setProperty('--swatch', c);
            return b;
          }))),
        h('div', { class: 'field' }, h('span', { class: 'field-label', text: t('settings.motion') }),
          M.segmented('ob-motion', [['expressive', 'settings.expressive'], ['calm', 'settings.calm'], ['off', 'settings.off']], S.motion, (v) => set({ motion: v }), 'settings.motion'))];
    } else if (step.id === 'server') {
      const keyBtn = h('button', { type: 'button', class: 'btn btn-tonal' }, icon('folder', 18), h('span', { text: t('server.browse') }));
      keyBtn.addEventListener('click', async () => { const p = await api.dialog.pickKey(); if (p) { state.server.keyPath = p; state.server.auth = 'key'; renderOnboarding('none'); } });
      body = [h('p', { class: 'lead', text: t('ob.serverText') }),
        h('div', { class: 'row wrap' }, h('div', { class: 'grow-3' }, obInput('ob-host', 'host', 'server.host', { placeholder: '203.0.113.24' })), h('div', { class: 'grow-1' }, obInput('ob-port', 'port', 'server.port'))),
        obInput('ob-user', 'user', 'server.user', { placeholder: 'ubuntu' }),
        h('div', { class: 'row gap-s align-end' }, h('div', { class: 'grow' }, obInput('ob-key', 'keyPath', 'server.keyFile', { mono: true, placeholder: '~/.ssh/id_ed25519' })), keyBtn),
        h('p', { class: 'muted small', text: t('ob.serverNote') })];
    } else if (step.id === 'site') {
      const gen = h('button', { type: 'button', class: 'btn btn-tonal' }, icon('dice', 18), h('span', { text: t('server.generate') }));
      gen.addEventListener('click', async () => { state.server.adminPassword = await api.passwords.generate(); M.toast(t('toast.generated'), { kind: 'ok' }); renderOnboarding('none'); });
      body = [h('p', { class: 'lead', text: t('ob.siteText') }),
        obInput('ob-site', 'siteName', 'server.siteName'),
        h('div', { class: 'row wrap' }, h('div', { class: 'grow-1' }, obInput('ob-admin', 'adminUser', 'server.adminUser')), h('div', { class: 'grow-2' }, obInput('ob-email', 'adminEmail', 'server.adminEmail', { type: 'email', placeholder: 'admin@example.org' }))),
        h('div', { class: 'field' }, h('span', { class: 'field-label', text: t('server.adminPassword') }),
          h('div', { class: 'row gap-s align-center' }, h('span', { class: 'chip ' + (state.server.adminPassword ? 'chip-ok' : 'chip-neutral'), text: t(state.server.adminPassword ? 'ob.passwordSet' : 'ob.passwordNone') }), gen)),
        h('p', { class: 'muted small', text: t('ob.siteNote') })];
    } else {
      const summary = [
        [t('server.host'), state.server.host || t('ob.notSet')],
        [t('server.user'), state.server.user || t('ob.notSet')],
        [t('server.siteName'), state.server.siteName || t('ob.notSet')],
        [t('server.adminEmail'), state.server.adminEmail || t('ob.notSet')],
      ];
      body = [h('p', { class: 'lead', text: t('ob.readyText') }),
        h('dl', { class: 'facts' }, summary.map(([k, v]) => [h('dt', { text: k }), h('dd', { text: v })])),
        ob.skipped.length ? h('p', { class: 'muted small', text: t('ob.skippedNote') }) : null];
    }
    const pct = Math.round((ob.step / (OB_STEPS.length - 1)) * 100);
    const progress = h('div', { class: 'ob-progress', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': String(OB_STEPS.length), 'aria-valuenow': String(ob.step + 1), 'aria-label': t('ob.progress') }, h('span', { class: 'ob-progress-fill' }));
    progress.firstChild.style.width = pct + '%';
    const dots = h('ol', { class: 'ob-dots', 'aria-hidden': 'true' }, OB_STEPS.map((s, i) => h('li', { class: i < ob.step ? 'done' : i === ob.step ? 'on' : '' }, h('span', { text: t('ob.' + s.id) }))));
    const back = h('button', { type: 'button', class: 'btn btn-text', id: 'ob-back', disabled: ob.step === 0, text: t('ob.back') });
    back.addEventListener('click', () => { ob.step--; saveOnboarding(false); renderOnboarding('back'); });
    const skip = step.optional ? h('button', { type: 'button', class: 'btn btn-text', id: 'ob-skip', text: t('ob.skip') }) : null;
    skip?.addEventListener('click', () => { ob.skipped.push(step.id); ob.step++; saveOnboarding(false); renderOnboarding('next'); });
    const last = ob.step === OB_STEPS.length - 1;
    const next = h('button', { type: 'button', class: 'btn btn-filled', id: 'ob-next', text: t(last ? 'ob.finish' : 'ob.next') });
    next.addEventListener('click', () => {
      if (last) { finishOnboarding(); return; }
      ob.step++;
      saveOnboarding(false);
      renderOnboarding('next');
    });
    const later = h('button', { type: 'button', class: 'btn btn-text', id: 'ob-later', text: t('ob.later') });
    later.addEventListener('click', () => saveOnboarding(true));
    const card = h('div', { class: 'ob-card', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'ob-title' },
      h('div', { class: 'row align-center gap-s' }, h('img', { class: 'brand-mark', src: 'assets/icon.png', alt: '', width: '32', height: '32' }),
        h('span', { class: 'muted grow', text: t('ob.stepOf', { n: ob.step + 1, total: OB_STEPS.length }) }), later),
      progress, dots,
      h('div', { class: 'ob-body' + (dir === 'next' ? ' slide-next' : dir === 'back' ? ' slide-back' : '') },
        h('h1', { class: 'page-title', id: 'ob-title', text: t('ob.' + step.id + 'Title') }),
        step.optional ? h('span', { class: 'chip chip-neutral', text: t('ob.optional') }) : null,
        body),
      h('div', { class: 'row gap-s ob-actions' }, back, h('span', { class: 'grow' }), skip, next));
    host.replaceChildren(card);
    requestAnimationFrame(() => (card.querySelector('.ob-body input, .ob-body [role=radio][aria-checked=true]') || next).focus());
  }

  // ---------- command palette ----------
  const palSearch = { query: '', regex: false, ignoreCase: true, builderOpen: false };
  let palItems = [];
  let palActive = 0;
  let releasePal = null;

  function paletteEntries() {
    const pages = M.PAGE_ORDER.map((p, i) => ({ group: 'palette.pages', label: t('nav.' + p), hint: 'Ctrl+' + (i + 1), run: () => M.go(p) }));
    const actions = [
      ['palette.testConnection', () => { M.go('server'); requestAnimationFrame(() => M.$('#srv-test')?.click()); }],
      ['palette.deploy', () => { M.go('server'); M.runJob('deploy', 0); }],
      ['palette.quickTunnel', () => { state.tunnelMode = 'quick'; M.go('tunnel'); }],
      ['palette.namedTunnel', () => { state.tunnelMode = 'named'; state.namedMode = 'guided'; M.go('tunnel', { focus: 'tun-host' }); }],
      ['palette.tokenTunnel', () => { state.tunnelMode = 'named'; state.namedMode = 'token'; M.go('tunnel', { focus: 'tun-token' }); }],
      ['palette.generate', () => { M.go('server'); requestAnimationFrame(() => M.$('#site-generate')?.click()); }],
      ['palette.pickKey', async () => { const p = await api.dialog.pickKey(); if (p) { state.server.keyPath = p; state.server.auth = 'key'; M.go('server'); M.rerender(); } }],
      ['palette.saveLog', () => M.saveLog(false), 'Ctrl+S'],
      ['palette.copyLog', () => M.copy(state.logs.map((l) => l.text).join('\n'), t('toast.logCopied'))],
      ['palette.toggleTheme', () => M.toggleTheme(), 'Ctrl+Shift+L'],
      ['palette.notifications', () => M.toggleNotifications(true)],
      ['palette.onboarding', () => M.openOnboarding(true)],
      ['palette.cancel', () => api.jobs.cancel()],
      ['palette.reload', () => api.win.reload(), 'Ctrl+R'],
    ].map(([k, run, hint]) => ({ group: 'palette.actions', label: t(k), hint, run }));
    const S = state.settings;
    const valueOf = (key) => {
      const v = S[key];
      if (typeof v === 'boolean') return t(v ? 'common.on' : 'common.off');
      if (key === 'theme') return t('settings.' + v);
      if (key === 'motion') return t('settings.' + v);
      if (key === 'language') return t({ en: 'settings.english', yue: 'settings.cantonese', bi: 'settings.bilingual' }[v]);
      if (key === 'seed') return v;
      return '';
    };
    const settings = M.SETTINGS.map((s) => ({ group: 'palette.settings', label: t(s.label), hint: valueOf(s.key), extra: t(s.info), run: () => M.go('settings', { focus: s.id }) }));
    const serverFields = [
      ['srv-host', 'server.host'], ['srv-port', 'server.port'], ['srv-user', 'server.user'], ['srv-sudo', 'server.sudo'],
      ['site-name', 'server.siteName'], ['site-admin', 'server.adminUser'], ['site-email', 'server.adminEmail'],
      ['site-password', 'server.adminPassword'], ['site-seed', 'server.seed'], ['site-port', 'server.httpPort'],
      ...Object.keys(M.appInfo?.phpSettings || {}).map(name => ['php-' + name.replaceAll('.', '-'), 'php.' + name]),
    ].map(([id, k]) => ({ group: 'palette.fields', label: t(k), hint: t('nav.server'), run: () => M.go('server', { focus: id }) }));
    return [...pages, ...actions, ...settings, ...serverFields];
  }

  function renderPalette(list, compiled) {
    const all = paletteEntries();
    palItems = all.filter((e) => window.MMDRegex.test(e.label + ' ' + (e.hint || '') + ' ' + (e.extra || '') + ' ' + t(e.group), compiled));
    palActive = Math.min(palActive, Math.max(0, palItems.length - 1));
    list.replaceChildren();
    if (!palItems.length) { list.append(h('li', { class: 'empty muted', text: t('palette.none') })); return; }
    let group = '';
    palItems.forEach((e, i) => {
      if (e.group !== group) { group = e.group; list.append(h('li', { class: 'palette-group', role: 'presentation', text: t(group) })); }
      const label = h('span', { class: 'grow' });
      M.highlightInto(label, e.label, compiled);
      const li = h('li', { class: 'palette-item' + (i === palActive ? ' active' : ''), role: 'option', id: 'pal-opt-' + i, 'aria-selected': String(i === palActive) },
        label, e.hint ? h('kbd', { text: e.hint }) : null);
      li.addEventListener('click', () => { palActive = i; runPalette(); });
      li.addEventListener('mousemove', () => { if (palActive !== i) { palActive = i; highlightActive(list); } });
      list.append(li);
    });
    M.$('#palette-input')?.setAttribute('aria-activedescendant', 'pal-opt-' + palActive);
  }

  function highlightActive(list) {
    M.$$('.palette-item', list).forEach((el) => {
      const on = el.id === 'pal-opt-' + palActive;
      el.classList.toggle('active', on);
      el.setAttribute('aria-selected', String(on));
      if (on) el.scrollIntoView({ block: 'nearest' });
    });
    M.$('#palette-input')?.setAttribute('aria-activedescendant', 'pal-opt-' + palActive);
  }

  function runPalette() {
    const e = palItems[palActive];
    M.closePalette();
    if (e) e.run();
  }

  M.openPalette = (prefill) => {
    const host = M.$('#palette');
    if (!host.hidden) { M.$('#palette-input').focus(); return; }
    if (typeof prefill === 'string') { palSearch.query = prefill; palSearch.regex = false; }
    palActive = 0;
    const list = h('ul', { class: 'palette-list', role: 'listbox', id: 'palette-list', 'aria-label': t('palette.results') });
    const bar = M.searchBar({ id: 'palette-input', placeholder: t('palette.placeholder'), state: palSearch, onChange: (c) => renderPalette(list, c) });
    bar.input.setAttribute('role', 'combobox');
    bar.input.setAttribute('aria-controls', 'palette-list');
    bar.input.setAttribute('aria-expanded', 'true');
    bar.input.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') { e.preventDefault(); palActive = Math.min(palItems.length - 1, palActive + 1); highlightActive(list); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); palActive = Math.max(0, palActive - 1); highlightActive(list); }
      else if (e.key === 'Enter') { e.preventDefault(); runPalette(); }
    });
    host.replaceChildren(h('div', { class: 'scrim', onclick: () => M.closePalette() }),
      h('div', { class: 'palette-card', role: 'dialog', 'aria-modal': 'true', 'aria-label': t('palette.title') }, bar.el, list,
        h('div', { class: 'palette-foot muted small' }, h('span', { text: t('palette.foot') }))));
    host.hidden = false;
    releasePal = trap(host, () => M.closePalette());
    bar.input.focus();
    bar.input.select();
  };

  M.closePalette = () => {
    const host = M.$('#palette');
    if (host.hidden) return;
    host.hidden = true;
    host.replaceChildren();
    if (releasePal) { releasePal(); releasePal = null; }
  };

  // ---------- context menu ----------
  let ctxTarget = null;
  M.closeContextMenu = () => {
    const menu = M.$('#ctxmenu');
    if (menu.hidden) return;
    menu.hidden = true;
    menu.replaceChildren();
  };

  function isField(el) {
    return el && (el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && !['button', 'checkbox', 'radio', 'color', 'range', 'file'].includes(el.type)));
  }

  M.openContextMenu = (x, y, target) => {
    ctxTarget = target;
    const menu = M.$('#ctxmenu');
    const sel = String(window.getSelection() || '');
    const field = isField(target) ? target : null;
    const fieldSel = field && field.selectionStart !== field.selectionEnd ? field.value.slice(field.selectionStart, field.selectionEnd) : '';
    const logLine = target.closest?.('.log-line');
    const code = target.closest?.('.code-block');
    const items = [];
    if (field) {
      items.push({ label: 'ctx.cut', key: 'Ctrl+X', disabled: !fieldSel || field.type === 'password', run: async () => { await api.clipboard.write(fieldSel); field.focus(); document.execCommand('delete'); } });
      items.push({ label: 'ctx.copy', key: 'Ctrl+C', disabled: !fieldSel || field.type === 'password', run: () => M.copy(fieldSel) });
      items.push({ label: 'ctx.paste', key: 'Ctrl+V', run: async () => { const txt = await api.clipboard.read(); field.focus(); document.execCommand('insertText', false, txt); } });
      items.push({ label: 'ctx.selectAll', key: 'Ctrl+A', run: () => { field.focus(); field.select(); } });
    } else {
      items.push({ label: 'ctx.copy', key: 'Ctrl+C', disabled: !sel, run: () => M.copy(sel) });
    }
    if (logLine) items.push({ label: 'ctx.copyLine', run: () => M.copy(logLine.dataset.line) });
    if (code) items.push({ label: 'ctx.copyCode', run: () => M.copy(code.querySelector('code, .log-list')?.innerText || code.innerText) });
    const query = (fieldSel || sel).trim();
    items.push({ label: 'ctx.search', key: 'Ctrl+Shift+F', run: () => M.openPalette(query.slice(0, 200)), extra: query ? '“' + query.slice(0, 24) + (query.length > 24 ? '…' : '') + '”' : '' });
    items.push('sep');
    items.push({ label: 'ctx.back', key: 'Alt+←', disabled: !state.history.length, run: () => M.back() });
    items.push({ label: 'ctx.reload', key: 'Ctrl+R', run: () => api.win.reload() });
    items.push({ label: 'ctx.theme', key: 'Ctrl+Shift+L', run: () => M.toggleTheme() });
    menu.replaceChildren(...items.map((it) => it === 'sep'
      ? h('div', { class: 'ctx-sep', role: 'separator' })
      : h('button', { type: 'button', role: 'menuitem', class: 'ctx-item', disabled: !!it.disabled, tabindex: '-1', onclick: () => { M.closeContextMenu(); it.run(); } },
        h('span', { class: 'grow', text: t(it.label) + (it.extra ? ' ' + it.extra : '') }), it.key ? h('kbd', { text: it.key }) : null)));
    menu.hidden = false;
    const r = menu.getBoundingClientRect();
    menu.style.left = Math.min(x, window.innerWidth - r.width - 8) + 'px';
    menu.style.top = Math.min(y, window.innerHeight - r.height - 8) + 'px';
    (menu.querySelector('.ctx-item:not([disabled])'))?.focus();
  };

  document.addEventListener('contextmenu', (e) => {
    e.preventDefault();
    // Tab group chips carry dockview's own rename and colour menu.
    if (e.target.closest?.('.dv-tab-group-chip, .dv-context-menu')) return;
    M.openContextMenu(e.clientX, e.clientY, e.target);
  });
  document.addEventListener('pointerdown', (e) => { if (!e.target.closest('#ctxmenu')) M.closeContextMenu(); }, true);
  window.addEventListener('blur', () => M.closeContextMenu());
  M.$('#ctxmenu').addEventListener('keydown', (e) => {
    const items = M.$$('.ctx-item:not([disabled])', M.$('#ctxmenu'));
    const i = items.indexOf(document.activeElement);
    if (e.key === 'Escape') { e.preventDefault(); M.closeContextMenu(); ctxTarget?.focus?.(); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); items[(i + 1) % items.length]?.focus(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); items[(i - 1 + items.length) % items.length]?.focus(); }
  });
})();
