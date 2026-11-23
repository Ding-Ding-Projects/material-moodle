// Checks the features launcher and the All features page on a running test site.
//   MOODLE_URL=http://localhost:8080 MOODLE_ADMIN_PASSWORD=... node material/tests/e2e/features-launcher.mjs
// Uses a disposable administrator account. Nothing is submitted or changed on the site.
import assert from 'node:assert/strict';
import {chromium, webkit} from 'playwright';

const base = process.env.MOODLE_URL || 'http://localhost:8080';
const out = process.env.SCREENSHOT_DIR || new URL('../../design/', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
const capture = process.env.SCREENSHOT_DIR !== undefined || process.env.CAPTURE === '1';

async function signIn(page) {
    await page.goto(base + '/login/index.php');
    // Scripts on the sign-in page reset the form while they load, so wait for them first.
    await page.waitForLoadState('networkidle');
    await page.fill('#username', process.env.MOODLE_ADMIN_USER || 'admin');
    await page.fill('#password', process.env.MOODLE_ADMIN_PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('#loginbtn')]);
    assert.ok(!page.url().includes('/login/'), 'Signed in');
}

async function visible(page) {
    return page.locator('.mm-launcher-panel [data-feature]:visible').evaluateAll(
        els => els.map(el => el.dataset.feature));
}

// ENGINES=chromium or ENGINES=webkit runs one engine; the default runs both.
const engines = (process.env.ENGINES || 'chromium,webkit').split(',').map(n => ({chromium, webkit})[n.trim()]);
for (const engine of engines) {
    const browser = await engine.launch(engine === chromium ? {channel: 'chromium'} : {});
    const context = await browser.newContext({viewport: {width: 1440, height: 900}});
    const page = await context.newPage();
    const errors = [];
    // WebKit reports Moodle core requests cut short by our own navigation as page errors
    // ("Fetch API cannot load ... due to access control checks", "Load failed"), and Moodle core
    // then fails to build its error object. Those come from navigating away, not from this code.
    const navigationAbort = /due to access control checks|TypeError: Load failed|new M\.core\.exception/;
    page.on('pageerror', e => {
        if (!navigationAbort.test(String(e))) {
            errors.push(String(e));
        }
    });
    await signIn(page);
    await page.goto(base + '/my/');

    for (const width of [320, 390, 1440]) {
        await page.setViewportSize({width, height: width < 500 ? 800 : 900});
        const toggle = page.locator('.mm-launcher-toggle');
        await toggle.click();
        const panel = page.locator('.mm-launcher-panel');
        await panel.waitFor({state: 'visible'});
        const box = await panel.boundingBox();
        assert.ok(box.x >= 0 && box.x + box.width <= width + 1, `Panel inside the viewport at ${width}`);
        assert.ok(box.y >= 0, `Panel starts below the top edge at ${width}`);
        assert.equal(await page.evaluate(() => document.activeElement.id), 'mm-launcher-q', 'Search takes focus');
        const all = await visible(page);
        for (const key of ['mycourses', 'calendar', 'grades', 'privatefiles', 'messages', 'preferences', 'siteadmin']) {
            assert.ok(all.includes(key), `${key} is listed at ${width}`);
        }
        // Every feature link is at least 44 pixels tall so it can be tapped.
        const heights = await page.locator('.mm-launcher-panel .mm-feature-link:visible').evaluateAll(
            els => els.map(el => el.getBoundingClientRect().height));
        assert.ok(Math.min(...heights) >= 44, `Touch targets at ${width}`);
        if (capture && engine === chromium && (width === 390 || width === 1440)) {
            await page.screenshot({path: `${out}features-launcher-${width}.png`});
        }
        await page.keyboard.press('Escape');
        await panel.waitFor({state: 'hidden'});
    }

    await page.setViewportSize({width: 1440, height: 900});
    await page.locator('.mm-launcher-toggle').click();
    const q = page.locator('#mm-launcher-q');
    await q.fill('grade');
    assert.deepEqual((await visible(page)).sort(), ['grades']);
    await q.fill('zzzz-no-such-feature');
    assert.ok(await page.locator('[data-features-empty]').isVisible(), 'Empty state shown');
    await page.locator('.mm-launcher-panel [data-features-regex]').click();
    await q.fill('^(cal|bad)');
    const regexHits = await visible(page);
    assert.ok(regexHits.includes('calendar') && regexHits.every(k => /^(calendar|badges)$/.test(k)), 'Pattern search');
    await q.fill('(');
    assert.ok(await page.locator('.mm-launcher-panel [data-features-invalid]').isVisible(), 'Incomplete pattern reported');
    await q.fill('');
    await page.locator('.mm-launcher-panel [data-features-chip="account"]').click();
    const account = await visible(page);
    assert.ok(account.includes('preferences') && !account.includes('calendar'), 'Group chip filters');
    await page.locator('.mm-launcher-panel [data-features-chip=""]').click();
    await q.focus();
    await page.keyboard.press('ArrowDown');
    const first = await page.evaluate(() => document.activeElement.closest('[data-feature]')?.dataset.feature);
    await page.keyboard.press('ArrowRight');
    const second = await page.evaluate(() => document.activeElement.closest('[data-feature]')?.dataset.feature);
    assert.ok(first && second && first !== second, 'Arrow keys move between features');
    await page.keyboard.press('Escape');

    await page.goto(base + '/theme/material/features.php');
    const groups = await page.locator('[data-region="mm-features-page"] [data-feature-group]').evaluateAll(
        els => els.map(el => el.dataset.featureGroup));
    assert.ok(groups.includes('learn') && groups.includes('admin'), 'Page groups');
    await page.fill('#mm-features-q', 'private files');
    await page.waitForFunction(() => /^1\b/.test(document.querySelector('[data-region="mm-features-page"] [data-features-status]').textContent));
    await page.fill('#mm-features-q', '');
    if (capture && engine === chromium) {
        await page.screenshot({path: `${out}features-page-1440.png`, fullPage: true});
        await page.setViewportSize({width: 390, height: 844});
        await page.screenshot({path: `${out}features-page-390.png`});
    }
    const scroll = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    assert.ok(scroll <= 1, 'No sideways scrolling on the page');
    assert.deepEqual(errors, [], 'No page errors');
    console.log(`${engine.name()}: launcher and page checks passed`);
    await browser.close();
}
