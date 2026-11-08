<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <b>Português (Brasil)</b> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

# HEL.ink — HEL.ink · Hub da família HOP e HOP ID

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="HEL.ink · Hub da família HOP" width="100%"></picture></p>

<p align="center"><b>O HEL.ink é o portal de contas e diretório da família para o HOP. Entre uma vez com seu HOP ID e navegue tranquilamente entre LinkHop, BioHop, PixelHop, NoteHop e FileHop.</b></p>

<p align="center">
  <a href="https://hel.ink">hel.ink</a> ·
  <a href="https://hub.hel.ink">hub.hel.ink</a> ·
  <a href="#run">Hospede você mesmo</a> ·
  <a href="#features">Recursos</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="#family">Família HOP</a>
</p>

---

Instale com um comando (ele verifica seu computador primeiro, faz poucas perguntas e pode instalar o hub HOP ID para você):

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/HEL.ink/main/install.sh | bash
```

A partir de um clone: `./install.sh` · atualizar: `./update.sh` · remover: `./uninstall.sh`. Use `--yes` para aceitar todos os padrões; `--help` lista as opções.

## Hub da família e portal de contas

Portal central de contas, logon único HOP ID e diretório da família para LinkHop, BioHop, PixelHop, NoteHop e FileHop.

<a id="features"></a>

## Recursos

- **Logon Único (HOP ID)**: Um login cobre todos os aplicativos HOP. Tickets assinados e encerramento em cadeia.
- **Inicializador unificado de apps**: Alterne entre LinkHop, BioHop, PixelHop, NoteHop e FileHop com um clique.
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
| **FileHop** | `file.hel.ink` | [navi-crwn/FileHop](https://github.com/navi-crwn/FileHop) | Conversão de arquivos no navegador, sem envio |

## Changelog

As mudanças estão listadas em [CHANGELOG.md](CHANGELOG.md).

<a id="security"></a>

## Hospedagem própria

<!-- self-hosting:start -->
Tudo o que o HEL.ink precisa está em repositórios públicos: nenhum repositório privado, nenhum token e nenhuma conta no hel.ink. Os detalhes estão no guia da família: [docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md) (em inglês).

| Peça | Para o HEL.ink | O que faz |
| --- | --- | --- |
| [HEL.ink](https://github.com/navi-crwn/HEL.ink) (hub, HOP ID) | Este repositório | Contas e login para todos os apps |
| [hop/auth](https://github.com/navi-crwn/hop-auth), [hop/ui](https://github.com/navi-crwn/hop-ui), [hop/safety](https://github.com/navi-crwn/hop-safety) | Incluídos em `packages/` | Código PHP compartilhado: login, interface, segurança de links |
| [Imperium](https://github.com/navi-crwn/Imperium) | Opcional | Painel de administração de toda a família; precisa do hub |
| Fontes e blocos da família | Nada a instalar: carregados de `hel.ink/hop-assets` | Sem hel.ink: fontes do sistema, sem blocos flutuantes |

- **Ordem de instalação**: primeiro este hub, depois os apps e, se quiser, o Imperium. Registre os apps com `php artisan db:seed --class='Database\Seeders\HopAppsSeeder' --force`. O [`install-family.sh`](install-family.sh) faz tudo de uma vez.
- **Configuração mínima**: `APP_URL`, `HUB_HOST`, `HOP_SSO_SECRET`, `DB_*`, `LINKHOP_URL` … `FILEHOP_URL` (um por app); com o Imperium: `HOP_CONTROL_URL`, `HOP_SSO_SECRET_IMPERIUM`.
- **Sozinho ou na família**: o hub sozinho oferece contas, login e o lançador; o README de cada app diz do que mais ele precisa.
- **Sem o Imperium**: o hub mantém suas próprias páginas de administração de contas (`/admin`); o Imperium acrescenta o conteúdo, as denúncias e a saúde dos apps.
<!-- self-hosting:end -->

## Ajuda e suporte

Encontrou um problema ou precisa de ajuda? Abra uma issue no <a href="https://github.com/navi-crwn/HEL.ink/issues">GitHub Issues</a>ou envie uma pull request.

---

<p align="center"><sub>HEL.ink · HOP ID · parte da família HOP · <a href="https://hel.ink">hel.ink</a></sub></p>
