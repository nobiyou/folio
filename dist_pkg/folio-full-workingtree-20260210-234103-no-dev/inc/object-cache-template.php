<?php
/**
 * Folio Memcached Object Cache Drop-in
 * 
 * 高性能Memcached对象缓存实现
 * 专为Folio主题优化
 *
 * @package Folio
 * @version 1.0
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 防止重复加载
if (defined('FOLIO_OBJECT_CACHE_LOADED')) {
    return;
}
define('FOLIO_OBJECT_CACHE_LOADED', true);

// 检查Memcached扩展
if (!class_exists('Memcached')) {
    return false;
}

// 检查是否已经有其他对象缓存实现
if (function_exists('wp_cache_get') && !defined('WP_INSTALLING')) {
    // 如果WordPress已经定义了缓存函数，说明可能有冲突
    // 在这种情况下，我们不重新定义函数，只是确保对象缓存可用
    if (!isset($GLOBALS['wp_object_cache'])) {
        $GLOBALS['wp_object_cache'] = new Folio_Memcached_Object_Cache();
    }
    return;
}

// 全局Memcached实例
global $wp_object_cache;

/**
 * Folio优化的Memcached对象缓存类
 */
class Folio_Memcached_Object_Cache {
    
    private $memcached;
    private $cache_hits = 0;
    private $cache_misses = 0;
    private $cache_group_ops = array();
    private $non_persistent_groups = array();
    private $multisite = false;
    private $blog_prefix = '';
    
    // Folio专用缓存组
    private $folio_groups = array(
        'folio_permissions',
        'folio_previews', 
        'folio_queries',
        'folio_stats'
    );
    
    public function __construct() {
        $this->multisite = is_multisite();
        $this->blog_prefix = $this->multisite ? get_current_blog_id() . ':' : '';
        
        $this->memcached = new Memcached('folio_cache');
        
        // 检查是否已经添加了服务器
        if (!count($this->memcached->getServerList())) {
            $this->add_servers();
        }
        
        $this->setup_memcached_options();
        $this->setup_non_persistent_groups();
    }
    
    /**
     * 添加Memcached服务器
     */
    private function add_servers() {
        global $memcached_servers;
        
        $servers = array();
        
        // 使用全局配置
        if (isset($memcached_servers['default'])) {
            foreach ($memcached_servers['default'] as $server) {
                if (strpos($server, ':') !== false) {
                    list($host, $port) = explode(':', $server);
                    $servers[] = array($host, (int)$port, 1);
                } else {
                    $servers[] = array($server, 11211, 1);
                }
            }
        } else {
            // 默认服务器
            $servers[] = array('127.0.0.1', 11211, 1);
        }
        
        $this->memcached->addServers($servers);
    }
    
    /**
     * 设置Memcached选项
     */
    private function setup_memcached_options() {
        $options = array(
            Memcached::OPT_COMPRESSION => true,
            Memcached::OPT_SERIALIZER => Memcached::SERIALIZER_PHP,
            Memcached::OPT_PREFIX_KEY => 'folio_',
            Memcached::OPT_HASH => Memcached::HASH_MD5,
            Memcached::OPT_DISTRIBUTION => Memcached::DISTRIBUTION_CONSISTENT,
            Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
            Memcached::OPT_BUFFER_WRITES => true,
            Memcached::OPT_BINARY_PROTOCOL => true,
            Memcached::OPT_NO_BLOCK => true,
            Memcached::OPT_TCP_NODELAY => true,
            Memcached::OPT_CONNECT_TIMEOUT => 1000,
            Memcached::OPT_RETRY_TIMEOUT => 2,
            Memcached::OPT_SEND_TIMEOUT => 3000000,
            Memcached::OPT_RECV_TIMEOUT => 3000000,
            Memcached::OPT_POLL_TIMEOUT => 1000,
        );
        
        // 设置压缩阈值（Folio优化：20KB以上启用压缩）
        if (defined('Memcached::OPT_COMPRESSION_THRESHOLD')) {
            $options[Memcached::OPT_COMPRESSION_THRESHOLD] = 20000;
        }
        
        $this->memcached->setOptions($options);
    }
    
