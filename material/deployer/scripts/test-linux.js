'use strict';
// Runs the whole deployer test suite inside a Linux container, as an ordinary user, so the end-to-end engine
// tests (which stand in for a Linux server) also run on Windows and macOS. Needs Docker.
//   npm run test:linux
const { spawnSync } = require('node:child_process');
const path = require('node:path');

const material = path.resolve(__dirname, '..', '..');
const script = [
  // Copy without the Windows node_modules and the built installers, which are large and not needed.
  'mkdir -p /tmp/m',
  'tar -C /src --exclude=./deployer/node_modules --exclude=./deployer/dist -cf - . | tar -C /tmp/m -xf -',
  'cd /tmp/m/deployer',
  'npm ci --ignore-scripts --no-audit --no-fund >/dev/null',
  'node --test "test/*.test.js"',
].join(' && ');

const r = spawnSync('docker', ['run', '--rm', '-u', 'node', '-v', material + ':/src:ro', 'node:22-bookworm', 'sh', '-c', script],
  { stdio: 'inherit', env: { ...process.env, MSYS_NO_PATHCONV: '1' } });
if (r.error) {
  console.error('Could not start Docker: ' + r.error.message);
  process.exit(1);
}
process.exit(r.status === null ? 1 : r.status);
