// Checks the not found, error, offline and back-online states in light and dark, at 1440 and 390 pixels on a running test site.
//   MOODLE_URL=http://localhost:8080 MOODLE_USER=admin MOODLE_PASSWORD=... node material/tests/e2e/states.mjs
// Uses a disposable administrator account on a test site; screenshots go to SHOTS_DIR when it is set.
import {chromium} from 'playwright'; import fs from 'node:fs'; import assert from 'node:assert/strict';
const env = {MOODLE_ADMIN_USER: process.env.MOODLE_USER, MOODLE_ADMIN_PASSWORD: process.env.MOODLE_PASSWORD};
const out = process.env.SHOTS_DIR || '', base = process.env.MOODLE_URL || 'http://localhost:8080';
const b = await chromium.launch();
for (const [w,h] of [[1440,900],[390,844]]) {
 const ctx = await b.newContext({viewport:{width:w,height:h}}); const p = await ctx.newPage();
 const errs=[]; p.on('pageerror', e=>errs.push(String(e)));
 await p.goto(base+'/login/index.php'); await p.waitForLoadState('networkidle');
 await p.fill('#username', env.MOODLE_ADMIN_USER); await p.fill('#password', env.MOODLE_ADMIN_PASSWORD);
 await Promise.all([p.waitForNavigation(), p.click('#loginbtn')]);
 for (const dark of [false,true]) {
  const sfx = `${w}${dark?'-dark':''}`;
  const setDark = () => p.evaluate(d => document.documentElement.setAttribute('data-bs-theme', d?'dark':'light'), dark);
  await p.goto(base+'/nope.php'); await p.waitForLoadState('networkidle'); await setDark(); await p.waitForTimeout(600);
  const nf = await p.evaluate(() => { const m=document.querySelector('#page-error [role="main"]'); const c=getComputedStyle(m); return {radius:c.borderTopLeftRadius, band:getComputedStyle(m,'::before').height, overflow: document.documentElement.scrollWidth>innerWidth}; });
  console.log(sfx,'not found',JSON.stringify(nf)); assert.equal(nf.radius,'36px'); assert.equal(nf.band,'124px'); assert.equal(nf.overflow,false);
  if (out) await p.screenshot({path: `${out}/state-notfound-${sfx}.png`});
  await p.goto(base+'/course/view.php?id=9999'); await p.waitForLoadState('networkidle'); await setDark(); await p.waitForTimeout(600);
  assert.equal(await p.evaluate(()=>getComputedStyle(document.querySelector('.errorbox')).borderTopLeftRadius),'36px');
  if (out) await p.screenshot({path: `${out}/state-error-${sfx}.png`});
  await p.goto(base+'/my/'); await p.waitForLoadState('networkidle'); await setDark();
  await ctx.setOffline(true); await p.evaluate(()=>window.dispatchEvent(new Event('offline'))); await p.waitForTimeout(700);
  const off = await p.locator('.mm-state-offline').count(); assert.equal(off,1,'offline card');
  const box = await p.locator('.mm-state-offline').boundingBox(); assert.ok(box.x>=0 && box.x+box.width<=w, 'offline card fits');
  if (out) await p.screenshot({path: `${out}/state-offline-${sfx}.png`});
  await p.locator('.mm-state-offline button').click(); await p.waitForTimeout(800);
  assert.equal(await p.locator('.mm-state-offline').count(),1,'still offline after a failed try');
  await ctx.setOffline(false); await p.waitForTimeout(1500);
  assert.equal(await p.locator('.mm-state-offline').count(),0,'gone once online');
  assert.equal(await p.locator('.mm-state-note').count(),1,'back online note');
  if (out) await p.screenshot({path: `${out}/state-online-${sfx}.png`});
 }
 assert.deepEqual(errs,[]); console.log(w,'states checks passed'); await ctx.close();
}
await b.close();
