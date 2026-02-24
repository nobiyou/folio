<?php
/**
 * Cache File Manager
 * 
 * 缓存文件管理器 - 自动管理object-cache.php文件的安装和卸载
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Cache_File_Manager {

    // 目标文件路径
    const TARGET_PATH = WP_CONTENT_DIR . '/object-cache.php';
    const BACKUP_PATH = WP_CONTENT_DIR . '/object-cache-backup.php';
    
    // 模板文件路径
    private $template_path;
    
    public function __construct() {
        $this->template_path = get_template_directory() . '/inc/object-cache-template.php';
        
        // 主题激活时的钩子
        add_action('after_switch_theme', array($this, 'on_theme_activation'));
        
        // 主题停用时的钩子
        add_action('switch_theme', array($this, 'on_theme_deactivation'));
        
        // 管理员页面钩子
        add_action('admin_init', array($this, 'admin_init'));
        
        // AJAX处理器：仅在未被其他模块注册时兜底注册
        if (!has_action('wp_ajax_folio_install_object_cache')) {
            add_action('wp_ajax_folio_install_object_cache', array($this, 'ajax_install_object_cache'));
        }
        if (!has_action('wp_ajax_folio_uninstall_object_cache')) {
            add_action('wp_ajax_folio_uninstall_object_cache', array($this, 'ajax_uninstall_object_cache'));
        }
        add_action('wp_ajax_folio_check_object_cache', array($this, 'ajax_check_object_cache'));
    }

    /**
     * 管理员初始化
     */
    public function admin_init() {
        // 检查是否需要显示安装提示
        if (current_user_can('manage_options')) {
            $this->maybe_show_install_notice();
        }
    }

    /**
     * 主题激活时自动安装object-cache.php
     */
    public function on_theme_activation() {
        // 延迟执行，避免在WordPress初始化过程中出现冲突
        add_action('wp_loaded', array($this, 'delayed_activation_setup'));
    }

    /**
     * 延迟的激活设置
     */
    public function delayed_activation_setup() {
        // 检查是否已经有object-cache.php
        if ($this->has_object_cache()) {
            // 如果是Folio的版本，无需操作
            if ($this->is_folio_object_cache()) {
                return;
            }
            
            // 如果是其他版本，只设置提示，不自动替换
            update_option('folio_show_object_cache_replace_notice', true);
            return;
        }
        
        // 如果Memcached可用，设置安装提示
        if ($this->is_memcached_available()) {
            update_option('folio_show_memcached_notice', true);
        } else {
            // Memcached不可用，设置安装提示
            update_option('folio_show_memcached_install_notice', true);
        }
    }

    /**
     * 主题停用时处理
     */
    public function on_theme_deactivation() {
        // 如果是Folio安装的object-cache.php，询问是否保留
        if ($this->is_folio_object_cache()) {
            // 设置标记，在下次访问管理页面时询问
            update_option('folio_object_cache_cleanup_needed', true);
        }
    }

    /**
     * 检查是否需要显示安装提示
     */
    private function maybe_show_install_notice() {
        // Memcached可用但未安装object-cache.php
        if (get_option('folio_show_memcached_notice') && $this->is_memcached_available() && !$this->has_object_cache()) {
            add_action('admin_notices', array($this, 'show_install_notice'));
        }
        
        // Memcached不可用提示
        if (get_option('folio_show_memcached_install_notice')) {
            add_action('admin_notices', array($this, 'show_memcached_install_notice'));
        }
        
        // 替换现有object-cache.php提示
        if (get_option('folio_show_object_cache_replace_notice')) {
            add_action('admin_notices', array($this, 'show_replace_notice'));
        }
        
        // 需要清理提示
        if (get_option('folio_object_cache_cleanup_needed')) {
            add_action('admin_notices', array($this, 'show_cleanup_notice'));
        }
    }

    /**
     * 显示Memcached安装提示
     */
    public function show_memcached_install_notice() {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php esc_html_e('Folio Cache Optimization', 'folio'); ?></strong>:
                <?php esc_html_e('To achieve best performance, install the Memcached extension.', 'folio'); ?>
            </p>
            <p>
                <strong><?php esc_html_e('Installation:', 'folio'); ?></strong>
                <code>sudo apt-get install memcached php-memcached</code> (Ubuntu/Debian)<br>
                <code>sudo yum install memcached php-memcached</code> (CentOS/RHEL)
            </p>
            <p>
                <button type="button" class="button" onclick="jQuery(this).closest('.notice').fadeOut(); jQuery.post(ajaxurl, {action: 'folio_dismiss_memcached_install_notice', nonce: '<?php echo wp_create_nonce('folio_object_cache'); ?>'});"><?php esc_html_e('Got it', 'folio'); ?></button>
            </p>
        </div>
        <?php
    }

    /**
     * 显示替换提示
     */
    public function show_replace_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e('Folio Cache Optimization', 'folio'); ?></strong>:
                <?php esc_html_e('Existing object cache file detected. Replace it with Folio optimized version?', 'folio'); ?>
            </p>
            <p>
                <button type="button" class="button button-primary" onclick="folioReplaceObjectCache()"><?php esc_html_e('Replace with Folio version', 'folio'); ?></button>
                <button type="button" class="button" onclick="jQuery(this).closest('.notice').fadeOut(); jQuery.post(ajaxurl, {action: 'folio_dismiss_replace_notice', nonce: '<?php echo wp_create_nonce('folio_object_cache'); ?>'});"><?php esc_html_e('Keep current version', 'folio'); ?></button>
            </p>
        </div>
        
        <script>
        function folioReplaceObjectCache() {
            if (!confirm('<?php echo esc_js(__('Replace existing object cache? Current file will be backed up automatically.', 'folio')); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'folio_install_object_cache',
                nonce: '<?php echo wp_create_nonce('folio_object_cache'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Object cache replaced successfully!', 'folio')); ?>');
                    location.reload();
                } else {
                    alert('<?php echo esc_js(__('Replace failed: ', 'folio')); ?>' + response.data);
                }
            });
        }
        </script>
        <?php
    }

    /**
     * 显示安装提示
     */
    public function show_install_notice() {
        ?>
        <div class="notice notice-info is-dismissible" id="folio-object-cache-notice">
            <p>
                <strong><?php esc_html_e('Folio Cache Optimization', 'folio'); ?></strong>:
                <?php esc_html_e('Memcached is available. Install Folio optimized object cache?', 'folio'); ?>
                <?php esc_html_e('This will significantly improve site performance.', 'folio'); ?>
            </p>
            <p>
                <button type="button" class="button button-primary" id="install-object-cache"><?php esc_html_e('Install now', 'folio'); ?></button>
                <button type="button" class="button" id="dismiss-object-cache-notice"><?php esc_html_e('Not now', 'folio'); ?></button>
            </p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#install-object-cache').on('click', function() {
                const btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Installing...', 'folio')); ?>');
                
                $.post(ajaxurl, {
                    action: 'folio_install_object_cache',
                    nonce: '<?php echo wp_create_nonce('folio_object_cache'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#folio-object-cache-notice').html(
                            '<p style="color: #46b450;"><strong><?php echo esc_js(__('Object cache installed successfully!', 'folio')); ?></strong> <?php echo esc_js(__('Site performance has been optimized.', 'folio')); ?></p>'
                        );
                        setTimeout(() => {
                            $('#folio-object-cache-notice').fadeOut();
                        }, 3000);
                    } else {
                        alert('<?php echo esc_js(__('Install failed: ', 'folio')); ?>' + response.data);
                        btn.prop('disabled', false).text('<?php echo esc_js(__('Install now', 'folio')); ?>');
                    }
                });
            });
            
            $('#dismiss-object-cache-notice').on('click', function() {
                $('#folio-object-cache-notice').fadeOut();
                $.post(ajaxurl, {
                    action: 'folio_dismiss_object_cache_notice',
                    nonce: '<?php echo wp_create_nonce('folio_object_cache'); ?>'
                });
            });
        });
        </script>
        <?php
    }

    /**
     * 显示清理提示
     */
    public function show_cleanup_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e('Folio theme is deactivated', 'folio'); ?></strong>:
                <?php esc_html_e('Folio object cache file detected. Do you want to clean it up?', 'folio'); ?>
            </p>
            <p>
                <button type="button" class="button" onclick="folioCleanupObjectCache(false)"><?php esc_html_e('Keep cache file', 'folio'); ?></button>
                <button type="button" class="button button-secondary" onclick="folioCleanupObjectCache(true)"><?php esc_html_e('Clean cache file', 'folio'); ?></button>
            </p>
        </div>
        
        <script>
        function folioCleanupObjectCache(remove) {
            if (remove) {
                jQuery.post(ajaxurl, {
                    action: 'folio_uninstall_object_cache',
                    nonce: '<?php echo wp_create_nonce('folio_object_cache'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('Cache file cleaned', 'folio')); ?>');
                    } else {
                        alert('<?php echo esc_js(__('Cleanup failed: ', 'folio')); ?>' + response.data);
                    }
                    location.reload();
                });
            } else {
                jQuery.post(ajaxurl, {
                    action: 'folio_dismiss_cleanup_notice',
                    nonce: '<?php echo wp_create_nonce('folio_object_cache'); ?>'
                }, function() {
                    location.reload();
                });
            }
        }
        </script>
        <?php
    }

    /**
     * 安装object-cache.php
     */
    public function install_object_cache() {
        // 检查权限
        if (!current_user_can('manage_options')) {
            return array('success' => false, 'message' => __('Insufficient permissions', 'folio'));
        }

        // 检查Memcached扩展
        if (!class_exists('Memcached')) {
            return array('success' => false, 'message' => __('Memcached extension is unavailable', 'folio'));
        }

        // 检查模板文件是否存在
        if (!file_exists($this->template_path)) {
            return array('success' => false, 'message' => __('Template file does not exist: ', 'folio') . $this->template_path);
        }

        // 检查目标目录是否可写
        if (!is_writable(WP_CONTENT_DIR)) {
            return array('success' => false, 'message' => __('wp-content directory is not writable', 'folio'));
        }

        // 检查是否已经有WordPress缓存函数定义
        if (function_exists('wp_cache_get') && !defined('WP_INSTALLING')) {
            // 如果已经有缓存函数，检查是否来自其他object-cache.php
            if ($this->has_object_cache() && !$this->is_folio_object_cache()) {
                return array('success' => false, 'message' => __('Another object cache implementation is detected. Uninstall it or handle manually first.', 'folio'));
            }
        }

        // 备份现有文件
        if ($this->has_object_cache() && !$this->is_folio_object_cache()) {
            if (!$this->backup_existing_object_cache()) {
                return array('success' => false, 'message' => __('Failed to back up existing file', 'folio'));
            }
        }

        // 复制模板文件
        if (!copy($this->template_path, self::TARGET_PATH)) {
            return array('success' => false, 'message' => __('Failed to copy file', 'folio'));
        }

        // 设置文件权限
        if (file_exists(self::TARGET_PATH)) {
            chmod(self::TARGET_PATH, 0644);
        }

        // 验证安装是否成功
        if (!$this->validate_object_cache_installation()) {
            // 如果验证失败，清理文件
            if (file_exists(self::TARGET_PATH)) {
                unlink(self::TARGET_PATH);
            }
            return array('success' => false, 'message' => __('Object cache validation failed after installation', 'folio'));
        }

        // 记录安装信息
        update_option('folio_object_cache_installed', true);
        update_option('folio_object_cache_install_time', time());
        update_option('folio_object_cache_version', '1.0');
        delete_option('folio_show_memcached_notice');

        return array('success' => true, 'message' => __('Object cache installed successfully', 'folio'));
    }

    /**
     * 卸载object-cache.php
     */
    public function uninstall_object_cache() {
        // 检查权限
        if (!current_user_can('manage_options')) {
            return array('success' => false, 'message' => __('Insufficient permissions', 'folio'));
        }

        // 检查是否是Folio的版本
        if (!$this->is_folio_object_cache()) {
            return array('success' => false, 'message' => __('This is not an object cache installed by Folio', 'folio'));
        }

        // 删除文件
        if (file_exists(self::TARGET_PATH)) {
            if (!unlink(self::TARGET_PATH)) {
                return array('success' => false, 'message' => __('Failed to delete file', 'folio'));
            }
        }

        // 恢复备份文件
        if (file_exists(self::BACKUP_PATH)) {
            rename(self::BACKUP_PATH, self::TARGET_PATH);
        }

        // 清理选项
        delete_option('folio_object_cache_installed');
        delete_option('folio_object_cache_install_time');
        delete_option('folio_object_cache_version');
        delete_option('folio_object_cache_cleanup_needed');

        return array('success' => true, 'message' => __('Object cache uninstalled successfully', 'folio'));
    }

    /**
     * 检查是否有object-cache.php
     */
    public function has_object_cache() {
        return file_exists(self::TARGET_PATH);
    }

    /**
     * 检查是否是Folio的object-cache.php
     */
    public function is_folio_object_cache() {
        if (!$this->has_object_cache()) {
            return false;
        }

        $content = file_get_contents(self::TARGET_PATH);
        return strpos($content, 'Folio Memcached Object Cache') !== false;
    }

    /**
     * 检查Memcached是否可用
     */
    public function is_memcached_available() {
        return class_exists('Memcached');
    }

    /**
     * 验证object-cache.php安装
     */
    private function validate_object_cache_installation() {
        if (!$this->has_object_cache()) {
            return false;
        }

        // 检查文件内容
        $content = file_get_contents(self::TARGET_PATH);
        if (strpos($content, 'Folio_Memcached_Object_Cache') === false) {
            return false;
        }

        // 检查语法错误
        $output = array();
        $return_var = 0;
        exec('php -l ' . escapeshellarg(self::TARGET_PATH) . ' 2>&1', $output, $return_var);
        
        if ($return_var !== 0) {
            error_log('Folio Object Cache: PHP syntax error in object-cache.php: ' . implode("\n", $output));
            return false;
        }

        return true;
    }

    /**
     * 备份现有的object-cache.php
     */
    private function backup_existing_object_cache() {
        if (!$this->has_object_cache()) {
            return true;
        }

        return copy(self::TARGET_PATH, self::BACKUP_PATH);
    }

    /**
     * 获取object-cache.php状态信息
     */
    public function get_status_info() {
        $info = array(
            'has_object_cache' => $this->has_object_cache(),
            'is_folio_version' => false,
            'is_memcached_available' => $this->is_memcached_available(),
            'install_time' => null,
            'version' => null,
            'file_size' => 0,
            'file_permissions' => null,
            'backup_exists' => file_exists(self::BACKUP_PATH)
        );

        if ($info['has_object_cache']) {
            $info['is_folio_version'] = $this->is_folio_object_cache();
            $info['file_size'] = filesize(self::TARGET_PATH);
            $info['file_permissions'] = substr(sprintf('%o', fileperms(self::TARGET_PATH)), -4);
            
            if ($info['is_folio_version']) {
                $info['install_time'] = get_option('folio_object_cache_install_time');
                $info['version'] = get_option('folio_object_cache_version', '1.0');
            }
        }

        return $info;
    }

    /**
     * AJAX: 安装object-cache.php
     */
    public function ajax_install_object_cache() {
        // 添加调试日志
        error_log('Folio: ajax_install_object_cache called');
        error_log('Folio: POST data: ' . print_r($_POST, true));
        
        // 检查nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_object_cache')) {
            error_log('Folio: Nonce verification failed');
            wp_send_json_error(__('Security verification failed', 'folio'));
            return;
        }

        error_log('Folio: Starting object cache installation');
        $result = $this->install_object_cache();
        error_log('Folio: Installation result: ' . print_r($result, true));
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: 卸载object-cache.php
     */
    public function ajax_uninstall_object_cache() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_object_cache')) {
            wp_send_json_error(__('Security verification failed', 'folio'));
            return;
        }

        $result = $this->uninstall_object_cache();
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: 检查object-cache.php状态
     */
    public function ajax_check_object_cache() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_object_cache')) {
            wp_send_json_error(__('Security verification failed', 'folio'));
            return;
        }

        wp_send_json_success($this->get_status_info());
    }
}

// 缓存文件管理器类已在 functions.php 中初始化

// 添加AJAX处理器用于忽略提示
add_action('wp_ajax_folio_dismiss_object_cache_notice', function() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if ($nonce !== '' && wp_verify_nonce($nonce, 'folio_object_cache')) {
        delete_option('folio_show_memcached_notice');
    }
    wp_die();
});

add_action('wp_ajax_folio_dismiss_memcached_install_notice', function() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if ($nonce !== '' && wp_verify_nonce($nonce, 'folio_object_cache')) {
        delete_option('folio_show_memcached_install_notice');
    }
    wp_die();
});

add_action('wp_ajax_folio_dismiss_replace_notice', function() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if ($nonce !== '' && wp_verify_nonce($nonce, 'folio_object_cache')) {
        delete_option('folio_show_object_cache_replace_notice');
    }
    wp_die();
});

add_action('wp_ajax_folio_dismiss_cleanup_notice', function() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if ($nonce !== '' && wp_verify_nonce($nonce, 'folio_object_cache')) {
        delete_option('folio_object_cache_cleanup_needed');
    }
    wp_die();
});
