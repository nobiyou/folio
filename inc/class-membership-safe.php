<?php
/**
 * Safe Membership System
 * 
 * 安全的会员系统 - 内存优化版本
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Membership_Safe {

    // 会员等级定义
    const LEVEL_FREE = 'free';
    const LEVEL_VIP = 'vip';
    const LEVEL_SVIP = 'svip';
    
    // 内存保护设置
    const MAX_MEMORY_USAGE = 200; // MB
    const BATCH_SIZE = 50; // 批处理大小
    
    private static $instance = null;
    private static $user_cache = array();

    public function __construct() {
        // 内存保护检查
        if (!$this->is_memory_safe()) {
            error_log('Folio Membership: Memory usage too high, skipping initialization');
            return;
        }
        
        // 只在必要时添加用户列表的会员列
        if ($this->should_add_user_columns()) {
            add_filter('manage_users_columns', array($this, 'add_membership_column'));
            add_filter('manage_users_custom_column', array($this, 'display_membership_column'), 10, 3);
        }
        
        // 用户编辑页面
        add_action('show_user_profile', array($this, 'add_membership_fields'));
        add_action('edit_user_profile', array($this, 'add_membership_fields'));
        add_action('personal_options_update', array($this, 'save_membership_fields'));
        add_action('edit_user_profile_update', array($this, 'save_membership_fields'));
        
        // 安全的定时任务
        add_action('folio_safe_membership_check', array($this, 'safe_check_expiry'));
        if (!wp_next_scheduled('folio_safe_membership_check')) {
            wp_schedule_event(time(), 'daily', 'folio_safe_membership_check');
        }
        
        // 清理缓存
        add_action('updated_user_meta', array($this, 'clear_user_cache_on_update'), 10, 4);
    }

    /**
     * 单例模式
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 内存安全检查
     */
    private function is_memory_safe() {
        $current_memory = memory_get_usage(true) / 1024 / 1024; // MB
        return $current_memory < self::MAX_MEMORY_USAGE;
    }

    /**
     * 是否应该添加用户列
     */
    private function should_add_user_columns() {
        // 只在用户管理页面且内存充足时添加
        global $pagenow;
        return ($pagenow === 'users.php' && $this->is_memory_safe());
    }

    /**
     * 获取所有会员等级
     */
    public static function get_levels() {
        return array(
            self::LEVEL_FREE => array(
                'name' => __('普通用户', 'folio'),
                'icon' => '<span class="membership-badge membership-badge-free">FREE</span>',
                'color' => '#6b7280',
                'badge_bg' => '#f3f4f6',
            ),
            self::LEVEL_VIP => array(
                'name' => __('VIP会员', 'folio'),
                'icon' => '<span class="membership-badge membership-badge-vip">VIP</span>',
                'color' => '#f59e0b',
                'badge_bg' => '#fef3c7',
            ),
            self::LEVEL_SVIP => array(
                'name' => __('SVIP会员', 'folio'),
                'icon' => '<span class="membership-badge membership-badge-svip">SVIP</span>',
                'color' => '#8b5cf6',
                'badge_bg' => '#ede9fe',
            ),
        );
    }

    /**
     * 获取用户会员等级（带缓存）
     */
    public static function get_user_level($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return self::LEVEL_FREE;
        }

        // 使用静态缓存
        if (isset(self::$user_cache[$user_id]['level'])) {
            return self::$user_cache[$user_id]['level'];
        }

        $level = get_user_meta($user_id, 'folio_membership_level', true);
        $expiry = get_user_meta($user_id, 'folio_membership_expiry', true);
        $is_permanent = get_user_meta($user_id, 'folio_membership_permanent', true);
        
        // 检查是否过期
        if ($level && $level !== self::LEVEL_FREE && $expiry && !$is_permanent) {
            $expiry_timestamp = strtotime($expiry . ' 23:59:59');
            $current_timestamp = current_time('timestamp');
            
            if ($expiry_timestamp < $current_timestamp) {
                // 会员已过期，降级为普通用户
                update_user_meta($user_id, 'folio_membership_level', self::LEVEL_FREE);
                delete_user_meta($user_id, 'folio_membership_expiry');
                $level = self::LEVEL_FREE;
            }
        }
        
        $level = $level ?: self::LEVEL_FREE;
        
        // 缓存结果
        self::$user_cache[$user_id]['level'] = $level;
        
        return $level;
    }

    /**
     * 获取用户会员信息（优化版）
     */
    public static function get_user_membership($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // 检查缓存
        if (isset(self::$user_cache[$user_id]['membership'])) {
            return self::$user_cache[$user_id]['membership'];
        }
        
        $level = self::get_user_level($user_id);
        $levels = self::get_levels();
        $level_info = isset($levels[$level]) ? $levels[$level] : $levels[self::LEVEL_FREE];
        
        $expiry = get_user_meta($user_id, 'folio_membership_expiry', true);
        $is_permanent = get_user_meta($user_id, 'folio_membership_permanent', true);
        
        // 计算剩余天数
        $days_left = null;
        if ($expiry && !$is_permanent) {
            $expiry_timestamp = strtotime($expiry . ' 23:59:59');
            $current_timestamp = current_time('timestamp');
            $days_left = max(0, ceil(($expiry_timestamp - $current_timestamp) / DAY_IN_SECONDS));
        }

        $membership = array(
            'level' => $level,
            'name' => $level_info['name'],
            'icon' => $level_info['icon'],
            'color' => $level_info['color'],
            'badge_bg' => $level_info['badge_bg'],
            'expiry' => $expiry,
            'is_permanent' => $is_permanent,
            'is_vip' => in_array($level, array(self::LEVEL_VIP, self::LEVEL_SVIP)),
            'is_svip' => $level === self::LEVEL_SVIP,
            'days_left' => $days_left,
        );
        
        // 缓存结果
        self::$user_cache[$user_id]['membership'] = $membership;
        
        return $membership;
    }

    /**
     * 设置用户会员等级
     */
    public static function set_user_level($user_id, $level, $days = 30, $permanent = false) {
        if (!in_array($level, array(self::LEVEL_FREE, self::LEVEL_VIP, self::LEVEL_SVIP))) {
            return false;
        }
        
        update_user_meta($user_id, 'folio_membership_level', $level);
        
        if ($level === self::LEVEL_FREE) {
            delete_user_meta($user_id, 'folio_membership_expiry');
            delete_user_meta($user_id, 'folio_membership_permanent');
        } else {
            if ($permanent) {
                update_user_meta($user_id, 'folio_membership_permanent', true);
                delete_user_meta($user_id, 'folio_membership_expiry');
            } else {
                $current_timestamp = current_time('timestamp');
                $expiry = date('Y-m-d', $current_timestamp + ($days * DAY_IN_SECONDS));
                update_user_meta($user_id, 'folio_membership_expiry', $expiry);
                delete_user_meta($user_id, 'folio_membership_permanent');
            }
        }
        
        // 清除缓存
        self::clear_user_cache($user_id);
        
        // 触发钩子
        do_action('folio_membership_level_changed', $user_id, $level, $permanent, $days);
        
        return true;
    }

    /**
     * 检查用户是否是VIP
     */
    public static function is_vip($user_id = null) {
        $membership = self::get_user_membership($user_id);
        return $membership['is_vip'];
    }

    /**
     * 检查用户是否是SVIP
     */
    public static function is_svip($user_id = null) {
        $membership = self::get_user_membership($user_id);
        return $membership['is_svip'];
    }

    /**
     * 清除用户缓存
     */
    public static function clear_user_cache($user_id) {
        unset(self::$user_cache[$user_id]);
        wp_cache_delete($user_id, 'user_meta');
        wp_cache_delete($user_id, 'users');
    }

    /**
     * 用户元数据更新时清除缓存
     */
    public function clear_user_cache_on_update($meta_id, $user_id, $meta_key, $meta_value) {
        if (in_array($meta_key, array('folio_membership_level', 'folio_membership_expiry', 'folio_membership_permanent'))) {
            self::clear_user_cache($user_id);
        }
    }

    /**
     * 安全的会员过期检查
     */
    public function safe_check_expiry() {
        // 内存保护
        if (!$this->is_memory_safe()) {
            error_log('Folio Membership: Skipping expiry check due to high memory usage');
            return;
        }
        
        global $wpdb;
        
        // 使用更高效的SQL查询
        $expired_users = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = 'folio_membership_expiry' 
             AND meta_value < %s 
             AND user_id NOT IN (
                 SELECT user_id FROM {$wpdb->usermeta} 
                 WHERE meta_key = 'folio_membership_permanent' 
                 AND meta_value = '1'
             )
             LIMIT %d",
            date('Y-m-d'),
            self::BATCH_SIZE
        ));
        
        $expired_count = 0;
        foreach ($expired_users as $user) {
            update_user_meta($user->user_id, 'folio_membership_level', self::LEVEL_FREE);
            delete_user_meta($user->user_id, 'folio_membership_expiry');
            self::clear_user_cache($user->user_id);
            $expired_count++;
        }
        
        if ($expired_count > 0) {
            error_log("Folio Membership: {$expired_count} users expired and downgraded");
        }
        
        return $expired_count;
    }

    /**
     * 后台用户列表 - 添加会员列
     */
    public function add_membership_column($columns) {
        $columns['folio_membership'] = __('会员等级', 'folio');
        return $columns;
    }

    /**
     * 后台用户列表 - 显示会员列（优化版）
     */
    public function display_membership_column($value, $column_name, $user_id) {
        if ($column_name === 'folio_membership') {
            // 内存保护
            if (!$this->is_memory_safe()) {
                return '<span style="color: #999;">内存保护</span>';
            }
            
            $membership = self::get_user_membership($user_id);
            
            $output = sprintf(
                '<span class="membership-badge membership-badge-%s">%s</span>',
                esc_attr($membership['level']),
                esc_html($membership['name'])
            );
            
            if ($membership['is_vip'] && !$membership['is_permanent'] && $membership['expiry']) {
                $output .= sprintf(
                    '<br><small style="color: #6b7280;">%s: %s</small>',
                    __('到期', 'folio'),
                    $membership['expiry']
                );
            } elseif ($membership['is_permanent']) {
                $output .= sprintf(
                    '<br><small style="color: #10b981;">%s</small>',
                    __('永久', 'folio')
                );
            }
            
            return $output;
        }
        return $value;
    }

    /**
     * 用户编辑页面 - 添加会员设置字段
     */
    public function add_membership_fields($user) {
        if (!current_user_can('edit_users')) {
            return;
        }
        
        $membership = self::get_user_membership($user->ID);
        $levels = self::get_levels();
        ?>
        <h3><?php esc_html_e('会员设置', 'folio'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="folio_membership_level"><?php esc_html_e('会员等级', 'folio'); ?></label></th>
                <td>
                    <select name="folio_membership_level" id="folio_membership_level">
                        <?php foreach ($levels as $key => $level) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($membership['level'], $key); ?>>
                            <?php echo esc_html($level['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="folio_membership_expiry"><?php esc_html_e('到期时间', 'folio'); ?></label></th>
                <td>
                    <input type="date" name="folio_membership_expiry" id="folio_membership_expiry" 
                           value="<?php echo esc_attr($membership['expiry']); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('留空表示永久会员（仅对VIP/SVIP有效）', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="folio_membership_permanent"><?php esc_html_e('永久会员', 'folio'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="folio_membership_permanent" id="folio_membership_permanent" value="1" 
                               <?php checked($membership['is_permanent']); ?>>
                        <?php esc_html_e('设为永久会员（不受到期时间限制）', 'folio'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <style>
        .membership-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .membership-badge-free {
            background: #f3f4f6;
            color: #6b7280;
        }
        .membership-badge-vip {
            background: #fef3c7;
            color: #f59e0b;
        }
        .membership-badge-svip {
            background: #ede9fe;
            color: #8b5cf6;
        }
        </style>
        <?php
    }

    /**
     * 保存会员设置
     */
    public function save_membership_fields($user_id) {
        if (!current_user_can('edit_users')) {
            return;
        }
        
        if (isset($_POST['folio_membership_level'])) {
            $level = sanitize_text_field($_POST['folio_membership_level']);
            $expiry = isset($_POST['folio_membership_expiry']) ? sanitize_text_field($_POST['folio_membership_expiry']) : '';
            $permanent = isset($_POST['folio_membership_permanent']);
            
            update_user_meta($user_id, 'folio_membership_level', $level);
            
            if ($level === self::LEVEL_FREE) {
                delete_user_meta($user_id, 'folio_membership_expiry');
                delete_user_meta($user_id, 'folio_membership_permanent');
            } else {
                if ($permanent) {
                    update_user_meta($user_id, 'folio_membership_permanent', true);
                    delete_user_meta($user_id, 'folio_membership_expiry');
                } else {
                    update_user_meta($user_id, 'folio_membership_expiry', $expiry);
                    delete_user_meta($user_id, 'folio_membership_permanent');
                }
            }
            
            // 清除缓存
            self::clear_user_cache($user_id);
        }
    }
}

// 全局函数（向后兼容）
if (!function_exists('folio_get_user_membership')) {
    function folio_get_user_membership($user_id = null) {
        return folio_Membership_Safe::get_user_membership($user_id);
    }
}

if (!function_exists('folio_is_vip')) {
    function folio_is_vip($user_id = null) {
        return folio_Membership_Safe::is_vip($user_id);
    }
}

if (!function_exists('folio_is_svip')) {
    function folio_is_svip($user_id = null) {
        return folio_Membership_Safe::is_svip($user_id);
    }
}

if (!function_exists('folio_get_membership_levels')) {
    function folio_get_membership_levels() {
        return folio_Membership_Safe::get_levels();
    }
}