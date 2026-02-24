<?php
/**
 * User Center - Forgot Password
 * 
 * 忘记密码页面
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

$message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
$error = isset($_GET['error']) ? urldecode($_GET['error']) : '';
?>

<div class="auth-page">
    <div class="auth-card">
        <!-- Logo/标题 -->
        <div class="text-center mb-8">
            <div class="icon-wrapper mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold mb-2"><?php esc_html_e('Forgot Password', 'folio'); ?></h1>
            <p><?php esc_html_e('Enter your email address and we will send you a password reset link', 'folio'); ?></p>
        </div>

        <!-- 成功消息 -->
        <?php if ($message === 'sent') : ?>
        <div class="alert alert-success">
            <?php esc_html_e('Password reset link has been sent to your email', 'folio'); ?>
        </div>
        <?php endif; ?>

        <!-- 错误信息 -->
        <?php if ($error) : ?>
        <div class="alert alert-error">
            <?php echo esc_html($error); ?>
        </div>
        <?php endif; ?>

        <!-- 重置密码表单 -->
        <form method="post" id="forgot-password-form" action="<?php echo wp_lostpassword_url(); ?>">
            <div class="form-group">
                <label for="user_login" class="form-label"><?php esc_html_e('Email Address', 'folio'); ?></label>
                <input type="email" id="user_login" name="user_login" class="form-input" required>
            </div>

            <button type="submit" class="btn-primary w-full">
                <?php esc_html_e('Send Reset Link', 'folio'); ?>
            </button>
        </form>

        <!-- 返回登录 -->
        <div class="text-center mt-6">
            <a href="<?php echo folio_User_Center::get_url('login'); ?>" class="font-medium hover:underline">
                ← <?php esc_html_e('Back to Sign In', 'folio'); ?>
            </a>
        </div>
    </div>
</div>
