// The "One account" stage as a globe: HOP ID at the core, every app on the surface, a dotted sphere
// that turns slowly and follows a drag. Plain canvas 2D for the sphere, the lines and the packet; the
// app tiles and the hub stay HTML (sharp marks, real text) and are placed from the same projection.
// No library. With reduced motion the caller does not start it at all: the plain ring (HTML and SVG) is the
// still picture, complete and readable.
//
//   const globe = createGlobe(stageElement, { onFrame(dt) {} });   // null without a 2D canvas
//   globe.beams[i] = 0..1      how far the line to app i is drawn
//   globe.packet = { i, t }    a packet on line i at 0..1, or null
//   globe.alpha[i] = 0..1      how present app i is (dim before it signs in, dim after it signs out)
//   globe.out = true           the sign-out colour
//   globe.draw(), globe.destroy()        destroy() gives the stage back to the plain ring

const TAU = Math.PI * 2;
const LAT = 40 * Math.PI / 180;      // the apps sit on two rings, 40 degrees above and below the equator, in turn
const REST_PITCH = 0.11;             // the globe leans a little towards the viewer
const PITCH_MIN = -0.42, PITCH_MAX = 0.62;
const CAM = 3.4;                     // camera distance in globe radii: a mild perspective
const SPIN = 0.14;                   // idle turn, radians per second
const RADIUS = 0.40;                 // globe radius as a share of the stage width

function clamp(v, a, b) { return v < a ? a : v > b ? b : v; }