    /**
     * 设置非持久化缓存组
     */
    private function setup_non_persistent_groups() {
        $this->non_persistent_groups = array(
            'comment', 'counts', 'plugins', 'themes', 'temp'
        );
    }
    
    /**
     * 获取缓存
     */
    public function get($key, $group = 'default') {
        $derived_key = $this->build_key($key, $group);
        
        // 非持久化组直接返回false
        if (in_array($group, $this->non_persistent_groups)) {
            $this->cache_misses++;
            return false;
        }
        
        $value = $this->memcached->get($derived_key);
        
        if ($this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            $this->cache_misses++;
            return false;
        }
        
        $this->cache_hits++;
        $this->group_ops_stats('get', $group, true);
        
        return $value;
    }
    
    /**
     * 设置缓存
     */
    public function set($key, $value, $group = 'default', $expiration = 0) {
        $derived_key = $this->build_key($key, $group);
        
        // 非持久化组不缓存
        if (in_array($group, $this->non_persistent_groups)) {
            return true;
        }
        
        // Folio优化：根据缓存组设置不同的过期时间
        if ($expiration === 0) {
            $expiration = $this->get_group_expiration($group);
        }
        
        $result = $this->memcached->set($derived_key, $value, $expiration);
        $this->group_ops_stats('set', $group, $result);
        
        return $result;
    }
    
    /**
     * 删除缓存
     */
    public function delete($key, $group = 'default') {
        $derived_key = $this->build_key($key, $group);
        
        $result = $this->memcached->delete($derived_key);
        $this->group_ops_stats('delete', $group, $result);
        
        return $result;
    }
    
    /**
     * 批量获取
     */
    public function get_multi($keys, $group = 'default') {
        if (in_array($group, $this->non_persistent_groups)) {
            return array_fill_keys($keys, false);
        }
        
        $derived_keys = array();
        foreach ($keys as $key) {
            $derived_keys[$key] = $this->build_key($key, $group);
        }
        
        $values = $this->memcached->getMulti(array_values($derived_keys));
        
        if (!$values) {
            $this->cache_misses += count($keys);
            return array_fill_keys($keys, false);
        }
        
        $result = array();
        foreach ($keys as $key) {
            $derived_key = $derived_keys[$key];
            if (isset($values[$derived_key])) {
                $result[$key] = $values[$derived_key];
                $this->cache_hits++;
            } else {
                $result[$key] = false;
                $this->cache_misses++;
            }
        }
        
        return $result;
    }
    
    /**
     * 批量设置
     */
    public function set_multi($items, $group = 'default', $expiration = 0) {
        if (in_array($group, $this->non_persistent_groups)) {
            return true;
        }
        
        $derived_items = array();
        foreach ($items as $key => $value) {
            $derived_key = $this->build_key($key, $group);
            $derived_items[$derived_key] = $value;
        }
        
        if ($expiration === 0) {
            $expiration = $this->get_group_expiration($group);
        }
        
        return $this->memcached->setMulti($derived_items, $expiration);
    }
    
    /**
     * 增加数值
     */
    public function incr($key, $offset = 1, $group = 'default') {
        $derived_key = $this->build_key($key, $group);
        
        $result = $this->memcached->increment($derived_key, $offset);
        
        // 如果键不存在，初始化为offset
        if ($result === false) {
            $this->set($key, $offset, $group);
            return $offset;
        }
        
        return $result;
    }
    
    /**
     * 减少数值
     */
    public function decr($key, $offset = 1, $group = 'default') {
        $derived_key = $this->build_key($key, $group);
        
        $result = $this->memcached->decrement($derived_key, $offset);
        
        // 如果键不存在，返回false
        if ($result === false) {
            return false;
        }
        
        return $result;
    }
    
