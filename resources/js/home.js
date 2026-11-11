// The hub home: the scenes beyond the hero, in the manner of the Amnesia site (GSAP ScrollTrigger + Lenis).
// GSAP and Lenis ship inside our own bundle (no CDN). Every scene is complete as plain HTML and CSS;
// this file only adds motion, and does nothing with "reduce motion" on.
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';
import { createGlobe } from './home-globe.js';

const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
const once = (trigger, start = 'top 78%') => ({ trigger, start, once: true });
const lang = () => document.documentElement.lang || 'en';

function start() {
    gsap.registerPlugin(ScrollTrigger);
    document.documentElement.classList.add('hx-motion', 'hx-live');

    // smooth scroll, kept in step with ScrollTrigger
    const lenis = new Lenis({ duration: 1.05, smoothWheel: true });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((t) => lenis.raf(t * 1000));
    gsap.ticker.lagSmoothing(0);
    $$('a[href^="#"]').forEach((a) => a.addEventListener('click', (e) => {
        const id = a.getAttribute('href');
        const target = id.length > 1 && $(id);
        if (!target) return;
        e.preventDefault();
        lenis.scrollTo(target, { offset: -72 });
    }));

    heads(); marquee(); account(); faq();
    addEventListener('load', () => ScrollTrigger.refresh());
    document.fonts?.ready.then(() => ScrollTrigger.refresh());
}

/** a heading block: label, title, lead rise one after another */
function heads() {
    $$('.hx-head, .hx-final .hx-wrap > *').forEach((head) => {
        const parts = head.classList.contains('hx-head') ? Array.from(head.children) : [head];
        gsap.from(parts, { y: 26, opacity: 0, duration: .8, stagger: .09, ease: 'power3.out', scrollTrigger: once(head, 'top 86%') });
    });
}

function marquee() {
    const track = $('[data-hx-marq] > div');
    if (track) {
        track.innerHTML += track.innerHTML;
        Array.from(track.children).slice(track.children.length / 2).forEach((c) => c.setAttribute('aria-hidden', 'true'));
        track.classList.add('run');
    }
    const nums = $('[data-hx-nums]');
    if (!nums) return;
    $$('[data-count]', nums).forEach((el) => {
        const end = Number(el.dataset.count), o = { v: 0 };
        el.textContent = '0';
        gsap.to(o, { v: end, duration: 1.3, ease: 'power2.out', onUpdate: () => { el.textContent = Math.round(o.v).toLocaleString(lang()); }, scrollTrigger: once(nums, 'top 85%') });
    });
}

/** 01: pinned. One sign-in, a ticket to each app, the codes that protect it all, and one sign-out that reaches every app.
 *  The globe (home-globe.js) draws the lines and the packet; the whole story is one pure function of time, apply(t),
 *  scrubbed by the scroll, so the picture is right in both directions. */
