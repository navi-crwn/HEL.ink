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
- **Минимальные настройки**: `APP_URL`, `HUB_HOST`, `HOP_SSO_SECRET`, `DB_*`, `LINKHOP_URL` … `FILEHOP_URL` (по одному на приложение); с Imperium: `HOP_CONTROL_URL`, `HOP_SSO_SECRET_IMPERIUM`.
- **Отдельно или в семействе**: хаб сам по себе даёт учётные записи, вход и лаунчер; что ещё нужно каждому приложению, написано в его README.
- **Без Imperium**: у хаба остаются свои страницы администрирования учётных записей (`/admin`); Imperium добавляет контент, жалобы и состояние приложений.
<!-- self-hosting:end -->

## Подробнее

Полная документация (возможности, настройки, безопасность) — на английском в [README](README.md).

Лицензия: MIT.
