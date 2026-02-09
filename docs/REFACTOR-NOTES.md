# 重构说明

## 分支
- `refactor/core-stability`

## 重构范围
- 以“稳定性优先”为目标进行重构。
- 尽量保持运行时行为不变。
- 收敛重复初始化与重复资源加载路径。

## 关键改动

### 1. 启动流程与 Hook 注册
- 将 `functions.php` 顶层分散的 Hook 注册重构为分组启动函数：
  - `folio_register_content_model_hooks()`
  - `folio_register_asset_hooks()`
  - `folio_register_route_hooks()`
  - `folio_register_compat_hooks()`
  - `folio_bootstrap_hooks()`

### 2. 资源加载结构
- 将 `folio_enqueue_assets()` 内部逻辑拆分为小函数：
  - `folio_enqueue_font_assets()`
  - `folio_enqueue_base_styles()`
  - `folio_enqueue_conditional_styles()`
  - `folio_enqueue_theme_init_scripts()`
  - `folio_localize_theme_init_script()`
- 新增资源辅助函数：
  - `folio_get_asset_version()`
  - `folio_enqueue_theme_style()`
  - `folio_enqueue_theme_script()`
- 将 `folio_user` 与 `folio_ajax` 的本地化注入统一到 `inc/class-script-manager.php`。

### 3. 重复初始化收敛
- `folio_User_Center` 改为单例使用（`get_instance()`），移除多处重复 `new`。
- `folio_Membership_Notifications` 增加兼容式单例保护：
  - `get_instance()`
  - 一次性初始化保护，避免重复挂载 hook/cron/建表逻辑。

### 4. 缺失脚本保护
- 脚本管理器改为按文件存在性加载以下脚本：
  - `membership-admin.js`
  - `notifications-admin.js`
  - `login.js`
- 使用 `enqueue_script_if_exists()` 避免 404 与依赖报错。

### 5. Portfolio 兼容层
- 当站点未注册时，兼容注册 `portfolio` 文章类型与 `portfolio_category` 分类法。
- 新增开关：
  - `folio_enable_portfolio_compat`（默认 `true`）
- 新增一次性重写刷新机制：
  - 标记：`folio_portfolio_compat_rewrite_pending`
  - 后台触发：`folio_maybe_flush_portfolio_compat_rewrite()`

### 6. 防御性修复
- 对用户中心与访客分页重定向中的 `$wp->request` 访问增加保护。
- 为新增 helper/bootstrap 函数补充 `function_exists` 防重定义保护。

### 7. 命名与兼容
- 将 `Folio_posts_per_page` 规范为 `folio_posts_per_page`。
- 保留旧函数名兼容代理，避免外部历史调用失效。

### 8. 缺陷修复（第二轮）
- 通知系统读状态修复：
  - 移除游客对 `folio_mark_notification_read` / `folio_mark_all_read` 的写操作入口。
  - 全局通知（`user_id = 0`）改为“按用户记录已读”，新增 `read_by_users` 字段并自动兼容迁移。
  - 游客可读取全局通知，但不再落库修改全局已读状态。
- 缓存接口冲突修复：
  - 旧性能管理器将 `folio_cache_stats` 迁移为 `folio_cache_stats_legacy`，避免与统一性能后台冲突。
  - `ajax_get_cache_stats` 与 `ajax_clear_cache` 增加 nonce 校验，补齐安全边界。
- 性能缓存 helper 修复：
  - `folio_clear_performance_cache()` 改为安全清理实现，避免调用不存在的方法导致致命错误。
- 重复 AJAX 注册收敛：
  - `cache-file-manager`、`cache-health-checker`、`memcached-helper` 在检测到统一性能后台类存在时仅保留兜底，不再主路径重复注册相同 action。
  - `ajax_clear_all_cache` 的 nonce 校验兼容 `folio_performance_admin`，避免历史入口误报安全失败。

### 9. 缺陷修复（第三轮）
- 修复用户中心通知页“全部已读”按钮 action 名称错误：
  - `folio_mark_all_notifications_read` -> `folio_mark_all_read`。
- 修复前端权限检查 action 命名不一致：
  - 前端主脚本统一使用 `folio_check_user_access_article`。
  - 后端新增 `folio_check_article_permission` 兼容注册，避免旧缓存脚本请求失败。
- 修复脚本管理器后台本地化 nonce 偏差：
  - `folioMembershipAdmin.nonce` 对齐 `folio_membership_admin`。
  - 元框本地化对象统一为 `folioMetaBox`，nonce 对齐 `folio_membership_metabox`。

### 10. 缺陷修复（第四轮）
- 统一性能后台增加 nonce 读取/校验封装，替换多处直接访问 `$_POST['nonce']`：
  - 新增 `get_request_nonce()` 与 `verify_request_nonce()`，减少未定义索引告警风险。
- 高频前端 AJAX 接口补充 nonce 判空与清洗：
  - `frontend-components` 的权限查询接口。
  - `post-stats` 的点赞/收藏接口。
  - `user-center` 的 AJAX 登录/注册接口。
  - `premium-content` 的内容解锁与文章徽章接口。
  - `cache-health-checker` 与 `cache-file-manager` 的通知 dismiss 接口。

## 兼容开关
- `folio_enable_style_manager_frontend`（默认 `false`）
  - 控制 `inc/class-style-manager.php` 的前台样式加载是否启用
- `folio_enable_portfolio_compat`（默认 `true`）
  - 控制 `portfolio` 内容模型兼容注册是否启用

## 最小回归检查清单

### 前台
- 首页/归档列表正常渲染。
- 访客访问分页 URL（如 `/page/2`）按预期跳转登录页。
- 主题初始化正常（`folioThemeInit` 可用）。
- 通知铃铛可打开弹窗并加载通知。
- 点赞/收藏在列表和单页交互正常。

### 用户中心
- `/user-center` 仪表盘正常渲染。
- 登录/注册/退出流程正常。
- 通知页正常加载，未读数量更新正常。
- 收藏页增删收藏功能正常。

### 后台
- 主题设置页可打开并保存。
- 通知管理页可打开并执行操作。
- 安全/性能管理页无脚本资源报错。

### 内容模型
- 在外部未注册场景下，`portfolio` 的归档/单页/分类路由可访问。
- 兼容注册后重写规则仅刷新一次。

## 备注
- 本目录最初不是 Git 仓库，已在本地初始化后创建分支。
- 已对修改文件执行 `php -l`；本机 PHP 扩展告警（`pdo_sqlsrv`、`imagick`）属于环境问题，不影响语法检查结果。
