# Changelog

All notable changes to HEL.ink (the HOP hub and HOP ID) are listed here, written for the people who use it. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) with one addition: every release has a short title, and every item starts with the place it concerns in bold, then says what changed, what that means for you and why. The /changelog page of the site is built from this file by scripts/build-changelog.py (the comment at the top of that script describes the format); nothing on the page is copied from commit or pull request titles.

## [Unreleased]

## [1.3.3] - 2026-11-14 - Settings stay put, calmer changelog

### Fixed

- **Settings, API tokens.** Revoking or creating an API token, or entering a wrong value in that form, now leaves you on the API tokens tab with the result message, instead of throwing you back to the Account tab. Reported after a hand test: the page reloaded without remembering the tab.
- **Settings, two-factor.** A wrong password when turning two-factor on or off, or when renewing recovery codes, now keeps you on the two-factor tab and shows the message there. The same rule as for tokens was missing in these forms.
- **Settings, language.** Changing the language while on another tab of Settings no longer jumps to the Account tab, because the tab now stays in the address.
- **Settings, API tokens without scripts.** When scripts are blocked, a newly made token is now shown once on the page. Before, the token was made but never displayed, so it could not be copied.
- **Public pages for members.** For a signed-in person, the logo and the Back link on the announcements, status and changelog pages, the app list in the top bar and the Open buttons now lead to the dashboard instead of the public home page. Visitors who are not signed in still go to the public home page. Reason: members found themselves on the public site when they only wanted to go back.
- **Top bar on narrow phones.** The public top bar no longer grows past the screen on 375 px wide phones with long button words, and the dashboard title keeps more room next to the buttons.

### Changed

- **Changelog page.** The page now shows one card per release of each app, with a short title and a list of what changed, where, what it means for you and why, instead of a stream of commit and pull request titles. The text of the entries stays in English; the page labels are in all nine languages. Reason: the old list was hard to read and showed internal wording.
- **Changelog history.** Every earlier release of the hub was rewritten in the same plain form.

## [1.3.2] - 2026-11-14 - Public pages and cookies

### Fixed

- **Public pages in other languages.** Pages that follow the chosen language (home, about, privacy and the other information pages) now tell shared caches that the answer depends on the language cookie. Effect: a visitor no longer receives a page in somebody else's language from a cache. Files and the public numbers are unchanged, and signed-in pages are still never stored.
- **Cookies on a first visit.** A visit without a language cookie no longer receives clean-up cookies, so a plain page answer carries no HOP cookie at all. Choosing a language with the language link sets only the one cookie for all HOP sites. A browser that still holds the old cookie is cleaned up once, as before.

## [1.3.1] - 2026-11-12 - One theme and one language for the whole family

### Changed

- **API page and token form.** The page no longer claims that one token works at every HOP API. Hub tokens are for LinkHop; the form offers only what LinkHop accepts, and PixelHop, which has its own keys, is listed separately with where to make them and read about them. A new token can no longer carry a PixelHop permission; a token that already does keeps working. Reason: people tried hub tokens at PixelHop and got refused. Shown in all nine languages.
- **Theme and language.** The light or dark choice and the language are now one choice for the whole family: set it on hel.ink and it holds on hub.hel.ink and the other way round, and the page is drawn in the right theme from the first moment. An old saved choice is moved over once. Reason: choices made in one HOP app were not followed in the next.

### Security

- **Software update.** A library used for encryption was updated to fix a reported weakness that let a crafted key use up processor time. The security audit of dependencies is clean again.

### Added

- **Checks.** New automatic checks cover the token form, the API page, the cookie rules and the first-paint theme script, so these behaviours do not break again unnoticed.

## [1.3.0] - 2026-11-12 - Faster sign-in and sign-out, safer accounts

### Changed

- **Signing in.** Signing in no longer waits for outside services. The login is recorded at once and the place and proxy lookups for the history are done in the background, so a slow lookup cannot hold you up. Until then a new history row shows no place. Reason: a slow or unreachable lookup service made sign-in slow.
- **Signing out.** Signing out no longer walks your browser through every app. The hub tells the apps to end your session in the background; an app's session ends within a second or two. Reason: the chain of redirects was slow and could stop halfway when one app was down. If the background delivery fails, the apps notice within five minutes.
- **Account events.** An event that cannot be delivered to an app after all retries is now counted and shown to the administrators, without any personal data. Reason: failed deliveries used to go unnoticed.

### Added

