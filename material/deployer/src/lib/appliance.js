'use strict';
// Server image (appliance) support: find the images on the latest release, download them with a checksum check,
// and hand them to VirtualBox, the default app or a card writer. Nothing here writes to disks directly.

const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const https = require('https');
const { execFile } = require('child_process');

const REPO = 'Ding-Ding-Projects/material-moodle';
const SUMS = 'SHA256SUMS-appliance';

/** What an asset is for, from its file name. */
function kindOf(name) {
  if (/\.ova$/i.test(name)) return 'ova';
  if (/\.qcow2$/i.test(name)) return 'qcow2';
  if (/arm64\.img\.xz$/i.test(name)) return 'pi';
  if (/\.img\.xz$/i.test(name)) return 'usb';
  return null;
}

/** Parse "hash  name" lines into a map of name to lower-case hash. */
function parseSums(text) {
  const out = {};
  for (const line of String(text || '').split(/\r?\n/)) {
    const m = line.trim().match(/^([a-f0-9]{64})\s+\*?(.+)$/i);
    if (m) out[path.basename(m[2].trim())] = m[1].toLowerCase();
  }
  return out;
}

/** GET a URL, following redirects, as a stream. */
function get(url, { headers = {}, signal, redirects = 5 } = {}) {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    if (u.protocol !== 'https:' && !(u.hostname === '127.0.0.1' && process.env.MMD_APPLIANCE_TEST)) {
      reject(new Error('Only HTTPS downloads are allowed.'));
      return;
    }
    const lib = u.protocol === 'https:' ? https : require('http');
    const req = lib.get(u, { headers: { 'User-Agent': 'material-moodle-deployer', ...headers }, signal }, (res) => {
      if ([301, 302, 303, 307, 308].includes(res.statusCode) && res.headers.location && redirects > 0) {
        res.resume();
        resolve(get(new URL(res.headers.location, u).toString(), { headers: {}, signal, redirects: redirects - 1 }));
        return;
      }
      if (res.statusCode !== 200) {
        res.resume();
        reject(new Error('The download server answered ' + res.statusCode + '.'));
        return;
      }
      resolve(res);
    });
    req.on('error', reject);
  });
}

async function getText(url, opts) {
  const res = await get(url, opts);
  let body = '';
  res.setEncoding('utf8');
  for await (const chunk of res) body += chunk;
  return body;
}

/**
 * The newest release that carries the server images, with each image's size and checksum.
 * @param {string} [api] Base API URL, for tests.
 */
async function listImages(api = 'https://api.github.com') {
  const releases = JSON.parse(await getText(api + '/repos/' + REPO + '/releases?per_page=15',
    { headers: { Accept: 'application/vnd.github+json' } }));
  for (const rel of releases) {
    const sums = (rel.assets || []).find((a) => a.name === SUMS);
    if (!sums) continue;
    const hashes = parseSums(await getText(sums.browser_download_url));
    const images = (rel.assets || []).filter((a) => kindOf(a.name) && hashes[a.name]).map((a) => ({
      name: a.name, kind: kindOf(a.name), size: a.size, url: a.browser_download_url, sha256: hashes[a.name],
    }));
    if (images.length) return { tag: rel.tag_name, title: rel.name || rel.tag_name, url: rel.html_url, images };
  }
  return null;
}

/**
 * Download one image into a folder, checking its SHA-256 as it arrives. A file that is already there with the
 * right checksum is reused.
 */
async function download(image, dir, { onProgress = () => {}, signal } = {}) {
  if (!/^[a-f0-9]{64}$/.test(image.sha256 || '')) throw new Error('The image has no checksum to check against.');
  if (path.basename(image.name) !== image.name) throw new Error('Unexpected file name.');
  fs.mkdirSync(dir, { recursive: true });
  const target = path.join(dir, image.name);
  if (fs.existsSync(target) && await hashFile(target) === image.sha256) {
    onProgress({ done: image.size, total: image.size, reused: true });
    return { path: target, reused: true };
  }
  const part = target + '.part';
  const res = await get(image.url, { signal });
  const total = Number(res.headers['content-length']) || image.size || 0;
  const hash = crypto.createHash('sha256');
  const out = fs.createWriteStream(part);
  let done = 0;
  let last = 0;
  try {
    for await (const chunk of res) {
      hash.update(chunk);
      done += chunk.length;
      if (!out.write(chunk)) await new Promise((r) => out.once('drain', r));
      if (Date.now() - last > 200) { last = Date.now(); onProgress({ done, total }); }
    }
    await new Promise((resolve, reject) => out.end((e) => (e ? reject(e) : resolve())));
  } catch (e) {
    out.destroy();
    fs.rmSync(part, { force: true });
    throw e;
  }
  const got = hash.digest('hex');
  if (got !== image.sha256) {
    fs.rmSync(part, { force: true });
    const e = new Error('The download does not match its published checksum, so it was deleted. Try again.');
    e.code = 'CHECKSUM';
    throw e;
  }
  fs.renameSync(part, target);
  onProgress({ done, total });
  return { path: target, reused: false };
}

