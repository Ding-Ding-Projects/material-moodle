import assert from 'node:assert/strict';
import {pathToFileURL} from 'node:url';

// Use a signed-in test administrator, the default seed and comfortable density.
// No course or administration form is submitted.
export async function verifyControls(page, base, capture = async () => {}) {
    await page.goto(new URL('/course/edit.php?category=1', base).href);
    const name = page.locator('#id_fullname');
    await name.waitFor();
    const display = page.locator('.mm-display-toggle');
    await display.click();
    await page.locator('.mm-display-panel [data-value="light"]').click();
    await page.keyboard.press('Escape');

    for (const width of [320, 390, 1280]) {
        await page.setViewportSize({width, height: 900});
        await page.waitForFunction(w => window.innerWidth === w, width);
        const account = await page.locator('#user-menu-toggle').boundingBox();
        assert.ok(account.x >= 0 && account.x + account.width <= width + 1, 'Account menu inside viewport');
        const input = await name.evaluate(el => {
            const s = getComputedStyle(el);
            return [s.height, s.borderRadius, s.borderTopWidth, s.borderBottomWidth];
        });
        assert.deepEqual(input, ['56px', '14px', '1px', '1px']);
        assert.equal(await page.locator('#id_saveanddisplay').evaluate(el => getComputedStyle(el).borderRadius), '18px');
        if (width === 390 || width === 1280) await capture('course-' + width, page);
        await display.click();
        const edit = page.locator('.editmode-switch-form');
        assert.equal(await edit.evaluate(el => !!el.closest('.mm-display-panel')), width < 576);
        assert.ok(await edit.locator('label').isVisible());
        await page.keyboard.press('Escape');
    }

    await name.fill('Unsaved course');
    await name.focus();
    assert.notEqual(await name.evaluate(el => getComputedStyle(el).boxShadow), 'none');
    const visibility = page.locator('#id_visible');
    const option = await visibility.locator('option').last().getAttribute('value');
    await visibility.selectOption(option);
    assert.equal(await visibility.inputValue(), option);
    const section = page.locator('#id_general .fheader').first();
    await section.click();
    await name.waitFor({state: 'hidden'});
    await section.click();
    await name.waitFor();
    assert.equal(await name.inputValue(), 'Unsaved course');
    const endDate = page.locator('#id_enddate_enabled');
    await endDate.uncheck();
    assert.ok(await page.locator('#id_enddate_day').isDisabled());
    await endDate.check();
    assert.ok(await page.locator('#id_enddate_day').isEnabled());

    await display.click();
    await page.locator('.mm-display-panel [data-value="dark"]').click();
    await page.keyboard.press('Escape');
    const toolbar = page.locator('.tox-toolbar__primary').first();
    await toolbar.waitFor();
    await page.waitForFunction(() => document.documentElement.dataset.bsTheme === 'dark');
    await page.waitForFunction(() => document.querySelector('.tox-tinymce')?.dataset.bsTheme === 'dark');
    const surface = await toolbar.evaluate(el => getComputedStyle(el).backgroundColor);
    assert.equal(surface, 'rgb(37, 30, 26)', 'Editor chrome follows dark palette');
    const canvas = page.frameLocator('iframe.tox-edit-area__iframe').first().locator('body.mce-content-body');
    await canvas.fill('Course overview');
    await canvas.press('End');
    assert.equal(await canvas.innerText(), 'Course overview');
    assert.equal(await canvas.evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(24, 18, 13)');
    assert.equal(await canvas.evaluate(el => getComputedStyle(el).color), 'rgb(237, 224, 217)');
    const editor = toolbar.locator('xpath=ancestor::*[contains(@class,"tox-tinymce")][1]');
    await editor.evaluate(el => el.scrollIntoView({block: 'center'}));
    await capture('editor-dark', page, editor);

    page.once('dialog', dialog => dialog.accept());
    await page.goto(new URL('/admin/settings.php?section=local_materialtools_operations', base).href);
    const check = page.locator('input[type=checkbox][name=s__usezipbackups]');
    const previous = await check.isChecked();
    await check.focus();
    await page.keyboard.press('Space');
    assert.equal(await check.isChecked(), !previous);
    assert.equal(await check.evaluate(el => getComputedStyle(el).appearance), 'none');
    await page.keyboard.press('Space');
    assert.equal(await check.isChecked(), previous);
    const select = page.locator('[name=s__enrolments_sync_interval]');
    await select.selectOption('900');
    assert.equal(await select.inputValue(), '900');
    await page.setViewportSize({width: 1280, height: 1400});
    await capture('admin-dark', page, page.locator('#adminsettings'));
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const {MOODLE_URL, MOODLE_STORAGE_STATE} = process.env;
    if (!MOODLE_URL || !MOODLE_STORAGE_STATE) throw new Error('Set MOODLE_URL and MOODLE_STORAGE_STATE for a test installation.');
    const {chromium, webkit} = await import('playwright');
    for (const engine of [chromium, webkit]) {
        const browser = await engine.launch();
        try {
            const context = await browser.newContext({storageState: MOODLE_STORAGE_STATE, reducedMotion: 'reduce'});
            await verifyControls(await context.newPage(), MOODLE_URL);
        } finally {
            await browser.close();
        }
    }
}
