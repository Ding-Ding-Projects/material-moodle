// Run against a test deployment with a signed-in, disposable browser storage state.
import assert from 'node:assert/strict';
import {chromium, webkit} from 'playwright';

const {MOODLE_URL, MOODLE_STORAGE_STATE} = process.env;
if (!MOODLE_URL || !MOODLE_STORAGE_STATE) {
    throw new Error('Provide MOODLE_URL and MOODLE_STORAGE_STATE for the test deployment.');
}
for (const engine of [chromium, webkit]) {
    const browser = await engine.launch();
    try {
        const context = await browser.newContext({storageState: MOODLE_STORAGE_STATE, hasTouch: true});
        const page = await context.newPage();
        await page.goto(new URL('/my/', MOODLE_URL).href);
        const trigger = page.locator('.mm-display-toggle');
        const panel = page.locator('.mm-display-panel');
        for (const [width, height] of [[320, 568], [390, 720], [740, 320], [1280, 800]]) {
            await page.setViewportSize({width, height});
            await trigger.click();
            await panel.waitFor();
            const box = await panel.boundingBox();
            assert.ok(box.x >= -1 && box.y >= 0 && box.x + box.width <= width + 1 && box.y + box.height <= height + 1);
            const choices = panel.locator('button[data-setting]');
            assert.equal(await choices.count(), 9);
            for (const choice of await choices.all()) {
                await choice.scrollIntoViewIfNeeded();
                const visible = await choice.evaluate(el => {
                    const r = el.getBoundingClientRect();
                    return el.contains(document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2));
                });
                assert.ok(visible, await choice.textContent());
            }
            await page.keyboard.press('Escape');
            await panel.waitFor({state: 'hidden'});
            assert.ok(await trigger.evaluate(el => el === document.activeElement));
        }
    } finally {
        await browser.close();
    }
}
