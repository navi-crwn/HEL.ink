// The first-paint script (resources/views/partials/hop-theme.blade.php) against a fake browser.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const html = readFileSync(new URL('../resources/views/partials/hop-theme.blade.php', import.meta.url), 'utf8');
const code = html.match(/<script[^>]*>([\s\S]*?)<\/script>/)[1];

function run({ cookie = '', local = {}, host = 'localhost', dark = false, throwStorage = false } = {}) {
  const classes = new Set();
  const written = [];
  const store = { ...local };
  const document = {
    documentElement: { dataset: {}, classList: { add: (c) => classes.add(c) } },
    get cookie() { return cookie; },
    set cookie(v) { written.push(v); cookie = cookie ? cookie + '; ' + v.split(';')[0] : v.split(';')[0]; },
  };
  const localStorage = throwStorage
    ? { getItem() { throw new Error('blocked'); }, setItem() { throw new Error('blocked'); } }
    : { getItem: (k) => store[k] ?? null, setItem: (k, v) => { store[k] = v; } };
  const window = { matchMedia: () => ({ matches: dark }) };
  vm.runInNewContext(code, { document, localStorage, location: { hostname: host }, window });
  return { classes, written, store, window };
}

test('the cookie wins over the old key and the system preference', () => {
  assert.ok(run({ cookie: 'hop_theme=dark', local: { 'helink-theme': 'light' } }).classes.has('dark'));
  assert.ok(!run({ cookie: 'hop_theme=light', dark: true }).classes.has('dark'));
});

test('without cookie the old key is used and migrated into the cookie', () => {
  const r = run({ local: { 'helink-theme': 'dark' } });
  assert.ok(r.classes.has('dark'));
  assert.ok(r.written.some((w) => w.startsWith('hop_theme=dark;')));
});

test('without cookie and key the system preference decides, and nothing is written', () => {
  assert.ok(run({ dark: true }).classes.has('dark'));
  const r = run({ dark: false });
  assert.ok(!r.classes.has('dark'));
  assert.deepEqual(r.written, []);
  assert.ok(!run({ cookie: 'hop_theme=system', dark: false }).classes.has('dark'));
});

test('the writer uses .hel.ink with Secure on hel.ink hosts and host-only elsewhere, and keeps the old key', () => {
  for (const host of ['hel.ink', 'hub.hel.ink']) {
    const r = run({ host });
    r.window.hopSetTheme('dark');
    assert.ok(r.written.at(-1).includes('domain=.hel.ink; Secure'));
    assert.ok(r.written.at(-1).includes('path=/; max-age=31536000; SameSite=Lax'));
    assert.equal(r.store['helink-theme'], 'dark');
  }
  for (const host of ['localhost', 'hel.ink.example.com']) {
    const r = run({ host });
    r.window.hopSetTheme('light');
    assert.ok(!r.written.at(-1).includes('domain='));
  }
});

test('blocked storage does not break the page', () => {
  assert.ok(run({ throwStorage: true, dark: true }).classes.has('dark'));
});
