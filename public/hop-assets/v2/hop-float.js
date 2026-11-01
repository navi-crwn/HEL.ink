/*
 * HOP floating tiles, one copy for every HOP app (served from hel.ink/hop-assets).
 *
 *   <link rel="stylesheet" href="https://hel.ink/hop-assets/v1/hop-float.css">
 *   <div data-hop-float></div>            fixed behind the page
 *   <div data-hop-float="section"></div>  inside a position:relative box
 *   <script src="https://hel.ink/hop-assets/v1/hop-float.js" defer></script>
 *
 * The script draws the six tiles itself, so every app shows the same marks. Each tile
 * drifts and bounces off the edges, changing colour on every bounce. Decorative only:
 * aria-hidden, no pointer events, paused when the tab is hidden, still under
 * prefers-reduced-motion. Uses no inline style attributes (CSSOM only), so it runs
 * under strict CSP style-src.
 */
(function () {
  'use strict';
  var THEMES = 6;
  var script = document.currentScript;
  var base = script && script.src ? script.src.replace(/[^/]*$/, '') : '';
  var SVG = 'http://www.w3.org/2000/svg';

  // White marks on the coloured tile. LinkHop uses its official white rabbit mark;
  // BioHop the official document mark (purple identity, owner decision 2026-10-30).
  var MARKS = {
    pixelhop: { box: '0 0 32 32', crisp: true, fill: 'M4 0h4v4H4zM24 0h4v4h-4zM4 4h4v4H4zM24 4h4v4h-4zM4 8h8v4H4zM20 8h8v4h-8zM4 12h24v4H4zM0 16h8v4H0zM12 16h8v4h-8zM24 16h8v4h-8zM0 20h32v4H0zM4 24h24v4H4zM8 28h16v4H8z' },
    notehop: { box: '0 0 32 32', crisp: true, fill: 'M8 0h4v4H8zM20 0h4v4h-4zM8 4h4v4H8zM20 4h4v4h-4zM4 8h16v4H4zM4 12h24v4H4zM4 16h4v4H4zM20 16h8v4h-8zM4 20h24v4H4zM4 24h4v4H4zM16 24h12v4H16zM4 28h4v4H4zM12 28h16v4H12z' },
    biohop: { box: '0 0 48 48', fill: 'M15 13h18a2 2 0 0 1 0 4H15a2 2 0 0 1 0-4zM15 22h18a2 2 0 0 1 0 4H15a2 2 0 0 1 0-4zM15 31h9a2 2 0 0 1 0 4h-9a2 2 0 0 1 0-4z' },
    remotehop: { box: '0 0 24 24', stroke: ['M4.5 9.5a11 11 0 0 1 15 0', 'M7.5 13a7 7 0 0 1 9 0', 'M10.5 16.3a3 3 0 0 1 3 0'], dot: [12, 19.5, 1] },
    imperium: { box: '0 0 64 64', strokeShield: 'M32 11.5 17 17v12c0 9.6 6 16.4 15 21.5 9-5.1 15-11.9 15-21.5V17Z', fill: 'M27 23.5h3v2.5h4v-2.5h3V34h3v-2.5h3V40h-3v3H25v-3h-3v-8.5h3V34h2Zm2.5 19.5h5v-4.5a2.5 2.5 0 0 0-5 0Z', evenodd: true }
  };
  var TILES = [
    ['linkhop', 0], ['pixelhop', 2], ['notehop', 1], ['biohop', 4], ['remotehop', 3], ['imperium', 5]
  ];

  function el(name, attrs) {
    var n = document.createElementNS(SVG, name);
    for (var k in attrs) n.setAttribute(k, attrs[k]);
    return n;
  }

  function mark(slug) {
    if (slug === 'linkhop') {
      var img = document.createElement('img');
      img.src = base + 'apps/linkhop/mark-white.svg';
      img.alt = '';
      img.draggable = false;
      return img;
    }
    var m = MARKS[slug];
    var svg = el('svg', { viewBox: m.box, 'aria-hidden': 'true' });
    if (m.crisp) svg.setAttribute('shape-rendering', 'crispEdges');
    if (m.strokeShield) svg.appendChild(el('path', { d: m.strokeShield, fill: 'none', stroke: '#fff', 'stroke-width': '3.2', 'stroke-linejoin': 'round' }));
    if (m.fill) svg.appendChild(el('path', { d: m.fill, fill: '#fff', 'fill-rule': m.evenodd ? 'evenodd' : 'nonzero' }));
    if (m.stroke) {
      m.stroke.forEach(function (d) {
        svg.appendChild(el('path', { d: d, fill: 'none', stroke: '#fff', 'stroke-width': '1.9', 'stroke-linecap': 'round' }));
      });
    }
    if (m.dot) svg.appendChild(el('circle', { cx: m.dot[0], cy: m.dot[1], r: m.dot[2], fill: '#fff' }));
    return svg;
  }

  function build(layer) {
    layer.classList.add('hop-float');
    layer.setAttribute('aria-hidden', 'true');
    if (!layer.querySelector('.hop-sprite')) {
      TILES.forEach(function (t, i) {
        var sprite = document.createElement('div');
        sprite.className = 'hop-sprite hop-theme-' + t[1];
        sprite.dataset.sprite = t[0];
        sprite.style.setProperty('--i', String(i));
        var tile = document.createElement('div');
        tile.className = 'hop-tile';
        var face = document.createElement('span');
        face.className = 'hop-face';
        face.appendChild(mark(t[0]));
        tile.appendChild(face);
        sprite.appendChild(tile);
        layer.appendChild(sprite);
      });
    }
    animate(layer);
  }

  function animate(layer) {
    var sprites = Array.prototype.slice.call(layer.querySelectorAll('.hop-sprite'));
    if (!sprites.length) return;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    var W = 0, H = 0;
    var bodies = sprites.map(function (sprite, i) {
      var angle = (i / sprites.length) * Math.PI * 2 + 0.6;
      var speed = 28 + (i % 3) * 9;
      var m = sprite.className.match(/hop-theme-(\d)/);
      return {
        el: sprite, theme: m ? Number(m[1]) : i % THEMES, x: 0, y: 0, size: 72,
        fx: 0.08 + ((i * 0.37) % 0.8), fy: 0.12 + ((i * 0.53) % 0.7),
        vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed, placed: false
      };
    });
    function paint(b) { b.el.style.transform = 'translate3d(' + b.x.toFixed(1) + 'px,' + b.y.toFixed(1) + 'px,0)'; }
    function measure() {
      W = layer.clientWidth; H = layer.clientHeight;
      bodies.forEach(function (b) {
        b.size = b.el.offsetWidth || 72;
        if (!b.placed) { b.x = b.fx * (W - b.size); b.y = b.fy * (H - b.size); b.placed = true; }
        b.x = Math.min(Math.max(b.x, 0), Math.max(W - b.size, 0));
        b.y = Math.min(Math.max(b.y, 0), Math.max(H - b.size, 0));
        paint(b);
      });
    }
    function recolor(b) {
      var next = (b.theme + 1 + Math.floor(Math.random() * (THEMES - 1))) % THEMES;
      b.el.classList.remove('hop-theme-' + b.theme);
      b.el.classList.add('hop-theme-' + next);
      b.theme = next;
      b.el.classList.remove('is-bounce');
      void b.el.offsetWidth;
      b.el.classList.add('is-bounce');
    }
    var last = 0, raf = 0, running = false;
    function step(t) {
      raf = 0;
      var dt = Math.min((t - last) / 1000, 0.05) || 0;
      last = t;
      bodies.forEach(function (b) {
        b.x += b.vx * dt; b.y += b.vy * dt;
        var maxX = Math.max(W - b.size, 0), maxY = Math.max(H - b.size, 0), hit = false;
        if (b.x <= 0) { b.x = 0; b.vx = Math.abs(b.vx); hit = true; } else if (b.x >= maxX) { b.x = maxX; b.vx = -Math.abs(b.vx); hit = true; }
        if (b.y <= 0) { b.y = 0; b.vy = Math.abs(b.vy); hit = true; } else if (b.y >= maxY) { b.y = maxY; b.vy = -Math.abs(b.vy); hit = true; }
        if (hit) recolor(b);
        paint(b);
      });
      if (running) raf = requestAnimationFrame(step);
    }
    function start() { if (running || reduce.matches) return; running = true; last = performance.now(); if (!raf) raf = requestAnimationFrame(step); }
    function stop() { running = false; if (raf) cancelAnimationFrame(raf); raf = 0; }
    measure();
    layer.classList.add('is-ready');
    window.addEventListener('resize', measure, { passive: true });
    document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); else start(); });
    if (reduce.addEventListener) reduce.addEventListener('change', function () { if (reduce.matches) stop(); else start(); });
    start();
  }

  function init() { Array.prototype.forEach.call(document.querySelectorAll('[data-hop-float]'), build); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
