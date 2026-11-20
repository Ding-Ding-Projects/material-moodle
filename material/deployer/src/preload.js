'use strict';
// The only bridge between the sandboxed renderer and the main process.
const { contextBridge, ipcRenderer } = require('electron');

const invoke = (channel) => (...args) => ipcRenderer.invoke(channel, ...args);
const listen = (channel) => (fn) => {
  const handler = (_e, payload) => fn(payload);
  ipcRenderer.on(channel, handler);
  return () => ipcRenderer.removeListener(channel, handler);
};

contextBridge.exposeInMainWorld('mmd', {
  app: { info: invoke('app:info'), capture: process.argv.includes('--mmd-capture') },
  win: {
    minimize: invoke('win:minimize'), toggleMaximize: invoke('win:toggle-maximize'), close: invoke('win:close'),
    reload: invoke('win:reload'), onMaximized: listen('win:maximized'),
  },
  settings: { get: invoke('settings:get'), set: invoke('settings:set') },
  profiles: {
    save: invoke('profiles:save'), remove: invoke('profiles:remove'), load: invoke('profiles:load'),
    storage: invoke('profiles:storage'),
  },
  knownHosts: { list: invoke('hosts:list'), forget: invoke('hosts:forget') },
  dialog: { pickKey: invoke('dialog:pick-key') },
  ssh: { test: invoke('ssh:test') },
  jobs: {
    run: invoke('jobs:run'), cancel: invoke('jobs:cancel'), snapshot: invoke('jobs:snapshot'),
    onUpdate: listen('jobs:update'), onLoginUrl: listen('jobs:login-url'),
  },
  logs: { get: invoke('logs:get'), clear: invoke('logs:clear'), save: invoke('logs:save'), onLine: listen('logs:line') },
  clipboard: { write: invoke('clipboard:write'), read: invoke('clipboard:read') },
  shell: { open: invoke('shell:open') },
  passwords: { generate: invoke('passwords:generate') },
  theme: { onSystemChange: listen('theme:system') },
});
