'use strict';
// Main process: window, IPC, settings, secure storage and the deployment engine.
const { app, BrowserWindow, ipcMain, dialog, shell, clipboard, safeStorage, nativeTheme, session, Menu } = require('electron');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const { SettingsStore, SECRET_FIELDS } = require('./lib/settings');
const { Engine } = require('./lib/engine');
const { generateAdminPassword } = require('./lib/envfile');
const phpSettings = require('../stack/php-settings.json');
const { isSafeLoginUrl } = require('./lib/tunnel');

const CAPTURE_DIR = process.env.MMD_CAPTURE_DIR ? path.resolve(process.env.MMD_CAPTURE_DIR) : null;
if (CAPTURE_DIR) {
  // Screenshot runs use a throwaway profile so they never touch real settings.
  app.setPath('userData', fs.mkdtempSync(path.join(os.tmpdir(), 'mmd-capture-')));
}

let win = null;
let store = null;
let engine = null;
let quitting = false;
const rendererErrors = [];

function send(channel, payload) {
  if (win && !win.isDestroyed()) win.webContents.send(channel, payload);
}

function createWindow() {
  const s = store.get();
  nativeTheme.themeSource = s.theme;
  win = new BrowserWindow({
    width: 1280, height: 840, minWidth: 860, minHeight: 600,
    frame: false, show: false, title: 'Material Moodle Deployer',
    backgroundColor: nativeTheme.shouldUseDarkColors ? '#18120D' : '#FFF8F5',
    icon: path.join(__dirname, '..', 'build', 'icon.png'),
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true, nodeIntegration: false, sandbox: true, spellcheck: false,
      webviewTag: false, navigateOnDragDrop: false,
      additionalArguments: CAPTURE_DIR ? ['--mmd-capture'] : [],
    },
  });
  win.loadFile(path.join(__dirname, '..', 'renderer', 'index.html'));
  win.once('ready-to-show', () => { if (!CAPTURE_DIR) win.show(); });
  win.on('maximize', () => send('win:maximized', true));
  win.on('unmaximize', () => send('win:maximized', false));
  win.webContents.on('will-navigate', (e) => e.preventDefault());
  win.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
  win.on('close', (e) => {
    if (quitting || !engine.running || !store.get().confirmClose) return;
    const choice = dialog.showMessageBoxSync(win, {
      type: 'warning', buttons: ['Keep running', 'Quit anyway'], defaultId: 0, cancelId: 0,
      message: 'A task is still running on the server.',
      detail: 'Quitting stops it. You can retry it later from the step where it stopped.',
    });
    if (choice === 0) e.preventDefault();
    else engine.cancel();
  });
  if (CAPTURE_DIR) {
    win.webContents.on('console-message', (e) => {
      const level = e.level ?? '';
      if (level === 'error' || level === 3) rendererErrors.push(e.message);
    });
  }
  if (CAPTURE_DIR) win.webContents.once('did-finish-load', () => runCapture().catch((e) => { console.error(e); app.exit(1); }));
}

// ---------- IPC ----------
function trusted(event) {
  const url = event.senderFrame?.url || '';
  return url.startsWith('file://') && win && event.sender === win.webContents;
}

function handle(channel, fn) {
  ipcMain.handle(channel, async (event, ...args) => {
    if (!trusted(event)) throw new Error('Blocked an IPC call from an untrusted frame.');
    return fn(...args);
  });
}

function storageStatus() {
  const available = safeStorage.isEncryptionAvailable();
  let backend = 'os';
  if (process.platform === 'linux') {
    try { backend = safeStorage.getSelectedStorageBackend(); } catch { backend = 'unknown'; }
  }
  return { available, backend, strong: available && backend !== 'basic_text' && backend !== 'unknown' };
}

function serverTarget(cfg) {
  if (!cfg || typeof cfg !== 'object') throw new Error('Missing server details.');
  const pick = ['host', 'port', 'user', 'auth', 'keyPath', 'passphrase', 'password', 'sudoPassword',
    'siteName', 'adminUser', 'adminEmail', 'adminPassword', 'seed', 'httpPort'];
  const out = {};
  for (const k of pick) if (cfg[k] !== undefined && cfg[k] !== null) out[k] = String(cfg[k]);
  out.phpLimits = {};
  for (const name of Object.keys(phpSettings)) {
    if (typeof cfg.phpLimits?.[name] === 'string') out.phpLimits[name] = cfg.phpLimits[name];
  }
  out.host = (out.host || '').trim();
  return out;
}

