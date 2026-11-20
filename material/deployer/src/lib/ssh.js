'use strict';
// A small promise-based wrapper around ssh2 with trust-on-first-use host key checking.
const crypto = require('node:crypto');
const fs = require('node:fs');
const { Client } = require('ssh2');

class HostKeyError extends Error {
  constructor(message, details) {
    super(message);
    this.code = 'HOST_KEY_CHANGED';
    this.details = details;
  }
}

function fingerprint(key) {
  return 'SHA256:' + crypto.createHash('sha256').update(key).digest('base64').replace(/=+$/, '');
}

class SshSession {
  /**
   * @param {object} knownHosts  { get(id) -> fingerprint|undefined, set(id, fingerprint) }
   */
  constructor(knownHosts) {
    this.knownHosts = knownHosts;
    this.client = null;
    this.fingerprint = null;
    this.sftpHandle = null;
  }

  connect(cfg) {
    const host = String(cfg.host || '').trim();
    const port = Number(cfg.port || 22);
    if (!host) return Promise.reject(new Error('Enter the server address.'));
    if (!(port >= 1 && port <= 65535)) return Promise.reject(new Error('The SSH port must be between 1 and 65535.'));
    if (!cfg.user) return Promise.reject(new Error('Enter the user name for the server.'));
    const options = {
      host, port, username: String(cfg.user).trim(),
      readyTimeout: 25000, keepaliveInterval: 15000, keepaliveCountMax: 4,
    };
    if (cfg.auth === 'password') {
      if (!cfg.password) return Promise.reject(new Error('Enter the SSH password.'));
      options.password = cfg.password;
      options.tryKeyboard = true;
    } else {
      if (!cfg.keyPath) return Promise.reject(new Error('Choose your SSH private key file.'));
      try {
        options.privateKey = fs.readFileSync(cfg.keyPath);
      } catch (e) {
        return Promise.reject(new Error('Could not read the key file: ' + e.message));
      }
      if (cfg.passphrase) options.passphrase = cfg.passphrase;
    }
    const hostId = host + ':' + port;
    let keyError = null;
    options.hostVerifier = (key) => {
      const fp = fingerprint(key);
      this.fingerprint = fp;
      const known = this.knownHosts.get(hostId);
      if (known && known !== fp) {
        keyError = new HostKeyError(
          'The server identity changed. Expected ' + known + ' but the server presented ' + fp + '.',
          { hostId, expected: known, actual: fp });
        return false;
      }
      if (!known) this.knownHosts.set(hostId, fp);
      return true;
    };
    return new Promise((resolve, reject) => {
      const client = new Client();
      let settled = false;
      client.on('keyboard-interactive', (_n, _i, _l, prompts, finish) => finish(prompts.map(() => cfg.password || '')));
      client.on('ready', () => { settled = true; this.client = client; resolve(this); });
      client.on('error', (err) => {
        if (!settled) { settled = true; reject(keyError || err); }
      });
      client.on('close', () => {
        if (this.client === client) { this.client = null; this.sftpHandle = null; }
        if (!settled) { settled = true; reject(keyError || new Error('The connection closed unexpectedly.')); }
      });
      try {
        client.connect(options);
      } catch (e) {
        settled = true;
        reject(e);
      }
    });
  }

  get connected() {
    return !!this.client;
  }

  /**
   * Run a command. stdin (optional) is written then closed; onLine receives each output line.
   * Resolves with { code, stdout, stderr } and never rejects on a non-zero exit code.
   */
  exec(command, { stdin, onLine, signal, timeoutMs } = {}) {
    if (!this.client) return Promise.reject(new Error('Not connected to the server.'));
    return new Promise((resolve, reject) => {
      this.client.exec(command, (err, stream) => {
        if (err) return reject(err);
        let stdout = '';
        let stderr = '';
        const bufs = { out: '', err: '' };
        let timer = null;
        let aborted = false;
        const flush = (kind, chunk, final) => {
          bufs[kind] += chunk;
          const parts = bufs[kind].split(/\r?\n|\r/);
          bufs[kind] = final ? '' : parts.pop();
          if (onLine) for (const p of parts) if (p.trim()) onLine(p, kind);
        };
        const stop = () => {
          aborted = true;
          try { stream.signal('KILL'); } catch { /* not supported by every server */ }
          try { stream.close(); } catch { /* already closed */ }
        };
        if (signal) {
          if (signal.aborted) stop();
          else signal.addEventListener('abort', stop, { once: true });
        }
        if (timeoutMs) timer = setTimeout(stop, timeoutMs);
        stream.on('data', (d) => { const s = d.toString('utf8'); stdout += s; flush('out', s); });
        stream.stderr.on('data', (d) => { const s = d.toString('utf8'); stderr += s; flush('err', s); });
        stream.on('close', (code) => {
          if (timer) clearTimeout(timer);
          if (signal) signal.removeEventListener('abort', stop);
          flush('out', '', true);
          flush('err', '', true);
          if (aborted && signal?.aborted) {
            const e = new Error('Cancelled.');
            e.code = 'CANCELLED';
            return reject(e);
          }
          if (aborted) return reject(new Error('The command timed out.'));
          resolve({ code: code ?? 0, stdout, stderr });
        });
        if (stdin !== undefined && stdin !== null) stream.end(stdin);
        else stream.end();
      });
    });
  }

  sftp() {
    if (this.sftpHandle) return Promise.resolve(this.sftpHandle);
    if (!this.client) return Promise.reject(new Error('Not connected to the server.'));
    return new Promise((resolve, reject) => {
      this.client.sftp((err, sftp) => {
        if (err) return reject(err);
        this.sftpHandle = sftp;
        sftp.on('close', () => { if (this.sftpHandle === sftp) this.sftpHandle = null; });
        resolve(sftp);
      });
    });
  }

  async writeFile(path, content, mode = 0o644) {
    const sftp = await this.sftp();
    await new Promise((resolve, reject) => {
      sftp.writeFile(path, content, { mode }, (err) => (err ? reject(err) : resolve()));
    });
    await new Promise((resolve) => sftp.chmod(path, mode, () => resolve()));
  }

  async readFile(path) {
    const sftp = await this.sftp();
    return new Promise((resolve, reject) => {
      sftp.readFile(path, (err, data) => {
        if (err) return err.code === 2 ? resolve(null) : reject(err);
        resolve(data.toString('utf8'));
      });
    });
  }

  close() {
    if (this.client) {
      try { this.client.end(); } catch { /* ignore */ }
    }
    this.client = null;
    this.sftpHandle = null;
  }
}

module.exports = { SshSession, HostKeyError, fingerprint };
