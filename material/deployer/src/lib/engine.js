'use strict';
// The deployment engine: runs deploy and tunnel jobs as retryable steps over one SSH session.
const fs = require('node:fs');
const path = require('node:path');
const { EventEmitter } = require('node:events');
const { SshSession } = require('./ssh');
const { shQuote, asRoot, composeScript, writeFileScript } = require('./shell');
const env = require('./envfile');
const tun = require('./tunnel');
const { classifyError } = require('./errors');

const REPO_URL = 'https://github.com/Ding-Ding-Projects/material-moodle';
const HELPER_IMAGE = 'busybox:stable';
const HELPER_DIR = '/mmdwork';
const HEALTH_TIMEOUT_MS = 15 * 60 * 1000;
const MAX_LOG_LINES = 5000;

const JOBS = {
  deploy: ['connect', 'docker', 'upload', 'image', 'start', 'health'],
  quick: ['connect', 'configure', 'starttunnel', 'quickurl'],
  named: ['connect', 'prepare', 'login', 'create', 'route', 'config', 'restart', 'connected'],
  token: ['connect', 'configure', 'restart', 'connected'],
};

const sleep = (ms, signal) => new Promise((resolve, reject) => {
  const t = setTimeout(resolve, ms);
  signal?.addEventListener('abort', () => { clearTimeout(t); const e = new Error('Cancelled.'); e.code = 'CANCELLED'; reject(e); }, { once: true });
});

class StepError extends Error {
  constructor(message, detail) {
    super(message);
    this.detail = detail;
  }
}

class Engine extends EventEmitter {
  constructor({ stackDir, knownHosts }) {
    super();
    this.stackDir = stackDir;
    this.knownHosts = knownHosts;
    this.session = null;
    this.sessionKey = '';
    this.server = null;
    this.ctx = {};
    this.logs = [];
    this.jobs = {};
    this.running = null;
    this.abort = null;
  }

  // ---------- logging ----------
  secrets() {
    const s = this.server || {};
    return [s.password, s.passphrase, s.sudoPassword, s.adminPassword, this.ctx.dbPassword, this.ctx.token]
      .filter((v) => typeof v === 'string' && v.length >= 3);
  }

  redact(text) {
    let out = String(text);
    for (const sec of this.secrets()) out = out.split(sec).join('********');
    return out;
  }

  log(source, text, level = 'info') {
    for (const line of this.redact(text).split(/\r?\n/)) {
      if (!line.trim()) continue;
      const entry = { t: Date.now(), source, level, text: line };
      this.logs.push(entry);
      if (this.logs.length > MAX_LOG_LINES) this.logs.splice(0, this.logs.length - MAX_LOG_LINES);
      this.emit('log', entry);
    }
  }

  clearLogs() {
    this.logs = [];
  }

  // ---------- server and session ----------
  setServer(cfg) {
    const key = JSON.stringify([cfg.host, cfg.port, cfg.user, cfg.auth, cfg.keyPath, cfg.password, cfg.passphrase]);
    if (key !== this.sessionKey && this.session) {
      this.session.close();
      this.session = null;
      this.ctx = {};
    }
    this.sessionKey = key;
    this.server = { ...cfg };
  }

  async ensureConnected(signal) {
    if (this.session?.connected && this.ctx.home) return;
    if (!this.server) throw new StepError('Fill in the server details first.');
    this.session?.close();
    const session = new SshSession(this.knownHosts);
    this.log('ssh', 'Connecting to ' + this.server.user + '@' + this.server.host + ':' + (this.server.port || 22) + ' ...');
    await session.connect(this.server);
    if (signal?.aborted) { session.close(); throw Object.assign(new Error('Cancelled.'), { code: 'CANCELLED' }); }
    this.session = session;
    this.log('ssh', 'Connected. Server key ' + session.fingerprint);
    const probe = await this.sh('printf "%s\\n" "$HOME" "$(id -u)"; (. /etc/os-release 2>/dev/null && printf "%s\\n" "$PRETTY_NAME") || uname -s; uname -m', { quiet: true });
    const [home, uid, osName, arch] = probe.stdout.split('\n').map((s) => s.trim());
    if (!home || !home.startsWith('/')) throw new StepError('Could not read the home folder on the server.', probe.stderr);
    this.ctx.home = home;
    this.ctx.dir = home.replace(/\/+$/, '') + '/material-moodle';
    this.ctx.os = osName || 'Linux';
    this.ctx.arch = arch || '';
    this.ctx.priv = await this.detectPrivilege(uid === '0');
    this.log('ssh', 'Server: ' + this.ctx.os + ' ' + this.ctx.arch + ', privileges: ' + (this.ctx.priv || 'none'));
  }

