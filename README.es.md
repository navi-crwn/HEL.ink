<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh.md">简体中文</a> · <b>Español</b> · <a href="README.ar.md">العربية</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <a href="README.ja.md">日本語</a> · <a href="README.id.md">Bahasa Indonesia</a></p>

# HEL.ink — HEL.ink · Hub de la familia HOP y HOP ID

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="HEL.ink · Hub de la familia HOP" width="100%"></picture></p>

<p align="center"><b>HEL.ink es el portal de cuentas y directorio para HOP. Inicia sesión una vez con tu HOP ID y muévete fácilmente entre LinkHop, BioHop, PixelHop y NoteHop.</b></p>

<p align="center">
  <a href="https://hel.ink">hel.ink</a> ·
  <a href="https://hub.hel.ink">hub.hel.ink</a> ·
  <a href="#run">Instálalo tú mismo</a> ·
  <a href="#features">Características</a> ·
  <a href="#faq">Preguntas frecuentes</a> ·
  <a href="#family">Familia HOP</a>
</p>

---

## Hub de la familia y portal de cuentas

Portal central de cuentas, inicio de sesión único HOP ID y directorio para LinkHop, BioHop, PixelHop y NoteHop.

<a id="features"></a>

## Características

- **Inicio de sesión único (HOP ID)**: Un solo inicio de sesión para todas las apps HOP. Tickets firmados y cierre de sesión encadenado.
- **Lanzador unificado de aplicaciones**: Cambia entre LinkHop, BioHop, PixelHop y NoteHop con un solo clic.
- **Arquitectura de base de datos compartida**: Una base de datos MySQL o SQLite almacena identidades con tablas de migración dedicadas por app.
- **Seguridad de cuenta robusta**: Hashing Argon2id, autenticación 2FA TOTP, protección Turnstile y verificación de correo.
- **Monitor de salud en vivo**: Verificaciones de salud automáticas y métricas de latencia sin exponer credenciales.
- **Activos centrales de la familia**: Marcas oficiales, fuentes familiares y mosaicos 3D servidos desde hel.ink con hashes SRI.
- **Tokens de API con permisos granulares**: Genera tokens con ámbitos definidos para la comunicación entre aplicaciones y automatización.
- **Completamente autohospedable**: 100% libre y de código abierto. Ejecuta el hub solo o todo el ecosistema en un único VPS.

<a id="run"></a>

## Instala tu propio hub

El hub funciona sobre una infraestructura PHP moderna estándar.

```bash
git clone https://github.com/navi-crwn/HEL.ink.git
cd HEL.ink
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

<a id="faq"></a>

## Preguntas frecuentes

**¿Qué es HOP ID?**
HOP ID es el servicio de autenticación central para la familia HOP. Una cuenta sirve para todas las apps.

**¿Puedo usar el hub sin las otras apps?**
Sí. El hub puede funcionar de forma independiente como proveedor de cuentas y directorio.

**¿Cómo funciona el cierre de sesión único?**
Cerrar sesión en el hub destruye tu sesión local y envía señales de cierre encadenadas a las apps conectadas.

**¿Es HEL.ink de código abierto?**
Sí, HEL.ink y todas las apps públicas de HOP tienen licencia MIT.

<a id="family"></a>

## La familia HOP

HEL.ink es el núcleo de una familia de herramientas web abiertas que respetan tu privacidad.

| Aplicación | Dirección en vivo | Código | Qué hace |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | este repositorio | Portal de cuentas, acceso HOP ID |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Acortador de URL y códigos QR |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Páginas de link-in-bio y temas |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Alojamiento de imágenes y archivos |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Notas privadas con cifrado en el cliente |

<a id="security"></a>

## Ayuda y soporte

¿Encontraste un error o necesitas ayuda? Abre una incidencia en <a href="https://github.com/navi-crwn/HEL.ink/issues">GitHub Issues</a>o envía un pull request.

---

<p align="center"><sub>HEL.ink · HOP ID · parte de la familia HOP · <a href="https://hel.ink">hel.ink</a></sub></p>
