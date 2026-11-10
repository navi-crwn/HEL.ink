import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL(`../public/hop-assets/${process.env.HOP_ASSETS_VERSION || 'v7'}/hop-hero3d.js`, import.meta.url), 'utf8');
function fixture({reduce = false, webgl = true, mobile = false, instanced = false} = {}) {
    const frames = new Map(), observers = [], canvases = [], calls = [];
    let sequence = 0;
    const preference = Object.assign(new EventTarget(), {matches: reduce});
    const document = Object.assign(new EventTarget(), {hidden: false, readyState: 'loading', querySelectorAll: () => canvases});
    class Observer {
        constructor(callback) { this.callback = callback; this.targets = new Set(); observers.push(this); }
        observe(target) { this.targets.add(target); }
        unobserve(target) { this.targets.delete(target); }
        disconnect() { this.targets.clear(); }
    }
    const context = {document, addEventListener() {}, Math: Object.assign(Object.create(Math), {random: () => .5}), matchMedia: query => query.includes('max-width') ? {matches: mobile} : preference, AbortController, IntersectionObserver: Observer, ResizeObserver: Observer,
        requestAnimationFrame: callback => { frames.set(++sequence, callback); return sequence; }, cancelAnimationFrame: id => frames.delete(id)};
    context.window = {...context, devicePixelRatio: 3};
    vm.runInNewContext(source, context);
    function canvas() {
        const classes = new Set();
        const gl = new Proxy({}, {get: (_, name) => (...args) => {
            calls.push([name, ...args]);
            if (name === 'getExtension' && instanced) return {vertexAttribDivisorANGLE() {}, drawArraysInstancedANGLE: (...values) => calls.push(['instances', ...values])};
            if (name === 'getProgramParameter') return true;
            if (name.startsWith('create')) return {};
        }});
        const cv = Object.assign(new EventTarget(), {dataset: {}, clientWidth: 400, clientHeight: 300,
            classList: {add: c => classes.add(c), remove: c => classes.delete(c), contains: c => classes.has(c)},
            getContext: () => webgl ? gl : null, setPointerCapture() {}, getBoundingClientRect: () => ({left: 0, top: 0, width: 400, height: 300})});
        canvases.push(cv); return cv;
    }
    function visible(cv, ratio) { for (const o of [...observers]) if (o.targets.has(cv)) o.callback([{target: cv, isIntersecting: ratio > 0, intersectionRatio: ratio}]); }
    function frame(time = 1000) { const pending = [...frames.values()]; frames.clear(); pending.forEach(callback => callback(time)); }
    return {api: context.window.HopHero3D, canvas, calls, frames, observers, visible, frame, preference, document};
}

test('the four most visible scenes animate together, a fifth waits; hidden documents and reduced motion stop the loop', () => {
    const f = fixture(), a = f.canvas(), b = f.canvas();
    const first = f.api.mount(a), second = f.api.mount(b);
    assert.equal(f.api.mount(a), first);
    assert.equal(a.width, 800); assert.equal(a.height, 600);
    f.visible(a, 1); f.visible(b, .5); f.frame();
    assert.equal(a.dataset.animating, 'true'); assert.equal(b.dataset.animating, 'true');
    assert.equal(f.frames.size, 1);
    f.visible(a, 0); f.frame();
    assert.equal(a.dataset.animating, 'false'); assert.equal(b.dataset.animating, 'true');
    f.visible(a, 1); f.frame();
    f.document.hidden = true; f.document.dispatchEvent(new Event('visibilitychange'));
    assert.equal(f.frames.size, 0);
    f.document.hidden = false; f.document.dispatchEvent(new Event('visibilitychange')); f.frame();
    f.preference.matches = true; f.preference.dispatchEvent(new Event('change'));
    assert.equal(f.frames.size, 0); assert.equal(b.classList.contains('on'), false);
    f.preference.matches = false; f.preference.dispatchEvent(new Event('change')); f.frame();
    assert.equal(b.classList.contains('on'), true);
    first.destroy(); second.destroy(); f.frame(); assert.equal(f.frames.size, 0);
});

