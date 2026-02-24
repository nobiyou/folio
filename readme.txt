=== Folio ===
Contributors: mpb
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

一个现代化的作品集展示主题，专为设计师和创意工作者打造。

== 主题说明 ==

Folio 是面向设计师与创作者的 WordPress 主题，采用六边形卡片与现代化排版，支持博客与作品集展示、用户中心、会员体系及丰富后台配置。

* 前台：响应式布局、深色/浅色模式、作品分类、用户中心（登录/注册/收藏/个人资料）、会员等级（普通/VIP/SVIP）、文章统计（浏览/点赞/收藏）、社交链接、多语言
* 后台：主题设置（外观、社交、SEO、性能）、安全与访问日志、性能与缓存管理、会员与通知管理、AI 辅助内容等
* 技术：内置 SEO、性能优化、注册邮箱验证码、用户封禁与黑名单、国际化（.pot/.po）

== Description ==

Folio 是一个简洁、现代的 WordPress 作品集主题，具有以下特点：

* 响应式设计，适配各种设备
* 深色/浅色模式切换
* 自定义作品集文章类型
* 作品分类管理
* 用户中心系统（登录、注册、收藏）
* 会员等级系统（普通用户、VIP、SVIP）
* 内置 SEO 优化
* 文章统计（浏览量、点赞、收藏）
* 性能优化
* 国际化支持

== 上传排除列表 ==

上传主题到服务器或打包发布时，请排除以下文件/目录，避免泄露开发环境或增大体积：

* node_modules/ （前端依赖，仅本地构建用）
* .git/、.github/ （版本控制与 CI，无需随主题发布）
* .env、.env.local、*.env （环境与密钥，禁止上传）
* .DS_Store、Thumbs.db （系统文件）
* .vscode/、.idea/、*.swp、*.swo （编辑器与临时文件）
* package-lock.json、package.json、tailwind.config.js、postcss.config.js （可选：若仅部署已编译资源可排除）
* build-for-production.bat、.distignore （构建与排除配置，可选排除）
* SECURITY.md、README.md 等仓库文档（运行时不需要，可选排除）
* assets/css/tailwind.css.map （Source map，可选排除）
* *.zip、.build_tmp/、dist/、dist_pkg/ （构建产物与打包目录）

使用 .distignore 时，可将上述规则写入该文件，便于脚本或 wp dist-archive 等工具自动排除。上传前请确保已执行构建并包含 assets/css/tailwind.css。

== Installation ==

1. 上传主题文件夹到 `/wp-content/themes/` 目录（上传时请参考上方「上传排除列表」排除开发用文件）
2. 在 WordPress 后台 "外观 > 主题" 中激活主题
3. 访问 "设置 > 固定链接" 并点击保存以刷新重写规则
4. 在 "外观 > 主题设置" 或 "外观 > 自定义" 中配置主题选项

== Frequently Asked Questions ==

= 如何添加作品？ =

激活主题后，在后台左侧菜单会出现 "作品集" 选项，点击 "添加新作品" 即可。

= 如何设置用户为 VIP？ =

在后台 "用户" 列表中，编辑用户，找到 "会员设置" 部分，选择会员等级和到期时间。

= 如何访问用户中心？ =

访问 `/user-center/` 即可进入用户中心。

== Requirements ==

* WordPress 5.0 or higher
* PHP 7.4 or higher

== Security ==

If you discover a security vulnerability, please report it responsibly (e.g. via GitHub Security Advisories or a private contact). We will respond to valid reports and credit reporters where appropriate. See the SECURITY.md file in the theme directory for security practices and details.

== Changelog ==

= 1.0.0 =
* 初始版本发布
* 作品集文章类型和分类
* 深色/浅色模式
* 用户中心系统
* 会员等级系统
* 文章统计功能
* SEO 优化
* 性能优化

== Credits ==

* Tailwind CSS - https://tailwindcss.com/
* Google Fonts (Oswald, Roboto Condensed)

== Copyright ==

Folio WordPress Theme, Copyright 2024
Folio is distributed under the terms of the GNU GPL

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.
