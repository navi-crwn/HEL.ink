<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <b>Français</b> · <a href="README.ru.md">Русский</a></p>

# HEL.ink — HEL.ink · Hub de la famille HOP et HOP ID

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="HEL.ink · Hub de la famille HOP" width="100%"></picture></p>

<p align="center"><b>HEL.ink est le portail de comptes et le répertoire de la famille HOP. Connectez-vous une fois avec votre HOP ID et passez en toute fluidité entre LinkHop, BioHop, PixelHop, NoteHop et FileHop.</b></p>

<p align="center">
  <a href="https://hel.ink">hel.ink</a> ·
  <a href="https://hub.hel.ink">hub.hel.ink</a> ·
  <a href="#run">Auto-hébergement</a> ·
  <a href="#features">Fonctionnalités</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="#family">Famille HOP</a>
</p>

---

Installez en une commande (il vérifie d’abord votre ordinateur, pose quelques questions et peut aussi installer le hub HOP ID pour vous) :

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/HEL.ink/main/install.sh | bash
```

Depuis un clone : `./install.sh` · mise à jour : `./update.sh` · suppression : `./uninstall.sh`. `--yes` prend toutes les valeurs par défaut ; `--help` liste les options.

## Hub familial et portail de comptes

Portail central de comptes, authentification unique HOP ID et répertoire de la famille LinkHop, BioHop, PixelHop, NoteHop et FileHop.

<a id="features"></a>

## Fonctionnalités

- **Authentification unique (HOP ID)**: Une seule connexion pour toutes les applications HOP avec tickets signés et déconnexion en chaîne.
- **Lanceur d'applications unifié**: Passez de LinkHop à BioHop, PixelHop, NoteHop et FileHop en un clic depuis n'importe où.
- **Base de données partagée**: Une base MySQL ou SQLite stocke les identités avec des tables de migration dédiées par application.
- **Sécurité robuste des comptes**: Hachage Argon2id, 2FA TOTP, protection Cloudflare Turnstile et vérification des e-mails.
- **Surveillance des services en direct**: Vérifications de santé automatisées et suivi de la latence sans exposition de secrets.
- **Ressources centrales de la famille**: Logos officiels, polices et cartes 3D flottantes servis depuis hel.ink avec hachages SRI.
- **Jetons d'API avec permissions ciblées**: Générez des jetons pour la communication inter-applications et l'automatisation des développeurs.
- **Entièrement auto-hébergeable**: 100 % gratuit et open source. Lancez le hub seul ou hébergez tout l'écosystème sur un unique VPS.

<a id="run"></a>

## Hébergez votre propre hub

Le hub s'exécute sur une infrastructure PHP moderne standard.

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

## Foire aux questions

**Qu'est-ce que HOP ID ?**
HOP ID est le service central d'authentification de la famille HOP. Un seul compte ouvre l'accès à toutes les applications.

**Puis-je utiliser le hub sans les autres applications ?**
Oui. Le hub peut fonctionner de manière autonome comme fournisseur d'identité et annuaire.

**Comment fonctionne la déconnexion unique ?**
Vous déconnecter du hub détruit votre session locale et transmet un signal de déconnexion aux apps connectées.

**HEL.ink est-il open source ?**
Oui, HEL.ink et toutes les applications publiques de HOP sont publiées sous licence MIT.

<a id="family"></a>

## Auto-hébergement

<!-- self-hosting:start -->
Tout ce dont HEL.ink a besoin se trouve dans des dépôts publics : aucun dépôt privé, aucun jeton et aucun compte hel.ink. Les détails sont dans le guide de la famille : [docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md) (en anglais).

| Élément | Pour HEL.ink | Rôle |
| --- | --- | --- |
| [HEL.ink](https://github.com/navi-crwn/HEL.ink) (hub, HOP ID) | Ce dépôt | Comptes et connexion pour toutes les apps |
| [hop/auth](https://github.com/navi-crwn/hop-auth), [hop/ui](https://github.com/navi-crwn/hop-ui), [hop/safety](https://github.com/navi-crwn/hop-safety) | Fournis dans `packages/` | Code PHP partagé : connexion, interface, sécurité des liens |
| [Imperium](https://github.com/navi-crwn/Imperium) | Facultatif | Panneau d'administration de toute la famille ; nécessite le hub |
| Polices et tuiles de la famille | Rien à installer : chargés depuis `hel.ink/hop-assets` | Sans hel.ink : polices système, pas de tuiles flottantes |

- **Ordre d'installation** : d'abord ce hub, puis les apps, puis Imperium si vous le voulez. Enregistrez les apps avec `php artisan db:seed --class='Database\Seeders\HopAppsSeeder' --force`. [`install-family.sh`](install-family.sh) fait tout d'un coup.
- **Réglages minimaux** : `APP_URL`, `HUB_HOST`, `HOP_SSO_SECRET`, `DB_*`, `LINKHOP_URL` … `FILEHOP_URL` (une par app); avec Imperium: `HOP_CONTROL_URL`, `HOP_SSO_SECRET_IMPERIUM`.
- **Seul ou en famille** : le hub seul fournit les comptes, la connexion et le lanceur ; le README de chaque app indique ce dont elle a besoin en plus.
- **Sans Imperium** : le hub garde ses propres pages d'administration des comptes (`/admin`) ; Imperium ajoute le contenu, les signalements et l'état des apps.
<!-- self-hosting:end -->

## La famille HOP

HEL.ink est le hub d'une famille d'outils web ouverts qui respectent votre vie privée.

| Application | Adresse en ligne | Code | Ce qu'elle fait |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | ce dépôt | Portail de comptes, connexion HOP ID |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Raccourcisseur de liens et codes QR |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Pages de liens bio et thèmes |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Hébergement d'images et médias |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Notes privées chiffrées côté client |
| **FileHop** | `file.hel.ink` | [navi-crwn/FileHop](https://github.com/navi-crwn/FileHop) | Conversion de fichiers dans le navigateur, sans envoi |

## Journal des modifications

Les modifications sont listées dans [CHANGELOG.md](CHANGELOG.md).

<a id="security"></a>

## Aide et support

Vous avez trouvé un bogue ou avez besoin d'aide ? Ouvrez un ticket sur <a href="https://github.com/navi-crwn/HEL.ink/issues">GitHub Issues</a>ou soumettez une pull request.

---

<p align="center"><sub>HEL.ink · HOP ID · membre de la famille HOP · <a href="https://hel.ink">hel.ink</a></sub></p>