function hashFile(file) {
  return new Promise((resolve, reject) => {
    const h = crypto.createHash('sha256');
    fs.createReadStream(file).on('data', (d) => h.update(d)).on('error', reject).on('end', () => resolve(h.digest('hex')));
  });
}

/** Places a program is usually installed, per platform. */
function candidates(program, platform = process.platform, env = process.env) {
  const exe = platform === 'win32' ? '.exe' : '';
  const dirs = String(env.PATH || '').split(platform === 'win32' ? ';' : ':').filter(Boolean);
  const out = dirs.map((d) => path.join(d, program + exe));
  if (program === 'VBoxManage') {
    if (platform === 'win32') {
      out.push(path.join(env.VBOX_MSI_INSTALL_PATH || env.VBOX_INSTALL_PATH || 'C:\\Program Files\\Oracle\\VirtualBox', 'VBoxManage.exe'));
    } else if (platform === 'darwin') {
      out.push('/Applications/VirtualBox.app/Contents/MacOS/VBoxManage', '/usr/local/bin/VBoxManage');
    } else {
      out.push('/usr/bin/VBoxManage', '/usr/lib/virtualbox/VBoxManage');
    }
  }
  return out;
}

/** The first existing program from the candidates, or null. */
function findProgram(program, platform = process.platform, env = process.env, exists = fs.existsSync) {
  return candidates(program, platform, env).find((p) => { try { return exists(p); } catch { return false; } }) || null;
}

/** Card writer apps that can open an .img.xz file. */
function findWriters(platform = process.platform, env = process.env, exists = fs.existsSync) {
  const home = env.HOME || env.USERPROFILE || os.homedir();
  const list = platform === 'win32' ? [
    ['Raspberry Pi Imager', path.join(env.ProgramFiles || 'C:\\Program Files', 'Raspberry Pi Imager', 'rpi-imager.exe')],
    ['Raspberry Pi Imager', path.join(env['ProgramFiles(x86)'] || 'C:\\Program Files (x86)', 'Raspberry Pi Imager', 'rpi-imager.exe')],
    ['balenaEtcher', path.join(env.LOCALAPPDATA || path.join(home, 'AppData', 'Local'), 'Programs', 'balena-etcher', 'balenaEtcher.exe')],
  ] : platform === 'darwin' ? [
    ['Raspberry Pi Imager', '/Applications/Raspberry Pi Imager.app'],
    ['balenaEtcher', '/Applications/balenaEtcher.app'],
  ] : [
    ['Raspberry Pi Imager', '/usr/bin/rpi-imager'],
    ['balenaEtcher', '/usr/bin/balena-etcher'],
    ['balenaEtcher', '/opt/balenaEtcher/balena-etcher'],
  ];
  const seen = new Set();
  return list.filter(([name, p]) => {
    if (seen.has(name) || !exists(p)) return false;
    seen.add(name);
    return true;
  }).map(([name, p]) => ({ name, path: p }));
}

function run(file, args, { timeout = 10 * 60 * 1000 } = {}) {
  return new Promise((resolve, reject) => {
    execFile(file, args, { timeout, windowsHide: true, maxBuffer: 16 * 1024 * 1024 }, (err, stdout, stderr) => {
      if (err) { err.message = (stderr || err.message).trim(); reject(err); return; }
      resolve(stdout);
    });
  });
}

/** Pick the bridged adapter that is up, from `VBoxManage list bridgedifs` output. */
function pickBridge(text) {
  let current = null;
  let fallback = null;
  for (const line of String(text).split(/\r?\n/)) {
    const name = line.match(/^Name:\s+(.+)$/);
    if (name) { current = name[1].trim(); if (!fallback) fallback = current; continue; }
    if (current && /^Status:\s+Up/i.test(line)) return current;
  }
  return fallback;
}

/**
 * Import the .ova into VirtualBox with a free name, bridge it to the active network adapter and start it.
 * @returns {Promise<{name: string, bridge: string|null}>}
 */
async function importVirtualBox(ova, { vbox = findProgram('VBoxManage'), runner = run } = {}) {
  if (!vbox) throw new Error('VirtualBox was not found on this computer.');
  const existing = await runner(vbox, ['list', 'vms']);
  let name = 'Material Moodle';
  for (let n = 2; existing.includes('"' + name + '"'); n++) name = 'Material Moodle ' + n;
  await runner(vbox, ['import', ova, '--vsys', '0', '--vmname', name]);
  const bridge = pickBridge(await runner(vbox, ['list', 'bridgedifs']));
  if (bridge) await runner(vbox, ['modifyvm', name, '--nic1', 'bridged', '--bridgeadapter1', bridge]);
  await runner(vbox, ['startvm', name, '--type', 'gui']);
  return { name, bridge };
}

module.exports = { REPO, SUMS, kindOf, parseSums, listImages, download, hashFile, candidates, findProgram, findWriters,
  pickBridge, importVirtualBox };
