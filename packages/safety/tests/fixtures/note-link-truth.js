// Ground truth: what a WHATWG URL parser (Node's `URL`, same rules as browsers) makes of each variant,
// and whether NoteHop's note.js (new URLSearchParams(location.hash.slice(1)).get('k'|'a')) would read a secret.
const fs = require('fs');
const v = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const noteHosts = new Set(['note.hel.ink', 'n.hel.ink']);
const out = v.map(([label, url]) => {
  let host = null, secret = false, err = null;
  try {
    // the address as entered; schemeless forms get https:// the way LinkRouting::address does
    let u = url.replace(/[\t\n\r]/g, '').replace(/^[\u0000-\u0020]+|[\u0000-\u0020]+$/g, '');
    if (!/^[a-z][a-z0-9+.\-]*:/i.test(u.replace(/^[\u0000- ]+/, '')) || /^[^/:]+:\d+(\/|$)/.test(u)) u = 'https://' + u.replace(/^\/+/, '');
    const p = new URL(u);
    host = p.hostname;
    const frag = new URLSearchParams(p.hash.slice(1));
    secret = ['k', 'a'].some((n) => (frag.get(n) || '') !== '');
    if (!['http:', 'https:'].includes(p.protocol)) host = null;
  } catch (e) { err = e.code || 'invalid'; }
  return { label, host, secret, opens_note: !!(host && noteHosts.has(host.replace(/^www\./, '').replace(/\.$/, '')) && secret), err };
});
fs.writeFileSync(process.argv[3], JSON.stringify(out, null, 1));
for (const o of out) console.log([o.label, o.host, o.secret, o.opens_note, o.err].join('\t'));