- **Sign-up.** Throwaway e-mail addresses are refused wherever an address is set: sign-up, changing your e-mail address and a new Google sign-up. Existing accounts are not touched. Reason: many fake accounts came from such addresses.
- **Administration.** Imperium can now create, edit, publish, unpublish and delete announcements through the hub, and shows each account's API tokens (never their values) and ten recent logins. Reason: announcements could only be managed on the hub's own old pages.
- **Administration rules.** Suspending an account, deleting it or changing its roles now needs a named administrator, an administrator cannot suspend or delete their own account, and only an owner can change roles. Reason: these actions must always be traceable to a person.

### Security

- **Google sign-in.** An account that is already linked to a Google account signs in to that account only, and no sign-in can move a Google link from one account to another.
- **Accounts that never verified their e-mail.** When Google proves the address of such an account, the password of the earlier sign-up is replaced and its sessions, tokens and two-factor settings are removed, so nobody who registered an address first can keep access. A reset of the password by e-mail does the same. Reason: someone could register another person's address before they did.
- **Changing e-mail or password.** Changing your e-mail address needs your current password, tells the old address and ends your other sessions. Changing your password, turning two-factor off or setting a password after a Google sign-up also ends your other sessions. The session you are using stays. In the apps you may have to sign in again once.
- **Suspending an account.** Suspending now also ends the account's sessions in the hub and in the apps at once, and removes its API tokens.
- **Messages.** The notice about an e-mail change is in the account's language and shows the new address only in part.

## [1.2.0] - 2026-11-11 - PayHop joins the family

### Added

- **Family.** PayHop (pay.hel.ink) is the seventh HOP app. It has its own page, its card on the home page and dashboard, entries in the FAQ, help, privacy and terms texts, and appears in the family menus, in all nine languages. Its dashboard card shows no numbers because it only keeps payment methods for people who choose to save them.
- **Terms.** A new version of the terms (2026-11-11) names FileHop and PayHop. It is not required of anyone yet; the earlier text is unchanged.
- **Recovery.** The command to turn off two-factor for an account that lost its authenticator is now official, so support has a safe way back in for people who lost access. It leaves a record without any secret.
- **Imperium.** Accounts with a role get an Open Imperium button in the top bar, in nine languages.
- **Quiet sign-in check.** Apps can ask the hub whether you are still signed in without showing any page. Reason: apps need to notice a sign-out elsewhere without interrupting you.
- **Site map.** The hub now serves its site map, which answered not found before, so search engines can list the pages.

### Changed

- **Home page, One account.** The picture of how one account serves every app is now a globe you can turn, with each app on its surface. Without scripts or with reduced motion the flat version is shown as before.
- **Home page text.** The small numbered labels are gone, and the text no longer says how many apps there are, so adding an app does not make it wrong.
- **Dashboard.** Cards and panels have a clearer outline in light and dark, the app cards sit in equal columns, and the gaps are even. The Open button on the FileHop card goes to the FileHop dashboard instead of its public page. The FileHop card shows its numbers so it is as tall as the others.
- **Administration pages.** The hub no longer offers an Admin section: signing in always leads to the dashboard, and accounts with a role get one shortcut to Imperium. Reason: the hub is for people and Imperium for administrators.
- **Owner account security.** The owner account may now turn on two-factor, confirm it and renew its recovery codes for itself, because Imperium refuses an account without it. Turning it off stays blocked, and nobody else can touch the owner's two-factor.
- **Public pages.** The hop-assets logos and visuals were refreshed for all apps (a new look for the LinkHop and BioHop marks, one logo without text per app, each app name in its own typeface, scenes that finish drawing on the home page, and a more even layout of the boxes on the information pages). Reason: the family looked uneven.
- **Documentation.** The documentation site and the READMEs list seven apps in nine languages, and the install guides cover PayHop and FileHop.

### Fixed

- **Status page.** The last 24 hours of failures show one row per app with a dot, the count, the time of the last failure and a small strip of the day, and a calm message when there is none.
- **Sign-out.** The hub can send you back to the app you left after signing out, but only to an address that belongs to a HOP app; anything else leads to the hub home. Reason: signing out of an app used to end on the hub home page.
- **Footer.** The HOP strip is the first row, the language row is gone from the footer (the language menu is in the top bar), and the copyright line is right-aligned on desktop and centred on phones.
- **Changelog page.** The history had stopped at 4 November because it was built from stale data; it was rebuilt and a check now fails when it falls behind.

## [1.1.0] - 2026-11-10 - Shared dialogs and a cleaner dashboard

### Added

- **Dialogs.** Questions and warnings now appear as one shared HOP dialog in every app instead of the browser's own boxes, with a red button that names what will be deleted, keyboard use first and no motion under reduced motion. Reason: the browser boxes looked different everywhere and could be blocked.
- **Reminders.** Reminder cards on the dashboard can be hidden for a week.
- **Documentation.** A guide lists every place to change when a new app joins the family.
- **Translations.** The FAQ, help, contact, about, app pages and status texts that were still shown in English now appear in every language.

