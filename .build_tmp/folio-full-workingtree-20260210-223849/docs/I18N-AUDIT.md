# 国际化扫描报告

## 结论
- 当前主题国际化（前后台用户可见文案）已完成可发布状态。
- 会员系统管理页、安全防护管理页、用户中心（侧边导航/登录/注册/资料/通知）已完成词条覆盖并通过语言包验证。
- 保留项：`inc/class-ai-content-generator.php` 中 AI 提示词样例按项目约定保留原文，不纳入界面国际化阻塞。

## 本轮进展（2026-02-09）
- 已完成 `inc/class-security-admin.php` 用户可见文案国际化：
  - 安全概览、访问日志、安全设置、蜘蛛池管理、运营报告、相关 AJAX 返回消息。
- 已完成 `assets/js/admin-options.js`：
  - 重写为 UTF-8，清理乱码，统一改为可翻译文案读取（含英文回退）。
  - 已在 `inc/class-theme-options.php` 补齐 `folioAdmin.i18n` 词典，与脚本 key 对齐。
- 已完成 `assets/js/unified-performance-admin.js` + `inc/class-unified-performance-admin.php`：
  - 前端提示与按钮文案改为词典读取。
  - 后端 `wp_localize_script` 已补齐对应词条。
  - 标题匹配/缓存类型匹配逻辑改为词典键，不再在源码里硬编码中文字符串。
- 已完成 `assets/js/folio-core.js` + `assets/js/theme.js` + `inc/class-script-manager.php`：
  - 前台通知/分享/点赞收藏/跳转提示改为词典读取。
  - `folio_ajax.strings` 已补齐主要文案。
- 已完成 `assets/js/notifications.js` + `inc/class-script-manager.php`：
  - “暂无通知/xx分钟前/刚刚”等通知时间与空态文案改为词典读取。
  - `folioNotifications.strings` 已补齐对应词条。
  - 已将剩余控制台日志与行内注释统一为英文，减少后续扫描噪音。
- 已完成 `assets/js/premium-content.js` + `inc/class-premium-content-enhanced.php` + `inc/class-script-manager.php`：
  - 会员升级弹窗标题、说明、权益列表、按钮文案改为词典读取。
  - 补齐 `folioPremium.strings` 并增加 `membership_url`，避免硬编码跳转地址。
- 已完成 `assets/js/frontend-components.js` + `inc/class-frontend-components.php` + `inc/class-script-manager.php`：
  - 登录成功/会员更新后的提示文案改为词典读取。
  - 统一兼容 `ajax_url/ajaxurl` 字段，修复不同 localize 来源下的潜在请求地址不一致问题。
- 已完成 `assets/js/admin-common.js` + `inc/class-script-manager.php`：
  - 后台通用 AJAX 错误提示、无障碍文案、时间单位改为词典读取。
  - 新增 `folioAdminCommon.strings`。
- 已完成 `assets/js/ai-generator.js` + `inc/class-ai-content-generator.php`：
  - AI 生成结果提示（摘要/关键词/错误标签）改为词典读取。
  - 补齐 `folioAI.strings` 对应词条。
- 已完成 `assets/js/membership-metabox.js` + `inc/class-membership-meta-box.php` + `inc/class-script-manager.php`：
  - 元框预览、批量操作、加载态与错误提示改为词典读取。
  - 处理了 `folio-membership-metabox` 在不同入口下 localize 覆盖问题（两处都补齐 `strings`）。
- 已完成 `template-parts/membership-showcase.php`：
  - 模板静态文案（方案、对比表、FAQ）全部包裹翻译函数。
- 已完成 `inc/class-membership-notifications.php`（本轮重点）：
  - 会员提醒、开通、变更、过期、注册成功等站内通知标题与内容改为可翻译字符串。
  - 邮件模板中的硬编码中文标签与按钮文案（欢迎邮件、管理员注册提醒、统一通知模板）改为翻译函数。
  - AJAX 返回消息（未登录、无效 ID、无权限、操作失败等）全部改为翻译函数。
  - 会员到期日期格式由固定 `Y年m月d日` 改为 `wp_date(get_option('date_format'))`，跟随站点语言与日期设置。
