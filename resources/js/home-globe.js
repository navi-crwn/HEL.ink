// The "One account" stage as a globe: HOP ID is the core inside it, every app sits on the surface, and a
// dotted sphere turns slowly and follows a drag. Two plain 2D canvases: one behind the core (the far half of the
// globe) and one in front of it (the near half), so the core is seen through the near side. The app tiles and the
// core stay HTML (sharp marks, real text) and are placed from the same projection. No library.
// With reduced motion the caller does not start it at all: the plain ring (HTML and SVG) is the still picture.
//
//   const globe = createGlobe(stageElement, { onFrame(dt) {} });   // null without a 2D canvas
//   globe.beams[i] = 0..1      how far the line to app i is drawn
//   globe.pulse[i] = 0..1      how strongly the line to app i pulses (a live connection)
//   globe.packet = { i, t }    a packet on line i at 0..1, or null
//   globe.alpha[i] = 0..1      how present app i is (dim before it signs in, dim after it signs out)
//   globe.out = true           the sign-out colour
//   globe.draw(), globe.destroy()        destroy() gives the stage back to the plain ring
const TAU = Math.PI * 2, DEG = Math.PI / 180;
const REST_PITCH = 0.11;             // the globe leans a little towards the viewer
const PITCH_MIN = -0.42, PITCH_MAX = 0.62;
const CAM = 3.4;                     // camera distance in globe radii: a mild perspective
const SPIN = 0.14;                   // idle turn, radians per second
const RADIUS = 0.40;                 // globe radius as a share of the stage width
const CORE = 0.30;                   // radius of the core in globe radii (the CSS makes the HOP ID orb 24% of the stage)
const SEG = 18;                      // pieces per line

function clamp(v, a, b) { return v < a ? a : v > b ? b : v; }
// a fixed "random" number 0..1 for app i and purpose k: the same picture on every visit
function hash(i, k) { var s = Math.sin(i * 127.1 + k * 311.7 + 4.2) * 43758.5453; return s - Math.floor(s); }