    /**
     * 清空所有缓存
     */
    public function flush() {
        $result = $this->memcached->flush();
        
        // 重置统计
        $this->cache_hits = 0;
        $this->cache_misses = 0;
        $this->cache_group_ops = array();
        
        return $result;
    }
    
    /**
     * 构建缓存键
     */
    private function build_key($key, $group) {
        if (empty($group)) {
            $group = 'default';
        }
        
        $prefix = $this->blog_prefix . $group . ':';
        
        // Folio优化：为特殊组添加额外前缀
        if (in_array($group, $this->folio_groups)) {
            $prefix = 'folio_' . $prefix;
        }
        
        return $prefix . $key;
    }
    
    /**
     * 根据缓存组获取过期时间
     */
    private function get_group_expiration($group) {
        // Folio专用缓存组的过期时间
        $folio_expirations = array(
            'folio_permissions' => 3600,    // 1小时
            'folio_previews' => 86400,      // 24小时
            'folio_queries' => 1800,        // 30分钟
            'folio_stats' => 300,           // 5分钟
        );
        
        if (isset($folio_expirations[$group])) {
            return $folio_expirations[$group];
        }
        
        // WordPress默认缓存组
        $wp_expirations = array(
            'posts' => 3600,
            'terms' => 3600,
            'users' => 1800,
            'options' => 86400,
            'site-options' => 86400,
            'transient' => 43200, // 12小时
        );
        
        return isset($wp_expirations[$group]) ? $wp_expirations[$group] : 3600;
    }
    
    /**
     * 记录缓存组操作统计
     */
    private function group_ops_stats($operation, $group, $success) {
        if (!isset($this->cache_group_ops[$group])) {
            $this->cache_group_ops[$group] = array(
                'get' => 0, 'set' => 0, 'delete' => 0, 'get_multi' => 0, 'set_multi' => 0
            );
        }
        
        if ($success) {
            $this->cache_group_ops[$group][$operation]++;
        }
    }
    
    /**
     * 获取缓存统计
     */
    public function get_stats() {
        $total_requests = $this->cache_hits + $this->cache_misses;
        $hit_rate = $total_requests > 0 ? ($this->cache_hits / $total_requests) * 100 : 0;
        
        return array(
            'hits' => $this->cache_hits,
            'misses' => $this->cache_misses,
            'hit_rate' => round($hit_rate, 2),
            'group_ops' => $this->cache_group_ops,
            'memcached_stats' => $this->get_memcached_stats()
        );
    }
    
    /**
     * 获取Memcached服务器统计
     */
    public function get_memcached_stats() {
        $stats = $this->memcached->getStats();
        return $stats ? reset($stats) : false;
    }
    
    /**
     * 添加非持久化组
     */
    public function add_non_persistent_groups($groups) {
        $groups = (array) $groups;
        $this->non_persistent_groups = array_unique(array_merge($this->non_persistent_groups, $groups));
    }
    
    /**
     * 切换博客（多站点支持）
     */
    public function switch_to_blog($blog_id) {
        if ($this->multisite) {
            $this->blog_prefix = $blog_id . ':';
        }
    }
}

// 初始化对象缓存
$wp_object_cache = new Folio_Memcached_Object_Cache();

/**
 * WordPress缓存API函数
 * 
 * 注意：这些函数只有在WordPress核心未定义时才会被定义
 * 这样可以避免与WordPress核心函数冲突
 */

// 检查WordPress是否已经加载了缓存函数
if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '') {
        global $wp_object_cache;
        return $wp_object_cache->get($key, $group);
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        global $wp_object_cache;
        return $wp_object_cache->set($key, $data, $group, $expire);
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        global $wp_object_cache;
        return $wp_object_cache->delete($key, $group);
    }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() {
        global $wp_object_cache;
        return $wp_object_cache->flush();
    }
}

if (!function_exists('wp_cache_add')) {
    function wp_cache_add($key, $data, $group = '', $expire = 0) {
        global $wp_object_cache;
        // 如果键已存在，不覆盖
        if ($wp_object_cache->get($key, $group) !== false) {
            return false;
        }
        return $wp_object_cache->set($key, $data, $group, $expire);
    }
}

