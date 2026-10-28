# Arsip desain LinkHop

Semua aset desain yang dibuat untuk LinkHop selama hel.ink masih menjadi situs LinkHop. Isinya logo, homepage dengan lautan piksel 3D, mockup, dan Turnstile. Sejak LinkHop pindah ke `link.hel.ink` dan hel.ink menjadi hub, homepage dan halaman marketing sudah dihapus dari repo LinkHop (commit `9f7cf44`, "Fase C"). Folder ini menyimpannya supaya tidak hilang dan bisa dipakai lagi di hub atau app lain.

Versi lengkap terakhir sebelum dihapus ada di repo [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop), commit `a371f72`:

```bash
git show a371f72:resources/views/landing.blade.php
```

## Isi

| Folder | Isi |
| --- | --- |
| `logo/final/` | Logo final: wordmark "LinkHop" dengan **o** sebagai kepala kelinci dan pita panah (gaya Amazon) dari L ke "op"; mark kepala kelinci (gradien, gelap, putih); `favicon.png`, `logo.png`, `logo-dark.png`. |
| `logo/linkhop-logo.cjs` | Generator logo: menulis semua SVG, PNG dan `favicon.ico` (`node linkhop-logo.cjs <folder-output>`, butuh `@playwright/test`). |
| `logo/final-preview.png`, `logo/final-zoom.png` | Pratinjau logo final, termasuk ukuran kecil. |
| `logo/explorations/` | Semua opsi logo yang pernah diajukan (`logo2`–`logo4`, `word`–`word3`); masing-masing ada `sheet.png` untuk dilihat sekaligus. |
| `homepage/source/` | Kode homepage dan halaman marketing terakhir: `landing.blade.php`, `marketing-layout`, nav/footer, about/faq/privacy/terms dll., login/register, `wordmark`, `hop-float`, `app.css`, dan `hop-hero.js` (scene three.js: lautan piksel holografik, pulau naik dengan bangunan, kelinci voxel, URL terenkripsi di dalam laut, miniatur loop). |
| `homepage/video/` | Rekaman homepage (terang/gelap), miniatur loop, dan dua versi awal mockup lautan holografik. Dikompres ke 960 px. |
| `homepage/screenshots/` | Screenshot homepage desktop/mobile, terang/gelap, lautan piksel, miniatur. |
| `homepage/mockups/` | Mockup HTML yang bisa dibuka langsung di browser (three.js dimuat dari CDN): `holo` dan `holo2` (lautan holografik), `town` (peta kota kecil dan gaya satelit), `mock` (arah awal homepage dan logo saudara). |
| `turnstile/` | Empat opsi posisi Turnstile (A–D) dan hasil akhir: ukuran normal, di bawah tombol Shorten, di tengah pada mobile. |
| `research/` | Screenshot situs HEL.ink lama, PixelHop, NoteHop dan Amnesia yang dipakai sebagai referensi gaya. |

## Catatan teknis homepage

- Scene dijalankan oleh `start(root)` untuk setiap elemen `[data-hop-scene]`; `data-mini` memilih versi miniatur (satu kelinci memendekkan link panjang + password, lalu loop).
- Skenario: `hop`, `share`, `pass-ok`, `pass-fail`, `loop`. Tombol pill memakai `[data-hop-play]` dengan `aria-pressed`.
- Semua contoh link memakai domain `example.com` berbahasa Inggris.
- three.js versi 0.186.1, di-bundle oleh Vite sebagai entry terpisah (`resources/js/hop-hero.js`).
