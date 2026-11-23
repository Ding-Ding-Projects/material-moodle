// Checks the dockview course workspace on a running test site.
//   MOODLE_URL=http://localhost:8080 MOODLE_USER=student MOODLE_PASSWORD=... COURSE_ID=2 node material/tests/e2e/course-workspace.mjs
// ENGINES=chromium or ENGINES=webkit runs one engine; the default runs both. Uses a disposable account.
import assert from 'node:assert/strict';
import {chromium, webkit} from 'playwright';

const base = process.env.MOODLE_URL || 'http://localhost:8080';
const course = process.env.COURSE_ID || '2';
const engines = (process.env.ENGINES || 'chromium,webkit').split(',').map(n => ({chromium, webkit})[n.trim()]);
// WebKit reports Moodle core requests cut short by navigation as page errors; they are not from this code.
const navigationAbort = /due to access control checks|TypeError: Load failed|new M\.core\.exception/;

for (const engine of engines) {
    const browser = await engine.launch();
    const page = await browser.newPage({viewport: {width: 1440, height: 900}});
    const errors = [];
    page.on('pageerror', e => {
        if (!navigationAbort.test(String(e))) {
            errors.push(String(e));
        }
    });
    await page.goto(base + '/login/index.php');
    await page.waitForLoadState('networkidle');
    await page.fill('#username', process.env.MOODLE_USER);
    await page.fill('#password', process.env.MOODLE_PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('#loginbtn')]);

    const open = async () => {
        await page.goto(`${base}/course/view.php?id=${course}`);
        await page.waitForLoadState('networkidle');
        await page.waitForSelector('.mm-ws-tab', {timeout: 15000});
        await page.waitForTimeout(600);
    };
    const tabs = async () => (await page.locator('.mm-ws-tab .mm-ws-tab-label').allInnerTexts()).sort();
    const menu = async item => {
        await page.locator('.mm-ws-bar .mm-ws-btn', {hasText: 'Layout'}).first().click();
        await page.locator('.mm-ws-menu-item', {hasText: item}).first().click();
        await page.waitForTimeout(1200);
    };

    await open();
    await menu('Reset layout');
    assert.deepEqual(await tabs(), ['Course', 'Course index'], 'Default panels');
    assert.ok(await page.locator('.mm-ws #region-main, .mm-ws .course-content').count() > 0, 'Course content inside the workspace');

    // Closing a tab is saved per person and survives a reload.
    await page.locator('.mm-ws-tab', {hasText: 'Course index'}).first().locator('.mm-ws-tab-close').click();
    await page.waitForTimeout(1500);
    await open();
    assert.deepEqual(await tabs(), ['Course'], 'Closed tab stays closed after reload');

    // Search tabs reopens it.
    await page.locator('.mm-ws-bar .mm-ws-btn', {hasText: 'Search tabs'}).first().click();
    const field = page.locator('.mm-ws-pop input, .mm-ws-pop [role="combobox"]').first();
    await field.fill('index');
    await field.press('Enter');
    await page.waitForTimeout(800);
    assert.deepEqual(await tabs(), ['Course', 'Course index'], 'Search tabs reopens a closed panel');

    // Classic layout puts Moodle's page back; switching again rebuilds the workspace.
    await menu('Classic layout');
    assert.equal(await page.locator('.mm-ws-tab').count(), 0, 'Classic layout removes the workspace');
    assert.equal(await page.locator('#region-main').count(), 1, 'Course content is back in the page');
    await page.locator('.mm-ws-classic-bar button, .mm-ws-classic-bar a').first().click();
    await page.waitForTimeout(1200);
    assert.ok(await page.locator('.mm-ws-tab').count() > 0, 'Workspace again');

    // Narrow windows keep the classic page.
    await page.setViewportSize({width: 900, height: 900});
    await page.waitForTimeout(1000);
    assert.equal(await page.locator('.mm-ws-tab').count(), 0, 'No workspace below 1200 pixels');

    assert.deepEqual(errors, [], 'No page errors');
    console.log(`${engine.name()}: course workspace checks passed`);
    await browser.close();
}
