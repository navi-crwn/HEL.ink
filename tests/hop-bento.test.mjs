import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const version = process.env.HOP_ASSETS_VERSION || 'v9';
const css = fs.readFileSync(new URL(`../public/hop-assets/${version}/hop-bento.css`, import.meta.url), 'utf8');

// Read the span of every box, per box count, from the count rules of one media block.
function spans(block, scope, defaultSpan) {
    const table = {};
    for (let n = 2; n <= 9; n++) table[n] = Array(n).fill(defaultSpan);
    for (const line of block.split('\n')) {
        if (!line.includes(scope) || !line.includes(':last-child)')) continue;
        const m = line.match(/:has\(> :nth-child\((\d+)\):last-child\) > (.+?) \{ grid-column: span (\d+); \}/);
        if (!m) continue;
        const n = Number(m[1]);
        for (const k of m[2].matchAll(/:nth-child\((\d+)\)/g)) table[n][Number(k[1]) - 1] = Number(m[3]);
    }
    return table;
}
function block(startMarker) {
    const i = css.indexOf(startMarker);
    assert.ok(i >= 0, `missing ${startMarker}`);
    const open = css.indexOf('{', i);
    let depth = 0, j = open;
    for (; j < css.length; j++) { if (css[j] === '{') depth++; else if (css[j] === '}' && --depth === 0) break; }
    return css.slice(open, j);
}
function rowsOf(list, total) {
    const rows = []; let row = [], acc = 0;
    for (const s of list) { acc += s; row.push(s); if (acc === total) { rows.push(row); row = []; acc = 0; } else assert.ok(acc < total, `row overflows: ${list}`); }
    assert.equal(acc, 0, `an open row is left: ${list}`);
    return rows;
}
function check(table, total, name) {
    for (let n = 2; n <= 9; n++) {
        const rows = rowsOf(table[n], total);
        for (const r of rows) {
            assert.ok(r.length >= 2, `${name}, ${n} boxes: a box alone on a row (${rows.map(x => x.join('+')).join(' / ')})`);
            assert.ok(Math.max(...r) <= total / 2, `${name}, ${n} boxes: a box wider than half the row`);
        }
    }
}

const desktop = block('@media (min-width: 1100px)');
test('desktop content boxes: whole rows, none alone, none wider than half the row', () => {
    check(spans(desktop, ':not([data-tiles])', 20), 60, 'content');
});
test('desktop tiles: whole rows, none alone, none wider than half the row, five share one row', () => {
    const t = spans(desktop, '.hop-bento[data-tiles]:not(:has', 20);
    check(t, 60, 'tiles');
    assert.deepEqual(t[5], [12, 12, 12, 12, 12]);
});
test('tablet: whole rows of sixths, none alone', () => {
    const tablet = block('@media (min-width: 900px) and (max-width: 1099px)');
    check(spans(tablet, ':not([data-tiles])', 2), 6, 'tablet');
    const tiles = block('@media (min-width: 641px) and (max-width: 1099px)');
    check(spans(tiles, '.hop-bento[data-tiles]:not(:has', 2), 6, 'tablet tiles');
});
test('media is capped', () => {
    assert.match(css, /\[data-media\][^}]*height: clamp\(160px, 14vw, 200px\)/);
    assert.match(css, /\[data-media\][^}]*height: clamp\(120px, 34vw, 140px\)/);
    assert.match(css, /\[data-scene\][^}]*max-height: 140px/);
});