export function createGlobe(stage, opts) {
  opts = opts || {};
  var canvas = stage.querySelector('canvas');
  if (!canvas || !canvas.getContext) return null;
  var ctx = canvas.getContext('2d');
  if (!ctx) return null;
  var nodes = Array.prototype.slice.call(stage.querySelectorAll('[data-hx-node]'));
  var n = nodes.length;

  // unit vectors: x right, y up, z towards the viewer
  var nodeVec = nodes.map(function (_, i) {
    var lon = i * TAU / n, lat = (i % 2 ? -1 : 1) * LAT;
    return [Math.cos(lat) * Math.sin(lon), Math.sin(lat), Math.cos(lat) * Math.cos(lon)];
  });
  var dots = [];
  for (var r = 1; r < 13; r++) {
    var lat = -Math.PI / 2 + r * Math.PI / 13, c = Math.cos(lat), count = Math.max(6, Math.round(34 * c));
    for (var k = 0; k < count; k++) {
      var lon = k * TAU / count + (r % 2 ? 0 : Math.PI / count);
      dots.push([c * Math.sin(lon), Math.sin(lat), c * Math.cos(lon)]);
    }
  }

  var orbit = [LAT, -LAT].map(function (lat) {
    var ring = [];
    for (var k = 0; k < 96; k++) { var lon = k * TAU / 96; ring.push([Math.cos(lat) * Math.sin(lon), Math.sin(lat), Math.cos(lat) * Math.cos(lon)]); }
    return ring;
  });

  var g = {
    yaw: 0.35, pitch: REST_PITCH, vel: SPIN, dir: 1,
    beams: nodes.map(function () { return 1; }),
    alpha: nodes.map(function () { return 1; }),
    packet: null, out: false,
    draw: draw, destroy: destroy
  };
  var shown = g.alpha.slice();           // alpha, eased
  var size = 0, dpr = 1, R = 0, cx = 0, cy = 0;
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
      pkt: v('--globe-pkt', '34,211,238'), body: v('--globe-body', '99,102,241'),
      bodyIn: +v('--globe-body-in', '.02'), bodyOut: +v('--globe-body-out', '.09'), limb: +v('--globe-limb', '.16'),
      dotMin: +v('--globe-dot-min', '.07'), dotMax: +v('--globe-dot-max', '.5'),
      ringMin: +v('--globe-ring-min', '.05'), ringMax: +v('--globe-ring-max', '.3'), core: +v('--globe-core', '.2')
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

  function resize() {
    var w = stage.clientWidth;
    if (!w) return;
    size = w; dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.round(size * dpr); canvas.height = Math.round(size * dpr);
    R = size * RADIUS; cx = size / 2; cy = size / 2;
    draw();
  }

  function draw() {
    if (!size || !alive) return;
    var c = colours(), i, p;
    sy = Math.sin(g.yaw); cyw = Math.cos(g.yaw); sp = Math.sin(g.pitch); cp = Math.cos(g.pitch);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, size, size);

    // the body of the globe: a soft ball with a thin edge (the outline of a sphere seen in perspective)
    var edge = R * CAM / Math.sqrt(CAM * CAM - 1);
    var body = ctx.createRadialGradient(cx - edge * .28, cy - edge * .34, edge * .05, cx, cy, edge);
    body.addColorStop(0, 'rgba(' + c.body + ',' + c.bodyIn + ')');
    body.addColorStop(1, 'rgba(' + c.body + ',' + c.bodyOut + ')');
    ctx.beginPath(); ctx.arc(cx, cy, edge, 0, TAU);
    ctx.fillStyle = body; ctx.fill();
    ctx.lineWidth = 1; ctx.strokeStyle = 'rgba(' + c.dot + ',' + c.limb + ')'; ctx.stroke();

    // the core: a soft light in the very middle, where HOP ID sits and every line starts
    var core = ctx.createRadialGradient(cx, cy, 0, cx, cy, R * .62);
    core.addColorStop(0, 'rgba(' + c.line + ',' + c.core + ')');
    core.addColorStop(1, 'rgba(' + c.line + ',0)');
    ctx.fillStyle = core; ctx.beginPath(); ctx.arc(cx, cy, R * .62, 0, TAU); ctx.fill();

    // the dots, far side faint and small, near side clear
    ctx.fillStyle = 'rgb(' + c.dot + ')';
    for (i = 0; i < dots.length; i++) {
      p = project(dots[i]);
      var depth = (p[2] + 1) / 2;
      ctx.globalAlpha = c.dotMin + (c.dotMax - c.dotMin) * depth * depth;
      ctx.beginPath(); ctx.arc(p[0], p[1], (0.7 + 0.75 * depth) * p[3] * (size / 520), 0, TAU); ctx.fill();
    }
    // the two rings the apps ride on
    ctx.lineWidth = 1; ctx.strokeStyle = 'rgb(' + c.dot + ')';
    for (i = 0; i < orbit.length; i++) {
      var ring = orbit[i], ax = 0, ay = 0, az = 0;
      for (var j = 0; j <= ring.length; j++) {
        p = project(ring[j % ring.length]);
        if (j) {
          ctx.globalAlpha = c.ringMin + (c.ringMax - c.ringMin) * ((az + p[2]) / 2 + 1) / 2;
          ctx.beginPath(); ctx.moveTo(ax, ay); ctx.lineTo(p[0], p[1]); ctx.stroke();
        }
        ax = p[0]; ay = p[1]; az = p[2];
      }
    }
    ctx.globalAlpha = 1;

    // the lines from the core to each app, the packet, and the tiles
    var tone = g.out ? c.gone : c.line;
    ctx.lineCap = 'round'; ctx.lineWidth = 1.6;
    for (i = 0; i < n; i++) {
      p = project(nodeVec[i]);
      var x = p[0], y = p[1], z = p[2], s = p[3], near = (z + 1) / 2, drawn = clamp(g.beams[i], 0, 1);
      if (drawn > 0) {
        var ex = cx + (x - cx) * drawn, ey = cy + (y - cy) * drawn;
        var line = ctx.createLinearGradient(cx, cy, x, y);
        line.addColorStop(0, 'rgba(' + tone + ',' + (0.12 + 0.2 * near) + ')');
        line.addColorStop(1, 'rgba(' + tone + ',' + (0.22 + 0.5 * near) + ')');
        ctx.strokeStyle = line; ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(ex, ey); ctx.stroke();
      }
      if (g.packet && g.packet.i === i) {
        var t = clamp(g.packet.t, 0, 1), qx = cx + (x - cx) * t, qy = cy + (y - cy) * t, pk = g.out ? c.gone : c.pkt;
        ctx.save();
        ctx.shadowColor = 'rgba(' + pk + ',.9)'; ctx.shadowBlur = 14;
        ctx.fillStyle = 'rgb(' + pk + ')'; ctx.beginPath(); ctx.arc(qx, qy, 4.5 + 1.5 * near, 0, TAU); ctx.fill();
        ctx.restore();
      }
      // near side: the whole tile; far side: it shrinks and fades to the mark alone (--card drives the frame and the text)
      var el = nodes[i], scale = 0.6 + 0.48 * near, card = clamp((z + 0.34) / 0.44, 0, 1);
      card = card * card * (3 - 2 * card);
      el.style.transform = 'translate(-50%,-50%) translate3d(' + (x - cx).toFixed(2) + 'px,' + (y - cy).toFixed(2) + 'px,0) scale(' + scale.toFixed(4) + ')';
      el.style.opacity = (shown[i] * (0.42 + 0.58 * near)).toFixed(3);
      el.style.setProperty('--card', card.toFixed(3));
      // the hub is z-index 3: a tile in front of the core is above it, one behind is below
      var front = z > 0.02;
      if (el._front !== front) { el._front = front; el.style.zIndex = front ? 4 : 1; el.classList.toggle('is-front', front); }
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
    for (var i = 0; i < n; i++) shown[i] += (g.alpha[i] - shown[i]) * (1 - Math.exp(-dt / 0.12));
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
    ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.clearRect(0, 0, canvas.width, canvas.height);
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
