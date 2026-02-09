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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'themes.php',
            '会员系统管理',
            '会员系统',
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
                'vip' => isset($input['membership_prices']['vip']) ? sanitize_text_field($input['membership_prices']['vip']) : '¥68/月',
                'svip' => isset($input['membership_prices']['svip']) ? sanitize_text_field($input['membership_prices']['svip']) : '¥128/月'
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
                <?php esc_html_e('会员系统管理', 'folio'); ?>
            </h1>
            
            <?php settings_errors(); ?>
            
            <!-- WordPress标准标签导航 -->
            <nav class="nav-tab-wrapper">
                <a href="?page=folio-membership&tab=overview" class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e('系统概览', 'folio'); ?>
                </a>
                <a href="?page=folio-membership&tab=members" class="nav-tab <?php echo $active_tab === 'members' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('会员管理', 'folio'); ?>
                </a>
                <a href="?page=folio-membership&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('系统设置', 'folio'); ?>
                </a>
                <a href="?page=folio-membership&tab=tools" class="nav-tab <?php echo $active_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('系统工具', 'folio'); ?>
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
            result.innerHTML = '<div class="notice notice-info"><p>🧪 正在测试会员系统...</p></div>';
            
            jQuery.post(ajaxurl, {
                action: 'folio_test_membership',
                nonce: '<?php echo wp_create_nonce('folio_membership_admin'); ?>'
            }, function(response) {
                if (response.success) {
                    result.innerHTML = '<div class="notice notice-success is-dismissible"><p>✅ 测试成功：<br>' + response.data.message + '</p></div>';
                } else {
                    result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ 测试失败：' + response.data.message + '</p></div>';
                }
            }).fail(function(xhr) {
                result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ 请求失败：' + xhr.status + '</p></div>';
            });
        }
        
        function checkExpiredMembers() {
            const result = document.getElementById('folio-action-result');
            result.innerHTML = '<div class="notice notice-info"><p>⏰ 正在检查过期会员...</p></div>';
            
            jQuery.post(ajaxurl, {
                action: 'folio_check_expired_members',
                nonce: '<?php echo wp_create_nonce('folio_membership_admin'); ?>'
            }, function(response) {
                if (response.success) {
                    result.innerHTML = '<div class="notice notice-success is-dismissible"><p>✅ 检查完成：' + response.data.message + '</p></div>';
                } else {
                    result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ 检查失败：' + response.data.message + '</p></div>';
                }
            }).fail(function(xhr) {
                result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ 请求失败：' + xhr.status + '</p></div>';
            });
        }
        
        function clearMembershipCache() {
            const result = document.getElementById('folio-action-result');
            result.innerHTML = '<div class="notice notice-info"><p>🗑️ 正在清除缓存...</p></div>';
            
            jQuery.post(ajaxurl, {
                action: 'folio_clear_membership_cache',
                nonce: '<?php echo wp_create_nonce('folio_membership_admin'); ?>'
            }, function(response) {
                if (response.success) {
                    result.innerHTML = '<div class="notice notice-success is-dismissible"><p>✅ 缓存清除成功</p></div>';
                } else {
                    result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ 缓存清除失败：' + response.data.message + '</p></div>';
                }
            }).fail(function(xhr) {
                result.innerHTML = '<div class="notice notice-error is-dismissible"><p>❌ 请求失败：' + xhr.status + '</p></div>';
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
                <div class="folio-stat-label">总用户数</div>
            </div>
            <div class="folio-stat-card folio-stat-vip">
                <div class="folio-stat-number"><?php echo esc_html($stats['vip_users']); ?></div>
                <div class="folio-stat-label">VIP用户</div>
            </div>
            <div class="folio-stat-card folio-stat-svip">
                <div class="folio-stat-number"><?php echo esc_html($stats['svip_users']); ?></div>
                <div class="folio-stat-label">SVIP用户</div>
            </div>
            <div class="folio-stat-card folio-stat-expiring">
                <div class="folio-stat-number"><?php echo esc_html($stats['expiring_soon']); ?></div>
                <div class="folio-stat-label">7天内到期</div>
            </div>
        </div>

        <div class="folio-memory-status">
            <h3><span class="dashicons dashicons-performance"></span> 系统状态</h3>
            <?php
            $memory_usage = memory_get_usage(true) / 1024 / 1024;
            $memory_peak = memory_get_peak_usage(true) / 1024 / 1024;
            $memory_limit = ini_get('memory_limit');
            
            $status_class = $memory_usage > 200 ? 'folio-memory-danger' : ($memory_usage > 100 ? 'folio-memory-warning' : 'folio-memory-safe');
            ?>
            <p>
                <strong>当前内存使用:</strong> <?php echo number_format($memory_usage, 2); ?> MB<br>
                <strong>峰值内存使用:</strong> <?php echo number_format($memory_peak, 2); ?> MB<br>
                <strong>PHP内存限制:</strong> <?php echo esc_html($memory_limit); ?>
            </p>
            <div class="folio-memory-indicator <?php echo esc_attr($status_class); ?>">
                <?php
                if ($status_class === 'folio-memory-safe') {
                    echo '✅ 内存使用正常';
                } elseif ($status_class === 'folio-memory-warning') {
                    echo '⚠️ 内存使用较高';
                } else {
                    echo '❌ 内存使用过高';
                }
                ?>
            </div>
        </div>

        <h3><span class="dashicons dashicons-admin-users"></span> 最近会员活动</h3>
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
                return '<span class="sorting-indicator" aria-label="排序"></span>';
            }
            return $order === 'asc' 
                ? '<span class="sorting-indicator asc" aria-label="升序"></span>'
                : '<span class="sorting-indicator desc" aria-label="降序"></span>';
        };
        ?>
        <h3>会员管理</h3>
        <p>在这里可以查看所有会员用户的具体信息。</p>
        
        <!-- 筛选器 -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <label for="filter-level" class="screen-reader-text">按会员等级筛选</label>
                <select name="filter_level" id="filter-level" onchange="location.href='<?php echo esc_url($base_url); ?>&filter_level='+this.value">
                    <option value="all_members" <?php selected($filter_level, 'all_members'); ?>>所有会员</option>
                    <option value="svip" <?php selected($filter_level, 'svip'); ?>>SVIP会员</option>
                    <option value="vip" <?php selected($filter_level, 'vip'); ?>>VIP会员</option>
                </select>
            </div>
            <div class="alignright">
                <span class="displaying-num"><?php echo count($members); ?> 个会员</span>
            </div>
        </div>
        
        <?php if (empty($members)) : ?>
        <div class="notice notice-info">
                <p>目前没有符合条件的会员用户。</p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-id sortable <?php echo $orderby === 'id' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('id')); ?>">
                                <span>用户ID</span>
                                <?php echo $get_sort_icon('id'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-username column-primary sortable <?php echo $orderby === 'username' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('username')); ?>">
                                <span>用户名</span>
                                <?php echo $get_sort_icon('username'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-display-name sortable <?php echo $orderby === 'display_name' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('display_name')); ?>">
                                <span>显示名称</span>
                                <?php echo $get_sort_icon('display_name'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-email sortable <?php echo $orderby === 'email' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('email')); ?>">
                                <span>邮箱</span>
                                <?php echo $get_sort_icon('email'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-level sortable <?php echo $orderby === 'level' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('level')); ?>">
                                <span>会员等级</span>
                                <?php echo $get_sort_icon('level'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-expiry sortable <?php echo $orderby === 'expiry' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('expiry')); ?>">
                                <span>到期时间</span>
                                <?php echo $get_sort_icon('expiry'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-status sortable <?php echo $orderby === 'status' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('status')); ?>">
                                <span>状态</span>
                                <?php echo $get_sort_icon('status'); ?>
                            </a>
                        </th>
                        <th scope="col" class="manage-column column-registered sortable <?php echo $orderby === 'registered' ? ($order === 'asc' ? 'asc' : 'desc') : ''; ?>">
                            <a href="<?php echo esc_url($get_sort_url('registered')); ?>">
                                <span>注册时间</span>
                                <?php echo $get_sort_icon('registered'); ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php foreach ($members as $member) : ?>
                        <tr>
                            <td class="id column-id" data-colname="用户ID">
                                <?php echo esc_html($member['user_id']); ?>
                            </td>
                            <td class="username column-username column-primary" data-colname="用户名">
                                <strong><?php echo esc_html($member['username']); ?></strong>
                            </td>
                            <td class="display-name column-display-name" data-colname="显示名称">
                                <?php echo esc_html($member['display_name']); ?>
                            </td>
                            <td class="email column-email" data-colname="邮箱">
                                <?php echo esc_html($member['email']); ?>
                            </td>
                            <td class="level column-level" data-colname="会员等级">
                                <?php 
                                $level_names = array(
                                    'free' => '普通用户',
                                    'vip' => 'VIP会员',
                                    'svip' => 'SVIP会员'
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
                            <td class="expiry column-expiry" data-colname="到期时间">
                                <?php 
                                if ($member['is_permanent']) {
                                    echo '<span style="color: #10b981; font-weight: 600;">永久会员</span>';
                                } elseif (!empty($member['expiry_display'])) {
                                    echo esc_html($member['expiry_display']);
                                    if ($member['days_left'] !== null) {
                                        $days_color = $member['days_left'] <= 7 ? '#ef4444' : ($member['days_left'] <= 30 ? '#f59e0b' : '#6b7280');
                                        echo '<br><small style="color: ' . esc_attr($days_color) . ';">剩余 ' . esc_html($member['days_left']) . ' 天</small>';
                                    }
                                } else {
                                    echo '<span style="color: #9ca3af;">—</span>';
                                }
                                ?>
                            </td>
                            <td class="status column-status" data-colname="状态">
                                <?php
                                if ($member['level'] === 'free') {
                                    echo '<span style="color: #6b7280;">普通用户</span>';
                                } elseif ($member['is_expired']) {
                                    echo '<span style="color: #ef4444;">已过期</span>';
                                } elseif ($member['is_permanent']) {
                                    echo '<span style="color: #10b981;">有效</span>';
                                } else {
                                    echo '<span style="color: #10b981;">有效</span>';
                                }
                                ?>
                            </td>
                            <td class="registered column-registered" data-colname="注册时间">
                                <?php echo esc_html($member['registered']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="notice notice-info" style="margin-top: 20px;">
            <p><strong>提示：</strong>您可以在用户编辑页面设置每个用户的会员等级和到期时间。</p>
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
            $status_value = '有效';
            if ($membership['level'] === 'free') {
                $status_value = '普通用户';
            } elseif ($is_expired) {
                $status_value = '已过期';
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
                    $status_order = array('有效' => 3, '普通用户' => 2, '已过期' => 1);
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
            <h3>系统设置</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">内存保护</th>
                    <td>
                        <label>
                            <input type="checkbox" name="folio_membership_options[memory_protection]" value="1" 
                                   <?php checked(isset($options['memory_protection']) ? $options['memory_protection'] : 1); ?>>
                            启用内存保护（推荐）
                        </label>
                        <p class="description">当内存使用过高时自动禁用部分功能</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">批处理大小</th>
                    <td>
                        <input type="number" name="folio_membership_options[batch_size]" 
                               value="<?php echo esc_attr(isset($options['batch_size']) ? $options['batch_size'] : 50); ?>" 
                               min="10" max="200" class="small-text">
                        <p class="description">处理用户数据时的批次大小</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">缓存时间</th>
                    <td>
                        <input type="number" name="folio_membership_options[cache_time]" 
                               value="<?php echo esc_attr(isset($options['cache_time']) ? $options['cache_time'] : 300); ?>" 
                               min="60" max="3600" class="small-text"> 秒
                        <p class="description">用户会员信息缓存时间</p>
                    </td>
                </tr>
            </table>
            
            <h3 style="margin-top: 30px;">会员权益对比设置</h3>
            <p class="description">配置会员升级页面显示的权益对比数据</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">会员价格</th>
                    <td>
                        <div style="display: flex; gap: 20px; align-items: center;">
                            <div>
                                <label for="vip_price">VIP价格：</label>
                                <input type="text" id="vip_price" name="folio_membership_options[membership_prices][vip]" 
                                       value="<?php echo esc_attr(isset($options['membership_prices']['vip']) ? $options['membership_prices']['vip'] : '¥68/月'); ?>" 
                                       class="regular-text" placeholder="¥68/月">
                            </div>
                            <div>
                                <label for="svip_price">SVIP价格：</label>
                                <input type="text" id="svip_price" name="folio_membership_options[membership_prices][svip]" 
                                       value="<?php echo esc_attr(isset($options['membership_prices']['svip']) ? $options['membership_prices']['svip'] : '¥128/月'); ?>" 
                                       class="regular-text" placeholder="¥128/月">
                            </div>
                        </div>
                        <p class="description">显示在升级页面的会员价格</p>
                    </td>
                </tr>
            </table>
            
            <h3 style="margin-top: 30px;">支付设置</h3>
            <p class="description">配置会员支付相关的二维码和说明</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">支付二维码</th>
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
                                <?php esc_html_e('选择图片', 'folio'); ?>
                            </button>
                            <?php if (!empty($options['payment_qr_code'])) : ?>
                            <button type="button" 
                                    id="remove_payment_qr_code" 
                                    class="button"
                                    style="color: #dc3232;">
                                <?php esc_html_e('移除', 'folio'); ?>
                            </button>
                            <?php endif; ?>
                        </div>
                        <p class="description">支付二维码图片URL（支持微信、支付宝等），可直接上传图片或输入图片URL</p>
                        <div id="payment_qr_code_preview" style="margin-top: 10px;">
                            <?php if (!empty($options['payment_qr_code'])) : ?>
                            <img src="<?php echo esc_url($options['payment_qr_code']); ?>" 
                                 alt="支付二维码" 
                                 style="max-width: 200px; border: 1px solid #ddd; padding: 5px; background: #fff; display: block;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">支付说明</th>
                    <td>
                        <?php 
                        $default_instructions = '支付步骤：' . "\n" . 
                            '1. 选择您要购买的会员类型（VIP或SVIP）' . "\n" . 
                            '2. 点击"升级VIP"或"升级SVIP"按钮' . "\n" . 
                            '3. 使用微信或支付宝扫描上方二维码完成支付' . "\n" . 
                            '4. 支付完成后，请截图保存支付凭证' . "\n" . 
                            '5. 联系客服或发送支付凭证，我们将在24小时内为您开通会员' . "\n\n" . 
                            '注意事项：' . "\n" . 
                            '• 请确保支付金额与所选会员类型一致' . "\n" . 
                            '• 支付完成后请保留支付凭证，以便核对' . "\n" . 
                            '• 如有疑问，请联系客服';
                        $current_instructions = isset($options['payment_instructions']) && !empty($options['payment_instructions']) 
                            ? $options['payment_instructions'] 
                            : $default_instructions;
                        ?>
                        <textarea name="folio_membership_options[payment_instructions]" 
                                  rows="12" 
                                  class="large-text"><?php echo esc_textarea($current_instructions); ?></textarea>
                        <p class="description">
                            支付步骤和注意事项说明（支持HTML，留空则显示默认说明）<br>
                            <strong>默认说明：</strong>如果未设置自定义说明，将显示默认的支付步骤和注意事项
                        </p>
                        <?php if (empty($options['payment_instructions'])) : ?>
                        <div style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1; font-size: 13px;">
                            <strong>当前使用默认说明：</strong>
                            <div style="margin-top: 5px; white-space: pre-wrap; color: #50575e;"><?php echo esc_html($default_instructions); ?></div>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">联系方式</th>
                    <td>
                        <input type="text" name="folio_membership_options[payment_contact]" 
                               value="<?php echo esc_attr(isset($options['payment_contact']) ? $options['payment_contact'] : ''); ?>" 
                               class="regular-text" placeholder="例如：客服QQ：123456789 或 微信：example">
                        <p class="description">支付后联系客服的联系方式</p>
                    </td>
                </tr>
            </table>
            
            <!-- VIP权益对比设置 -->
            <h4 style="margin-top: 30px;">VIP权益对比</h4>
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
                           placeholder="权益名称" style="width: 100%;" required>
                    <input type="text" name="folio_membership_options[benefits_comparison][vip][<?php echo $index; ?>][normal]" 
                           value="<?php echo esc_attr($benefit['normal']); ?>" 
                           placeholder="普通用户（如：× 或 有广告）" style="width: 100%;">
                    <input type="text" name="folio_membership_options[benefits_comparison][vip][<?php echo $index; ?>][vip]" 
                           value="<?php echo esc_attr($benefit['vip']); ?>" 
                           placeholder="VIP用户（如：✓ 或 具体权益）" style="width: 100%;">
                    <button type="button" class="button button-small" onclick="removeBenefitRow(this)" style="color: #dc3232; white-space: nowrap;">删除</button>
                </div>
                <?php endforeach; ?>
                <button type="button" class="button" onclick="addVipBenefit()" style="margin-top: 10px;">+ 添加权益</button>
            </div>
            
            <!-- SVIP权益对比设置 -->
            <h4 style="margin-top: 30px;">SVIP权益对比</h4>
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
                           placeholder="权益名称" style="width: 100%;" required>
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][<?php echo $index; ?>][normal]" 
                           value="<?php echo esc_attr($benefit['normal']); ?>" 
                           placeholder="普通用户" style="width: 100%;">
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][<?php echo $index; ?>][vip]" 
                           value="<?php echo esc_attr(isset($benefit['vip']) ? $benefit['vip'] : ''); ?>" 
                           placeholder="VIP用户" style="width: 100%;">
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][<?php echo $index; ?>][svip]" 
                           value="<?php echo esc_attr(isset($benefit['svip']) ? $benefit['svip'] : ''); ?>" 
                           placeholder="SVIP用户（如：✓）" style="width: 100%;">
                    <button type="button" class="button button-small" onclick="removeBenefitRow(this)" style="color: #dc3232; white-space: nowrap;">删除</button>
                </div>
                <?php endforeach; ?>
                <button type="button" class="button" onclick="addSvipBenefit()" style="margin-top: 10px;">+ 添加权益</button>
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
                           placeholder="权益名称" style="width: 100%;" required>
                    <input type="text" name="folio_membership_options[benefits_comparison][vip][${vipIndex}][normal]" 
                           placeholder="普通用户（如：× 或 有广告）" style="width: 100%;">
                    <input type="text" name="folio_membership_options[benefits_comparison][vip][${vipIndex}][vip]" 
                           placeholder="VIP用户（如：✓）" style="width: 100%;">
                    <button type="button" class="button button-small" onclick="removeBenefitRow(this)" style="color: #dc3232; white-space: nowrap;">删除</button>
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
                           placeholder="权益名称" style="width: 100%;" required>
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][${svipIndex}][normal]" 
                           placeholder="普通用户" style="width: 100%;">
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][${svipIndex}][vip]" 
                           placeholder="VIP用户" style="width: 100%;">
                    <input type="text" name="folio_membership_options[benefits_comparison][svip][${svipIndex}][svip]" 
                           placeholder="SVIP用户（如：✓）" style="width: 100%;">
                    <button type="button" class="button button-small" onclick="removeBenefitRow(this)" style="color: #dc3232; white-space: nowrap;">删除</button>
                `;
                container.insertBefore(row, container.lastElementChild);
                svipIndex++;
            }
            
            function removeBenefitRow(button) {
                if (confirm('确定要删除这个权益项吗？')) {
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
                        title: '选择支付二维码图片',
                        button: {
                            text: '使用此图片'
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
                        var previewHtml = '<img src="' + attachment.url + '" alt="支付二维码" style="max-width: 200px; border: 1px solid #ddd; padding: 5px; background: #fff; display: block;">';
                        $('#payment_qr_code_preview').html(previewHtml);
                        
                        // 显示移除按钮（如果还没有）
                        if ($('#remove_payment_qr_code').length === 0) {
                            $('#upload_payment_qr_code').after('<button type="button" id="remove_payment_qr_code" class="button" style="color: #dc3232;">移除</button>');
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
            array('name' => '查看VIP专属内容', 'normal' => '×', 'vip' => '✓'),
            array('name' => '无广告浏览体验', 'normal' => '有广告', 'vip' => '✓'),
            array('name' => '优先客服支持', 'normal' => '普通排队', 'vip' => '✓'),
            array('name' => '专属会员标识', 'normal' => '×', 'vip' => '✓'),
            array('name' => '高清图片下载', 'normal' => '限制下载', 'vip' => '✓'),
            array('name' => '文章收藏功能', 'normal' => '×', 'vip' => '✓'),
            array('name' => '评论优先显示', 'normal' => '普通排序', 'vip' => '✓'),
            array('name' => '专属内容推送', 'normal' => '×', 'vip' => '✓'),
            array('name' => '会员专属活动', 'normal' => '×', 'vip' => '✓')
        );
    }
    
    /**
     * 获取默认SVIP权益数据
     */
    private static function get_default_svip_benefits() {
        return array(
            array('name' => '查看所有专属内容', 'normal' => '×', 'vip' => '部分内容', 'svip' => '✓'),
            array('name' => '无广告浏览体验', 'normal' => '有广告', 'vip' => '✓', 'svip' => '✓'),
            array('name' => '24小时专属客服', 'normal' => '工作时间', 'vip' => '优先支持', 'svip' => '✓'),
            array('name' => '专属SVIP标识', 'normal' => '×', 'vip' => 'VIP标识', 'svip' => '✓'),
            array('name' => '独家高清资源', 'normal' => '×', 'vip' => '标准资源', 'svip' => '✓'),
            array('name' => '提前体验新功能', 'normal' => '×', 'vip' => '×', 'svip' => '✓'),
            array('name' => '无限下载权限', 'normal' => '限制下载', 'vip' => '有限下载', 'svip' => '✓'),
            array('name' => '专属内容定制', 'normal' => '×', 'vip' => '×', 'svip' => '✓'),
            array('name' => 'SVIP专属活动', 'normal' => '×', 'vip' => '部分活动', 'svip' => '✓')
        );
    }

    /**
     * 渲染工具标签
     */
    private function render_tools_tab() {
        ?>
        <h3>系统工具</h3>
        <p>使用以下工具来测试和维护会员系统。</p>
        
        <table class="form-table">
            <tr>
                <th scope="row">系统测试</th>
                <td>
                    <button type="button" class="button button-primary" onclick="testMembershipSystem()">
                        <span class="dashicons dashicons-admin-tools"></span> 测试会员系统
                    </button>
                    <p class="description">测试会员系统的各项功能是否正常</p>
                </td>
            </tr>
            <tr>
                <th scope="row">过期检查</th>
                <td>
                    <button type="button" class="button" onclick="checkExpiredMembers()">
                        <span class="dashicons dashicons-clock"></span> 检查过期会员
                    </button>
                    <p class="description">手动检查并处理过期的会员账户</p>
                </td>
            </tr>
            <tr>
                <th scope="row">缓存管理</th>
                <td>
                    <button type="button" class="button button-secondary" onclick="clearMembershipCache()">
                        <span class="dashicons dashicons-trash"></span> 清除缓存
                    </button>
                    <p class="description">清除会员系统的所有缓存数据</p>
                </td>
            </tr>
        </table>
        
        <div id="folio-action-result"></div>
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
            echo '<div class="notice notice-info"><p>暂无会员用户</p></div>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>用户</th><th>等级</th><th>到期时间</th><th>状态</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($recent_members as $member) {
            $level_name = $member->membership_level === 'svip' ? 'SVIP' : 'VIP';
            $level_class = 'membership-badge-' . $member->membership_level;
            
            $status = '正常';
            $status_class = 'folio-status-active';
            
            if ($member->membership_expiry) {
                $days_left = ceil((strtotime($member->membership_expiry) - time()) / DAY_IN_SECONDS);
                if ($days_left <= 0) {
                    $status = '已过期';
                    $status_class = 'folio-status-expired';
                } elseif ($days_left <= 7) {
                    $status = '即将到期';
                    $status_class = 'folio-status-expiring';
                }
            } else {
                $status = '永久';
                $status_class = 'folio-status-permanent';
            }
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($member->display_name) . '</strong><br><small>' . esc_html($member->user_email) . '</small></td>';
            echo '<td><span class="membership-badge ' . esc_attr($level_class) . '">' . esc_html($level_name) . '</span></td>';
            echo '<td>' . ($member->membership_expiry ? esc_html($member->membership_expiry) : '永久') . '</td>';
            echo '<td><span class="folio-status ' . esc_attr($status_class) . '">' . esc_html($status) . '</span></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }

    /**
     * AJAX: 测试会员系统
     */
    public function test_membership_system() {
        check_ajax_referer('folio_membership_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '权限不足'));
        }
        
        $tests = array();
        
        // 测试1: 检查类是否存在
        $tests[] = class_exists('folio_Membership_Safe') ? '✅ 会员类加载正常' : '❌ 会员类未加载';
        
        // 测试2: 测试函数
        $membership = folio_get_user_membership();
        $tests[] = is_array($membership) ? '✅ 会员函数正常' : '❌ 会员函数异常';
        
        // 测试3: 内存使用
        $memory_mb = memory_get_usage(true) / 1024 / 1024;
        $tests[] = $memory_mb < 200 ? '✅ 内存使用正常 (' . round($memory_mb, 2) . 'MB)' : '⚠️ 内存使用较高 (' . round($memory_mb, 2) . 'MB)';
        
        // 测试4: 数据库连接
        global $wpdb;
        $test_query = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users} LIMIT 1");
        $tests[] = $test_query !== null ? '✅ 数据库连接正常' : '❌ 数据库连接异常';
        
        $message = implode('<br>', $tests);
        
        wp_send_json_success(array('message' => $message));
    }

    /**
     * AJAX: 检查过期会员
     */
    public function check_expired_members() {
        check_ajax_referer('folio_membership_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '权限不足'));
        }
        
        try {
            if (class_exists('folio_Membership_Safe')) {
                $instance = folio_Membership_Safe::get_instance();
                $expired_count = $instance->safe_check_expiry();
                
                $message = "检查完成。处理了 {$expired_count} 个过期会员。";
                wp_send_json_success(array('message' => $message));
            } else {
                wp_send_json_error(array('message' => '会员系统未加载'));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => '检查失败: ' . $e->getMessage()));
        }
    }

    /**
     * AJAX: 清除会员缓存
     */
    public function clear_membership_cache() {
        check_ajax_referer('folio_membership_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '权限不足'));
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
            
            wp_send_json_success(array('message' => '缓存已清除'));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => '清除失败: ' . $e->getMessage()));
        }
    }
}

// 初始化管理面板
new folio_Membership_Admin();