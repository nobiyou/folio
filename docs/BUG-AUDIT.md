# 主题缺陷审计报告（阶段性）

## 审计时间
- 2026-02-09
- 分支：`refactor/core-stability`

## 审计范围
- 前端/后台 AJAX action 与 nonce 一致性
- 通知系统读状态与权限边界
- 缓存与性能管理模块的重复注册冲突
- PHP 8.2 下常见的 `$_POST` 未定义索引风险

## 已修复问题（按批次）

### 第一批（核心缺陷）
- 修复全局通知被游客“全局已读”污染的问题（改为按用户记录已读）。
- 关闭游客对通知“标记已读/全部已读”的写入口。
- 修复 `folio_cache_stats` action 冲突（旧接口迁移为 `folio_cache_stats_legacy`）。
- 修复 `folio_clear_performance_cache()` 调用不存在方法的致命风险。

对应提交：
- `2cd83ad`

### 第二批（性能后台冲突与兼容）
- 收敛对象缓存安装/卸载、健康检查、memcached 状态的重复 AJAX 注册。
- `ajax_clear_all_cache` nonce 校验兼容旧入口。

对应提交：
- `db230fe`

### 第三批（前端 action/nonce 对齐）
- 修复用户中心通知页“全部已读”错误 action 名。
- 修复权限检查 action 前后端命名不一致，并补后端兼容 action。
- 修复后台脚本本地化对象名/nonce 名与后端校验值不一致。

对应提交：
- `dedc222`
- `2556023`

### 第四批（高频接口防御）
- 统一性能后台引入统一 nonce 读取/校验封装。
- 高频 AJAX 接口补齐 nonce 判空与清洗（点赞收藏、用户登录注册、权限检查、解锁内容等）。

对应提交：
- `262b3a8`

### 第五批（低频接口防御）
- 低频后台接口补齐 nonce 判空与清洗（AI SEO、SEO 管理、缓存自动验证、后端验证、前端优化等）。
- cache-file-manager 的安装/卸载/状态检查接口统一 nonce 清洗模式。

对应提交：
- `8023855`

### 第六批（通用 AJAX 封装收敛）
- `FolioCore.ajax()` 默认不再自动注入全局 nonce。
- 新增 `nonceMode` / `nonceField` / `nonceValue` 显式注入策略，降低跨接口误注入风险。

对应提交：
- （当前待提交）

## 当前残余风险（未发现阻断级）

### 中风险
- 暂未发现新的中风险阻断项。

### 低风险
- 代码库仍保留历史脚本 `assets/js/frontend-components-original.js`（含旧 action 约定）。
  - 当前未发现主路径加载，但后续若误接入可能引入已修复过的旧行为。

## 建议优先级
1. 已完成：`assets/js/folio-core.min.js` 已与 `folio-core.js` 同步构建。
2. 清理或显式标记历史脚本（如 `frontend-components-original.js`）为废弃，避免误用。
3. 增加最小集成回归（通知、用户中心、性能后台关键按钮）并纳入发布前检查。

## 结论
- 本轮重构后，已定位并修复的功能缺陷集中在“通知读状态模型、AJAX action 冲突、nonce 一致性、防御性校验”四大类。
- 当前未发现新的阻断级缺陷；剩余风险以“未来维护可预防问题”为主，建议按优先级逐步收敛。
