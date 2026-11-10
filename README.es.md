<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <b>Español</b> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

# HEL.ink — HEL.ink · Hub de la familia HOP y HOP ID

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="HEL.ink · Hub de la familia HOP" width="100%"></picture></p>

<p align="center"><b>HEL.ink es el portal de cuentas y directorio para HOP. Inicia sesión una vez con tu HOP ID y muévete fácilmente entre LinkHop, BioHop, PixelHop, NoteHop, FileHop y PayHop.</b></p>

<p align="center">
  <a href="https://hel.ink">hel.ink</a> ·
  <a href="https://hub.hel.ink">hub.hel.ink</a> ·
  <a href="#run">Instálalo tú mismo</a> ·
  <a href="#features">Características</a> ·
  <a href="#faq">Preguntas frecuentes</a> ·
  <a href="#family">Familia HOP</a>
</p>

---

Instala con un solo comando (primero revisa tu equipo, hace unas pocas preguntas y puede instalar el hub HOP ID por ti):

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/HEL.ink/main/install.sh | bash
```

Desde un clon: `./install.sh` · actualizar: `./update.sh` · quitar: `./uninstall.sh`. Con `--yes` se usan todos los valores por defecto; `--help` lista las opciones.

## Hub de la familia y portal de cuentas

Portal central de cuentas, inicio de sesión único HOP ID y directorio para LinkHop, BioHop, PixelHop, NoteHop, FileHop y PayHop.

<a id="features"></a>

## Características

- **Inicio de sesión único (HOP ID)**: Un solo inicio de sesión para todas las apps HOP. Tickets firmados y cierre de sesión encadenado.
- **Lanzador unificado de aplicaciones**: Cambia entre LinkHop, BioHop, PixelHop, NoteHop, FileHop y PayHop con un solo clic.
- **Arquitectura de base de datos compartida**: Una base de datos MySQL o SQLite almacena identidades con tablas de migración dedicadas por app.
- **Seguridad de cuenta robusta**: Hashing Argon2id, autenticación 2FA TOTP, protección Turnstile y verificación de correo.
- **Puerta SSO, CSP y UI compartida**: todas las páginas llevan Content-Security-Policy (con nonce en las páginas de la app), el traspaso SSO va firmado y lo verifican las apps, y los diálogos y los menús de idioma y cuenta vienen del paquete compartido HopUI.
- **Un estándar de controles**: campos, selectores y botones comparten radio y altura (tokens `--hop-ctl-*`), texto de 16 px y objetivos táctiles de 44 px en el móvil, y un diseño estático con movimiento reducido.
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

## Autoalojamiento

<!-- self-hosting:start -->
Todo lo que HEL.ink necesita está en repositorios públicos: ningún repositorio privado, ningún token y ninguna cuenta en hel.ink. Los detalles están en la guía de la familia: [docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md) (en inglés).

| Pieza | Para HEL.ink | Qué hace |
| --- | --- | --- |
| [HEL.ink](https://github.com/navi-crwn/HEL.ink) (hub, HOP ID) | Este repositorio | Cuentas e inicio de sesión para todas las apps |
| [hop/auth](https://github.com/navi-crwn/hop-auth), [hop/ui](https://github.com/navi-crwn/hop-ui), [hop/safety](https://github.com/navi-crwn/hop-safety) | Incluidos en `packages/` | Código PHP compartido: inicio de sesión, interfaz, seguridad de enlaces |
| [Imperium](https://github.com/navi-crwn/Imperium) | Opcional | Panel de administración de toda la familia; necesita el hub |
| Fuentes y mosaicos de la familia | Nada que instalar: se cargan desde `hel.ink/hop-assets` | Sin hel.ink: fuentes del sistema, sin mosaicos flotantes |

- **Orden de instalación**: primero este hub, luego las apps y, si lo quieres, Imperium. Registra las apps con `php artisan db:seed --class='Database\Seeders\HopAppsSeeder' --force`. [`install-family.sh`](install-family.sh) lo hace todo de una vez.
- **Ajustes mínimos**: `APP_URL`, `HUB_HOST`, `HOP_SSO_SECRET`, `DB_*`, `LINKHOP_URL` … `PAYHOP_URL` (uno por app); con Imperium: `HOP_CONTROL_URL`, `HOP_SSO_SECRET_IMPERIUM`.
- **Solo o en la familia**: el hub solo ofrece cuentas, inicio de sesión y el lanzador; el README de cada app indica qué más necesita.
- **Sin Imperium**: el hub conserva sus propias páginas de administración de cuentas (`/admin`); Imperium añade el contenido, los reportes y el estado de las apps.
<!-- self-hosting:end -->

## La familia HOP

HEL.ink es el núcleo de una familia de herramientas web abiertas que respetan tu privacidad.

| Aplicación | Dirección en vivo | Código | Qué hace |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | este repositorio | Portal de cuentas, acceso HOP ID |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Acortador de URL y códigos QR |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Páginas de link-in-bio y temas |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Alojamiento de imágenes y archivos |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Notas privadas con cifrado en el cliente |
| **FileHop** | `file.hel.ink` | [navi-crwn/FileHop](https://github.com/navi-crwn/FileHop) | Conversión de archivos en el navegador, sin subirlos |
| **PayHop** | `pay.hel.ink` | [navi-crwn/PayHop](https://github.com/navi-crwn/PayHop) | Crea un QR de pago y divide cuentas; nada sale de tu navegador. |

## Registro de cambios

Los cambios se enumeran en [CHANGELOG.md](CHANGELOG.md).

<a id="security"></a>

## Ayuda y soporte

¿Encontraste un error o necesitas ayuda? Abre una incidencia en <a href="https://github.com/navi-crwn/HEL.ink/issues">GitHub Issues</a>o envía un pull request.

---

<p align="center"><sub>HEL.ink · HOP ID · parte de la familia HOP · <a href="https://hel.ink">hel.ink</a></sub></p>