  async detectPrivilege(isRoot) {
    if (isRoot) return 'root';
    if (this.server.sudoPassword) {
      const r = await this.session.exec("sudo -S -p '' true", { stdin: this.server.sudoPassword + '\n' });
      if (r.code === 0) return 'sudo';
      this.log('ssh', 'The sudo password was not accepted.', 'warn');
    }
    const r = await this.session.exec('sudo -n true');
    return r.code === 0 ? 'sudo-n' : null;
  }

  /** Run a script with sh on the server. Options: root, docker, stream, quiet, signal, timeoutMs. */
  async sh(script, opts = {}) {
    if (!this.session?.connected) throw new StepError('Not connected to the server.');
    let command = 'sh -c ' + shQuote(script);
    let stdin;
    const needRoot = opts.root || (opts.docker && this.ctx.dockerRoot);
    if (needRoot) {
      if (!this.ctx.priv) {
        throw new StepError('This step needs administrator rights on the server.',
          'Connect as root, or enter the sudo password for ' + this.server.user + '.');
      }
      command = asRoot(script, this.ctx.priv);
      if (this.ctx.priv === 'sudo') stdin = this.server.sudoPassword + '\n';
    }
    if (!opts.quiet) this.log('cmd', '$ ' + (opts.label || script));
    const res = await this.session.exec(command, {
      stdin, signal: opts.signal || this.abort?.signal, timeoutMs: opts.timeoutMs,
      onLine: opts.stream ? (line, kind) => { this.log(opts.source || 'server', line, kind === 'err' ? 'detail' : 'info'); opts.onLine?.(line); } : opts.onLine,
    });
    return res;
  }

  async must(script, opts = {}) {
    const r = await this.sh(script, opts);
    if (r.code !== 0) {
      const detail = (r.stderr || r.stdout || '').trim().split('\n').slice(-25).join('\n');
      throw new StepError(opts.failMessage || 'A command failed on the server (exit code ' + r.code + ').', detail);
    }
    return r;
  }

  compose(args, opts = {}) {
    return this.must(composeScript(this.ctx.dir, args), { docker: true, stream: true, source: 'docker', label: 'docker compose ' + args.join(' '), ...opts });
  }

  helper(script, opts = {}) {
    const run = 'docker run --rm -v ' + shQuote(this.ctx.dir + '/cloudflared') + ':' + HELPER_DIR + ' ' + HELPER_IMAGE + ' sh -c ' + shQuote(script);
    return this.sh(run, { docker: true, ...opts });
  }

  cloudflared(args, opts = {}) {
    const name = opts.name ? '--name ' + opts.name + ' ' : '';
    const run = 'docker run --rm ' + name + '-v ' + shQuote(this.ctx.dir + '/cloudflared') + ':' + tun.CONTAINER_DIR + ' ' +
      tun.CLOUDFLARED_IMAGE + ' ' + args.map(shQuote).join(' ');
    return this.sh(run, { docker: true, stream: true, source: 'cloudflared', ...opts });
  }

  async readEnv() {
    const text = await this.session.readFile(this.ctx.dir + '/.env');
    if (text === null) throw new StepError('Moodle is not deployed on this server yet.', 'Run "Deploy with one click" on the Server page first.');
    return text;
  }

  async updateEnv(values) {
    const text = await this.readEnv();
    const next = env.setEnvValues(text, values);
    await this.session.writeFile(this.ctx.dir + '/.env', next, 0o600);
    this.log('deploy', 'Updated .env: ' + Object.keys(values).join(', '));
    return env.envToObject(next);
  }

