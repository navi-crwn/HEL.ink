<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <b>Deutsch</b> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="HEL.ink: one HOP account for every app" width="100%"></picture></p>

<p align="center"><b>Ein HOP-Konto für jede App. Einmal anmelden und frei durch die Familie wechseln.</b></p>

---

HEL.ink ist die Startseite und das Kontoportal der HOP-Familie: datenschutzfreundliche Web-Apps, die du selbst betreiben kannst. Dieses Repository enthält die öffentliche Startseite, das Portal für HOP ID (Anmeldung, Zwei-Faktor-Schutz, Passwort-Reset) und die gemeinsamen Pakete hop-auth, hop-ui und hop-safety.

## Installation

Mit einem Befehl installieren (es prüft zuerst deinen Rechner, stellt ein paar Fragen und kann auch den HOP-ID-Hub für dich einrichten):

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/HEL.ink/main/install.sh | bash
```

Aus einem Klon: `./install.sh` · aktualisieren: `./update.sh` · entfernen: `./uninstall.sh`. Mit `--yes` gelten alle Standardwerte; `--help` zeigt die Optionen.

## Changelog

Änderungen stehen in [CHANGELOG.md](CHANGELOG.md).

## Selbst hosten

<!-- self-hosting:start -->
Alles, was HEL.ink braucht, liegt in öffentlichen Repositorys: kein privates Repository, kein Token und kein hel.ink-Konto. Details stehen in der Familienanleitung: [docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md) (Englisch).

| Baustein | Für HEL.ink | Wozu |
| --- | --- | --- |
| [HEL.ink](https://github.com/navi-crwn/HEL.ink) (Hub, HOP ID) | Dieses Repository | Konten und Anmeldung für alle Apps |
| [hop/auth](https://github.com/navi-crwn/hop-auth), [hop/ui](https://github.com/navi-crwn/hop-ui), [hop/safety](https://github.com/navi-crwn/hop-safety) | Liegen in `packages/` bei | Gemeinsamer PHP-Code: Anmeldung, Oberfläche, Link-Sicherheit |
| [Imperium](https://github.com/navi-crwn/Imperium) | Optional | Admin-Panel für die ganze Familie; braucht den Hub |
| Familien-Schriften und -Kacheln | Nichts zu installieren: kommen von `hel.ink/hop-assets` | Ohne hel.ink: Systemschriften, keine schwebenden Kacheln |

- **Reihenfolge**: zuerst dieser Hub, dann die Apps, dann bei Bedarf Imperium. Die Apps registriert `php artisan db:seed --class='Database\Seeders\HopAppsSeeder' --force`. [`install-family.sh`](install-family.sh) erledigt alles in einem Durchgang.
- **Mindest-Einstellungen**: `APP_URL`, `HUB_HOST`, `HOP_SSO_SECRET`, `DB_*`, `LINKHOP_URL` … `FILEHOP_URL` (je App einer); mit Imperium: `HOP_CONTROL_URL`, `HOP_SSO_SECRET_IMPERIUM`.
- **Allein oder in der Familie**: der Hub allein bietet Konten, Anmeldung und den Launcher; die README jeder App sagt, was sie sonst noch braucht.
- **Ohne Imperium**: der Hub behält seine eigenen Admin-Seiten für Konten (`/admin`); Imperium ergänzt Inhalte, Meldungen und Zustand der Apps.
<!-- self-hosting:end -->

## Mehr erfahren

Die vollständige Dokumentation (Funktionen, Einstellungen, Sicherheit) steht auf Englisch in der [README](README.md).

Lizenz: MIT.
