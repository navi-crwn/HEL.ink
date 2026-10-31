<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh.md">简体中文</a> · <a href="README.es.md">Español</a> · <a href="README.ar.md">العربية</a> · <a href="README.pt.md">Português</a> · <a href="README.fr.md">Français</a> · <b>日本語</b> · <a href="README.id.md">Bahasa Indonesia</a></p>

# HEL.ink — HEL.ink · HOP ファミリーハブ & HOP ID

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="HEL.ink · HOP ファミリーハブ" width="100%"></picture></p>

<p align="center"><b>HEL.ink は HOP のアカウントポータルおよびディレクトリです。HOP ID で一度ログインすれば、LinkHop、BioHop、PixelHop、NoteHop をシームレスに利用できます。</b></p>

<p align="center">
  <a href="https://hel.ink">hel.ink</a> ·
  <a href="https://hub.hel.ink">hub.hel.ink</a> ·
  <a href="#run">セルフホスト</a> ·
  <a href="#features">機能紹介</a> ·
  <a href="#faq">よくある質問</a> ·
  <a href="#family">HOP ファミリー</a>
</p>

---

## ファミリーハブ & アカウントポータル

LinkHop、BioHop、PixelHop、NoteHop のための中央アカウントポータル、HOP ID シングルサインオン、ファミリーディレクトリ。

<a id="features"></a>

## 主な機能

- **シングルサインオン (HOP ID)**: 1 回のログインですべての HOP アプリに対応。署名付きチケットと連鎖ログアウトで安全を維持。
- **統合アプリランチャー**: どこからでもワンクリックで LinkHop、BioHop、PixelHop、NoteHop を行き来できます。
- **共有データベース構造**: 1 つの MySQL または SQLite でアカウントを保持し、アプリ別のマイグレーション台帳で競合を防止。
- **堅牢なアカウントセキュリティ**: Argon2id ハッシュ、TOTP 二要素認証、Cloudflare Turnstile 保護、メール確認に対応。
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

## HOP ファミリー

HEL.ink はプライバシーを重視したオープンソース Web アプリファミリーの中心です。

| アプリ | 公開アドレス | ソースコード | 役割 |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | このリポジトリ | アカウントポータル、HOP ID ログイン |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | 短縮 URL と QR コード |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | リンクインバイオとテーマ |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | 画像およびメディア配信 |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | クライアント暗号化プライベートノート |

<a id="security"></a>

## ヘルプとサポート

バグの報告やサポートが必要ですか？ 次の場所で Issue を開いてください： <a href="https://github.com/navi-crwn/HEL.ink/issues">GitHub Issues</a>またはプルリクエストを送信してください。

---

<p align="center"><sub>HEL.ink · HOP ID · HOP ファミリーの一員 · <a href="https://hel.ink">hel.ink</a></sub></p>