test('with more than four scenes on screen, every scene that has not settled keeps animating until it has', () => {
    const f = fixture(), cs = [1, .9, .8, .7, .6, .5].map(() => f.canvas());
    const apis = cs.map(c => f.api.mount(c));
    cs.forEach((c, i) => f.visible(c, 1 - i * .1)); f.frame(1000);
    assert.deepEqual(cs.map(c => c.dataset.animating), ['true', 'true', 'true', 'true', 'true', 'true']);
    for (let i = 0; i < 400 && !cs.every(c => c.dataset.settled === 'true'); i++) f.frame(1000 + i * 50);
    assert.deepEqual(cs.map(c => c.dataset.settled), Array(6).fill('true'));
    apis.forEach(api => assert.equal(api.settled(), true));
    // once settled, only the four most visible keep floating; the loop stays bounded
    f.frame(30000);
    assert.equal(cs.filter(c => c.dataset.animating === 'true').length, 4);
    assert.equal(f.frames.size, 1);
    apis.forEach(api => api.destroy());
});

test('destroy and context loss release resources, listeners and observers', () => {
    const f = fixture(), cv = f.canvas(), api = f.api.mount(cv);
    f.visible(cv, 1); f.frame();
    cv.dispatchEvent(new Event('webglcontextlost', {cancelable: true}));
    api.destroy(); f.frame();
    assert.equal(cv.classList.contains('on'), false);
    assert.equal(f.calls.filter(([name]) => name === 'deleteBuffer').length, 2);
    assert.equal(f.calls.filter(([name]) => name === 'deleteProgram').length, 1);
    assert.equal(f.observers.some(o => o.targets.has(cv)), false);
    assert.equal(f.frames.size, 0);
    assert.notEqual(f.api.mount(cv), api);
});

test('no WebGL and reduced motion keep the fallback without allocating a scene', () => {
    for (const options of [{webgl: false}, {reduce: true}]) {
        const f = fixture(options), cv = f.canvas();
        assert.equal(f.api.mount(cv), undefined);
        assert.equal(cv.classList.contains('on'), false);
        assert.equal(f.frames.size, 0);
        assert.equal(f.calls.length, 0);
    }
});

test('mobile limits pixel density and frame rate', () => {
    const f = fixture({mobile: true}), cv = f.canvas(), api = f.api.mount(cv);
    assert.equal(cv.width, 500);
    api.render(1000);
    const draws = f.calls.filter(([name]) => name === 'drawArrays').length;
    api.render(1016);
    assert.equal(f.calls.filter(([name]) => name === 'drawArrays').length, draws);
    api.render(1033);
    assert.equal(f.calls.filter(([name]) => name === 'drawArrays').length, draws * 2);
    api.destroy();
});

test('instancing batches blocks and gems into two draw calls and frees its buffer', () => {
    const f = fixture({instanced: true}), cv = f.canvas(), api = f.api.mount(cv);
    api.render(1000);
    const draws = f.calls.filter(([name]) => name === 'instances');
    assert.equal(draws.length, 2);
    assert.ok(draws[0][4] > 20);
    assert.equal(draws[1][4], 5);
    api.destroy();
    assert.equal(f.calls.filter(([name]) => name === 'deleteBuffer').length, 3);
});

test('keyboard and drag change the model; noninteractive canvases ignore controls', () => {
    for (const interactive of [true, false]) {
        const f = fixture(), cv = f.canvas(), api = f.api.mount(cv, {interactive});
        const reference = fixture(), baseline = reference.api.mount(reference.canvas(), {interactive});
        const model = scene => scene.calls.filter(([name]) => name === 'uniformMatrix4fv').map(call => Array.from(call[3]));
        api.render(1000);
        baseline.render(1000); f.calls.length = 0; reference.calls.length = 0;
        cv.dispatchEvent(Object.assign(new Event('keydown', {cancelable: true}), {key: 'ArrowRight'}));
        cv.dispatchEvent(Object.assign(new Event('pointerdown'), {clientX: 100, clientY: 100, pointerId: 1}));
        cv.dispatchEvent(Object.assign(new Event('pointermove'), {clientX: 180, clientY: 100}));
        api.render(1000);
        baseline.render(1000);
        if (interactive) assert.notDeepEqual(model(f), model(reference));
        else assert.deepEqual(model(f), model(reference));
        const event = Object.assign(new Event('keydown', {cancelable: true}), {key: 'Home'});
        cv.dispatchEvent(event); assert.equal(event.defaultPrevented, interactive);
        api.destroy(); baseline.destroy();
    }
});
