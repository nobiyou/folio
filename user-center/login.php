<?php
/**
 * User Center - Login
 * 
 * 登录页面
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

// 从transient获取错误信息
$login_error = '';
if (isset($_GET['login_error_key']) && !empty($_GET['login_error_key'])) {
    $error_key = sanitize_text_field($_GET['login_error_key']);
    $transient_name = 'folio_login_error_' . $error_key;
    $login_error = get_transient($transient_name);
    
    if ($login_error) {
        // 获取后立即删除，确保只显示一次
        delete_transient($transient_name);
    }
}
?>

<div class="auth-page">
    <div class="auth-card">
        <!-- Logo/标题 -->
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold mb-2"><?php esc_html_e('Welcome Back', 'folio'); ?></h1>
            <p><?php esc_html_e('Sign in to your account', 'folio'); ?></p>
        </div>

        <!-- 错误信息 -->
        <?php if (!empty($login_error)) : ?>
        <div id="login-error-alert" class="alert alert-error mb-4 p-4 bg-red-50 border border-red-200 rounded-lg transition-all duration-300" style="display: block !important;">
            <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-red-700 text-sm flex-1">
                    <?php echo wp_kses_post($login_error); ?>
                </div>
                <button type="button" 
                        onclick="document.getElementById('login-error-alert').style.display='none';" 
                        class="text-red-500 hover:text-red-700 ml-2 flex-shrink-0"
                        aria-label="<?php esc_attr_e('Close', 'folio'); ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        <script>
        // 自动隐藏错误提示（5秒后）
        (function() {
            var errorAlert = document.getElementById('login-error-alert');
            if (errorAlert) {
                setTimeout(function() {
                    errorAlert.style.opacity = '0';
                    errorAlert.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        errorAlert.style.display = 'none';
                    }, 300);
                }, 5000);
            }
        })();
        </script>
        <?php endif; ?>

        <!-- 登录表单 -->
        <form method="post" id="login-form">
            <input type="hidden" name="folio_action" value="login">
            <?php wp_nonce_field('folio_login', 'folio_login_nonce'); ?>

            <div class="form-group">
                <label for="username" class="form-label"><?php esc_html_e('Username or Email', 'folio'); ?></label>
                <input type="text" id="username" name="username" class="form-input" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label"><?php esc_html_e('Password', 'folio'); ?></label>
                <input type="password" id="password" name="password" class="form-input" required>
            </div>

            <div class="form-group flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember" class="w-4 h-4">
                    <span><?php esc_html_e('Remember me', 'folio'); ?></span>
                </label>
                <a href="<?php echo folio_User_Center::get_url('forgot-password'); ?>" class="text-sm hover:underline">
                    <?php esc_html_e('Forgot password?', 'folio'); ?>
                </a>
            </div>

            <button type="submit" class="btn-primary w-full">
                <?php esc_html_e('Sign In', 'folio'); ?>
            </button>
        </form>

        <!-- 注册链接 -->
        <div class="text-center mt-6">
            <p>
                <?php esc_html_e("Don't have an account?", 'folio'); ?>
                <a href="<?php echo folio_User_Center::get_url('register'); ?>" class="font-medium hover:underline">
                    <?php esc_html_e('Sign Up', 'folio'); ?>
                </a>
            </p>
        </div>

        <!-- 返回首页 -->
        <div class="text-center mt-4">
            <a href="<?php echo home_url(); ?>" class="text-sm hover:underline">
                ← <?php esc_html_e('Back to Home', 'folio'); ?>
            </a>
        </div>
    </div>
</div>
