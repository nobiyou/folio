<?php
/**
 * User Center System
 * 
 * 完整的用户中心系统，支持自定义 URL 路由
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_User_Center {
    
    private static $instance = null;
    private $base_slug = 'user-center';
    private $current_action = '';

    private function __construct() {
        // 添加重写规则
        add_action('init', array($this, 'add_rewrite_rules'));
        
        // 处理查询变量
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // 模板重定向
        add_action('template_redirect', array($this, 'template_redirect'));
        
        // 处理表单提交
        add_action('init', array($this, 'handle_form_submissions'));
        
        // 注册 AJAX 处理
        add_action('wp_ajax_folio_user_login', array($this, 'ajax_user_login'));
        add_action('wp_ajax_nopriv_folio_user_login', array($this, 'ajax_user_login'));
        add_action('wp_ajax_folio_user_register', array($this, 'ajax_user_register'));
        add_action('wp_ajax_nopriv_folio_user_register', array($this, 'ajax_user_register'));
        add_action('wp_ajax_folio_send_register_code', array($this, 'ajax_send_register_code'));
        add_action('wp_ajax_nopriv_folio_send_register_code', array($this, 'ajax_send_register_code'));
        
        // 封禁用户：登录时拦截
        add_filter('authenticate', array($this, 'check_banned_user'), 99, 3);
        
        // 用户列表：批量封禁 / 行内封禁 + 注册与登录信息列
        if (is_admin()) {
            add_filter('bulk_actions-users', array($this, 'add_user_bulk_actions'));
            add_filter('handle_bulk_actions-users', array($this, 'handle_user_bulk_actions'), 10, 3);
            add_filter('user_row_actions', array($this, 'add_user_row_actions'), 10, 2);
            add_action('admin_init', array(__CLASS__, 'maybe_handle_ban_row_action'));
            add_action('admin_init', array(__CLASS__, 'maybe_handle_unban_row_action'));
            add_action('admin_notices', array($this, 'user_list_ban_notice'));
            add_action('admin_notices', array($this, 'user_list_unban_notice'));
            add_filter('manage_users_columns', array($this, 'add_user_list_columns'));
            add_filter('manage_users_custom_column', array($this, 'render_user_list_column'), 10, 3);
            add_filter('manage_users_sortable_columns', array($this, 'sortable_user_list_columns'));
        }
        
        // 添加导航菜单项（已禁用，用户信息显示在右上角）
        // add_filter('wp_nav_menu_items', array($this, 'add_user_menu_item'), 10, 2);
        
        // 重写规则刷新由 functions.php 中 folio_flush_rewrite_rules() 统一处理
    }

    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 添加重写规则
     */
    public function add_rewrite_rules() {
        // 用户中心首页
        add_rewrite_rule(
            '^' . $this->base_slug . '/?$',
            'index.php?folio_user_center=dashboard',
            'top'
        );
        
        // 用户中心子页面
        add_rewrite_rule(
            '^' . $this->base_slug . '/([^/]+)/?$',
            'index.php?folio_user_center=$matches[1]',
            'top'
        );
    }

    /**
     * 添加查询变量
     */
    public function add_query_vars($vars) {
        $vars[] = 'folio_user_center';
        return $vars;
    }

    /**
     * 检查是否是用户中心页面
     */
    public function is_user_center_page() {
        $action = get_query_var('folio_user_center');
        if ($action) {
            return true;
        }
        global $wp;
        if (isset($wp) && isset($wp->request) && is_string($wp->request) && strpos($wp->request, $this->base_slug) === 0) {
            return true;
        }
        return false;
    }

    /**
     * 模板重定向
     */
    public function template_redirect() {
        $action = get_query_var('folio_user_center');
        
        // 如果没有action，检查是否在用户中心页面
        if (!$action) {
            // 检查是否是用户中心页面
            global $wp;
            $request = (isset($wp) && isset($wp->request) && is_string($wp->request)) ? $wp->request : '';
            if ($request !== '' && strpos($request, $this->base_slug) === 0) {
                $action = 'dashboard'; // 默认显示仪表盘
            } else {
                return;
            }
        }

        $this->current_action = $action;

        // 检查是否需要登录
        $public_actions = array('login', 'register', 'forgot-password');
        
        if (!is_user_logged_in() && !in_array($action, $public_actions)) {
            wp_redirect(home_url($this->base_slug . '/login'));
            exit;
        }

        // 已登录用户访问登录页面，重定向到仪表盘
        if (is_user_logged_in() && in_array($action, array('login', 'register'))) {
            wp_redirect(home_url($this->base_slug));
            exit;
        }

        // 加载用户中心模板
        $this->load_user_center_template();
    }

    /**
     * 加载用户中心模板
     */
    private function load_user_center_template() {
        // 设置页面标题
        add_filter('wp_title', array($this, 'set_page_title'), 10, 2);
        add_filter('document_title_parts', array($this, 'set_document_title'));
        
        // 加载模板
        include get_template_directory() . '/user-center.php';
        exit;
    }

    /**
     * 设置页面标题
     */
    public function set_page_title($title, $sep) {
        $titles = array(
            'dashboard' => __('User Center', 'folio'),
            'favorites' => __('My Favorites', 'folio'),
            'profile' => __('Profile', 'folio'),
            'membership' => __('Membership Center', 'folio'),
            'notifications' => __('My Notifications', 'folio'),
            'login' => __('Sign In', 'folio'),
            'register' => __('Sign Up', 'folio'),
            'forgot-password' => __('Forgot Password', 'folio'),
        );

        $page_title = isset($titles[$this->current_action]) ? $titles[$this->current_action] : __('User Center', 'folio');
        
        return $page_title . ' ' . $sep . ' ' . get_bloginfo('name');
    }

    /**
     * 设置文档标题
     */
    public function set_document_title($title_parts) {
        $titles = array(
            'dashboard' => __('User Center', 'folio'),
            'favorites' => __('My Favorites', 'folio'),
            'profile' => __('Profile', 'folio'),
            'membership' => __('Membership Center', 'folio'),
            'notifications' => __('My Notifications', 'folio'),
            'login' => __('Sign In', 'folio'),
            'register' => __('Sign Up', 'folio'),
            'forgot-password' => __('Forgot Password', 'folio'),
        );

        $page_title = isset($titles[$this->current_action]) ? $titles[$this->current_action] : __('User Center', 'folio');
        $title_parts['title'] = $page_title;
        
        return $title_parts;
    }

    /**
     * 处理表单提交
     */
    public function handle_form_submissions() {
        if (!isset($_POST['folio_action'])) {
            return;
        }

        switch ($_POST['folio_action']) {
            case 'login':
                $this->handle_login();
                break;
            case 'register':
                $this->handle_register();
                break;
            case 'update_profile':
                $this->handle_update_profile();
                break;
            case 'logout':
                $this->handle_logout();
                break;
        }
    }

    /**
     * 处理登录
     */
    private function handle_login() {
        if (!wp_verify_nonce($_POST['folio_login_nonce'], 'folio_login')) {
            wp_die(__('Security verification failed', 'folio'));
        }

        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

        $user = wp_signon(array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember,
        ));

        if (is_wp_error($user)) {
            // 转换错误消息为友好的中文提示
            $error_message = $this->translate_login_error($user, $username);
            
            // 使用transient存储错误信息，避免URL参数暴露
            $random_key = wp_generate_password(12, false);
            $transient_key = 'folio_login_error_' . $random_key;
            $transient_set = set_transient($transient_key, $error_message, 60); // 60秒过期
            
            // 重定向时传递随机key而不是transient完整名称
            $redirect_url = add_query_arg('login_error_key', $random_key, home_url($this->base_slug . '/login'));
            wp_redirect($redirect_url);
        } else {
            wp_redirect(home_url($this->base_slug));
        }
        exit;
    }
    
    /**
     * 转换登录错误消息为友好的中文提示
     */
    private function translate_login_error($error, $username = '') {
        $error_code = $error->get_error_code();
        $error_message = $error->get_error_message();
        
        // 获取忘记密码页面URL
        $forgot_password_url = self::get_url('forgot-password');
        
        // 根据错误代码返回友好的中文消息
        switch ($error_code) {
            case 'incorrect_password':
                return sprintf(__('Incorrect password. Please try again. If you forgot it, <a href="%s">reset here</a>.', 'folio'), 
                    esc_url($forgot_password_url));
            
            case 'invalid_username':
            case 'invalid_email':
                return __('Username or email does not exist. Please check and try again.', 'folio');
            
            case 'empty_username':
                return __('Please enter username or email.', 'folio');
            
            case 'empty_password':
                return __('Please enter password.', 'folio');
            
            default:
                // 尝试从错误消息中提取关键信息
                if (strpos($error_message, 'password') !== false && strpos($error_message, 'incorrect') !== false) {
                    return sprintf(__('Incorrect password. Please try again. If you forgot it, <a href="%s">reset here</a>.', 'folio'), 
                        esc_url($forgot_password_url));
                }
                if (strpos($error_message, 'username') !== false || strpos($error_message, 'email') !== false) {
                    return __('Username or email does not exist. Please check and try again.', 'folio');
                }
                // 如果无法识别，返回通用错误消息
                return __('Login failed. Please check your username and password.', 'folio');
        }
    }

    /**
     * 登录时检查是否为封禁用户
     */
    public function check_banned_user($user, $username, $password) {
        if (!$user || !($user instanceof WP_User)) {
            return $user;
        }
        if ($this->is_user_banned($user->user_login, $user->ID)) {
            return new WP_Error('user_banned', __('This account has been banned and cannot log in. Please contact the administrator if you have questions.', 'folio'));
        }
        return $user;
    }

    /**
     * 判断用户是否在封禁名单
     */
    public static function is_user_banned($login, $user_id = 0) {
        $list = get_option('folio_banned_users', '');
        if (empty($list)) {
            return false;
        }
        $lines = array_filter(array_map('trim', preg_split('/[\r\n]+/', $list)));
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) {
                continue;
            }
            if (is_numeric($line) && (int) $line === (int) $user_id) {
                return true;
            }
            if (strtolower($line) === strtolower($login)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断用户名/邮箱是否在注册黑名单
     * 每行可为：用户名、完整邮箱、或邮箱域名（如 @tempmail.com）
     */
    public static function is_register_blocked($username, $email) {
        $list = get_option('folio_register_blocklist', '');
        if (empty($list)) {
            return false;
        }
        $lines = array_filter(array_map('trim', preg_split('/[\r\n]+/', $list)));
        $email_lower = strtolower($email);
        $username_lower = strtolower($username);
        foreach ($lines as $line) {
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $line_lower = strtolower($line);
            if (strpos($line_lower, '@') !== false) {
                if ($line_lower === $email_lower) {
                    return true;
                }
                if (strpos($line_lower, '@') === 0 && substr($email_lower, strrpos($email_lower, '@')) === $line_lower) {
                    return true;
                }
            } else {
                if ($line_lower === $username_lower) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 用户列表批量操作：添加「封禁」选项
     */
    public function add_user_bulk_actions($actions) {
        if (!current_user_can('list_users')) {
            return $actions;
        }
        $actions['folio_ban_users'] = __('Ban', 'folio');
        return $actions;
    }

    /**
     * 用户列表批量操作：处理封禁
     */
    public function handle_user_bulk_actions($redirect_to, $doaction, $user_ids) {
        if ($doaction !== 'folio_ban_users' || empty($user_ids) || !current_user_can('list_users')) {
            return $redirect_to;
        }
        $added = self::add_users_to_banned_list($user_ids);
        $redirect_to = add_query_arg('folio_banned', $added, remove_query_arg('folio_banned', $redirect_to));
        return $redirect_to;
    }

    /**
     * 将一批用户 ID 加入封禁名单（去重、不重复添加）
     */
    public static function add_users_to_banned_list($user_ids) {
        $current = get_option('folio_banned_users', '');
        $lines = array_filter(array_map('trim', preg_split('/[\r\n]+/', $current)));
        $existing_ids = array();
        foreach ($lines as $line) {
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (is_numeric($line)) {
                $existing_ids[(int) $line] = true;
            }
        }
        $added = 0;
        foreach ((array) $user_ids as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($existing_ids[$id])) {
                continue;
            }
            $lines[] = $id;
            $existing_ids[$id] = true;
            $added++;
        }
        if ($added > 0) {
            update_option('folio_banned_users', implode("\n", $lines));
        }
        return $added;
    }

    /**
     * 用户列表行操作：已封禁显示「已封禁」，未封禁显示「封禁」链接，已封禁可「解除封禁」
     */
    public function add_user_row_actions($actions, $user) {
        if (!current_user_can('list_users') || !$user instanceof WP_User) {
            return $actions;
        }
        $is_banned = self::is_user_banned($user->user_login, $user->ID);
        if ($is_banned) {
            $actions['folio_banned'] = '<span style="color:#b32d2e;">' . __('Banned', 'folio') . '</span>';
            $url = wp_nonce_url(
                add_query_arg(array('action' => 'folio_unban_user', 'user_id' => $user->ID), admin_url('users.php')),
                'folio_unban_user_' . $user->ID
            );
            $actions['folio_unban'] = '<a href="' . esc_url($url) . '">' . __('Unban', 'folio') . '</a>';
        } else {
            $url = wp_nonce_url(
                add_query_arg(array('action' => 'folio_ban_user', 'user_id' => $user->ID), admin_url('users.php')),
                'folio_ban_user_' . $user->ID
            );
            $actions['folio_ban'] = '<a href="' . esc_url($url) . '">' . __('Ban', 'folio') . '</a>';
        }
        return $actions;
    }

    /**
     * 从封禁名单中移除用户
     */
    public static function remove_user_from_banned_list($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }
        $user = get_userdata($user_id);
        $current = get_option('folio_banned_users', '');
        $lines = array_filter(array_map('trim', preg_split('/[\r\n]+/', $current)));
        $new_lines = array();
        $removed = false;
        foreach ($lines as $line) {
            if ($line === '' || strpos($line, '#') === 0) {
                $new_lines[] = $line;
                continue;
            }
            if (is_numeric($line)) {
                if ((int) $line === $user_id) {
                    $removed = true;
                    continue;
                }
            } elseif ($user && strtolower($line) === strtolower($user->user_login)) {
                $removed = true;
                continue;
            }
            $new_lines[] = $line;
        }
        if ($removed) {
            update_option('folio_banned_users', implode("\n", $new_lines));
        }
        return $removed;
    }

    /**
     * 处理用户列表中的单用户封禁链接（admin_init）
     */
    public static function maybe_handle_ban_row_action() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'folio_ban_user' || !isset($_GET['user_id']) || !current_user_can('list_users')) {
            return;
        }
        $user_id = (int) $_GET['user_id'];
        if ($user_id <= 0) {
            return;
        }
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'folio_ban_user_' . $user_id)) {
            wp_die(__('Security check failed', 'folio'));
        }
        $added = self::add_users_to_banned_list(array($user_id));
        $redirect = add_query_arg('folio_banned', $added, remove_query_arg(array('action', 'user_id', '_wpnonce', 'folio_banned')));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * 处理用户列表中的单用户解除封禁链接（admin_init）
     */
    public static function maybe_handle_unban_row_action() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'folio_unban_user' || !isset($_GET['user_id']) || !current_user_can('list_users')) {
            return;
        }
        $user_id = (int) $_GET['user_id'];
        if ($user_id <= 0) {
            return;
        }
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'folio_unban_user_' . $user_id)) {
            wp_die(__('Security check failed', 'folio'));
        }
        $removed = self::remove_user_from_banned_list($user_id);
        $redirect = add_query_arg('folio_unbanned', $removed ? '1' : '0', remove_query_arg(array('action', 'user_id', '_wpnonce', 'folio_unbanned')));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * 用户列表封禁成功后的提示
     */
    public function user_list_ban_notice() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'users') {
            return;
        }
        if (!isset($_GET['folio_banned']) || $_GET['folio_banned'] === '') {
            return;
        }
        $num = (int) $_GET['folio_banned'];
        if ($num <= 0) {
            return;
        }
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('Banned %d user(s).', 'folio'), $num)) . '</p></div>';
    }

    /**
     * 用户列表解除封禁成功后的提示
     */
    public function user_list_unban_notice() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'users') {
            return;
        }
        if (!isset($_GET['folio_unbanned']) || $_GET['folio_unbanned'] !== '1') {
            return;
        }
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Unbanned.', 'folio') . '</p></div>';
    }

    /**
     * 用户列表增加列：注册时间、注册IP、最后登录时间、最后登录IP
     */
    public function add_user_list_columns($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'email') {
                $new['folio_registered']   = __('Registration Time', 'folio');
                $new['folio_register_ip']  = __('Registration IP', 'folio');
                $new['folio_last_login']   = __('Last Login Time', 'folio');
                $new['folio_last_login_ip'] = __('Last Login IP', 'folio');
            }
        }
        return $new;
    }

    /**
     * 输出用户列表自定义列内容
     */
    public function render_user_list_column($value, $column_name, $user_id) {
        $empty = esc_html__('—', 'folio');
        switch ($column_name) {
            case 'folio_registered':
                $user = get_userdata($user_id);
                return $user && $user->user_registered
                    ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($user->user_registered))
                    : $empty;
            case 'folio_register_ip':
                $ip = get_user_meta($user_id, 'folio_register_ip', true);
                return $ip ? esc_html($ip) : $empty;
            case 'folio_last_login':
                $t = get_user_meta($user_id, 'folio_last_login_time', true);
                return $t ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $t) : $empty;
            case 'folio_last_login_ip':
                $ip = get_user_meta($user_id, 'folio_last_login_ip', true);
                return $ip ? esc_html($ip) : $empty;
        }
        return $value;
    }

    /**
     * 用户列表可排序列（注册时间映射到 user_registered）
     */
    public function sortable_user_list_columns($columns) {
        $columns['folio_registered'] = 'user_registered';
        return $columns;
    }

    /**
     * 校验注册邮箱验证码
     *
     * @param string $email 邮箱
     * @param string $code  用户输入的验证码
     * @return true|WP_Error
     */
    private function verify_registration_code($email, $code) {
        $email = strtolower(trim($email));
        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', __('Invalid email address.', 'folio'));
        }
        $code = preg_replace('/\s+/', '', $code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return new WP_Error('invalid_code', __('Please enter the 6-digit verification code from your email.', 'folio'));
        }
        $key = 'folio_reg_vcode_' . md5($email);
        $stored = get_transient($key);
        if ($stored === false) {
            return new WP_Error('code_expired', __('Verification code expired. Please request a new one.', 'folio'));
        }
        if (!hash_equals((string) $stored, $code)) {
            return new WP_Error('code_mismatch', __('Incorrect verification code.', 'folio'));
        }
        return true;
    }

    /**
     * 处理注册
     */
    private function handle_register() {
        if (!wp_verify_nonce($_POST['folio_register_nonce'], 'folio_register')) {
            wp_die(__('Security verification failed', 'folio'));
        }

        $username = sanitize_text_field($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $verification_code = isset($_POST['verification_code']) ? preg_replace('/\s+/', '', sanitize_text_field($_POST['verification_code'])) : '';

        if (self::is_register_blocked($username, $email)) {
            wp_redirect(add_query_arg('register_error', urlencode(__('该用户名或邮箱不允许注册。', 'folio')), home_url($this->base_slug . '/register')));
            exit;
        }

        $verify = $this->verify_registration_code($email, $verification_code);
        if (is_wp_error($verify)) {
            wp_redirect(add_query_arg('register_error', urlencode($verify->get_error_message()), home_url($this->base_slug . '/register')));
            exit;
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_redirect(add_query_arg('register_error', urlencode($user_id->get_error_message()), home_url($this->base_slug . '/register')));
        } else {
            delete_transient('folio_reg_vcode_' . md5(strtolower($email)));
            // 发送注册邮件
            $this->send_registration_emails($user_id, $username, $email);
            
            // 添加站内通知
            if (class_exists('folio_Membership_Notifications')) {
                $notifications = folio_Membership_Notifications::get_instance();
                $notifications->add_notification(
                    $user_id,
                    'user_register',
                    __('Registration Successful', 'folio'),
                    __('Welcome aboard! Your account has been created successfully. You can now start exploring our content.', 'folio')
                );
            }
            
            // 自动登录
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            wp_redirect(home_url($this->base_slug));
        }
        exit;
    }

    /**
     * 处理个人资料更新
     */
    private function handle_update_profile() {
        if (!is_user_logged_in()) {
            wp_die(__('Please log in first', 'folio'));
        }

        if (!wp_verify_nonce($_POST['folio_profile_nonce'], 'folio_update_profile')) {
            wp_die(__('Security verification failed', 'folio'));
        }

        $user_id = get_current_user_id();
        $display_name = sanitize_text_field($_POST['display_name']);
        $description = sanitize_textarea_field($_POST['description']);

        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name,
            'description' => $description,
        ));

        wp_redirect(add_query_arg('updated', '1', home_url($this->base_slug . '/profile')));
        exit;
    }

    /**
     * 处理退出登录
     */
    private function handle_logout() {
        if (!wp_verify_nonce($_POST['folio_logout_nonce'], 'folio_logout')) {
            wp_die(__('Security verification failed', 'folio'));
        }

        wp_logout();
        wp_redirect(home_url());
        exit;
    }

    /**
     * AJAX 登录
     */
    public function ajax_user_login() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed', 'folio')));
        }

        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

        $user = wp_signon(array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember,
        ));

        if (is_wp_error($user)) {
            // 使用友好的中文错误消息
            $error_message = $this->translate_login_error($user, $username);
            // 移除HTML标签，因为AJAX响应通常是纯文本
            $error_message_text = wp_strip_all_tags($error_message);
            wp_send_json_error(array('message' => $error_message_text));
        } else {
            wp_send_json_success(array(
                'message' => __('Login successful!', 'folio'),
                'redirect' => home_url($this->base_slug)
            ));
        }
    }

    /**
     * AJAX 注册
     */
    public function ajax_user_register() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed', 'folio')));
        }

        $username = sanitize_text_field($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $verification_code = isset($_POST['verification_code']) ? preg_replace('/\s+/', '', sanitize_text_field($_POST['verification_code'])) : '';

        if (self::is_register_blocked($username, $email)) {
            wp_send_json_error(array('message' => __('该用户名或邮箱不允许注册。', 'folio')));
        }

        $verify = $this->verify_registration_code($email, $verification_code);
        if (is_wp_error($verify)) {
            wp_send_json_error(array('message' => $verify->get_error_message()));
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        } else {
            delete_transient('folio_reg_vcode_' . md5(strtolower($email)));
            // 发送注册邮件
            $this->send_registration_emails($user_id, $username, $email);
            
            // 添加站内通知
            if (class_exists('folio_Membership_Notifications')) {
                $notifications = folio_Membership_Notifications::get_instance();
                $notifications->add_notification(
                    $user_id,
                    'user_register',
                    __('Registration Successful', 'folio'),
                    __('Welcome aboard! Your account has been created successfully. You can now start exploring our content.', 'folio')
                );
            }
            
            // 自动登录
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            wp_send_json_success(array(
                'message' => __('Registration successful!', 'folio'),
                'redirect' => home_url($this->base_slug)
            ));
        }
    }

    /**
     * AJAX 发送注册验证码到邮箱
     */
    public function ajax_send_register_code() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed', 'folio')));
        }

        $email = sanitize_email($_POST['email'] ?? '');
        $email = strtolower(trim($email));
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'folio')));
        }

        if (self::is_register_blocked('', $email)) {
            wp_send_json_error(array('message' => __('该用户名或邮箱不允许注册。', 'folio')));
        }

        if (email_exists($email)) {
            wp_send_json_error(array('message' => __('This email is already registered.', 'folio')));
        }

        $ip = $this->get_client_ip_for_rate_limit();
        $email_key = 'folio_reg_vcode_' . md5($email);
        $rate_email = 'folio_reg_code_sent_' . md5($email);
        $rate_ip_key = 'folio_reg_code_ip_' . md5($ip);

        if (get_transient($rate_email) !== false) {
            wp_send_json_error(array('message' => __('Please wait one minute before requesting another code.', 'folio')));
        }

        $ip_count = (int) get_transient($rate_ip_key);
        if ($ip_count >= 5) {
            wp_send_json_error(array('message' => __('Too many requests. Please try again later.', 'folio')));
        }

        $code = (string) wp_rand(100000, 999999);
        set_transient($email_key, $code, 600);
        set_transient($rate_email, '1', 60);
        set_transient($rate_ip_key, (string) ($ip_count + 1), 900);

        $sent = $this->send_verification_code_email($email, $code);
        if (!$sent) {
            wp_send_json_error(array('message' => __('Failed to send verification email. Please try again later.', 'folio')));
        }

        wp_send_json_success(array('message' => __('Verification code sent. Please check your email.', 'folio')));
    }

    /**
     * 发送注册验证码邮件（版式与主题欢迎邮件模板一致）
     *
     * @param string $email 邮箱
     * @param string $code  6位验证码
     * @return bool 是否发送成功
     */
    private function send_verification_code_email($email, $code) {
        if (class_exists('folio_SMTP_Mailer')) {
            folio_SMTP_Mailer::configure();
        }
        $site_name = get_bloginfo('name');
        $subject = '[' . $site_name . '] ' . __('Email verification code for registration', 'folio');

        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
        $message .= '<h2 style="color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">' . esc_html__('Email verification code', 'folio') . '</h2>';
        $message .= '<p>' . esc_html__('You requested a verification code for registration. Use the code below on the sign-up page:', 'folio') . '</p>';
        $message .= '<div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 24px 0; text-align: center;">';
        $message .= '<p style="margin: 0 0 8px 0; font-size: 14px; color: #666;">' . esc_html__('Your verification code:', 'folio') . '</p>';
        $message .= '<p style="margin: 0; font-size: 28px; font-weight: bold; letter-spacing: 8px; font-family: \'Courier New\', monospace; color: #0073aa;">' . esc_html($code) . '</p>';
        $message .= '</div>';
        $message .= '<p>' . esc_html__('Valid for 10 minutes. Do not share this code with anyone.', 'folio') . '</p>';
        $message .= '<p style="color: #666; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">';
        $message .= esc_html__('If you did not request this code, please ignore this email.', 'folio') . '<br>';
        $message .= esc_html__('This email was sent automatically by the system. Please do not reply.', 'folio');
        $message .= '</p>';
        $message .= '</div></body></html>';

        $headers = array('Content-Type: text/html; charset=UTF-8');
        return wp_mail($email, $subject, $message, $headers) !== false;
    }

    /**
     * 获取客户端 IP（用于验证码频率限制）
     */
    private function get_client_ip_for_rate_limit() {
        $keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = is_string($_SERVER[$key]) ? trim($_SERVER[$key]) : '';
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * 发送注册邮件
     *
     * @param int $user_id 用户ID
     * @param string $username 用户名
     * @param string $email 用户邮箱
     */
    private function send_registration_emails($user_id, $username, $email) {
        // 标记已通过 User Center 发送，避免 WordPress 钩子重复发送
        set_transient('folio_registration_email_sent_' . $user_id, true, 300);
        
        // 确保 SMTP 配置已加载
        if (class_exists('folio_SMTP_Mailer')) {
            folio_SMTP_Mailer::configure();
        }
        
        // 发送给用户的注册成功邮件
        $this->send_user_welcome_email($user_id, $username, $email);
        
        // 发送给管理员的注册提醒邮件
        $this->send_admin_registration_notice($user_id, $username, $email);
    }

    /**
     * 发送用户欢迎邮件
     */
    private function send_user_welcome_email($user_id, $username, $email) {
        $settings = get_option('folio_notification_settings', array());
        
        // 检查是否启用邮件通知
        if (empty($settings['email_enabled'])) {
            return;
        }
        
        // 检查用户注册通知是否启用了邮件通知
        if (isset($settings['email_types']['user_register']) && empty($settings['email_types']['user_register'])) {
            return;
        }
        
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $login_url = home_url('/user-center/login');
        $user_center_url = home_url('/user-center');
        
        $subject = '[' . $site_name . '] ' . __('Welcome Registration!', 'folio');
        
        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
        $message .= '<h2 style="color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">' . esc_html(sprintf(__('Welcome to %s!', 'folio'), $site_name)) . '</h2>';
        $message .= '<p>' . wp_kses_post(sprintf(__('Dear <strong>%s</strong>,', 'folio'), esc_html($username))) . '</p>';
        $message .= '<p>' . esc_html__('Thank you for registering on our site. Your account has been created successfully.', 'folio') . '</p>';
        $message .= '<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        $message .= '<p style="margin: 0;"><strong>' . esc_html__('Your Account Information:', 'folio') . '</strong></p>';
        $message .= '<p style="margin: 5px 0;">' . esc_html__('Username:', 'folio') . ' ' . esc_html($username) . '</p>';
        $message .= '<p style="margin: 5px 0;">' . esc_html__('Email:', 'folio') . ' ' . esc_html($email) . '</p>';
        $message .= '</div>';
        $message .= '<p>' . esc_html__('Now you can:', 'folio') . '</p>';
        $message .= '<ul style="padding-left: 20px;">';
        $message .= '<li>' . esc_html__('Browse our featured content', 'folio') . '</li>';
        $message .= '<li>' . esc_html__('Bookmark your favorite works', 'folio') . '</li>';
        $message .= '<li>' . esc_html__('Upgrade membership for more benefits', 'folio') . '</li>';
        $message .= '</ul>';
        $message .= '<div style="text-align: center; margin: 30px 0;">';
        $message .= '<a href="' . esc_url($user_center_url) . '" style="display: inline-block; background: #0073aa; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">' . esc_html__('Go to User Center', 'folio') . '</a>';
        $message .= '</div>';
        $message .= '<p style="color: #666; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">';
        $message .= esc_html__('If you have any questions, feel free to contact us.', 'folio') . '<br>';
        $message .= esc_html__('This email was sent automatically by the system. Please do not reply.', 'folio');
        $message .= '</p>';
        $message .= '</div>';
        $message .= '</body></html>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($email, $subject, $message, $headers);
    }

    /**
     * 发送管理员注册提醒邮件
     */
    private function send_admin_registration_notice($user_id, $username, $email) {
        $settings = get_option('folio_notification_settings', array());
        
        // 检查是否启用邮件通知
        if (empty($settings['email_enabled'])) {
            return;
        }
        
        // 检查管理员用户注册提醒是否启用了邮件通知
        if (isset($settings['email_types']['admin_user_register']) && empty($settings['email_types']['admin_user_register'])) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }
        
        $user = get_userdata($user_id);
        $site_name = get_bloginfo('name');
        $admin_url = admin_url('user-edit.php?user_id=' . $user_id);
        $users_url = admin_url('users.php');
        
        $subject = '[' . $site_name . '] ' . __('New User Registration Alert', 'folio');
        
        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
        $message .= '<h2 style="color: #d63638; border-bottom: 2px solid #d63638; padding-bottom: 10px;">' . esc_html__('New User Registration', 'folio') . '</h2>';
        $message .= '<p>' . esc_html__('Hello Admin,', 'folio') . '</p>';
        $message .= '<p>' . esc_html__('A new user has registered on your website. Details are as follows:', 'folio') . '</p>';
        $message .= '<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        $message .= '<p style="margin: 5px 0;"><strong>' . esc_html__('Username:', 'folio') . '</strong>' . esc_html($username) . '</p>';
        $message .= '<p style="margin: 5px 0;"><strong>' . esc_html__('Email:', 'folio') . '</strong>' . esc_html($email) . '</p>';
        $message .= '<p style="margin: 5px 0;"><strong>' . esc_html__('Registered At:', 'folio') . '</strong>' . esc_html($user->user_registered) . '</p>';
        $message .= '<p style="margin: 5px 0;"><strong>' . esc_html__('User ID:', 'folio') . '</strong>' . esc_html($user_id) . '</p>';
        $message .= '</div>';
        $message .= '<div style="text-align: center; margin: 30px 0;">';
        $message .= '<a href="' . esc_url($admin_url) . '" style="display: inline-block; background: #0073aa; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-right: 10px;">' . esc_html__('View User Details', 'folio') . '</a>';
        $message .= '<a href="' . esc_url($users_url) . '" style="display: inline-block; background: #666; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">' . esc_html__('Manage All Users', 'folio') . '</a>';
        $message .= '</div>';
        $message .= '<p style="color: #666; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">';
        $message .= esc_html__('This email was sent automatically by the system.', 'folio');
        $message .= '</p>';
        $message .= '</div>';
        $message .= '</body></html>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * 添加用户菜单项
     */
    public function add_user_menu_item($items, $args) {
        if ($args->theme_location !== 'primary') {
            return $items;
        }

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $items .= '<li class="menu-item"><a href="' . home_url($this->base_slug) . '">' . sprintf(__('Hello, %s', 'folio'), $user->display_name) . '</a></li>';
        } else {
            $items .= '<li class="menu-item"><a href="' . home_url($this->base_slug . '/login') . '">' . __('Login', 'folio') . '</a></li>';
        }

        return $items;
    }

    /**
     * 获取当前操作
     */
    public function get_current_action() {
        // 如果 current_action 未设置，尝试从 query_var 获取
        if (empty($this->current_action)) {
            $action = get_query_var('folio_user_center');
            $this->current_action = $action ? $action : 'dashboard';
        }
        return $this->current_action;
    }

    /**
     * 获取用户中心 URL
     */
    public static function get_url($action = '') {
        $base_url = home_url('user-center');
        
        if (!empty($action)) {
            $base_url .= '/' . $action;
        }
        
        return $base_url;
    }
}

// 初始化
folio_User_Center::get_instance();

// 注意：folio_flush_rewrite_rules() 会在主题切换时统一刷新重写规则

