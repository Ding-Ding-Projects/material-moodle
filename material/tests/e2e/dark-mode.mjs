// Checks dark mode text contrast (at least 3:1) on the main pages on a running test site.
//   MOODLE_URL=http://localhost:8080 MOODLE_USER=admin MOODLE_PASSWORD=... node material/tests/e2e/dark-mode.mjs
// Uses a disposable administrator account on a test site; screenshots go to SHOTS_DIR when it is set.
import {chromium} from 'playwright';
import fs from 'node:fs';
const env = {MOODLE_ADMIN_USER: process.env.MOODLE_USER, MOODLE_ADMIN_PASSWORD: process.env.MOODLE_PASSWORD};
const base = process.env.MOODLE_URL || 'http://localhost:8080', out = process.env.SHOTS_DIR || '';
const b = await chromium.launch();
const ctx = await b.newContext({viewport:{width:1440,height:900}, colorScheme:'dark'});
const p = await ctx.newPage();
await p.goto(base+'/login/index.php'); await p.waitForLoadState('networkidle');
await p.fill('#username', env.MOODLE_ADMIN_USER); await p.fill('#password', env.MOODLE_ADMIN_PASSWORD);
await Promise.all([p.waitForNavigation(), p.click('#loginbtn')]);
const pages = {dashboard:'/my/', mycourses:'/my/courses.php', course:'/course/view.php?id=2', calendar:'/calendar/view.php?view=month', grades:'/grade/report/overview/index.php', messages:'/message/index.php', profile:'/user/profile.php', preferences:'/user/preferences.php', admin:'/admin/search.php', features:'/theme/material/features.php', assignment:'/course/view.php?id=2'};
const report = {};
for (const [name, path] of Object.entries(pages)) {
  await p.goto(base+path); await p.waitForLoadState('networkidle');
  await p.evaluate(() => document.documentElement.setAttribute('data-bs-theme','dark'));
  await p.waitForTimeout(500);
  report[name] = await p.evaluate(() => {
    const rgb = s => { const m = s.match(/[\d.]+/g); return m ? m.map(Number) : null; };
    const lum = ([r,g,b]) => { const f = c => (c/=255) <= .03928 ? c/12.92 : ((c+.055)/1.055)**2.4; return .2126*f(r)+.7152*f(g)+.0722*f(b); };
    const bgOf = el => { for (let e = el; e; e = e.parentElement) { const c = rgb(getComputedStyle(e).backgroundColor); if (c && (c[3] === undefined || c[3] > .5)) return c; } return [255,255,255]; };
    const bad = [];
    for (const el of document.querySelectorAll('body *')) {
      if (![...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim())) continue;
      const r = el.getBoundingClientRect(); if (!r.width || !r.height) continue;
      const cs = getComputedStyle(el); if (cs.visibility === 'hidden' || +cs.opacity === 0) continue;
      if (el.closest('.sr-only,.visually-hidden,[aria-hidden="true"]')) continue;
      const fg = rgb(cs.color), bg = bgOf(el); const a = lum(fg), c = lum(bg);
      const ratio = (Math.max(a,c)+.05)/(Math.min(a,c)+.05);
      if (ratio < 3) bad.push(`${ratio.toFixed(2)} ${el.tagName.toLowerCase()}.${[...el.classList].slice(0,2).join('.')} "${el.textContent.trim().slice(0,30)}" fg=${cs.color} bg=rgb(${bg.slice(0,3)})`);
    }
    return {bodyBg: getComputedStyle(document.body).backgroundColor, count: bad.length, bad: bad.slice(0,12)};
  });
  if (out) await p.screenshot({path: `${out}/dark-${name}.png`});
}
console.log(JSON.stringify(report, null, 1));
const failing = Object.entries(report).filter(([, r]) => r.count);
if (failing.length) { console.error('Text below 3:1 contrast in dark mode on: ' + failing.map(([n]) => n).join(', ')); process.exit(1); }
console.log('dark mode contrast checks passed');
await b.close();