if (!function_exists('wp_cache_replace')) {
    function wp_cache_replace($key, $data, $group = '', $expire = 0) {
        global $wp_object_cache;
        // 只有键存在时才替换
        if ($wp_object_cache->get($key, $group) === false) {
            return false;
        }
        return $wp_object_cache->set($key, $data, $group, $expire);
    }
}

if (!function_exists('wp_cache_get_multi')) {
    function wp_cache_get_multi($keys, $group = '') {
        global $wp_object_cache;
        if (method_exists($wp_object_cache, 'get_multi')) {
            return $wp_object_cache->get_multi($keys, $group);
        }
        
        // 降级处理：逐个获取
        $result = array();
        foreach ($keys as $key) {
            $result[$key] = $wp_object_cache->get($key, $group);
        }
        return $result;
    }
}

if (!function_exists('wp_cache_set_multi')) {
    function wp_cache_set_multi($data, $group = '', $expire = 0) {
        global $wp_object_cache;
        if (method_exists($wp_object_cache, 'set_multi')) {
            return $wp_object_cache->set_multi($data, $group, $expire);
        }
        
        // 降级处理：逐个设置
        $success = true;
        foreach ($data as $key => $value) {
            if (!$wp_object_cache->set($key, $value, $group, $expire)) {
                $success = false;
            }
        }
        return $success;
    }
}

if (!function_exists('wp_cache_incr')) {
    function wp_cache_incr($key, $offset = 1, $group = '') {
        global $wp_object_cache;
        if (method_exists($wp_object_cache, 'incr')) {
            return $wp_object_cache->incr($key, $offset, $group);
        }
        
        // 降级处理
        $value = $wp_object_cache->get($key, $group);
        if ($value === false) {
            $wp_object_cache->set($key, $offset, $group);
            return $offset;
        }
        
        $new_value = (int)$value + $offset;
        $wp_object_cache->set($key, $new_value, $group);
        return $new_value;
    }
}

if (!function_exists('wp_cache_decr')) {
    function wp_cache_decr($key, $offset = 1, $group = '') {
        global $wp_object_cache;
        if (method_exists($wp_object_cache, 'decr')) {
            return $wp_object_cache->decr($key, $offset, $group);
        }
        
        // 降级处理
        $value = $wp_object_cache->get($key, $group);
        if ($value === false) {
            return false;
        }
        
        $new_value = max(0, (int)$value - $offset);
        $wp_object_cache->set($key, $new_value, $group);
        return $new_value;
    }
}

if (!function_exists('wp_cache_add_non_persistent_groups')) {
    function wp_cache_add_non_persistent_groups($groups) {
        global $wp_object_cache;
        if (method_exists($wp_object_cache, 'add_non_persistent_groups')) {
            return $wp_object_cache->add_non_persistent_groups($groups);
        }
        return true;
    }
}

if (!function_exists('wp_cache_switch_to_blog')) {
    function wp_cache_switch_to_blog($blog_id) {
        global $wp_object_cache;
        if (method_exists($wp_object_cache, 'switch_to_blog')) {
            return $wp_object_cache->switch_to_blog($blog_id);
        }
        return true;
    }
}

if (!function_exists('wp_cache_close')) {
    function wp_cache_close() {
        return true;
    }
}

if (!function_exists('wp_cache_init')) {
    function wp_cache_init() {
        global $wp_object_cache;
        if (!isset($wp_object_cache)) {
            $wp_object_cache = new Folio_Memcached_Object_Cache();
        }
    }
}

/**
 * Folio专用缓存函数
 */
if (!function_exists('folio_cache_get_stats')) {
    function folio_cache_get_stats() {
        global $wp_object_cache;
        if (method_exists($wp_object_cache, 'get_stats')) {
            return $wp_object_cache->get_stats();
        }
        return false;
    }
}

// 设置WordPress使用外部对象缓存
if (!defined('WP_CACHE')) {
    define('WP_CACHE', true);
}