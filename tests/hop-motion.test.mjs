import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL(`../public/hop-assets/${process.env.HOP_ASSETS_VERSION || 'v9'}/hop-motion.js`, import.meta.url), 'utf8');

function el(attrs = {}, top = 100) {
    const classes = new Set(), props = {}, own = new Map(Object.entries(attrs));
    return {
        children: [], props, classes,
        classList: {add: c => classes.add(c), remove: c => classes.delete(c), contains: c => classes.has(c), toggle(c, on) { if (on) classes.add(c); else classes.delete(c); }},
        setAttribute: (k, v) => own.set(k, v), hasAttribute: k => own.has(k), getAttribute: k => own.get(k),
        style: {setProperty: (k, v) => { props[k] = v; }},
        getBoundingClientRect: () => ({top, bottom: top + 100, left: 10, right: 210, width: 200, height: 100}),
        closest(sel) { return own.has(sel.replace(/[\[\]]/g, '')) ? this : null; },
    };
}

function fixture({reduce = false, fine = true, scenes = [], groups = [], bentos = []} = {}) {
    const listeners = {}, observers = [], rafs = [];
    const html = el();
    const all = {'[data-scene]': scenes, '[data-split]': [], '[data-rv-group], .hop-bento': [...groups, ...bentos]};
    const document = {
        documentElement: html,
        querySelectorAll: sel => sel === '[data-rv], [data-split]' ? [...groups, ...bentos].flatMap(g => g.children) : (all[sel] || []),
        addEventListener: (type, fn) => { (listeners[type] ||= []).push(fn); },
    };
    class Observer {
        constructor(cb) { this.cb = cb; this.targets = new Set(); observers.push(this); }
        observe(t) { this.targets.add(t); } unobserve(t) { this.targets.delete(t); } disconnect() { this.targets.clear(); }
    }
    const context = {
        document, innerHeight: 800, IntersectionObserver: Observer,
        matchMedia: q => ({matches: q.includes('reduced-motion') ? reduce : fine}),
        requestAnimationFrame: fn => { rafs.push(fn); return rafs.length; },
        addEventListener() {}, performance: {now: () => 0},
    };
    context.window = context;
    vm.runInNewContext(source, context);
    const flush = () => { const q = rafs.splice(0); q.forEach(fn => fn(16)); };
    return {context, html, observers, listeners, flush};
}

test('a [data-scene] is live only while it is on screen', () => {
    const scene = el({'data-scene': ''}), other = el({'data-scene': ''});
    const f = fixture({scenes: [scene, other]});
    assert.equal(f.observers.length, 1);
    const [observer] = f.observers;
    assert.equal(observer.targets.size, 2);
    observer.cb([{target: scene, isIntersecting: true}]);
    assert.equal(scene.classes.has('is-live'), true); assert.equal(other.classes.has('is-live'), false);
    observer.cb([{target: scene, isIntersecting: false}, {target: other, isIntersecting: true}]);
    assert.equal(scene.classes.has('is-live'), false); assert.equal(other.classes.has('is-live'), true);
    f.context.HopMotion.scan({querySelectorAll: () => [scene]});
    assert.equal(observer.targets.size, 2, 'a scene already wired is not observed twice');
});

test('reduced motion: no scene goes live, no spotlight, no hidden boxes, the old API still shows the final state', () => {
    const scene = el({'data-scene': ''}), box = el({'data-spot': ''}), grid = el({}); grid.classes.add('hop-bento'); grid.children = [box];
    const f = fixture({reduce: true, scenes: [scene], bentos: [grid]});
    assert.equal(f.observers.length, 0);
    assert.equal(scene.classes.has('is-live'), false);
    assert.equal((f.listeners.pointermove || []).length, 0);
    assert.equal(f.html.classes.has('mo'), false);
    assert.equal(box.hasAttribute('data-rv'), false);
    let ran = 0; f.context.HopMotion.once(box, () => ran++);
    assert.equal(ran, 1);
    const seen = []; f.context.HopMotion.scrub(box, v => seen.push(v)); assert.deepEqual(seen, [1]);
});

test('boxes of a .hop-bento come in 40 ms apart, a [data-rv-group] 80 ms apart, [data-rv-load] on load', () => {
    const mk = n => Array.from({length: n}, () => el({}, 5000));
    const bento = el({}), group = el({'data-rv-group': ''}), dash = el({'data-rv-load': ''});
    bento.classes.add('hop-bento'); bento.children = mk(3); group.children = mk(3); dash.classes.add('hop-bento'); dash.children = mk(2);
    const f = fixture({bentos: [bento, dash], groups: [group]});
    assert.deepEqual(bento.children.map(c => c.style.transitionDelay), ['0ms', '40ms', '80ms']);
    assert.deepEqual(group.children.map(c => c.style.transitionDelay), ['0ms', '80ms', '160ms']);
    assert.equal(f.html.classes.has('mo'), true);
    assert.equal(bento.children.every(c => !c.classes.has('in')), true, 'off screen boxes wait for the scroll');
    assert.equal(dash.children.every(c => !c.classes.has('in')), true);
    f.flush();
    assert.equal(dash.children.every(c => c.classes.has('in')), true, 'a data-rv-load group shows on load');
});

test('pointermove on a [data-spot] box writes --mx and --my once per frame', () => {
    const box = el({'data-spot': ''});
    const f = fixture();
    const [move] = f.listeners.pointermove;
    move({target: box, clientX: 60, clientY: 40}); move({target: box, clientX: 70, clientY: 50});
    f.flush();
    assert.equal(box.props['--mx'], '60px', 'the last position of the frame, relative to the box (left 10)');
    assert.equal(box.props['--my'], '-50px', 'relative to the box (top 100)');
    move({target: {closest: () => null}, clientX: 1, clientY: 1});
});

test('no spotlight listener without a fine pointer', () => {
    const f = fixture({fine: false});
    assert.equal((f.listeners.pointermove || []).length, 0);
});