  // ---------- connection test ----------
  async test(cfg) {
    this.setServer(cfg);
    if (this.session) { this.session.close(); this.session = null; this.ctx = {}; }
    await this.ensureConnected();
    const d = await this.sh('docker --version 2>/dev/null; docker compose version 2>/dev/null', { quiet: true });
    const docker = /Docker version/i.test(d.stdout);
    const compose = /Docker Compose version/i.test(d.stdout);
    const deployed = (await this.session.readFile(this.ctx.dir + '/.env')) !== null;
    const result = {
      os: this.ctx.os, arch: this.ctx.arch, docker, compose, deployed,
      dockerVersion: (/Docker version ([^,\s]+)/.exec(d.stdout) || [])[1] || '',
      privilege: this.ctx.priv, fingerprint: this.session.fingerprint,
    };
    this.log('ssh', 'Connection test passed. Docker ' + (docker ? 'installed' : 'not installed') + '.');
    return result;
  }

  // ---------- job runner ----------
  // Steps whose behaviour differs per job are resolved by name: step_<job>_<id> first, then step_<id>.
  snapshot() {
    return { jobs: this.jobs, running: this.running };
  }

  emitJob(name) {
    this.emit('job', { name, ...this.jobs[name] });
  }

  async run(name, from = 0, params = {}) {
    if (this.running) throw new Error('Another task is still running. Wait for it to finish or cancel it.');
    const ids = JOBS[name];
    if (!ids) throw new Error('Unknown task: ' + name);
    const prev = this.jobs[name];
    const keepParams = from > 0 && prev ? prev.params : {};
    const job = {
      params: { ...keepParams, ...params },
      steps: ids.map((id, i) => ({ id, status: i < from && prev?.steps[i]?.status === 'done' ? 'done' : 'pending' })),
      status: 'running', error: null, result: prev && from > 0 ? prev.result || {} : {}, startedAt: Date.now(),
    };
    for (let i = 0; i < from; i++) job.steps[i].status = 'done';
    this.jobs[name] = job;
    this.running = name;
    this.abort = new AbortController();
    this.emitJob(name);
    this.log(name, from > 0 ? 'Retrying from step ' + (from + 1) + ' of ' + ids.length + '.' : 'Started.');
    let i = from;
    try {
      if (from > 0) {
        await this.ensureConnected(this.abort.signal);
        if (this.ctx.dockerRoot === undefined) await this.detectDockerAccess(false);
      }
      for (; i < ids.length; i++) {
        job.steps[i].status = 'running';
        job.current = i;
        this.emitJob(name);
        const fn = this['step_' + name + '_' + ids[i]] || this['step_' + ids[i]];
        await fn.call(this, job);
        job.steps[i].status = 'done';
        this.emitJob(name);
      }
      job.status = 'done';
      job.finishedAt = Date.now();
      this.log(name, 'Finished successfully.');
    } catch (err) {
      const cancelled = err.code === 'CANCELLED';
      if (job.steps[i]) job.steps[i].status = cancelled ? 'pending' : 'failed';
      job.status = cancelled ? 'cancelled' : 'failed';
      job.error = cancelled ? null : {
        step: i, stepId: ids[i], total: ids.length, message: this.redact(err.message || String(err)),
        detail: this.redact(err.detail || ''),
        fixes: classifyError(ids[i], (err.message || '') + '\n' + (err.detail || ''), err.code),
      };
      this.log(name, cancelled ? 'Cancelled.' : 'Failed at step ' + (i + 1) + ': ' + err.message, cancelled ? 'warn' : 'error');
      if (err.detail) this.log(name, err.detail, 'error');
      if (!this.session?.connected) this.ctx = {};
    } finally {
      this.running = null;
      this.abort = null;
      this.emitJob(name);
    }
    return this.jobs[name];
  }

  cancel() {
    if (this.abort) this.abort.abort();
    if (this.running === 'named' && this.session?.connected) {
      this.sh('docker rm -f mmd-login >/dev/null 2>&1 || true', { docker: true, quiet: true, signal: new AbortController().signal }).catch(() => {});
    }
  }

  // ---------- shared steps ----------
  async step_connect() {
    if (this.session) { this.session.close(); this.session = null; }
    this.ctx = { dockerRoot: this.ctx.dockerRoot };
    await this.ensureConnected(this.abort?.signal);
    await this.detectDockerAccess(false);
  }

