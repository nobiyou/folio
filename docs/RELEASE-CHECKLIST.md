# 发布检查清单（refactor/core-stability）

## 发布信息
- 分支：`refactor/core-stability`
- 基线起点：`fb8652c`
- 当前状态：工作区干净，可发布候选

## 提交清单（按时间）
1. `59884df` `refactor(runtime): 收敛 user-center/notifications 重复实例化`
2. `866eebb` `docs: 补充中文重构说明与回归检查清单`
3. `2c46e02` `chore(repo): 添加主题其余基线文件`
4. `2cd83ad` `fix(core): 修复通知读状态与缓存接口冲突及安全问题`
5. `db230fe` `fix(perf): 收敛缓存AJAX冲突并补齐兼容校验`
6. `dedc222` `fix(ui): 修复通知与权限检查AJAX action命名错误`
7. `2556023` `fix(admin): 对齐后台脚本nonce与元框本地化对象`
8. `262b3a8` `fix(security): 补齐高频AJAX nonce判空与统一校验`
9. `8023855` `fix(security): 补齐低频后台接口nonce防御与清洗`
10. `9de6c4e` `docs(audit): 新增阶段性缺陷审计报告`
11. `e47dca8` `refactor(js): 收敛FolioCore.ajax的nonce注入策略`
12. `7140638` `build(js): 同步更新folio-core压缩文件`
13. `84d8329` `chore(legacy): 默认禁用废弃前端组件旧脚本`

## 自动检查（发布前必须）
1. PHP 语法检查：
   - `php -l functions.php`
   - `Get-ChildItem inc -Recurse -Filter *.php | % { php -l $_.FullName }`
2. 关键 JS 语法检查：
   - `node --check assets/js/folio-core.js`
   - `node --check assets/js/frontend-components.js`
   - `node --check assets/js/notifications.js`
3. Git 状态确认：
   - `git status --short` 为空

## 手工回归（发布前必须）
1. 前台：
   - 首页/归档正常。
   - 点赞、收藏按钮可用。
   - 通知铃铛可拉取通知，已读/全部已读行为正常。
2. 用户中心：
   - 登录/注册/退出流程正常。
   - 通知页单条已读、全部已读、删除可用。
   - 收藏页增删收藏正常。
3. 后台：
   - 性能与缓存页所有按钮无“安全验证失败”误报。
   - 主题选项页、通知管理页打开和保存正常。
4. 权限相关：
   - 会员保护文章提示逻辑正常。
   - 升级后权限状态刷新正常。

## 发布后观察项（建议）
1. 观察 PHP 错误日志是否出现新增 `Undefined index: nonce`。
2. 观察管理端 AJAX 错误率（403/500）是否上升。
3. 观察通知已读/未读数据是否异常波动。

## 回滚方案
1. 整体回滚到基线：
   - `git checkout fb8652c`
2. 分批回滚（推荐）：
   - 先回滚最后 3 个提交观察：
     - `84d8329`
     - `7140638`
     - `e47dca8`
3. 回滚后执行：
   - `php -l` 快速巡检
   - 清理站点缓存与对象缓存
   - 重新验证通知与性能后台关键流程

## 关联文档
- `docs/REFACTOR-NOTES.md`
- `docs/BUG-AUDIT.md`
- `docs/COMMIT-PLAN.md`