- 已完成 `inc/class-premium-content-enhanced.php`：
  - 锁定内容提示、登录/注册/升级按钮、默认短代码参数文案、摘要兜底提示改为可翻译字符串。
  - 会员卡按钮与“当前等级”提示改为翻译函数输出。
- 已完成 `inc/class-user-center.php`：
  - 注册流程中的安全校验提示、成功提示、站内通知文案改为可翻译字符串。
  - 用户欢迎邮件与管理员注册提醒邮件中的标题、字段名、按钮文案改为翻译函数。
- 已完成缓存/性能模块的关键提示文案国际化（高频后台交互）：
  - `inc/class-performance-cache-manager.php`：nonce/权限错误、清理结果、Memcached/Redis 状态与失败提示改为翻译函数。
  - `inc/class-cache-health-checker.php`：健康检查 AJAX 错误、关键健康告警文案改为翻译函数。
  - `inc/class-unified-performance-admin.php`：缓存清理、预热、分析、导出、重置、定时任务等 AJAX 成功/失败提示改为翻译函数。
  - 同步补齐 `inc/class-premium-content-enhanced.php` 中遗漏的 AJAX 安全/权限错误提示。
- 已完成 `inc/class-cache-health-checker.php`（本轮扩展）：
  - 健康检查结果中的 section 标题、label、value、description、actions 统一改为可翻译字符串。
  - 命中率/查询量描述函数返回值统一国际化。
- 已完成 `inc/class-theme-options.php`（本轮扩展）：
  - 图片优化与性能仪表盘内联脚本中的“加载中/失败/确认清理/统计字段”等硬编码提示改为翻译函数输出。
  - 补齐“清除维护日志”确认弹窗与“刷新重写规则”失败提示国际化。
  - 继续补齐“资源优化/清除优化缓存”脚本中的按钮状态、错误提示、确认弹窗文案国际化。
- 已完成 `inc/class-performance-monitor.php`：
  - 前端性能条 tooltip、详情面板标题与字段、提示文案、慢查询与优化建议标签改为翻译函数。
  - AJAX 清空日志返回信息与优化建议文案改为翻译函数。
- 已完成 `inc/class-frontend-components.php`：
  - 会员徽章状态文案、权限提示主标题/按钮/表头/状态信息改为翻译函数。
  - 默认会员权益对比数据、权益摘要与升级按钮默认文案改为翻译函数。
  - AJAX 权限检查错误提示（nonce/无效文章 ID）改为翻译函数。
- 已完成 `inc/class-security-protection-manager.php`（对外文案层）：
  - RSS/API/SEO 的会员内容替代提示改为翻译函数。
  - 访问限制响应（429 提示、标题、阻止页 HTML 文案）改为翻译函数。

## 已通过检查
- `node --check`：
  - `assets/js/admin-common.js`
  - `assets/js/admin-options.js`
  - `assets/js/folio-core.js`
  - `assets/js/theme.js`
  - `assets/js/unified-performance-admin.js`
- `php -l`：
  - `inc/class-security-admin.php`
  - `inc/class-script-manager.php`
  - `inc/class-unified-performance-admin.php`
  - `inc/class-membership-notifications.php`
  - `inc/class-premium-content-enhanced.php`
  - `inc/class-performance-cache-manager.php`
  - `inc/class-cache-health-checker.php`
  - `inc/class-unified-performance-admin.php`
  - `inc/class-theme-options.php`
  - `inc/class-performance-monitor.php`
  - `inc/class-frontend-components.php`
  - `inc/class-security-protection-manager.php`
  - `inc/class-user-center.php`
  - `template-parts/membership-showcase.php`

## 当前阻塞
1. 语言包工具缺失：
- 无法在当前环境执行 `wp i18n make-pot`、`msgmerge`。
- `msgfmt` 不可用，但已通过 Python 脚本完成 `.po -> .mo` 编译兜底。
2. 历史编码问题：
- 部分旧文件存在历史乱码痕迹（非本轮新增），需分批清理，避免一次性大改引入风险。