export function createGlobe(stage, opts) {
  opts = opts || {};
  var layers = stage.querySelectorAll('canvas');
  if (!layers.length || !layers[0].getContext) return null;
  var back = layers[0].getContext('2d'), front = layers[1] ? layers[1].getContext('2d') : back;
  if (!back || !front) return null;
  var nodes = Array.prototype.slice.call(stage.querySelectorAll('[data-hx-node]'));
  var n = nodes.length;

  // unit vectors: x right, y up, z towards the viewer.
  // The apps are scattered, not on a ring: each has its own latitude and its own step around the globe.
  // They alternate between the upper and the lower half and stay clear of the equator, so a tile on the
  // near side never covers the core.
  var nodeVec = nodes.map(function (_, i) {
    var lon = (i + (hash(i, 1) - .5) * .64) * TAU / n;
    var lat = (i % 2 ? -(28 + 16 * hash(i, 2)) : 32 + 22 * hash(i, 2)) * DEG;
    return [Math.cos(lat) * Math.sin(lon), Math.sin(lat), Math.cos(lat) * Math.cos(lon)];
  });
  // the line from the core to each app: a gentle curve, each bent its own way and by its own amount
  var lines = nodeVec.map(function (v, i) {
    var ax = [hash(i, 3) - .5, hash(i, 4) - .5, hash(i, 5) - .5];
    var w = [v[1] * ax[2] - v[2] * ax[1], v[2] * ax[0] - v[0] * ax[2], v[0] * ax[1] - v[1] * ax[0]];
    var wl = Math.sqrt(w[0] * w[0] + w[1] * w[1] + w[2] * w[2]) || 1;
    var bend = (.12 + .16 * hash(i, 6)) * (hash(i, 7) < .5 ? -1 : 1), mid = (CORE + 1) / 2;
    var c = [v[0] * mid + w[0] / wl * bend, v[1] * mid + w[1] / wl * bend, v[2] * mid + w[2] / wl * bend];
    var pts = [];
    for (var k = 0; k <= SEG; k++) {
      var t = k / SEG, u = 1 - t, p = u * u * CORE, q = 2 * u * t, r = t * t;
      pts.push([p * v[0] + q * c[0] + r * v[0], p * v[1] + q * c[1] + r * v[1], p * v[2] + q * c[2] + r * v[2]]);
    }
    return { pts: pts, x: new Float32Array(SEG + 1), y: new Float32Array(SEG + 1), z: new Float32Array(SEG + 1),
      speed: .32 + .2 * hash(i, 8), phase: hash(i, 9) };
  });
  var dots = [];
  for (var r = 1; r < 13; r++) {
    var lat = -Math.PI / 2 + r * Math.PI / 13, c = Math.cos(lat), count = Math.max(6, Math.round(34 * c));
    for (var k = 0; k < count; k++) {
      var lon = k * TAU / count + (r % 2 ? 0 : Math.PI / count);
      dots.push([c * Math.sin(lon), Math.sin(lat), c * Math.cos(lon)]);
    }
  }
  // two faint great circles, tilted differently: they make the ball read as a ball while it turns
  var orbit = [[.5, .3], [-1.02, 2.1]].map(function (o) {
    var ring = [], ct = Math.cos(o[0]), st = Math.sin(o[0]), cr = Math.cos(o[1]), sr = Math.sin(o[1]);
    for (var k = 0; k < 96; k++) {
      var a = k * TAU / 96, x = Math.cos(a) * ct, y = Math.cos(a) * st, z = Math.sin(a);
      ring.push([x * cr + z * sr, y, -x * sr + z * cr]);
    }
    return ring;
  });

  var g = {
    yaw: 0.35, pitch: REST_PITCH, vel: SPIN, dir: 1,
    beams: nodes.map(function () { return 1; }),
    pulse: nodes.map(function () { return 1; }),
    alpha: nodes.map(function () { return 1; }),
    packet: null, out: false,
    draw: draw, destroy: destroy
  };
  var shown = g.alpha.slice();           // alpha, eased
  var flow = g.pulse.slice();            // pulse, eased
  var size = 0, dpr = 1, R = 0, cx = 0, cy = 0, clock = 0;
  var raf = 0, last = 0, visible = true, dragging = false, alive = true;
  var px0 = 0, py0 = 0, moveAt = 0, fling = 0;
  var paint = null;
  var sy = 0, cyw = 1, sp = 0, cp = 1;   // sin and cos of yaw and pitch, set once per frame
  var out = [0, 0, 0, 0];

  function colours() {
    if (paint) return paint;
    var s = getComputedStyle(stage), v = function (name, fallback) { return (s.getPropertyValue(name) || '').trim() || fallback; };
    paint = {
      dot: v('--globe-dot', '79,70,229'), line: v('--globe-line', '99,102,241'), gone: v('--globe-out', '244,63,94'),
      pkt: v('--globe-pkt', '34,211,238'), body: v('--globe-body', '99,102,241'), beat: v('--globe-pulse', '129,140,248'),
      bodyIn: +v('--globe-body-in', '.02'), bodyOut: +v('--globe-body-out', '.09'), limb: +v('--globe-limb', '.16'),
      dotMin: +v('--globe-dot-min', '.07'), dotMax: +v('--globe-dot-max', '.5'),
      ringMin: +v('--globe-ring-min', '.05'), ringMax: +v('--globe-ring-max', '.3'), core: +v('--globe-core', '.2'),
      gloss: +v('--globe-gloss', '.1')
    };
    return paint;
  }

  function project(v) {
    var x = v[0] * cyw + v[2] * sy, z = -v[0] * sy + v[2] * cyw;
    var y = v[1] * cp - z * sp, z2 = v[1] * sp + z * cp;
    var s = CAM / (CAM - z2);
    out[0] = cx + R * x * s; out[1] = cy - R * y * s; out[2] = z2; out[3] = s;
    return out;
  }
  // the near half is painted in front of the core, the far half behind it
  function side(z) { return z > 0 ? front : back; }
  // the place at t (0..1) on a projected line
  function point(L, t) {
    var f = t * SEG, k = Math.min(SEG - 1, Math.floor(f)); f -= k;
    out[0] = L.x[k] + (L.x[k + 1] - L.x[k]) * f; out[1] = L.y[k] + (L.y[k + 1] - L.y[k]) * f; out[2] = L.z[k] + (L.z[k + 1] - L.z[k]) * f;
    return out;
  }

  function resize() {
    var w = stage.clientWidth;
    if (!w) return;
    size = w; dpr = Math.min(window.devicePixelRatio || 1, 2);
    for (var i = 0; i < layers.length && i < 2; i++) { layers[i].width = Math.round(size * dpr); layers[i].height = Math.round(size * dpr); }
    R = size * RADIUS; cx = size / 2; cy = size / 2;
    draw();
  }

  function draw() {
    if (!size || !alive) return;
    var c = colours(), i, j, k, p, ctx, L;
    sy = Math.sin(g.yaw); cyw = Math.cos(g.yaw); sp = Math.sin(g.pitch); cp = Math.cos(g.pitch);
    back.setTransform(dpr, 0, 0, dpr, 0, 0); back.clearRect(0, 0, size, size);
    if (front !== back) { front.setTransform(dpr, 0, 0, dpr, 0, 0); front.clearRect(0, 0, size, size); }

    // the body of the globe: a soft ball with a thin edge (the outline of a sphere seen in perspective)
    var edge = R * CAM / Math.sqrt(CAM * CAM - 1);
    var body = back.createRadialGradient(cx - edge * .28, cy - edge * .34, edge * .05, cx, cy, edge);
    body.addColorStop(0, 'rgba(' + c.body + ',' + c.bodyIn + ')');
    body.addColorStop(1, 'rgba(' + c.body + ',' + c.bodyOut + ')');
    back.beginPath(); back.arc(cx, cy, edge, 0, TAU);
    back.fillStyle = body; back.fill();
    back.lineWidth = 1; back.strokeStyle = 'rgba(' + c.dot + ',' + c.limb + ')'; back.stroke();
    // the light of the core, where HOP ID sits and every line starts
    var core = back.createRadialGradient(cx, cy, 0, cx, cy, R * .7);
    core.addColorStop(0, 'rgba(' + c.line + ',' + c.core + ')');
    core.addColorStop(1, 'rgba(' + c.line + ',0)');
    back.fillStyle = core; back.beginPath(); back.arc(cx, cy, R * .7, 0, TAU); back.fill();

    // the dots, far side faint and small, near side clear
    back.fillStyle = front.fillStyle = 'rgb(' + c.dot + ')';
    for (i = 0; i < dots.length; i++) {
      p = project(dots[i]); ctx = side(p[2]);
      var depth = (p[2] + 1) / 2;
      ctx.globalAlpha = c.dotMin + (c.dotMax - c.dotMin) * depth * depth;
      ctx.beginPath(); ctx.arc(p[0], p[1], (0.7 + 0.75 * depth) * p[3] * (size / 520), 0, TAU); ctx.fill();
    }
    back.lineWidth = front.lineWidth = 1; back.strokeStyle = front.strokeStyle = 'rgb(' + c.dot + ')';
    for (i = 0; i < orbit.length; i++) {
      var ring = orbit[i], ax = 0, ay = 0, az = 0;
      for (j = 0; j <= ring.length; j++) {
        p = project(ring[j % ring.length]);
        if (j) {
          var mz = (az + p[2]) / 2; ctx = side(mz);
          ctx.globalAlpha = c.ringMin + (c.ringMax - c.ringMin) * (mz + 1) / 2;
          ctx.beginPath(); ctx.moveTo(ax, ay); ctx.lineTo(p[0], p[1]); ctx.stroke();
        }
        ax = p[0]; ay = p[1]; az = p[2];
      }
    }
    back.globalAlpha = front.globalAlpha = 1;

    // the lines from the core to each app, their pulses, the packet, and the tiles
    var tone = g.out ? c.gone : c.line;
    back.lineCap = front.lineCap = 'round';
    for (i = 0; i < n; i++) {
      L = lines[i];
      for (k = 0; k <= SEG; k++) { p = project(L.pts[k]); L.x[k] = p[0]; L.y[k] = p[1]; L.z[k] = p[2]; }
      var drawn = clamp(g.beams[i], 0, 1) * SEG;
      for (k = 0; k < SEG && k < drawn; k++) {
        var f = Math.min(1, drawn - k), z = (L.z[k] + L.z[k + 1]) / 2;
        ctx = side(z);
        // the line grows out of the core's light: faint where it leaves the core, so it never strikes through the code
        var rise = Math.min(1, (k + .5) / SEG / .3);
        ctx.lineWidth = 1.5; ctx.strokeStyle = 'rgba(' + tone + ',' + ((0.2 + 0.42 * (z + 1) / 2) * rise * rise).toFixed(3) + ')';
        ctx.beginPath(); ctx.moveTo(L.x[k], L.y[k]);
        ctx.lineTo(L.x[k] + (L.x[k + 1] - L.x[k]) * f, L.y[k] + (L.y[k + 1] - L.y[k]) * f); ctx.stroke();
      }
      // a pulse runs out from the core along a live line: a bright head with a fading tail
      var beat = g.beams[i] >= 1 ? flow[i] : 0;
      if (beat > .02) {
        var head = (clock * L.speed + L.phase) % 1, tail = .26, glow = beat * Math.sin(Math.PI * head);
        for (k = 0; k < SEG; k++) {
          var behind = head - (k + .5) / SEG;
          if (behind < 0 || behind > tail) continue;
          var w = 1 - behind / tail;
          ctx = side((L.z[k] + L.z[k + 1]) / 2);
          ctx.lineWidth = 1.4 + 1.6 * w; ctx.strokeStyle = 'rgba(' + c.beat + ',' + (glow * w * w * .95).toFixed(3) + ')';
          ctx.beginPath(); ctx.moveTo(L.x[k], L.y[k]); ctx.lineTo(L.x[k + 1], L.y[k + 1]); ctx.stroke();
        }
        var hp = point(L, head); ctx = side(hp[2]);
        ctx.fillStyle = 'rgba(' + c.beat + ',' + glow.toFixed(3) + ')';
        ctx.beginPath(); ctx.arc(hp[0], hp[1], 2.3, 0, TAU); ctx.fill();
      }
      if (g.packet && g.packet.i === i) {
        var q = point(L, clamp(g.packet.t, 0, 1)), pk = g.out ? c.gone : c.pkt;
        ctx = side(q[2]);
        ctx.save();
        ctx.shadowColor = 'rgba(' + pk + ',.9)'; ctx.shadowBlur = 14;
        ctx.fillStyle = 'rgb(' + pk + ')'; ctx.beginPath(); ctx.arc(q[0], q[1], 4.5 + 1.5 * (q[2] + 1) / 2, 0, TAU); ctx.fill();
        ctx.restore();
      }
      // near side: the whole tile; far side: it shrinks and fades to the mark alone (--card drives the frame and the text)
      var x = L.x[SEG], y = L.y[SEG], zn = L.z[SEG], near = (zn + 1) / 2;
      var el = nodes[i], scale = 0.6 + 0.48 * near, card = clamp((zn + 0.34) / 0.44, 0, 1);
      card = card * card * (3 - 2 * card);
      el.style.transform = 'translate(-50%,-50%) translate3d(' + (x - cx).toFixed(2) + 'px,' + (y - cy).toFixed(2) + 'px,0) scale(' + scale.toFixed(4) + ')';
      el.style.opacity = (shown[i] * (0.42 + 0.58 * near)).toFixed(3);
      el.style.setProperty('--card', card.toFixed(3));
      // the core is z-index 3 and the near canvas 4: a tile on the near side is above both, one on the far side below both
      var ahead = zn > 0.02;
      if (el._front !== ahead) { el._front = ahead; el.style.zIndex = ahead ? 5 : 1; el.classList.toggle('is-front', ahead); }
    }

    // the glass of the near side: a soft highlight, so the core reads as inside the ball
    if (c.gloss > 0) {
      var hx = cx - edge * .36, hy = cy - edge * .46, gl = front.createRadialGradient(hx, hy, 0, hx, hy, edge * .8);
      gl.addColorStop(0, 'rgba(255,255,255,' + c.gloss + ')'); gl.addColorStop(1, 'rgba(255,255,255,0)');
      front.save(); front.beginPath(); front.arc(cx, cy, edge, 0, TAU); front.clip();
      front.fillStyle = gl; front.fillRect(cx - edge, cy - edge, edge * 2, edge * 2); front.restore();
    }
  }

  function tick(now) {
    raf = 0;
    var dt = last ? Math.min((now - last) / 1000, 0.05) : 0;   // first frame after a pause: no jump; slow frames: small steps
    last = now;
    if (!dragging) {
      g.vel += (SPIN * g.dir - g.vel) * (1 - Math.exp(-dt / 0.9));   // a fling fades into the idle turn
      g.yaw += g.vel * dt;
      g.pitch += (REST_PITCH - g.pitch) * (1 - Math.exp(-dt / 0.4)); // and the lean settles back
    }
    clock += dt;
    for (var i = 0; i < n; i++) {
      shown[i] += (g.alpha[i] - shown[i]) * (1 - Math.exp(-dt / 0.12));
      flow[i] += (g.pulse[i] - flow[i]) * (1 - Math.exp(-dt / 0.3));
    }
    if (opts.onFrame) opts.onFrame(dt);
    draw();
    schedule();
  }
  function schedule() {
    if (!raf && alive && visible && !document.hidden) raf = requestAnimationFrame(tick);
  }

  // drag: the surface under the pointer follows it one to one; letting go keeps the turn going
  function down(e) {
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    dragging = true; px0 = e.clientX; py0 = e.clientY; moveAt = e.timeStamp; fling = 0;
    try { stage.setPointerCapture(e.pointerId); } catch (err) {}
    stage.classList.add('is-grabbing');
  }
  function move(e) {
    if (!dragging) return;
    var dx = e.clientX - px0, dy = e.clientY - py0, dtm = (e.timeStamp - moveAt) / 1000;
    px0 = e.clientX; py0 = e.clientY; moveAt = e.timeStamp;
    g.yaw += dx / R;
    var next = g.pitch + dy / R;
    var over = next > PITCH_MAX ? next - PITCH_MAX : next < PITCH_MIN ? PITCH_MIN - next : 0;
    g.pitch += dy / R / (1 + over * 8);      // past the limit it resists instead of stopping dead
    if (dtm > 0) fling = fling * 0.6 + (dx / R / dtm) * 0.4;
  }
  function up(e) {
    if (!dragging) return;
    dragging = false;
    stage.classList.remove('is-grabbing');
    try { stage.releasePointerCapture(e.pointerId); } catch (err) {}
    if (e.timeStamp - moveAt > 90) fling = 0;   // held still before letting go: no throw
    g.vel = clamp(fling, -4.5, 4.5);
    if (Math.abs(g.vel) > 0.05) g.dir = g.vel > 0 ? 1 : -1;
  }

  var ro = 'ResizeObserver' in window ? new ResizeObserver(resize) : null;
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    visible = entries[entries.length - 1].isIntersecting; last = 0; schedule();
  }) : null;
  var mo = new MutationObserver(function () { paint = null; if (!raf) draw(); });
  function onVisibility() { last = 0; schedule(); }
  function destroy() {
    alive = false;
    if (raf) cancelAnimationFrame(raf);
    if (ro) ro.disconnect(); if (io) io.disconnect(); mo.disconnect();
    document.removeEventListener('visibilitychange', onVisibility);
    stage.removeEventListener('pointerdown', down); stage.removeEventListener('pointermove', move);
    stage.removeEventListener('pointerup', up); stage.removeEventListener('pointercancel', up);
    stage.classList.remove('is-globe', 'is-grabbing');
    [back, front].forEach(function (c2) { c2.setTransform(1, 0, 0, 1, 0, 0); c2.clearRect(0, 0, c2.canvas.width, c2.canvas.height); });
    nodes.forEach(function (el) { el.style.transform = ''; el.style.opacity = ''; el.style.zIndex = ''; el.style.removeProperty('--card'); el.classList.remove('is-front'); });
  }

  stage.classList.add('is-globe');
  stage.addEventListener('pointerdown', down); stage.addEventListener('pointermove', move);
  stage.addEventListener('pointerup', up); stage.addEventListener('pointercancel', up);
  document.addEventListener('visibilitychange', onVisibility);
  mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] });
  if (ro) ro.observe(stage); else window.addEventListener('resize', resize);
  if (io) io.observe(stage);
  resize();
  schedule();
  return g;
}