function account() {
    const sec = $('[data-hx-acct]');
    if (!sec) return;
    const stage = $('.hx-stage', sec), hub = $('.hx-hub', sec), code = $('[data-hx-code]', sec);
    const nodes = $$('[data-hx-node]', sec), sts = $$('[data-hx-st]', sec), ticket = $('[data-hx-ticket]', sec), note = $('[data-hx-note]', sec), steps = $$('[data-hx-steps] li', sec);
    const inTxt = sts[0].textContent, outTxt = stage.dataset.signedOut || 'Signed out', noteIn = note.textContent, noteOut = stage.dataset.noteOut || noteIn;

    // the ticket's code: eight characters, a different code for every app. While a ticket is being made its
    // characters keep changing; they settle from the left as the packet sets off.
    const tk = $('[data-hx-tk]', ticket), GLYPHS = '0123456789ABCDEF#$%&+=<>', TK = 8, cells = [];
    let tkApp = 0, tkSolved = 0, tkClock = 0;
    const codeFor = (i) => { let s = ''; for (let k = 0; k < TK; k++) { const h = Math.sin(i * 91.7 + k * 47.3 + 1.3) * 43758.5453; s += '0123456789ABCDEF'.charAt(Math.floor((h - Math.floor(h)) * 16)); } return s; };
    const paintTicket = () => {
        if (!cells.length) { tk.textContent = ''; for (let k = 0; k < TK; k++) cells.push(tk.appendChild(document.createElement('i'))); }
        const word = codeFor(tkApp);
        for (let j = 0; j < TK; j++) {
            const raw = j >= tkSolved;
            const ch = raw ? GLYPHS.charAt(Math.floor(Math.random() * GLYPHS.length)) : word.charAt(j);
            if (cells[j].textContent !== ch) cells[j].textContent = ch;
            cells[j].classList.toggle('raw', raw);
        }
    };
    // the story only updates on scroll; the code keeps changing between scrolls, so it is repainted every 70 ms
    const onFrame = (dt) => {
        tkClock += dt;
        if (tkClock >= .07 && ticket.classList.contains('show')) { tkClock = 0; paintTicket(); }
    };

    const globe = createGlobe(stage, { onFrame });
    if (!globe) {
        // no 2D canvas: the flat ring is the finished picture, nothing hidden
        sts.forEach((s) => s.classList.add('show'));
        [code, note].forEach((el) => el.classList.add('show'));
        steps.forEach((li) => li.classList.add('on'));
        return;
    }

    const n = nodes.length, TOTAL = 8.8;
    // the same 2.8 s (in) and 2 s (out) for any number of apps, so the steps stay in place
    const inStep = 2.8 / n, outStep = 2 / n;
    const clamp = (v, a, b) => (v < a ? a : v > b ? b : v);
    const inOut = (t) => (t < .5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2);
    const backOut = (t) => { const c = 1.6; return 1 + (c + 1) * Math.pow(t - 1, 3) + c * Math.pow(t - 1, 2); };
    const text = (el, value) => { if (el.textContent !== value) el.textContent = value; };

    // the picture at time t of the story (0 .. TOTAL seconds)
    const apply = (t) => {
        const k = clamp(t / .5, 0, 1), out = t >= 6.2;
        let packet = null;
        // 1 sign in once: the hub appears and the lines are drawn
        hub.style.setProperty('--s', (.85 + .15 * backOut(k)).toFixed(4));
        hub.style.opacity = (.4 + .6 * k).toFixed(3);
        for (let i = 0; i < n; i++) {
            globe.beams[i] = inOut(clamp((t - .3 - i * .08) / .8, 0, 1));
            // 2 open any app: a ticket travels to each app, which signs in
            const a = 1.7 + i * inStep, d = Math.min(.55, inStep - .02), signed = t >= a + d;
            // 4 leave everywhere: one sign-out reaches every app, one after another
            const b = 6.3 + i * outStep, e = Math.min(.45, outStep - .02), gone = t >= b + e;
            if (t >= a && t < a + d) packet = { i, t: inOut((t - a) / d) };
            if (t >= b && t < b + e) packet = { i, t: inOut((t - b) / e) };
            nodes[i].classList.toggle('is-out', gone);
            text(sts[i], gone ? outTxt : inTxt);
            sts[i].classList.toggle('show', signed);
            globe.alpha[i] = gone ? .55 : signed ? 1 : .5;
            globe.pulse[i] = gone ? 0 : signed ? 1 : .3;   // a live line pulses; a signed-out one is still
        }
        ticket.classList.toggle('show', t >= 1.6 && t < 4.6);
        tkApp = clamp(Math.floor((t - 1.7) / inStep), 0, n - 1);
        tkSolved = Math.floor(clamp((t - 1.7 - tkApp * inStep) / (inStep * .45), 0, 1) * TK);
        // 3 keep it safe: the code and the green ring
        code.classList.toggle('show', t >= 4.9);
        hub.classList.toggle('is-secure', t >= 5.0 && t < 6.2);
        note.classList.toggle('show', t >= 5.1);
        text(note, out ? noteOut : noteIn);
        stage.classList.toggle('is-out', out);
        globe.out = out;
        globe.packet = packet;
        const at = Math.min(steps.length - 1, Math.floor(t / TOTAL * steps.length));
        steps.forEach((li, j) => { li.classList.toggle('on', j === at); li.classList.toggle('done', j < at); });
    };
    apply(0);

    // one linear tween of the story clock, scrubbed by the scroll
    const clock = { t: 0 };
    const tween = gsap.to(clock, { t: TOTAL, duration: TOTAL, ease: 'none', paused: true, onUpdate: () => apply(clock.t) });
    const mm = gsap.matchMedia();
    mm.add('(min-width: 981px)', () => {
        ScrollTrigger.create({ animation: tween, trigger: sec, start: 'top 64px', end: '+=3400', pin: true, scrub: .7 });
    });
    mm.add('(max-width: 980px)', () => {
        ScrollTrigger.create({ animation: tween, trigger: stage, start: 'top 75%', end: 'bottom 25%', scrub: .7 });
    });
}

function faq() {
}

if (!reduced && document.querySelector('.hop-home')) {
    if (document.readyState === 'loading') addEventListener('DOMContentLoaded', start);
    else start();
}

