# disposable_email_blocklist.conf

A snapshot of the list of throwaway e-mail domains that the hub refuses when an address is set (sign-up, the apps' sign-up, a changed e-mail address, a new Google sign-up).

- Source: https://github.com/disposable-email-domains/disposable-email-domains, file `disposable_email_blocklist.conf`
- Taken from commit `206a1b0a0546421df4db204ea66ce7ac146b371b` (last change of that file: 2026-11-08), copied on 2026-11-12
- License: CC0 1.0 Universal (public domain dedication), see `LICENSE.txt` of that repository. It may be copied and changed without conditions.

The file is the data as published: one domain per line, lower case. The hub reads it from this repository, never from the network while someone signs up.

Refreshing: the server fetches the same file weekly (`hop:email-blocklist:refresh`, scheduled on Mondays) and keeps its own copy in `storage/framework/`. To refresh this snapshot before a release run `php artisan hop:email-blocklist:refresh --snapshot`, then update the commit and the dates above and commit both files.
