<?php
/**
 * SMTP 邮件发送类
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_SMTP_Mailer {

    private static $smtp_hook_added = false;

    /**
     * 配置 SMTP
     */
    public static function configure() {
        $settings = get_option('folio_notification_settings', array());
        
        // 配置 SMTP（如果启用）
        if (!empty($settings['smtp_enabled']) && !self::$smtp_hook_added) {
            add_action('phpmailer_init', array(__CLASS__, 'configure_smtp'), 10, 1);
            self::$smtp_hook_added = true;
        }
        
        // 设置发件人信息（每次都需要设置，因为过滤器可能被其他插件修改）
        self::set_from_info($settings);
    }

    /**
     * 配置 SMTP 设置
     */
    public static function configure_smtp($phpmailer) {
        $settings = get_option('folio_notification_settings', array());
        
        if (empty($settings['smtp_enabled'])) {
            return;
        }
        
        $phpmailer->isSMTP();
        $phpmailer->Host = isset($settings['smtp_host']) ? $settings['smtp_host'] : '';
        $phpmailer->Port = isset($settings['smtp_port']) ? absint($settings['smtp_port']) : 587;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = isset($settings['smtp_username']) ? $settings['smtp_username'] : '';
        $phpmailer->Password = isset($settings['smtp_password']) ? $settings['smtp_password'] : '';
        
        // 设置加密方式
        $encryption = isset($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls';
        if ($encryption === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($encryption === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
        }
        
        // 调试模式（可选）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $phpmailer->SMTPDebug = 0; // 0 = 关闭调试，1-4 = 不同级别的调试
        }
    }

    /**
     * 设置发件人信息
     */
    private static function set_from_info($settings) {
        $from_email = isset($settings['from_email']) && !empty($settings['from_email']) 
            ? $settings['from_email'] 
            : get_option('admin_email');
        
        $from_name = isset($settings['from_name']) && !empty($settings['from_name']) 
            ? $settings['from_name'] 
            : get_bloginfo('name');
        
        // 使用高优先级确保我们的设置生效
        add_filter('wp_mail_from', function($from) use ($from_email) {
            return $from_email;
        }, 999);
        
        add_filter('wp_mail_from_name', function($name) use ($from_name) {
            return $from_name;
        }, 999);
    }

    /**
     * 设置默认发件人信息
     */
    private static function set_default_from() {
        add_filter('wp_mail_from', function($from) {
            return get_option('admin_email');
        });
        
        add_filter('wp_mail_from_name', function($name) {
            return get_bloginfo('name');
        });
    }
}

