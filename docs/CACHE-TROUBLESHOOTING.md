# 🔧 Folio缓存系统故障排查指南

## 📋 概述

本指南帮助你解决Folio缓存系统可能遇到的各种问题，包括安装、配置和运行时错误。

---

## 🚨 常见错误及解决方案

### 1. PHP Fatal Error: Cannot redeclare wp_cache_set()

#### 错误信息
```
PHP Fatal error: Cannot redeclare wp_cache_set() (previously declared in /wp-content/object-cache.php:405) in /wp-includes/cache.php on line 108
```

#### 原因分析
- WordPress缓存函数被重复声明
- 通常发生在已有其他对象缓存插件的情况下
- 或者object-cache.php文件有语法错误

#### 解决方案

##### 方案1：通过管理界面解决
1. 访问 **工具 → 缓存管理**
2. 查看"对象缓存管理"部分
3. 如果显示"第三方版本"，点击"替换为Folio版本"
4. 如果操作失败，使用方案2

##### 方案2：手动处理
```bash
# 1. 备份现有文件
cp wp-content/object-cache.php wp-content/object-cache-backup.php

# 2. 删除现有文件
rm wp-content/object-cache.php

# 3. 重新访问网站，检查是否正常

# 4. 通过管理界面重新安装Folio版本
```

##### 方案3：检查文件冲突
```bash
# 检查是否有多个缓存插件
ls -la wp-content/plugins/ | grep -i cache

# 停用所有缓存插件
# 然后重新安装Folio对象缓存
```

### 2. Memcached连接失败

#### 错误症状
- 缓存管理页面显示"❌ 服务不可达"
- 网站性能没有提升
- 缓存命中率为0%

#### 解决方案

##### 检查Memcached服务
```bash
# 检查服务状态
systemctl status memcached

# 如果未运行，启动服务
sudo systemctl start memcached
sudo systemctl enable memcached

# 检查端口监听
netstat -tlnp | grep 11211
```

##### 检查PHP扩展
```bash
# 检查扩展是否安装
php -m | grep memcached

# 如果未安装
sudo apt-get install php-memcached  # Ubuntu/Debian
sudo yum install php-memcached      # CentOS/RHEL

# 重启Web服务器
sudo systemctl restart apache2  # 或 nginx
```

##### 测试连接
```bash
# 使用telnet测试
telnet 127.0.0.1 11211

# 应该看到连接成功的提示
# 输入 "version" 查看版本
# 输入 "quit" 退出
```

### 3. 缓存不生效

#### 症状
- 网站速度没有明显提升
- 缓存命中率很低
- 数据库查询数量没有减少

#### 排查步骤

##### 1. 检查对象缓存状态
```php
// 在主题的functions.php中临时添加
add_action('wp_footer', function() {
    if (current_user_can('manage_options')) {
        echo '<!-- Object Cache: ' . (wp_using_ext_object_cache() ? 'Enabled' : 'Disabled') . ' -->';
        
        if (function_exists('folio_cache_get_stats')) {
            $stats = folio_cache_get_stats();
            echo '<!-- Cache Stats: ' . json_encode($stats) . ' -->';
        }
    }
});
```

##### 2. 检查缓存配置
访问 **工具 → 缓存管理**，查看：
- 对象缓存状态是否为"已安装"
- Memcached支持是否为"可用"
- 缓存命中率是否正常

##### 3. 手动测试缓存
```php
// 在WordPress中测试
$test_key = 'folio_test_' . time();
$test_value = 'test_data_' . rand(1000, 9999);

// 设置缓存
wp_cache_set($test_key, $test_value, '', 300);

// 获取缓存
$retrieved = wp_cache_get($test_key);

if ($retrieved === $test_value) {
    echo "缓存工作正常";
} else {
    echo "缓存不工作";
}

// 清理测试数据
wp_cache_delete($test_key);
```

### 4. 权限问题

#### 错误信息
```
wp-content目录不可写
复制文件失败
```

#### 解决方案
```bash
# 检查目录权限
ls -la wp-content/

# 设置正确权限
sudo chown -R www-data:www-data wp-content/
sudo chmod 755 wp-content/

# 或者使用你的Web服务器用户
sudo chown -R apache:apache wp-content/  # CentOS
```

