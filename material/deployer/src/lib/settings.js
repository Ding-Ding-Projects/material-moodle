'use strict';
// Settings persisted as JSON in the user data folder. Writes are atomic (temp file + rename).
const fs = require('node:fs');
const path = require('node:path');

const DEFAULTS = Object.freeze({
  version: 1,
  theme: 'system',
  seed: '#8B5000',
  motion: 'expressive',
  language: 'en',
  rememberSecrets: false,
  confirmClose: true,
  notifySound: false,
  logWrap: true,
  onboarding: { done: false, step: 0, data: {} },
  lastServer: {},
  profiles: [],
  knownHosts: {},
  lastPage: 'server',
  workspace: null,
});

const ENUMS = {
  theme: ['system', 'light', 'dark'],
  motion: ['expressive', 'calm', 'off'],
  language: ['en', 'yue', 'bi'],
};

const SECRET_FIELDS = ['password', 'passphrase', 'sudoPassword', 'adminPassword'];

function clone(v) {
  return JSON.parse(JSON.stringify(v));
}

/** Validate and merge a patch over current settings. Unknown keys are dropped. */
function applyPatch(current, patch) {
  const next = clone(current);
  for (const [k, v] of Object.entries(patch || {})) {
    if (!(k in DEFAULTS) || k === 'version' || k === 'knownHosts' || k === 'profiles') continue;
    if (ENUMS[k] && !ENUMS[k].includes(v)) continue;
    if (k === 'seed' && !/^#[0-9a-fA-F]{6}$/.test(v)) continue;
    if (typeof DEFAULTS[k] === 'boolean' && typeof v !== 'boolean') continue;
    if (k === 'workspace') {
      // The saved dock layout: an object or null, and bounded so a corrupt value cannot bloat the file.
      if (v === null || (typeof v === 'object' && !Array.isArray(v) && JSON.stringify(v).length < 200000)) next[k] = clone(v);
      continue;
    }
    if (k === 'lastServer') {
      const safe = { ...v };
      for (const f of SECRET_FIELDS) delete safe[f];
      next[k] = safe;
      continue;
    }
    next[k] = clone(v);
  }
  return next;
}

class SettingsStore {
  constructor(dir) {
    this.file = path.join(dir, 'settings.json');
    this.data = clone(DEFAULTS);
    try {
      const raw = JSON.parse(fs.readFileSync(this.file, 'utf8'));
      this.data = { ...clone(DEFAULTS), ...applyPatch(DEFAULTS, raw) };
      if (Array.isArray(raw.profiles)) this.data.profiles = raw.profiles.filter((p) => p && typeof p.id === 'string');
      if (raw.knownHosts && typeof raw.knownHosts === 'object') this.data.knownHosts = raw.knownHosts;
    } catch {
      // First run or unreadable file: start from defaults.
    }
  }

  get() {
    return clone(this.data);
  }

  set(patch) {
    this.data = applyPatch(this.data, patch);
    this.save();
    return this.get();
  }

  replaceInternal(key, value) {
    this.data[key] = value;
    this.save();
  }

  save() {
    fs.mkdirSync(path.dirname(this.file), { recursive: true });
    const tmp = this.file + '.tmp';
    fs.writeFileSync(tmp, JSON.stringify(this.data, null, 2), { mode: 0o600 });
    fs.renameSync(tmp, this.file);
  }
}

module.exports = { SettingsStore, DEFAULTS, applyPatch, SECRET_FIELDS };
