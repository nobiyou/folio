<?php
/**
 * Membership Admin Panel
 * 
 * 会员系统管理面板 - 使用WordPress内置样式
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Membership_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_folio_test_membership', array($this, 'test_membership_system'));
        add_action('wp_ajax_folio_check_expired_members', array($this, 'check_expired_members'));
        add_action('wp_ajax_folio_clear_membership_cache', array($this, 'clear_membership_cache'));
        add_action('wp_ajax_folio_clear_membership_audit_logs', array($this, 'clear_membership_audit_logs'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'themes.php',
            __('Membership System Management', 'folio'),
            __('Membership System', 'folio'),
            'manage_options',
            'folio-membership',
            array($this, 'admin_page')
        );
    }

    /**
     * 注册设置
     */
    public function register_settings() {
        register_setting('folio_membership_settings', 'folio_membership_options', array(
            'sanitize_callback' => array($this, 'sanitize_membership_options')
        ));
    }
    
    /**
     * 清理和验证设置数据
     */
    public function sanitize_membership_options($input) {
        $sanitized = array();
        
        // 系统设置
        $sanitized['memory_protection'] = isset($input['memory_protection']) ? 1 : 0;
        $sanitized['batch_size'] = isset($input['batch_size']) ? absint($input['batch_size']) : 50;
        $sanitized['cache_time'] = isset($input['cache_time']) ? absint($input['cache_time']) : 300;
        
        // 会员权益对比数据
        if (isset($input['benefits_comparison']) && is_array($input['benefits_comparison'])) {
            foreach ($input['benefits_comparison'] as $level => $benefits) {
                if (in_array($level, array('vip', 'svip'))) {
                    $sanitized['benefits_comparison'][$level] = array();
                    foreach ($benefits as $index => $benefit) {
                        if (isset($benefit['name']) && !empty($benefit['name'])) {
                            $sanitized['benefits_comparison'][$level][] = array(
                                'name' => sanitize_text_field($benefit['name']),
                                'normal' => isset($benefit['normal']) ? sanitize_text_field($benefit['normal']) : '×',
                                'vip' => isset($benefit['vip']) ? sanitize_text_field($benefit['vip']) : '',
                                'svip' => isset($benefit['svip']) ? sanitize_text_field($benefit['svip']) : ''
                            );
                        }
                    }
                }
            }
        }
        
        // 会员价格设置
        if (isset($input['membership_prices'])) {
            $sanitized['membership_prices'] = array(
                'vip' => isset($input['membership_prices']['vip']) ? sanitize_text_field($input['membership_prices']['vip']) : __('¥68/month', 'folio'),
                'svip' => isset($input['membership_prices']['svip']) ? sanitize_text_field($input['membership_prices']['svip']) : __('¥128/month', 'folio')
            );
        }
        
        // 支付设置
        if (isset($input['payment_qr_code'])) {
            $sanitized['payment_qr_code'] = esc_url_raw($input['payment_qr_code']);
        }
        if (isset($input['payment_instructions'])) {
            $sanitized['payment_instructions'] = wp_kses_post($input['payment_instructions']);
        }
        if (isset($input['payment_contact'])) {
            $sanitized['payment_contact'] = sanitize_text_field($input['payment_contact']);
        }
        
        return $sanitized;
    }

    /**
     * 加载管理脚本
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'appearance_page_folio-membership') {
            return;
        }
        
        wp_enqueue_script('jquery');
        // 启用WordPress媒体上传器
        wp_enqueue_media();
    }

    /**
     * 管理页面
     */
    public function admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        $stats = $this->get_membership_statistics();
        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-groups"></span>
                <?php esc_html_e('Membership System Management', 'folio'); ?>
            </h1>
            
            <?php settings_errors(); ?>
            
            <!-- WordPress标准标签导航 -->
            <nav class="nav-tab-wrapper">
                <a href="?page=folio-membership&tab=overview" class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e('System Overview', 'folio'); ?>
                </a>
                <a href="?page=folio-membership&tab=members" class="nav-tab <?php echo $active_tab === 'members' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('Member Management', 'folio'); ?>
                </a>
                <a href="?page=folio-membership&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('System Settings', 'folio'); ?>
                </a>
                <a href="?page=folio-membership&tab=analytics" class="nav-tab <?php echo $active_tab === 'analytics' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e('Membership Analytics', 'folio'); ?>
                </a>
                <a href="?page=folio-membership&tab=tools" class="nav-tab <?php echo $active_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('System Tools', 'folio'); ?>
                </a>
            </nav>
            
            <div class="folio-membership-content">
                <?php
                switch ($active_tab) {
                    case 'members':
                        $this->render_members_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'analytics':
                        $this->render_analytics_tab();
                        break;
                    case 'tools':
                        $this->render_tools_tab();
                        break;
                    default:
                        $this->render_overview_tab($stats);
                }
                ?>
            </div>
        </div>
        
        <!-- WordPress内置样式 + 最小化自定义 -->
        <style>
        .folio-membership-content {
            margin-top: 20px;
        }
        
        .folio-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .folio-stat-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .folio-stat-number {
            font-size: 32px;
            font-weight: 600;
            color: #1d4ed8;
            margin: 10px 0;
        }
        
        .folio-stat-label {
            color: #6b7280;
            font-size: 14px;
        }
        
        .folio-stat-vip .folio-stat-number {
            color: #f59e0b;
        }
        
        .folio-stat-svip .folio-stat-number {
            color: #8b5cf6;
        }
        
        .folio-stat-expiring .folio-stat-number {
            color: #dc2626;
        }
        
        .folio-memory-status {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .folio-memory-indicator {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .folio-memory-safe {
            background: #d1f2eb;
            color: #155724;
        }
        
        .folio-memory-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .folio-memory-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
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
        
        .folio-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .folio-status-active {
            background: #d1f2eb;
            color: #155724;
        }
        
        .folio-status-expiring {
            background: #fff3cd;
            color: #856404;
        }
        
        .folio-status-expired {
            background: #f8d7da;
            color: #721c24;
        }
        
        .folio-status-permanent {
            background: #d4edda;
            color: #155724;
        }
        
        @media (max-width: 768px) {
            .folio-stats-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        
        <script>
        function testMembershipSystem() {
            const result = document.getElementById('folio-action-result');
            result.innerHTML = '<div class="notice notice-info"><p>🧪 <?php echo esc_js(__('Testing membership system...', 'folio')); ?></p></div>';
            
            jQuery.post(ajaxurl, {
                action: 'folio_test_membership',
                nonce: '<?php echo wp_create_nonce('folio_membership_admin'); ?>'
            }, function(response) {
                if (response.success) {
                    result.innerHTML = '<div class="notice notice-success is-dismissible"><p>✅ <?php echo esc_js(__('Test succeeded:', 'folio')); ?><br>' + response.data.message + '</p></div>';
                } else {
                    result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ <?php echo esc_js(__('Test failed:', 'folio')); ?>' + response.data.message + '</p></div>';
                }
            }).fail(function(xhr) {
                result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ <?php echo esc_js(__('Request failed:', 'folio')); ?>' + xhr.status + '</p></div>';
            });
        }
        
        function checkExpiredMembers() {
            const result = document.getElementById('folio-action-result');
            result.innerHTML = '<div class="notice notice-info"><p>⏰ <?php echo esc_js(__('Checking expired memberships...', 'folio')); ?></p></div>';
            
            jQuery.post(ajaxurl, {
                action: 'folio_check_expired_members',
                nonce: '<?php echo wp_create_nonce('folio_membership_admin'); ?>'
            }, function(response) {
                if (response.success) {
                    result.innerHTML = '<div class="notice notice-success is-dismissible"><p>✅ <?php echo esc_js(__('Check completed:', 'folio')); ?>' + response.data.message + '</p></div>';
                } else {
                    result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ <?php echo esc_js(__('Check failed:', 'folio')); ?>' + response.data.message + '</p></div>';
                }
            }).fail(function(xhr) {
                result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ <?php echo esc_js(__('Request failed:', 'folio')); ?>' + xhr.status + '</p></div>';
            });
        }
        
        function clearMembershipCache() {
            const result = document.getElementById('folio-action-result');
            result.innerHTML = '<div class="notice notice-info"><p>🗑️ <?php echo esc_js(__('Clearing cache...', 'folio')); ?></p></div>';
            
            jQuery.post(ajaxurl, {
                action: 'folio_clear_membership_cache',
                nonce: '<?php echo wp_create_nonce('folio_membership_admin'); ?>'
            }, function(response) {
                if (response.success) {
                    result.innerHTML = '<div class="notice notice-success is-dismissible"><p>✅ <?php echo esc_js(__('Cache cleared successfully', 'folio')); ?></p></div>';
                } else {
                    result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ <?php echo esc_js(__('Cache clear failed:', 'folio')); ?>' + response.data.message + '</p></div>';
                }
            }).fail(function(xhr) {
                result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ <?php echo esc_js(__('Request failed:', 'folio')); ?>' + xhr.status + '</p></div>';
            });
        }

        function clearMembershipAuditLogs() {
            if (!window.confirm('<?php echo esc_js(__('Are you sure to clear all membership audit logs?', 'folio')); ?>')) {
                return;
            }

            const result = document.getElementById('folio-action-result');
            result.innerHTML = '<div class="notice notice-info"><p>🧹 <?php echo esc_js(__('Clearing membership audit logs...', 'folio')); ?></p></div>';

            jQuery.post(ajaxurl, {
                action: 'folio_clear_membership_audit_logs',
                nonce: '<?php echo wp_create_nonce('folio_membership_admin'); ?>'
            }, function(response) {
                if (response.success) {
                    result.innerHTML = '<div class="notice notice-success is-dismissible"><p>✅ ' + response.data.message + '</p></div>';
                    window.location.reload();
                } else {
                    result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ ' + response.data.message + '</p></div>';
                }
            }).fail(function(xhr) {
                result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ <?php echo esc_js(__('Request failed:', 'folio')); ?>' + xhr.status + '</p></div>';
            });
        }

        </script>
        <?php
    }

    /**
     * 渲染概览标签
     */
    private function render_overview_tab($stats) {
        ?>
        <div class="folio-stats-grid">
            <div class="folio-stat-card">
                <div class="folio-stat-number"><?php echo esc_html($stats['total_users']); ?></div>
                <div class="folio-stat-label"><?php esc_html_e('Total Users', 'folio'); ?></div>
            </div>
            <div class="folio-stat-card folio-stat-vip">
                <div class="folio-stat-number"><?php echo esc_html($stats['vip_users']); ?></div>
                <div class="folio-stat-label"><?php esc_html_e('VIP Users', 'folio'); ?></div>
            </div>
            <div class="folio-stat-card folio-stat-svip">
                <div class="folio-stat-number"><?php echo esc_html($stats['svip_users']); ?></div>
                <div class="folio-stat-label"><?php esc_html_e('SVIP Users', 'folio'); ?></div>
            </div>
            <div class="folio-stat-card folio-stat-expiring">
                <div class="folio-stat-number"><?php echo esc_html($stats['expiring_soon']); ?></div>
                <div class="folio-stat-label"><?php esc_html_e('Expiring Within 7 Days', 'folio'); ?></div>
            </div>
        </div>

        <div class="folio-memory-status">
            <h3><span class="dashicons dashicons-performance"></span> <?php esc_html_e('System Status', 'folio'); ?></h3>
            <?php
            $memory_usage = memory_get_usage(true) / 1024 / 1024;
            $memory_peak = memory_get_peak_usage(true) / 1024 / 1024;
            $memory_limit = ini_get('memory_limit');
            
            $status_class = $memory_usage > 200 ? 'folio-memory-danger' : ($memory_usage > 100 ? 'folio-memory-warning' : 'folio-memory-safe');
            ?>
            <p>
                <strong><?php esc_html_e('Current Memory Usage:', 'folio'); ?></strong> <?php echo number_format($memory_usage, 2); ?> MB<br>
                <strong><?php esc_html_e('Peak Memory Usage:', 'folio'); ?></strong> <?php echo number_format($memory_peak, 2); ?> MB<br>
                <strong><?php esc_html_e('PHP Memory Limit:', 'folio'); ?></strong> <?php echo esc_html($memory_limit); ?>
            </p>
            <div class="folio-memory-indicator <?php echo esc_attr($status_class); ?>">
                <?php
                if ($status_class === 'folio-memory-safe') {
                    echo esc_html__('✅ Memory usage is normal', 'folio');
                } elseif ($status_class === 'folio-memory-warning') {
                    echo esc_html__('⚠️ Memory usage is high', 'folio');
                } else {
                    echo esc_html__('❌ Memory usage is too high', 'folio');
                }
                ?>
            </div>
        </div>

        <h3><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('Recent Membership Activity', 'folio'); ?></h3>
        <?php $this->render_recent_members(); ?>
        <?php
    }

    /**
     * 渲染会员管理标签
     */
    private function render_members_tab() {
        // 获取筛选和排序参数，默认只显示会员（VIP和SVIP）
        $filter_level = isset($_GET['filter_level']) ? sanitize_text_field($_GET['filter_level']) : '';
        // 如果筛选为空，默认排除普通用户
        if ($filter_level === '') {
            $filter_level = 'all_members'; // 特殊值，表示所有会员（不包括普通用户）
        }
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'level';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
        
        // 获取所有会员用户
        $members = $this->get_all_members($filter_level, $orderby, $order);
        
        // 构建排序URL
        $base_url = admin_url('admin.php?page=folio-membership&tab=members');
        $filter_url = ($filter_level && $filter_level !== 'all_members') ? '&filter_level=' . urlencode($filter_level) : '';
        
        // 获取排序链接
        $get_sort_url = function($column) use ($base_url, $filter_url, $orderby, $order) {
            $new_order = ($orderby === $column && $order === 'asc') ? 'desc' : 'asc';
            return $base_url . $filter_url . '&orderby=' . urlencode($column) . '&order=' . $new_order;
        };
        
        // 获取排序图标
        $get_sort_icon = function($column) use ($orderby, $order) {
            if ($orderby !== $column) {
                return '<span class="sorting-indicator" aria-label="' . esc_attr__('Sort', 'folio') . '"></span>';
            }
            return $order === 'asc' 
                ? '<span class="sorting-indicator asc" aria-label="' . esc_attr__('Ascending', 'folio') . '"></span>'
                : '<span class="sorting-indicator desc" aria-label="' . esc_attr__('Descending', 'folio') . '"></span>';
        };
        ?>
        <h3><?php esc_html_e('Membership Management', 'folio'); ?></h3>
        <p><?php esc_html_e('View detailed information for all membership users here.', 'folio'); ?></p>
        
        <!-- 筛选器 -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <label for="filter-level" class="screen-reader-text"><?php esc_html_e('Filter by membership level', 'folio'); ?></label>
                <select name="filter_level" id="filter-level" onchange="location.href='<?php echo esc_url($base_url); ?>&filter_level='+this.value">
                    <option value="all_members" <?php selected($filter_level, 'all_members'); ?>><?php esc_html_e('All Members', 'folio'); ?></option>
                    <option value="svip" <?php selected($filter_level, 'svip'); ?>><?php esc_html_e('SVIP Members', 'folio'); ?></option>
                    <option value="vip" <?php selected($filter_level, 'vip'); ?>><?php esc_html_e('VIP Members', 'folio'); ?></option>
                </select>
            </div>
            <div class="alignright">
                <span class="displaying-num"><?php echo esc_html(sprintf(__('%d members', 'folio'), count($members))); ?></span>
            </div>
        </div>
        
        <?php if (empty($members)) : ?>
        <div class="notice notice-info">
                <p><?php esc_html_e('No members match the current filter.', 'folio'); ?></p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-id sortable <?php echo $orderby === 'id' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('id')); ?>">
                                <span><?php esc_html_e('User ID', 'folio'); ?></span>
                                <?php echo $get_sort_icon('id'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-username column-primary sortable <?php echo $orderby === 'username' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('username')); ?>">
                                <span><?php esc_html_e('Username', 'folio'); ?></span>
                                <?php echo $get_sort_icon('username'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-display-name sortable <?php echo $orderby === 'display_name' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('display_name')); ?>">
                                <span><?php esc_html_e('Display Name', 'folio'); ?></span>
                                <?php echo $get_sort_icon('display_name'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-email sortable <?php echo $orderby === 'email' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('email')); ?>">
                                <span><?php esc_html_e('Email', 'folio'); ?></span>
                                <?php echo $get_sort_icon('email'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-level sortable <?php echo $orderby === 'level' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('level')); ?>">
                                <span><?php esc_html_e('Membership Level', 'folio'); ?></span>
                                <?php echo $get_sort_icon('level'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-expiry sortable <?php echo $orderby === 'expiry' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('expiry')); ?>">
                                <span><?php esc_html_e('Expiry Date', 'folio'); ?></span>
                                <?php echo $get_sort_icon('expiry'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-status sortable <?php echo $orderby === 'status' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('status')); ?>">
                                <span><?php esc_html_e('Status', 'folio'); ?></span>
                                <?php echo $get_sort_icon('status'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-registered sortable <?php echo $orderby === 'registered' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('registered')); ?>">
                                <span><?php esc_html_e('Registration Date', 'folio'); ?></span>
                                <?php echo $get_sort_icon('registered'); ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php foreach ($members as $member) : ?>
                        <tr>
                            <td class="id column-id" data-colname="<?php echo esc_attr__('User ID', 'folio'); ?>">
                                <?php echo esc_html($member['user_id']); ?>
                            </td>
                            <td class="username column-username column-primary" data-colname="<?php echo esc_attr__('Username', 'folio'); ?>">
                                <strong><?php echo esc_html($member['username']); ?></strong>
                            </td>
                            <td class="display-name column-display-name" data-colname="<?php echo esc_attr__('Display Name', 'folio'); ?>">
                                <?php echo esc_html($member['display_name']); ?>
                            </td>
                            <td class="email column-email" data-colname="<?php echo esc_attr__('Email', 'folio'); ?>">
                                <?php echo esc_html($member['email']); ?>
                            </td>
                            <td class="level column-level" data-colname="<?php echo esc_attr__('Membership Level', 'folio'); ?>">
                                <?php 
                                $level_names = array(
                                    'free' => __('Regular User', 'folio'),
                                    'vip' => __('VIP Member', 'folio'),
                                    'svip' => __('SVIP Member', 'folio')
                                );
                                $level_name = isset($level_names[$member['level']]) ? $level_names[$member['level']] : $member['level'];
                                $level_colors = array(
                                    'free' => '#6b7280',
                                    'vip' => '#f59e0b',
                                    'svip' => '#8b5cf6'
                                );
                                $level_color = isset($level_colors[$member['level']]) ? $level_colors[$member['level']] : '#6b7280';
                                ?>
                                <span style="color: <?php echo esc_attr($level_color); ?>; font-weight: 600;">
                                    <?php echo esc_html($level_name); ?>
                                </span>
                            </td>
                            <td class="expiry column-expiry" data-colname="<?php echo esc_attr__('Expiry Date', 'folio'); ?>">
                                <?php 
                                if ($member['is_permanent']) {
                                    echo '<span style="color: #10b981; font-weight: 600;">' . esc_html__('Permanent Member', 'folio') . '</span>';
                                } elseif (!empty($member['expiry_display'])) {
                                    echo esc_html($member['expiry_display']);
                                    if ($member['days_left'] !== null) {
                                        $days_color = $member['days_left'] <= 7 ? '#ef4444' : ($member['days_left'] <= 30 ? '#f59e0b' : '#6b7280');
                                        echo '<br><small style="color: ' . esc_attr($days_color) . ';">' . sprintf(esc_html__('%d days left', 'folio'), $member['days_left']) . '</small>';
                                    }
                                } else {
                                    echo '<span style="color: #9ca3af;">—</span>';
                                }
                                ?>
                            </td>
                            <td class="status column-status" data-colname="<?php echo esc_attr__('Status', 'folio'); ?>">
                                <?php
                                if ($member['level'] === 'free') {
                                    echo '<span style="color: #6b7280;">' . esc_html__('Regular User', 'folio') . '</span>';
                                } elseif ($member['is_expired']) {
                                    echo '<span style="color: #ef4444;">' . esc_html__('Expired', 'folio') . '</span>';
                                } elseif ($member['is_permanent']) {
                                    echo '<span style="color: #10b981;">' . esc_html__('Active', 'folio') . '</span>';
                                } else {
                                    echo '<span style="color: #10b981;">' . esc_html__('Active', 'folio') . '</span>';
                                }
                                ?>
                            </td>
                            <td class="registered column-registered" data-colname="<?php echo esc_attr__('Registration Date', 'folio'); ?>">
                                <?php echo esc_html($member['registered']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="notice notice-info" style="margin-top: 20px;">
            <p><strong><?php esc_html_e('Tip:', 'folio'); ?></strong> <?php esc_html_e('You can set each user\'s membership level and expiry date on the user edit page.', 'folio'); ?></p>
        </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * 获取所有会员用户信息
     */
    private function get_all_members($filter_level = 'all_members', $orderby = 'level', $order = 'desc') {
        global $wpdb;
        
        // 构建查询参数
        $query_args = array(
            'meta_key' => 'folio_membership_level',
            'meta_compare' => 'EXISTS'
        );
        
        // 如果指定了等级筛选
        if ($filter_level === 'all_members') {
            // 所有会员（VIP和SVIP），排除普通用户
            $query_args['meta_query'] = array(
                array(
                    'key' => 'folio_membership_level',
                    'value' => array('vip', 'svip'),
                    'compare' => 'IN'
                )
            );
        } elseif ($filter_level === 'svip' || $filter_level === 'vip') {
            // 特定等级的会员
            $query_args['meta_value'] = $filter_level;
        }
        // 如果filter_level为空或'free'，不返回任何结果（因为这是会员管理页面）
        
        // 获取所有有会员等级的用户
        $users = get_users($query_args);
        
        $members = array();
        
        foreach ($users as $user) {
            $membership = folio_Membership_Safe::get_user_membership($user->ID);
            
            // 检查是否过期
            $is_expired = false;
            if ($membership['expiry'] && !$membership['is_permanent']) {
                $expiry_timestamp = strtotime($membership['expiry'] . ' 23:59:59');
                $current_timestamp = current_time('timestamp');
                $is_expired = $expiry_timestamp < $current_timestamp;
            }
            
            // 状态值用于排序
            $status_value = __('Active', 'folio');
            if ($membership['level'] === 'free') {
                $status_value = __('Regular User', 'folio');
            } elseif ($is_expired) {
                $status_value = __('Expired', 'folio');
            }
            
            $members[] = array(
                'user_id' => $user->ID,
                'username' => $user->user_login,
                'display_name' => $user->display_name ?: $user->user_login,
                'email' => $user->user_email,
                'level' => $membership['level'],
                'level_name' => $membership['name'],
                'expiry' => $membership['expiry'] ?: '9999-12-31', // 永久会员用最大日期排序
                'expiry_display' => $membership['expiry'],
                'is_permanent' => $membership['is_permanent'],
                'days_left' => $membership['days_left'],
                'is_expired' => $is_expired,
                'status' => $status_value,
                'registered' => date('Y-m-d', strtotime($user->user_registered)),
                'registered_timestamp' => strtotime($user->user_registered)
            );
        }
        
        // 排序
        usort($members, function($a, $b) use ($orderby, $order) {
            $result = 0;
            
            switch ($orderby) {
                case 'id':
                    $result = $a['user_id'] - $b['user_id'];
                    break;
                case 'username':
                    $result = strcasecmp($a['username'], $b['username']);
                    break;
                case 'display_name':
                    $result = strcasecmp($a['display_name'], $b['display_name']);
                    break;
                case 'email':
                    $result = strcasecmp($a['email'], $b['email']);
                    break;
                case 'level':
                    $level_order = array('svip' => 3, 'vip' => 2, 'free' => 1);
                    $a_order = isset($level_order[$a['level']]) ? $level_order[$a['level']] : 0;
                    $b_order = isset($level_order[$b['level']]) ? $level_order[$b['level']] : 0;
                    $result = $a_order - $b_order;
                    break;
                case 'expiry':
                    $a_expiry = $a['is_permanent'] ? '9999-12-31' : $a['expiry'];
                    $b_expiry = $b['is_permanent'] ? '9999-12-31' : $b['expiry'];
                    $result = strcmp($a_expiry, $b_expiry);
                    break;
                case 'status':
                    $status_order = array(
                        __('Active', 'folio') => 3,
                        __('Regular User', 'folio') => 2,
                        __('Expired', 'folio') => 1
                    );
                    $a_order = isset($status_order[$a['status']]) ? $status_order[$a['status']] : 0;
                    $b_order = isset($status_order[$b['status']]) ? $status_order[$b['status']] : 0;
                    $result = $a_order - $b_order;
                    break;
                case 'registered':
                    $result = $a['registered_timestamp'] - $b['registered_timestamp'];
                    break;
                default:
                    // 默认按等级和注册时间排序
                    $level_order = array('svip' => 3, 'vip' => 2, 'free' => 1);
                    $a_order = isset($level_order[$a['level']]) ? $level_order[$a['level']] : 0;
                    $b_order = isset($level_order[$b['level']]) ? $level_order[$b['level']] : 0;
                    if ($a_order !== $b_order) {
                        $result = $a_order - $b_order;
                    } else {
                        $result = $a['registered_timestamp'] - $b['registered_timestamp'];
                    }
            }
            
            return $order === 'asc' ? $result : -$result;
        });
        
        return $members;
    }

    /**
     * 渲染设置标签
     */
    private function render_settings_tab() {
        $options = get_option('folio_membership_options', array());
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('folio_membership_settings');
            ?>
            <h3><?php esc_html_e('System Settings', 'folio'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Memory Protection', 'folio'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="folio_membership_options[memory_protection]" value="1" 
                                   <?php checked(isset($options['memory_protection']) ? $options['memory_protection'] : 1); ?>>
                            <?php esc_html_e('Enable memory protection (recommended)', 'folio'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Automatically disable some features when memory usage is too high', 'folio'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Batch Size', 'folio'); ?></th>
                    <td>
                        <input type="number" name="folio_membership_options[batch_size]" 
                               value="<?php echo esc_attr(isset($options['batch_size']) ? $options['batch_size'] : 50); ?>" 
                               min="10" max="200" class="small-text">
                        <p class="description"><?php esc_html_e('Batch size when processing user data', 'folio'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Cache Time', 'folio'); ?></th>
                    <td>
                        <input type="number" name="folio_membership_options[cache_time]" 
                               value="<?php echo esc_attr(isset($options['cache_time']) ? $options['cache_time'] : 300); ?>" 
                               min="60" max="3600" class="small-text"> <?php esc_html_e('seconds', 'folio'); ?>
                        <p class="description"><?php esc_html_e('Cache duration for user membership information', 'folio'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h3 style="margin-top: 30px;"><?php esc_html_e('Membership Benefits Comparison Settings', 'folio'); ?></h3>
            <p class="description"><?php esc_html_e('Configure benefits comparison data shown on membership upgrade pages', 'folio'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Membership Pricing', 'folio'); ?></th>
                    <td>
                        <div style="display: flex; gap: 20px; align-items: center;">
                            <div>
                                <label for="vip_price"><?php esc_html_e('VIP Price:', 'folio'); ?></label>
                                <input type="text" id="vip_price" name="folio_membership_options[membership_prices][vip]" 
                                       value="<?php echo esc_attr(isset($options['membership_prices']['vip']) ? $options['membership_prices']['vip'] : __('¥68/month', 'folio')); ?>" 
                                       class="regular-text" placeholder="<?php echo esc_attr__('¥68/month', 'folio'); ?>">
                            </div>
                            <div>
                                <label for="svip_price"><?php esc_html_e('SVIP Price:', 'folio'); ?></label>
                                <input type="text" id="svip_price" name="folio_membership_options[membership_prices][svip]" 
                                       value="<?php echo esc_attr(isset($options['membership_prices']['svip']) ? $options['membership_prices']['svip'] : __('¥128/month', 'folio')); ?>" 
                                       class="regular-text" placeholder="<?php echo esc_attr__('¥128/month', 'folio'); ?>">
                            </div>
                        </div>
                        <p class="description"><?php esc_html_e('Membership prices displayed on the upgrade page', 'folio'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h3 style="margin-top: 30px;"><?php esc_html_e('Payment Settings', 'folio'); ?></h3>
            <p class="description"><?php esc_html_e('Configure payment QR code and instructions for membership purchase', 'folio'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Payment QR Code', 'folio'); ?></th>
                    <td>
                        <div style="display: flex; gap: 10px; align-items: flex-start; margin-bottom: 10px;">
                            <input type="url" 
                                   id="payment_qr_code_url" 
                                   name="folio_membership_options[payment_qr_code]" 
                                   value="<?php echo esc_url(isset($options['payment_qr_code']) ? $options['payment_qr_code'] : ''); ?>" 
                                   class="regular-text" 
                                   placeholder="https://example.com/qr-code.jpg"
                                   style="flex: 1;">
                            <button type="button" 
                                    id="upload_payment_qr_code" 
                                    class="button">
                                <?php esc_html_e('Select Image', 'folio'); ?>
                            </button>
                            <?php if (!empty($options['payment_qr_code'])) : ?>
                            <button type="button" 
                                    id="remove_payment_qr_code" 
                                    class="button"
                                    style="color: #dc3232;">
                                <?php esc_html_e('Remove', 'folio'); ?>
                            </button>
                            <?php endif; ?>
                        </div>
                        <p class="description"><?php esc_html_e('Payment QR image URL (supports WeChat Pay, Alipay, etc.). You can upload an image directly or enter an image URL.', 'folio'); ?></p>
                        <div id="payment_qr_code_preview" style="margin-top: 10px;">
                            <?php if (!empty($options['payment_qr_code'])) : ?>
                            <img src="<?php echo esc_url($options['payment_qr_code']); ?>" 
                                 alt="<?php echo esc_attr__('Payment QR Code', 'folio'); ?>" 
                                 style="max-width: 200px; border: 1px solid #ddd; padding: 5px; background: #fff; display: block;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Payment Instructions', 'folio'); ?></th>
                    <td>
                        <?php 
                        $default_instructions = __('Payment Steps:', 'folio') . "\n" .
                            '1. ' . __('Select the membership type you want to purchase (VIP or SVIP)', 'folio') . "\n" .
                            '2. ' . __('Click the \"Upgrade VIP\" or \"Upgrade SVIP\" button', 'folio') . "\n" .
                            '3. ' . __('Use WeChat Pay or Alipay to scan the QR code above and complete payment', 'folio') . "\n" .
                            '4. ' . __('After payment, please save a screenshot as payment proof', 'folio') . "\n" .
                            '5. ' . __('Contact support or send payment proof. We will activate your membership within 24 hours', 'folio') . "\n\n" .
                            __('Notes:', 'folio') . "\n" .
                            '• ' . __('Please ensure the payment amount matches the selected membership type', 'folio') . "\n" .
                            '• ' . __('Please keep your payment proof after payment for verification', 'folio') . "\n" .
                            '• ' . __('If you have any questions, please contact customer support', 'folio');
                        $current_instructions = isset($options['payment_instructions']) && !empty($options['payment_instructions']) 
                            ? $options['payment_instructions'] 
                            : $default_instructions;
                        ?>
                        <textarea name="folio_membership_options[payment_instructions]" 
                                  rows="12" 
                                  class="large-text"><?php echo esc_textarea($current_instructions); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Payment steps and notes (HTML supported). If left empty, default instructions will be shown.', 'folio'); ?><br>
                            <strong><?php esc_html_e('Default instructions:', 'folio'); ?></strong> <?php esc_html_e('If custom instructions are not set, the default payment guide will be displayed.', 'folio'); ?>
                        </p>
                        <?php if (empty($options['payment_instructions'])) : ?>
                        <div style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1; font-size: 13px;">
                            <strong><?php esc_html_e('Currently using default instructions:', 'folio'); ?></strong>
                            <div style="margin-top: 5px; white-space: pre-wrap; color: #50575e;"><?php echo esc_html($default_instructions); ?></div>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Contact Information', 'folio'); ?></th>
                    <td>
                        <input type="text" name="folio_membership_options[payment_contact]" 
                               value="<?php echo esc_attr(isset($options['payment_contact']) ? $options['payment_contact'] : ''); ?>" 
                               class="regular-text" placeholder="<?php echo esc_attr__('For example: Support QQ: 123456789 or WeChat: example', 'folio'); ?>">
                        <p class="description"><?php esc_html_e('Contact info for customer support after payment', 'folio'); ?></p>
                    </td>
                </tr>
            </table>
            
            <!-- VIP权益对比设置 -->
            <h4 style="margin-top: 30px;"><?php esc_html_e('VIP Benefits Comparison', 'folio'); ?></h4>
            <div id="vip-benefits-container" style="margin-bottom: 30px;">
                <?php
                $vip_benefits = isset($options['benefits_comparison']['vip']) && is_array($options['benefits_comparison']['vip']) 
                    ? $options['benefits_comparison']['vip'] 
                    : self::get_default_vip_benefits();
                
                foreach ($vip_benefits as $index => $benefit) :
                ?>
                <div class="benefit-row" style="display: grid; grid-template-columns: 3fr 2fr 2fr auto; gap: 12px; margin-bottom: 10px; align-items: center;">
                    <input type="text" name="folio_membership_options[benefits_comparison][vip][<?php echo $index; ?>][name]" 
                           value="<?php echo esc_attr($benefit['name']); ?>" 
                           placeholder="<?php echo esc_attr__('Benefit name', 'folio'); ?>" style="width: 100%;" required>
                    <input type="text" name="folio_membership_options[benefits_comparison][vip][<?php echo $index; ?>][normal]" 
                           value="<?php echo esc_attr($benefit['normal']); ?>" 
                           placeholder="<?php echo esc_attr__('Free user (e.g. x or ads)', 'folio'); ?>" style="width: 100%;">
                    <input type="text" name="folio_membership_options[benefits_comparison][vip][<?php echo $index; ?>][vip]" 
                           value="<?php echo esc_attr($benefit['vip']); ?>" 
                           placeholder="<?php echo esc_attr__('VIP user (e.g. checkmark or specific benefit)', 'folio'); ?>" style="width: 100%;">
                    <button type="button" class="button button-small" onclick="removeBenefitRow(this)" style="color: #dc3232; white-space: nowrap;"><?php esc_html_e('Delete', 'folio'); ?></button>
                </div>
                <?php endforeach; ?>
                <button type="button" class="button" onclick="addVipBenefit()" style="margin-top: 10px;"><?php esc_html_e('+ Add Benefit', 'folio'); ?></button>
            </div>
            
            <!-- SVIP权益对比设置 -->
            <h4 style="margin-top: 30px;"><?php esc_html_e('SVIP Benefits Comparison', 'folio'); ?></h4>
            <div id="svip-benefits-container" style="margin-bottom: 30px;">
                <?php
                $svip_benefits = isset($options['benefits_comparison']['svip']) && is_array($options['benefits_comparison']['svip']) 
                    ? $options['benefits_comparison']['svip'] 
                    : self::get_default_svip_benefits();
                
                foreach ($svip_benefits as $index => $benefit) :
                ?>
                <div class="benefit-row" style="display: grid; grid-template-columns: 3fr 2fr 2fr 2fr auto; gap: 12px; margin-bottom: 10px; align-items: center;">
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][<?php echo $index; ?>][name]" 
                           value="<?php echo esc_attr($benefit['name']); ?>" 
                           placeholder="<?php echo esc_attr__('Benefit name', 'folio'); ?>" style="width: 100%;" required>
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][<?php echo $index; ?>][normal]" 
                           value="<?php echo esc_attr($benefit['normal']); ?>" 
                           placeholder="<?php echo esc_attr__('Free user', 'folio'); ?>" style="width: 100%;">
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][<?php echo $index; ?>][vip]" 
                           value="<?php echo esc_attr(isset($benefit['vip']) ? $benefit['vip'] : ''); ?>" 
                           placeholder="<?php echo esc_attr__('VIP user', 'folio'); ?>" style="width: 100%;">
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][<?php echo $index; ?>][svip]" 
                           value="<?php echo esc_attr(isset($benefit['svip']) ? $benefit['svip'] : ''); ?>" 
                           placeholder="<?php echo esc_attr__('SVIP user (e.g. checkmark)', 'folio'); ?>" style="width: 100%;">
                    <button type="button" class="button button-small" onclick="removeBenefitRow(this)" style="color: #dc3232; white-space: nowrap;"><?php esc_html_e('Delete', 'folio'); ?></button>
                </div>
                <?php endforeach; ?>
                <button type="button" class="button" onclick="addSvipBenefit()" style="margin-top: 10px;"><?php esc_html_e('+ Add Benefit', 'folio'); ?></button>
            </div>
            
            <script>
            let vipIndex = <?php echo count($vip_benefits); ?>;
            let svipIndex = <?php echo count($svip_benefits); ?>;
            
            function addVipBenefit() {
                const container = document.getElementById('vip-benefits-container');
                const row = document.createElement('div');
                row.className = 'benefit-row';
                row.style.cssText = 'display: grid; grid-template-columns: 3fr 2fr 2fr auto; gap: 12px; margin-bottom: 10px; align-items: center;';
                row.innerHTML = `
                    <input type="text" name="folio_membership_options[benefits_comparison][vip][${vipIndex}][name]" 
                           placeholder="<?php echo esc_attr__('Benefit name', 'folio'); ?>" style="width: 100%;" required>
                    <input type="text" name="folio_membership_options[benefits_comparison][vip][${vipIndex}][normal]" 
                           placeholder="<?php echo esc_attr__('Free user (e.g. x or ads)', 'folio'); ?>" style="width: 100%;">
                    <input type="text" name="folio_membership_options[benefits_comparison][vip][${vipIndex}][vip]" 
                           placeholder="<?php echo esc_attr__('VIP user (e.g. checkmark)', 'folio'); ?>" style="width: 100%;">
                    <button type="button" class="button button-small" onclick="removeBenefitRow(this)" style="color: #dc3232; white-space: nowrap;"><?php echo esc_js(__('Delete', 'folio')); ?></button>
                `;
                container.insertBefore(row, container.lastElementChild);
                vipIndex++;
            }
            
            function addSvipBenefit() {
                const container = document.getElementById('svip-benefits-container');
                const row = document.createElement('div');
                row.className = 'benefit-row';
                row.style.cssText = 'display: grid; grid-template-columns: 3fr 2fr 2fr 2fr auto; gap: 12px; margin-bottom: 10px; align-items: center;';
                row.innerHTML = `
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][${svipIndex}][name]" 
                           placeholder="<?php echo esc_attr__('Benefit name', 'folio'); ?>" style="width: 100%;" required>
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][${svipIndex}][normal]" 
                           placeholder="<?php echo esc_attr__('Free user', 'folio'); ?>" style="width: 100%;">
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][${svipIndex}][vip]" 
                           placeholder="<?php echo esc_attr__('VIP user', 'folio'); ?>" style="width: 100%;">
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][${svipIndex}][svip]" 
                           placeholder="<?php echo esc_attr__('SVIP user (e.g. checkmark)', 'folio'); ?>" style="width: 100%;">
                    <button type="button" class="button button-small" onclick="removeBenefitRow(this)" style="color: #dc3232; white-space: nowrap;"><?php echo esc_js(__('Delete', 'folio')); ?></button>
                `;
                container.insertBefore(row, container.lastElementChild);
                svipIndex++;
            }
            
            function removeBenefitRow(button) {
                if (confirm('<?php echo esc_js(__('Are you sure you want to delete this benefit item?', 'folio')); ?>')) {
                    button.closest('.benefit-row').remove();
                }
            }
            
            // 支付二维码上传功能
            jQuery(document).ready(function($) {
                var mediaUploader;
                
                // 选择图片按钮
                $('#upload_payment_qr_code').on('click', function(e) {
                    e.preventDefault();
                    
                    // 如果媒体上传器已存在，先打开它
                    if (mediaUploader) {
                        mediaUploader.open();
                        return;
                    }
                    
                    // 创建媒体上传器
                    mediaUploader = wp.media({
                        title: '<?php echo esc_js(__('Select payment QR code image', 'folio')); ?>',
                        button: {
                            text: '<?php echo esc_js(__('Use this image', 'folio')); ?>'
                        },
                        multiple: false,
                        library: {
                            type: 'image'
                        }
                    });
                    
                    // 当选择图片后
                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        $('#payment_qr_code_url').val(attachment.url);
                        
                        // 显示预览
                        var previewHtml = '<img src="' + attachment.url + '" alt="<?php echo esc_js(__('Payment QR Code', 'folio')); ?>" style="max-width: 200px; border: 1px solid #ddd; padding: 5px; background: #fff; display: block;">';
                        $('#payment_qr_code_preview').html(previewHtml);
                        
                        // 显示移除按钮（如果还没有）
                        if ($('#remove_payment_qr_code').length === 0) {
                            $('#upload_payment_qr_code').after('<button type="button" id="remove_payment_qr_code" class="button" style="color: #dc3232;"><?php echo esc_js(__('Remove', 'folio')); ?></button>');
                        }
                    });
                    
                    // 打开媒体上传器
                    mediaUploader.open();
                });
                
                // 移除图片按钮
                $(document).on('click', '#remove_payment_qr_code', function(e) {
                    e.preventDefault();
                    $('#payment_qr_code_url').val('');
                    $('#payment_qr_code_preview').html('');
                    $(this).remove();
                });
            });
            </script>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * 获取默认VIP权益数据
     */
    private static function get_default_vip_benefits() {
        return array(
            array('name' => __('View VIP exclusive content', 'folio'), 'normal' => '×', 'vip' => '✓'),
            array('name' => __('Ad-free browsing experience', 'folio'), 'normal' => __('Ads', 'folio'), 'vip' => '✓'),
            array('name' => __('Priority customer support', 'folio'), 'normal' => __('Standard queue', 'folio'), 'vip' => '✓'),
            array('name' => __('Exclusive member badge', 'folio'), 'normal' => '×', 'vip' => '✓'),
            array('name' => __('High-resolution image downloads', 'folio'), 'normal' => __('Limited downloads', 'folio'), 'vip' => '✓'),
            array('name' => __('Post favorites feature', 'folio'), 'normal' => '×', 'vip' => '✓'),
            array('name' => __('Priority comment display', 'folio'), 'normal' => __('Standard order', 'folio'), 'vip' => '✓'),
            array('name' => __('Exclusive content push', 'folio'), 'normal' => '×', 'vip' => '✓'),
            array('name' => __('Member-only activities', 'folio'), 'normal' => '×', 'vip' => '✓')
        );
    }
    
    /**
     * 获取默认SVIP权益数据
     */
    private static function get_default_svip_benefits() {
        return array(
            array('name' => __('View all exclusive content', 'folio'), 'normal' => '×', 'vip' => __('Partial content', 'folio'), 'svip' => '✓'),
            array('name' => __('Ad-free browsing experience', 'folio'), 'normal' => __('Ads', 'folio'), 'vip' => '✓', 'svip' => '✓'),
            array('name' => __('24/7 dedicated support', 'folio'), 'normal' => __('Business hours', 'folio'), 'vip' => __('Priority support', 'folio'), 'svip' => '✓'),
            array('name' => __('Exclusive SVIP badge', 'folio'), 'normal' => '×', 'vip' => __('VIP badge', 'folio'), 'svip' => '✓'),
            array('name' => __('Exclusive high-quality resources', 'folio'), 'normal' => '×', 'vip' => __('Standard resources', 'folio'), 'svip' => '✓'),
            array('name' => __('Early access to new features', 'folio'), 'normal' => '×', 'vip' => '×', 'svip' => '✓'),
            array('name' => __('Unlimited download access', 'folio'), 'normal' => __('Limited downloads', 'folio'), 'vip' => __('Limited download quota', 'folio'), 'svip' => '✓'),
            array('name' => __('Exclusive content customization', 'folio'), 'normal' => '×', 'vip' => '×', 'svip' => '✓'),
            array('name' => __('SVIP exclusive activities', 'folio'), 'normal' => '×', 'vip' => __('Partial activities', 'folio'), 'svip' => '✓')
        );
    }

    /**
     * 渲染会员统计标签（原 folio-membership-analytics 页面已并入）
     */
    private function render_analytics_tab() {
        if (!class_exists('folio_Membership_Analytics')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Membership analytics module is not loaded.', 'folio') . '</p></div>';
            return;
        }

        $days = isset($_GET['days']) ? absint($_GET['days']) : 30;
        if (!in_array($days, array(7, 30, 90), true)) {
            $days = 30;
        }

        $content_stats = folio_Membership_Analytics::get_membership_content_stats($days);
        $access_stats = folio_Membership_Analytics::get_access_stats($days);
        $conversion_stats = folio_Membership_Analytics::get_conversion_stats($days);
        $popular_articles = folio_Membership_Analytics::get_popular_protected_articles($days, 20);

        $base_url = admin_url('admin.php?page=folio-membership&tab=analytics');
        $export_url = wp_nonce_url(
            admin_url('admin-ajax.php?action=folio_export_analytics'),
            'folio_export_analytics'
        );
        ?>
        <h3><?php esc_html_e('Membership Analytics', 'folio'); ?></h3>
        <p>
            <?php esc_html_e('Statistics window:', 'folio'); ?>
            <a class="button button-small <?php echo $days === 7 ? 'button-primary' : ''; ?>" href="<?php echo esc_url($base_url . '&days=7'); ?>">7<?php esc_html_e(' days', 'folio'); ?></a>
            <a class="button button-small <?php echo $days === 30 ? 'button-primary' : ''; ?>" href="<?php echo esc_url($base_url . '&days=30'); ?>">30<?php esc_html_e(' days', 'folio'); ?></a>
            <a class="button button-small <?php echo $days === 90 ? 'button-primary' : ''; ?>" href="<?php echo esc_url($base_url . '&days=90'); ?>">90<?php esc_html_e(' days', 'folio'); ?></a>
            <a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export Report', 'folio'); ?></a>
        </p>

        <div class="folio-stats-grid">
            <div class="folio-stat-card">
                <div class="folio-stat-number"><?php echo esc_html($content_stats['total_protected_articles']); ?></div>
                <div class="folio-stat-label"><?php esc_html_e('Protected Articles', 'folio'); ?></div>
            </div>
            <div class="folio-stat-card">
                <div class="folio-stat-number"><?php echo esc_html($content_stats['vip_articles']); ?></div>
                <div class="folio-stat-label"><?php esc_html_e('VIP Articles', 'folio'); ?></div>
            </div>
            <div class="folio-stat-card">
                <div class="folio-stat-number"><?php echo esc_html($content_stats['svip_articles']); ?></div>
                <div class="folio-stat-label"><?php esc_html_e('SVIP Articles', 'folio'); ?></div>
            </div>
            <div class="folio-stat-card">
                <div class="folio-stat-number"><?php echo esc_html($access_stats['total_access']); ?></div>
                <div class="folio-stat-label"><?php esc_html_e('Total Accesses', 'folio'); ?></div>
            </div>
            <div class="folio-stat-card">
                <div class="folio-stat-number"><?php echo esc_html($access_stats['denied_access']); ?></div>
                <div class="folio-stat-label"><?php esc_html_e('Denied Access', 'folio'); ?></div>
            </div>
            <div class="folio-stat-card">
                <div class="folio-stat-number"><?php echo esc_html($conversion_stats['conversion_rate']); ?>%</div>
                <div class="folio-stat-label"><?php esc_html_e('Conversion Rate', 'folio'); ?></div>
            </div>
        </div>

        <h3><?php echo esc_html(sprintf(__('Top Protected Posts (%d days)', 'folio'), $days)); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Post Title', 'folio'); ?></th>
                    <th><?php esc_html_e('Total Access', 'folio'); ?></th>
                    <th><?php esc_html_e('Granted', 'folio'); ?></th>
                    <th><?php esc_html_e('Denied', 'folio'); ?></th>
                    <th><?php esc_html_e('Conversions', 'folio'); ?></th>
                    <th><?php esc_html_e('Conversion Rate', 'folio'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($popular_articles)) : ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No analytics data found in the selected period.', 'folio'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($popular_articles as $article) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($article->ID)); ?>">
                                    <?php echo esc_html($article->post_title); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($article->total_access); ?></td>
                            <td><?php echo esc_html($article->granted_access); ?></td>
                            <td><?php echo esc_html($article->denied_access); ?></td>
                            <td><?php echo esc_html($article->conversions); ?></td>
                            <td><?php echo esc_html($article->conversion_rate); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * 渲染工具标签
     */
    private function render_tools_tab() {
        ?>
        <h3><?php esc_html_e('System Tools', 'folio'); ?></h3>
        <p><?php esc_html_e('Use the following tools to test and maintain the membership system.', 'folio'); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('System Test', 'folio'); ?></th>
                <td>
                    <button type="button" class="button button-primary" onclick="testMembershipSystem()">
                        <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('Test Membership System', 'folio'); ?>
                    </button>
                    <p class="description"><?php esc_html_e('Test whether all membership system functions work properly', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Expiry Check', 'folio'); ?></th>
                <td>
                    <button type="button" class="button" onclick="checkExpiredMembers()">
                        <span class="dashicons dashicons-clock"></span> <?php esc_html_e('Check Expired Members', 'folio'); ?>
                    </button>
                    <p class="description"><?php esc_html_e('Manually check and process expired membership accounts', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Cache Management', 'folio'); ?></th>
                <td>
                    <button type="button" class="button button-secondary" onclick="clearMembershipCache()">
                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Clear Cache', 'folio'); ?>
                    </button>
                    <p class="description"><?php esc_html_e('Clear all cached data used by the membership system', 'folio'); ?></p>
                </td>
            </tr>
        </table>
        
        <div id="folio-action-result"></div>

        <hr>
        <h3><?php esc_html_e('Membership Audit Logs', 'folio'); ?></h3>
        <p><?php esc_html_e('Track who changed membership levels and when.', 'folio'); ?></p>
        <p>
            <button type="button" class="button" onclick="clearMembershipAuditLogs()">
                <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Clear Audit Logs', 'folio'); ?>
            </button>
        </p>
        <?php $this->render_membership_audit_logs(); ?>
        <?php
    }

    /**
     * 获取会员统计信息
     */
    private function get_membership_statistics() {
        global $wpdb;
        
        $stats = array(
            'total_users' => 0,
            'vip_users' => 0,
            'svip_users' => 0,
            'expiring_soon' => 0
        );
        
        // 总用户数
        $stats['total_users'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        
        // VIP用户数
        $stats['vip_users'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'folio_membership_level' AND meta_value = %s",
            'vip'
        ));
        
        // SVIP用户数
        $stats['svip_users'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'folio_membership_level' AND meta_value = %s",
            'svip'
        ));
        
        // 7天内到期的用户
        $expiry_date = date('Y-m-d', strtotime('+7 days'));
        $stats['expiring_soon'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'folio_membership_expiry' 
             AND meta_value <= %s 
             AND meta_value >= %s",
            $expiry_date,
            date('Y-m-d')
        ));
        
        return $stats;
    }

    /**
     * 渲染最近会员列表
     */
    private function render_recent_members() {
        global $wpdb;
        
        $recent_members = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email, 
                    m1.meta_value as membership_level,
                    m2.meta_value as membership_expiry
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} m1 ON u.ID = m1.user_id AND m1.meta_key = 'folio_membership_level'
             LEFT JOIN {$wpdb->usermeta} m2 ON u.ID = m2.user_id AND m2.meta_key = 'folio_membership_expiry'
             WHERE m1.meta_value IN ('vip', 'svip')
             ORDER BY u.user_registered DESC
             LIMIT %d",
            10
        ));
        
        if (empty($recent_members)) {
            echo '<div class="notice notice-info"><p>' . esc_html__('No membership users yet', 'folio') . '</p></div>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__('User', 'folio') . '</th><th>' . esc_html__('Level', 'folio') . '</th><th>' . esc_html__('Expiry Date', 'folio') . '</th><th>' . esc_html__('Status', 'folio') . '</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($recent_members as $member) {
            $level_name = $member->membership_level === 'svip' ? 'SVIP' : 'VIP';
            $level_class = 'membership-badge-' . $member->membership_level;
            
            $status = __('Normal', 'folio');
            $status_class = 'folio-status-active';
            
            if ($member->membership_expiry) {
                $days_left = ceil((strtotime($member->membership_expiry) - time()) / DAY_IN_SECONDS);
                if ($days_left <= 0) {
                    $status = __('Expired', 'folio');
                    $status_class = 'folio-status-expired';
                } elseif ($days_left <= 7) {
                    $status = __('Expiring Soon', 'folio');
                    $status_class = 'folio-status-expiring';
                }
            } else {
                $status = __('Permanent', 'folio');
                $status_class = 'folio-status-permanent';
            }
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($member->display_name) . '</strong><br><small>' . esc_html($member->user_email) . '</small></td>';
            echo '<td><span class="membership-badge ' . esc_attr($level_class) . '">' . esc_html($level_name) . '</span></td>';
            echo '<td>' . ($member->membership_expiry ? esc_html($member->membership_expiry) : esc_html__('Permanent', 'folio')) . '</td>';
            echo '<td><span class="folio-status ' . esc_attr($status_class) . '">' . esc_html($status) . '</span></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }

    /**
     * 渲染会员审计日志。
     */
    private function render_membership_audit_logs() {
        if (!class_exists('folio_Membership_Safe')) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Membership system is not loaded', 'folio') . '</p></div>';
            return;
        }

        $logs = folio_Membership_Safe::get_audit_logs(30);
        if (empty($logs)) {
            echo '<div class="notice notice-info"><p>' . esc_html__('No membership audit logs yet.', 'folio') . '</p></div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Time', 'folio') . '</th>';
        echo '<th>' . esc_html__('User', 'folio') . '</th>';
        echo '<th>' . esc_html__('Change', 'folio') . '</th>';
        echo '<th>' . esc_html__('Operator', 'folio') . '</th>';
        echo '<th>' . esc_html__('Source', 'folio') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($logs as $log) {
            $target_user = get_userdata(absint($log['user_id']));
            $operator_user = !empty($log['operator_id']) ? get_userdata(absint($log['operator_id'])) : false;

            $target_name = $target_user ? $target_user->display_name : sprintf(__('User #%d', 'folio'), absint($log['user_id']));
            $operator_name = $operator_user ? $operator_user->display_name : __('System', 'folio');
            $old_level = !empty($log['old_level']) ? strtoupper(sanitize_text_field($log['old_level'])) : 'FREE';
            $new_level = !empty($log['new_level']) ? strtoupper(sanitize_text_field($log['new_level'])) : 'FREE';
            $source = $this->get_audit_source_label(isset($log['source']) ? $log['source'] : '');

            echo '<tr>';
            echo '<td>' . esc_html(isset($log['time']) ? $log['time'] : '') . '</td>';
            echo '<td>' . esc_html($target_name) . '</td>';
            echo '<td><strong>' . esc_html($old_level . ' -> ' . $new_level) . '</strong></td>';
            echo '<td>' . esc_html($operator_name) . '</td>';
            echo '<td>' . esc_html($source) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * 审计来源标签。
     */
    private function get_audit_source_label($source) {
        $source = sanitize_key($source);
        $map = array(
            'admin_profile_update' => __('Admin Profile Update', 'folio'),
            'api_set_user_level' => __('Programmatic Update', 'folio'),
            'cron_expiry_check' => __('Cron Expiry Check', 'folio'),
            'auto_expiry_check' => __('Auto Expiry Check', 'folio'),
        );

        return isset($map[$source]) ? $map[$source] : __('Unknown', 'folio');
    }

    /**
     * AJAX: 测试会员系统
     */
    public function test_membership_system() {
        check_ajax_referer('folio_membership_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'folio')));
        }
        
        $tests = array();
        
        // 测试1: 检查类是否存在
        $tests[] = class_exists('folio_Membership_Safe') ? __('✅ Membership class loaded correctly', 'folio') : __('❌ Membership class not loaded', 'folio');
        
        // 测试2: 测试函数
        $membership = folio_get_user_membership();
        $tests[] = is_array($membership) ? __('✅ Membership function is working', 'folio') : __('❌ Membership function error', 'folio');
        
        // 测试3: 内存使用
        $memory_mb = memory_get_usage(true) / 1024 / 1024;
        $tests[] = $memory_mb < 200
            ? sprintf(__('✅ Memory usage is normal (%sMB)', 'folio'), round($memory_mb, 2))
            : sprintf(__('⚠️ Memory usage is high (%sMB)', 'folio'), round($memory_mb, 2));
        
        // 测试4: 数据库连接
        global $wpdb;
        $test_query = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users} LIMIT 1");
        $tests[] = $test_query !== null ? __('✅ Database connection is normal', 'folio') : __('❌ Database connection error', 'folio');
        
        $message = implode('<br>', $tests);
        
        wp_send_json_success(array('message' => $message));
    }

    /**
     * AJAX: 检查过期会员
     */
    public function check_expired_members() {
        check_ajax_referer('folio_membership_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'folio')));
        }
        
        try {
            if (class_exists('folio_Membership_Safe')) {
                $instance = folio_Membership_Safe::get_instance();
                $expired_count = $instance->safe_check_expiry();
                
                $message = sprintf(__('Check completed. Processed %d expired members.', 'folio'), $expired_count);
                wp_send_json_success(array('message' => $message));
            } else {
                wp_send_json_error(array('message' => __('Membership system is not loaded', 'folio')));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Check failed: ', 'folio') . $e->getMessage()));
        }
    }

    /**
     * AJAX: 清除会员缓存
     */
    public function clear_membership_cache() {
        check_ajax_referer('folio_membership_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'folio')));
        }
        
        try {
            // 清除WordPress缓存
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            // 清除对象缓存
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('user_meta');
                wp_cache_flush_group('users');
            }
            
            // 清除临时缓存
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_folio_membership_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_folio_membership_%'");
            
            wp_send_json_success(array('message' => __('Cache cleared', 'folio')));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Clear failed: ', 'folio') . $e->getMessage()));
        }
    }

    /**
     * AJAX: 清空会员审计日志
     */
    public function clear_membership_audit_logs() {
        check_ajax_referer('folio_membership_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'folio')));
        }

        if (!class_exists('folio_Membership_Safe')) {
            wp_send_json_error(array('message' => __('Membership system is not loaded', 'folio')));
        }

        $result = folio_Membership_Safe::clear_audit_logs();
        if ($result) {
            wp_send_json_success(array('message' => __('Membership audit logs cleared', 'folio')));
        }

        wp_send_json_error(array('message' => __('Clear failed', 'folio')));
    }
}

// 初始化管理面板
new folio_Membership_Admin();
