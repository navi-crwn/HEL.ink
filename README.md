# HEL.ink — the HOP family

**Hop Easy Link.** hel.ink is the home of HOP, a small family of free, open-source web apps that share one account (HOP ID), one database and one home page. This repository is the **hub**: the public home page on `hel.ink` and the account portal on `hub.hel.ink`, plus the shared Composer packages and the deployment notes for running the whole family on one server.

| App | Address | Repo | What it does |
| --- | --- | --- | --- |
| **Hub** (this repo) | hel.ink · hub.hel.ink | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | Home page, HOP ID accounts, SSO issuer, launcher, announcements, status page, watchdog, admin |
| **LinkHop** | link.hel.ink · public `hel.ink/l/{slug}` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Short links, QR codes, click analytics, API |
| **BioHop** | bio.hel.ink · public `hel.ink/b/{slug}` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Link-in-bio pages with blocks, themes and stats |
| **PixelHop** | p.hel.ink (soon `pixel.hel.ink`) | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Image hosting with albums and direct links; signs in with HOP ID |
| **NoteHop** | note.hel.ink | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Zero-knowledge client-encrypted live notes & docs |

Imperium / citadel (the owner's admin console over Tailscale) is not part of the public launcher.

## Kegunaan, kelebihan, kekurangan

- **Hub**: satu akun untuk semua app (password, Google, 2FA, Turnstile, verifikasi email), launcher, pengumuman, status layanan, watchdog, admin pengguna, token API. Kelebihan: auth ditulis sekali, suspensi dan hapus akun berlaku di semua app seketika. Kekurangan: satu titik masuk; kalau hub mati, orang tidak bisa login ke app lain (sesi yang sudah ada tetap jalan).
- **LinkHop**: pemendek URL dengan slug kustom, QR, kedaluwarsa, password, analitik klik, folder, tag, API. Kelebihan: ringan, redirect cepat, ada deteksi URL berbahaya (`hop/safety`). Kekurangan: analitik dasar, tanpa domain kustom.
- **BioHop**: halaman link-in-bio dengan blok, tema, statistik, pembuatan short link lewat API LinkHop. Kelebihan: namespace sendiri (`/b/`), tema dan upload terpisah. Kekurangan: masih baru; fitur lanjutan menyusul.
- **PixelHop**: hosting gambar dengan album. Kelebihan: sudah berjalan dan memakai SSO HOP. Kekurangan: bukan Laravel, jadi tidak memakai paket `hop/*`.
- **NoteHop**: catatan terenkripsi langsung di browser (zero-knowledge), tautan berhak akses, kolaborasi real-time. Kelebihan: server tidak bisa membaca isi catatan. Kekurangan: jika link hilang, catatan tidak bisa dibuka kembali.

## Menjalankan hub secara lokal

Butuh PHP 8.3, Composer, Node 22.

```bash
git clone git@github.com:navi-crwn/HEL.ink.git && cd HEL.ink
composer install
cp .env.example .env && php artisan key:generate
# dev: biarkan HUB_HOST dan HUB_PUBLIC_HOST = localhost, lalu pilih database
```

**Dengan database bersama LinkHop** (yang direkomendasikan, supaya akun yang sama terlihat di kedua app): isi `DB_*` di `.env` dengan database yang dipakai LinkHop, lalu

```bash
php artisan migrate      # hanya menyentuh tabel milik hub, dicatat di migrations_hub
```

**Tanpa LinkHop** (coba-coba): `DB_CONNECTION=sqlite`, `touch database/database.sqlite`, lalu `php artisan migrate`.

```bash
npm install && npm run build   # atau npm run dev
php artisan serve              # http://localhost:8000
php artisan hop:owner you@example.com   # jadikan akun pertama pemilik (atau isi SUPERADMIN_EMAIL)
php artisan hop:watch          # jalankan watchdog sekali; scheduler menjalankannya tiap menit
```

Dengan kedua host `localhost`, semua route (beranda, marketing, akun) dilayani di satu host. Di produksi `HUB_PUBLIC_HOST=hel.ink` dan `HUB_HOST=hub.hel.ink` memisahkannya lewat `Route::domain`.

Test dan gaya kode (sama dengan CI):

```bash
php artisan test && vendor/bin/pint --test
```

## Database bersama: aturan kepemilikan tabel

Semua app HOP memakai **satu database**. Setiap repo hanya menjalankan migration miliknya dan memakai tabel pencatat sendiri (`database.migrations.table`), sehingga `migrate:rollback` di satu app tidak menyentuh app lain.

| Tabel | Pemilik migration | Tabel pencatat |
| --- | --- | --- |
| `users`, `roles`, `username_history`, `login_histories`, `activity_logs`, `api_tokens` (+ kolom `scopes`), `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`, `announcements`, `service_checks` | **hub** (repo ini) | `migrations_hub` |
| `links`, `link_clicks`, `folders`, `tags`, `link_tag`, `comments`, `retired_slugs`, `seo_settings`, `domain_blacklist`, `ip_bans`, `ip_watchlist`, `abuse_*`, `apps`, `hop_audit`, `hop_user_notes` | **LinkHop** | `migrations` (lama) / `migrations_link` |
| `bio_pages`, `bio_links`, `bio_clicks` | **BioHop** | `migrations_bio` |

Aturan:

- Migration hub **idempoten**: setiap tabel dibuat hanya kalau belum ada (`Schema::hasTable`) dan kolom ditambah hanya kalau belum ada (`Schema::hasColumn`), karena di produksi tabel akun sudah dibuat LinkHop. Tidak ada `down()` yang menghapus tabel bersama.
- Hanya hub yang **menulis** ke `users` (profil, status, hapus akun). App lain membacanya dan login lewat tiket SSO (`Auth::loginUsingId`).
- Hapus akun di hub menghapus baris milik hub lalu `users`; baris app lain ikut lewat foreign key `user_id` (cascade). File milik app (QR, upload bio) dibersihkan app masing-masing.
- `domain_blacklist` milik LinkHop tetapi dibaca semua app lewat `hop/safety`.
- Token API dibuat dan dicabut di hub (kolom `scopes`: `link:*`, `bio:read`, …); setiap app memvalidasinya dari tabel yang sama lewat middleware `hop.token` dari `hop/auth`.

## SSO (HOP ID)

Hub menerbitkan tiket untuk setiap app di `config/hop.php` (`linkhop`, `biohop`, `pixelhop`):

```
GET https://hub.hel.ink/sso/{app}?state={16-128 karakter}&return_to={callback app}
→ 302 {callback}?ticket=base64url(json).base64url(HMAC-SHA256(secret, json))
```

Payload: profil HOP (`sub`, `username`, `name`, `email`, `avatar`, `roles`, `status`, `verified`, `mfa`, `profile_version`, …) + `aud`, `admin`, `state`, `iat`, `exp` (120 detik), `jti`, `v: 2`. Formatnya sama persis dengan yang sudah diverifikasi PixelHop, jadi PixelHop cukup mengganti alamat penerbit dari `hel.ink` ke `hub.hel.ink`. Secret: `HOP_SSO_SECRET` bersama, atau per app (`HOP_SSO_SECRET_LINKHOP`, …).

Logout di hub (`POST /logout`) berantai: hub → `{app}/auth/sso/logout?next=…` untuk setiap app yang punya endpoint logout → kembali ke beranda.

`GET /sso/{app}/google` adalah tombol "Lanjutkan dengan Google" di app: tamu dibuatkan akun lewat Google lalu dikembalikan ke app.

## Paket bersama (`packages/`)

Dipakai lewat Composer *path repository* (`"hop/ui": "@dev"`, dst.). Nanti bisa dipindah ke repo sendiri tanpa mengubah kode.

| Paket | Namespace | Isi |
| --- | --- | --- |
| `hop/ui` | `Hop\Ui` | `<x-hop::pill />` (pil pindah app), `<x-hop::nav-items />` (item sidebar), `<x-hop::badge />`; membaca `config('hop.apps')` dan `config('hop.current')` |
| `hop/auth` | `Hop\Auth` | Klien SSO untuk app konsumen: route `/auth/sso`, `/auth/sso/callback`, `/auth/sso/logout`, verifikasi tiket (`Ticket::verify`), middleware `hop.auth` (tamu dikirim ke hub) dan `hop.token[:scope]` (token API dari tabel bersama), model `ApiToken` dengan `scopes` |
| `hop/safety` | `Hop\Safety` | `LinkSafety` (skor URL, blacklist domain, feed ancaman, Safe Browsing opsional), `UrlGuard` (tolak host privat), `SafeContent` (sanitasi HTML/teks), perintah `abuse:feeds`; config `safety.php` |

Memasang di app konsumen (LinkHop, BioHop): tambahkan path repository ke `packages/` repo ini (atau salin foldernya), `composer require hop/auth hop/ui hop/safety`, salin `config/hop.php` dengan `'current' => 'linkhop'`, isi `HOP_APP`, `HOP_HUB_URL`, `HOP_SSO_SECRET`. Route yang dilindungi memakai `->middleware('hop.auth')`; API memakai `->middleware('hop.token:link:create')`.

## Watchdog

`php artisan hop:watch` (terjadwal tiap menit lewat `schedule:run`) memanggil `/up` setiap app dan URL uji fungsional opsional (`LINKHOP_CHECK_URL`, …), menyimpan hasilnya ke `service_checks`, menampilkannya di `hel.ink/status`, `hel.ink/status.json`, beranda dan launcher, dan mengirim email (`WATCH_ALERT_EMAIL`) serta Telegram (`TELEGRAM_BOT_TOKEN` + `TELEGRAM_CHAT_ID`) setelah tiga kegagalan berturut, plus satu pesan saat pulih. Pastikan cron `* * * * * php /var/www/helink/hub/artisan schedule:run` ada.

## Pasang di satu server dengan nginx router

Semua app di satu mesin, Cloudflare gratis di depan (DNS, TLS, WAF, Turnstile), nginx membagi berdasarkan host dan prefix:

```
hel.ink      /l/*  → LinkHop      /b/*  → BioHop      /api/* → 308 link.hel.ink/api (cabut setelah 27 Nov 2026)
             /{slug} satu segmen → 301 /l/{slug}      (cabut setelah 27 Nov 2026)
             sisanya → hub
hub.hel.ink  → hub       link.hel.ink → LinkHop       bio.hel.ink → BioHop       p.hel.ink → PixelHop
```

Contoh konfigurasi lengkap: [`deploy/nginx/hel.ink.conf`](deploy/nginx/hel.ink.conf) dan snippet [`deploy/nginx/snippets-hop-laravel.conf`](deploy/nginx/snippets-hop-laravel.conf). Tata letak folder: `/var/www/helink/hub`, `/var/www/helink/linkhop`, `/var/www/helink/biohop`. Sertifikat: origin certificate Cloudflare untuk `hel.ink` dan `*.hel.ink`.

Urutan pasang hub di server:

1. `git clone` ke `/var/www/helink/hub`, `composer install --no-dev -o`, `npm ci && npm run build`.
2. `.env` dari `.env.example`: `APP_URL=https://hub.hel.ink`, `HUB_HOST`, `HUB_PUBLIC_HOST`, `DB_*` yang sama dengan LinkHop, `HOP_SSO_SECRET`, Google, Turnstile, SMTP `@hel.ink`.
3. `php artisan key:generate`, `php artisan migrate --force` (hanya tabel hub), `php artisan hop:owner pemilik@hel.ink`.
4. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
5. nginx: pasang `hel.ink.conf`, `nginx -t`, `systemctl reload nginx`. Cron `schedule:run` tiap menit.
6. Google Cloud: redirect URI `https://hub.hel.ink/auth/google/callback`. Turnstile: tambah hostname `hub.hel.ink`.
7. PixelHop: ganti alamat penerbit SSO ke `https://hub.hel.ink`.

Rencana lengkap pemisahan (keputusan, fase, cutover, rollback): [`docs/planning/PLAN-pemisahan-aplikasi.md`](docs/planning/PLAN-pemisahan-aplikasi.md). Website github.io: folder [`docs/`](docs/).

## Struktur repo

```
app/              hub: auth (disalin dari LinkHop), SsoController (penerbit), LauncherController, StatusController,
                  AnnouncementController, Admin/*, Services/Watchdog, Console/Commands/HopWatch
config/hop.php    daftar app, host, secret SSO, watchdog, username terlarang
database/         migration idempoten tabel akun + announcements + service_checks (tabel pencatat migrations_hub)
packages/         hop/ui, hop/auth, hop/safety
deploy/nginx/     router nginx untuk satu server
docs/             website github.io (placeholder) dan docs/planning
```

## Lisensi

MIT.