### 5. 内存不足

#### 症状
- 网站出现500错误
- 错误日志显示内存不足
- 缓存频繁被驱逐

#### 解决方案

##### 增加PHP内存限制
```php
// wp-config.php
ini_set('memory_limit', '256M');
// 或
define('WP_MEMORY_LIMIT', '256M');
```

##### 增加Memcached内存
```bash
# 编辑配置文件
sudo nano /etc/memcached.conf

# 修改内存分配
-m 512  # 分配512MB内存

# 重启服务
sudo systemctl restart memcached
```

---

## 🔍 诊断工具

### 1. 缓存状态检查脚本

创建文件 `cache-check.php`：
```php
<?php
// 临时诊断脚本
require_once 'wp-config.php';
require_once ABSPATH . 'wp-settings.php';

echo "=== Folio缓存系统诊断 ===\n";

// 检查基本状态
echo "WordPress版本: " . get_bloginfo('version') . "\n";
echo "PHP版本: " . PHP_VERSION . "\n";
echo "对象缓存: " . (wp_using_ext_object_cache() ? '启用' : '禁用') . "\n";

// 检查扩展
echo "Memcached扩展: " . (class_exists('Memcached') ? '可用' : '不可用') . "\n";
echo "Redis扩展: " . (class_exists('Redis') ? '可用' : '不可用') . "\n";

// 检查文件
$object_cache_file = WP_CONTENT_DIR . '/object-cache.php';
echo "Object-cache.php: " . (file_exists($object_cache_file) ? '存在' : '不存在') . "\n";

if (file_exists($object_cache_file)) {
    $content = file_get_contents($object_cache_file);
    echo "文件类型: " . (strpos($content, 'Folio') !== false ? 'Folio版本' : '其他版本') . "\n";
    echo "文件大小: " . size_format(filesize($object_cache_file)) . "\n";
}

// 测试缓存功能
if (function_exists('wp_cache_set')) {
    $test_key = 'diagnostic_test_' . time();
    $test_value = 'test_' . rand(1000, 9999);
    
    wp_cache_set($test_key, $test_value, '', 60);
    $retrieved = wp_cache_get($test_key);
    
    echo "缓存测试: " . ($retrieved === $test_value ? '通过' : '失败') . "\n";
    wp_cache_delete($test_key);
}

// Folio特定检查
if (function_exists('folio_cache_get_stats')) {
    $stats = folio_cache_get_stats();
    echo "Folio缓存统计: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n";
}

echo "=== 诊断完成 ===\n";
?>
```

运行诊断：
```bash
php cache-check.php
```

### 2. 性能基准测试

```php
// 性能测试脚本
function benchmark_cache_performance() {
    $iterations = 1000;
    $data = str_repeat('x', 1024); // 1KB数据
    
    // 测试写入性能
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        wp_cache_set("bench_key_$i", $data, '', 3600);
    }
    $write_time = microtime(true) - $start;
    
    // 测试读取性能
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        wp_cache_get("bench_key_$i");
    }
    $read_time = microtime(true) - $start;
    
    // 清理
    for ($i = 0; $i < $iterations; $i++) {
        wp_cache_delete("bench_key_$i");
    }
    
    return array(
        'write_ops_per_sec' => $iterations / $write_time,
        'read_ops_per_sec' => $iterations / $read_time,
        'write_time_ms' => $write_time * 1000,
        'read_time_ms' => $read_time * 1000
    );
}
```

---

## 📊 监控和维护

### 1. 日常检查清单

#### 每日检查
- [ ] 缓存命中率 >80%
- [ ] Memcached服务运行正常
- [ ] 网站响应时间正常
- [ ] 错误日志无异常

#### 每周检查
- [ ] 缓存内存使用情况
- [ ] 驱逐率是否正常
- [ ] 连接数是否合理
- [ ] 性能统计趋势

#### 每月检查
- [ ] 更新缓存配置
- [ ] 清理过期数据
- [ ] 优化缓存策略
- [ ] 备份配置文件

### 2. 监控脚本

