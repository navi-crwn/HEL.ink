import test from 'node:test';
import assert from 'node:assert/strict';

// A small fake of the DOM the globe touches: a stage with a canvas and six tiles, a 2D context that records nothing.
function setup(canvases = 2) {
    const frames = new Map(), cancelled = [];
    let sequence = 0;
    const listeners = [];
    const layers = Array.from({ length: canvases }, () => {
        const cv = { width: 0, height: 0 };
        const c2 = new Proxy({}, { get: (_, name) => (name === 'canvas' ? cv : name === 'clearRect' ? () => { cv.cleared++; } : name === 'createRadialGradient' || name === 'createLinearGradient' ? () => ({ addColorStop() {} }) : () => {}), set: () => true });
        cv.getContext = (kind) => (kind === '2d' ? c2 : null);
        cv.cleared = 0;
        cv.ctx = c2;
        return cv;
    });
    const tile = () => {
        const classes = new Set(), vars = new Map();
        return {
            style: { transform: '', opacity: '', zIndex: '', setProperty: (k, v) => vars.set(k, v), removeProperty: (k) => vars.delete(k), getPropertyValue: (k) => vars.get(k) },
            classList: { add: (...c) => c.forEach((x) => classes.add(x)), remove: (...c) => c.forEach((x) => classes.delete(x)), contains: (c) => classes.has(c), toggle: (c, on) => (on ? classes.add(c) : classes.delete(c)) },
            vars,
        };
    };
    const nodes = Array.from({ length: 6 }, tile);
    const stageClasses = new Set();
    const stage = {
        clientWidth: 520,
        querySelector: (sel) => (sel === 'canvas' ? layers[0] : null),
        querySelectorAll: (sel) => (sel === '[data-hx-node]' ? nodes : sel === 'canvas' ? layers : []),
        addEventListener: (type) => listeners.push(type),
        removeEventListener: (type) => listeners.splice(listeners.indexOf(type), 1),
        classList: { add: (...c) => c.forEach((x) => stageClasses.add(x)), remove: (...c) => c.forEach((x) => stageClasses.delete(x)), contains: (c) => stageClasses.has(c) },
        setPointerCapture() {}, releasePointerCapture() {},
    };
    const noop = class { observe() {} disconnect() {} };
    const raf = (cb) => { frames.set(++sequence, cb); return sequence; };
    const doc = { hidden: false, documentElement: {}, addEventListener() {}, removeEventListener() {} };
    Object.assign(globalThis, {
        window: { devicePixelRatio: 2, ResizeObserver: noop, IntersectionObserver: noop, addEventListener() {} },
        document: doc, ResizeObserver: noop, IntersectionObserver: noop, MutationObserver: noop,
        getComputedStyle: () => ({ getPropertyValue: () => '' }),
        requestAnimationFrame: raf,
        cancelAnimationFrame: (id) => { cancelled.push(id); frames.delete(id); },
    });
    return { stage, layers, nodes, stageClasses, frames, cancelled, listeners };
}

const { createGlobe } = await import('../resources/js/home-globe.js');

test('placing tiles: transform and --card, near side in front, far side behind', () => {
    const env = setup();
    const globe = createGlobe(env.stage);
    assert.ok(globe);
    assert.ok(env.stageClasses.has('is-globe'));
    globe.yaw = 0; globe.pitch = 0.11; globe.draw();
    for (const el of env.nodes) {
        assert.match(el.style.transform, /^translate\(-50%,-50%\) translate3d\(.+\) scale\(.+\)$/);
        assert.ok(el.vars.has('--card'));
        assert.ok(el.style.opacity !== '');
    }
    const [front, , , far] = env.nodes;           // app 0 sits at longitude 0, app 3 on the opposite side
    assert.ok(front.classList.contains('is-front'));
    assert.equal(front.style.zIndex, 5);
    assert.equal(Number(front.vars.get('--card')), 1);
    assert.ok(!far.classList.contains('is-front'));
    assert.equal(far.style.zIndex, 1);
    assert.ok(Number(far.vars.get('--card')) < 0.01);
    globe.destroy();
});

test('turning the globe moves a tile from the far side to the near side', () => {
    const env = setup();
    const globe = createGlobe(env.stage);
    globe.yaw = Math.PI; globe.draw();
    assert.ok(env.nodes[3].classList.contains('is-front'));
    assert.ok(!env.nodes[0].classList.contains('is-front'));
    globe.destroy();
});

test('public fields: one beam and one alpha per app, no packet, not signed out', () => {
    const env = setup();
    const globe = createGlobe(env.stage);
    assert.equal(globe.beams.length, 6);
    assert.equal(globe.alpha.length, 6);
    assert.equal(globe.pulse.length, 6);
    assert.equal(globe.packet, null);
    assert.equal(globe.out, false);
    assert.equal(typeof globe.vel, 'number');
    assert.ok(globe.dir === 1 || globe.dir === -1);
    globe.destroy();
});

test('no 2D canvas: createGlobe returns null and leaves the stage alone', () => {
    const env = setup();
    env.stage.querySelectorAll = () => [{ getContext: () => null }];
    assert.equal(createGlobe(env.stage), null);
    assert.ok(!env.stageClasses.has('is-globe'));
});

test('destroy() gives the stage back and stops scheduling frames', () => {
    const env = setup();
    const globe = createGlobe(env.stage);
    assert.equal(env.frames.size, 1);              // one frame is waiting
    const [id, tick] = [...env.frames][0];
    globe.draw();
    const before = env.layers.map((cv) => cv.cleared);
    globe.destroy();
    env.layers.forEach((cv, k) => assert.ok(cv.cleared > before[k], 'canvas ' + k + ' is cleared'));
    assert.ok(env.cancelled.includes(id));
    assert.ok(!env.stageClasses.has('is-globe'));
    assert.deepEqual(env.listeners, []);
    for (const el of env.nodes) {
        assert.equal(el.style.transform, '');
        assert.equal(el.style.opacity, '');
        assert.equal(el.style.zIndex, '');
        assert.ok(!el.vars.has('--card'));
        assert.ok(!el.classList.contains('is-front'));
    }
    tick(1000);                                    // a frame that was already running must not schedule another
    assert.equal(env.frames.size, 0);
});

test('two canvases: both are sized to the stage', () => {
    const env = setup();
    const globe = createGlobe(env.stage);
    assert.equal(env.layers.length, 2);
    for (const cv of env.layers) assert.equal(cv.width, 1040);   // 520 px at a pixel ratio of 2
    globe.destroy();
});

test('with a single canvas the engine still works', () => {
    const env = setup(1);
    const globe = createGlobe(env.stage);
    assert.ok(globe);
    globe.yaw = 0; globe.pulse[0] = 0; globe.draw();
    assert.ok(env.nodes[0].classList.contains('is-front'));
    assert.equal(env.nodes[0].style.zIndex, 5);
    assert.ok(!env.nodes[3].classList.contains('is-front'));
    const before = env.layers[0].cleared;
    globe.destroy();
    assert.ok(env.layers[0].cleared > before);
    assert.ok(!env.stageClasses.has('is-globe'));
});
