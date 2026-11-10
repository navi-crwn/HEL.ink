<!-- hop-family:start -->
<!-- Generated from HEL.ink resources/hop-assets/apps.source.json; edit there, then run scripts/hop_assets.py build. -->
| App | Address | Repository | What it does |
| --- | --- | --- | --- |
| **HOP ID** | [hel.ink](https://hel.ink) · accounts at `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | One account for every HOP app. |
| **LinkHop** | [link.hel.ink](https://link.hel.ink) · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Short links, QR codes and click analytics. |
| **BioHop** | [bio.hel.ink](https://bio.hel.ink) · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | One link-in-bio page for everything you do. |
| **PixelHop** | [pixel.hel.ink](https://pixel.hel.ink) · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Image hosting with albums and direct links. |
| **NoteHop** | [note.hel.ink](https://note.hel.ink) · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Encrypted notes you share with a link. |
| **FileHop** | [file.hel.ink](https://file.hel.ink) | [navi-crwn/FileHop](https://github.com/navi-crwn/FileHop) | Convert files in your browser; nothing is uploaded. |
| **Imperium** | not public-facing: optional, for admins | [navi-crwn/Imperium](https://github.com/navi-crwn/Imperium) | The HOP control panel. |
<!-- hop-family:end -->

## Silent SSO check (`prompt=none`)

An app that must confirm which HOP ID is signed in at this browser, without ever showing a sign-in page, sends the visitor to the normal start URL with one more parameter:

`GET https://<hub>/sso/{app}?state=<16-128 chars>&return_to=<the app's registered callback URL>&prompt=none`

- Hub session present (and two-factor done, account not suspended): exactly the normal flow, `302 <callback>?ticket=...`; the ticket carries the signed-in account, so the app compares it with its own session.
- No hub session, a suspended account, or a two-factor check still open: `302 <registered callback>?error=login_required&state=<same state>`. No page is shown, nothing is stored as the intended URL. The target is the callback registered for `{app}`, never a value from the request (a different `return_to` is refused with 400 as before); an unknown app is 404.
- Without `prompt=none` nothing changes: a guest is sent to sign in and returns to the ticket step.
