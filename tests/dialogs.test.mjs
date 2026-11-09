import test from 'node:test';
import assert from 'node:assert/strict';

// resources/js/dialogs.js asks through window.HopUI when the shared dialog is on the page and
// through the browser's own dialogs when it is not, with the same promise either way.
const calls = [];
globalThis.window = {
    confirm: (text) => { calls.push(['confirm', text]); return true; },
    alert: (text) => { calls.push(['alert', text]); },
    prompt: (text) => { calls.push(['prompt', text]); return 'secret'; },
};
const { confirmDialog, alertDialog, promptDialog, confirmOptions, default: install } = await import('../resources/js/dialogs.js');

test('without HopUI the browser dialogs are used and answer as promises', async () => {
    calls.length = 0;
    assert.equal(await confirmDialog({ title: 'Revoke', message: 'Revoke the token “CLI”?', danger: true, subject: 'CLI' }), true);
    assert.equal(await alertDialog('Something went wrong.'), undefined);
    assert.equal(await promptDialog({ title: 'Disable 2FA', message: 'Turn off two-factor?', label: 'Enter your password:', type: 'password' }), 'secret');
    assert.deepEqual(calls, [
        ['confirm', 'Revoke the token “CLI”?'],
        ['alert', 'Something went wrong.'],
        ['prompt', 'Turn off two-factor?\n\nEnter your password:'],
    ]);
});

test('a cancelled or empty browser prompt is null, a refused confirm is false', async () => {
    window.prompt = () => null;
    assert.equal(await promptDialog({ label: 'Password' }), null);
    window.prompt = () => '';
    assert.equal(await promptDialog({ label: 'Password' }), null);
    window.confirm = () => false;
    assert.equal(await confirmDialog('Sure?'), false);
});

test('with HopUI on the page every question goes to it, options untouched', async () => {
    const seen = [];
    window.confirm = () => { throw new Error('the browser dialog must not open'); };
    window.HopUI = {
        confirm: (o) => { seen.push(['confirm', o]); return Promise.resolve(false); },
        alert: (o) => { seen.push(['alert', o]); return Promise.resolve(); },
        prompt: (o) => { seen.push(['prompt', o]); return Promise.resolve('pw'); },
    };
    const anchor = { id: 'button' };
    assert.equal(await confirmDialog({ message: 'Delete?', danger: true, subject: 'Work links', anchor }), false);
    assert.equal(await promptDialog({ label: 'Password', type: 'password' }), 'pw');
    await alertDialog('Done');
    assert.deepEqual(seen[0], ['confirm', { message: 'Delete?', danger: true, subject: 'Work links', anchor }]);
    assert.equal(seen[1][1].type, 'password');
    assert.deepEqual(seen[2], ['alert', { message: 'Done' }]);
    delete window.HopUI;
});

test('a half-loaded HopUI is not trusted', async () => {
    window.HopUI = { t: () => '' };
    window.confirm = () => true;
    assert.equal(await confirmDialog('Sure?'), true);
    delete window.HopUI;
});

test('data attributes become dialog options', () => {
    const el = { dataset: { confirm: 'Delete this announcement?', danger: '', subject: 'Maintenance on Friday', confirmOk: 'Delete it' } };
    assert.deepEqual(confirmOptions(el), {
        title: undefined, message: 'Delete this announcement?', ok: 'Delete it', danger: true, subject: 'Maintenance on Friday', anchor: el,
    });
    assert.equal(confirmOptions({ dataset: { confirm: 'Suspend?' } }).danger, false);
});

test('install exposes the helpers to inline page scripts', () => {
    install();
    assert.equal(window.HopDialogs.confirm, confirmDialog);
    assert.equal(window.HopDialogs.alert, alertDialog);
    assert.equal(window.HopDialogs.prompt, promptDialog);
});
