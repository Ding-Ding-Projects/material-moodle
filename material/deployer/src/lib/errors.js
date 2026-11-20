'use strict';
// Maps a failure to "how to fix" hint ids. The renderer turns each id into localised text (fix.<id>).

const PATTERNS = [
  [/HOST_KEY_CHANGED|identity changed/i, 'hostkey'],
  [/All configured authentication methods failed|authentication failed/i, 'auth'],
  [/Cannot parse privateKey|Encrypted private (OpenSSH )?key detected|bad passphrase|Unsupported key format/i, 'key'],
  [/ECONNREFUSED/i, 'refused'],
  [/ENOTFOUND|EAI_AGAIN|getaddrinfo/i, 'dns'],
  [/Timed out while waiting for handshake|ETIMEDOUT|EHOSTUNREACH|ENETUNREACH/i, 'timeout'],
  [/administrator rights|a password is required|incorrect password|not in the sudoers|sudo:/i, 'sudo'],
  [/permission denied while trying to connect to the Docker daemon|cannot use it/i, 'dockergroup'],
  [/Cannot connect to the Docker daemon|Is the docker daemon running/i, 'daemon'],
  [/port is already allocated|address already in use/i, 'port'],
  [/no space left on device/i, 'disk'],
  [/manifest unknown|pull access denied|denied: |unauthorized: /i, 'pull'],
  [/Could not resolve host|Temporary failure in name resolution|TLS handshake timeout|connection reset/i, 'network'],
  [/not deployed on this server yet/i, 'deployfirst'],
  [/did not answer within 15 minutes|stopped while installing/i, 'slowinstall'],
  [/sign-in did not finish/i, 'login'],
  [/already exists|DNS record/i, 'dnsrecord'],
  [/tunnel token|Unauthorized|invalid token/i, 'token'],
  [/error parsing tunnel credentials|tunnel could not start|did not connect/i, 'tunnelstart'],
  [/full address such as/i, 'hostname'],
  [/quick link within/i, 'quicklink'],
];

const STEP_DEFAULTS = {
  connect: ['checkdetails'],
  docker: ['sudo', 'manualdocker'],
  upload: ['disk', 'retry'],
  image: ['network', 'retry'],
  start: ['port', 'logs'],
  health: ['logs', 'slowinstall'],
  configure: ['deployfirst', 'retry'],
  starttunnel: ['logs', 'retry'],
  quickurl: ['quicklink', 'retry'],
  prepare: ['deployfirst', 'retry'],
  login: ['login', 'retry'],
  create: ['relogin', 'retry'],
  route: ['dnsrecord', 'relogin'],
  config: ['retry'],
  restart: ['logs', 'retry'],
  connected: ['tunnelstart', 'quickinstead'],
};

function classifyError(stepId, text, code) {
  const hits = [];
  const hay = String(text || '') + ' ' + (code || '');
  for (const [re, id] of PATTERNS) if (re.test(hay) && !hits.includes(id)) hits.push(id);
  for (const id of STEP_DEFAULTS[stepId] || ['retry']) if (!hits.includes(id)) hits.push(id);
  return hits.slice(0, 3);
}

module.exports = { classifyError };
