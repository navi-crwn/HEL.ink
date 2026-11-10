<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <b>Русский</b></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="HEL.ink: one HOP account for every app" width="100%"></picture></p>

<p align="center"><b>Один аккаунт HOP для всех приложений. Войдите один раз и свободно переходите между ними.</b></p>

---

HEL.ink — главная страница и портал аккаунтов семейства HOP: веб-приложений, уважающих приватность, которые можно развернуть у себя. В репозитории — публичная главная страница, портал HOP ID (вход, двухфакторная защита, сброс пароля) и общие пакеты hop-auth, hop-ui и hop-safety.

## Установка

Установка одной командой (сначала проверяет ваш компьютер, задаёт несколько вопросов и может сам установить хаб HOP ID):

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/HEL.ink/main/install.sh | bash
```

Из клона: `./install.sh` · обновление: `./update.sh` · удаление: `./uninstall.sh`. С `--yes` берутся все значения по умолчанию; `--help` показывает параметры.

## Журнал изменений

Изменения перечислены в [CHANGELOG.md](CHANGELOG.md).

## Собственный хостинг

<!-- self-hosting:start -->
Всё, что нужно HEL.ink, лежит в публичных репозиториях: не нужны ни приватный репозиторий, ни токен, ни учётная запись на hel.ink. Подробности — в руководстве по семейству: [docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md) (на английском).

| Компонент | Для HEL.ink | Зачем |
| --- | --- | --- |
| [HEL.ink](https://github.com/navi-crwn/HEL.ink) (хаб, HOP ID) | Этот репозиторий | Учётные записи и вход для всех приложений |
| [hop/auth](https://github.com/navi-crwn/hop-auth), [hop/ui](https://github.com/navi-crwn/hop-ui), [hop/safety](https://github.com/navi-crwn/hop-safety) | Уже лежат в `packages/` | Общий PHP-код: вход, интерфейс, безопасность ссылок |
| [Imperium](https://github.com/navi-crwn/Imperium) | Необязательно | Панель администратора для всего семейства; нужен хаб |
| Шрифты и плитки семейства | Ничего устанавливать не нужно: загружаются с `hel.ink/hop-assets` | Без hel.ink: системные шрифты, без плавающих плиток |

- **Порядок установки**: сначала этот хаб, затем приложения, затем при желании Imperium. Приложения регистрирует `php artisan db:seed --class='Database\Seeders\HopAppsSeeder' --force`. [`install-family.sh`](install-family.sh) делает всё за один раз.
- **Минимальные настройки**: `APP_URL`, `HUB_HOST`, `HOP_SSO_SECRET`, `DB_*`, `LINKHOP_URL` … `PAYHOP_URL` (по одному на приложение); с Imperium: `HOP_CONTROL_URL`, `HOP_SSO_SECRET_IMPERIUM`.
- **Отдельно или в семействе**: хаб сам по себе даёт учётные записи, вход и лаунчер; что ещё нужно каждому приложению, написано в его README.
- **Без Imperium**: у хаба остаются свои страницы администрирования учётных записей (`/admin`); Imperium добавляет контент, жалобы и состояние приложений.
<!-- self-hosting:end -->

## Возможности

- **SSO-шлюз, CSP и общий интерфейс**: каждая страница отдаётся с Content-Security-Policy (на страницах приложения — с nonce), передача SSO подписана и проверяется приложениями, а диалоги и меню языка и аккаунта берутся из общего пакета HopUI.
- **Единый стандарт элементов управления**: поля ввода, списки и кнопки используют один радиус и одну высоту (токены `--hop-ctl-*`), текст 16 px и области касания 44 px на телефоне, а при уменьшенном движении — статичная вёрстка.

## Подробнее

Полная документация (возможности, настройки, безопасность) — на английском в [README](README.md).

Лицензия: MIT.

## Семейство HOP

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
| **PayHop** | [pay.hel.ink](https://pay.hel.ink) | [navi-crwn/PayHop](https://github.com/navi-crwn/PayHop) | Make a payment QR and split a bill; nothing leaves your browser. |
| **Imperium** | not public-facing: optional, for admins | [navi-crwn/Imperium](https://github.com/navi-crwn/Imperium) | The HOP control panel. |
<!-- hop-family:end -->
