# 缓存设置页面修复

## 问题描述

在使用缓存管理页面时，出现以下错误：
```
错误：选项页面 folio_cache_settings 不在允许列表中。
```

## 问题原因

缓存管理页面中使用了 `settings_fields('folio_cache_settings')` 来处理设置表单，但没有在WordPress中注册这个设置组，导致WordPress拒绝处理该设置页面。

## 修复方案

### 1. 添加设置注册钩子

在 `folio_Cache_Admin` 类的构造函数中添加：
```php
add_action('admin_init', array($this, 'register_settings'));
```

### 2. 实现设置注册方法

添加 `register_settings()` 方法来注册所有缓存相关的设置：

```php
public function register_settings() {
    // 注册设置组
    register_setting('folio_cache_settings', 'folio_cache_enabled', array(
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ));
    
    register_setting('folio_cache_settings', 'folio_permission_cache_expiry', array(
        'type' => 'integer',
        'default' => 3600,
        'sanitize_callback' => array($this, 'sanitize_cache_expiry')
    ));
    
    register_setting('folio_cache_settings', 'folio_preview_cache_expiry', array(
        'type' => 'integer',
        'default' => 86400,
        'sanitize_callback' => array($this, 'sanitize_cache_expiry')
    ));
    
    register_setting('folio_cache_settings', 'folio_query_cache_expiry', array(
        'type' => 'integer',
        'default' => 1800,
        'sanitize_callback' => array($this, 'sanitize_cache_expiry')
    ));
    
    register_setting('folio_cache_settings', 'folio_cache_auto_cleanup', array(
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ));
}
```

### 3. 添加数据清理方法

添加 `sanitize_cache_expiry()` 方法来确保缓存过期时间在合理范围内：

```php
public function sanitize_cache_expiry($value) {
    $value = intval($value);
    
    // 设置合理的范围
    if ($value < 300) {
        $value = 300; // 最少5分钟
    } elseif ($value > 604800) {
        $value = 604800; // 最多7天
    }
    
    return $value;
}
```

## 修复效果

### 修复前
- 设置表单提交时出现错误
- 无法保存缓存配置
- 用户体验不佳

### 修复后
- ✅ 设置表单正常工作
- ✅ 缓存配置可以保存
- ✅ 数据验证和清理
- ✅ 用户体验良好

## 验证方法

### 1. 功能测试
访问缓存管理页面，修改配置并保存，确认没有错误提示。

### 2. 自动测试
运行测试脚本验证设置功能：
```bash
访问: /wp-content/themes/folio/test-cache-settings.php
```

### 3. 手动验证
1. 进入 WordPress 后台
2. 导航到 "工具" → "缓存管理"
3. 滚动到 "缓存配置" 部分
4. 修改任意设置并点击 "保存配置"
5. 确认页面刷新后设置已保存

## 相关文件

- `folio/inc/class-cache-admin.php` - 主要修复文件
- `folio/test-cache-settings.php` - 测试验证文件
- `folio/docs/SETTINGS-FIX.md` - 本修复文档

## 技术说明

### WordPress设置API
WordPress要求所有通过 `settings_fields()` 处理的设置必须先通过 `register_setting()` 注册。这是WordPress的安全机制，防止未授权的设置被修改。

### 数据验证
通过 `sanitize_callback` 参数，我们确保：
- 布尔值正确转换
- 整数值在合理范围内
- 防止恶意数据注入

### 默认值处理
每个设置都有合理的默认值，确保系统在首次使用时有正确的配置。

---

**修复状态**: ✅ 已完成  
**测试状态**: ✅ 已验证  
**文档状态**: ✅ 已更新