## 下一步优先级
1. 在具备工具的环境重建语言文件：
- 更新 `languages/folio.pot`
- 合并 `languages/zh_CN.po`
- 使用标准 gettext 工具链复编译 `languages/zh_CN.mo`（与 Python 兜底结果交叉验证）
2. 做一轮中英文切换回归：
- 前台：分享、点赞、收藏、会员页面。
- 后台：统一性能页、安全页、主题设置页。

## 复扫状态（2026-02-09）
- 已执行项目级复扫（排除 `docs/` 与 `languages/`）：
  - `*.php` 代码中的中文字符串字面量：未发现
  - `*.js`（非 `*.min.js`）中的中文字符串字面量：未发现
- 本机工具现状：
  - `wp`（WP-CLI）：不可用
  - `msgfmt`（gettext）：不可用（已用 Python 兜底编译）
- 词条同步状态：
  - 已基于本轮改动文件做增量提取与差集补齐，新增缺失词条 367 条到 `languages/folio.pot` 与 `languages/zh_CN.po`。
  - 目前 `msgid/msgstr` 计数一致（`folio.pot` 与 `zh_CN.po` 均为 1599）。
  - 已修复 `languages/zh_CN.po` 中重复条目与异常转义（`\\\"`）问题。
  - 已在当前环境用 Python 成功编译 `languages/zh_CN.mo`（文件大小 108,973 字节）。
  - 已继续补齐 `inc/class-script-manager.php` 相关漏提取词条 53 条（通知中心、预览、复制、网络错误、时间文案等），并完成中文翻译。
  - 已补齐会员审计日志功能新增词条 13 条（管理页按钮/提示/来源标签等），并完成中文翻译。
  - 已重新编译 `languages/zh_CN.mo`，当前词条规模为 1650 entries。
  - 已再次编译 `languages/zh_CN.mo`，当前词条规模为 1663 entries。
  - 已回填一批高优先级中文翻译（权限校验、缓存操作、性能提示、访问限制等核心用户可见文案）。
  - 已继续分批回填剩余条目，`languages/zh_CN.po` 当前未翻译条目为 0（空 `msgstr` 已清零）。

## 备注
- 本文档以“可发布可追踪”为目标，只保留高价值状态信息。
- 历史大列表（前200条疑似未国际化）已不再维护，改为按模块闭环推进与复扫。

## 复扫状态（2026-02-10）
- 已完成本轮剩余中文 `msgid` 收尾（源码层）：
  - `inc/class-ai-content-generator.php`
  - `inc/class-membership-safe.php`
  - `inc/class-membership-analytics.php`
  - `inc/class-post-sidebar-widgets.php`
  - `inc/class-editor-navigation.php`
  - `inc/class-theme-seo.php`
  - `inc/class-theme-optimize.php`
  - `inc/template-tags.php`
- 已执行复扫（排除 `dist/` 与 `dist_pkg/`）：
  - 中文 `msgid` 残留：0
- 已同步语言文件：
  - `languages/folio.pot`
  - `languages/zh_CN.po`（未翻译条目 0）
  - `languages/zh_CN.mo`（Python 编译）
- 已同步发布目录：
  - `dist_pkg/folio`
  - `dist/folio`
- 本轮继续清理（已按“AI 提示词可保留”执行）：
  - 已完成：`class-membership-admin.php`、`class-premium-content-enhanced.php`、`class-membership-seo.php`、`class-membership-analytics.php`、`class-notification-widget.php` 等会员核心页面文案国际化。
  - 已完成：缓存提示与部分后台文案国际化（`class-performance-cache-manager.php`、`class-theme-options.php`、`user-center/notifications.php` 等）。
  - 当前剩余主要集中：`class-operations-report.php`、`class-security-protection-manager.php`、`memcached-helper.php`。

## 复扫状态（2026-02-10 晚）
- 已执行发布门禁 `scripts/release-gate.ps1`：
  - PHP 语法检查通过
  - 关键 JS 语法检查通过
  - 国际化残留扫描通过
- 已清理未使用遗留资源：`assets/js/folio-core.min.js`、`assets/js/membership-optimized.min.js`。
- 当前 `languages/zh_CN.po` 未翻译条目为 0，`languages/zh_CN.mo` 已同步可用。
