'use strict';
// Helpers that build POSIX shell commands sent to the server over SSH.
// Every value that comes from the user passes through shQuote before it reaches a shell.

/** Quote a value for a POSIX shell. The result is always a single word. */
function shQuote(value) {
  const s = String(value);
  if (s.includes('\0')) throw new Error('Shell arguments cannot contain NUL bytes.');
  if (s === '') return "''";
  if (/^[A-Za-z0-9_@%+=:,./-]+$/.test(s)) return s;
  return "'" + s.replace(/'/g, "'\\''") + "'";
}

/** Join command words, quoting each one. */
function shJoin(words) {
  return words.map(shQuote).join(' ');
}

/**
 * Wrap a script so it runs with root rights.
 *   mode 'root'      the SSH user is already root
 *   mode 'sudo'      sudo reads the password from stdin (-S) with an empty prompt
 *   mode 'sudo-n'    passwordless sudo (fails instead of prompting)
 */
function asRoot(script, mode) {
  if (mode === 'root') return 'sh -c ' + shQuote(script);
  if (mode === 'sudo') return "sudo -S -p '' sh -c " + shQuote(script);
  if (mode === 'sudo-n') return 'sudo -n sh -c ' + shQuote(script);
  throw new Error('Unknown privilege mode: ' + mode);
}

/** Write a file on the server without using stdin: the content travels base64 encoded in the command. */
function writeFileScript(path, content, mode) {
  const b64 = Buffer.from(String(content), 'utf8').toString('base64');
  let script = 'printf %s ' + shQuote(b64) + ' | base64 -d > ' + shQuote(path);
  if (mode) script += ' && chmod ' + shQuote(mode) + ' ' + shQuote(path);
  return script;
}

/**
 * Build a docker compose invocation for the stack directory. Override files are added only when
 * they exist on the server, so the same command works before and after the tunnel is set up.
 */
const OVERRIDE_FILES = ['build.override.yml', 'namedtunnel.override.yml'];
function composeScript(dir, args) {
  const parts = ['cd ' + shQuote(dir), 'set -- -f docker-compose.yml'];
  for (const f of OVERRIDE_FILES) parts.push('if [ -f ' + f + ' ]; then set -- "$@" -f ' + f + '; fi');
  parts.push('docker compose "$@" ' + shJoin(args));
  return parts.join('; ');
}

module.exports = { shQuote, shJoin, asRoot, writeFileScript, composeScript, OVERRIDE_FILES };
