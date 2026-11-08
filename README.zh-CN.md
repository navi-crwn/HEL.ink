<p align="center">🌐 <a href="README.md">English</a> · <b>简体中文</b> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

# HEL.ink — HEL.ink · HOP 家族中心与 HOP ID

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <a href="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml"><img src="https://github.com/navi-crwn/HEL.ink/actions/workflows/tests.yml/badge.svg?branch=main" alt="tests workflow"></a>
</p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/hero-en-dark.webp"><img src="docs/readme/hero-en-light.webp" alt="HEL.ink · HOP 家族中心" width="100%"></picture></p>

<p align="center"><b>HEL.ink 是 HOP 的账户门户和家族目录。只需使用 HOP ID 登录一次，即可在 LinkHop、BioHop、PixelHop、NoteHop 和 FileHop 之间无缝切换。</b></p>

<p align="center">
  <a href="https://hel.ink">hel.ink</a> ·
  <a href="https://hub.hel.ink">hub.hel.ink</a> ·
  <a href="#run">自建部署</a> ·
  <a href="#features">功能特性</a> ·
  <a href="#faq">常见问题</a> ·
  <a href="#family">HOP 家族</a>
</p>

---

一条命令安装（它会先检查你的电脑，只问几个问题，还能为你装好 HOP ID 中心）：

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/HEL.ink/main/install.sh | bash
```

已克隆仓库时：`./install.sh` · 更新：`./update.sh` · 卸载：`./uninstall.sh`。加上 `--yes` 即接受所有默认值；`--help` 列出全部选项。

## 家族中心与账户门户

适用于 LinkHop、BioHop、PixelHop、NoteHop 和 FileHop 的中心账户门户、HOP ID 单点登录和家族目录。

<a id="features"></a>

## 功能特性

- **单点登录 (HOP ID)**: 一次登录覆盖所有 HOP 应用。签名凭证与链式登出确保会话一致。
- **统一应用启动器**: 随时随地一键在 LinkHop、BioHop、PixelHop、NoteHop 和 FileHop 间切换。
- **共享数据库架构**: 共享单个 MySQL 或 SQLite 数据库存储身份，各应用独立的迁移账本避免冲突。
- **严格的安全防护**: Argon2id 哈希、TOTP 双重认证、Cloudflare Turnstile 防护与邮箱验证。
- **实时看门狗监测**: 自动健康监测与延迟监控，零密钥泄露。
- **集中式家族资产**: 官方图标、家族字体（Syne、Plus Jakarta Sans）和 3D 浮动卡片由 hel.ink 提供 SRI 支持。
- **细粒度 API 令牌**: 生成带权限范围的令牌，用于应用间通信与开发者自动化。
- **完全开源与自建**: 100% 自由开源。可独立运行 hub，也可在单个 VPS 上部署整个 HOP 生态。

<a id="run"></a>

## 自建您的 Hub

Hub 运行在标准现代 PHP 基础设施上。

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

## 常见问题

**什么是 HOP ID？**
HOP ID 是 HOP 家族的集中认证系统。拥有一个账户即可使用所有 HOP 应用。

**我能单独运行 Hub 吗？**
可以。Hub 可以作为独立的账号与目录服务运行，但配合 HOP 应用体验最佳。

**单点登出如何工作？**
从 Hub 登出将销毁本地会话，并向所有已连接的应用发送链式登出信号。

**HEL.ink 是开源的吗？**
是的，HEL.ink 和所有公开的 HOP 应用均采用宽松的 MIT 许可证。

<a id="family"></a>

## 自行托管

<!-- self-hosting:start -->
HEL.ink 所需的一切都在公开仓库中：不需要私有仓库、令牌或 hel.ink 账户。详细说明见家族指南：[docs/self-hosting.md](https://github.com/navi-crwn/HEL.ink/blob/main/docs/self-hosting.md)（英文）。

| 组件 | 对 HEL.ink 而言 | 作用 |
| --- | --- | --- |
| [HEL.ink](https://github.com/navi-crwn/HEL.ink) (中心，HOP ID) | 就是本仓库 | 所有应用的账户与登录 |
| [hop/auth](https://github.com/navi-crwn/hop-auth), [hop/ui](https://github.com/navi-crwn/hop-ui), [hop/safety](https://github.com/navi-crwn/hop-safety) | 已附带在 `packages/` 中 | 共享 PHP 代码：登录、界面、链接安全 |
| [Imperium](https://github.com/navi-crwn/Imperium) | 可选 | 整个家族的管理面板；需要中心 |
| 家族字体与浮动图块 | 无需安装：从 `hel.ink/hop-assets` 加载 | 无法访问 hel.ink 时：使用系统字体，没有浮动图块 |

- **安装顺序**：先装本中心，再装各应用，需要时最后装 Imperium。用 `php artisan db:seed --class='Database\Seeders\HopAppsSeeder' --force` 登记各应用。[`install-family.sh`](install-family.sh) 可一次完成全部步骤。
- **最少设置**：`APP_URL`, `HUB_HOST`, `HOP_SSO_SECRET`, `DB_*`, `LINKHOP_URL` … `FILEHOP_URL` (每个应用一个); 使用 Imperium 时: `HOP_CONTROL_URL`, `HOP_SSO_SECRET_IMPERIUM`。
- **单独运行或加入家族**：单独的中心即可提供账户、登录与启动器；各应用的 README 会说明还需要什么。
- **没有 Imperium 时**：中心保留自己的账户管理页面（`/admin`）；Imperium 另外提供各应用的内容、举报和健康状态。
<!-- self-hosting:end -->

## HOP 家族

HEL.ink 是注重隐私的开源应用家族中心。

| 应用 | 线上地址 | 代码 | 功能 |
| --- | --- | --- | --- |
| **HOP ID** | `hel.ink` · `hub.hel.ink` | 本仓库 | 账户门户，HOP ID 登录 |
| **LinkHop** | `link.hel.ink` · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | 短链接与二维码生成 |
| **BioHop** | `bio.hel.ink` · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | 个人主页与链接展示 |
| **PixelHop** | `pixel.hel.ink` · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | 图片与媒体存储托管 |
| **NoteHop** | `note.hel.ink` · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | 客户端加密的私密便签 |
| **FileHop** | `file.hel.ink` | [navi-crwn/FileHop](https://github.com/navi-crwn/FileHop) | 在浏览器中转换文件，无需上传 |

## 更新日志

变更记录在 [CHANGELOG.md](CHANGELOG.md) 中。

<a id="security"></a>

## 帮助与支持

发现问题或需要协助？请在以下地址提交议题： <a href="https://github.com/navi-crwn/HEL.ink/issues">GitHub Issues</a>或提交拉取请求。

---

<p align="center"><sub>HEL.ink · HOP ID · HOP 家族的一员 · <a href="https://hel.ink">hel.ink</a></sub></p>
