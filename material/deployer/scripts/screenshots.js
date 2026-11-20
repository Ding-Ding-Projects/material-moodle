'use strict';
// Launches the app with a throwaway profile, captures every page to screenshots/*.png, then quits.
// On Linux without a display, run it under xvfb-run: xvfb-run -a npm run screenshots
const { spawnSync } = require('node:child_process');
const path = require('node:path');

const electron = require('electron');
const root = path.join(__dirname, '..');
const args = ['.'];
if (process.platform === 'linux' && process.getuid && process.getuid() === 0) args.push('--no-sandbox');
const r = spawnSync(electron, args, {
  cwd: root, stdio: 'inherit',
  env: { ...process.env, MMD_CAPTURE_DIR: path.join(root, 'screenshots') },
});
process.exit(r.status ?? 1);
