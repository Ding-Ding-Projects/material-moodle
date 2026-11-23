'use strict';
// Start-up, global keyboard shortcuts, main-process events and the screenshot hook.
(function () {
  const M = window.MMD;
  const { state, api, t } = M;

  async function boot() {
    state.settings = await api.settings.get();
    M.appInfo = await api.app.info();
    Object.assign(state.server, state.settings.lastServer || {});
    state.logs = await api.logs.get();
    const snap = await api.jobs.snapshot();
    state.jobs = snap.jobs || {};
    state.running = snap.running;
    M.applyLook();
    M.renderChrome();
    state.page = M.pages[state.settings.lastPage] ? state.settings.lastPage : 'server';
    M.renderRail();
    M.workspace.mount();

    api.logs.onLine((l) => {
      state.logs.push(l);
      if (state.logs.length > 5000) state.logs.splice(0, state.logs.length - 5000);
      M.onLogLine(l);
      for (const tail of M.$$('.steps-card .tail')) {
        tail.append(M.h('div', { class: 'tail-line', text: l.text }));
        while (tail.children.length > 4) tail.firstElementChild.remove();
      }
    });
    api.jobs.onUpdate((job) => M.onJobUpdate(job));
    api.jobs.onLoginUrl((url) => {
      state.loginUrl = url;
      if (url) {
        M.toast(t('toast.loginReady'), { kind: 'info', action: () => api.shell.open(url), actionLabel: t('tunnel.openBrowser'), timeout: 12000 });
        if (state.page !== 'tunnel') { state.tunnelMode = 'named'; M.go('tunnel'); }
      }
      M.refreshTunnelBody();
    });
    api.win.onMaximized((v) => { M.maximized = v; M.renderChrome(); });
    api.theme.onSystemChange((dark) => { state.systemDark = dark; M.applyLook(); });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => { state.systemDark = e.matches; M.applyLook(); });
    window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', (e) => { state.reducedMotion = e.matches; M.applyLook(); });

    if (!state.settings.onboarding?.done && !api.app.capture) M.openOnboarding(false);
    document.body.classList.add('ready');
  }

  document.addEventListener('keydown', (e) => {
    const ctrl = e.ctrlKey || e.metaKey;
    const k = e.key.toLowerCase();
    if (ctrl && e.shiftKey && k === 'f') { e.preventDefault(); M.openPalette(String(window.getSelection() || '').trim().slice(0, 200) || undefined); return; }
    if (ctrl && e.shiftKey && k === 'l') { e.preventDefault(); M.toggleTheme(); return; }
    if (ctrl && !e.shiftKey && k === 'r') { e.preventDefault(); api.win.reload(); return; }
    if (e.altKey && e.key === 'ArrowLeft') { e.preventDefault(); M.back(); return; }
    if (ctrl && !e.shiftKey && k === 's' && state.page === 'logs') { e.preventDefault(); M.saveLog(true); return; }
    if (ctrl && !e.shiftKey && /^[1-5]$/.test(e.key)) {
      if (!M.$('#onboarding').hidden || !M.$('#palette').hidden) return;
      e.preventDefault();
      M.go(M.PAGE_ORDER[Number(e.key) - 1]);
      return;
    }
    if (e.key === 'F10' && e.shiftKey) {
      e.preventDefault();
      const el = document.activeElement || document.body;
      const r = el.getBoundingClientRect();
      M.openContextMenu(r.left + 8, r.bottom, el);
    }
  });

  // Screenshot hook used only by the verification run (MMD_CAPTURE_DIR). Returns the wait in ms.
  if (api.app.capture) {
    const demoLogs = [
      ['ssh', 'Connecting to ubuntu@203.0.113.24:22 ...'],
      ['ssh', 'Connected. Server key SHA256:3q2+7wAbCdEfGhIjKlMnOpQrStUvWxYz0123456789'],
      ['ssh', 'Server: Ubuntu 24.04.1 LTS x86_64, privileges: sudo'],
      ['deploy', 'Docker and Docker Compose are already installed.'],
      ['deploy', 'Uploaded docker-compose.yml to /home/ubuntu/material-moodle.'],
      ['docker', ' moodle Pulled'],
      ['docker', ' Container material-moodle-db-1  Healthy'],
      ['moodle', 'Installing Moodle. This takes a few minutes the first time.'],
      ['moodle', 'MATERIAL_MOODLE_INSTALLED'],
      ['deploy', 'Moodle answers on http://127.0.0.1:8080/login/index.php.'],
    ];
    const steps = (name, upTo, failAt) => M.JOB_STEPS[name].map((id, i) => ({ id, status: i === failAt ? 'failed' : i < upTo ? 'done' : i === upTo ? 'running' : 'pending' }));
    const reset = () => {
      M.closePalette(); M.hideError(); M.closeContextMenu(); M.toggleNotifications(false);
      M.$('#celebrate').hidden = true;
      M.$('#onboarding').hidden = true;
      M.$('#toasts').replaceChildren();
      state.unread = 0;
      M.renderBell();
    };
    window.__mmdCapture = async (name) => {
      reset();
      if (!state.logs.length) state.logs = demoLogs.map(([source, text], i) => ({ t: Date.now() - (demoLogs.length - i) * 4000, source, level: 'info', text }));
      Object.assign(state.server, { host: '203.0.113.24', user: 'ubuntu', keyPath: '/home/you/.ssh/id_ed25519', adminEmail: 'admin@example.org', adminPassword: 'Demo-Passw0rd!' });
      if (state.settings.theme !== 'light' && name !== 'server-dark') await M.updateSettings({ theme: 'light' });
      if (state.settings.language !== 'en' && name !== 'yue') await M.updateSettings({ language: 'en' });
      switch (name) {
        case 'onboarding': M.openOnboarding(true); return 900;
        case 'server': state.jobs = {}; state.test = { ok: true, result: { os: 'Ubuntu 24.04.1 LTS', docker: true, dockerVersion: '28.5.1', privilege: 'sudo', deployed: false } }; M.go('server'); return 900;
        case 'server-running': state.jobs = { deploy: { steps: steps('deploy', 3), status: 'running' } }; state.running = 'deploy'; M.go('server'); M.rerender(); return 900;
        case 'tunnel': state.running = null; state.tunnelMode = 'quick'; state.jobs = { quick: { steps: steps('quick', 4), status: 'done', result: { url: 'https://bright-otter-lake.trycloudflare.com' } } }; M.go('tunnel'); M.rerender(); return 900;
        case 'tunnel-named': state.tunnelMode = 'named'; state.namedMode = 'guided'; state.hostname = 'learn.example.org'; state.running = 'named';
          state.loginUrl = 'https://dash.cloudflare.com/argotunnel?aud=&callback=https%3A%2F%2Flogin.cloudflareaccess.org%2FdemoToken';
          state.jobs = { named: { steps: steps('named', 2), status: 'running' } }; M.go('tunnel'); M.rerender(); return 900;
        case 'logs': state.running = null; state.loginUrl = null; M.go('logs'); requestAnimationFrame(() => { const i = M.$('#log-search'); i.value = 'moodle'; i.dispatchEvent(new Event('input')); }); return 900;
        case 'settings': M.go('settings'); return 1000;
        case 'about': M.go('about'); return 900;
        case 'palette': M.go('server'); M.openPalette('the'); return 700;
        case 'context-menu': M.go('logs'); requestAnimationFrame(() => { const line = M.$('.log-line') || M.$('#page-host'); const r = line.getBoundingClientRect(); M.openContextMenu(r.left + 200, r.top + 10, line); }); return 700;
        case 'notifications': M.go('server'); M.toast(t('toast.testOk', { os: 'Ubuntu 24.04.1 LTS' }), { kind: 'ok' }); M.toggleNotifications(true); return 700;
        case 'celebration': M.celebrate('deploy', { url: 'http://203.0.113.24:8080' }); return 650;
        case 'error': M.go('tunnel'); M.showError('named', { step: 7, stepId: 'connected', total: 8, message: 'The tunnel could not start.', detail: 'cloudflared: error parsing tunnel credentials\nexit status 1', fixes: ['tunnelstart', 'relogin', 'quickinstead'] }); return 1500;
        case 'server-dark': await M.updateSettings({ theme: 'dark' }); state.jobs = { deploy: { steps: steps('deploy', 6), status: 'done', startedAt: 0, finishedAt: 241000 } }; M.go('server'); M.rerender(); return 900;
        case 'yue': await M.updateSettings({ language: 'yue', theme: 'light' }); M.go('server'); M.rerender(); return 900;
        default: return 300;
      }
    };
  }

  // Interaction self-test used by the verification run: drives real DOM events and reports results.
  if (api.app.capture) {
    window.__mmdSelfTest = async () => {
      const wait = (ms) => new Promise((r) => setTimeout(r, ms));
      const key = (k, opts = {}) => document.dispatchEvent(new KeyboardEvent('keydown', { key: k, bubbles: true, ...opts }));
      const results = [];
      const check = (name, ok) => results.push([name, !!ok]);
      M.closePalette(); M.hideError(); M.$('#celebrate').hidden = true;
      await M.updateSettings({ language: 'en', theme: 'light' });
      M.go('server');
      key('3', { ctrlKey: true }); await wait(100);
      check('Ctrl+3 opens Logs', state.page === 'logs');
      key('ArrowLeft', { altKey: true }); await wait(100);
      check('Alt+Left goes back', state.page === 'server');
      key('F', { ctrlKey: true, shiftKey: true }); await wait(100);
      check('Ctrl+Shift+F opens the palette', !M.$('#palette').hidden);
      const input = M.$('#palette-input');
      input.value = 'Motion'; input.dispatchEvent(new Event('input')); await wait(50);
      input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
      for (let i = 0; i < 30 && !document.activeElement?.closest('#set-motion'); i++) await wait(50);
      check('Palette jumps to a setting and focuses it', state.page === 'settings' && document.activeElement?.closest('#set-motion'));
      key('F', { ctrlKey: true, shiftKey: true }); await wait(100);
      const pi = M.$('#palette-input');
      pi.value = '(unclosed'; pi.dispatchEvent(new Event('input'));
      M.$$('.toggle-chip', M.$('#palette')).find((b) => b.textContent === t('search.regex')).click(); await wait(50);
      check('Invalid regex shows the invalid state', M.$('#palette .search-bar').classList.contains('invalid'));
      pi.value = ''; pi.dispatchEvent(new Event('input'));
      M.$$('.toggle-chip', M.$('#palette')).find((b) => b.getAttribute('aria-controls') === 'palette-input-blocks').click();
      M.$$('.block-chip', M.$('#palette')).find((b) => b.textContent.includes('Digit')).click(); await wait(50);
      check('Regex block inserts its token', pi.value === '\\d');
      pi.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })); await wait(50);
      check('Escape closes the palette', M.$('#palette').hidden);
      M.go('server'); await wait(50);
      const host = M.$('#srv-host');
      host.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, clientX: 200, clientY: 300 })); await wait(50);
      const labels = M.$$('.ctx-item', M.$('#ctxmenu')).map((b) => b.textContent);
      check('Right-click on a field offers Paste with its shortcut', !M.$('#ctxmenu').hidden && labels.some((l) => l.startsWith('Paste') && l.includes('Ctrl+V')));
      M.closeContextMenu();
      const before = M.isDark();
      key('L', { ctrlKey: true, shiftKey: true }); await wait(150);
      check('Ctrl+Shift+L toggles the theme', M.isDark() !== before && document.documentElement.dataset.scheme === (M.isDark() ? 'dark' : 'light'));
      key('L', { ctrlKey: true, shiftKey: true }); await wait(150);
      M.$('#srv-host').value = ''; state.server.host = '';
      M.$('#srv-deploy').click(); await wait(100);
      check('Deploy with an empty host is blocked and marks the field', M.$('#srv-host').getAttribute('aria-invalid') === 'true');
      await M.updateSettings({ motion: 'off' });
      check('Motion off applies the reduced-motion class', document.documentElement.classList.contains('motion-off'));
      await M.updateSettings({ motion: 'expressive' });
      M.openOnboarding(true); await wait(50);
      M.$('#ob-next').click(); await wait(50);
      M.$('#ob-skip').click(); await wait(50);
      check('Onboarding Next and Skip advance', M.$('#ob-title').textContent === t('ob.serverTitle'));
      M.$('#ob-later').click(); await wait(100);
      const saved = await api.settings.get();
      check('Onboarding saves progress to resume later', saved.onboarding.step === 2 && !saved.onboarding.done && M.$('#onboarding').hidden);
      M.openOnboarding(false); await wait(50);
      check('Onboarding resumes at the saved step', M.$('#ob-title').textContent === t('ob.serverTitle'));
      M.$('#ob-next').click(); await wait(30); M.$('#ob-next').click(); await wait(30); M.$('#ob-next').click(); await wait(150);
      const done = await api.settings.get();
      check('Finishing onboarding opens the Server page', done.onboarding.done && state.page === 'server' && M.$('#onboarding').hidden);
      M.showError('deploy', { step: 1, stepId: 'docker', total: 6, message: 'x', detail: 'y', fixes: ['sudo'] }); await wait(50);
      check('Error screen shows retry from the failed step', M.$('#poke-retry')?.textContent.includes('2'));
      M.hideError();
      const copyCount = M.$$('.code-block').filter((b) => b.querySelector('.code-copy')).length;
      check('Every code block has a copy button', copyCount === M.$$('.code-block').length && copyCount > 0);
      M.go('server');
      const keys = Object.keys(M.appInfo.phpSettings);
      const fields = keys.map(name => document.getElementById('php-' + name.replaceAll('.', '-')));
      check('Each supported PHP runtime setting has a labelled selector', keys.length === 9 &&
        fields.every(el => el?.tagName === 'SELECT' && el.labels?.length));
      const memory = document.getElementById('php-memory_limit');
      memory.value = '768M';
      memory.dispatchEvent(new Event('change'));
      await wait(800);
      const withLimits = await api.settings.get();
      check('The PHP choice persists and an existing stack can be read or updated', withLimits.lastServer.phpLimits.memory_limit === '768M' &&
        M.$('#php-read') && M.$('#php-apply'));
      return results;
    };
  }

  boot().catch((e) => {
    document.body.textContent = 'The app could not start: ' + e.message;
  });
})();
