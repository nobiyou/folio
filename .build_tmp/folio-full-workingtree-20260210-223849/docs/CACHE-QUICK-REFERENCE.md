# Folio缓存系统 - 快速参考

## 🚀 快速开始

### 1分钟启用缓存
```bash
# 1. 确认Memcached已安装
php -m | grep memcached

# 2. 访问WordPress管理后台
# 工具 → 缓存管理

# 3. 点击"安装Folio对象缓存"按钮
# 完成！
```

## 📋 常用操作

### 清除缓存
| 操作 | 位置 | 说明 |
|------|------|------|
| 清除所有缓存 | 缓存管理 → 清除所有缓存 | 清空所有缓存数据 |
| 清除权限缓存 | 缓存管理 → 权限验证缓存 → 清除 | 用户权限变更后 |
| 清除预览缓存 | 缓存管理 → 内容预览缓存 → 清除 | 文章内容更新后 |
| 清除查询缓存 | 缓存管理 → 查询缓存 → 清除 | 数据结构变更后 |

### 性能监控
```
访问路径: 工具 → 缓存管理

关键指标:
• 总体命中率: > 80% 为良好
• 缓存条目数: 根据网站规模
• 性能提升: 显示优化效果
• 缓存后端: Memcached/Redis/内置
```

## 🔧 配置参数

### 推荐配置
```php
// wp-config.php 或主题配置

// 启用缓存
define('FOLIO_CACHE_ENABLED', true);

// 权限缓存 - 1小时
define('FOLIO_PERMISSION_CACHE_EXPIRY', 3600);

// 预览缓存 - 24小时
define('FOLIO_PREVIEW_CACHE_EXPIRY', 86400);

// 查询缓存 - 30分钟
define('FOLIO_QUERY_CACHE_EXPIRY', 1800);

// 自动清理
define('FOLIO_CACHE_AUTO_CLEANUP', true);
```

### 性能调优
| 场景 | 推荐配置 | 说明 |
|------|----------|------|
| 高流量网站 | 增加过期时间 | 减少缓存重建频率 |
| 内容更新频繁 | 减少过期时间 | 保持内容新鲜度 |
| 内存有限 | 减少缓存条目 | 控制内存使用 |
| 会员网站 | 优化权限缓存 | 提升权限检查速度 |

## 🛠️ 故障排除

### 问题诊断流程
```
1. 检查缓存状态
   工具 → 缓存管理 → 查看状态

2. 运行测试工具
   访问: /wp-content/themes/folio/test-cache-management.php

3. 查看错误日志
   启用 WP_DEBUG 查看详细日志

4. 重置缓存
   清除所有缓存 → 重新测试
```

### 常见问题速查

#### 缓存未生效
```bash
# 检查Memcached服务
systemctl status memcached

# 检查PHP扩展
php -m | grep memcached

# 检查object-cache.php
ls -la /wp-content/object-cache.php
```

#### 性能提升不明显
```
1. 检查缓存命中率 (应 > 70%)
2. 调整缓存过期时间
3. 启用更多缓存类型
4. 检查服务器资源
```

#### 内存使用过高
```
1. 减少缓存过期时间
2. 限制缓存条目数量
3. 启用自动清理
4. 优化缓存键设计
```

## 📊 性能指标

### 健康指标
| 指标 | 优秀 | 良好 | 需改进 |
|------|------|------|--------|
| 缓存命中率 | > 90% | 70-90% | < 70% |
| 页面加载时间 | < 1s | 1-2s | > 2s |
| 数据库查询 | < 15次 | 15-30次 | > 30次 |
| 内存使用 | < 100MB | 100-200MB | > 200MB |

### 监控建议
- **每日**: 查看缓存命中率
- **每周**: 分析性能趋势
- **每月**: 优化缓存配置
- **季度**: 全面性能评估

## 🔐 安全建议

### 权限控制
```php
// 只允许管理员访问缓存管理
if (!current_user_can('manage_options')) {
    wp_die('权限不足');
}
```

### 数据保护
- 敏感数据不缓存
- 用户特定数据隔离
- 定期清理过期缓存
- 监控异常访问

## 📱 API参考

### 常用函数
```php
// 检查用户访问权限
folio_can_user_access_article($post_id, $user_id);

// 生成文章预览
folio_generate_article_preview($content, $settings);

// 批量获取保护信息
folio_get_bulk_protection_info($post_ids);

// 批量检查权限
folio_check_bulk_permissions($post_ids, $user_id);

// 清除特定缓存
wp_cache_delete($key, $group);

// 获取缓存统计
folio_Performance_Cache_Manager::get_cache_statistics();
```

### 缓存操作
```php
// 设置缓存
wp_cache_set($key, $value, $group, $expiration);

// 获取缓存
$value = wp_cache_get($key, $group);

// 删除缓存
wp_cache_delete($key, $group);

// 清空分组
wp_cache_flush_group($group);
```

## 🎯 最佳实践

### DO ✅
- 定期监控缓存性能
- 内容更新后清理相关缓存
- 根据访问模式调整配置
- 备份重要的缓存配置
- 使用批量操作减少查询

### DON'T ❌
- 不要缓存敏感用户数据
- 不要设置过长的过期时间
- 不要忽略缓存命中率
- 不要在生产环境频繁清理
- 不要缓存实时变化的数据

## 📞 获取帮助

### 文档
- [完整优化总结](CACHE-MANAGEMENT-OPTIMIZATION-SUMMARY.md)
- [快速开始指南](CACHE-QUICK-START.md)
- [Memcached设置](MEMCACHED-SETUP-GUIDE.md)
- [故障排除](CACHE-TROUBLESHOOTING.md)

### 测试工具
```bash
# 功能测试
/wp-content/themes/folio/test-cache-management.php

# 函数测试
/wp-content/themes/folio/test-function-fixes.php

# 性能测试
工具 → 缓存性能测试
```

### 支持渠道
- WordPress管理后台工单
- Folio主题社区论坛
- GitHub Issues
- 技术文档反馈

---

**提示**: 将此页面加入书签，随时查阅！