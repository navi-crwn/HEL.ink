# HOP family assets

One place for what every HOP app shows the same way: app logos and favicons, the
family typography, the floating 3D tiles, and the list of apps. They are served by the
hub from `https://hel.ink/hop-assets/`.

| Path | What | Cache |
|---|---|---|
| `/hop-assets/apps.json` | Manifest: version, asset URLs with SRI hashes, and every app (name, tagline, URL, colour, icons, public or not) | 5 minutes |
| `/hop-assets/v9/fonts.css` | Syne (display), Plus Jakarta Sans (body), JetBrains Mono (code), self-hosted woff2, plus `--hop-font-display`, `--hop-font-body`, `--hop-font-mono` | 1 year |
| `/hop-assets/v9/hop-float.css`, `hop-float.js` | The floating tiles; the script draws all eight tiles itself | 1 year |
| `/hop-assets/v9/hop-ui.css`, `hop-ui.js` | `HopUI.alert/confirm/prompt` dialogs that replace the browser's own, and the "Hide, shows again next week" button on reminder cards (`[data-hop-remind]`) | 1 year |
| `/hop-assets/v9/apps/<slug>/…` | Official marks, wordmarks, favicons and icons per app | 1 year |

Sources: `public/hop-assets/v9/` (the files) and `resources/hop-assets/apps.source.json`
(the app list). `python3 scripts/hop_assets.py build` writes `apps.json` with URLs and
sha384 hashes; `check` (run in CI) fails when the manifest is stale or a file is missing.

## Versions

- **v1** (2026-10): the first shared folder.
- **v2**: the files that changed after v1 went live; every app mark animates.
- **v3** (2026-11-08): FileHop joins as the sixth public app. v3 is v2 copied unchanged plus
  FileHop's mark and icons (`apps/filehop/`), its hero art (`home/filehop-*.webp`, rendered
  from the v3 engine), a seventh floating tile in `hop-float.js`, a `filehop` preset in
  `hop-hero3d.js` and FileHop's demo in `hop-home.js`. v2 was not edited: browsers and
  Cloudflare keep it for a year, so adding an app needs a new folder. Every app moves to v3
  with its own pull request; until then it keeps working on v2.
