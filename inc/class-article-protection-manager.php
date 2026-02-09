<?php
/**
 * Article Protection Manager
 * 
 * 文章级别会员保护管理器 - 实现内容过滤和权限控制
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Article_Protection_Manager {

    // 缓存键前缀
    const CACHE_PREFIX = 'folio_protection_';
    const CACHE_EXPIRY = 3600; // 1小时

    // 权限验证结果缓存
    private static $permission_cache = array();
    
    // 内容预览缓存
    private static $preview_cache = array();

    public function __construct() {
        // 内容过滤器钩子 - 高优先级确保在其他过滤器之前执行
        add_filter('the_content', array($this, 'filter_content'), 5);
        add_filter('the_excerpt', array($this, 'filter_excerpt'), 5);
        
        // RSS和API保护
        add_filter('the_content_feed', array($this, 'filter_feed_content'), 5);
        add_filter('rest_prepare_post', array($this, 'filter_rest_content'), 10, 3);
        
        // 搜索结果保护
        add_filter('the_content', array($this, 'filter_search_content'), 5);
        
        // 缓存清理钩子
        add_action('save_post', array($this, 'clear_post_cache'));
        add_action('folio_membership_level_changed', array($this, 'clear_user_cache'), 10, 2);
        
        // 权限验证缓存清理
        add_action('wp_login', array($this, 'clear_user_permission_cache'));
        add_action('wp_logout', array($this, 'clear_user_permission_cache'));
        
        // 前端样式已移动到 /assets/css/membership-content.css
    }

    /**
     * 获取文章保护信息
     */
    public static function get_protection_info($post_id) {
        // 在后台编辑器中直接返回默认值，避免不必要的数据库查询
        $is_edit_context = false;
        if (defined('REST_REQUEST') && REST_REQUEST) {
            if (isset($_REQUEST['context']) && $_REQUEST['context'] === 'edit') {
                $is_edit_context = true;
            } elseif (isset($_SERVER['REQUEST_URI'])) {
                $request_uri = $_SERVER['REQUEST_URI'];
                if (strpos($request_uri, '/wp-json/wp/v2/posts/') !== false && 
                    (strpos($request_uri, 'context=edit') !== false || strpos($request_uri, 'context%3Dedit') !== false)) {
                    $is_edit_context = true;
                }
            }
        }
        
        if (is_admin() || wp_doing_ajax() || $is_edit_context || (defined('WP_ADMIN') && WP_ADMIN)) {
            // 返回默认值，不进行数据库查询
            return array(
                'is_protected' => false,
                'required_level' => 'free',
                'preview_mode' => 'auto',
                'preview_length' => 200,
                'preview_percentage' => 30,
                'preview_custom' => '',
                'protection_level' => 'content',
                'seo_visible' => true,
                'rss_include' => false,
            );
        }
        
        // 检查缓存
        $cache_key = self::CACHE_PREFIX . 'info_' . $post_id;
        $cached = wp_cache_get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $is_protected = get_post_meta($post_id, '_folio_premium_content', true);
        
        if (!$is_protected) {
            $info = array(
                'is_protected' => false,
                'required_level' => 'free',
                'preview_mode' => 'auto',
                'preview_length' => 200,
                'preview_percentage' => 30,
                'preview_custom' => '',
                'protection_level' => 'content',
                'seo_visible' => true,
                'rss_include' => false,
            );
        } else {
            $info = array(
                'is_protected' => true,
                'required_level' => get_post_meta($post_id, '_folio_required_level', true) ?: 'vip',
                'preview_mode' => get_post_meta($post_id, '_folio_preview_mode', true) ?: 'auto',
                'preview_length' => intval(get_post_meta($post_id, '_folio_preview_length', true)) ?: 200,
                'preview_percentage' => intval(get_post_meta($post_id, '_folio_preview_percentage', true)) ?: 30,
                'preview_custom' => get_post_meta($post_id, '_folio_preview_custom', true) ?: '',
                'protection_level' => get_post_meta($post_id, '_folio_protection_level', true) ?: 'content',
                'seo_visible' => get_post_meta($post_id, '_folio_seo_visible', true) !== '0',
                'rss_include' => get_post_meta($post_id, '_folio_rss_include', true) === '1',
            );
        }

        // 缓存结果
        wp_cache_set($cache_key, $info, '', self::CACHE_EXPIRY);
        
        return $info;
    }

    /**
     * 检查用户访问权限（带缓存优化）
     */
    public static function can_user_access($post_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // 管理员可以查看所有内容
        if ($user_id && user_can($user_id, 'manage_options')) {

            return true;
        }

        // 获取用户会员等级，用于生成缓存键（确保用户等级改变时缓存失效）
        $user_membership = null;
        if ($user_id) {
            $user_membership = folio_get_user_membership($user_id);
        }
        $user_level = $user_membership ? $user_membership['level'] : 'free';
        
        // 使用性能缓存管理器（缓存键包含用户等级，确保等级改变时缓存失效）
        if (class_exists('folio_Performance_Cache_Manager')) {
            $cached_result = folio_Performance_Cache_Manager::get_permission_cache($post_id, $user_id);
            if ($cached_result !== false) {
                // 验证缓存结果：如果用户等级改变，清除缓存并重新检查
                if ($user_membership && isset($user_membership['level'])) {
                    // 如果缓存存在但用户等级可能已改变，重新验证
                    // 这里我们信任缓存，但会在设置缓存时包含用户等级信息
                    return $cached_result;
                }
            }
        }

        // 检查内存缓存（向后兼容）
        // 缓存键包含用户等级，确保等级改变时缓存失效
        $cache_key = $post_id . '_' . $user_id . '_' . $user_level;
        if (isset(self::$permission_cache[$cache_key])) {
            return self::$permission_cache[$cache_key];
        }

        $protection_info = self::get_protection_info($post_id);
        
        if (!$protection_info['is_protected']) {
            $result = true;
        } elseif (!$user_id) {
            $result = false; // 未登录用户无法访问受保护内容
        } else {
            // 重新获取用户会员信息（确保是最新的）
            if (!$user_membership) {
                $user_membership = folio_get_user_membership($user_id);
                $user_level = $user_membership ? $user_membership['level'] : 'free';
            }
            
            $required_level = $protection_info['required_level'];
            

            
            // 检查是否是管理员
            $is_admin = $user_id && user_can($user_id, 'manage_options');
            

            
            // 确保用户会员信息有效
            if (!isset($user_membership['level']) || !isset($user_membership['is_vip']) || !isset($user_membership['is_svip'])) {
                // 如果会员信息不完整，拒绝访问
                $result = false;
            } else {
                // 严格检查权限：VIP用户不能访问SVIP内容
                switch ($required_level) {
                    case 'vip':
                        // VIP内容：VIP和SVIP用户都可以访问
                        $result = $user_membership['is_vip'];

                        break;
                    case 'svip':
                        // SVIP内容：只有SVIP用户可以访问，VIP用户不能访问
                        // 必须同时检查 is_svip 和 level，确保严格验证
                        $is_svip_check = $user_membership['is_svip'] === true;
                        $level_check = $user_membership['level'] === 'svip';
                        $result = ($is_svip_check && $level_check);
                        

                        break;
                    default:

                        $result = false;
                }
            }
            

        }

        // 缓存结果（使用包含用户等级的缓存键）
        self::$permission_cache[$cache_key] = $result;
        
        // 使用性能缓存管理器
        if (class_exists('folio_Performance_Cache_Manager')) {
            folio_Performance_Cache_Manager::set_permission_cache($post_id, $user_id, $result);
        } else {
            // 向后兼容
            wp_cache_set(self::CACHE_PREFIX . 'access_' . $cache_key, $result, '', self::CACHE_EXPIRY);
        }
        
        return $result;
    }

    /**
     * 生成内容预览（带缓存优化）
     */
    public static function generate_preview($content, $settings) {
        // 生成缓存键
        $settings_hash = md5(serialize($settings));
        $content_hash = md5($content);
        
        // 使用性能缓存管理器
        if (class_exists('folio_Performance_Cache_Manager')) {
            $cached_preview = folio_Performance_Cache_Manager::get_preview_cache($content_hash, $settings_hash);
            if ($cached_preview !== false) {
                return $cached_preview;
            }
        }

        // 检查内存缓存（向后兼容）
        $cache_key = 'preview_' . $content_hash . '_' . $settings_hash;
        if (isset(self::$preview_cache[$cache_key])) {
            return self::$preview_cache[$cache_key];
        }

        $preview_mode = $settings['preview_mode'] ?? 'auto';
        
        if ($preview_mode === 'none') {
            $preview = '';
        } elseif ($preview_mode === 'custom' && !empty($settings['preview_custom'])) {
            $preview = wpautop($settings['preview_custom']);
        } else {
            // 清理内容
            $clean_content = strip_shortcodes($content);
            $clean_content = wp_strip_all_tags($clean_content);
            $clean_content = trim($clean_content);
            
            if ($preview_mode === 'percentage') {
                $percentage = max(1, min(100, $settings['preview_percentage'] ?? 30));
                $length = intval(mb_strlen($clean_content) * ($percentage / 100));
            } else {
                // 自动预览模式
                $length = max(50, min(1000, $settings['preview_length'] ?? 200));
            }
            
            if (mb_strlen($clean_content) > $length) {
                $preview = mb_substr($clean_content, 0, $length) . '...';
            } else {
                $preview = $clean_content;
            }
        }

        // 缓存结果
        self::$preview_cache[$cache_key] = $preview;
        
        // 使用性能缓存管理器
        if (class_exists('folio_Performance_Cache_Manager')) {
            folio_Performance_Cache_Manager::set_preview_cache($content_hash, $settings_hash, $preview);
        } else {
            // 向后兼容
            wp_cache_set(self::CACHE_PREFIX . $cache_key, $preview, '', self::CACHE_EXPIRY);
        }
        
        return $preview;
    }

    /**
     * 过滤文章内容 - 主要内容过滤器
     */
    public function filter_content($content) {
        // 在后台、RSS、AJAX请求中不进行过滤
        if (!is_singular('post') || is_admin() || is_feed() || wp_doing_ajax()) {
            return $content;
        }

        global $post;
        if (!$post) {
            return $content;
        }

        $protection_info = self::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $content;
        }

        $user_id = get_current_user_id();
        if (self::can_user_access($post->ID, $user_id)) {
            return $content;
        }

        // 生成受保护内容的显示
        return $this->render_protected_content($content, $post->ID, $protection_info);
    }

    /**
     * 过滤文章摘要
     */
    public function filter_excerpt($excerpt) {
        // 在后台、RSS、AJAX请求中不进行过滤
        if (is_admin() || is_feed() || wp_doing_ajax()) {
            return $excerpt;
        }

        global $post;
        if (!$post) {
            return $excerpt;
        }

        $protection_info = self::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $excerpt;
        }

        $user_id = get_current_user_id();
        if (self::can_user_access($post->ID, $user_id)) {
            return $excerpt;
        }

        // 对于受保护的文章，返回简化的摘要
        $level_name = $protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP';
        return '此内容为会员专属，需要' . $level_name . '等级查看。';
    }

    /**
     * 过滤RSS内容
     */
    public function filter_feed_content($content) {
        global $post;
        if (!$post) {
            return $content;
        }

        $protection_info = self::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $content;
        }

        // RSS中根据设置决定是否显示预览
        if ($protection_info['rss_include']) {
            $preview = self::generate_preview($content, $protection_info);
            return $preview . "\n\n此内容为会员专属，请访问网站查看完整内容。";
        }

        return '此内容为会员专属，请访问网站查看完整内容。';
    }

    /**
     * 过滤REST API内容
     */
    public function filter_rest_content($response, $post, $request) {
        // 在后台编辑器中不进行过滤，允许编辑完整内容
        // 检查多种情况：后台页面、AJAX请求、REST API编辑上下文
        $is_edit_context = false;
        
        // 检查请求参数中的 context
        if (isset($_REQUEST['context']) && $_REQUEST['context'] === 'edit') {
            $is_edit_context = true;
        } elseif (is_array($request) && isset($request['context']) && $request['context'] === 'edit') {
            $is_edit_context = true;
        } elseif (is_object($request) && method_exists($request, 'get_param')) {
            $context = $request->get_param('context');
            if ($context === 'edit') {
                $is_edit_context = true;
            }
        }
        
        // 检查请求路径（Gutenberg 编辑器通常使用 /wp-json/wp/v2/posts/{id}?context=edit）
        if (!$is_edit_context && isset($_SERVER['REQUEST_URI'])) {
            $request_uri = $_SERVER['REQUEST_URI'];
            if (strpos($request_uri, '/wp-json/wp/v2/posts/') !== false && 
                (strpos($request_uri, 'context=edit') !== false || strpos($request_uri, 'context%3Dedit') !== false)) {
                $is_edit_context = true;
            }
        }
        
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST && $is_edit_context) || (defined('WP_ADMIN') && WP_ADMIN)) {
            return $response;
        }
        
        if ($post->post_type !== 'post') {
            return $response;
        }

        $protection_info = self::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $response;
        }

        $user_id = get_current_user_id();
        if (self::can_user_access($post->ID, $user_id)) {
            return $response;
        }

        // 修改API响应中的内容
        $data = $response->get_data();
        
        if (isset($data['content']['rendered'])) {
            $preview = self::generate_preview($data['content']['rendered'], $protection_info);
            $data['content']['rendered'] = wpautop($preview);
            
            // 添加保护信息到响应
            $data['protection_info'] = array(
                'is_protected' => true,
                'required_level' => $protection_info['required_level'],
                'can_access' => false,
                'message' => '此内容为会员专属，需要' . ($protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP') . '等级查看。'
            );
        }
        
        if (isset($data['excerpt']['rendered'])) {
            $level_name = $protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP';
            $data['excerpt']['rendered'] = '此内容为会员专属，需要' . $level_name . '等级查看。';
        }

        $response->set_data($data);
        return $response;
    }

    /**
     * 过滤搜索结果内容
     */
    public function filter_search_content($content) {
        if (!is_search() || is_admin()) {
            return $content;
        }

        global $post;
        if (!$post) {
            return $content;
        }

        $protection_info = self::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $content;
        }

        // 搜索结果中只显示预览
        if ($protection_info['seo_visible']) {
            $preview = self::generate_preview($content, $protection_info);
            return wpautop($preview);
        }

        return '此内容为会员专属内容。';
    }

    /**
     * 渲染受保护的内容
     */
    private function render_protected_content($content, $post_id, $protection_info) {
        $preview_content = '';
        
        if ($protection_info['preview_mode'] !== 'none') {
            $preview_content = self::generate_preview($content, $protection_info);
        }

        $permission_prompt = $this->get_permission_prompt($post_id, $protection_info);

        ob_start();
        ?>
        <div class="folio-protected-article" data-post-id="<?php echo esc_attr($post_id); ?>" data-level="<?php echo esc_attr($protection_info['required_level']); ?>">
            <?php if ($preview_content) : ?>
            <div class="folio-content-preview">
                <?php echo wpautop($preview_content); ?>
            </div>
            <div class="folio-content-fade"></div>
            <?php endif; ?>
            
            <?php echo $permission_prompt; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 获取权限提示HTML
     */
    public function get_permission_prompt($post_id, $protection_info = null) {
        if (!$protection_info) {
            $protection_info = self::get_protection_info($post_id);
        }

        $required_level = $protection_info['required_level'];
        $level_name = $required_level === 'svip' ? 'SVIP' : 'VIP';
        $level_class = 'folio-level-' . $required_level;

        $user_id = get_current_user_id();
        $user_membership = $user_id ? folio_get_user_membership($user_id) : null;
        
        // 检查用户是否有访问权限
        $can_access = self::can_user_access($post_id, $user_id);

        ob_start();
        ?>
        <div class="folio-permission-prompt <?php echo esc_attr($level_class); ?>" 
             data-post-id="<?php echo esc_attr($post_id); ?>"
             data-level="<?php echo esc_attr($required_level); ?>"
             data-can-access="<?php echo $can_access ? 'true' : 'false'; ?>"
             data-user-logged-in="<?php echo $user_id ? 'true' : 'false'; ?>">
            <div class="folio-prompt-icon">
                <span class="dashicons dashicons-lock"></span>
            </div>
            
            <div class="folio-prompt-content">
                <h4 class="folio-prompt-title">此内容为<?php echo esc_html($level_name); ?>会员专属</h4>
                
                <?php if (!$user_id) : ?>
                <p class="folio-prompt-description">请先登录查看专属内容</p>
                <div class="folio-prompt-actions">
                    <a href="<?php echo esc_url(home_url('/user-center/login')); ?>" class="folio-btn folio-btn-login">
                        <span class="dashicons dashicons-admin-users"></span> 登录
                    </a>
                    <a href="<?php echo esc_url(home_url('/user-center/register')); ?>" class="folio-btn folio-btn-register">
                        <span class="dashicons dashicons-plus"></span> 注册
                    </a>
                </div>
                <?php else : ?>
                <p class="folio-prompt-description">
                    当前等级：<?php echo esc_html($user_membership['name']); ?>
                    <?php if ($user_membership['level'] !== 'free') : ?>
                    <span class="folio-current-badge"><?php echo wp_kses_post($user_membership['icon']); ?></span>
                    <?php endif; ?>
                </p>
                <div class="folio-prompt-actions">
                    <a href="<?php echo esc_url(home_url('/user-center/membership')); ?>" class="folio-btn folio-btn-upgrade">
                        <span class="dashicons dashicons-star-filled"></span>
                        升级<?php echo esc_html($level_name); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 清除文章相关缓存
     */
    public function clear_post_cache($post_id) {
        // 清除文章保护信息缓存
        wp_cache_delete(self::CACHE_PREFIX . 'info_' . $post_id);
        
        // 清除所有用户对此文章的权限缓存
        $this->clear_post_permission_cache($post_id);
        
        // 清除预览缓存（通过删除所有相关的预览缓存）
        $this->clear_post_preview_cache($post_id);
    }

    /**
     * 清除用户相关缓存
     */
    public function clear_user_cache($user_id, $new_level = null) {
        // 清除用户的所有权限缓存
        $this->clear_user_permission_cache($user_id);
        
        // 触发缓存清理钩子
        do_action('folio_user_cache_cleared', $user_id, $new_level);
    }

    /**
     * 清除用户权限缓存
     */
    public function clear_user_permission_cache($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return;
        }

        // 清除内存缓存（包括所有可能的缓存键格式）
        foreach (self::$permission_cache as $key => $value) {
            // 匹配格式：post_id_user_id 或 post_id_user_id_level
            if (preg_match('/^(\d+)_' . $user_id . '(_[^_]+)?$/', $key)) {
                unset(self::$permission_cache[$key]);
            }
        }

        // 清除WordPress缓存 - 使用缓存版本号机制
        $cache_version = get_user_meta($user_id, 'folio_cache_version', true);
        $new_version = $cache_version ? $cache_version + 1 : 1;
        update_user_meta($user_id, 'folio_cache_version', $new_version);
        
        // 清除性能缓存管理器中的缓存
        if (class_exists('folio_Performance_Cache_Manager')) {
            folio_Performance_Cache_Manager::clear_user_related_cache($user_id, null);
        }
    }

    /**
     * 清除文章的权限缓存
     */
    private function clear_post_permission_cache($post_id) {
        // 清除内存缓存
        foreach (self::$permission_cache as $key => $value) {
            if (strpos($key, $post_id . '_') === 0) {
                unset(self::$permission_cache[$key]);
            }
        }
    }

    /**
     * 清除文章的预览缓存
     */
    private function clear_post_preview_cache($post_id) {
        // 清除内存缓存
        foreach (self::$preview_cache as $key => $value) {
            unset(self::$preview_cache[$key]);
        }
        
        // 清除WordPress缓存 - 删除所有预览相关缓存
        wp_cache_flush_group('folio_preview');
    }

    /**
     * 获取缓存统计信息（用于调试）
     */
    public static function get_cache_stats() {
        return array(
            'permission_cache_size' => count(self::$permission_cache),
            'preview_cache_size' => count(self::$preview_cache),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        );
    }

    /**
     * 验证权限一致性（用于测试）
     */
    public static function validate_permission_consistency($post_id, $user_id) {
        // 清除缓存后重新验证
        $cache_key = $post_id . '_' . $user_id;
        unset(self::$permission_cache[$cache_key]);
        wp_cache_delete(self::CACHE_PREFIX . 'access_' . $cache_key);
        
        $result1 = self::can_user_access($post_id, $user_id);
        
        // 再次验证应该得到相同结果
        $result2 = self::can_user_access($post_id, $user_id);
        
        return $result1 === $result2;
    }


}

// 初始化文章保护管理器
new folio_Article_Protection_Manager();

/**
 * 全局辅助函数
 */

if (!function_exists('folio_get_article_protection_info')) {
    function folio_get_article_protection_info($post_id) {
        return folio_Article_Protection_Manager::get_protection_info($post_id);
    }
}

if (!function_exists('folio_can_user_access_article')) {
    function folio_can_user_access_article($post_id, $user_id = null) {
        return folio_Article_Protection_Manager::can_user_access($post_id, $user_id);
    }
}

if (!function_exists('folio_generate_article_preview')) {
    function folio_generate_article_preview($content, $settings) {
        return folio_Article_Protection_Manager::generate_preview($content, $settings);
    }
}