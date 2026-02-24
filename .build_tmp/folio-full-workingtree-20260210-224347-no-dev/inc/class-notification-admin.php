<?php
/**
 * 通知系统管理页面
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Notification_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_folio_send_test_notification', array($this, 'send_test_notification'));
        add_action('wp_ajax_folio_send_test_email', array($this, 'send_test_email'));
        add_action('wp_ajax_folio_run_expiry_check', array($this, 'run_expiry_check'));
        add_action('wp_ajax_folio_send_notification', array($this, 'send_notification'));
        add_action('wp_ajax_folio_search_users', array($this, 'search_users'));
        add_action('wp_ajax_folio_admin_delete_notification', array($this, 'admin_delete_notification'));
        add_action('wp_ajax_folio_admin_bulk_delete_notifications', array($this, 'admin_bulk_delete_notifications'));
        add_action('wp_ajax_folio_admin_mark_notification_read', array($this, 'admin_mark_notification_read'));
        add_action('wp_ajax_folio_admin_bulk_mark_read', array($this, 'admin_bulk_mark_read'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'users.php',
            __('Membership Notification Management', 'folio'),
            __('Membership Notifications', 'folio'),
            'manage_options',
            'folio-notifications',
            array($this, 'admin_page')
        );
    }

    /**
     * 注册设置
     */
    public function register_settings() {
        register_setting('folio_notifications', 'folio_notification_settings', array($this, 'sanitize_settings'));
        
        add_settings_section(
            'folio_notification_email',
            __('Email Notification Settings', 'folio'),
            array($this, 'email_section_callback'),
            'folio_notifications'
        );
        
        add_settings_field(
            'email_enabled',
            __('Enable Email Notifications', 'folio'),
            array($this, 'email_enabled_callback'),
            'folio_notifications',
            'folio_notification_email'
        );
        
        add_settings_field(
            'reminder_days',
            __('Reminder Days', 'folio'),
            array($this, 'reminder_days_callback'),
            'folio_notifications',
            'folio_notification_email'
        );
        
        add_settings_field(
            'from_email',
            __('From Email', 'folio'),
            array($this, 'from_email_callback'),
            'folio_notifications',
            'folio_notification_email'
        );
        
        add_settings_field(
            'from_name',
            __('From Name', 'folio'),
            array($this, 'from_name_callback'),
            'folio_notifications',
            'folio_notification_email'
        );
        
        // 添加通知类型邮件开关设置部分
        add_settings_section(
            'folio_notification_types',
            __('Notification Type Email Settings', 'folio'),
            array($this, 'notification_types_section_callback'),
            'folio_notifications'
        );
        
        // 为每种通知类型添加设置字段
        $notification_types = $this->get_notification_types();
        foreach ($notification_types as $type => $label) {
            add_settings_field(
                'email_type_' . $type,
                $label,
                array($this, 'email_type_callback'),
                'folio_notifications',
                'folio_notification_types',
                array('type' => $type, 'label' => $label)
            );
        }
        
        add_settings_section(
            'folio_smtp_settings',
            __('SMTP Mail Server Settings (Optional)', 'folio'),
            array($this, 'smtp_section_callback'),
            'folio_notifications'
        );
        
        add_settings_field(
            'smtp_enabled',
            __('Enable SMTP', 'folio'),
            array($this, 'smtp_enabled_callback'),
            'folio_notifications',
            'folio_smtp_settings'
        );
        
        add_settings_field(
            'smtp_host',
            __('SMTP Server', 'folio'),
            array($this, 'smtp_host_callback'),
            'folio_notifications',
            'folio_smtp_settings'
        );
        
        add_settings_field(
            'smtp_port',
            __('SMTP Port', 'folio'),
            array($this, 'smtp_port_callback'),
            'folio_notifications',
            'folio_smtp_settings'
        );
        
        add_settings_field(
            'smtp_encryption',
            __('Encryption Method', 'folio'),
            array($this, 'smtp_encryption_callback'),
            'folio_notifications',
            'folio_smtp_settings'
        );
        
        add_settings_field(
            'smtp_username',
            __('SMTP Username', 'folio'),
            array($this, 'smtp_username_callback'),
            'folio_notifications',
            'folio_smtp_settings'
        );
        
        add_settings_field(
            'smtp_password',
            __('SMTP Password', 'folio'),
            array($this, 'smtp_password_callback'),
            'folio_notifications',
            'folio_smtp_settings'
        );
    }

    /**
     * 管理页面
     */
    public function admin_page() {
        // 获取当前标签
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'notifications';
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Membership Notification Management', 'folio'); ?></h1>
            
            <?php settings_errors(); ?>
            
            <!-- 标签导航 -->
            <nav class="nav-tab-wrapper">
                <a href="?page=folio-notifications&tab=notifications" class="nav-tab <?php echo $current_tab === 'notifications' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-bell"></span> <?php esc_html_e('On-site Notification Management', 'folio'); ?>
                </a>
                <a href="?page=folio-notifications&tab=email" class="nav-tab <?php echo $current_tab === 'email' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-email"></span> <?php esc_html_e('Email Notification Settings', 'folio'); ?>
                </a>
            </nav>
            
            <div class="folio-notification-content">
                <?php
                switch ($current_tab) {
                    case 'email':
                        $this->render_email_settings_tab();
                        break;
                    case 'notifications':
                    default:
                        $this->render_notifications_tab();
                        break;
                }
                ?>
            </div>
            
            <?php
            // 输出样式和脚本
            $this->output_page_assets();
            ?>
        </div>
        <?php
    }

    /**
     * 渲染站内通知管理标签页
     */
    private function render_notifications_tab() {
        global $wpdb;
        
        // 获取筛选参数
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : '';
        $filter_read = isset($_GET['filter_read']) ? sanitize_text_field($_GET['filter_read']) : '';
        
        // 获取统计数据
        $notification_table = $wpdb->prefix . 'folio_notifications';
        $total_notifications = $wpdb->get_var("SELECT COUNT(*) FROM {$notification_table}");
        $unread_notifications = $wpdb->get_var("SELECT COUNT(*) FROM {$notification_table} WHERE is_read = 0");
        
        // 构建查询条件
        $where = array('1=1');
        $values = array();
        
        if ($search) {
            $where[] = "(n.title LIKE %s OR n.message LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $values = array_merge($values, array($search_like, $search_like, $search_like, $search_like));
        }
        
        if ($filter_type) {
            $where[] = "n.type = %s";
            $values[] = $filter_type;
        }
        
        if ($filter_read !== '') {
            $where[] = "n.is_read = %d";
            $values[] = absint($filter_read);
        }
        
        $where_clause = implode(' AND ', $where);
        
        // 获取总数
        $total_query = "SELECT COUNT(*) FROM {$notification_table} n 
                        LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID 
                        WHERE {$where_clause}";
        if (!empty($values)) {
            $total_query = $wpdb->prepare($total_query, $values);
        }
        $total_count = $wpdb->get_var($total_query);
        $total_pages = ceil($total_count / $per_page);
        
        // 获取通知列表
        $query = "SELECT n.*, u.display_name, u.user_email 
             FROM {$notification_table} n 
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID 
                  WHERE {$where_clause}
             ORDER BY n.created_at DESC 
                  LIMIT %d OFFSET %d";
        
        $query_values = array_merge($values, array($per_page, $offset));
        $recent_notifications = $wpdb->get_results($wpdb->prepare($query, $query_values));
        
        ?>
            
            <!-- 统计卡片 -->
            <div class="folio-stats-cards" style="display: flex; gap: 20px; margin: 20px 0;">
                <div class="folio-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
                    <h3 style="margin: 0 0 10px 0; color: #23282d;"><?php esc_html_e('Total Notifications', 'folio'); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0; color: #0073aa;"><?php echo $total_notifications; ?></p>
                </div>
                <div class="folio-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
                    <h3 style="margin: 0 0 10px 0; color: #23282d;"><?php esc_html_e('Unread Notifications', 'folio'); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0; color: #d63638;"><?php echo $unread_notifications; ?></p>
                </div>
            </div>
            
            <!-- 发送通知 -->
            <div class="folio-send-notification" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
                <h3><?php esc_html_e('Send On-site Notification', 'folio'); ?></h3>
                <form id="send-notification-form" style="margin-top: 15px;">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="notification-recipient-type"><?php esc_html_e('Send To', 'folio'); ?></label></th>
                            <td>
                                <select id="notification-recipient-type" name="recipient_type" style="width: 200px;" onchange="toggleRecipientOptions()">
                                    <option value="single"><?php esc_html_e('Single User', 'folio'); ?></option>
                                    <option value="multiple"><?php esc_html_e('Multiple Users', 'folio'); ?></option>
                                    <option value="all"><?php esc_html_e('All Registered Users', 'folio'); ?></option>
                                    <option value="all_users"><?php esc_html_e('Everyone (including guests)', 'folio'); ?></option>
                                    <option value="role"><?php esc_html_e('By Role', 'folio'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr id="single-user-row" style="display: table-row;">
                            <th scope="row"><label for="notification-user-id"><?php esc_html_e('Select User', 'folio'); ?></label></th>
                            <td>
                                <input type="text" id="notification-user-search" placeholder="<?php esc_attr_e('Search by username or email...', 'folio'); ?>" style="width: 300px;" autocomplete="off">
                                <input type="hidden" id="notification-user-id" name="user_id">
                                <div id="user-search-results" style="position: absolute; background: white; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; display: none; z-index: 1000; margin-top: 5px;"></div>
                                <p class="description"><?php esc_html_e('Search by username or email', 'folio'); ?></p>
                            </td>
                        </tr>
                        <tr id="multiple-users-row" style="display: none;">
                            <th scope="row"><label><?php esc_html_e('Select Users', 'folio'); ?></label></th>
                            <td>
                                <input type="text" id="notification-users-search" placeholder="<?php esc_attr_e('Search by username or email, supports multiple...', 'folio'); ?>" style="width: 100%;" autocomplete="off">
                                <div id="selected-users-list" style="margin-top: 10px;"></div>
                                <p class="description"><?php esc_html_e('Enter usernames or emails, separated by commas', 'folio'); ?></p>
                            </td>
                        </tr>
                        <tr id="role-row" style="display: none;">
                            <th scope="row"><label for="notification-role"><?php esc_html_e('User Role', 'folio'); ?></label></th>
                            <td>
                                <select id="notification-role" name="role" style="width: 200px;">
                                    <option value="subscriber"><?php esc_html_e('Subscriber', 'folio'); ?></option>
                                    <option value="contributor"><?php esc_html_e('Contributor', 'folio'); ?></option>
                                    <option value="author"><?php esc_html_e('Author', 'folio'); ?></option>
                                    <option value="editor"><?php esc_html_e('Editor', 'folio'); ?></option>
                                    <option value="administrator"><?php esc_html_e('Administrator', 'folio'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="notification-type"><?php esc_html_e('Notification Type', 'folio'); ?></label></th>
                            <td>
                                <select id="notification-type" name="type" style="width: 200px;">
                                    <option value="user_register" selected><?php esc_html_e('User Registration', 'folio'); ?></option>
                                    <option value="membership_expiry"><?php esc_html_e('Membership Expiry Reminder', 'folio'); ?></option>
                                    <option value="membership_expired"><?php esc_html_e('Membership Expired', 'folio'); ?></option>
                                    <option value="membership_changed"><?php esc_html_e('Membership Level Changed', 'folio'); ?></option>
                                    <option value="membership_activated"><?php esc_html_e('Membership Activated', 'folio'); ?></option>
                                    <option value="security_alert"><?php esc_html_e('Security Alert', 'folio'); ?></option>
                                    <option value="security_warning"><?php esc_html_e('Security Warning', 'folio'); ?></option>
                                    <option value="test"><?php esc_html_e('Test Notification', 'folio'); ?></option>
                                    <option value="custom"><?php esc_html_e('Custom', 'folio'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="notification-title"><?php esc_html_e('Notification Title', 'folio'); ?> <span style="color: red;">*</span></label></th>
                            <td>
                                <input type="text" id="notification-title" name="title" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="notification-message"><?php esc_html_e('Notification Message', 'folio'); ?> <span style="color: red;">*</span></label></th>
                            <td>
                                <textarea id="notification-message" name="message" rows="5" class="large-text" required></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"></th>
                            <td>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Send Notification', 'folio'); ?></button>
                                <span id="send-notification-result" style="margin-left: 10px;"></span>
                            </td>
                        </tr>
                    </table>
            </form>
            </div>
            
            <!-- 站内通知测试工具 -->
            <div class="folio-test-tools" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
                <h3><?php esc_html_e('On-site Notification Test', 'folio'); ?></h3>
                <p>
                    <button type="button" class="button button-primary" onclick="sendTestNotification()"><?php esc_html_e('Send On-site Test Notification', 'folio'); ?></button>
                </p>
                <p class="description" style="margin-top: 10px;">
                    <strong><?php esc_html_e('Note:', 'folio'); ?></strong><?php esc_html_e(' On-site test notifications are only sent to your on-site notification center and no emails are sent. You can view them in "My Notifications" in User Center.', 'folio'); ?>
                </p>
                <div id="test-result"></div>
            </div>
            
            <!-- 通知管理 -->
            <div class="folio-notification-management" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
                <div class="folio-notification-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;"><?php esc_html_e('Notification Management', 'folio'); ?></h3>
                    <div class="folio-notification-actions">
                        <button type="button" class="button" id="bulk-delete-btn" style="display: none;"><?php esc_html_e('Bulk Delete', 'folio'); ?></button>
                        <button type="button" class="button" id="bulk-mark-read-btn" style="display: none;"><?php esc_html_e('Bulk Mark Read', 'folio'); ?></button>
                    </div>
                </div>
                
                <!-- 搜索和筛选 -->
                <div class="folio-notification-filters" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <form method="get" action="" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <input type="hidden" name="page" value="folio-notifications">
                        <input type="hidden" name="tab" value="notifications">
                        
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search user, title, or message...', 'folio'); ?>" style="flex: 1; min-width: 200px;">
                        
                        <select name="filter_type" style="width: 150px;">
                            <option value=""><?php esc_html_e('All Types', 'folio'); ?></option>
                            <option value="user_register" <?php selected($filter_type, 'user_register'); ?>><?php esc_html_e('User Registration', 'folio'); ?></option>
                            <option value="membership_expiry" <?php selected($filter_type, 'membership_expiry'); ?>><?php esc_html_e('Membership Expiry Reminder', 'folio'); ?></option>
                            <option value="membership_expired" <?php selected($filter_type, 'membership_expired'); ?>><?php esc_html_e('Membership Expired', 'folio'); ?></option>
                            <option value="membership_changed" <?php selected($filter_type, 'membership_changed'); ?>><?php esc_html_e('Membership Level Changed', 'folio'); ?></option>
                            <option value="membership_activated" <?php selected($filter_type, 'membership_activated'); ?>><?php esc_html_e('Membership Activated', 'folio'); ?></option>
                            <option value="security_alert" <?php selected($filter_type, 'security_alert'); ?>><?php esc_html_e('Security Alert', 'folio'); ?></option>
                            <option value="security_warning" <?php selected($filter_type, 'security_warning'); ?>><?php esc_html_e('Security Warning', 'folio'); ?></option>
                            <option value="test" <?php selected($filter_type, 'test'); ?>><?php esc_html_e('Test Notification', 'folio'); ?></option>
                            <option value="custom" <?php selected($filter_type, 'custom'); ?>><?php esc_html_e('Custom', 'folio'); ?></option>
                        </select>
                        
                        <select name="filter_read" style="width: 120px;">
                            <option value=""><?php esc_html_e('All Statuses', 'folio'); ?></option>
                            <option value="0" <?php selected($filter_read, '0'); ?>><?php esc_html_e('Unread', 'folio'); ?></option>
                            <option value="1" <?php selected($filter_read, '1'); ?>><?php esc_html_e('Read', 'folio'); ?></option>
                        </select>
                        
                        <button type="submit" class="button"><?php esc_html_e('Filter', 'folio'); ?></button>
                        <?php if ($search || $filter_type || $filter_read !== ''): ?>
                        <a href="?page=folio-notifications&tab=notifications" class="button"><?php esc_html_e('Clear', 'folio'); ?></a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- 通知列表 -->
                <?php if (empty($recent_notifications)): ?>
                    <p><?php esc_html_e('No notification records yet.', 'folio'); ?></p>
                <?php else: ?>
                    <form id="notifications-form" method="post">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                    <td class="check-column">
                                        <input type="checkbox" id="cb-select-all">
                                    </td>
                                <th><?php esc_html_e('User', 'folio'); ?></th>
                                <th><?php esc_html_e('Type', 'folio'); ?></th>
                                <th><?php esc_html_e('Title', 'folio'); ?></th>
                                <th><?php esc_html_e('Message', 'folio'); ?></th>
                                <th><?php esc_html_e('Status', 'folio'); ?></th>
                                <th><?php esc_html_e('Time', 'folio'); ?></th>
                                <th><?php esc_html_e('Actions', 'folio'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_notifications as $notification): ?>
                                <tr data-id="<?php echo esc_attr($notification->id); ?>" class="<?php echo $notification->is_read ? 'read' : 'unread'; ?>">
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="notification_ids[]" value="<?php echo esc_attr($notification->id); ?>" class="notification-checkbox">
                                    </th>
                                    <td>
                                        <strong><?php echo esc_html($notification->display_name ?: __('Unknown User', 'folio')); ?></strong><br>
                                        <small style="color: #666;"><?php echo esc_html($notification->user_email ?: ''); ?></small>
                                    </td>
                                <td>
                                    <span class="folio-notification-type folio-type-<?php echo esc_attr($notification->type); ?>">
                                        <?php echo esc_html($this->get_type_label($notification->type)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($notification->title); ?></td>
                                    <td><?php echo esc_html(wp_trim_words($notification->message, 15)); ?></td>
                                <td>
                                    <?php if ($notification->is_read): ?>
                                        <span style="color: #46b450;"><?php esc_html_e('Read', 'folio'); ?></span>
                                    <?php else: ?>
                                        <span style="color: #d63638; font-weight: bold;"><?php esc_html_e('Unread', 'folio'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($notification->created_at); ?></td>
                                    <td>
                                        <a href="#" class="delete-notification-link" data-id="<?php echo esc_attr($notification->id); ?>" style="color: #a00;"><?php esc_html_e('Delete', 'folio'); ?></a> |
                                        <?php if (!$notification->is_read): ?>
                                        <a href="#" class="mark-read-link" data-id="<?php echo esc_attr($notification->id); ?>" style="color: #0073aa;"><?php esc_html_e('Mark as Read', 'folio'); ?></a>
                                        <?php else: ?>
                                        <a href="#" class="mark-unread-link" data-id="<?php echo esc_attr($notification->id); ?>" style="color: #666;"><?php esc_html_e('Mark as Unread', 'folio'); ?></a>
                                        <?php endif; ?>
                                    </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </form>
                    
                    <!-- 分页 -->
                    <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom" style="margin-top: 20px;">
                        <div class="tablenav-pages">
                            <?php
                            $base_url = admin_url('admin.php?page=folio-notifications&tab=notifications');
                            if ($search) $base_url .= '&s=' . urlencode($search);
                            if ($filter_type) $base_url .= '&filter_type=' . urlencode($filter_type);
                            if ($filter_read !== '') $base_url .= '&filter_read=' . urlencode($filter_read);
                            
                            echo paginate_links(array(
                                'base' => $base_url . '%_%',
                                'format' => '&paged=%#%',
                                'current' => $paged,
                                'total' => $total_pages,
                                'prev_text' => '&laquo; ' . __('Previous', 'folio'),
                                'next_text' => __('Next', 'folio') . ' &raquo;',
                            ));
                            ?>
                        </div>
                        <div class="tablenav-pages" style="float: right;">
                            <span class="displaying-num"><?php echo esc_html(sprintf(__('Total %d notifications', 'folio'), $total_count)); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php
    }

    /**
     * 渲染邮件通知设置标签页
     */
    private function render_email_settings_tab() {
        ?>
        <div class="folio-email-settings-tab">
            <!-- 邮件设置表单 -->
            <form method="post" action="options.php" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
                <?php
                settings_fields('folio_notifications');
                do_settings_sections('folio_notifications');
                submit_button(__('Save Settings', 'folio'));
                ?>
            </form>
            
            <!-- 邮件测试工具 -->
            <div class="folio-email-test" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
                <h3><?php esc_html_e('Email Test', 'folio'); ?></h3>
                <p>
                    <button type="button" class="button button-primary" onclick="sendTestEmail()"><?php esc_html_e('Send Test Email', 'folio'); ?></button>
                    <button type="button" class="button" onclick="runExpiryCheck()"><?php esc_html_e('Run Expiry Check Manually', 'folio'); ?></button>
                </p>
                <p class="description" style="margin-top: 10px;">
                    <strong><?php esc_html_e('Note:', 'folio'); ?></strong><?php esc_html_e(' Test email will be sent to the currently logged-in administrator email. Expiry check will scan all members and send reminder emails when needed.', 'folio'); ?>
                </p>
                <div id="email-test-result"></div>
        </div>
        
        </div>
        <?php
    }
    
    /**
     * 输出页面样式和脚本（在 admin_page 方法中调用）
     */
    private function output_page_assets() {
        ?>
        <style>
        .folio-notification-type {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .folio-type-membership_expiry {
            background: #fff3cd;
            color: #856404;
        }
        .folio-type-membership_expired {
            background: #f8d7da;
            color: #721c24;
        }
        .folio-type-membership_changed {
            background: #d4edda;
            color: #155724;
        }
        .folio-type-test {
            background: #d1ecf1;
            color: #0c5460;
        }
        .folio-type-membership_activated {
            background: #cff4fc;
            color: #055160;
        }
        .folio-type-user_register {
            background: #e2e3ff;
            color: #3730a3;
        }
        .folio-type-security_alert {
            background: #f8d7da;
            color: #721c24;
        }
        .folio-type-security_warning {
            background: #fff3cd;
            color: #856404;
        }
        </style>
        
        <script>
        // 切换收件人选项显示
        function toggleRecipientOptions() {
            var type = document.getElementById('notification-recipient-type').value;
            document.getElementById('single-user-row').style.display = (type === 'single') ? 'table-row' : 'none';
            document.getElementById('multiple-users-row').style.display = (type === 'multiple') ? 'table-row' : 'none';
            document.getElementById('role-row').style.display = (type === 'role') ? 'table-row' : 'none';
        }
        
        // 通知类型模板
        var notificationTemplates = {
            'user_register': {
                title: '<?php echo esc_js(__('Registration Successful', 'folio')); ?>',
                message: '<?php echo esc_js(__('Welcome aboard! Your account has been created successfully. You can now explore our content.', 'folio')); ?>'
            },
            'membership_expiry': {
                title: '<?php echo esc_js(__('Membership Expiring Soon', 'folio')); ?>',
                message: '<?php echo esc_js(__('Your membership will expire in {days} days. Please renew in time to keep member benefits.', 'folio')); ?>'
            },
            'membership_expired': {
                title: '<?php echo esc_js(__('Membership Expired', 'folio')); ?>',
                message: '<?php echo esc_js(__('Your membership has expired. Please renew to restore member benefits.', 'folio')); ?>'
            },
            'membership_changed': {
                title: '<?php echo esc_js(__('Membership Level Changed', 'folio')); ?>',
                message: '<?php echo esc_js(__('Your membership level has been changed to {level}. Thank you for your support!', 'folio')); ?>'
            },
            'membership_activated': {
                title: '<?php echo esc_js(__('Membership Activated', 'folio')); ?>',
                message: '<?php echo esc_js(__('Congratulations! Your membership is now active. You can enjoy all member-exclusive benefits.', 'folio')); ?>'
            },
            'security_alert': {
                title: '<?php echo esc_js(__('Security Alert', 'folio')); ?>',
                message: '<?php echo esc_js(__('Unusual activity was detected on your account. Please review your security settings.', 'folio')); ?>'
            },
            'security_warning': {
                title: '<?php echo esc_js(__('Security Warning', 'folio')); ?>',
                message: '<?php echo esc_js(__('Your account may be at risk. Please change your password and review account settings immediately.', 'folio')); ?>'
            },
            'test': {
                title: '<?php echo esc_js(__('Test Notification', 'folio')); ?>',
                message: '<?php echo esc_js(__('This is a test notification used to verify that the notification system works correctly.', 'folio')); ?>'
            },
            'custom': {
                title: '',
                message: ''
            }
        };
        
        // 页面加载时初始化显示
        jQuery(document).ready(function($) {
            toggleRecipientOptions();
            
            // 页面加载时自动填充默认通知类型（用户注册）的内容
            var defaultType = $('#notification-type').val();
            var defaultTemplate = notificationTemplates[defaultType];
            if (defaultTemplate && defaultType !== 'custom') {
                $('#notification-title').val(defaultTemplate.title);
                $('#notification-message').val(defaultTemplate.message);
            }
            
            // 通知类型改变时自动填充内容
            $('#notification-type').on('change', function() {
                var type = $(this).val();
                var template = notificationTemplates[type];
                
                if (template && type !== 'custom') {
                    // 每次切换都更新标题和内容（使用模板）
                    $('#notification-title').val(template.title);
                    $('#notification-message').val(template.message);
                    
                    // 如果有选中的用户，更新用户信息
                    setTimeout(function() {
                        updateNotificationWithUserInfo();
                    }, 100);
                } else if (type === 'custom') {
                    // 自定义类型时不清空，让用户自己填写
                }
            });
        });
        
        // 更新通知内容中的用户信息（全局函数）
        window.updateNotificationWithUserInfo = function() {
            var message = $('#notification-message').val();
            if (!message) return;
            
            var recipientType = $('#notification-recipient-type').val();
            var userName = '';
            var userEmail = '';
            
            if (recipientType === 'single') {
                var userSearchVal = $('#notification-user-search').val();
                if (userSearchVal) {
                    // 从搜索框中提取用户名和邮箱（格式：用户名 (邮箱)）
                    var match = userSearchVal.match(/^([^(]+)\s*\(([^)]+)\)/);
                    if (match) {
                        userName = match[1].trim();
                        userEmail = match[2].trim();
                    } else {
                        // 如果没有括号，整个值作为用户名
                        userName = userSearchVal.trim();
                    }
                }
            } else if (recipientType === 'multiple' && typeof selectedUsers !== 'undefined' && selectedUsers.length > 0) {
                // 多个用户时，使用第一个用户的信息
                userName = selectedUsers[0].name;
                userEmail = selectedUsers[0].email;
            }
            
            if (userName) {
                // 替换各种占位符
                message = message.replace(/{username}/g, userName);
                message = message.replace(/{user_name}/g, userName);
                message = message.replace(/{user}/g, userName);
                if (userEmail) {
                    message = message.replace(/{email}/g, userEmail);
                    message = message.replace(/{user_email}/g, userEmail);
                }
                $('#notification-message').val(message);
            }
        };
        
        // 用户搜索
        var userSearchTimeout;
        jQuery(document).ready(function($) {
            // 单个用户搜索
            $('#notification-user-search').on('input', function() {
                clearTimeout(userSearchTimeout);
                var query = $(this).val();
                
                if (query.length < 2) {
                    $('#user-search-results').hide();
                    return;
                }
                
                userSearchTimeout = setTimeout(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'folio_search_users',
                            query: query,
                            nonce: '<?php echo wp_create_nonce('folio_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success && response.data.length > 0) {
                                var html = '';
                                response.data.forEach(function(user) {
                                    html += '<div class="user-search-item" style="padding: 8px; cursor: pointer; border-bottom: 1px solid #eee;" onclick="selectUser(' + user.ID + ', \'' + user.display_name + '\', \'' + user.user_email + '\')">';
                                    html += '<strong>' + user.display_name + '</strong> (' + user.user_email + ')';
                                    html += '</div>';
                                });
                                $('#user-search-results').html(html).show();
                            } else {
                                $('#user-search-results').html('<div style="padding: 8px;"><?php echo esc_js(__('No users found', 'folio')); ?></div>').show();
                            }
                        }
                    });
                }, 300);
            });
            
            // 点击外部关闭搜索结果
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#notification-user-search, #user-search-results').length) {
                    $('#user-search-results').hide();
                }
            });
            
            // 多个用户搜索
            var selectedUsers = [];
            $('#notification-users-search').on('blur', function() {
                var input = $(this).val().trim();
                if (input) {
                    var users = input.split(',').map(function(u) { return u.trim(); });
                    users.forEach(function(userInput) {
                        if (userInput && !selectedUsers.some(function(u) { return u.input === userInput; })) {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'folio_search_users',
                                    query: userInput,
                                    nonce: '<?php echo wp_create_nonce('folio_admin_nonce'); ?>'
                                },
                                success: function(response) {
                                    if (response.success && response.data.length > 0) {
                                        var user = response.data[0];
                                        if (!selectedUsers.some(function(u) { return u.id === user.ID; })) {
                                            selectedUsers.push({
                                                id: user.ID,
                                                name: user.display_name,
                                                email: user.user_email,
                                                input: userInput
                                            });
                                            updateSelectedUsersList();
                                        }
                                    }
                                }
                            });
                        }
                    });
                }
            });
            
            function updateSelectedUsersList() {
                var html = '';
                selectedUsers.forEach(function(user, index) {
                    html += '<span style="display: inline-block; background: #0073aa; color: white; padding: 4px 8px; border-radius: 4px; margin: 4px; font-size: 12px;">';
                    html += user.name + ' (' + user.email + ') ';
                    html += '<span onclick="removeUser(' + index + ')" style="cursor: pointer; margin-left: 5px;">×</span>';
                    html += '</span>';
                });
                $('#selected-users-list').html(html);
                
                // 更新通知内容中的用户信息
                updateNotificationWithUserInfo();
            }
            
            window.removeUser = function(index) {
                selectedUsers.splice(index, 1);
                updateSelectedUsersList();
            };
            
            // 发送通知表单提交
            $('#send-notification-form').on('submit', function(e) {
                e.preventDefault();
                
                var recipientType = $('#notification-recipient-type').val();
                var type = $('#notification-type').val();
                var title = $('#notification-title').val();
                var message = $('#notification-message').val();
                
                if (!title || !message) {
                    alert('<?php echo esc_js(__('Please enter notification title and message', 'folio')); ?>');
                    return;
                }
                
                var data = {
                    action: 'folio_send_notification',
                    recipient_type: recipientType,
                    type: type,
                    title: title,
                    message: message,
                    nonce: '<?php echo wp_create_nonce('folio_admin_nonce'); ?>'
                };
                
                if (recipientType === 'single') {
                    var userId = $('#notification-user-id').val();
                    if (!userId) {
                        alert('<?php echo esc_js(__('Please select a user', 'folio')); ?>');
                        return;
                    }
                    data.user_id = userId;
                } else if (recipientType === 'multiple') {
                    if (selectedUsers.length === 0) {
                        alert('<?php echo esc_js(__('Please select at least one user', 'folio')); ?>');
                        return;
                    }
                    data.user_ids = selectedUsers.map(function(u) { return u.id; });
                } else if (recipientType === 'role') {
                    data.role = $('#notification-role').val();
                } else if (recipientType === 'all_users') {
                    // 所有人（包括未登录用户），不需要额外参数
                }
                
                var resultDiv = $('#send-notification-result');
                resultDiv.html('<span style="color: #0073aa;"><?php echo esc_js(__('Sending...', 'folio')); ?></span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                            $('#send-notification-form')[0].reset();
                            $('#notification-user-id').val('');
                            $('#user-search-results').hide();
                            selectedUsers = [];
                            $('#selected-users-list').html('');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            resultDiv.html('<span style="color: red;">✗ ' + (response.data || '<?php echo esc_js(__('Send failed', 'folio')); ?>') + '</span>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<span style="color: red;">✗ <?php echo esc_js(__('Network error, please try again', 'folio')); ?></span>');
                    }
                });
            });
        });
        
        function selectUser(userId, userName, userEmail) {
            $('#notification-user-id').val(userId);
            $('#notification-user-search').val(userName + ' (' + userEmail + ')');
            $('#user-search-results').hide();
            
            // 更新通知内容中的用户信息
            updateNotificationWithUserInfo();
        }
        
        function sendTestNotification() {
            var result = document.getElementById('test-result');
            result.innerHTML = '<p><?php echo esc_js(__('Sending...', 'folio')); ?></p>';
            
            jQuery.post(ajaxurl, {
                action: 'folio_send_test_notification',
                nonce: '<?php echo wp_create_nonce('folio_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    result.innerHTML = '<p style="color: green;">✅ <?php echo esc_js(__('On-site test notification sent successfully. Please check your on-site notification center.', 'folio')); ?></p>';
                } else {
                    result.innerHTML = '<p style="color: red;">❌ <?php echo esc_js(__('Send failed:', 'folio')); ?> ' + response.data + '</p>';
                }
            });
        }
        
        function sendTestEmail() {
            var result = document.getElementById('email-test-result');
            if (!result) {
                result = document.getElementById('test-result');
            }
            result.innerHTML = '<p><?php echo esc_js(__('Sending...', 'folio')); ?></p>';
            
            jQuery.post(ajaxurl, {
                action: 'folio_send_test_email',
                nonce: '<?php echo wp_create_nonce('folio_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    result.innerHTML = '<p style="color: green;">✅ ' + response.data + '</p>';
                } else {
                    result.innerHTML = '<p style="color: red;">❌ ' + response.data + '</p>';
                }
            });
        }
        
        function runExpiryCheck() {
            var result = document.getElementById('email-test-result') || document.getElementById('test-result');
            if (!result) {
                alert('<?php echo esc_js(__('Cannot find result display area', 'folio')); ?>');
                return;
            }
            
            result.innerHTML = '<p><?php echo esc_js(__('Checking...', 'folio')); ?></p>';
            
            jQuery.post(ajaxurl, {
                action: 'folio_run_expiry_check',
                nonce: '<?php echo wp_create_nonce('folio_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    result.innerHTML = '<p style="color: green;">✅ ' + (response.data || '<?php echo esc_js(__('Expiry check completed!', 'folio')); ?>') + '</p>';
                setTimeout(function() {
                    location.reload();
                }, 2000);
                } else {
                    result.innerHTML = '<p style="color: red;">❌ ' + (response.data || '<?php echo esc_js(__('Expiry check failed', 'folio')); ?>') + '</p>';
        }
            }).fail(function() {
                result.innerHTML = '<p style="color: red;">❌ <?php echo esc_js(__('Request failed, please check your network connection', 'folio')); ?></p>';
            });
        }
        
        // 通知管理功能
        jQuery(document).ready(function($) {
            // 全选/取消全选
            $('#cb-select-all').on('change', function() {
                $('.notification-checkbox').prop('checked', $(this).prop('checked'));
                toggleBulkActions();
            });
            
            // 单个复选框改变时
            $(document).on('change', '.notification-checkbox', function() {
                toggleBulkActions();
            });
            
            // 显示/隐藏批量操作按钮
            function toggleBulkActions() {
                var checkedCount = $('.notification-checkbox:checked').length;
                if (checkedCount > 0) {
                    $('#bulk-delete-btn, #bulk-mark-read-btn').show();
                } else {
                    $('#bulk-delete-btn, #bulk-mark-read-btn').hide();
                }
            }
            
            // 删除单个通知
            $(document).on('click', '.delete-notification-link', function(e) {
                e.preventDefault();
                if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this notification?', 'folio')); ?>')) {
                    return;
                }
                
                var notificationId = $(this).data('id');
                var $row = $(this).closest('tr');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'folio_admin_delete_notification',
                        notification_id: notificationId,
                        nonce: '<?php echo wp_create_nonce('folio_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $row.fadeOut(300, function() {
                                $(this).remove();
                                if ($('tbody tr').length === 0) {
                                    location.reload();
                                }
                            });
                        } else {
                            alert('<?php echo esc_js(__('Delete failed:', 'folio')); ?> ' + (response.data || '<?php echo esc_js(__('Unknown error', 'folio')); ?>'));
                        }
                    }
                });
            });
            
            // 标记已读/未读
            $(document).on('click', '.mark-read-link, .mark-unread-link', function(e) {
                e.preventDefault();
                var notificationId = $(this).data('id');
                var isRead = $(this).hasClass('mark-read-link') ? 1 : 0;
                var $row = $(this).closest('tr');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'folio_admin_mark_notification_read',
                        notification_id: notificationId,
                        is_read: isRead,
                        nonce: '<?php echo wp_create_nonce('folio_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('<?php echo esc_js(__('Operation failed:', 'folio')); ?> ' + (response.data || '<?php echo esc_js(__('Unknown error', 'folio')); ?>'));
                        }
                    }
                });
            });
            
            // 批量删除
            $('#bulk-delete-btn').on('click', function() {
                var checkedIds = [];
                $('.notification-checkbox:checked').each(function() {
                    checkedIds.push($(this).val());
                });
                
                if (checkedIds.length === 0) {
                    alert('<?php echo esc_js(__('Please select notifications to delete', 'folio')); ?>');
                    return;
                }
                
                if (!confirm('<?php echo esc_js(__('Are you sure you want to delete selected notifications?', 'folio')); ?>')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'folio_admin_bulk_delete_notifications',
                        notification_ids: checkedIds,
                        nonce: '<?php echo wp_create_nonce('folio_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('<?php echo esc_js(__('Delete failed:', 'folio')); ?> ' + (response.data || '<?php echo esc_js(__('Unknown error', 'folio')); ?>'));
                        }
                    }
                });
            });
            
            // 批量标记已读
            $('#bulk-mark-read-btn').on('click', function() {
                var checkedIds = [];
                $('.notification-checkbox:checked').each(function() {
                    checkedIds.push($(this).val());
                });
                
                if (checkedIds.length === 0) {
                    alert('<?php echo esc_js(__('Please select notifications to operate on', 'folio')); ?>');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'folio_admin_bulk_mark_read',
                        notification_ids: checkedIds,
                        nonce: '<?php echo wp_create_nonce('folio_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('<?php echo esc_js(__('Operation failed:', 'folio')); ?> ' + (response.data || '<?php echo esc_js(__('Unknown error', 'folio')); ?>'));
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * 邮件设置说明
     */
    public function email_section_callback() {
        echo '<p>' . esc_html__('Configure email notification settings for membership expiry reminders.', 'folio') . '</p>';
    }

    /**
     * 邮件启用设置
     */
    public function email_enabled_callback() {
        $options = get_option('folio_notification_settings', array());
        $enabled = isset($options['email_enabled']) ? $options['email_enabled'] : 1;
        ?>
        <input type="checkbox" name="folio_notification_settings[email_enabled]" value="1" <?php checked($enabled, 1); ?> />
        <label><?php esc_html_e('Enable email notifications (if disabled, only on-site notifications will be sent)', 'folio'); ?></label>
        <?php
    }

    /**
     * 提醒天数设置
     */
    public function reminder_days_callback() {
        $options = get_option('folio_notification_settings', array());
        $days = isset($options['reminder_days']) ? $options['reminder_days'] : '7,3,1,0';
        ?>
        <input type="text" name="folio_notification_settings[reminder_days]" value="<?php echo esc_attr($days); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('Reminder days separated by commas (e.g. 7,3,1,0 means send reminders 7 days, 3 days, 1 day before expiry, and on expiry day)', 'folio'); ?></p>
        <?php
    }

    /**
     * 发件人邮箱设置
     */
    public function from_email_callback() {
        $options = get_option('folio_notification_settings', array());
        $from_email = isset($options['from_email']) ? $options['from_email'] : get_option('admin_email');
        ?>
        <input type="email" name="folio_notification_settings[from_email]" value="<?php echo esc_attr($from_email); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('Sender email address (defaults to site admin email)', 'folio'); ?></p>
        <?php
    }

    /**
     * 发件人名称设置
     */
    public function from_name_callback() {
        $options = get_option('folio_notification_settings', array());
        $from_name = isset($options['from_name']) ? $options['from_name'] : get_bloginfo('name');
        ?>
        <input type="text" name="folio_notification_settings[from_name]" value="<?php echo esc_attr($from_name); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('Sender name (defaults to site name)', 'folio'); ?></p>
        <?php
    }
    
    /**
     * 通知类型邮件设置部分说明
     */
    public function notification_types_section_callback() {
        echo '<p>' . esc_html__('Choose which notification types should send email notifications. Only enabled types will send emails.', 'folio') . '</p>';
    }
    
    /**
     * 获取通知类型列表
     */
    private function get_notification_types() {
        return array(
            'user_register' => __('User Registration Notification', 'folio'),
            'admin_user_register' => __('Admin User Registration Alert', 'folio'),
            'membership_expiry' => __('Membership Expiry Reminder', 'folio'),
            'membership_expired' => __('Membership Expired', 'folio'),
            'membership_changed' => __('Membership Level Changed', 'folio'),
            'membership_activated' => __('Membership Activated', 'folio'),
            'security_alert' => __('Security Alert', 'folio'),
            'security_warning' => __('Security Warning', 'folio')
        );
    }
    
    /**
     * 通知类型邮件开关回调
     */
    public function email_type_callback($args) {
        $type = $args['type'];
        $label = $args['label'];
        $options = get_option('folio_notification_settings', array());
        
        // 默认值：会员相关通知默认开启，其他默认关闭
        $default_enabled = in_array($type, array('membership_expiry', 'membership_expired', 'membership_changed', 'membership_activated'));
        $enabled = isset($options['email_types'][$type]) ? $options['email_types'][$type] : ($default_enabled ? 1 : 0);
        ?>
        <label>
            <input type="checkbox" name="folio_notification_settings[email_types][<?php echo esc_attr($type); ?>]" value="1" <?php checked($enabled, 1); ?> />
            <?php echo esc_html($label); ?>
        </label>
        <?php
    }

    /**
     * SMTP 设置说明
     */
    public function smtp_section_callback() {
        echo '<p>' . esc_html__('If your server cannot send email directly, configure an SMTP server. If not configured, WordPress default mail method will be used.', 'folio') . '</p>';
    }

    /**
     * SMTP 启用设置
     */
    public function smtp_enabled_callback() {
        $options = get_option('folio_notification_settings', array());
        $enabled = isset($options['smtp_enabled']) ? $options['smtp_enabled'] : 0;
        ?>
        <input type="checkbox" name="folio_notification_settings[smtp_enabled]" value="1" <?php checked($enabled, 1); ?> />
        <label><?php esc_html_e('Enable SMTP mail server', 'folio'); ?></label>
        <?php
    }

    /**
     * SMTP 服务器设置
     */
    public function smtp_host_callback() {
        $options = get_option('folio_notification_settings', array());
        $host = isset($options['smtp_host']) ? $options['smtp_host'] : 'smtp.example.com';
        ?>
        <input type="text" name="folio_notification_settings[smtp_host]" value="<?php echo esc_attr($host); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('SMTP server address (e.g. smtp.gmail.com, smtp.qq.com)', 'folio'); ?></p>
        <?php
    }

    /**
     * SMTP 端口设置
     */
    public function smtp_port_callback() {
        $options = get_option('folio_notification_settings', array());
        $port = isset($options['smtp_port']) ? $options['smtp_port'] : '587';
        ?>
        <input type="number" name="folio_notification_settings[smtp_port]" value="<?php echo esc_attr($port); ?>" class="small-text" min="1" max="65535" />
        <p class="description"><?php esc_html_e('SMTP port (common: 25, 465, 587)', 'folio'); ?></p>
        <?php
    }

    /**
     * SMTP 加密方式设置
     */
    public function smtp_encryption_callback() {
        $options = get_option('folio_notification_settings', array());
        $encryption = isset($options['smtp_encryption']) ? $options['smtp_encryption'] : 'tls';
        ?>
        <select name="folio_notification_settings[smtp_encryption]">
            <option value="none" <?php selected($encryption, 'none'); ?>><?php esc_html_e('No Encryption', 'folio'); ?></option>
            <option value="ssl" <?php selected($encryption, 'ssl'); ?>>SSL</option>
            <option value="tls" <?php selected($encryption, 'tls'); ?>>TLS</option>
        </select>
        <p class="description"><?php esc_html_e('Mail encryption method (TLS recommended)', 'folio'); ?></p>
        <?php
    }

    /**
     * SMTP 用户名设置
     */
    public function smtp_username_callback() {
        $options = get_option('folio_notification_settings', array());
        $username = isset($options['smtp_username']) ? $options['smtp_username'] : '';
        ?>
        <input type="text" name="folio_notification_settings[smtp_username]" value="<?php echo esc_attr($username); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('SMTP authentication username (usually your email address)', 'folio'); ?></p>
        <?php
    }

    /**
     * SMTP 密码设置
     */
    public function smtp_password_callback() {
        $options = get_option('folio_notification_settings', array());
        $password = isset($options['smtp_password']) ? $options['smtp_password'] : '';
        ?>
        <input type="password" name="folio_notification_settings[smtp_password]" value="<?php echo esc_attr($password); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('SMTP authentication password (some providers require an app password instead of login password)', 'folio'); ?></p>
        <?php
    }

    /**
     * 发送测试通知
     */
    public function send_test_notification() {
        check_ajax_referer('folio_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $user_id = get_current_user_id();
        
        if (class_exists('folio_Membership_Notifications')) {
            $notifications = folio_Membership_Notifications::get_instance();
            $result = $notifications->add_notification(
                $user_id,
                'test',
                __('Admin Test Notification', 'folio'),
                __('This is a test notification from the admin panel used to verify the notification system. Sent at: ', 'folio') . current_time('Y-m-d H:i:s')
            );
            
            if ($result) {
                wp_send_json_success(__('Test notification sent successfully', 'folio'));
            } else {
                wp_send_json_error(__('Send failed', 'folio'));
            }
        } else {
            wp_send_json_error(__('Notification system is not initialized', 'folio'));
        }
    }

    /**
     * 发送测试邮件
     */
    public function send_test_email() {
        check_ajax_referer('folio_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $settings = get_option('folio_notification_settings', array());
        
        if (empty($settings['email_enabled'])) {
            wp_send_json_error(__('Email notifications are disabled. Please enable them in settings.', 'folio'));
            return;
        }
        
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        
        if (!$user || !$user->user_email) {
            wp_send_json_error(__('Unable to get your email address', 'folio'));
            return;
        }
        
        // 确保 SMTP 配置已加载
        if (class_exists('folio_SMTP_Mailer')) {
            folio_SMTP_Mailer::configure();
        }
        
        // 使用邮件模板
        if (class_exists('folio_Membership_Notifications')) {
            $notifications = folio_Membership_Notifications::get_instance();
            $title = __('Email Notification Test', 'folio');
            $message = __('This is a test email used to verify whether email notifications are working properly.', 'folio') . "\n\n";
            $message .= __('Sent at: ', 'folio') . current_time('Y-m-d H:i:s') . "\n\n";
            $message .= __('If you receive this email, email notifications are working correctly.', 'folio');
            
            $email_message = $notifications->get_email_template($title, $message, 'test', $user);
        } else {
            // 如果没有通知类，使用简单格式
            $email_message = nl2br(esc_html($message));
        }
        
        $subject = '[' . get_bloginfo('name') . '] ' . __('Email Notification Test', 'folio');
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $result = wp_mail($user->user_email, $subject, $email_message, $headers);
        
        if ($result) {
            wp_send_json_success(__('Test email has been sent to: ', 'folio') . $user->user_email);
        } else {
            wp_send_json_error(__('Email sending failed, please check WordPress mail configuration', 'folio'));
        }
    }

    /**
     * 手动运行到期检查
     */
    public function run_expiry_check() {
        check_ajax_referer('folio_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        if (class_exists('folio_Membership_Notifications')) {
            $notifications = folio_Membership_Notifications::get_instance();
            
            // 执行到期检查并获取统计信息
            $stats = $notifications->check_membership_expiry();
            
            // 构建返回消息
            $message = sprintf(
                __('Expiry check completed. Checked %d users, sent %d expiry reminders, and sent %d expired notices.', 'folio'),
                $stats['checked'],
                $stats['reminders_sent'],
                $stats['expired_sent']
            );
            
            // 如果有错误，添加到消息中
            if (!empty($stats['errors'])) {
                $message .= ' ' . __('Errors: ', 'folio') . implode('; ', $stats['errors']);
            }
            
            // 如果检查了用户但没有发送提醒，添加调试信息
            if ($stats['checked'] > 0 && $stats['reminders_sent'] == 0 && $stats['expired_sent'] == 0) {
                $message .= ' ' . __('Hint: users were checked but no reminders were sent. Possible reasons: 1) remaining days do not match configured reminder days; 2) reminder already sent today; 3) user already expired and expired notice already sent.', 'folio');
            }
            
            // 检查邮件设置
            $settings = get_option('folio_notification_settings', array());
            if (empty($settings['email_enabled'])) {
                $message .= ' ' . __('Note: email notifications are disabled; only on-site notifications were sent.', 'folio');
            } elseif (isset($settings['email_types']['membership_expiry']) && empty($settings['email_types']['membership_expiry'])) {
                $message .= ' ' . __('Note: membership expiry reminder emails are disabled; only on-site notifications were sent.', 'folio');
            }
            
            wp_send_json_success($message);
        } else {
            wp_send_json_error(__('Notification system is not initialized', 'folio'));
        }
    }

    /**
     * 清理和验证设置
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // 邮件启用
        $sanitized['email_enabled'] = isset($input['email_enabled']) ? 1 : 0;
        
        // 提醒天数
        if (isset($input['reminder_days'])) {
            $sanitized['reminder_days'] = sanitize_text_field($input['reminder_days']);
        }
        
        // 发件人邮箱
        if (isset($input['from_email'])) {
            $sanitized['from_email'] = sanitize_email($input['from_email']);
        }
        
        // 发件人名称
        if (isset($input['from_name'])) {
            $sanitized['from_name'] = sanitize_text_field($input['from_name']);
        }
        
        // 通知类型邮件开关
        if (isset($input['email_types']) && is_array($input['email_types'])) {
            $sanitized['email_types'] = array();
            $notification_types = $this->get_notification_types();
            foreach ($notification_types as $type => $label) {
                $sanitized['email_types'][$type] = isset($input['email_types'][$type]) ? 1 : 0;
            }
        }
        
        // SMTP 设置
        $sanitized['smtp_enabled'] = isset($input['smtp_enabled']) ? 1 : 0;
        
        if (isset($input['smtp_host'])) {
            $sanitized['smtp_host'] = sanitize_text_field($input['smtp_host']);
        }
        
        if (isset($input['smtp_port'])) {
            $sanitized['smtp_port'] = absint($input['smtp_port']);
        }
        
        if (isset($input['smtp_encryption'])) {
            $allowed = array('none', 'ssl', 'tls');
            $sanitized['smtp_encryption'] = in_array($input['smtp_encryption'], $allowed) 
                ? $input['smtp_encryption'] 
                : 'tls';
        }
        
        if (isset($input['smtp_username'])) {
            $sanitized['smtp_username'] = sanitize_text_field($input['smtp_username']);
        }
        
        // 密码保持原值（如果未修改则不更新）
        if (isset($input['smtp_password']) && !empty($input['smtp_password'])) {
            $sanitized['smtp_password'] = $input['smtp_password']; // 密码不进行 sanitize，保持原样
        } else {
            // 如果密码为空，保留旧值
            $old_options = get_option('folio_notification_settings', array());
            if (isset($old_options['smtp_password'])) {
                $sanitized['smtp_password'] = $old_options['smtp_password'];
            }
        }
        
        return $sanitized;
    }

    /**
     * 搜索用户
     */
    public function search_users() {
        check_ajax_referer('folio_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        
        if (strlen($query) < 2) {
            wp_send_json_success(array());
            return;
        }
        
        $users = get_users(array(
            'search' => '*' . esc_attr($query) . '*',
            'search_columns' => array('user_login', 'user_nicename', 'user_email', 'display_name'),
            'number' => 10
        ));
        
        $results = array();
        foreach ($users as $user) {
            $results[] = array(
                'ID' => $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'user_login' => $user->user_login
            );
        }
        
        wp_send_json_success($results);
    }

    /**
     * 发送通知
     */
    public function send_notification() {
        check_ajax_referer('folio_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $recipient_type = isset($_POST['recipient_type']) ? sanitize_text_field($_POST['recipient_type']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'custom';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';
        
        if (empty($title) || empty($message)) {
            wp_send_json_error(__('Notification title and message cannot be empty', 'folio'));
            return;
        }
        
        if (!class_exists('folio_Membership_Notifications')) {
            wp_send_json_error(__('Notification system is not initialized', 'folio'));
            return;
        }
        
        $notifications = folio_Membership_Notifications::get_instance();
        $user_ids = array();
        $sent_count = 0;
        $include_guests = false; // 初始化变量，标记是否包含未登录用户
        
        // 根据收件人类型获取用户ID列表
        switch ($recipient_type) {
            case 'single':
                $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
                if ($user_id) {
                    $user_ids[] = $user_id;
                }
                break;
                
            case 'multiple':
                $user_ids_input = isset($_POST['user_ids']) ? $_POST['user_ids'] : array();
                if (is_array($user_ids_input)) {
                    $user_ids = array_map('absint', $user_ids_input);
                }
                break;
                
            case 'role':
                $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';
                if ($role) {
                    $users = get_users(array('role' => $role));
                    foreach ($users as $user) {
                        $user_ids[] = $user->ID;
                    }
                }
                break;
                
            case 'all':
                // 所有注册用户
                $users = get_users();
                foreach ($users as $user) {
                    $user_ids[] = $user->ID;
                }
                break;
                
            case 'all_users':
                // 所有人（包括未登录用户）
                // 只创建全局通知（user_id = 0），不创建用户特定通知，避免重复
                $include_guests = true; // 标记需要包含未登录用户
                $user_ids = array(); // 清空用户ID列表，不发送给单个用户
                break;
        }
        
        // 检查：如果没有用户且不包含未登录用户，则报错
        if (empty($user_ids) && !$include_guests) {
            wp_send_json_error(__('No users selected', 'folio'));
            return;
        }
        
        // 发送通知给每个注册用户（不包括"所有人"的情况）
        foreach ($user_ids as $user_id) {
            $result = $notifications->add_notification($user_id, $type, $title, $message);
            if ($result) {
                $sent_count++;
            }
        }
        
        // 如果需要包含未登录用户，创建全局通知
        if ($include_guests) {
            // 使用 user_id = 0 表示全局通知（未登录用户可见）
            // 登录用户也会看到这条全局通知，所以不需要再单独发送给每个用户
            $result = $notifications->add_notification(0, $type, $title, $message);
            if ($result) {
                $sent_count++;
            }
        }
        
        if ($sent_count > 0) {
            $message_text = sprintf(__('Successfully sent notifications to %d users', 'folio'), $sent_count);
            if ($include_guests) {
                $message_text .= __(' (including guests)', 'folio');
            }
            
            wp_send_json_success(array(
                'message' => $message_text,
                'sent_count' => $sent_count,
                'total_users' => count($user_ids),
                'includes_guests' => $include_guests
            ));
        } else {
            wp_send_json_error(__('Send failed', 'folio'));
        }
    }

    /**
     * 管理员删除通知
     */
    public function admin_delete_notification() {
        check_ajax_referer('folio_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $notification_id = isset($_POST['notification_id']) ? absint($_POST['notification_id']) : 0;
        
        if (!$notification_id) {
            wp_send_json_error(__('Invalid notification ID', 'folio'));
        }
        
        if (!class_exists('folio_Membership_Notifications')) {
            wp_send_json_error(__('Notification system is not initialized', 'folio'));
        }
        
        $notifications = folio_Membership_Notifications::get_instance();
        $result = $notifications->delete_notification($notification_id);
        
        if ($result) {
            wp_send_json_success(__('Notification deleted', 'folio'));
        } else {
            wp_send_json_error(__('Delete failed', 'folio'));
        }
    }

    /**
     * 批量删除通知
     */
    public function admin_bulk_delete_notifications() {
        check_ajax_referer('folio_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $notification_ids = isset($_POST['notification_ids']) ? array_map('absint', $_POST['notification_ids']) : array();
        
        if (empty($notification_ids)) {
            wp_send_json_error(__('Please select notifications to delete', 'folio'));
        }
        
        if (!class_exists('folio_Membership_Notifications')) {
            wp_send_json_error(__('Notification system is not initialized', 'folio'));
        }
        
        $notifications = folio_Membership_Notifications::get_instance();
        $deleted_count = 0;
        
        foreach ($notification_ids as $notification_id) {
            if ($notifications->delete_notification($notification_id)) {
                $deleted_count++;
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully deleted %d notifications', 'folio'), $deleted_count),
            'deleted_count' => $deleted_count
        ));
    }

    /**
     * 管理员标记通知为已读/未读
     */
    public function admin_mark_notification_read() {
        check_ajax_referer('folio_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $notification_id = isset($_POST['notification_id']) ? absint($_POST['notification_id']) : 0;
        $is_read = isset($_POST['is_read']) ? absint($_POST['is_read']) : 1;
        
        if (!$notification_id) {
            wp_send_json_error(__('Invalid notification ID', 'folio'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'folio_notifications';
        
        $result = $wpdb->update(
            $table_name,
            array('is_read' => $is_read),
            array('id' => $notification_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => $is_read ? __('Marked as read', 'folio') : __('Marked as unread', 'folio'),
                'is_read' => $is_read
            ));
        } else {
            wp_send_json_error(__('Operation failed', 'folio'));
        }
    }

    /**
     * 批量标记为已读
     */
    public function admin_bulk_mark_read() {
        check_ajax_referer('folio_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $notification_ids = isset($_POST['notification_ids']) ? array_map('absint', $_POST['notification_ids']) : array();
        
        if (empty($notification_ids)) {
            wp_send_json_error(__('Please select notifications to operate on', 'folio'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'folio_notifications';
        $ids_placeholder = implode(',', array_fill(0, count($notification_ids), '%d'));
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} SET is_read = 1 WHERE id IN ({$ids_placeholder})",
            $notification_ids
        ));
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => sprintf(__('Successfully marked %d notifications as read', 'folio'), $result),
                'updated_count' => $result
            ));
        } else {
            wp_send_json_error(__('Operation failed', 'folio'));
        }
    }

    /**
     * 获取通知类型标签
     */
    private function get_type_label($type) {
        $labels = array(
            'membership_expiry' => __('Membership Expiry Reminder', 'folio'),
            'membership_expired' => __('Membership Expired', 'folio'),
            'membership_changed' => __('Membership Level Changed', 'folio'),
            'membership_activated' => __('Membership Activated', 'folio'),
            'user_register' => __('User Registration', 'folio'),
            'security_alert' => __('Security Alert', 'folio'),
            'security_warning' => __('Security Warning', 'folio'),
            'test' => __('Test Notification', 'folio')
        );
        
        return isset($labels[$type]) ? $labels[$type] : $type;
    }
}

// 初始化管理页面
if (is_admin()) {
    new folio_Notification_Admin();
}