- **v4** (2026-11-08): FileHop gets a pixel mark like NoteHop and PixelHop (owner decision
  2026-11-08): a document with a folded corner and a white arrow down, the same 8x8 map as
  its 3D preset, its floating tile and its PWA icons (`apps/filehop/`: `mark.svg`,
  `icon-192.png`, `icon-512.png`, and a new `apple-touch-icon.png`, all on #08090c). The map
  gets a longer arrow shaft (row 2 is `###ww##.`), so `hop-hero3d.js`, `hop-float.js` and
  `home/filehop-*.webp` change too. Everything else is v3 copied unchanged; v3 stays as it was.

- **v5** (2026-11-09): `hop-ui.js` and `hop-ui.css` join: dialogs (`HopUI.alert`, `HopUI.confirm`,
  `HopUI.prompt`, Promise-based, `<dialog>`, keyboard first, nine languages from `<html lang>`, a red
  button that names what is deleted, light or dark after the page, still under reduced motion) and
  reminder cards (`[data-hop-remind="key"]` gets a "Hide · Shows again next week" button that posts
  `{key}` to `data-hop-remind-url`; the app stores `dismissed_at` per account and shows the card again
  after seven days unless the task is done). Everything else is v4 copied unchanged; v4 stays as it was.
  Laravel apps include it with `<x-hop::ui />` (hop/ui); PixelHop and FileHop take `ui_css`/`ui_js`
  from the manifest.

- **v6** (2026-11-10): new LinkHop and BioHop marks (owner decision 2026-11-10; HOP ID is unchanged).
  LinkHop is candidate C, an 8x8 pixel rabbit (two ears over a ring-shaped face, same grid and gradient
  language as NoteHop, PixelHop and FileHop): `apps/linkhop/mark.svg`, `mark-white.svg`, `favicon.png`
  (256), `logo.png` (white mark) and `logo-dark.png` (tile). BioHop is a 9x9 pixel document (a rounded
  square with three white lines): `apps/biohop/mark.svg` and `wordmark.svg`. `hop-float.js` draws both
  as pixel paths (no more `<img>` for LinkHop) and `hop-hero3d.js` uses the 8x8 LinkHop map. `home/linkhop-*.webp`
  (the fallback image under the 3D canvas) is re-rendered from the v6 engine; the other hero images are v5 copies (BioHop's 3D map was already this pixel mark). Everything else is v5 copied unchanged; v5 stays as it was.

- **v7** (2026-11-10): one engine fix in `hop-hero3d.js`. The scheduler used to animate only the four most
  visible scenes (`.slice(0, 4)`), so a page with five family tiles (the LinkHop and BioHop homes) never drew
  the fifth and it never settled. Now every visible scene that has not settled keeps drawing until it has; the cap
  of four applies only to settled scenes that keep floating, so per-frame work stays bounded. Reduced motion still
  goes straight to the final shape.
  v7 also adds the shared bento (owner, Round 3): `hop-bento.css` (`bento_css` in the manifest) is a 6-column
  `.hop-bento` grid whose spans depend on the box count, so 2 to 9 boxes always fill whole rows (2: 4+2; 3: 3+3 / 6;
  4: 4+2 / 2+4; 5: 3+3 / 2+2+2; 6: 4+2 / 2+2+2 / 6; 7: 3+3 / 2+2+2 / 3+3; 8: 3+3 / 2+2+2 / 2+2+2;
  9: 3+3 / 2+2+2 / 2+2+2 / 6; `data-span="3|4|6"` overrides; tablet 2 columns, phone 1), and `hop-motion.js` gains,
  with its old API unchanged, the box reveal (40 ms stagger, once; `[data-rv-load]` for dashboards), the pointer
  spotlight (`data-spot`) and `is-live` on `[data-scene]` only while on screen; reduced motion shows the final state.
  Markup: `<div class="hop-bento"><article data-spot data-scene>…</article>…</div>`; the contract is documented at the
  top of `hop-bento.css`. Everything else is v6 copied unchanged; v6 stays as it was.

  v7 logo rule (owner, 2026-11-10): one logo, no text, for every app. The `wordmark` and `wordmark_white` files of
  LinkHop and BioHop are now the same pixel mark as `mark` (kept so existing keys do not break); never draw the app
  name inside an image. Footers and headers show the mark image plus the name as real page text next to it.
  App names in their own typeface (owner, 2026-11-10): `hop-brand.css` (`brand_css`) with ASCII-subset fonts in
  `fonts/brand/` and their OFL licences: LinkHop Pixelify Sans, BioHop Space Grotesk, PixelHop Silkscreen,
  NoteHop Caveat, FileHop Sora, HOP ID Syne (already in v7). Use `<span class="hop-name hop-name-linkhop">LinkHop</span>`
  next to the mark image; the name stays real text.

- **v9** (2026-11-11): PayHop joins the family (pay.hel.ink). A copy of v8 plus: `apps/payhop/` (mark A, a coin with a P cut
  out of it, `mark.svg`, `icon-192.png`, `icon-512.png`, `apple-touch-icon.png`); `MAPS.payhop` and `STOPS.payhop` in
  `hop-hero3d.js` (same 8x8 grid as the mark, teal); the `payhop` tile and `.hop-theme-7` (teal) in `hop-float.js/.css`
  (eight tiles now); the name font Unbounded 700, subset to the letters of "PayHop"
  (`fonts/brand/payhop-name.woff2`, `fonts/LICENSE-unbounded.txt`, `.hop-name-payhop` in `hop-brand.css`); the home art
  `home/payhop-{light,dark}.webp` (460x460, the same picture for both looks like FileHop's); and a `payhop` demo in
  `hop-home.js` (a bill split in the page). Everything else is byte-identical to v8.

- **v8** (2026-11-10, owner round 4): the bento is reworked and BioHop gets a real pixel mark. `hop-bento.css`: 60
  desktop columns (five tiles share one row from 1100 px), smaller boxes, no box alone on a row and none wider than
  half the row unless `data-span` asks; `data-tiles` for small boxes; tablet 6 columns from 900 px (tiles from
  641 px), 2 columns below, phone 1 column (tiles 2); media capped (`[data-media]` 160-200 px desktop, 120-140 px
  phone; scenes at most 140 px). The pattern tables per count 2 to 9 are at the top of the file. BioHop mark: an 8x8
  page with three lines and visibly stepped corners (`apps/biohop/mark.svg`, new `mark-white.svg`, `wordmark.svg` = mark),
  its 3D map in `hop-hero3d.js`, the tile in `hop-float.js` and `home/biohop-*.webp`. Everything else is v7 copied
  unchanged; v7 stays as it was.

FileHop sends `Cross-Origin-Embedder-Policy: require-corp`, so anything it loads from
hel.ink must carry `Cross-Origin-Resource-Policy: cross-origin` (already in
`deploy/nginx/hop-assets.conf`; check the live vhost has it before FileHop uses v3 or v4).

## Rules

- **A version folder never changes after deploy.** Browsers and Cloudflare keep it
  for a year. To change a logo or a font, or to add an app, copy the newest folder to the
  next version (v3 → v4), edit there, point `apps.source.json` at it, rebuild the manifest,
  then move each app to it.
- **Identity:** BioHop is the purple–fuchsia document mark (owner decision 2026-10-30).
  The other apps use the marks from their own repositories, copied here unchanged.
- Fonts are SIL OFL 1.1 (`v3/fonts/LICENSE-*.txt`), from Fontsource 5.3.0.

## Serving (owner step, once)

nginx must send CORS and cache headers, or browsers refuse the fonts on other HOP
domains. Paste `deploy/nginx/hop-assets.conf` into the `server_name hel.ink` block of
the live config, before `location /`, then `sudo nginx -t && sudo systemctl reload nginx`.

## Using it in an app

```html
<link rel="stylesheet" href="https://hel.ink/hop-assets/v9/fonts.css" crossorigin>
<link rel="stylesheet" href="https://hel.ink/hop-assets/v9/hop-float.css" crossorigin>
<div data-hop-float></div>
<script src="https://hel.ink/hop-assets/v9/hop-float.js" integrity="sha384-…" crossorigin defer></script>
<link rel="stylesheet" href="https://hel.ink/hop-assets/v9/hop-ui.css" crossorigin>
<script src="https://hel.ink/hop-assets/v9/hop-ui.js" integrity="sha384-…" crossorigin defer></script>
```

Dialogs, in place of `confirm()`:

```js
if (await HopUI.confirm({ title: 'Delete this folder?', message: '…', danger: true, subject: folder.name, anchor: event.currentTarget })) { … }
const name = await HopUI.prompt({ title: 'Rename tag', label: 'Name', value: tag.name }); // null when cancelled
await HopUI.alert({ title: 'Export ready', message: '…' });
```

A reminder card: `<section data-hop-remind="setup-2fa" data-hop-remind-url="/reminders/dismiss">…</section>`.
The app answers the POST by storing `dismissed_at` for that key and account, hides the card while
`dismissed_at` is younger than seven days, and drops it for good once the task is done.

Take the `integrity` values from `apps.json`. CSP needs `https://hel.ink` in
`style-src`, `font-src`, `img-src` and, for the tiles, `script-src`. Laravel apps get
this through the `hop/ui` components; PixelHop and NoteHop read the manifest at start-up.

## Profile photos

Avatars are uploaded at the hub and stored on its public disk (`storage/app/public/avatars`),
served by `hub.hel.ink/storage/avatars/…` (`php artisan storage:link` must have run, and
`deploy/nginx/hel.ink.conf` has a `/storage/` location for the hub). Every URL the hub sends
(`hop_profile.avatar` and `avatar_url` in the SSO ticket, `/api/hop/profile`,
`/api/hop/profiles`, the `profile.updated` event) is absolute, built by `App\Support\Avatar::url()`.
An app must use that URL as it is: never put a path in front of it and never resolve it against
its own host, because the apps run on other hosts (`link.`, `bio.`, `pixel.`, `note.`).
