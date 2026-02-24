# Folio 主题安全说明

本文档说明主题的安全设计、开源发布前的审查结果及使用建议。

## 一、已实施的安全措施

### 1. 直接访问防护
- 所有 PHP 模板与 `inc/` 下文件均包含 `if (!defined('ABSPATH')) { exit; }`，防止被直接通过 URL 访问执行。

### 2. CSRF 防护（Nonce）
- **表单**：登录、注册、个人资料更新、退出等均使用 `wp_nonce_field()` / `wp_verify_nonce()`。
- **AJAX**：用户中心（登录、注册、发送验证码）、安全中心、性能/缓存管理、会员管理、AI 生成等均校验 `nonce`（`wp_ajax_*` 与 `wp_ajax_nopriv_*` 中）。
- **管理操作**：封禁/解封用户使用 `_wpnonce` + `wp_verify_nonce('folio_ban_user_' . $user_id)` 等。

### 3. 权限与能力校验
- 主题设置页：`add_theme_page(..., 'manage_options', ...)`，仅管理员可访问。
- 安全中心、性能仪表盘、缓存管理、会员管理、AI 内容生成等后台功能：在 AJAX 与页面逻辑中均使用 `current_user_can('manage_options')` 或相应 `edit_posts` / `edit_post` 校验。
- 用户列表封禁/解封：`current_user_can('list_users')`。
- 用户中心：需登录的 action 通过 `is_user_logged_in()` 与路由控制，避免未授权访问。

### 4. 输入处理
- 用户中心：`sanitize_text_field()`、`sanitize_email()`、`sanitize_textarea_field()` 等对 POST/GET 进行清理。
- 主题选项：`register_setting` 配合 `sanitize_options` 回调，对保存内容做白名单与清理。
- 注册验证码：6 位数字用 `ctype_digit` 校验，与存储比对使用 `hash_equals()` 防时序攻击。

### 5. 输出转义
- 模板中用户可见文本使用 `esc_html()`、`esc_attr()`、`esc_url()`、`esc_js()` 等。
- 邮件内容中动态部分使用 `esc_html()` / `wp_kses_post()` 等，避免注入 HTML。

### 6. 数据库查询
- 涉及用户输入或动态条件的 SQL 均使用 `$wpdb->prepare()`。
- 表名使用 `$wpdb->prefix . '...'` 等固定拼接，不直接拼接用户输入。`inc/class-helper-manager.php` 中 `SHOW TABLES LIKE` 已改为 `$wpdb->prepare('SHOW TABLES LIKE %s', $notification_table)`。

### 7. 敏感信息
- 无硬编码 API Key、密码或 Token；AI API Key、SMTP 密码等均通过主题选项/通知设置保存于数据库，仅管理员可配置。
- 建议：若后续增加 `.env` 或本地配置，请加入 `.gitignore`，勿提交仓库。

### 8. 文件包含
- `require_once` / `include` 仅使用常量路径（如 `FOLIO_DIR`、`get_template_directory()`、`ABSPATH . 'wp-admin/...'`），无用户可控路径，避免本地文件包含。

### 9. 注册与登录
- 注册：邮箱验证码、频率限制（同邮箱 60 秒/次，同 IP 15 分钟 5 次）、黑名单、`email_exists()` 校验。
- 登录：封禁用户通过 `authenticate` 钩子拦截并返回明确错误信息。

---

## 二、发布前已修复项

- **class-helper-manager.php**：`folio_get_unread_notification_count` 与 `folio_get_user_latest_notifications` 中 “SHOW TABLES LIKE” 由字符串拼接改为 `$wpdb->prepare('SHOW TABLES LIKE %s', $notification_table)`，与项目内其他 SQL 规范一致。

---

## 三、建议与最佳实践

1. **输出统一转义**（已落实）  
   管理界面中从数据库取出的数字/状态已统一使用 `esc_html()` 或 `esc_attr()`：`class-security-admin.php`（标签页 class、蜘蛛/报告 ID、日期）、`class-unified-performance-admin.php`（统计数值、缓存状态、推荐卡片）、`class-notification-admin.php`（标签页 class）。

2. **.gitignore**  
   若使用环境变量或本地配置，建议在 `.gitignore` 中增加：
   ```gitignore
   .env
   .env.local
   *.env
   ```

3. **依赖与版本**  
   定期更新 WordPress 最低版本要求与所依赖的 PHP 版本，并在 README 中说明，以利于安全更新。

4. **漏洞反馈**  
   开源发布后，建议在 README 中说明安全问题的反馈方式（如 GitHub Security Advisories 或私密邮箱），并承诺对合理报告进行响应与致谢。

---

## 四、审查范围摘要

| 项目           | 状态说明 |
|----------------|----------|
| Nonce 校验     | 表单与 AJAX 已覆盖关键操作 |
| 能力/权限校验  | 管理功能已使用 `current_user_can` / `is_user_logged_in` |
| 输入清理       | 用户中心、主题选项等已做 sanitize |
| 输出转义       | 模板与邮件已普遍使用 esc_*，少数管理页可进一步加强 |
| SQL 注入防护   | 动态条件已用 prepare；表名已用固定前缀并修正 SHOW TABLES |
| 敏感信息       | 无硬编码密钥，敏感配置存选项 |
| 直接访问与包含 | ABSPATH 检查与固定路径包含已落实 |

本主题在开源发布前已完成上述安全审查与修正，适合在 GitHub 上公开仓库发布。后续新增功能请继续遵循：**校验 nonce、校验权限、清理输入、转义输出、SQL 使用 prepare**。