  async detectDockerAccess(required = true) {
    const plain = await this.sh('docker info >/dev/null 2>&1', { quiet: true });
    if (plain.code === 0) { this.ctx.dockerRoot = false; return true; }
    if (this.ctx.priv) {
      this.ctx.dockerRoot = true;
      const r = await this.sh('docker info >/dev/null 2>&1', { quiet: true, docker: true });
      if (r.code === 0) return true;
    }
    if (required) {
      throw new StepError('Docker is installed but this account cannot use it.',
        'Add the user to the docker group, connect as root, or enter the sudo password.');
    }
    return false;
  }

  // ---------- deploy steps ----------
  async step_docker() {
    const check = await this.sh('command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1', { quiet: true });
    const hasDocker = (await this.sh('command -v docker >/dev/null 2>&1', { quiet: true })).code === 0;
    if (check.code !== 0) {
      if (!hasDocker) {
        this.log('deploy', 'Docker is not installed. Installing it with the official script from get.docker.com. This takes a few minutes.');
        await this.must('(curl -fsSL https://get.docker.com || wget -qO- https://get.docker.com) > /tmp/get-docker.sh && sh /tmp/get-docker.sh; status=$?; rm -f /tmp/get-docker.sh; exit $status',
          { root: true, stream: true, source: 'docker', label: 'curl -fsSL https://get.docker.com | sh', failMessage: 'Docker could not be installed.', timeoutMs: 20 * 60 * 1000 });
      } else {
        this.log('deploy', 'Docker is installed but the Compose plugin is missing. Installing docker-compose-plugin.');
        await this.must('if command -v apt-get >/dev/null; then apt-get update -qq && apt-get install -y docker-compose-plugin; ' +
          'elif command -v dnf >/dev/null; then dnf install -y docker-compose-plugin; ' +
          'elif command -v yum >/dev/null; then yum install -y docker-compose-plugin; ' +
          'elif command -v zypper >/dev/null; then zypper --non-interactive install docker-compose; ' +
          'elif command -v apk >/dev/null; then apk add docker-cli-compose; else exit 3; fi',
        { root: true, stream: true, source: 'docker', failMessage: 'The Docker Compose plugin could not be installed.' });
      }
    } else {
      this.log('deploy', 'Docker and Docker Compose are already installed.');
    }
    if (!(await this.detectDockerAccess(false))) {
      await this.sh('systemctl enable --now docker >/dev/null 2>&1 || service docker start >/dev/null 2>&1 || true', { root: true, quiet: true });
      await sleep(2000, this.abort?.signal);
      await this.detectDockerAccess(true);
    }
    const v = await this.sh('docker compose version', { docker: true, quiet: true });
    this.log('deploy', v.stdout.trim() || 'Docker Compose is ready.');
  }

  async step_upload(job) {
    const dir = this.ctx.dir;
    await this.must('mkdir -p ' + shQuote(dir) + ' ' + shQuote(dir + '/cloudflared'), { failMessage: 'Could not create ' + dir + '.' });
    const existing = (await this.session.readFile(dir + '/.env')) || '';
    const s = this.server;
    const text = env.buildEnv({
      host: s.host, httpPort: s.httpPort, siteName: s.siteName, adminUser: s.adminUser,
      adminPassword: s.adminPassword, adminEmail: s.adminEmail, seed: s.seed,
      phpLimits: s.phpLimits,
    }, existing);
    const composeText = fs.readFileSync(path.join(this.stackDir, 'docker-compose.yml'), 'utf8');
    await this.session.writeFile(dir + '/docker-compose.yml', composeText, 0o644);
    this.log('deploy', 'Uploaded docker-compose.yml to ' + dir + '.');
    this.ctx.dbPassword = env.envToObject(text).MOODLE_DB_PASSWORD;
    await this.session.writeFile(dir + '/.env', text, 0o600);
    this.log('deploy', existing ? 'Updated .env (kept the existing database password and tunnel settings).' : 'Wrote .env with a new random database password.');
    job.result.wwwroot = env.envToObject(text).MOODLE_WWWROOT;
  }

