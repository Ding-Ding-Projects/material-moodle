import { chromium } from 'playwright';
const B=process.env.MOODLE_URL || 'http://localhost:8080', OUT=new URL('../../screenshots/', import.meta.url).pathname;
const browser = await chromium.launch();
const errors=[];
async function ctx(scheme){ const c=await browser.newContext({viewport:{width:1440,height:900},colorScheme:scheme}); const p=await c.newPage(); p.on('pageerror',e=>errors.push(String(e))); p.on('console',m=>{if(m.type()==='error')errors.push(m.text())}); return [c,p]; }
let [c,p]=await ctx('light');
await p.goto(B+'/login/index.php'); await p.waitForTimeout(1500);
await p.screenshot({path:OUT+'login-light.png'});
const [c2,p2]=await ctx('dark'); await p2.goto(B+'/login/index.php'); await p2.waitForTimeout(1500); await p2.screenshot({path:OUT+'login-dark.png'}); await c2.close();
await p.fill('#username','admin'); await p.fill('#password',process.env.MOODLE_ADMIN_PASSWORD); await p.click('#loginbtn'); await p.waitForURL(/\/my\//); await p.waitForTimeout(1500);
await p.screenshot({path:OUT+'dashboard.png'});
await p.goto(B+'/course/view.php?id=2'); await p.waitForTimeout(1200); await p.screenshot({path:OUT+'course.png'});
await p.keyboard.press('Control+Shift+F'); await p.waitForTimeout(500);
await p.keyboard.type('^Intro'); await p.waitForTimeout(800); await p.screenshot({path:OUT+'command-palette.png'});
await p.keyboard.press('Escape');
await p.mouse.click(700,500,{button:'right'}); await p.waitForTimeout(400); await p.screenshot({path:OUT+'context-menu.png'}); await p.keyboard.press('Escape');
for (const [name,url] of [['theme-settings','/admin/settings.php?section=themesettingmaterial'],['new-student','/local/materialtools/newstudent.php'],['vocabulary','/local/materialtools/vocabulary.php'],['site-wording','/local/materialtools/wording.php'],['personal-titles','/local/materialtools/titles.php'],['passkeys','/auth/passkey/manage.php'],['participants','/user/index.php?id=2']]) {
  await p.goto(B+url); await p.waitForTimeout(1200); await p.screenshot({path:OUT+name+'.png', fullPage: name!=='participants'});
}
// phone
const m = await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true,deviceScaleFactor:2,storageState: await c.storageState()});
const mp = await m.newPage();
for (const [name,url] of [['phone-dashboard','/my/'],['phone-course','/course/view.php?id=2']]) { await mp.goto(B+url); await mp.waitForTimeout(1500); await mp.screenshot({path:OUT+name+'.png'}); }
const mm = await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true,deviceScaleFactor:2}); const lp=await mm.newPage(); await lp.goto(B+'/login/index.php'); await lp.waitForTimeout(1500); await lp.screenshot({path:OUT+'phone-login.png'});
// create a student
await p.goto(B+'/local/materialtools/newstudent.php');
await p.fill('#id_firstname','Sam'); await p.fill('#id_lastname','Lee'); await p.fill('#id_title','Intern');
await p.click('#id_submitbutton'); await p.waitForTimeout(1500);
await p.screenshot({path:OUT+'new-student-credentials.png', fullPage:true});
const msg = await p.inputValue('#local-materialtools-message').catch(()=> 'NO MESSAGE');
console.log('MESSAGE:\n'+msg);
console.log('ERRORS', JSON.stringify(errors.slice(0,15)));
await browser.close();
