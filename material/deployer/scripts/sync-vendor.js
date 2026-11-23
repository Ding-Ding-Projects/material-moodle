'use strict';
// Copies the dockview-core build and its stylesheet from node_modules into renderer/vendor/ so the
// window loads them from disk (the Content Security Policy allows only 'self', so no CDN and no
// injected <style> element). The stylesheet is embedded as a string in the package's full bundle;
// this script extracts it, and the style-free bundle is what the window actually runs.
const fs = require('node:fs');
const path = require('node:path');

const pkgDir = path.resolve(__dirname, '..', 'node_modules', 'dockview-core');
const target = path.resolve(__dirname, '..', 'renderer', 'vendor');

function extractCss(bundleSource) {
  const match = /\bs\.textContent = ("(?:[^"\\]|\\.)*");/.exec(bundleSource);
  if (!match) throw new Error('Could not find the stylesheet inside dockview-core.js.');
  const css = JSON.parse(match[1]);
  if (!css.includes('.dv-dockview')) throw new Error('The extracted stylesheet does not look like dockview CSS.');
  return css;
}

function sync() {
  const need = ['dist/dockview-core.js', 'dist/dockview-core.noStyle.js', 'LICENCE.md', 'package.json'];
  for (const f of need) {
    if (!fs.existsSync(path.join(pkgDir, f))) {
      throw new Error('Missing ' + path.join(pkgDir, f) + '. Run npm install in material/deployer first.');
    }
  }
  const version = JSON.parse(fs.readFileSync(path.join(pkgDir, 'package.json'), 'utf8')).version;
  fs.mkdirSync(target, { recursive: true });
  fs.copyFileSync(path.join(pkgDir, 'dist', 'dockview-core.noStyle.js'), path.join(target, 'dockview-core.js'));
  const css = extractCss(fs.readFileSync(path.join(pkgDir, 'dist', 'dockview-core.js'), 'utf8'));
  fs.writeFileSync(path.join(target, 'dockview.css'), '/* dockview-core ' + version + ' (MIT), extracted by scripts/sync-vendor.js */\n' + css);
  fs.copyFileSync(path.join(pkgDir, 'LICENCE.md'), path.join(target, 'LICENSE-dockview.txt'));
  return version;
}

if (require.main === module) {
  try {
    console.log('Copied dockview-core ' + sync() + ' into renderer/vendor/');
  } catch (e) {
    console.error(e.message);
    process.exit(1);
  }
}

module.exports = { extractCss, sync };