  async step_image() {
    const pull = await this.sh(composeScript(this.ctx.dir, ['pull', 'moodle']), { docker: true, stream: true, source: 'docker', label: 'docker compose pull moodle', timeoutMs: 30 * 60 * 1000 });
    if (pull.code === 0) {
      await this.sh('rm -f ' + shQuote(this.ctx.dir + '/build.override.yml'), { quiet: true });
      this.log('deploy', 'Pulled the Moodle image.');
    } else {
      this.log('deploy', 'The image could not be pulled. Building it from source instead.', 'warn');
      const git = await this.sh('command -v git >/dev/null 2>&1', { quiet: true });
      if (git.code !== 0) {
        await this.must('if command -v apt-get >/dev/null; then apt-get update -qq && apt-get install -y git; ' +
          'elif command -v dnf >/dev/null; then dnf install -y git; elif command -v yum >/dev/null; then yum install -y git; ' +
          'elif command -v apk >/dev/null; then apk add git; elif command -v zypper >/dev/null; then zypper --non-interactive install git; else exit 3; fi',
        { root: true, stream: true, source: 'deploy', failMessage: 'Git is needed to build from source and could not be installed.' });
      }
      const src = this.ctx.dir + '/src';
      await this.must('if [ -d ' + shQuote(src + '/.git') + ' ]; then git -C ' + shQuote(src) + ' fetch --depth 1 origin && git -C ' + shQuote(src) +
        ' reset --hard FETCH_HEAD; else rm -rf ' + shQuote(src) + ' && git clone --depth 1 ' + REPO_URL + ' ' + shQuote(src) + '; fi',
      { stream: true, source: 'git', failMessage: 'Could not download the Material Moodle source.', timeoutMs: 30 * 60 * 1000 });
      await this.session.writeFile(this.ctx.dir + '/build.override.yml', tun.buildBuildOverride(src), 0o644);
      await this.compose(['build', 'moodle'], { failMessage: 'Building the Moodle image failed.', timeoutMs: 60 * 60 * 1000 });
    }
    await this.compose(['pull', 'db'], { failMessage: 'Could not pull the PostgreSQL image.', timeoutMs: 20 * 60 * 1000 });
  }

  async step_start() {
    await this.compose(['up', '-d', '--no-build'], { failMessage: 'The containers could not be started.', timeoutMs: 15 * 60 * 1000 });
  }

  async step_health(job) {
    const envObj = env.envToObject(await this.readEnv());
    const port = Number(envObj.MOODLE_PORT || 8080);
    const url = 'http://127.0.0.1:' + port + '/login/index.php';
    const tools = await this.sh('command -v curl >/dev/null 2>&1 && echo curl; command -v wget >/dev/null 2>&1 && echo wget', { quiet: true });
    const probe = /curl/.test(tools.stdout) ? 'curl -fsS -o /dev/null --max-time 10 ' + url
      : /wget/.test(tools.stdout) ? 'wget -q -O /dev/null -T 10 ' + url : null;
    this.log('deploy', 'Waiting for Moodle to finish installing. The first start takes a few minutes (limit 15 minutes).');
    const started = Date.now();
    const seen = new Set();
    let installed = false;
    let polls = 0;
    while (Date.now() - started < HEALTH_TIMEOUT_MS) {
      polls++;
      if (probe) {
        const r = await this.sh(probe, { quiet: true });
        if (r.code === 0) { this.log('deploy', 'Moodle answers on ' + url + '.'); break; }
      }
      const logs = await this.sh(composeScript(this.ctx.dir, ['logs', '--no-color', '--tail', '30', 'moodle']), { docker: true, quiet: true });
      for (const line of logs.stdout.split('\n')) {
        const clean = line.replace(/^moodle-\d+\s*\|\s?/, '').trim();
        if (clean && !seen.has(clean)) { seen.add(clean); this.log('moodle', clean); }
        if (line.includes('MATERIAL_MOODLE_INSTALLED')) installed = true;
      }
      if (installed && !probe) break;
      if (polls % 4 === 0) {
        const ps = await this.sh(composeScript(this.ctx.dir, ['ps', '-a', '--format', '{{.Service}} {{.State}}']), { docker: true, quiet: true });
        const state = (/^moodle (\S+)/m.exec(ps.stdout) || [])[1];
        if (state === 'exited' || state === 'dead') {
          throw new StepError('The Moodle container stopped while installing.', logs.stdout.split('\n').slice(-20).join('\n'));
        }
      }
      await sleep(5000, this.abort?.signal);
    }
    if (Date.now() - started >= HEALTH_TIMEOUT_MS) {
      throw new StepError('Moodle did not answer within 15 minutes.', 'Open the Logs page for the container output.');
    }
    job.result.wwwroot = envObj.MOODLE_WWWROOT;
    job.result.url = envObj.MOODLE_WWWROOT === 'auto' ? 'http://' + this.server.host + ':' + port : envObj.MOODLE_WWWROOT;
    job.result.adminUser = envObj.MOODLE_ADMIN_USER;
  }

