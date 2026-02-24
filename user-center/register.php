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
                <label for="verification_code" class="form-label"><?php esc_html_e('Email verification code', 'folio'); ?></label>
                <div class="flex gap-2">
                    <input type="text" id="verification_code" name="verification_code" class="form-input flex-1" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="<?php esc_attr_e('6-digit code', 'folio'); ?>" autocomplete="one-time-code">
                    <button type="button" id="send-code-btn" class="btn-secondary whitespace-nowrap"><?php esc_html_e('Send code', 'folio'); ?></button>
                </div>
                <small><?php esc_html_e('We will send a 6-digit code to your email. Enter it above to verify.', 'folio'); ?></small>
                <span id="send-code-msg" class="hidden text-sm mt-1"></span>
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
    const emailInput = document.getElementById('email');
    const codeInput = document.getElementById('verification_code');
    const sendCodeBtn = document.getElementById('send-code-btn');
    const sendCodeMsg = document.getElementById('send-code-msg');

    form.addEventListener('submit', function(e) {
        if (password.value !== passwordConfirm.value) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Passwords do not match', 'folio')); ?>');
            passwordConfirm.focus();
            return;
        }
        if (!codeInput.value.trim()) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Please enter the verification code from your email.', 'folio')); ?>');
            codeInput.focus();
        }
    });

    if (sendCodeBtn && emailInput) {
        let countdown = 0;
        sendCodeBtn.addEventListener('click', function() {
            var email = emailInput.value.trim();
            if (!email) {
                sendCodeMsg.textContent = '<?php echo esc_js(__('Please enter your email first.', 'folio')); ?>';
                sendCodeMsg.classList.remove('hidden');
                sendCodeMsg.classList.add('text-red-600');
                return;
            }
            if (countdown > 0) return;
            sendCodeMsg.classList.add('hidden');
            sendCodeBtn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'folio_send_register_code');
            fd.append('nonce', '<?php echo esc_js(wp_create_nonce('folio_ajax_nonce')); ?>');
            fd.append('email', email);
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        sendCodeMsg.textContent = res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Verification code sent. Please check your email.', 'folio')); ?>';
                        sendCodeMsg.classList.remove('text-red-600');
                        sendCodeMsg.classList.add('text-green-600');
                        sendCodeMsg.classList.remove('hidden');
                        countdown = 60;
                        var t = setInterval(function() {
                            countdown--;
                            sendCodeBtn.textContent = countdown > 0 ? countdown + 's' : '<?php echo esc_js(__('Send code', 'folio')); ?>';
                            if (countdown <= 0) {
                                clearInterval(t);
                                sendCodeBtn.disabled = false;
                            }
                        }, 1000);
                    } else {
                        sendCodeMsg.textContent = (res.data && res.data.message) ? res.data.message : '<?php echo esc_js(__('Failed to send code.', 'folio')); ?>';
                        sendCodeMsg.classList.add('text-red-600');
                        sendCodeMsg.classList.remove('hidden');
                        sendCodeBtn.disabled = false;
                    }
                })
                .catch(function() {
                    sendCodeMsg.textContent = '<?php echo esc_js(__('Network error. Please try again.', 'folio')); ?>';
                    sendCodeMsg.classList.add('text-red-600');
                    sendCodeMsg.classList.remove('hidden');
                    sendCodeBtn.disabled = false;
                });
        });
    }
});
</script>
