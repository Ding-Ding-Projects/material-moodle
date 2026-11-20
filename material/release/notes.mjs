#!/usr/bin/env node
// Writes the Markdown release notes for a Material Moodle release.
//
// Usage:
//   node material/release/notes.mjs \
//     --version 1.0.0 --tag material-v1.0.0-42 --codename "Har Gow" \
//     --started-at 2026-11-20T10:00:00Z [--completed-at 2026-11-20T10:31:12Z] \
//     [--assets-dir release-assets] [--line-counts line-counts.md] \
//     [--repository owner/name] [--sha <commit>] [--run-url <url>] [--image ghcr.io/owner/name] \
//     [--output notes.md]
//
// --completed-at defaults to the current time. All times are UTC. A missing or empty line-count file is reported
// as unavailable instead of failing the release.

import { existsSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export function parseArgs(argv) {
  const options = {};
  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    if (!arg.startsWith('--')) {
      throw new Error(`Unexpected argument: ${arg}`);
    }
    const key = arg.slice(2).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
    const value = argv[i + 1];
    if (value === undefined || value.startsWith('--')) {
      throw new Error(`Missing value for ${arg}`);
    }
    options[key] = value;
    i++;
  }
  return options;
}

export function utc(date) {
  return date.toISOString().replace(/\.\d{3}Z$/, 'Z');
}

export function formatDuration(ms) {
  const total = Math.max(0, Math.round(ms / 1000));
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const seconds = total % 60;
  const parts = [];
  if (hours) parts.push(`${hours} h`);
  if (hours || minutes) parts.push(`${minutes} min`);
  parts.push(`${seconds} s`);
  return parts.join(' ');
}

function parseTime(value, name) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    throw new Error(`--${name} is not a valid time: ${value}`);
  }
  return date;
}

function listAssets(dir) {
  if (!dir || !existsSync(dir)) return [];
  const files = [];
  const walk = (current) => {
    for (const entry of readdirSync(current).sort()) {
      const path = join(current, entry);
      if (statSync(path).isDirectory()) walk(path);
      else files.push({ name: entry, size: statSync(path).size });
    }
  };
  walk(dir);
  return files;
}

