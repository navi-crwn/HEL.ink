# Silent SSO check
An app that must confirm which HOP ID is signed in at this browser, without ever showing a sign-in page, sends the visitor to the normal start URL with one more parameter:

`GET https://<hub>/sso/{app}?state=<16-128 chars>&return_to=<the app's registered callback URL>&prompt=none`

- Hub session present (and two-factor done, account not suspended): exactly the normal flow, `302 <callback>?ticket=...`; the ticket carries the signed-in account, so the app compares it with its own session.
- No hub session, a suspended account, or a two-factor check still open: `302 <registered callback>?error=login_required&state=<same state>`. No page is shown, nothing is stored as the intended URL. The target is the callback registered for `{app}`, never a value from the request (a different `return_to` is refused with 400 as before); an unknown app is 404.
- Over the rate limit (30 per minute): `302 <registered callback>?error=temporarily_unavailable&state=<same state>`, still no page (without `prompt=none` the 429 page stays). Apps treat any `error` as "keep the current session, ask again later".
- Without `prompt=none` nothing changes: a guest is sent to sign in and returns to the ticket step.
