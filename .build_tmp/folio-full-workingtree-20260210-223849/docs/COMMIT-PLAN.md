# 提交计划（建议）

## 目标
- 将本轮重构按“低风险、可回滚”原则拆成少量提交。
- 每个提交关注单一主题，便于代码评审和问题定位。

## 改动总览（按文件）
- `functions.php`
- `inc/class-user-center.php`
- `user-center.php`
- `user-center/notifications.php`
- `inc/class-post-stats.php`
- `inc/class-script-manager.php`
- `inc/class-style-manager.php`
- `inc/class-membership-notifications.php`
- `inc/class-helper-manager.php`
- `inc/class-notification-admin.php`
- `docs/REFACTOR-NOTES.md`

## 建议提交切分

### Commit 1：启动流程与资源加载重构
建议提交说明：
- `refactor(core): 重构 functions 启动流程并收敛前端资源加载`

建议包含文件：
- `functions.php`
- `inc/class-script-manager.php`
- `inc/class-style-manager.php`
- `inc/class-post-stats.php`

建议包含内容：
- `functions.php` 启动流程 bootstrap 化（内容模型/资源/路由/兼容 hooks 分组）
- 资源加载拆分为小函数（字体/基础样式/条件样式/初始化脚本/本地化）
- 资源版本 helper 与主题设置请求内缓存
- `folio_user`/`folio_ajax` 本地化职责收敛到脚本管理器
- 缺失脚本的“存在才加载”保护
- `Folio_posts_per_page` 规范命名为 `folio_posts_per_page`（保留兼容包装）

### Commit 2：单例化与重复初始化收敛
建议提交说明：
- `refactor(runtime): 收敛 user-center/notifications 重复实例化`

建议包含文件：
- `inc/class-user-center.php`
- `user-center.php`
- `user-center/notifications.php`
- `inc/class-membership-notifications.php`
- `inc/class-helper-manager.php`
- `inc/class-notification-admin.php`

建议包含内容：
- `folio_User_Center` 单例化并替换主要调用点
- `folio_Membership_Notifications` 增加单例入口与一次性初始化保护
- 通知相关调用统一为 `get_instance()`
- `$wp->request` 访问防护，减少 notice 风险

### Commit 3：文档与回归清单
建议提交说明：
- `docs: 补充中文重构说明与回归检查清单`

建议包含文件：
- `docs/REFACTOR-NOTES.md`
- `docs/COMMIT-PLAN.md`

建议包含内容：
- 中文重构说明
- 兼容开关说明
- 最小回归检查清单
- 提交切分建议

## 建议提交流程（命令示例）
```bash
# Commit 1
git add functions.php inc/class-script-manager.php inc/class-style-manager.php inc/class-post-stats.php
git commit -m "refactor(core): 重构 functions 启动流程并收敛前端资源加载"

# Commit 2
git add inc/class-user-center.php user-center.php user-center/notifications.php inc/class-membership-notifications.php inc/class-helper-manager.php inc/class-notification-admin.php
git commit -m "refactor(runtime): 收敛 user-center/notifications 重复实例化"

# Commit 3
git add docs/REFACTOR-NOTES.md docs/COMMIT-PLAN.md
git commit -m "docs: 补充中文重构说明与回归检查清单"
```

## 提交前检查（最小）
- `php -l functions.php`
- `php -l inc/class-user-center.php`
- `php -l inc/class-membership-notifications.php`
- 关键页面手测：
  - 首页/归档
  - `/user-center` 登录与仪表盘
  - 用户中心通知页与未读数量
  - 后台主题设置页、通知管理页