```bash
#!/bin/bash
# cache-monitor.sh

LOG_FILE="/var/log/folio-cache-monitor.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

echo "[$DATE] 开始缓存监控" >> $LOG_FILE

# 检查Memcached状态
if ! pgrep memcached > /dev/null; then
    echo "[$DATE] 错误: Memcached服务未运行" >> $LOG_FILE
    systemctl start memcached
fi

# 检查内存使用
MEMORY_USAGE=$(echo "stats" | nc 127.0.0.1 11211 | grep "bytes " | awk '{print $3}')
MEMORY_LIMIT=$(echo "stats" | nc 127.0.0.1 11211 | grep "limit_maxbytes" | awk '{print $3}')

if [ ! -z "$MEMORY_USAGE" ] && [ ! -z "$MEMORY_LIMIT" ]; then
    USAGE_PERCENT=$((MEMORY_USAGE * 100 / MEMORY_LIMIT))
    echo "[$DATE] 内存使用率: ${USAGE_PERCENT}%" >> $LOG_FILE
    
    if [ $USAGE_PERCENT -gt 90 ]; then
        echo "[$DATE] 警告: 内存使用率过高 (${USAGE_PERCENT}%)" >> $LOG_FILE
    fi
fi

# 检查命中率
HITS=$(echo "stats" | nc 127.0.0.1 11211 | grep "get_hits" | awk '{print $3}')
MISSES=$(echo "stats" | nc 127.0.0.1 11211 | grep "get_misses" | awk '{print $3}')

if [ ! -z "$HITS" ] && [ ! -z "$MISSES" ]; then
    TOTAL=$((HITS + MISSES))
    if [ $TOTAL -gt 0 ]; then
        HIT_RATE=$((HITS * 100 / TOTAL))
        echo "[$DATE] 缓存命中率: ${HIT_RATE}%" >> $LOG_FILE
        
        if [ $HIT_RATE -lt 70 ]; then
            echo "[$DATE] 警告: 缓存命中率过低 (${HIT_RATE}%)" >> $LOG_FILE
        fi
    fi
fi

echo "[$DATE] 缓存监控完成" >> $LOG_FILE
```

设置定时任务：
```bash
# 添加到crontab
crontab -e

# 每5分钟检查一次
*/5 * * * * /path/to/cache-monitor.sh
```

---

## 🆘 紧急恢复

### 如果网站完全无法访问

#### 1. 立即恢复
```bash
# 删除object-cache.php
rm wp-content/object-cache.php

# 网站应该立即恢复正常
```

#### 2. 恢复备份
```bash
# 如果有备份文件
mv wp-content/object-cache-backup.php wp-content/object-cache.php
```

#### 3. 禁用所有缓存
```php
// 在wp-config.php中添加
define('WP_CACHE', false);
```

### 数据恢复

如果缓存数据丢失：
1. 缓存数据通常是临时的，丢失不会影响网站功能
2. 重新访问页面会自动重建缓存
3. 可以使用缓存预热功能快速重建

---

## 📞 获取帮助

### 1. 日志文件位置
- **PHP错误日志**: `/var/log/php_errors.log`
- **Apache错误日志**: `/var/log/apache2/error.log`
- **Nginx错误日志**: `/var/log/nginx/error.log`
- **Memcached日志**: `/var/log/memcached.log`

### 2. 调试模式
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### 3. 收集诊断信息
在报告问题时，请提供：
- WordPress版本
- PHP版本
- 服务器操作系统
- Memcached版本
- 错误日志内容
- 缓存管理页面截图

---

## 🎯 预防措施

### 1. 定期备份
```bash
# 备份object-cache.php
cp wp-content/object-cache.php wp-content/object-cache-$(date +%Y%m%d).php

# 备份配置
cp wp-config.php wp-config-$(date +%Y%m%d).php
```

### 2. 测试环境
- 在测试环境中先验证缓存配置
- 使用相同的服务器环境
- 测试各种场景和负载

### 3. 监控告警
- 设置缓存命中率告警
- 监控内存使用情况
- 关注错误日志

通过遵循这些故障排查步骤，你应该能够解决大部分Folio缓存系统相关的问题。如果问题仍然存在，请参考相关文档或寻求技术支持。