  // ---------- tunnel steps ----------
  async step_quick_configure() {
    const cur = env.envToObject(await this.readEnv());
    await this.updateEnv({ MOODLE_WWWROOT: 'auto', COMPOSE_PROFILES: env.addProfile(cur.COMPOSE_PROFILES, 'quicktunnel') });
  }

  async step_starttunnel() {
    await this.recreate();
  }

  async recreate() {
    await this.compose(['up', '-d', '--no-build', '--force-recreate', 'moodle', 'cron'], { failMessage: 'Moodle could not be restarted with the new address.' });
    await this.compose(['up', '-d', '--no-build'], { failMessage: 'The tunnel container could not be started.' });
  }

  async step_quickurl(job) {
    const started = Date.now();
    while (Date.now() - started < 120000) {
      const r = await this.sh(composeScript(this.ctx.dir, ['logs', '--no-color', '--tail', '200', 'quicktunnel']), { docker: true, quiet: true });
      const url = tun.parseQuickTunnelUrl(r.stdout + '\n' + r.stderr);
      if (url) {
        job.result.url = url;
        this.log('tunnel', 'Quick link ready: ' + url);
        return;
      }
      await sleep(3000, this.abort?.signal);
    }
    throw new StepError('Cloudflare did not hand out a quick link within two minutes.', 'Check that the server can reach the internet on port 443 and 7844.');
  }

  async step_prepare(job) {
    if (!tun.isValidHostname(job.params.hostname)) throw new StepError('Enter a full address such as learn.example.org.');
    await this.readEnv();
    await this.must('mkdir -p ' + shQuote(this.ctx.dir + '/cloudflared'));
    await this.helper('chown 65532:65532 ' + HELPER_DIR + ' && chmod 700 ' + HELPER_DIR, { stream: true, source: 'tunnel', label: 'prepare the cloudflared folder for uid 65532' })
      .then((r) => { if (r.code !== 0) throw new StepError('Could not prepare the cloudflared folder.', r.stderr || r.stdout); });
  }

  async certExists() {
    const r = await this.helper('test -f ' + HELPER_DIR + '/cert.pem', { quiet: true });
    return r.code === 0;
  }

  async step_login(job) {
    if (await this.certExists() && !job.params.relogin) {
      this.log('tunnel', 'Already signed in to Cloudflare on this server (cert.pem found).');
      job.result.loginSkipped = true;
      return;
    }
    if (job.params.relogin) await this.helper('rm -f ' + HELPER_DIR + '/cert.pem', { quiet: true });
    await this.sh('docker rm -f mmd-login >/dev/null 2>&1 || true', { docker: true, quiet: true });
    let sent = false;
    const r = await this.cloudflared(['tunnel', 'login'], {
      name: 'mmd-login', timeoutMs: 15 * 60 * 1000,
      onLine: (line) => {
        const url = !sent && tun.parseLoginUrl(line);
        if (url) { sent = true; job.result.loginUrl = url; this.emit('login-url', url); }
      },
    });
    this.emit('login-url', null);
    if (!(await this.certExists())) {
      throw new StepError('Cloudflare sign-in did not finish.', (r.stderr || r.stdout).trim().split('\n').slice(-10).join('\n'));
    }
    this.log('tunnel', 'Signed in. The Cloudflare certificate is saved on the server.');
  }

