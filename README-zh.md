- **简体中文**
- [English](./README.md)

<p align="center"><img src="https://media.githubusercontent.com/media/bs-community/logo/main/logo.png"></p>

<p align="center">
<a href="https://github.com/bs-community/blessing-skin-server/actions"><img alt="GitHub Workflow Status" src="https://img.shields.io/github/workflow/status/bs-community/blessing-skin-server/CI?style=flat-square"></a>
<a href="https://codecov.io/gh/bs-community/blessing-skin-server"><img alt="Codecov" src="https://img.shields.io/codecov/c/github/bs-community/blessing-skin-server?style=flat-square"></a>
<a href="https://github.com/bs-community/blessing-skin-server/releases"><img alt="GitHub release (latest SemVer including pre-releases)" src="https://img.shields.io/github/v/release/bs-community/blessing-skin-server?include_prereleases&style=flat-square"></a>
<a href="https://github.com/bs-community/blessing-skin-server/blob/master/LICENSE"><img alt="GitHub" src="https://img.shields.io/github/license/bs-community/blessing-skin-server?style=flat-square"></a>
<a href="https://discord.com/invite/QAsyEyt"><img alt="Discord" src="https://discord.com/api/guilds/761226550921658380/widget.png"></a>
</p>

优雅的开源 Minecraft 皮肤站，现在，回应您的等待。

Blessing Skin 是一款能让您上传、管理和分享您的 Minecraft 皮肤和披风的 Web 应用程序。与修改游戏材质包不同的是，所有人都能在游戏中看到各自的皮肤和披风（当然，前提是玩家们要使用同一个皮肤站）。

Blessing Skin 是一个开源的 PHP 项目，这意味着您可以自由地在您的服务器上部署它。

## 特性

- 完整实现了一个皮肤站该有的功能
- 支持单用户多个角色
- 通过皮肤库来分享您的皮肤和披风！
- 易于使用
  - 可视化的用户、角色、材质管理页面
  - 详细的站点配置页面
  - 多处 UI/UX 优化只为更好的用户体验
- 安全
  - 支持多种安全密码 Hash 算法
  - 注册可要求 Email 验证
  - 防止恶意请求的积分系统
- 强大的可扩展性
  - 多种多样的插件
  - 支持与 Authme/Discuz 等程序的用户数据对接（插件）
  - 支持自定义 Yggdrasil API 外置登录系统（插件）

## 环境要求

Blessing Skin 对您的服务器有一定的要求。在大多数情况下，下列所需的 PHP 扩展已经开启。

- 一台支持 URL 重写的主机，Nginx 或 Apache
- PHP >= 8.1.0（已验证 PHP 8.4）
- 安装并启用如下 PHP 扩展：
  - OpenSSL >= 1.1.1 (TLS 1.3)
  - PDO
  - Mbstring
  - Tokenizer
  - GD
  - XML
  - Ctype
  - JSON
  - fileinfo
  - zip
  - Imagick

## 快速使用

请参阅 [安装指南](https://blessing.netlify.app/setup.html)。升级时只覆盖程序文件，必须保留现有的 `storage` 目录，尤其是 `storage/textures` 和 `storage/database.db`，否则数据库中的皮肤记录会保留但图片文件会丢失。

### Docker

项目提供 PHP 8.4 Apache 镜像，并在发布版本时自动推送到 GitHub Container Registry。镜像内置 SQLite，首次启动后访问站点根路径完成安装；`/app/storage` 必须持久化，该目录包含数据库、配置、插件和材质文件。

使用仓库提供的 Compose 文件启动最新镜像：

```bash
echo "${GHCR_TOKEN}" | docker login ghcr.io -u "${GITHUB_USERNAME}" --password-stdin
docker compose up -d
```

其中 `GHCR_TOKEN` 需要具备 `read:packages` 权限；如果管理员已将 GHCR 包设为公开，可以跳过登录。

默认通过 `http://localhost:8080` 访问。指定版本或端口：

```bash
BLESSING_SKIN_IMAGE=ghcr.io/fuuuuwaaaaaaa/blessing-skin-server:6.0.14 \
BLESSING_SKIN_PORT=2333 \
docker compose up -d
```

也可以从源码构建镜像：

```bash
docker build -t blessing-skin-server:local .
docker run -d --name blessing-skin \
  -p 8080:80 \
  -v blessing-skin-storage:/app/storage \
  --restart unless-stopped \
  blessing-skin-server:local
```

将站点放在反向代理后时，请转发 `Host`、`X-Forwarded-Host`、`X-Forwarded-Port` 和 `X-Forwarded-Proto`。其中 `X-Forwarded-Port` 用于保留非标准公网端口，例如 `2333`。升级时保留同一个 `blessing-skin-storage` 卷。

## 插件系统

Blessing Skin 提供了强大的插件系统，您可以通过添加多种多样的插件来为您的皮肤站添加功能。

## 自行构建

详情可阅读 [这里](https://blessing.netlify.app/build.html)。

> 您可以订阅我们的 Telegram 频道 [Blessing Skin News](https://t.me/blessing_skin_news) 来获取最新开发动态。当有新的 Commit 被推送时，我们的机器人将会在频道内发送一条消息来提示您能否拉取最新代码，以及拉取后应该做什么。

## 国际化（i18n）

Blessing Skin 可支持多种语言，当前支持英语、简体中文和西班牙语。

如果您愿意将您的翻译贡献出来，欢迎参与 [我们的 Crowdin 项目](https://crowdin.com/project/blessing-skin)。

## 问题报告

请参阅 [报告问题的正确姿势](https://blessing.netlify.app/report.html)。

## 相关链接

- [用户手册](https://blessing.netlify.app/)
- [插件开发文档](https://bs-plugin.netlify.app/)

## 版权

MIT License

Copyright (c) 2016-present The Blessing Skin Team

程序原作者为 [@printempw](https://printempw.github.io/)，转载请注明。
