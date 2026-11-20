'use strict';
// A tiny in-process SSH server for tests: password auth, exec through /bin/sh and a minimal SFTP subsystem.
const fs = require('node:fs');
const { spawn } = require('node:child_process');
const { Server, utils } = require('ssh2');

const { OPEN_MODE, STATUS_CODE, flagsToString } = utils.sftp;

function attrsOf(st) {
  return { mode: st.mode, uid: st.uid, gid: st.gid, size: st.size, atime: Math.floor(st.atimeMs / 1000), mtime: Math.floor(st.mtimeMs / 1000) };
}

function startServer({ password, env }) {
  const hostKey = utils.generateKeyPairSync('ed25519').private;
  const server = new Server({ hostKeys: [hostKey] }, (client) => {
    client.on('authentication', (ctx) => {
      if (ctx.method === 'password' && ctx.username === 'tester' && ctx.password === password) ctx.accept();
      else ctx.reject(['password']);
    });
    client.on('ready', () => {
      client.on('session', (accept) => {
        const session = accept();
        session.on('exec', (acceptExec, _reject, info) => {
          const stream = acceptExec();
          const child = spawn('/bin/sh', ['-c', info.command], { env });
          child.stdout.pipe(stream, { end: false });
          child.stderr.pipe(stream.stderr, { end: false });
          stream.pipe(child.stdin);
          stream.on('signal', () => child.kill('SIGKILL'));
          stream.on('close', () => child.kill('SIGKILL'));
          child.on('close', (code) => { stream.exit(code ?? 1); stream.end(); });
        });
        session.on('sftp', (acceptSftp) => {
          const sftp = acceptSftp();
          const handles = new Map();
          let next = 0;
          const fdOf = (h) => handles.get(h.readUInt32BE(0));
          sftp.on('OPEN', (id, filename, flags, attrs) => {
            try {
              const fd = fs.openSync(filename, flagsToString(flags), attrs.mode || 0o644);
              const h = Buffer.alloc(4);
              h.writeUInt32BE(next);
              handles.set(next++, fd);
              sftp.handle(id, h);
            } catch (e) {
              sftp.status(id, e.code === 'ENOENT' ? STATUS_CODE.NO_SUCH_FILE : STATUS_CODE.FAILURE);
            }
          });
          sftp.on('WRITE', (id, h, offset, data) => { fs.writeSync(fdOf(h), data, 0, data.length, offset); sftp.status(id, STATUS_CODE.OK); });
          sftp.on('READ', (id, h, offset, length) => {
            const buf = Buffer.alloc(length);
            const n = fs.readSync(fdOf(h), buf, 0, length, offset);
            if (!n) sftp.status(id, STATUS_CODE.EOF);
            else sftp.data(id, buf.subarray(0, n));
          });
          sftp.on('FSTAT', (id, h) => sftp.attrs(id, attrsOf(fs.fstatSync(fdOf(h)))));
          sftp.on('STAT', (id, p) => { try { sftp.attrs(id, attrsOf(fs.statSync(p))); } catch { sftp.status(id, STATUS_CODE.NO_SUCH_FILE); } });
          sftp.on('LSTAT', (id, p) => { try { sftp.attrs(id, attrsOf(fs.lstatSync(p))); } catch { sftp.status(id, STATUS_CODE.NO_SUCH_FILE); } });
          sftp.on('SETSTAT', (id, p, attrs) => { if (attrs.mode !== undefined) fs.chmodSync(p, attrs.mode & 0o7777); sftp.status(id, STATUS_CODE.OK); });
          sftp.on('FSETSTAT', (id, h, attrs) => { if (attrs.mode !== undefined) fs.fchmodSync(fdOf(h), attrs.mode & 0o7777); sftp.status(id, STATUS_CODE.OK); });
          sftp.on('CLOSE', (id, h) => { const k = h.readUInt32BE(0); fs.closeSync(handles.get(k)); handles.delete(k); sftp.status(id, STATUS_CODE.OK); });
        });
      });
    });
    client.on('error', () => {});
  });
  void OPEN_MODE;
  return new Promise((resolve) => server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port })));
}

module.exports = { startServer };
