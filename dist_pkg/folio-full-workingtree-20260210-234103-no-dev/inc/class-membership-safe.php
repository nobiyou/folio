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
    const AUDIT_LOG_OPTION = 'folio_membership_audit_logs';
    const AUDIT_LOG_LIMIT = 500;
    
    // 内存保护设置
    const MAX_MEMORY_USAGE = 200; // MB
    const BATCH_SIZE = 50; // 批处理大小
    
    private static $instance = null;
    private static $user_cache = array();

    /**
     * 规范化会员等级，防止无效值写入。
     *
     * @param mixed $level 传入的等级值
     * @return string
     */
    private static function normalize_level($level) {
        $level = sanitize_text_field((string) $level);
        if (in_array($level, array(self::LEVEL_FREE, self::LEVEL_VIP, self::LEVEL_SVIP), true)) {
            return $level;
        }
        return self::LEVEL_FREE;
    }

    /**
     * 记录会员等级变更审计日志。
     */
    public function capture_membership_change_audit($user_id, $new_level, $permanent = false, $days = 0, $old_level = self::LEVEL_FREE, $source = 'system') {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        $old_level = self::normalize_level($old_level);
        $new_level = self::normalize_level($new_level);
        if ($old_level === $new_level) {
            return;
        }

        $entry = array(
            'time' => current_time('mysql'),
            'user_id' => $user_id,
            'old_level' => $old_level,
            'new_level' => $new_level,
            'operator_id' => absint(get_current_user_id()),
            'source' => sanitize_key($source),
            'permanent' => $permanent ? 1 : 0,
            'days' => max(0, absint($days)),
        );

        $logs = get_option(self::AUDIT_LOG_OPTION, array());
        if (!is_array($logs)) {
            $logs = array();
        }

        array_unshift($logs, $entry);
        if (count($logs) > self::AUDIT_LOG_LIMIT) {
            $logs = array_slice($logs, 0, self::AUDIT_LOG_LIMIT);
        }

        if (false === get_option(self::AUDIT_LOG_OPTION, false)) {
            add_option(self::AUDIT_LOG_OPTION, $logs, '', false);
            return;
        }

        update_option(self::AUDIT_LOG_OPTION, $logs, false);
    }

    /**
     * 获取会员审计日志。
     */
    public static function get_audit_logs($limit = 50) {
        $limit = max(1, absint($limit));
        $logs = get_option(self::AUDIT_LOG_OPTION, array());
        if (!is_array($logs)) {
            return array();
        }
        return array_slice($logs, 0, $limit);
    }

    /**
     * 清空会员审计日志。
     */
    public static function clear_audit_logs() {
        return delete_option(self::AUDIT_LOG_OPTION);
    }

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
        add_action('folio_membership_level_changed', array($this, 'capture_membership_change_audit'), 10, 6);
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
                'name' => __('Free User', 'folio'),
                'icon' => '<span class="membership-badge membership-badge-free">FREE</span>',
                'color' => '#6b7280',
                'badge_bg' => '#f3f4f6',
            ),
            self::LEVEL_VIP => array(
                'name' => __('VIP Member', 'folio'),
                'icon' => '<span class="membership-badge membership-badge-vip">VIP</span>',
                'color' => '#f59e0b',
                'badge_bg' => '#fef3c7',
            ),
            self::LEVEL_SVIP => array(
                'name' => __('SVIP Member', 'folio'),
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

        $level = self::normalize_level(get_user_meta($user_id, 'folio_membership_level', true));
        $expiry = get_user_meta($user_id, 'folio_membership_expiry', true);
        $is_permanent = get_user_meta($user_id, 'folio_membership_permanent', true);
        
        // 检查是否过期
        if ($level && $level !== self::LEVEL_FREE && $expiry && !$is_permanent) {
            $expiry_timestamp = strtotime($expiry . ' 23:59:59');
            $current_timestamp = current_time('timestamp');
            
            if ($expiry_timestamp < $current_timestamp) {
                // 会员已过期，降级为普通用户
                $old_level = $level;
                update_user_meta($user_id, 'folio_membership_level', self::LEVEL_FREE);
                delete_user_meta($user_id, 'folio_membership_expiry');
                $level = self::LEVEL_FREE;
                do_action('folio_membership_expired', $user_id);
                do_action('folio_membership_level_changed', $user_id, self::LEVEL_FREE, false, 0, $old_level, 'auto_expiry_check');
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
        $level = self::normalize_level($level);
        $old_level = self::get_user_level($user_id);

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
        do_action('folio_membership_level_changed', $user_id, $level, $permanent, $days, $old_level, 'api_set_user_level');
        if ($old_level === self::LEVEL_FREE && in_array($level, array(self::LEVEL_VIP, self::LEVEL_SVIP), true)) {
            do_action('folio_membership_activated', $user_id, $level);
        } elseif ($old_level !== $level) {
            do_action('folio_membership_changed', $user_id, $old_level, $level);
        }
        
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
            $old_level = self::get_user_level($user->user_id);
            update_user_meta($user->user_id, 'folio_membership_level', self::LEVEL_FREE);
            delete_user_meta($user->user_id, 'folio_membership_expiry');
            self::clear_user_cache($user->user_id);
            if ($old_level !== self::LEVEL_FREE) {
                do_action('folio_membership_expired', $user->user_id);
                do_action('folio_membership_level_changed', $user->user_id, self::LEVEL_FREE, false, 0, $old_level, 'cron_expiry_check');
            }
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
        $columns['folio_membership'] = __('Membership Level', 'folio');
        return $columns;
    }

    /**
     * 后台用户列表 - 显示会员列（优化版）
     */
    public function display_membership_column($value, $column_name, $user_id) {
        if ($column_name === 'folio_membership') {
            // 内存保护
            if (!$this->is_memory_safe()) {
                return '<span style="color: #999;">' . esc_html__('Memory protection', 'folio') . '</span>';
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
                    __('Expires', 'folio'),
                    $membership['expiry']
                );
            } elseif ($membership['is_permanent']) {
                $output .= sprintf(
                    '<br><small style="color: #10b981;">%s</small>',
                    __('Permanent', 'folio')
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
        <h3><?php esc_html_e('Membership Settings', 'folio'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="folio_membership_level"><?php esc_html_e('Membership Level', 'folio'); ?></label></th>
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
                <th><label for="folio_membership_expiry"><?php esc_html_e('Expiry Date', 'folio'); ?></label></th>
                <td>
                    <input type="date" name="folio_membership_expiry" id="folio_membership_expiry" 
                           value="<?php echo esc_attr($membership['expiry']); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Leave blank for permanent membership (VIP/SVIP only)', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="folio_membership_permanent"><?php esc_html_e('Permanent Membership', 'folio'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="folio_membership_permanent" id="folio_membership_permanent" value="1" 
                               <?php checked($membership['is_permanent']); ?>>
                        <?php esc_html_e('Set as permanent membership (no expiry limit)', 'folio'); ?>
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
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        if (isset($_POST['folio_membership_level'])) {
            $old_level = self::get_user_level($user_id);
            $level = self::normalize_level($_POST['folio_membership_level']);
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

            // 触发状态变更事件，确保通知/联动逻辑一致
            $days = 0;
            if (!$permanent && !empty($expiry)) {
                $days = max(0, (int) ceil((strtotime($expiry . ' 23:59:59') - current_time('timestamp')) / DAY_IN_SECONDS));
            }
            do_action('folio_membership_level_changed', $user_id, $level, $permanent, $days, $old_level, 'admin_profile_update');
            if ($old_level === self::LEVEL_FREE && in_array($level, array(self::LEVEL_VIP, self::LEVEL_SVIP), true)) {
                do_action('folio_membership_activated', $user_id, $level);
            } elseif ($old_level !== $level) {
                do_action('folio_membership_changed', $user_id, $old_level, $level);
            }
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