function registerIpc() {
  handle('app:info', () => ({
    phpSettings,
    version: app.getVersion(), electron: process.versions.electron, chrome: process.versions.chrome,
    node: process.versions.node, platform: process.platform + ' ' + process.arch,
    userData: app.getPath('userData'),
  }));
  handle('win:minimize', () => win.minimize());
  handle('win:toggle-maximize', () => { if (win.isMaximized()) win.unmaximize(); else win.maximize(); return win.isMaximized(); });
  handle('win:close', () => win.close());
  handle('win:reload', () => win.webContents.reload());

  handle('settings:get', () => {
    const s = store.get();
    delete s.knownHosts;
    s.profiles = s.profiles.map(({ secrets, ...p }) => ({ ...p, hasSecrets: !!secrets }));
    return s;
  });
  handle('settings:set', (patch) => {
    const s = store.set(patch);
    if (patch && 'theme' in patch) nativeTheme.themeSource = s.theme;
    if (patch && patch.rememberSecrets === false) {
      store.replaceInternal('profiles', s.profiles.map(({ secrets, ...p }) => p));
    }
    return true;
  });

  handle('profiles:storage', () => storageStatus());
  handle('profiles:save', (profile) => {
    const s = store.get();
    const clean = {};
    for (const [k, v] of Object.entries(profile || {})) if (!SECRET_FIELDS.includes(k) && typeof v !== 'object') clean[k] = String(v);
    clean.phpLimits = serverTarget(profile).phpLimits;
    clean.id = /^[a-f0-9]{16}$/.test(clean.id || '') ? clean.id : crypto.randomBytes(8).toString('hex');
    clean.name = (clean.name || clean.host || 'Server').slice(0, 80);
    const secrets = {};
    for (const f of SECRET_FIELDS) if (profile?.[f]) secrets[f] = String(profile[f]);
    let stored = false;
    if (s.rememberSecrets && Object.keys(secrets).length && storageStatus().strong) {
      clean.secrets = safeStorage.encryptString(JSON.stringify(secrets)).toString('base64');
      stored = true;
    }
    const list = s.profiles.filter((p) => p.id !== clean.id);
    list.unshift(clean);
    store.replaceInternal('profiles', list.slice(0, 50));
    return { id: clean.id, secretsStored: stored };
  });
  handle('profiles:remove', (id) => {
    store.replaceInternal('profiles', store.get().profiles.filter((p) => p.id !== id));
    return true;
  });
  handle('profiles:load', (id) => {
    const p = store.get().profiles.find((x) => x.id === id);
    if (!p) throw new Error('That profile no longer exists.');
    const { secrets, ...rest } = p;
    if (secrets && storageStatus().available) {
      try { Object.assign(rest, JSON.parse(safeStorage.decryptString(Buffer.from(secrets, 'base64')))); } catch { /* unreadable on this machine */ }
    }
    return rest;
  });

  handle('hosts:list', () => store.get().knownHosts);
  handle('hosts:forget', (id) => {
    const k = { ...store.get().knownHosts };
    delete k[id];
    store.replaceInternal('knownHosts', k);
    return true;
  });

  handle('dialog:pick-key', async () => {
    const r = await dialog.showOpenDialog(win, {
      title: 'Choose your SSH private key', defaultPath: path.join(os.homedir(), '.ssh'),
      properties: ['openFile', 'showHiddenFiles'],
    });
    return r.canceled ? null : r.filePaths[0];
  });

  handle('ssh:test', (cfg) => engine.test(serverTarget(cfg)));
  handle('jobs:run', (name, from, params, cfg) => {
    if (cfg) engine.setServer(serverTarget(cfg));
    const p = {};
    for (const [k, v] of Object.entries(params || {})) p[k] = typeof v === 'boolean' ? v : String(v);
    return engine.run(String(name), Math.max(0, Number(from) || 0), p);
  });
  handle('jobs:cancel', () => engine.cancel());
  handle('jobs:snapshot', () => engine.snapshot());

  handle('logs:get', () => engine.logs);
  handle('logs:clear', () => engine.clearLogs());
  handle('logs:save', async (text) => {
    const content = typeof text === 'string' ? text : engine.logs.map((l) =>
      new Date(l.t).toISOString() + ' [' + l.source + '] ' + (l.level !== 'info' ? l.level.toUpperCase() + ' ' : '') + l.text).join('\n') + '\n';
    const r = await dialog.showSaveDialog(win, {
      title: 'Save log', defaultPath: path.join(app.getPath('documents'), 'material-moodle-deployer-' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + '.log'),
      filters: [{ name: 'Log files', extensions: ['log', 'txt'] }],
    });
    if (r.canceled || !r.filePath) return null;
    fs.writeFileSync(r.filePath, content);
    return r.filePath;
  });

  handle('clipboard:write', (text) => { clipboard.writeText(String(text ?? '')); return true; });
  handle('clipboard:read', () => clipboard.readText());

  handle('shell:open', (url) => {
    let u;
    try { u = new URL(String(url)); } catch { throw new Error('That is not a valid address.'); }
    const serverHost = engine.server?.host;
    const ok = u.protocol === 'https:' || (u.protocol === 'http:' && serverHost && u.hostname === serverHost);
    if (!ok) throw new Error('Only https addresses (or your own server) can be opened.');
    if (u.hostname === 'dash.cloudflare.com' && !isSafeLoginUrl(u.href)) throw new Error('Unexpected Cloudflare address.');
    return shell.openExternal(u.href);
  });

  handle('passwords:generate', () => generateAdminPassword());

  nativeTheme.on('updated', () => send('theme:system', nativeTheme.shouldUseDarkColors));
}

