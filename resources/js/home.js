// The hub home: the scenes beyond the hero, in the manner of the Amnesia site (GSAP ScrollTrigger + Lenis).
// GSAP and Lenis ship inside our own bundle (no CDN). Every scene is complete as plain HTML and CSS;
// this file only adds motion, and does nothing with "reduce motion" on.
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';

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
    gsap.from($$('div', nums), { y: 24, opacity: 0, duration: .7, stagger: .08, ease: 'power3.out', scrollTrigger: once(nums, 'top 88%') });
    $$('[data-count]', nums).forEach((el) => {
        const end = Number(el.dataset.count), o = { v: 0 };
        el.textContent = '0';
        gsap.to(o, { v: end, duration: 1.3, ease: 'power2.out', onUpdate: () => { el.textContent = Math.round(o.v).toLocaleString(lang()); }, scrollTrigger: once(nums, 'top 85%') });
    });
}

/** 01: pinned. One sign-in, a ticket to each app, the codes that protect it all, and one sign-out that reaches every app. */
function account() {
    const sec = $('[data-hx-acct]');
    if (!sec) return;
    const stage = $('.hx-stage', sec), paths = $$('.lines path', sec), pkt = $('.pkt', sec), hub = $('.hx-hub', sec), code = $('[data-hx-code]', sec);
    const nodes = $$('[data-hx-node]', sec), sts = $$('[data-hx-st]', sec), ticket = $('[data-hx-ticket]', sec), note = $('[data-hx-note]', sec), steps = $$('[data-hx-steps] li', sec);
    const inTxt = sts[0].textContent, outTxt = stage.dataset.signedOut || 'Signed out', noteIn = note.textContent, noteOut = stage.dataset.noteOut || noteIn;
    paths.forEach((p) => { const l = p.getTotalLength(); gsap.set(p, { strokeDasharray: l, strokeDashoffset: l }); });
    gsap.set(pkt, { opacity: 0 });
    gsap.set(nodes, { opacity: .5 });
    gsap.set(sts, { autoAlpha: 0 });
    gsap.set([code, note], { autoAlpha: 0 });

    const travel = (i, dur = .55) => {
        const o = { t: 0 }, p = paths[i], len = p.getTotalLength();
        return gsap.to(o, { t: 1, duration: dur, ease: 'power1.inOut', onUpdate: () => { const pt = p.getPointAtLength(len * o.t); pkt.setAttribute('cx', pt.x); pkt.setAttribute('cy', pt.y); } });
    };
    const tl = gsap.timeline({ defaults: { ease: 'power2.out' } });
    // 1 sign in once: the hub appears and the lines are drawn
    tl.fromTo(hub, { scale: .85, autoAlpha: .4 }, { scale: 1, autoAlpha: 1, duration: .5, ease: 'back.out(1.6)' }, 0)
        .to(paths, { strokeDashoffset: 0, duration: .8, stagger: .08, ease: 'power2.inOut' }, .3)
        // 2 open any app: a ticket travels to each app, which signs in
        .set(ticket, { display: 'grid' }, 1.6);
    [0, 1, 2, 3].forEach((i) => {
        const at = 1.7 + i * .7;
        tl.set(pkt, { opacity: 1 }, at).add(travel(i), at).set(pkt, { opacity: 0 }, at + .55)
            .to(nodes[i], { opacity: 1, duration: .25 }, at + .5).to(sts[i], { autoAlpha: 1, duration: .25 }, at + .55);
    });
    tl.to(ticket, { autoAlpha: 0, duration: .2 }, 4.6)
        // 3 keep it safe: the code and the green ring
        .to(code, { autoAlpha: 1, duration: .3 }, 4.9).call(() => hub.classList.add('is-secure'), null, 5.0)
        .to(note, { autoAlpha: 1, duration: .3 }, 5.1)
        // 4 leave everywhere: one sign-out reaches every app, one after another
        .call(() => { stage.classList.add('is-out'); hub.classList.remove('is-secure'); note.textContent = noteOut; }, null, 6.2);
    [0, 1, 2, 3].forEach((i) => {
        const at = 6.3 + i * .5;
        tl.set(pkt, { opacity: 1 }, at).add(travel(i, .45), at).set(pkt, { opacity: 0 }, at + .45)
            .call(() => { nodes[i].classList.add('is-out'); sts[i].textContent = outTxt; }, null, at + .45).to(nodes[i], { opacity: .55, duration: .2 }, at + .45);
    });
    tl.to({}, { duration: .5 }); // a short hold at the end
    // when the scrub goes back, the stage returns to its signed-in state
    tl.eventCallback('onReverseComplete', () => { stage.classList.remove('is-out'); nodes.forEach((n) => n.classList.remove('is-out')); sts.forEach((s) => { s.textContent = inTxt; }); note.textContent = noteIn; });

    const mark = (progress) => {
        const at = Math.min(steps.length - 1, Math.floor(progress * steps.length));
        steps.forEach((li, i) => { li.classList.toggle('on', i === at); li.classList.toggle('done', i < at); });
        // before the sign-out part, the stage shows the signed-in wording
        if (progress < .72 && stage.classList.contains('is-out')) { stage.classList.remove('is-out'); nodes.forEach((n) => n.classList.remove('is-out')); sts.forEach((s) => { s.textContent = inTxt; }); note.textContent = noteIn; }
    };
    mark(0);
    const mm = gsap.matchMedia();
    mm.add('(min-width: 981px)', () => {
        ScrollTrigger.create({ animation: tl, trigger: sec, start: 'top 64px', end: '+=3400', pin: true, scrub: .7, onUpdate: (self) => mark(self.progress) });
    });
    mm.add('(max-width: 980px)', () => {
        ScrollTrigger.create({ animation: tl, trigger: stage, start: 'top 75%', end: 'bottom 25%', scrub: .7, onUpdate: (self) => mark(self.progress) });
    });
}

function faq() {
    $$('[data-hx-faq] .hx-cell').forEach((c, i) => gsap.from(c, { y: 34, opacity: 0, duration: .7, ease: 'power3.out', delay: (i % 3) * .08, scrollTrigger: once(c, 'top 92%') }));
}

if (!reduced && document.querySelector('.hop-home')) {
    if (document.readyState === 'loading') addEventListener('DOMContentLoaded', start);
    else start();
}
