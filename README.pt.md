<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh.md">简体中文</a> · <a href="README.es.md">Español</a> · <a href="README.ar.md">العربية</a> · <b>Português</b> · <a href="README.fr.md">Français</a> · <a href="README.ja.md">日本語</a> · <a href="README.id.md">Bahasa Indonesia</a></p>

# HEL.ink — HEL.ink · Hub da família HOP e HOP ID

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="HEL.ink · Hub da família HOP" width="100%"></picture></p>

<p align="center"><b>O HEL.ink é o portal de contas e diretório da família para o HOP. Entre uma vez com seu HOP ID e navegue tranquilamente entre LinkHop, BioHop, PixelHop e NoteHop.</b></p>

<p align="center">
  <a href="https://hel.ink">hel.ink</a> ·
  <a href="https://hub.hel.ink">hub.hel.ink</a> ·
  <a href="#run">Hospede você mesmo</a> ·
  <a href="#features">Recursos</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="#family">Família HOP</a>
</p>

---

## Hub da família e portal de contas

Portal central de contas, logon único HOP ID e diretório da família para LinkHop, BioHop, PixelHop e NoteHop.

<a id="features"></a>

## Recursos

- **Logon Único (HOP ID)**: Um login cobre todos os aplicativos HOP. Tickets assinados e encerramento em cadeia.
- **Inicializador unificado de apps**: Alterne entre LinkHop, BioHop, PixelHop e NoteHop com um clique.
- **Arquitetura de banco compartilhado**: Um banco MySQL ou SQLite armazena identidades com tabelas de migração dedicadas por app.
- **Segurança robusta de conta**: Hashing Argon2id, autenticação em dois fatores TOTP, Turnstile e verificação de e-mail.
- **Monitor de integridade em tempo real**: Verificações automáticas de integridade e latência sem vazamento de segredos.
- **Recursos visuais centralizados**: Marcas oficiais, fontes e blocos flutuantes 3D servidos de hel.ink com hashes SRI.
- **Tokens de API com escopos granulares**: Crie tokens de serviço para comunicação entre apps e automações de desenvolvedores.
- **Totalmente auto-hospedável**: 100% gratuito e código aberto. Execute apenas o hub ou hospede todo o ecossistema em um único VPS.

<a id="run"></a>

## Execute seu próprio hub

O hub roda sobre infraestrutura PHP moderna padrão.

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

## Perguntas frequentes

**O que é o HOP ID?**
O HOP ID é o serviço central de autenticação da família HOP. Uma conta dá acesso a todos os apps.

**Posso rodar o hub sem os outros apps?**
Sim. O hub pode rodar de forma isolada, mas alcança seu potencial máximo com os apps HOP.

**Como funciona o logout único?**
Sair do hub encerra sua sessão local e dispara avisos de encerramento em cadeia aos apps conectados.

**O HEL.ink é código aberto?**
Sim, o HEL.ink e todos os apps públicos da HOP são licenciados sob a licença permissiva MIT.

<a id="family"></a>

## A família HOP

O HEL.ink é o hub de uma família de ferramentas web abertas que respeitam sua privacidade.

| Aplicativo | Endereço ativo | Código | O que faz |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | este repositório | Portal de contas, login HOP ID |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Encurtador de links e códigos QR |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Páginas de link-in-bio e temas |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Hospedagem de imagens e mídia |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Notas privadas com criptografia no cliente |

<a id="security"></a>

## Ajuda e suporte

Encontrou um problema ou precisa de ajuda? Abra uma issue no <a href="https://github.com/navi-crwn/HEL.ink/issues">GitHub Issues</a>ou envie uma pull request.

---

<p align="center"><sub>HEL.ink · HOP ID · parte da família HOP · <a href="https://hel.ink">hel.ink</a></sub></p>
