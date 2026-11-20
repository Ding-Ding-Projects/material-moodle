#!/usr/bin/env node
// Serves the site locally and saves a screenshot of every page at desktop (1440x900) and phone (390x844) width
// into screenshots/. Playwright is not a dependency of the site: install it anywhere and set PLAYWRIGHT_DIR.
//   PLAYWRIGHT_DIR=/tmp/pw node scripts/screenshots.mjs [--full]
import {createRequire} from 'node:module';
import {createServer} from 'node:http';
import {readFileSync, existsSync, mkdirSync, readdirSync, statSync} from 'node:fs';
import {dirname, extname, join, resolve, relative} from 'node:path';
import {fileURLToPath} from 'node:url';

const site = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(join(resolve(process.env.PLAYWRIGHT_DIR || '.'), 'package.json'));
const {chromium} = require('playwright');
const full = process.argv.includes('--full');

const TYPES = {'.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.json': 'application/json',
    '.svg': 'image/svg+xml', '.png': 'image/png', '.woff2': 'font/woff2', '.xml': 'application/xml', '.txt': 'text/plain'};
// Serve the folder under /material-moodle/ like GitHub Pages does for a project site.
const server = createServer((req, res) => {
    const path = decodeURIComponent(new URL(req.url, 'http://x').pathname).replace(/^\/material-moodle/, '');
    let file = join(site, path);
    if (existsSync(file) && statSync(file).isDirectory()) {
        file = join(file, 'index.html');
    }
    if (!file.startsWith(site) || !existsSync(file)) {
        res.writeHead(404, {'content-type': TYPES['.html']});
        res.end(readFileSync(join(site, '404.html')));
        return;
    }
    res.writeHead(200, {'content-type': TYPES[extname(file)] || 'application/octet-stream'});
    res.end(readFileSync(file));
}).listen(0);
const port = server.address().port;

const pages = readdirSync(site).filter((f) => f.endsWith('.html'))
    .concat(readdirSync(join(site, 'features')).filter((f) => f.endsWith('.html')).map((f) => `features/${f}`));
const out = join(site, 'screenshots');
mkdirSync(out, {recursive: true});

const browser = await chromium.launch();
const errors = [];
for (const [label, viewport, mobile] of [['desktop', {width: 1440, height: 900}, false], ['phone', {width: 390, height: 844}, true]]) {
    const context = await browser.newContext({viewport, isMobile: mobile, hasTouch: mobile, deviceScaleFactor: mobile ? 2 : 1, reducedMotion: 'reduce'});
    const page = await context.newPage();
    page.on('pageerror', (e) => errors.push(`${label} ${page.url()}: ${e.message}`));
    page.on('console', (m) => m.type() === 'error' && errors.push(`${label} ${page.url()}: ${m.text()}`));
    for (const p of pages) {
        await page.goto(`http://127.0.0.1:${port}/material-moodle/${p}`, {waitUntil: 'networkidle'});
        await page.evaluate(() => document.fonts.ready);
        await page.waitForTimeout(150);
        const name = `${p.replace(/\//g, '-').replace(/\.html$/, '')}-${label}.png`;
        await page.screenshot({path: join(out, name), fullPage: full});
    }
    await context.close();
}
// One dark-mode pair of the home page for the README.
for (const [label, viewport, mobile] of [['desktop-dark', {width: 1440, height: 900}, false], ['phone-dark', {width: 390, height: 844}, true]]) {
    const context = await browser.newContext({viewport, isMobile: mobile, deviceScaleFactor: mobile ? 2 : 1, colorScheme: 'dark', reducedMotion: 'reduce'});
    const page = await context.newPage();
    await page.goto(`http://127.0.0.1:${port}/material-moodle/index.html`, {waitUntil: 'networkidle'});
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({path: join(out, `index-${label}.png`), fullPage: full});
    await context.close();
}
await browser.close();
server.close();
console.log(`Saved ${pages.length * 2 + 2} screenshots to ${relative(process.cwd(), out) || out}.`);
if (errors.length) {
    console.log(`Browser errors:\n  ${errors.join('\n  ')}`);
    process.exit(1);
}
