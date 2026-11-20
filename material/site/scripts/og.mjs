#!/usr/bin/env node
// Renders the social image (assets/og.png, 1200x630) and the PNG icons from original artwork with Playwright.
// Playwright is not a dependency of the site: install it anywhere and point PLAYWRIGHT_DIR at that folder.
//   PLAYWRIGHT_DIR=/tmp/pw node scripts/og.mjs
import {createRequire} from 'node:module';
import {readFileSync} from 'node:fs';
import {dirname, join, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const site = join(dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(join(resolve(process.env.PLAYWRIGHT_DIR || '.'), 'package.json'));
const {chromium} = require('playwright');

const font = readFileSync(join(site, 'assets/fonts/roboto-flex-latin-wght-normal.woff2')).toString('base64');
const icon = readFileSync(join(site, 'assets/favicon.svg'), 'utf8');
const css = `@font-face{font-family:'Roboto Flex';src:url(data:font/woff2;base64,${font}) format('woff2');font-weight:100 1000}
body{margin:0;font-family:'Roboto Flex',sans-serif}`;

const og = `<!doctype html><html><head><style>${css}
.wrap{width:1200px;height:630px;background:#FFF8F5;color:#201A17;position:relative;overflow:hidden;display:flex}
.blob{position:absolute;border-radius:42% 58% 63% 37%/41% 44% 56% 59%}
.copy{position:relative;padding:72px 0 0 80px;width:620px;display:flex;flex-direction:column;gap:26px}
.pill{align-self:flex-start;padding:10px 20px;border-radius:999px;background:#DCE8B4;color:#161E01;font-weight:650;font-size:24px}
h1{margin:0;font-size:86px;line-height:.96;font-weight:820;letter-spacing:-2.5px}
p{margin:0;font-size:28px;line-height:1.4;color:#51443A}
.brand{display:flex;align-items:center;gap:16px;font-size:32px;font-weight:720}
.brand svg{width:60px;height:60px}
.art{position:absolute;right:-40px;top:70px;width:560px;height:560px;border-radius:48px;background:#E8DCCC;overflow:hidden}
.mock{position:absolute;left:70px;top:70px;width:460px;height:470px;border-radius:26px 0 0 0;background:#FFF8F5;box-shadow:0 30px 60px rgba(0,0,0,.18)}
.bar{height:44px;background:#F6ECE5;display:flex;align-items:center;gap:8px;padding:0 18px}
.bar i{width:12px;height:12px;border-radius:50%;background:#D6C3B6}
.form{padding:40px 44px;display:flex;flex-direction:column;gap:16px}
.t{font-size:34px;font-weight:720}
.pk{height:66px;border-radius:20px;background:#8B5000;color:#fff;display:flex;align-items:center;justify-content:center;gap:12px;font-size:22px;font-weight:700}
.line{height:12px;width:70%;border-radius:6px;background:#EBE0D9}
.in{height:54px;border-radius:14px;border:2px solid #D6C3B6}
.ok{padding:12px 16px;border-radius:14px;background:#B7F1B4;color:#002106;font-weight:650;font-size:20px}
</style></head><body><div class="wrap">
<span class="blob" style="width:420px;height:420px;left:-160px;bottom:-220px;background:#F8DEC6"></span>
<div class="copy"><div class="brand">${icon}Material Moodle</div><span class="pill">Free and open source</span>
<h1>Moodle, redesigned in Material&nbsp;3.</h1><p>Material theme, passkey sign-in and a one-click deployer.</p></div>
<div class="art"><span class="blob" style="width:380px;height:380px;left:-110px;top:-110px;background:#8B5000;opacity:.25"></span>
<div class="mock"><div class="bar"><i></i><i></i><i></i></div><div class="form"><span class="t">Sign in</span>
<div class="pk"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 11a2 2 0 0 0-2 2c0 3-1 5-2 6"/><path d="M14 13c0 3-.5 5.5-1.5 7.5"/><path d="M6.5 16.5C7 15 7 14 7 13a5 5 0 0 1 10 0c0 1 0 2.5-.3 4"/><path d="M4.5 9.5A8.5 8.5 0 0 1 19.5 9.5"/></svg>Sign in with a passkey</div>
<div class="line"></div><div class="in"></div><div class="ok">Welcome back</div></div></div></div>
</div></body></html>`;

const iconPage = (size) => `<!doctype html><html><head><style>body{margin:0;background:transparent}svg{width:${size}px;height:${size}px;display:block}</style></head><body>${icon}</body></html>`;

const browser = await chromium.launch();
const page = await browser.newPage({viewport: {width: 1200, height: 630}});
await page.setContent(og);
await page.evaluate(() => document.fonts.ready);
await page.screenshot({path: join(site, 'assets/og.png')});
for (const [size, name] of [[32, 'favicon-32.png'], [180, 'apple-touch-icon.png']]) {
    await page.setViewportSize({width: size, height: size});
    await page.setContent(iconPage(size));
    await page.screenshot({path: join(site, 'assets', name), omitBackground: size === 32});
}
await browser.close();
console.log('Wrote assets/og.png, assets/favicon-32.png and assets/apple-touch-icon.png');
