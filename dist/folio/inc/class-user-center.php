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
            'dashboard' => __('用户中心', 'folio'),
            'favorites' => __('我的收藏', 'folio'),
            'profile' => __('个人资料', 'folio'),
            'membership' => __('会员中心', 'folio'),
            'notifications' => __('我的通知', 'folio'),
            'login' => __('Sign In', 'folio'),
            'register' => __('Sign Up', 'folio'),
            'forgot-password' => __('Forgot Password', 'folio'),
        );

        $page_title = isset($titles[$this->current_action]) ? $titles[$this->current_action] : __('用户中心', 'folio');
        
        return $page_title . ' ' . $sep . ' ' . get_bloginfo('name');
    }

    /**
     * 设置文档标题
     */
    public function set_document_title($title_parts) {
        $titles = array(
            'dashboard' => __('用户中心', 'folio'),
            'favorites' => __('我的收藏', 'folio'),
            'profile' => __('个人资料', 'folio'),
            'membership' => __('会员中心', 'folio'),
            'notifications' => __('我的通知', 'folio'),
            'login' => __('Sign In', 'folio'),
            'register' => __('Sign Up', 'folio'),
            'forgot-password' => __('Forgot Password', 'folio'),
        );

        $page_title = isset($titles[$this->current_action]) ? $titles[$this->current_action] : __('用户中心', 'folio');
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
                return sprintf(__('密码不正确，请重新输入。如忘记密码，可<a href="%s">点击重置</a>', 'folio'), 
                    esc_url($forgot_password_url));
            
            case 'invalid_username':
            case 'invalid_email':
                return __('用户名或邮箱不存在，请检查后重试', 'folio');
            
            case 'empty_username':
                return __('请输入用户名或邮箱', 'folio');
            
            case 'empty_password':
                return __('请输入密码', 'folio');
            
            default:
                // 尝试从错误消息中提取关键信息
                if (strpos($error_message, 'password') !== false && strpos($error_message, 'incorrect') !== false) {
                    return sprintf(__('密码不正确，请重新输入。如忘记密码，可<a href="%s">点击重置</a>', 'folio'), 
                        esc_url($forgot_password_url));
                }
                if (strpos($error_message, 'username') !== false || strpos($error_message, 'email') !== false) {
                    return __('用户名或邮箱不存在，请检查后重试', 'folio');
                }
                // 如果无法识别，返回通用错误消息
                return __('登录失败，请检查用户名和密码', 'folio');
        }
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

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_redirect(add_query_arg('register_error', urlencode($user_id->get_error_message()), home_url($this->base_slug . '/register')));
        } else {
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
                'message' => __('登录成功！', 'folio'),
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

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        } else {
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
            $items .= '<li class="menu-item"><a href="' . home_url($this->base_slug) . '">' . sprintf(__('你好，%s', 'folio'), $user->display_name) . '</a></li>';
        } else {
            $items .= '<li class="menu-item"><a href="' . home_url($this->base_slug . '/login') . '">' . __('登录', 'folio') . '</a></li>';
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

// 注意：folio_flush_rewrite_rules() 函数已在 custom-post-types.php 中定义
// 该函数会同时刷新作品集和用户中心的重写规则

