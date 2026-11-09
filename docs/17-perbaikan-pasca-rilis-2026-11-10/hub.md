# HEL.ink (hub): post-release fixes, 2026-11-10

PR: PR_URL · branch `fix/post-release-hub` · items 1, 2, 3, 5, 6 (4 is the hop-assets v6 scope).

## 1. Changelog snapshot
- Root cause: `scripts/build-changelog.py` read the checkout's local `origin/main` and tags without fetching. A family checkout that was not fetched gave a snapshot that stops at the last fetch (2026-11-04); `resources/changelog.json` was last committed on 2026-11-04, so the 2026-11-10 "regeneration" did not change it. PRs came live from `gh`, commits and tags from stale git.
- Fix: `history()` runs `git fetch --tags --prune origin` first. New `--check`: compares merged PRs on GitHub with the snapshot and fails when any are missing, except the newest per repo (normally the snapshot's own PR). Run at release: build with `--family <checkouts>`, then `--check`. Not wired into CI (needs `gh` auth for five repos and would always lag by the PR that fixes it).
- Snapshot regenerated from fresh clones: 1586 entries, newest 2026-11-10 (hub #60, LinkHop #115, BioHop #67).
- For the app sessions: `scripts/build-changelog.py` in BioHop is byte-identical to the hub's (diffed); LinkHop/other copies: same fix applies (add the fetch line, optionally `--check`). The Go apps' embedded copy is generated from the same output.
- Not covered: `PUBLIC` lists five apps; FileHop and Imperium are not aggregated (unchanged).

## 2, 3. Footer
- `packages/ui` `<x-hop::footer>`: new `brand` slot rendered as the first row inside the footer, below the top border, aligned with column 1; the language row is removed (`:languages` is accepted and ignored); copyright right-aligned from `sm`, centred on phones. Hub partial passes the HOP strip through the slot; `.coss-footer-brand` lost its own margins; coarse-pointer rule for the removed row deleted.
- The hub dashboard has no footer language row (checked layouts); nothing to remove there. Go apps' footers are not in this repo.
- Test: the old "language row lists nine languages" test is now "no language row".

## 5. Sign-out `return` (hub side)
- `GET`/`POST /logout` and `AuthenticatedSessionController::destroy` use `SsoController::returnTarget()`: `return` is honoured only if it is http(s), has no userinfo, backslash or whitespace, and its exact origin (scheme+host+port) is a configured app origin (`HopApps::origins()`: config apps and registry apps such as Imperium when registered with a URL). Otherwise `route('home')`. The target is the end of the existing chained logout. Parameter name `return`.
- Tests (`LogoutReturnTest`): accepted on GET and POST; other host, suffix lookalike, userinfo both ways, backslash, `//host`, relative, `javascript:`, ftp, wrong port, http downgrade all fall back.
- App sessions must send `{hub}/logout?return=<home URL>`. Imperium only works if it is a registered app with its URL.

## 6. Status failures
- `Watchdog::failures()`: per app count, last failure, error and a 24-cell hourly strip; view: status dot (current state), count in `tabular-nums`, relative time with full UTC date in the tooltip, strip with per-hour tooltips, last error line, empty state with one check icon. Four new strings in nine languages. No JS, no motion.
- Preview: `C:\Users\Red\Keep\HOP\HOP-Mac\preview-pasca-rilis-2026-11-10\hub\index.html` (1440 light/dark, 390 light; default and Clean; seeded sample data; headless Chromium on the VPS, not a real GPU). The preview was taken before the last-error line was added.

## Checks
- `php artisan test`: 604 passed, 1 skipped. Pint clean. `hop_assets.py check` OK. CI: see PR.
- Not tested: real browsers other than headless Chromium; Clean style on the status page looks the same as the default there; RTL not applicable; the footer was not visually previewed beyond the 390 px shots.