  async step_create(job) {
    const created = await this.cloudflared(['tunnel', 'create', tun.TUNNEL_NAME]);
    let id = tun.parseCreatedTunnelId(created.stdout + created.stderr);
    if (!id) {
      const list = await this.cloudflared(['tunnel', 'list', '--output', 'json', '--name', tun.TUNNEL_NAME], { stream: false });
      id = tun.parseTunnelListId(list.stdout);
      if (!id) throw new StepError('Could not create the tunnel.', (created.stderr || created.stdout).trim());
      this.log('tunnel', 'Using the existing tunnel ' + tun.TUNNEL_NAME + ' (' + id + ').');
    }
    const creds = await this.helper('test -f ' + HELPER_DIR + '/' + id + '.json', { quiet: true });
    if (creds.code !== 0) {
      await this.cloudflared(['tunnel', 'token', '--cred-file', tun.CONTAINER_DIR + '/' + id + '.json', tun.TUNNEL_NAME], { stream: false })
        .then((t) => { if (t.code !== 0) throw new StepError('Could not fetch the tunnel credentials.', t.stderr.trim()); });
    }
    job.result.tunnelId = id;
  }

  async step_route(job) {
    const r = await this.cloudflared(['tunnel', 'route', 'dns', '--overwrite-dns', tun.TUNNEL_NAME, job.params.hostname]);
    if (r.code !== 0) throw new StepError('Could not create the DNS record for ' + job.params.hostname + '.', (r.stderr || r.stdout).trim());
  }

  async step_config(job) {
    const yml = tun.buildConfigYml({ tunnelId: job.result.tunnelId, hostname: job.params.hostname });
    const r = await this.helper(writeFileScript(HELPER_DIR + '/config.yml', yml, '644') + ' && chown 65532:65532 ' + HELPER_DIR + '/config.yml', { label: 'write cloudflared/config.yml' });
    if (r.code !== 0) throw new StepError('Could not write config.yml.', r.stderr);
    await this.session.writeFile(this.ctx.dir + '/namedtunnel.override.yml', tun.buildNamedTunnelOverride(), 0o644);
    this.log('tunnel', 'Wrote cloudflared/config.yml and namedtunnel.override.yml.');
    const cur = env.envToObject(await this.readEnv());
    await this.updateEnv({
      MOODLE_WWWROOT: 'https://' + job.params.hostname,
      MOODLE_TRUSTED_HOSTS: env.trustedHosts(job.params.hostname, (cur.MOODLE_TRUSTED_HOSTS || '').split(',').filter(Boolean)),
    });
    job.result.url = 'https://' + job.params.hostname;
  }

  async step_restart() {
    await this.recreate();
  }

  async step_connected(job) {
    const service = this.running === 'token' ? 'tunnel' : 'namedtunnel';
    const started = Date.now();
    while (Date.now() - started < 90000) {
      const r = await this.sh(composeScript(this.ctx.dir, ['logs', '--no-color', '--tail', '80', service]), { docker: true, quiet: true });
      const text = r.stdout + r.stderr;
      if (/Registered tunnel connection/i.test(text)) { this.log('tunnel', 'The tunnel is connected to Cloudflare.'); return; }
      const bad = /(error parsing tunnel|Unauthorized|invalid token|Provided Tunnel token is not valid)[^\n]*/i.exec(text);
      if (bad) throw new StepError('The tunnel could not start.', bad[0]);
      await sleep(3000, this.abort?.signal);
    }
    throw new StepError('The tunnel did not connect within 90 seconds.', 'Open the Logs page for the cloudflared output.');
  }

  async step_token_configure(job) {
    const token = tun.normaliseTunnelToken(job.params.token);
    if (!token) throw new StepError('That does not look like a Cloudflare tunnel token.', 'Copy the long token from the tunnel install command in the Cloudflare dashboard.');
    this.ctx.token = token;
    const cur = env.envToObject(await this.readEnv());
    const values = { CLOUDFLARE_TUNNEL_TOKEN: token, COMPOSE_PROFILES: env.addProfile(cur.COMPOSE_PROFILES, 'tunnel') };
    if (job.params.hostname) {
      if (!tun.isValidHostname(job.params.hostname)) throw new StepError('Enter a full address such as learn.example.org.');
      values.MOODLE_WWWROOT = 'https://' + job.params.hostname;
      values.MOODLE_TRUSTED_HOSTS = env.trustedHosts(job.params.hostname, (cur.MOODLE_TRUSTED_HOSTS || '').split(',').filter(Boolean));
      job.result.url = values.MOODLE_WWWROOT;
    } else {
      values.MOODLE_WWWROOT = 'auto';
    }
    await this.updateEnv(values);
  }

  dispose() {
    this.session?.close();
  }
}

module.exports = { Engine, JOBS, StepError };
