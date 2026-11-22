'use strict';
// Copies the Docker Compose stack into the app so it ships inside the package.
const fs = require('node:fs');
const path = require('node:path');

const source = path.resolve(__dirname, '..', '..', 'docker', 'docker-compose.yml');
const targetDir = path.resolve(__dirname, '..', 'stack');
fs.mkdirSync(targetDir, { recursive: true });
if (!fs.existsSync(source)) {
  console.error('Missing ' + source + '. Run this from a full checkout of the repository.');
  process.exit(1);
}
fs.copyFileSync(source, path.join(targetDir, 'docker-compose.yml'));
fs.copyFileSync(path.join(path.dirname(source), 'php-settings.json'), path.join(targetDir, 'php-settings.json'));
console.log('Copied docker-compose.yml into stack/');