### Changed

- **Controls.** Buttons, inputs and menus follow one shape and size across the dashboard, Settings, sign-in and administration pages, with text of 16 px and touch targets of 44 px on phones. Reason: controls looked different from page to page and were small on phones.
- **Look.** A calmer, flat look is available as an optional Clean style in Settings; the HOP look stays the default.
- **Dashboard.** The top bar title and the Deleted badge are translated, links that open a new tab show an arrow, the logo opens the HOP ID dashboard and the sidebar lists the HOP family.
- **FileHop.** FileHop gets its own pixel logo and amber colours, appears in the home page diagram (it was missing), and is named in the privacy, terms and FAQ texts. The languages answer in the FAQ now lists the nine languages (it named eight, including Arabic).

### Fixed

- **Settings.** The shortcuts to the security, data and tokens tabs open the tab they name, and on phones the active tab is scrolled into view.
- **Apps list.** The hub reports LinkHop's abilities from its own record instead of a fixed list.
- **Sign-in history.** Hand-offs from an app are no longer counted as real sign-ins.
- **Google sign-in.** Starting Google sign-in while no terms are required failed with an error; it now works.
- **API page.** It names the real addresses and documentation of LinkHop and PixelHop and no longer links pages that do not exist at BioHop and NoteHop.
- **Status.** The status page answers while the database is down, with no numbers, as the home page already did.
- **Goodbye page.** It follows the visitor's language and speaks of HOP, not LinkHop.
- **Error pages.** The expired-page, too-many-requests and not-found notices follow the chosen language.
- **Odd requests.** Forged values sent as lists in the address or forms now count as missing instead of causing an error page.

### Security

- **Profile photos.** A photo above 50 megapixels is refused before it is opened, and uploads are limited to 20 a minute.

## [1.0.0] - 2026-11-04 - First stable release

The first stable release of the hub. Everything below existed before and from now on later releases build on it.

### Added

- **HOP ID.** One account for LinkHop, BioHop, PixelHop and NoteHop, with sign-up and sign-in by e-mail or Google, e-mail verification, password reset, two-factor with recovery codes, a bot check, a profile with a photo and API tokens.
- **Single sign-on.** Signing in at the hub signs you in to the apps, and signing out at the hub signs you out of them. The secrets that protect this can be changed without downtime.
- **Account events.** The hub tells each app when a profile changes, an account is suspended, restored or deleted, or sessions end. Deliveries are signed and retried. Reason: apps must follow the account without asking you again.
- **Administration for Imperium.** A signed interface lets the administration app see accounts, statistics and logs and act on accounts. Owner accounts and the configured main administrator are protected from these actions.
- **Public site.** A home page that tells the story of the family, one page per app, terms, privacy, FAQ, help, about, a status page that checks every app, live numbers from the project page, and a history page.
- **Dashboard.** A launcher for the family, a card with numbers per app, and the same frame as the apps: a sidebar with the HOP family and a top bar with the main action, language, theme and account.
- **Shared look.** Logos, fonts and the home page engine are served from the hub in fixed versions so every app looks the same.
- **Languages.** The interface, READMEs and documentation site are in English, Chinese, German, Indonesian, Japanese, Portuguese, Spanish, French and Russian. Arabic and right-to-left layout were removed.
- **Self-hosting.** Install and update scripts, a documentation site and a security policy let others run their own copy.
- **Terms.** New accounts can be asked to accept a versioned set of terms; it is off until the owner turns it on.

### Security

- **Signed-in pages.** They are never stored by the browser, so Back after signing out does not show them again.
- **Onboarding and tickets.** The pages after sign-up need a signed-in account, recovery codes are compared strictly, return addresses are checked, and the public status data carries only public fields.
- **Old records.** Sign-in history and activity records are deleted after 90 days; backup copies are kept for 14 days on the same server, and the privacy page says so in all nine languages.
- **Deleting an account.** It also removes the data the apps hold, and the hub checks that each app did.
- **Links.** Links to encrypted NoteHop notes (the key is in the part after the hash sign) are refused, and pages are fetched only from the addresses that were checked.
- **Pictures and fonts.** They come from the hub's own host, and flags are country codes, not pictures from another site.

### Changed

- **Audit fixes.** Error pages follow the visitor's language and the language cookie works when sessions are shared between hosts.

[Unreleased]: https://github.com/navi-crwn/HEL.ink/compare/v1.3.2...HEAD
