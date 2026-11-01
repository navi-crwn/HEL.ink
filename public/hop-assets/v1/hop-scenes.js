// Scroll-linked scenes for the home pages, small and local (the CSP allows only this site's scripts).
// A scene is a tall block whose inner stage sticks while the visitor scrolls through it; the position
// becomes a number from 0 to 1 that the markup reads:
//   <div class="sc" data-scene style="--sc-len:320vh"> <div class="sc-stage"> ... </div> </div>
//   --p                    on the scene: 0..1 (CSS can use calc(var(--p) * 100%) and clamp())
//   [data-at=".4"]         gets class "on" once --p passes .4, and loses it again when scrolling back
//   [data-p-num="a,b"]     a number that runs from a to b over data-p-range=".1,.6" (unit: data-p-unit)
//   [data-p-type]          its text is typed out over data-p-range
//   [data-p-draw]          an SVG path that is drawn over data-p-range
//   [data-p-steps]         its children light up one by one, the current one gets "on", earlier ones "done"
// Anything else on the page:
//   [data-count="1234"]    counts up once when seen          [data-marquee]  a seamless running line
//   [data-glow]            a light that follows the cursor   [data-tilt]     a card that leans towards the cursor
//   [data-chapters]        a list of #anchors; the one in view is marked
// Nothing is hidden before this runs; with "reduce motion" every scene shows its end state.
(function () {
  'use strict';
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var root = document.documentElement;
  var scenes = [], ticking = false;
  function clamp(v, a, b) { return Math.min(b, Math.max(a, v)); }
  function range(el, fallback) {
    var r = (el.getAttribute('data-p-range') || fallback || '0,1').split(',');
    return [parseFloat(r[0]), parseFloat(r[1])];
  }
  function local(p, r) { return clamp((p - r[0]) / Math.max(.0001, r[1] - r[0]), 0, 1); }
  function fmt(n, unit) { return Math.round(n).toLocaleString() + (unit ? ' ' + unit : ''); }

  function prepare(el) {
    var s = { el: el, p: -1, at: [], nums: [], types: [], draws: [], steps: [] };
    el.querySelectorAll('[data-at]').forEach(function (n) { s.at.push({ n: n, at: parseFloat(n.getAttribute('data-at')) }); });
    el.querySelectorAll('[data-p-num]').forEach(function (n) {
      var ab = n.getAttribute('data-p-num').split(',').map(parseFloat);
      s.nums.push({ n: n, a: ab[0], b: ab[1], r: range(n), unit: n.getAttribute('data-p-unit') || '' });
    });
    el.querySelectorAll('[data-p-type]').forEach(function (n) {
      var text = n.getAttribute('data-p-type') || n.textContent;
      n.setAttribute('data-p-type', text);
      s.types.push({ n: n, text: text, r: range(n) });
    });
    el.querySelectorAll('[data-p-draw]').forEach(function (n) {
      var len = n.getTotalLength ? n.getTotalLength() : 0;
      n.style.strokeDasharray = len; n.style.strokeDashoffset = len;
      s.draws.push({ n: n, len: len, r: range(n) });
    });
    el.querySelectorAll('[data-p-steps]').forEach(function (n) { s.steps.push({ n: n, r: range(n), kids: Array.prototype.slice.call(n.children) }); });
    return s;
  }

  function apply(s, p) {
    s.el.style.setProperty('--p', p.toFixed(4));
    s.at.forEach(function (a) { a.n.classList.toggle('on', p >= a.at); });
    s.nums.forEach(function (x) { x.n.textContent = fmt(x.a + (x.b - x.a) * local(p, x.r), x.unit); });
    s.types.forEach(function (x) { x.n.textContent = x.text.slice(0, Math.round(x.text.length * local(p, x.r))); });
    s.draws.forEach(function (x) { x.n.style.strokeDashoffset = x.len * (1 - local(p, x.r)); });
    s.steps.forEach(function (x) {
      var k = local(p, x.r), at = Math.min(x.kids.length - 1, Math.floor(k * x.kids.length * .9999));
      x.kids.forEach(function (c, i) { c.classList.toggle('on', i === at); c.classList.toggle('done', i < at || k >= 1); });
    });
    s.el.dispatchEvent(new CustomEvent('hop-scene', { detail: { p: p } }));
  }

  function tick() {
    ticking = false;
    var h = innerHeight;
    scenes.forEach(function (s) {
      var r = s.el.getBoundingClientRect();
      if (r.bottom < -h * .5 || r.top > h * 1.5) return;
      // pinned: progress runs while the stage sticks; otherwise while the block crosses the screen
      var pinned = s.el.classList.contains('is-pinned');
      var p = pinned ? clamp(-(r.top - 72) / Math.max(1, r.height - h + 72), 0, 1) : clamp((h - r.top) / Math.max(1, h + r.height), 0, 1);
      if (Math.abs(p - s.p) > .0004) { s.p = p; apply(s, p); }
    });
  }
  function schedule() { if (!ticking) { ticking = true; requestAnimationFrame(tick); } }

  var narrow = matchMedia('(max-width: 800px)');
  function layout() {
    scenes.forEach(function (s) { if (s.el.classList.contains('sc')) s.el.classList.toggle('is-pinned', !reduce && !narrow.matches); });
    schedule();
  }

  document.querySelectorAll('[data-scene]').forEach(function (el) {
    var s = prepare(el); scenes.push(s);
    if (reduce) { el.classList.add('is-static'); apply(s, 1); }
  });
  if (scenes.length && !reduce) {
    root.classList.add('sc-on');
    narrow.addEventListener('change', layout);
    addEventListener('scroll', schedule, { passive: true });
    addEventListener('resize', schedule);
    layout();
    // fonts and images change heights
    addEventListener('load', schedule);
  }

  // ---------- counters ----------
  document.querySelectorAll('[data-count]').forEach(function (el) {
    var n = parseFloat(el.getAttribute('data-count')), done = false, suffix = el.getAttribute('data-suffix') || '';
    function run() {
      if (done) return; done = true;
      var M = window.HopMotion;
      if (M && M.count) M.count(el, n, function (v) { return v.toLocaleString() + suffix; });
      else el.textContent = n.toLocaleString() + suffix;
    }
    if (reduce || !('IntersectionObserver' in window)) { el.textContent = n.toLocaleString() + suffix; return; }
    var io = new IntersectionObserver(function (es) { if (es[0].isIntersecting) { run(); io.disconnect(); } }, { threshold: .4 });
    io.observe(el);
  });

  // ---------- running line: two copies make the loop seamless ----------
  document.querySelectorAll('[data-marquee]').forEach(function (el) {
    var track = el.firstElementChild;
    if (!track || reduce) return;
    track.innerHTML += track.innerHTML;
    Array.prototype.slice.call(track.children, track.children.length / 2).forEach(function (c) { c.setAttribute('aria-hidden', 'true'); });
  });

  // ---------- a light and a lean that follow the cursor ----------
  document.querySelectorAll('[data-glow]').forEach(function (el) {
    el.addEventListener('pointermove', function (e) {
      var r = el.getBoundingClientRect();
      el.style.setProperty('--mx', (e.clientX - r.left) + 'px'); el.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });
  });
  if (!reduce) document.querySelectorAll('[data-tilt]').forEach(function (el) {
    el.addEventListener('pointermove', function (e) {
      var r = el.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      el.style.transform = 'perspective(900px) rotateY(' + (x * 8).toFixed(2) + 'deg) rotateX(' + (-y * 8).toFixed(2) + 'deg)';
    });
    el.addEventListener('pointerleave', function () { el.style.transform = ''; });
  });

  // ---------- chapter dots: the section in view is marked ----------
  document.querySelectorAll('[data-chapters]').forEach(function (nav) {
    var links = Array.prototype.slice.call(nav.querySelectorAll('a[href^="#"]')), map = {};
    links.forEach(function (a) { var t = document.getElementById(a.getAttribute('href').slice(1)); if (t) map[t.id] = a; });
    var ids = Object.keys(map);
    if (!ids.length || !('IntersectionObserver' in window)) return;
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (!e.isIntersecting) return;
        links.forEach(function (a) { a.classList.toggle('on', a === map[e.target.id]); a.removeAttribute('aria-current'); });
        map[e.target.id].setAttribute('aria-current', 'true');
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    ids.forEach(function (id) { io.observe(document.getElementById(id)); });
  });

  // ---------- a frame of fixed size (a phone screen) that is scaled to fit its box: --fit = box width / data-fit ----------
  document.querySelectorAll('[data-fit]').forEach(function (el) {
    var w = parseFloat(el.getAttribute('data-fit')) || 360;
    function fit() { el.style.setProperty('--fit', (el.clientWidth / w).toFixed(4)); }
    fit();
    if ('ResizeObserver' in window) new ResizeObserver(fit).observe(el); else addEventListener('resize', fit);
  });

  // ---------- the 3D hero hands over to the page behind it and takes it back (BioHop) ----------
  document.addEventListener('hop-hero-reveal', function (e) {
    var host = e.target.closest && e.target.closest('[data-cycle-hold]');
    if (host) host.setAttribute('data-revealed', String(!!(e.detail && e.detail.shown)));
  });

  window.HopScenes = { refresh: schedule };
})();