function formatSize(bytes) {
  if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${bytes} B`;
}

const ASSET_GROUPS = [
  { title: 'Deployer for Windows', test: (n) => /\.exe$/i.test(n) },
  { title: 'Deployer for macOS', test: (n) => /\.dmg$/i.test(n) || (/\.zip$/i.test(n) && /mac|darwin|arm64|x64/i.test(n)) },
  { title: 'Deployer for Linux', test: (n) => /\.(AppImage|deb)$/i.test(n) },
  { title: 'Docker stack bundle', test: (n) => /^material-moodle-stack.*\.zip$/i.test(n) },
  { title: 'Moodle plugins', test: (n) => /^(theme_material|auth_passkey|local_materialtools)\.zip$/i.test(n) },
];

export function groupAssets(assets) {
  const groups = ASSET_GROUPS.map((group) => ({ title: group.title, files: [] }));
  const other = { title: 'Other files', files: [] };
  for (const asset of assets) {
    // Plugin and stack zips are matched before the generic macOS zip rule.
    const order = [4, 3, 0, 1, 2];
    const index = order.find((i) => ASSET_GROUPS[i].test(asset.name));
    (index === undefined ? other : groups[index]).files.push(asset);
  }
  return [...groups, other].filter((group) => group.files.length > 0);
}

export function buildNotes(options) {
  const required = ['version', 'tag', 'codename', 'startedAt'];
  for (const key of required) {
    if (!options[key]) {
      throw new Error(`Missing --${key.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`)}`);
    }
  }
  const started = parseTime(options.startedAt, 'started-at');
  // Whole seconds, so the printed times and the duration agree.
  const completed = options.completedAt ? parseTime(options.completedAt, 'completed-at') : new Date(Math.floor(Date.now() / 1000) * 1000);
  const image = options.image || 'ghcr.io/ding-ding-projects/material-moodle';
  const repository = options.repository || 'Ding-Ding-Projects/material-moodle';
  const assets = listAssets(options.assetsDir);

  let lineCounts = '';
  if (options.lineCounts && existsSync(options.lineCounts)) {
    lineCounts = readFileSync(options.lineCounts, 'utf8').trim();
  }

  const out = [];
  out.push(`# Material Moodle ${options.version} "${options.codename}"`);
  out.push('');
  out.push(`Code name: **${options.codename}**  `);
  out.push(`Tag: \`${options.tag}\`  `);
  if (options.sha) out.push(`Commit: \`${options.sha}\`  `);
  out.push(`Repository: ${repository}`);
  out.push('');

  out.push('## What is included');
  out.push('');
  out.push('- **Material Moodle Deployer**: a desktop app that deploys the Docker stack to an SSH server and can set up a Cloudflare tunnel. Builds for Windows (installer and portable), macOS (DMG and ZIP) and Linux (AppImage and DEB).');
  out.push('- **Docker stack bundle**: `docker-compose.yml`, `.env.example` and the stack README. The compose file runs the published container image with PostgreSQL and cron.');
  out.push('- **Moodle plugins** as installable ZIP files: `theme_material.zip` (Material theme), `auth_passkey.zip` (passkey sign-in) and `local_materialtools.zip` (Material tools).');
  out.push(`- **Container image**: \`${image}\`, published by the image workflow with the \`latest\` and commit SHA tags.`);
  out.push('');

  if (assets.length > 0) {
    out.push('### Attached files');
    out.push('');
    for (const group of groupAssets(assets)) {
      out.push(`**${group.title}**`);
      out.push('');
      for (const file of group.files) out.push(`- \`${file.name}\` (${formatSize(file.size)})`);
      out.push('');
    }
  }

  out.push('## Code signing');
  out.push('');
  out.push('The deployer installers are **not code signed**. Windows SmartScreen and macOS Gatekeeper will warn before the first launch:');
  out.push('');
  out.push('- Windows: choose **More info**, then **Run anyway**.');
  out.push('- macOS: open the app once with Control-click and **Open**, or allow it under System Settings > Privacy & Security.');
  out.push('- Linux: mark the AppImage as executable (`chmod +x`), or install the DEB with `sudo apt install ./<file>.deb`.');
  out.push('');
  out.push('Verify downloads against the checksums GitHub shows for each release file.');
  out.push('');

  out.push('## Install');
  out.push('');
  out.push('### Option 1: Desktop deployer');
  out.push('');
  out.push('1. Download the deployer for your operating system from the files below.');
  out.push('2. Start it and enter the SSH details of the target server and the site settings.');
  out.push('3. The deployer sets up the Docker stack on the server and can configure a Cloudflare tunnel.');
  out.push('');
  out.push('### Option 2: Docker stack');
  out.push('');
  out.push('1. Download and unzip the stack bundle on a host with Docker Engine and the Compose plugin.');
  out.push('2. Copy `.env.example` to `.env` and set at least `MOODLE_ADMIN_PASSWORD`, `MOODLE_DB_PASSWORD` and `MOODLE_WWWROOT`.');
  out.push('3. Run `docker compose up -d`. The first start installs Moodle; `docker compose logs -f moodle` shows `MATERIAL_MOODLE_INSTALLED` when it is ready.');
  out.push('4. Open the address from `MOODLE_WWWROOT` (by default http://localhost:8080) and sign in as the admin user.');
  out.push('');
  out.push('### Option 3: Plugins on an existing Moodle site');
  out.push('');
  out.push('1. Go to Site administration > Plugins > Install plugins and upload each ZIP file, or unzip `theme_material.zip` into `theme/`, `auth_passkey.zip` into `auth/` and `local_materialtools.zip` into `local/` of the Moodle code (the `public/` folder on Moodle 5.1 and later).');
  out.push('2. Visit Site administration > Notifications to complete the upgrade.');
  out.push('3. Select the Material theme under Appearance > Themes and enable passkey sign-in under Plugins > Authentication.');
  out.push('');

  out.push('## Line counts');
  out.push('');
  out.push(lineCounts || '_Line counts were not available for this run._');
  out.push('');

  out.push('## Workflow timing (UTC)');
  out.push('');
  out.push('| Stage | Time |');
  out.push('| --- | --- |');
  out.push(`| Workflow started | ${utc(started)} |`);
  out.push(`| Completed (release publish step) | ${utc(completed)} |`);
  out.push(`| End-to-end duration | ${formatDuration(completed.getTime() - started.getTime())} |`);
  out.push('');
  if (options.runUrl) {
    out.push(`Workflow run: ${options.runUrl}`);
    out.push('');
  }
  return out.join('\n');
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    const options = parseArgs(process.argv.slice(2));
    const notes = buildNotes(options);
    if (options.output) writeFileSync(options.output, notes + '\n');
    else process.stdout.write(notes + '\n');
  } catch (error) {
    console.error(error.message);
    process.exit(1);
  }
}
