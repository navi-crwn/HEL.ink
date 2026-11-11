<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <b>日本語</b> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

# HEL.ink — HEL.ink · HOP ファミリーハブ & HOP ID

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="HEL.ink · HOP ファミリーハブ" width="100%"></picture></p>

<p align="center"><b>HEL.ink は HOP のアカウントポータルおよびディレクトリです。HOP ID で一度ログインすれば、LinkHop、BioHop、PixelHop、NoteHop、FileHop、PayHop をシームレスに利用できます。</b></p>

<p align="center">
  <a href="https://hel.ink">hel.ink</a> ·
  <a href="https://hub.hel.ink">hub.hel.ink</a> ·
  <a href="#run">セルフホスト</a> ·
  <a href="#features">機能紹介</a> ·
  <a href="#faq">よくある質問</a> ·
  <a href="#family">HOP ファミリー</a>
</p>

---

1 つのコマンドでインストール（まずお使いのコンピューターを確認し、いくつか質問し、HOP ID ハブの設定もできます）：

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/HEL.ink/main/install.sh | bash
```

クローンから: `./install.sh` · 更新: `./update.sh` · 削除: `./uninstall.sh`。`--yes` を付けるとすべて既定値になります。`--help` でオプション一覧を表示します。

## ファミリーハブ & アカウントポータル

LinkHop、BioHop、PixelHop、NoteHop、FileHop、PayHop のための中央アカウントポータル、HOP ID シングルサインオン、ファミリーディレクトリ。

<a id="features"></a>

## 主な機能

- **シングルサインオン (HOP ID)**: 1 回のログインですべての HOP アプリに対応。署名付きチケットと連鎖ログアウトで安全を維持。
- **統合アプリランチャー**: どこからでもワンクリックで LinkHop、BioHop、PixelHop、NoteHop、FileHop、PayHop を行き来できます。
- **共有データベース構造**: 1 つの MySQL または SQLite でアカウントを保持し、アプリ別のマイグレーション台帳で競合を防止。
- **堅牢なアカウントセキュリティ**: Argon2id ハッシュ、TOTP 二要素認証、Cloudflare Turnstile 保護、メール確認に対応。
- **SSO ゲート、CSP、共通 UI**: すべてのページは Content-Security-Policy 付きで配信され（アプリのページでは nonce 方式）、SSO の引き継ぎは署名されてアプリ側で検証され、ダイアログと言語・アカウントメニューは共通パッケージ HopUI から提供されます。
- **統一されたコントロール基準**: 入力欄、セレクト、ボタンは同じ角丸と高さ（`--hop-ctl-*` トークン）を共有し、スマートフォンでは 16 px の文字と 44 px のタッチ領域、モーション低減時は静的なレイアウトになります。
- **ライブサービスウォッチドッグ**: 秘密情報を漏らすことなく、ファミリーアプリ全体の稼働状況と応答速度を自動監視。
- **集中管理されたファミリー資産**: 公式マーク、フォント、3D フローティングタイルが SRI ハッシュ付きで hel.ink から配信されます。
- **きめ細やかな API トークン**: アプリ間通信や開発者自動化のためのスコープ付きサービストークンを発行可能。
- **完全なセルフホスト対応**: 100% 無料かつオープンソース。Hub 単体での運用も、単一の VPS 上で HOP 全体を構築することも自由です。

<a id="run"></a>

## 独自の Hub を構築する

Hub は標準的なモダン PHP 環境で動作します。

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

## よくある質問

**HOP ID とは何ですか？**
HOP ID は HOP ファミリーの中央認証サービスです。1 つのアカウントで全アプリにアクセスできます。

**他のアプリなしで Hub を単体実行できますか？**
はい。Hub は単体のアカウント・ディレクトリサービスとしても動作しますが、HOP アプリと併用することで真価を発揮します。

**シングルログアウトはどのように機能しますか？**
Hub からログアウトするとローカルセッションが破棄され、接続中の各アプリへ連鎖ログアウト信号が送信されます。

**HEL.ink はオープンソースですか？**
はい、HEL.ink およびすべての公開 HOP アプリは MIT ライセンスで提供されています。

<a id="family"></a>

## セルフホスティング

<!-- self-hosting:start -->
HEL.ink に必要なものはすべて公開リポジトリにあります。非公開リポジトリ、トークン、hel.ink のアカウントは不要です。詳しくはファミリーガイド [docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md)（英語）を参照してください。

| 構成要素 | HEL.ink にとって | 役割 |
| --- | --- | --- |
| [HEL.ink](https://github.com/navi-crwn/HEL.ink) (ハブ、HOP ID) | このリポジトリ | すべてのアプリのアカウントとサインイン |
| [hop/auth](https://github.com/navi-crwn/hop-auth), [hop/ui](https://github.com/navi-crwn/hop-ui), [hop/safety](https://github.com/navi-crwn/hop-safety) | `packages/` に同梱 | 共有 PHP コード：サインイン、UI、リンクの安全性 |
| [Imperium](https://github.com/navi-crwn/Imperium) | 任意 | ファミリー全体の管理パネル。ハブが必要 |
| ファミリーのフォントとタイル | インストール不要：`hel.ink/hop-assets` から読み込み | hel.ink がなければシステムフォント、浮遊タイルなし |

- **インストール順**：まずこのハブ、次にアプリ、必要なら最後に Imperium。アプリの登録は `php artisan db:seed --class='Database\Seeders\HopAppsSeeder' --force`。[`install-family.sh`](install-family.sh) なら一度にすべて行います。
- **最小限の設定**：`APP_URL`, `HUB_HOST`, `HOP_SSO_SECRET`, `DB_*`, `LINKHOP_URL` … `PAYHOP_URL` (アプリごとに 1 つ); Imperium 利用時: `HOP_CONTROL_URL`, `HOP_SSO_SECRET_IMPERIUM`。
- **単体かファミリーか**：ハブ単体でアカウント、サインイン、ランチャーが使えます。各アプリに他に何が必要かは、それぞれの README にあります。
- **Imperium なしの場合**：ハブにはアカウント用の独自の管理ページ（`/admin`）があります。Imperium はアプリのコンテンツ、通報、稼働状況を加えます。
<!-- self-hosting:end -->

## HOP ファミリー

HEL.ink はプライバシーを重視したオープンソース Web アプリファミリーの中心です。

| アプリ | 公開アドレス | ソースコード | 役割 |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | このリポジトリ | アカウントポータル、HOP ID ログイン |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | 短縮 URL と QR コード |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | リンクインバイオとテーマ |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | 画像およびメディア配信 |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | クライアント暗号化プライベートノート |
| **FileHop** | `file.hel.ink` | [navi-crwn/FileHop](https://github.com/navi-crwn/FileHop) | ブラウザ内のファイル変換（アップロードなし） |
| **PayHop** | `pay.hel.ink` | [navi-crwn/PayHop](https://github.com/navi-crwn/PayHop) | 支払い用QRコードの作成と割り勘。内容がブラウザの外に出ることはありません。 |

## 変更履歴

変更は [CHANGELOG.md](CHANGELOG.md) に記載しています。

<a id="security"></a>

## ヘルプとサポート

バグの報告やサポートが必要ですか？ 次の場所で Issue を開いてください： <a href="https://github.com/navi-crwn/HEL.ink/issues">GitHub Issues</a>またはプルリクエストを送信してください。

---

<p align="center"><sub>HEL.ink · HOP ID · HOP ファミリーの一員 · <a href="https://hel.ink">hel.ink</a></sub></p>
