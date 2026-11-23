'use strict';
// Pages: Server, Tunnel, Logs, Settings and About.
(function () {
  const M = window.MMD;
  const { h, t, icon, state, api } = M;

  // ---------- form helpers ----------
  function field(id, labelKey, control, { info, hint, grow, cls = '' } = {}) {
    const label = h('label', { class: 'field-label', for: id, text: t(labelKey) });
    const [ib, tip] = info ? M.infoButton(info) : [null, null];
    return h('div', { class: 'field ' + cls + (grow ? ' grow-' + grow : '') },
      h('div', { class: 'field-head' }, label, ib),
      control,
      hint ? h('span', { class: 'field-hint', text: t(hint) }) : null,
      tip);
  }

  function textInput(id, key, { type = 'text', mono, placeholder, autocomplete = 'off', inputmode, target = state.server } = {}) {
    const el = h('input', {
      id, type, class: 'input' + (mono ? ' mono' : ''), value: target[key] ?? '', placeholder,
      autocomplete, spellcheck: 'false', inputmode,
    });
    el.addEventListener('input', () => {
      target[key] = el.value;
      el.removeAttribute('aria-invalid');
      if (target === state.server) saveServerSoon();
    });
    return el;
  }

  function secretInput(id, key, opts = {}) {
    const input = textInput(id, key, { ...opts, type: 'password', autocomplete: 'new-password' });
    const btn = h('button', { type: 'button', class: 'icon-btn input-adorn', 'aria-label': t('common.show'), 'aria-pressed': 'false' }, icon('eye', 18));
    btn.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', String(show));
      btn.setAttribute('aria-label', t(show ? 'common.hide' : 'common.show'));
    });
    return h('div', { class: 'input-wrap' }, input, btn);
  }

  function phpLimitField(name, spec) {
    const id = 'php-' + name.replaceAll('.', '-');
    const control = h('select', { id, class: 'input' },
      h('option', { value: '', text: t('php.keep', { value: spec.default }) }),
      spec.options.map(value => h('option', { value, text: value })));
    control.value = state.server.phpLimits?.[name] || '';
    control.addEventListener('change', () => {
      state.server.phpLimits = { ...state.server.phpLimits, [name]: control.value };
      saveServerSoon();
    });
    return field(id, 'php.' + name, control);
  }

  let saveTimer = null;
  function saveServerSoon() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => api.settings.set({ lastServer: state.server }), 600);
  }

  M.segmented = (id, options, value, onPick, labelKey) => {
    const group = h('div', { class: 'seg', role: 'radiogroup', id, 'aria-label': labelKey ? t(labelKey) : null });
    const buttons = options.map(([v, k]) => {
      const b = h('button', { type: 'button', role: 'radio', 'aria-checked': String(v === value), class: v === value ? 'on' : '', tabindex: v === value ? '0' : '-1', text: t(k) });
      b.addEventListener('click', () => onPick(v));
      return b;
    });
    group.addEventListener('keydown', (e) => {
      const i = buttons.indexOf(document.activeElement);
      if (i < 0) return;
      const d = e.key === 'ArrowRight' || e.key === 'ArrowDown' ? 1 : e.key === 'ArrowLeft' || e.key === 'ArrowUp' ? -1 : 0;
      if (!d) return;
      e.preventDefault();
      const n = buttons[(i + d + buttons.length) % buttons.length];
      n.focus();
      n.click();
    });
    group.append(...buttons);
    return group;
  };

  // ---------- jobs ----------
  M.JOB_STEPS = {
    limits: ['connect', 'limits'],
    deploy: ['connect', 'docker', 'upload', 'image', 'start', 'health'],
    quick: ['connect', 'configure', 'starttunnel', 'quickurl'],
    named: ['connect', 'prepare', 'login', 'create', 'route', 'config', 'restart', 'connected'],
    token: ['connect', 'configure', 'restart', 'connected'],
  };

  M.validateServer = (forDeploy) => {
    const s = state.server;
    const problems = [];
    if (!s.host.trim()) problems.push(['srv-host', 'val.host']);
    if (!(Number(s.port) >= 1 && Number(s.port) <= 65535)) problems.push(['srv-port', 'val.port']);
    if (!s.user.trim()) problems.push(['srv-user', 'val.user']);
    if (s.auth === 'key' && !s.keyPath.trim()) problems.push(['srv-key', 'val.key']);
    if (s.auth === 'password' && !s.password) problems.push(['srv-password', 'val.password']);
    if (forDeploy) {
      if (!s.siteName.trim()) problems.push(['site-name', 'val.siteName']);
      if (/'/.test(s.siteName) && /["$\\`]/.test(s.siteName)) problems.push(['site-name', 'val.siteNameChars']);
      if (!/^[a-z0-9._-]+$/i.test(s.adminUser)) problems.push(['site-admin', 'val.adminUser']);
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s.adminEmail)) problems.push(['site-email', 'val.adminEmail']);
      const p = s.adminPassword;
      if (!(p.length >= 8 && /[A-Z]/.test(p) && /[a-z]/.test(p) && /\d/.test(p) && /[^A-Za-z0-9]/.test(p))) problems.push(['site-password', 'val.adminPassword']);
      if (/['"$\\`]/.test(p)) problems.push(['site-password', 'val.adminPasswordChars']);
      if (!(Number(s.httpPort) >= 1 && Number(s.httpPort) <= 65535)) problems.push(['site-port', 'val.httpPort']);
    }
    for (const el of M.$$('[aria-invalid="true"]')) el.removeAttribute('aria-invalid');
    if (problems.length) {
      if (state.page !== 'server') M.go('server');
      requestAnimationFrame(() => {
        for (const [id] of problems) document.getElementById(id)?.setAttribute('aria-invalid', 'true');
        document.getElementById(problems[0][0])?.focus();
      });
      M.toast(t(problems[0][1]), { kind: 'error' });
      return false;
    }
    return true;
  };

  M.runJob = async (name, from = 0, params = {}) => {
    if (state.running) { M.toast(t('toast.busy'), { kind: 'error' }); return; }
    if (!M.validateServer(name === 'deploy')) return;
    M.hideError();
    state.running = name;
    state.jobs[name] = { ...(state.jobs[name] || {}), status: 'running' };
    M.onJobsChanged();
    try {
      await api.jobs.run(name, from, params, { ...state.server });
    } catch (e) {
      state.running = null;
      M.toast(String(e.message || e).replace(/^Error invoking remote method '[^']+': (Error: )?/, ''), { kind: 'error' });
      M.onJobsChanged();
    }
  };

  M.onJobUpdate = (job) => {
    const prev = state.jobs[job.name];
    state.jobs[job.name] = job;
    state.running = job.status === 'running' ? job.name : (state.running === job.name ? null : state.running);
    if (prev?.status === 'running' || prev?.status === undefined) {
      if (job.status === 'done' && job.name === 'limits') {
        M.toast(t('php.verified'), { kind: 'ok' });
        if (M.workspace.isVisible('server')) M.rerender();
      } else if (job.status === 'done') M.celebrate(job.name, job.result || {});
      if (job.status === 'failed' && job.error) M.showError(job.name, job.error);
      if (job.status === 'cancelled') M.toast(t('toast.cancelled'));
    }
    M.onJobsChanged();
  };

  M.onJobsChanged = () => {
    const limitsStatus = M.$('#php-runtime-status');
    if (limitsStatus) limitsStatus.textContent = state.running === 'limits' ? t('step.limits') : '';
    for (const el of M.$$('[data-job-view]')) el.replaceWith(M.stepCard(el.dataset.jobView, el.dataset.compact === '1'));
    for (const el of M.$$('[data-running-disable]')) el.disabled = !!state.running;
    M.renderBell?.();
    if (M.workspace.isVisible('tunnel')) M.refreshTunnelBody?.();
  };

  M.stepCard = (name, compact) => {
    const job = state.jobs[name];
    const ids = M.JOB_STEPS[name];
    const list = h('ol', { class: 'steps' + (compact ? ' compact' : '') });
    ids.forEach((id, i) => {
      const st = job?.steps?.[i]?.status || 'pending';
      const row = h('li', { class: 'step step-' + st },
        h('span', { class: 'step-ring', 'aria-hidden': 'true' }, st === 'done' ? icon('check', 16) : st === 'failed' ? '!' : compact ? '' : String(i + 1)),
        h('span', { class: 'step-label', text: t('step.' + id) }),
        h('span', { class: 'sr-only', text: t('status.' + st) }));
      if (st === 'failed') {
        row.append(h('button', { type: 'button', class: 'btn btn-text btn-sm', 'data-running-disable': '1', onclick: () => M.runJob(name, i, job.params || {}) }, icon('retry', 16), h('span', { text: t('common.retry') })));
      }
      list.append(row);
    });
    const card = h('section', { class: 'card steps-card', 'data-job-view': name, 'data-compact': compact ? '1' : '0', 'aria-label': t('job.' + name) });
    if (!compact) card.append(h('h2', { class: 'card-title', text: t('server.steps') }));
    card.append(list);
    if (job?.status === 'running') {
      const last = state.logs.slice(-4);
      card.append(h('div', { class: 'tail', 'aria-live': 'off' }, last.map((l) => h('div', { class: 'tail-line', text: l.text }))));
      card.append(h('button', { type: 'button', class: 'btn btn-outline btn-sm', onclick: () => api.jobs.cancel() }, icon('stop', 16), h('span', { text: t('common.cancel') })));
    } else if (job?.status === 'done' && job.finishedAt) {
      card.append(h('p', { class: 'muted small', text: t('server.finishedIn', { s: Math.round((job.finishedAt - job.startedAt) / 1000) }) }));
    }
    return card;
  };

  // ---------- Server page ----------
  M.pages.server = (page) => {
    const s = state.server;
    const profiles = state.settings.profiles || [];
    const profileSel = h('select', { id: 'srv-profile', class: 'input select' },
      h('option', { value: '', text: profiles.length ? t('server.profilePick') : t('server.profileNone') }),
      profiles.map((p) => h('option', { value: p.id, text: p.name + (p.hasSecrets ? ' (' + t('settings.secretsSaved') + ')' : '') })));
    profileSel.addEventListener('change', async () => {
      if (!profileSel.value) return;
      const p = await api.profiles.load(profileSel.value);
      state.server.phpLimits = {};
      Object.assign(state.server, Object.fromEntries(Object.entries(p).filter(([k]) => k in state.server)));
      M.toast(t('toast.profileLoaded', { name: p.name }), { kind: 'ok' });
      M.rerender();
    });
    const saveProfile = h('button', { type: 'button', class: 'btn btn-outline btn-sm', id: 'srv-save-profile' }, icon('save', 16), h('span', { text: t('server.saveProfile') }));
    saveProfile.addEventListener('click', async () => {
      if (!s.host) { M.toast(t('val.host'), { kind: 'error' }); return; }
      const name = s.user ? s.user + '@' + s.host : s.host;
      const r = await api.profiles.save({ ...s, name });
      state.settings = await api.settings.get();
      M.toast(t(r.secretsStored ? 'toast.profileSavedSecrets' : 'toast.profileSaved', { name }), { kind: 'ok' });
      M.rerender();
    });

    const authSeg = M.segmented('srv-auth', [['key', 'server.authKey'], ['password', 'server.authPassword']], s.auth, (v) => { s.auth = v; saveServerSoon(); M.rerender(); }, 'server.auth');
    const keyInput = textInput('srv-key', 'keyPath', { mono: true, placeholder: '~/.ssh/id_ed25519' });
    const browse = h('button', { type: 'button', class: 'btn btn-tonal' }, icon('folder', 18), h('span', { text: t('server.browse') }));
    browse.addEventListener('click', async () => {
      const p = await api.dialog.pickKey();
      if (p) { s.keyPath = p; keyInput.value = p; saveServerSoon(); }
    });

    const genBtn = h('button', { type: 'button', class: 'btn btn-tonal', id: 'site-generate' }, icon('dice', 18), h('span', { text: t('server.generate') }));
    genBtn.addEventListener('click', async () => {
      s.adminPassword = await api.passwords.generate();
      const el = document.getElementById('site-password');
      el.value = s.adminPassword;
      el.type = 'text';
      M.toast(t('toast.generated'), { kind: 'ok', action: () => M.copy(s.adminPassword), actionLabel: t('common.copy') });
    });

    const seedPicker = h('input', { type: 'color', id: 'site-seed', class: 'color-input', value: s.seed, 'aria-label': t('server.seed') });
    seedPicker.addEventListener('input', () => { s.seed = seedPicker.value.toUpperCase(); seedHex.textContent = s.seed; saveServerSoon(); });
    const seedHex = h('code', { class: 'seed-hex', text: s.seed });

    const testChip = h('span', { class: 'test-result', 'aria-live': 'polite' });
    const renderTest = () => {
      testChip.replaceChildren();
      if (state.testing) testChip.append(h('span', { class: 'chip chip-neutral' }, h('span', { class: 'spinner sm', 'aria-hidden': 'true' }), t('server.testing')));
      else if (state.test?.ok) {
        const r = state.test.result;
        testChip.append(h('span', { class: 'chip chip-ok pop', text: t('server.connected', { os: r.os }) }),
          h('span', { class: 'chip ' + (r.docker ? 'chip-ok' : 'chip-warn') + ' pop', text: r.docker ? t('server.dockerYes', { v: r.dockerVersion }) : t('server.dockerNo') }));
        if (r.deployed) testChip.append(h('span', { class: 'chip chip-neutral pop', text: t('server.alreadyDeployed') }));
        if (!r.privilege) testChip.append(h('span', { class: 'chip chip-warn pop', text: t('server.noSudo') }));
      } else if (state.test && !state.test.ok) {
        testChip.append(h('span', { class: 'chip chip-err pop', text: state.test.error }));
      }
    };
    renderTest();
    const testBtn = h('button', { type: 'button', class: 'btn btn-outline btn-lg', id: 'srv-test', 'data-running-disable': '1', disabled: !!state.running }, h('span', { text: t('server.test') }));
    testBtn.addEventListener('click', async () => {
      if (!M.validateServer(false)) return;
      state.testing = true;
      renderTest();
      try {
        const result = await api.ssh.test({ ...s });
        state.test = { ok: true, result };
        M.toast(t('toast.testOk', { os: result.os }), { kind: 'ok' });
      } catch (e) {
        const msg = String(e.message || e).replace(/^Error invoking remote method '[^']+': (Error: )?/, '');
        state.test = { ok: false, error: msg };
        M.toast(t('toast.testFail'), { kind: 'error' });
      }
      state.testing = false;
      renderTest();
    });

    const deployLabel = state.running === 'deploy' ? t('server.deploying') : state.jobs.deploy?.status === 'done' ? t('server.redeploy') : t('server.deploy');
    const deployBtn = h('button', { type: 'button', class: 'btn btn-hero', id: 'srv-deploy', 'data-running-disable': '1', disabled: !!state.running },
      state.running === 'deploy' ? h('span', { class: 'spinner on-primary', 'aria-hidden': 'true' }) : icon('rocket', 22), h('span', { text: deployLabel }));
    deployBtn.addEventListener('click', () => M.runJob('deploy', 0));

    const form = h('section', { class: 'form-col' },
      h('h1', { class: 'page-title', text: t('server.title') }),
      h('p', { class: 'lead', text: t('server.lead') }),
      h('div', { class: 'row wrap gap-s align-end' }, field('srv-profile', 'server.profiles', profileSel, { grow: 3, info: 'info.profiles' }), saveProfile),
      h('fieldset', { class: 'group' },
        h('legend', { class: 'group-title', text: t('server.connection') }),
        h('div', { class: 'row wrap' },
          field('srv-host', 'server.host', textInput('srv-host', 'host', { placeholder: '203.0.113.24' }), { grow: 3, info: 'info.host' }),
          field('srv-port', 'server.port', textInput('srv-port', 'port', { inputmode: 'numeric' }), { grow: 1 })),
        field('srv-user', 'server.user', textInput('srv-user', 'user', { placeholder: 'ubuntu' }), { info: 'info.user' }),
        h('div', { class: 'field' }, h('span', { class: 'field-label', id: 'srv-auth-label', text: t('server.auth') }), authSeg),
        s.auth === 'key'
          ? [field('srv-key', 'server.keyFile', h('div', { class: 'row gap-s' }, keyInput, browse), { info: 'info.key' }),
            field('srv-passphrase', 'server.passphrase', secretInput('srv-passphrase', 'passphrase'), { hint: 'server.optional' })]
          : field('srv-password', 'server.password', secretInput('srv-password', 'password'), { info: 'info.password' }),
        field('srv-sudo', 'server.sudo', secretInput('srv-sudo', 'sudoPassword'), { hint: 'server.sudoHint', info: 'info.sudo' })),
      h('fieldset', { class: 'group' },
        h('legend', { class: 'group-title', text: t('server.site') }),
        field('site-name', 'server.siteName', textInput('site-name', 'siteName')),
        h('div', { class: 'row wrap' },
          field('site-admin', 'server.adminUser', textInput('site-admin', 'adminUser'), { grow: 1 }),
          field('site-email', 'server.adminEmail', textInput('site-email', 'adminEmail', { type: 'email', placeholder: 'admin@example.org' }), { grow: 2 })),
        field('site-password', 'server.adminPassword', h('div', { class: 'row gap-s' }, secretInput('site-password', 'adminPassword'), genBtn), { info: 'info.adminPassword' }),
        h('div', { class: 'row wrap' },
          field('site-seed', 'server.seed', h('div', { class: 'row gap-s align-center' }, seedPicker, seedHex), { grow: 1, info: 'info.seed' }),
          field('site-port', 'server.httpPort', textInput('site-port', 'httpPort', { inputmode: 'numeric' }), { grow: 1, info: 'info.httpPort' }))),
      h('fieldset', { class: 'group' },
        h('legend', { class: 'group-title', text: t('php.title') }),
        h('p', { class: 'field-hint', text: t('php.description') }),
        Object.entries(M.appInfo?.phpSettings || {}).map(([name, spec]) => phpLimitField(name, spec)),
        h('div', { class: 'row wrap gap-s' },
          h('button', { type: 'button', id: 'php-read', class: 'btn btn-outline', 'data-running-disable': '1', disabled: !!state.running,
            text: t('php.read'), onclick: () => M.runJob('limits', 0, { apply: false }) }),
          h('button', { type: 'button', id: 'php-apply', class: 'btn btn-outline', 'data-running-disable': '1', disabled: !!state.running,
            text: t('php.apply'), onclick: () => M.runJob('limits', 0, { apply: true }) })),
        h('span', { id: 'php-runtime-status', role: 'status', text: state.running === 'limits' ? t('step.limits') : '' }),
        state.jobs.limits?.status === 'done' && state.jobs.limits.result?.values
          ? h('details', { open: true }, h('summary', { text: t('php.readback') + ' · ' + state.jobs.limits.result.host }),
            h('dl', {}, Object.entries(state.jobs.limits.result.values).map(([name, value]) =>
              [h('dt', { text: t('php.' + name) }), h('dd', { text: value })]))) : null),
      h('div', { class: 'row wrap gap-s align-center' }, testBtn, testChip),
      deployBtn);

    page.append(h('div', { class: 'split' }, form, h('div', { class: 'side-col' }, M.stepCard('deploy', false),
      h('section', { class: 'card hint-card' },
        h('h2', { class: 'card-title', text: t('server.whatHappens') }),
        h('p', { class: 'muted', text: t('server.whatHappensText') }),
        M.codeBlock('~/material-moodle/\n  docker-compose.yml\n  .env            (mode 600)\n  cloudflared/', { label: t('server.layout') })))));
  };

  // ---------- Tunnel page ----------
  let tunnelBody = null;
  M.pages.tunnel = (page) => {
    const pick = (mode) => { state.tunnelMode = mode; M.rerender(); };
    const card = (mode, titleKey, textKey, iconName) => {
      const on = state.tunnelMode === mode;
      return h('button', { type: 'button', role: 'radio', 'aria-checked': String(on), class: 'choice-card' + (on ? ' on' : ''), onclick: () => pick(mode) },
        h('span', { class: 'choice-icon' }, icon(iconName, 22)),
        h('span', { class: 'choice-title', text: t(titleKey) }),
        h('span', { class: 'choice-text', text: t(textKey) }));
    };
    tunnelBody = h('div', { class: 'tunnel-body' });
    page.append(h('div', { class: 'narrow' },
      h('button', { type: 'button', class: 'link-btn', onclick: () => M.go('server') }, '‹ ' + t('nav.server')),
      h('h1', { class: 'page-title', text: t('tunnel.title') }),
      h('p', { class: 'lead', text: t('tunnel.lead') }),
      h('div', { class: 'choice-grid', role: 'radiogroup', 'aria-label': t('tunnel.type') },
        card('quick', 'tunnel.quick', 'tunnel.quickText', 'link'),
        card('named', 'tunnel.named', 'tunnel.namedText', 'globe')),
      tunnelBody));
    tunnelBody.replaceChildren(state.tunnelMode === 'quick' ? quickSection() : namedSection());
  };

  M.refreshTunnelBody = () => {
    if (!tunnelBody || !tunnelBody.isConnected) return;
    const focusId = document.activeElement?.id;
    tunnelBody.replaceChildren(state.tunnelMode === 'quick' ? quickSection() : namedSection());
    if (focusId && tunnelBody.querySelector('#' + CSS.escape(focusId))) document.getElementById(focusId).focus();
  };

  function urlResult(url, labelKey) {
    return h('div', { class: 'url-result pop' },
      h('span', { class: 'url-label', text: t(labelKey) }),
      h('div', { class: 'row wrap gap-s align-center' },
        h('code', { class: 'url-code', text: url }),
        h('button', { type: 'button', class: 'btn btn-tonal', onclick: () => M.copy(url) }, icon('copy', 18), h('span', { text: t('common.copy') })),
        h('button', { type: 'button', class: 'btn btn-filled', onclick: () => api.shell.open(url) }, icon('open', 18), h('span', { text: t('common.open') }))));
  }

  function quickSection() {
    const job = state.jobs.quick;
    const running = state.running === 'quick';
    const btn = h('button', { type: 'button', class: 'btn btn-filled btn-lg', id: 'tun-quick-start', 'data-running-disable': '1', disabled: !!state.running },
      running ? h('span', { class: 'spinner on-primary', 'aria-hidden': 'true' }) : icon('link', 18),
      h('span', { text: t(running ? 'tunnel.quickRunning' : job?.status === 'done' ? 'tunnel.quickAgain' : 'tunnel.quickStart') }));
    btn.addEventListener('click', () => M.runJob('quick', 0));
    return h('section', { class: 'card slide' },
      h('h2', { class: 'card-title', text: t('tunnel.quickTitle') }),
      h('p', { class: 'muted', text: t('tunnel.quickExplain') }),
      job?.status === 'done' && job.result?.url ? urlResult(job.result.url, 'tunnel.quickYour') : null,
      h('div', { class: 'row wrap gap-s align-center' }, btn),
      job ? M.stepCard('quick', true) : null);
  }

  function namedSection() {
    const job = state.jobs[state.namedMode === 'token' ? 'token' : 'named'];
    const sub = M.segmented('tun-named-mode', [['guided', 'tunnel.guided'], ['token', 'tunnel.tokenMode']], state.namedMode, (v) => { state.namedMode = v; M.refreshTunnelBody(); }, 'tunnel.method');
    if (state.namedMode === 'token') {
      const tokenInput = textInput('tun-token', 'token', { target: state, mono: true, type: 'password', placeholder: 'eyJhIjoi…' });
      const hostInput = textInput('tun-token-host', 'tokenHost', { target: state, placeholder: 'learn.example.org' });
      const btn = h('button', { type: 'button', class: 'btn btn-filled btn-lg', 'data-running-disable': '1', disabled: !!state.running },
        state.running === 'token' ? h('span', { class: 'spinner on-primary', 'aria-hidden': 'true' }) : icon('key', 18), h('span', { text: t('tunnel.tokenStart') }));
      btn.addEventListener('click', () => {
        if (!state.token.trim()) { M.toast(t('val.token'), { kind: 'error' }); document.getElementById('tun-token').focus(); return; }
        M.runJob('token', 0, { token: state.token, hostname: state.tokenHost.trim().toLowerCase() });
      });
      return h('section', { class: 'card slide' }, sub,
        h('p', { class: 'muted', text: t('tunnel.tokenExplain') }),
        field('tun-token', 'tunnel.token', tokenInput, { info: 'info.token' }),
        field('tun-token-host', 'tunnel.tokenHost', hostInput, { hint: 'tunnel.tokenHostHint' }),
        btn,
        job?.status === 'done' && job.result?.url ? urlResult(job.result.url, 'tunnel.namedYour') : null,
        job ? M.stepCard('token', true) : null);
    }
    const idx = (id) => M.JOB_STEPS.named.indexOf(id);
    const stepStatus = (id) => job?.steps?.[idx(id)]?.status;
    const guide = [
      { n: 1, title: 'tunnel.g1', desc: 'tunnel.g1Text', done: stepStatus('login') === 'done', active: stepStatus('login') === 'running' },
      { n: 2, title: 'tunnel.g2', desc: 'tunnel.g2Text', done: stepStatus('login') === 'done', active: stepStatus('login') === 'running' && !!state.loginUrl },
      { n: 3, title: 'tunnel.g3', desc: 'tunnel.g3Text', done: job?.status === 'done', active: ['create', 'route', 'config', 'restart', 'connected'].some((s) => stepStatus(s) === 'running') },
    ];
    const hostInput = textInput('tun-host', 'hostname', { target: state, placeholder: 'learn.example.org' });
    const start = h('button', { type: 'button', class: 'btn btn-filled btn-lg', id: 'tun-named-start', 'data-running-disable': '1', disabled: !!state.running },
      state.running === 'named' ? h('span', { class: 'spinner on-primary', 'aria-hidden': 'true' }) : icon('globe', 18),
      h('span', { text: t(state.running === 'named' ? 'tunnel.namedRunning' : 'tunnel.namedStart') }));
    start.addEventListener('click', () => {
      const host = state.hostname.trim().toLowerCase();
      if (!/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/.test(host)) {
        M.toast(t('val.hostname'), { kind: 'error' });
        document.getElementById('tun-host').setAttribute('aria-invalid', 'true');
        document.getElementById('tun-host').focus();
        return;
      }
      M.runJob('named', 0, { hostname: host });
    });
    const relogin = h('button', { type: 'button', class: 'btn btn-text', 'data-running-disable': '1', disabled: !!state.running, text: t('tunnel.relogin') });
    relogin.addEventListener('click', () => {
      const host = state.hostname.trim().toLowerCase();
      if (!host) { M.toast(t('val.hostname'), { kind: 'error' }); return; }
      M.runJob('named', 0, { hostname: host, relogin: true });
    });
    const waiting = state.running === 'named' && stepStatus('login') === 'running';
    const loginBox = state.loginUrl
      ? h('div', { class: 'login-box glow' },
        h('code', { class: 'login-url', text: state.loginUrl }),
        h('button', { type: 'button', class: 'btn btn-tonal', onclick: () => M.copy(state.loginUrl, t('toast.linkCopied')) }, icon('copy', 18), h('span', { text: t('tunnel.copyLink') })),
        h('button', { type: 'button', class: 'btn btn-filled', onclick: () => api.shell.open(state.loginUrl) }, icon('open', 18), h('span', { text: t('tunnel.openBrowser') })))
      : null;
    return h('section', { class: 'card slide guide' }, sub,
      field('tun-host', 'tunnel.address', hostInput, { info: 'info.hostname', cls: 'max-420' }),
      h('ol', { class: 'guide-steps' }, guide.map((g) => h('li', { class: 'guide-step' + (g.done ? ' done' : '') + (g.active ? ' active' : '') },
        h('span', { class: 'guide-badge', 'aria-hidden': 'true' }, g.done ? icon('check', 18) : String(g.n)),
        h('div', { class: 'guide-text' }, h('span', { class: 'guide-title', text: t(g.title) }), h('span', { class: 'muted', text: t(g.desc) }))))),
      loginBox,
      waiting || stepStatus('login') === 'done' ? h('div', { class: 'row gap-s align-center muted', 'aria-live': 'polite' },
        waiting ? h('span', { class: 'spinner sm', 'aria-hidden': 'true' }) : icon('check', 18),
        h('span', { text: t(waiting ? (state.loginUrl ? 'tunnel.waitApprove' : 'tunnel.waitLink') : 'tunnel.approved') })) : null,
      h('div', { class: 'row wrap gap-s' }, start, relogin),
      job?.status === 'done' && job.result?.url ? urlResult(job.result.url, 'tunnel.namedYour') : null,
      job ? M.stepCard('named', true) : null);
  }

  // ---------- Logs page ----------
  const logSearch = { query: '', regex: false, ignoreCase: true, builderOpen: true, follow: true };
  let logList = null;
  let logCount = null;
  let logCompiled = null;

  function logLineEl(l) {
    const text = h('span', { class: 'log-text' });
    M.highlightInto(text, l.text, logCompiled);
    return h('div', { class: 'log-line lvl-' + l.level, 'data-line': l.text },
      h('span', { class: 'log-time', text: M.fmtTime(l.t) }),
      h('span', { class: 'log-src src-' + l.source, text: l.source }), text);
  }

  function renderLogLines() {
    if (!logList) return;
    const lines = state.logs.filter((l) => window.MMDRegex.test(l.source + ' ' + l.text, logCompiled));
    logList.replaceChildren(...(lines.length ? lines.map(logLineEl) : [h('div', { class: 'empty', text: t(state.logs.length ? 'logs.noMatch' : 'logs.empty') })]));
    logCount.textContent = t('logs.count', { shown: lines.length, total: state.logs.length });
    if (logSearch.follow) logList.scrollTop = logList.scrollHeight;
  }

  M.onLogLine = (l) => {
    if (!logList || !logList.isConnected) return;
    if (!window.MMDRegex.test(l.source + ' ' + l.text, logCompiled)) return;
    logList.querySelector('.empty')?.remove();
    logList.append(logLineEl(l));
    while (logList.children.length > 5000) logList.firstElementChild.remove();
    logCount.textContent = t('logs.count', { shown: logList.children.length, total: state.logs.length });
    if (logSearch.follow) logList.scrollTop = logList.scrollHeight;
  };

  M.visibleLogText = () => M.$$('.log-line', logList || document).map((el) => el.textContent.replace(/^(\d\d:\d\d:\d\d(?: [AP]M)?)/, '$1 ')).join('\n');

  M.saveLog = async (filteredOnly) => {
    const path = await api.logs.save(filteredOnly ? M.visibleLogText() : undefined);
    if (path) M.toast(t('toast.logSaved', { path }), { kind: 'ok' });
  };

  M.pages.logs = (page) => {
    const bar = M.searchBar({ id: 'log-search', placeholder: t('logs.search'), state: logSearch, onChange: (c) => { logCompiled = c; renderLogLines(); } });
    logCompiled = bar.compiled();
    logList = h('div', { class: 'log-list' + (state.settings.logWrap ? ' wrap' : ''), id: 'log-list', role: 'log', 'aria-label': t('logs.title'), tabindex: '0' });
    logList.addEventListener('scroll', () => {
      const atEnd = logList.scrollHeight - logList.scrollTop - logList.clientHeight < 24;
      if (atEnd !== logSearch.follow) { logSearch.follow = atEnd; followBtn.classList.toggle('on', atEnd); followBtn.setAttribute('aria-pressed', String(atEnd)); }
    });
    logCount = h('span', { class: 'muted small', 'aria-live': 'polite' });
    const followBtn = h('button', { type: 'button', class: 'toggle-chip' + (logSearch.follow ? ' on' : ''), 'aria-pressed': String(logSearch.follow), text: t('logs.follow') });
    followBtn.addEventListener('click', () => { logSearch.follow = !logSearch.follow; followBtn.classList.toggle('on', logSearch.follow); followBtn.setAttribute('aria-pressed', String(logSearch.follow)); if (logSearch.follow) logList.scrollTop = logList.scrollHeight; });
    const copyBtn = h('button', { type: 'button', class: 'btn btn-tonal btn-sm', id: 'log-copy' }, icon('copy', 16), h('span', { text: t('logs.copy') }));
    copyBtn.addEventListener('click', () => M.copy(M.visibleLogText(), t('toast.logCopied')));
    const saveBtn = h('button', { type: 'button', class: 'btn btn-tonal btn-sm', id: 'log-save', title: 'Ctrl+S' }, icon('save', 16), h('span', { text: t('logs.save') }));
    saveBtn.addEventListener('click', () => M.saveLog(true));
    const clearBtn = h('button', { type: 'button', class: 'btn btn-text btn-sm', id: 'log-clear' }, icon('trash', 16), h('span', { text: t('logs.clear') }));
    clearBtn.addEventListener('click', async () => { await api.logs.clear(); state.logs = []; renderLogLines(); M.toast(t('toast.logCleared')); });
    page.append(h('div', { class: 'logs-page' },
      h('div', { class: 'row wrap align-center gap-s' }, h('h1', { class: 'page-title grow', text: t('logs.title') }), logCount, followBtn, copyBtn, saveBtn, clearBtn),
      bar.el,
      h('div', { class: 'code-block log-block' }, logList,
        h('button', { type: 'button', class: 'btn btn-tonal btn-sm code-copy', 'aria-label': t('logs.copy'), onclick: () => M.copy(M.visibleLogText(), t('toast.logCopied')) }, icon('copy', 16), h('span', { text: t('common.copy') })))));
    renderLogLines();
  };

  // ---------- Settings page ----------
  M.SETTINGS = [
    { id: 'set-theme', key: 'theme', label: 'settings.theme', info: 'info.theme', group: 'settings.appearance' },
    { id: 'set-seed', key: 'seed', label: 'settings.seed', info: 'info.appSeed', group: 'settings.appearance' },
    { id: 'set-motion', key: 'motion', label: 'settings.motion', info: 'info.motion', group: 'settings.appearance' },
    { id: 'set-language', key: 'language', label: 'settings.language', info: 'info.language', group: 'settings.languageGroup' },
    { id: 'set-remember', key: 'rememberSecrets', label: 'settings.remember', info: 'info.remember', group: 'settings.profilesGroup' },
    { id: 'set-profiles', key: 'profiles', label: 'settings.profiles', info: 'info.profilesList', group: 'settings.profilesGroup' },
    { id: 'set-hosts', key: 'knownHosts', label: 'settings.hosts', info: 'info.hosts', group: 'settings.security' },
    { id: 'set-confirm', key: 'confirmClose', label: 'settings.confirmClose', info: 'info.confirmClose', group: 'settings.behaviour' },
    { id: 'set-wrap', key: 'logWrap', label: 'settings.logWrap', info: 'info.logWrap', group: 'settings.behaviour' },
    { id: 'set-onboarding', key: 'onboarding', label: 'settings.onboarding', info: 'info.onboarding', group: 'settings.behaviour' },
  ];

  function switchControl(id, value, onChange) {
    const sw = h('button', { type: 'button', role: 'switch', id, class: 'switch' + (value ? ' on' : ''), 'aria-checked': String(!!value) }, h('span', { class: 'switch-thumb' }));
    sw.addEventListener('click', () => onChange(!(sw.getAttribute('aria-checked') === 'true')));
    return sw;
  }

  let storage = null;
  let hosts = null;

  M.pages.settings = (page) => {
    const S = state.settings;
    const set = async (patch, rerender = true) => { await M.updateSettings(patch); if (rerender) M.rerender(); };
    const controls = {
      theme: () => M.segmented('set-theme', [['system', 'settings.system'], ['light', 'settings.light'], ['dark', 'settings.dark']], S.theme, (v) => set({ theme: v }), 'settings.theme'),
      seed: () => {
        const picker = h('input', { type: 'color', class: 'color-input', id: 'set-seed', value: S.seed, 'aria-label': t('settings.seedCustom') });
        picker.addEventListener('change', () => set({ seed: picker.value.toUpperCase() }));
        return h('div', { class: 'row wrap gap-s align-center' },
          window.MMDTheme.SWATCHES.map((c) => {
            const b = h('button', { type: 'button', class: 'swatch' + (c === S.seed ? ' on' : ''), 'aria-label': c, 'aria-pressed': String(c === S.seed), onclick: () => set({ seed: c }) });
            b.style.setProperty('--swatch', c);
            return b;
          }), picker);
      },
      motion: () => M.segmented('set-motion', [['expressive', 'settings.expressive'], ['calm', 'settings.calm'], ['off', 'settings.off']], S.motion, (v) => set({ motion: v }), 'settings.motion'),
      language: () => M.segmented('set-language', [['en', 'settings.english'], ['yue', 'settings.cantonese'], ['bi', 'settings.bilingual']], S.language, (v) => set({ language: v }), 'settings.language'),
      rememberSecrets: () => {
        const sw = switchControl('set-remember', S.rememberSecrets, async (v) => {
          if (v && storage && !storage.strong) { M.toast(t('toast.noKeychain'), { kind: 'error' }); return; }
          await set({ rememberSecrets: v });
          state.settings = await api.settings.get();
          M.rerender();
        });
        return h('div', { class: 'col gap-xs' }, sw, storage ? h('span', { class: 'field-hint', text: t(storage.strong ? 'settings.keychainOk' : 'settings.keychainNo', { backend: storage.backend }) }) : null);
      },
      profiles: () => {
        const list = S.profiles || [];
        if (!list.length) return h('span', { class: 'muted', id: 'set-profiles', tabindex: '-1', text: t('settings.noProfiles') });
        return h('ul', { class: 'mini-list', id: 'set-profiles', tabindex: '-1' }, list.map((p) => h('li', {},
          h('span', { class: 'grow', text: p.name + (p.hasSecrets ? '  ·  ' + t('settings.secretsSaved') : '') }),
          h('button', { type: 'button', class: 'btn btn-text btn-sm', text: t('settings.load'), onclick: async () => {
            const full = await api.profiles.load(p.id);
            Object.assign(state.server, Object.fromEntries(Object.entries(full).filter(([k]) => k in state.server)));
            M.go('server');
            M.toast(t('toast.profileLoaded', { name: p.name }), { kind: 'ok' });
          } }),
          h('button', { type: 'button', class: 'icon-btn', 'aria-label': t('settings.delete') + ' ' + p.name, onclick: async () => {
            await api.profiles.remove(p.id);
            state.settings = await api.settings.get();
            M.toast(t('toast.profileDeleted', { name: p.name }));
            M.rerender();
          } }, icon('trash', 18)))));
      },
      knownHosts: () => {
        const entries = Object.entries(hosts || {});
        if (!entries.length) return h('span', { class: 'muted', id: 'set-hosts', tabindex: '-1', text: t('settings.noHosts') });
        return h('ul', { class: 'mini-list', id: 'set-hosts', tabindex: '-1' }, entries.map(([id, fp]) => h('li', {},
          h('span', { class: 'grow' }, h('strong', { text: id }), h('br'), h('code', { class: 'small', text: fp })),
          h('button', { type: 'button', class: 'btn btn-text btn-sm', text: t('settings.forget'), onclick: async () => {
            await api.knownHosts.forget(id);
            hosts = await api.knownHosts.list();
            M.toast(t('toast.hostForgotten', { host: id }));
            M.rerender();
          } }))));
      },
      confirmClose: () => switchControl('set-confirm', S.confirmClose, (v) => set({ confirmClose: v })),
      logWrap: () => switchControl('set-wrap', S.logWrap, (v) => set({ logWrap: v })),
      onboarding: () => h('button', { type: 'button', class: 'btn btn-outline', id: 'set-onboarding', text: t('settings.runOnboarding'), onclick: () => M.openOnboarding(true) }),
    };
    const groups = new Map();
    for (const def of M.SETTINGS) {
      if (!groups.has(def.group)) groups.set(def.group, []);
      const [ib, tip] = M.infoButton(def.info);
      groups.get(def.group).push(h('div', { class: 'setting', 'data-setting': def.key },
        h('div', { class: 'setting-head' }, h('label', { class: 'setting-label', for: def.id, text: t(def.label) }), ib),
        tip, h('div', { class: 'setting-control' }, controls[def.key]())));
    }
    page.append(h('div', { class: 'narrow' },
      h('h1', { class: 'page-title', text: t('settings.title') }),
      h('p', { class: 'lead', text: t('settings.lead') }),
      [...groups].map(([g, rows]) => h('section', { class: 'card settings-card' }, h('h2', { class: 'card-title', text: t(g) }), rows))));
    if (!storage) api.profiles.storage().then((s) => { storage = s; if (M.workspace.isVisible('settings')) M.rerender(); });
    if (!hosts) api.knownHosts.list().then((k) => { hosts = k; if (M.workspace.isVisible('settings')) M.rerender(); });
  };

  // ---------- About page ----------
  let info = null;
  M.pages.about = (page) => {
    const links = [
      ['about.repo', 'https://github.com/Ding-Ding-Projects/material-moodle'],
      ['about.issues', 'https://github.com/Ding-Ding-Projects/material-moodle/issues'],
      ['about.moodle', 'https://moodle.org/'],
      ['about.tunnels', 'https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/'],
      ['about.docker', 'https://docs.docker.com/engine/install/'],
    ];
    const body = h('div', { class: 'narrow about' },
      h('div', { class: 'about-hero' },
        h('img', { class: 'about-logo badge-in', src: 'assets/icon.png', alt: '', width: '96', height: '96' }),
        h('div', {}, h('h1', { class: 'page-title', text: 'Material Moodle Deployer' }),
          h('p', { class: 'lead', text: t('about.lead') }))),
      h('section', { class: 'card' },
        h('h2', { class: 'card-title', text: t('about.version') }),
        info ? h('dl', { class: 'facts' },
          h('dt', { text: t('about.app') }), h('dd', { text: info.version }),
          h('dt', { text: 'Electron' }), h('dd', { text: info.electron }),
          h('dt', { text: 'Chromium' }), h('dd', { text: info.chrome }),
          h('dt', { text: 'Node.js' }), h('dd', { text: info.node }),
          h('dt', { text: t('about.platform') }), h('dd', { text: info.platform }),
          h('dt', { text: t('about.data') }), h('dd', { class: 'mono small', text: info.userData })) : h('span', { class: 'spinner sm' })),
      h('section', { class: 'card' },
        h('h2', { class: 'card-title', text: t('about.links') }),
        h('ul', { class: 'link-list' }, links.map(([k, url]) => h('li', {},
          h('button', { type: 'button', class: 'btn btn-text', onclick: () => api.shell.open(url) }, icon('open', 18), h('span', { text: t(k) })),
          h('span', { class: 'muted small mono', text: url }))))),
      h('section', { class: 'card' },
        h('h2', { class: 'card-title', text: t('about.licence') }),
        h('p', { class: 'muted', text: t('about.licenceText') })));
    page.append(body);
    if (!info) api.app.info().then((i) => { info = i; M.appInfo = i; if (M.workspace.isVisible('about')) M.rerender(); });
  };
})();
