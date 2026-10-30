# HOP family assets

One place for what every HOP app shows the same way: app logos and favicons, the
family typography, the floating 3D tiles, and the list of apps. They are served by the
hub from `https://hel.ink/hop-assets/`.

| Path | What | Cache |
|---|---|---|
| `/hop-assets/apps.json` | Manifest: version, asset URLs with SRI hashes, and every app (name, tagline, URL, colour, icons, public or not) | 5 minutes |
| `/hop-assets/v1/fonts.css` | Syne (display), Plus Jakarta Sans (body), JetBrains Mono (code), self-hosted woff2, plus `--hop-font-display`, `--hop-font-body`, `--hop-font-mono` | 1 year |
| `/hop-assets/v1/hop-float.css`, `hop-float.js` | The floating tiles; the script draws all six tiles itself | 1 year |
| `/hop-assets/v1/apps/<slug>/…` | Official marks, wordmarks, favicons and icons per app | 1 year |

Sources: `public/hop-assets/v1/` (the files) and `resources/hop-assets/apps.source.json`
(the app list). `python3 scripts/hop_assets.py build` writes `apps.json` with URLs and
sha384 hashes; `check` (run in CI) fails when the manifest is stale or a file is missing.

## Rules

- **A version folder never changes after deploy.** Browsers and Cloudflare keep `v1`
  for a year. To change a logo or a font, copy the folder to `v2`, edit there, point
  `apps.source.json` at `v2`, rebuild the manifest, then move each app to `v2`.
- **Identity:** BioHop is the purple–fuchsia document mark (owner decision 2026-10-30).
  The other apps use the marks from their own repositories, copied here unchanged.
- Fonts are SIL OFL 1.1 (`v1/fonts/LICENSE-*.txt`), from Fontsource 5.3.0.

## Serving (owner step, once)

nginx must send CORS and cache headers, or browsers refuse the fonts on other HOP
domains. Paste `deploy/nginx/hop-assets.conf` into the `server_name hel.ink` block of
the live config, before `location /`, then `sudo nginx -t && sudo systemctl reload nginx`.

## Using it in an app

```html
<link rel="stylesheet" href="https://hel.ink/hop-assets/v1/fonts.css" crossorigin>
<link rel="stylesheet" href="https://hel.ink/hop-assets/v1/hop-float.css" crossorigin>
<div data-hop-float></div>
<script src="https://hel.ink/hop-assets/v1/hop-float.js" integrity="sha384-…" crossorigin defer></script>
```

Take the `integrity` values from `apps.json`. CSP needs `https://hel.ink` in
`style-src`, `font-src`, `img-src` and, for the tiles, `script-src`. Laravel apps get
this through the `hop/ui` components; PixelHop and NoteHop read the manifest at start-up.
