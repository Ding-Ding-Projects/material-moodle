import { chromium } from 'playwright';
const B=process.env.MOODLE_URL || 'http://localhost:8080', OUT=new URL('../../screenshots/', import.meta.url).pathname;
const b=await chromium.launch(); const p=await (await b.newContext({viewport:{width:1440,height:900}})).newPage();
const errs=[]; p.on('pageerror',e=>errs.push(String(e)));
await p.goto(B+'/login/index.php'); await p.waitForLoadState('networkidle'); await p.fill('#username','admin'); await p.fill('#password',process.env.MOODLE_ADMIN_PASSWORD); await p.click('#loginbtn'); await p.waitForTimeout(3000);
console.log('after login', p.url());
await p.screenshot({path:OUT+'onboarding-colour.png'});
await p.click('label.local-materialtools-swatch >> nth=1'); await p.click('button[value=next]'); await p.waitForTimeout(1500);
await p.screenshot({path:OUT+'onboarding-signin.png'});
for (let i=0;i<4;i++){ await p.click('button[value=next]'); await p.waitForTimeout(1500); }
await p.screenshot({path:OUT+'onboarding-done.png'});
console.log('final', p.url(), errs);
await b.close();
