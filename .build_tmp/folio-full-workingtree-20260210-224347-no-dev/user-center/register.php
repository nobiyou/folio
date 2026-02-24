<?php
/**
 * User Center - Register
 * 
 * 注册页面
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

$register_error = isset($_GET['register_error']) ? urldecode($_GET['register_error']) : '';
?>

<div class="auth-page">
    <div class="auth-card">
        <!-- Logo/标题 -->
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold mb-2"><?php esc_html_e('Create Account', 'folio'); ?></h1>
            <p><?php esc_html_e('Join us and explore more amazing content', 'folio'); ?></p>
        </div>

        <!-- 错误信息 -->
        <?php if ($register_error) : ?>
        <div class="alert alert-error">
            <?php echo esc_html($register_error); ?>
        </div>
        <?php endif; ?>

        <!-- 注册表单 -->
        <form method="post" id="register-form">
            <input type="hidden" name="folio_action" value="register">
            <?php wp_nonce_field('folio_register', 'folio_register_nonce'); ?>

            <div class="form-group">
                <label for="username" class="form-label"><?php esc_html_e('Username', 'folio'); ?></label>
                <input type="text" id="username" name="username" class="form-input" required minlength="3" maxlength="60">
                <small><?php esc_html_e('3-60 characters, letters, numbers and underscores only', 'folio'); ?></small>
            </div>

            <div class="form-group">
                <label for="email" class="form-label"><?php esc_html_e('Email Address', 'folio'); ?></label>
                <input type="email" id="email" name="email" class="form-input" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label"><?php esc_html_e('Password', 'folio'); ?></label>
                <input type="password" id="password" name="password" class="form-input" required minlength="6">
                <small><?php esc_html_e('At least 6 characters', 'folio'); ?></small>
            </div>

            <div class="form-group">
                <label for="password_confirm" class="form-label"><?php esc_html_e('Confirm Password', 'folio'); ?></label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-input" required>
            </div>

            <button type="submit" class="btn-primary w-full">
                <?php esc_html_e('Sign Up', 'folio'); ?>
            </button>
        </form>

        <!-- 登录链接 -->
        <div class="text-center mt-6">
            <p>
                <?php esc_html_e('Already have an account?', 'folio'); ?>
                <a href="<?php echo folio_User_Center::get_url('login'); ?>" class="font-medium hover:underline">
                    <?php esc_html_e('Sign In', 'folio'); ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('register-form');
    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('password_confirm');

    form.addEventListener('submit', function(e) {
        if (password.value !== passwordConfirm.value) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Passwords do not match', 'folio')); ?>');
            passwordConfirm.focus();
        }
    });
});
</script>
