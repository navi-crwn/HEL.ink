# Silent SSO check
An app that must confirm which HOP ID is signed in at this browser, without ever showing a sign-in page, sends the visitor to the normal start URL with one more parameter:

`GET https://<hub>/sso/{app}?state=<16-128 chars>&return_to=<the app's registered callback URL>&prompt=none`

- Hub session present (and two-factor done, account not suspended): exactly the normal flow, `302 <callback>?ticket=...`; the ticket carries the signed-in account, so the app compares it with its own session.
- No hub session, a suspended account, or a two-factor check still open: `302 <registered callback>?error=login_required&state=<same state>`. No page is shown, nothing is stored as the intended URL. The target is the callback registered for `{app}`, never a value from the request (a different `return_to` is refused with 400 as before); an unknown app is 404.
- Over the rate limit (30 per minute): `302 <registered callback>?error=temporarily_unavailable&state=<same state>`, still no page (without `prompt=none` the 429 page stays). Apps treat any `error` as "keep the current session, ask again later".
- Without `prompt=none` nothing changes: a guest is sent to sign in and returns to the ticket step.

## Recent sign-in: `max_age` and `auth_time`
An app that is about to do something sensitive (reset a vault, delete data) can ask the hub for a sign-in that is recent:

`GET https://<hub>/sso/{app}?state=...&return_to=...&max_age=<seconds>`

- `max_age`: digits only, at most 86400 (a day), at least 10. Anything else is ignored. `prompt=login` is the same as `max_age=60` (with both, the smaller wins). The "Continue with Google" route `/sso/{app}/google` passes both on.
- The hub stamps `auth_time` (Unix seconds) in its session each time the person really signs in: password, Google, a new sign-up, and the second factor when that is the last step. Restoring a "remember me" cookie and handing the session on to an app do not touch it.
- The version 2 ticket carries `auth_time` (0 when unknown). The other keys are unchanged, so an app that never sends `max_age` is not affected.
- If the last sign-in is older than `max_age`, or has no stamp, the hub ends this browser's hub session (the "remember me" cookie of this browser too, but not the cookies on the person's other devices), shows the usual sign-in page and, once the person has signed in, continues with the same address, so the ticket follows at once. With `prompt=none` the answer is `302 <registered callback>?error=login_required&state=<same state>` and nothing is ended.
- The app must check the ticket itself: `auth_time > 0` and `now - auth_time <= max_age + 60`. The browser can drop `max_age` from the hub address, but it cannot forge the signed `auth_time`. A ticket from an older hub (`auth_time` 0 or missing) is a refusal, not a pass.