// ---------- screenshots (verification only) ----------
async function runCapture() {
  fs.mkdirSync(CAPTURE_DIR, { recursive: true });
  const shots = (process.env.MMD_CAPTURE_SHOTS || 'onboarding,server,server-running,tunnel,tunnel-named,logs,settings,about,palette,context-menu,notifications,celebration,error,server-dark,yue')
    .split(',');
  win.setSize(1280, 840);
  win.showInactive();
  await new Promise((r) => setTimeout(r, 1200));
  for (const name of shots) {
    const wait = await win.webContents.executeJavaScript('window.__mmdCapture && window.__mmdCapture(' + JSON.stringify(name) + ')');
    await new Promise((r) => setTimeout(r, Number(wait) || 900));
    const img = await win.webContents.capturePage();
    fs.writeFileSync(path.join(CAPTURE_DIR, name + '.png'), img.toPNG());
    console.log('captured ' + name);
  }
  const results = await win.webContents.executeJavaScript('window.__mmdSelfTest ? window.__mmdSelfTest() : []');
  let failed = 0;
  for (const [name, ok] of results) {
    console.log((ok ? 'PASS ' : 'FAIL ') + name);
    if (!ok) failed++;
  }
  console.log('self-test: ' + (results.length - failed) + '/' + results.length + ' passed; renderer errors: ' + rendererErrors.length);
  for (const e of rendererErrors) console.log('renderer error: ' + e);
  quitting = true;
  app.exit(failed || rendererErrors.length ? 1 : 0);
}

app.whenReady().then(() => {
  Menu.setApplicationMenu(null);
  store = new SettingsStore(app.getPath('userData'));
  engine = new Engine({
    stackDir: path.join(__dirname, '..', 'stack'),
    knownHosts: {
      get: (id) => store.get().knownHosts[id],
      set: (id, fp) => store.replaceInternal('knownHosts', { ...store.get().knownHosts, [id]: fp }),
    },
  });
  engine.on('log', (entry) => send('logs:line', entry));
  engine.on('job', (job) => send('jobs:update', job));
  engine.on('login-url', (url) => send('jobs:login-url', url));
  session.defaultSession.setPermissionRequestHandler((_wc, _perm, cb) => cb(false));
  session.defaultSession.setPermissionCheckHandler(() => false);
  registerIpc();
  createWindow();
});

app.on('before-quit', () => { quitting = true; engine?.dispose(); });
app.on('window-all-closed', () => app.quit());
app.on('web-contents-created', (_e, wc) => {
  wc.on('will-attach-webview', (e) => e.preventDefault());
});
