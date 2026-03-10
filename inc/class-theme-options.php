<?php
/**
 * Theme Options Page
 * 
 * 主题设置页面 - 提供独立的设置界面
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class Folio_Theme_Options {
    
    private $option_name = 'folio_theme_options';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_options_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * 添加主题设置菜单
     */
    public function add_options_page() {
        add_theme_page(
            __('Folio Theme Settings', 'folio'),
            __('Theme Settings', 'folio'),
            'manage_options',
            'folio-theme-options',
            array($this, 'render_options_page')
        );
    }
    
    /**
     * 注册设置
     */
    public function register_settings() {
        register_setting(
            'folio_theme_options_group',
            $this->option_name,
            array($this, 'sanitize_options')
        );
    }
    
    /**
     * 加载管理页面资源
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'appearance_page_folio-theme-options') {
            return;
        }
        
        wp_enqueue_style('folio-admin-options', FOLIO_URI . '/assets/css/admin-options.css', array(), FOLIO_VERSION);
        
        // 加载媒体上传器（这会自动加载所需的脚本和样式）
        wp_enqueue_media();
        
        // 确保脚本在媒体上传器之后加载，并添加正确的依赖关系
        // 注意：脚本路径使用 FOLIO_URI 常量，确保路径正确
        $script_path = FOLIO_URI . '/assets/js/admin-options.js';
        wp_enqueue_script(
            'folio-admin-options', 
            $script_path, 
            array('jquery', 'media-upload', 'media-views', 'media-models'), 
            FOLIO_VERSION, 
            true
        );
        
        // 传递AJAX参数
        wp_localize_script('folio-admin-options', 'folioAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('folio_admin_action'),
            'script_loaded' => true, // 用于调试，确认脚本已加载
            'config' => array(
                'reset_settings_url' => wp_nonce_url(
                    admin_url('themes.php?page=folio-theme-options&tab=backup&action=reset'),
                    'folio_reset_settings'
                ),
                'nonces' => array(
                    'optimize_assets' => wp_create_nonce('folio_optimize_assets'),
                    'clear_optimized_cache' => wp_create_nonce('folio_clear_optimized_cache'),
                    'flush_rewrite' => wp_create_nonce('folio_flush_rewrite'),
                    'batch_update_alt' => wp_create_nonce('folio_batch_update_alt'),
                    'batch_optimize_images' => wp_create_nonce('folio_batch_optimize_images'),
                    'get_optimization_stats' => wp_create_nonce('folio_get_optimization_stats'),
                    'get_performance_data' => wp_create_nonce('folio_get_performance_data'),
                    'clear_performance_logs' => wp_create_nonce('folio_clear_performance_logs'),
                ),
            ),
            'i18n' => array(
                'all_images_optimized' => __('All images have been optimized!', 'folio'),
                'ai_response' => __('AI Response', 'folio'),
                'api_config_prefix' => __('API Config #', 'folio'),
                'api_config_name' => __('Config Name', 'folio'),
                'api_delete_confirm' => __('Are you sure you want to delete this API config?', 'folio'),
                'api_endpoint' => __('API Endpoint', 'folio'),
                'api_endpoint_url' => __('API endpoint URL', 'folio'),
                'api_key' => __('API Key', 'folio'),
                'api_test_details' => __('API test details:', 'folio'),
                'cannot_open_media_uploader' => __('Unable to open media uploader. Please refresh and try again.', 'folio'),
                'connected' => __('Connected', 'folio'),
                'delete_label' => __('Delete', 'folio'),
                'enable_this_api_config' => __('Enable this API config', 'folio'),
                'enabled_label' => __('Enabled', 'folio'),
                'connection_failed' => __('Connection failed', 'folio'),
                'continue_optimization' => __('Continue Optimization', 'folio'),
                'error_message' => __('Error', 'folio'),
                'example_openai_primary' => __('Example: OpenAI Primary', 'folio'),
                'asset_optimization_completed' => __('Asset optimization completed!', 'folio'),
                'average_load_time' => __('Average Load Time:', 'folio'),
                'average_memory_usage' => __('Average Memory Usage:', 'folio'),
                'average_query_count' => __('Average Query Count:', 'folio'),
                'batch_optimize_images' => __('Batch Optimize Images', 'folio'),
                'batch_optimizing_images' => __('Batch optimizing images...', 'folio'),
                'cache_cleared' => __('Cache cleared!', 'folio'),
                'clear_cache_confirm' => __('Are you sure you want to clear optimized cache?', 'folio'),
                'clear_failed_prefix' => __('Clear failed:', 'folio'),
                'clear_performance_logs_confirm' => __('Are you sure you want to clear all performance logs?', 'folio'),
                'clear_optimized_cache' => __('Clear Optimized Cache', 'folio'),
                'clearing' => __('Clearing...', 'folio'),
                'clearing_cache' => __('Clearing cache...', 'folio'),
                'fill_required_fields' => __('Please fill all required fields', 'folio'),
                'frontend_page_data_only' => __('Frontend page data only', 'folio'),
                'frontend_page_performance' => __('Frontend Page Performance', 'folio'),
                'icon' => __('Icon', 'folio'),
                'identify_api_config' => __('Used to identify this API config.', 'folio'),
                'images_left_to_optimize' => __('images left to optimize. You can continue by clicking the optimize button.', 'folio'),
                'load_failed' => __('Load Failed', 'folio'),
                'loading' => __('Loading...', 'folio'),
                'max_load_time' => __('Max Load Time:', 'folio'),
                'model_name' => __('Model Name', 'folio'),
                'media_uploader_not_loaded' => __('Media uploader is not loaded. Please refresh the page and try again.', 'folio'),
                'model' => __('Model', 'folio'),
                'mobile_device' => __('Mobile Device', 'folio'),
                'mobile_visits' => __('Mobile Visits:', 'folio'),
                'no_response_content' => __('No response content', 'folio'),
                'no_slow_query_records' => __('No slow query records', 'folio'),
                'not_set' => __('Not set', 'folio'),
                'optimization_failed_prefix' => __('Optimization failed:', 'folio'),
                'optimization_issues' => __('Optimization Issues:', 'folio'),
                'optimization_suggestions' => __('Optimization Suggestions', 'folio'),
                'optimized_images' => __('Optimized Images:', 'folio'),
                'preview' => __('Preview', 'folio'),
                'pending_optimization' => __('Pending Optimization:', 'folio'),
                'performance_logs_cleared' => __('Performance logs have been cleared', 'folio'),
                'performance_score' => __('Performance Score:', 'folio'),
                'queries_label' => __('queries', 'folio'),
                'refresh_data' => __('Refresh Data', 'folio'),
                'refresh_stats' => __('Refresh Stats', 'folio'),
                'refresh_rewrite_rules' => __('Refresh Rewrite Rules', 'folio'),
                'remove_prefix' => __('Remove ', 'folio'),
                'replace_prefix' => __('Replace ', 'folio'),
                'request_failed' => __('Request failed', 'folio'),
                'request_failed_retry' => __('Request failed, please try again', 'folio'),
                'reset_settings_confirm' => __('Are you sure you want to reset all settings? This cannot be undone!', 'folio'),
                'reset_settings_final_confirm' => __('Final confirmation: really reset?', 'folio'),
                're_optimize_assets' => __('Re-optimize Assets', 'folio'),
                'saved_label' => __('Saved:', 'folio'),
                'select_image' => __('Select Image', 'folio'),
                'select_prefix' => __('Select ', 'folio'),
                'selection' => __('Selection', 'folio'),
                'settings_saved' => __('Settings saved!', 'folio'),
                'space_saved' => __('Space Saved:', 'folio'),
                'status' => __('Status', 'folio'),
                'there_are_still' => __('There are still', 'folio'),
                'test_connection' => __('Test Connection', 'folio'),
                'total_page_visits' => __('Total Page Visits:', 'folio'),
                'testing' => __('Testing...', 'folio'),
                'testing_all_api_connections' => __('Testing all API connections...', 'folio'),
                'unknown_error' => __('Unknown error', 'folio'),
                'unknown_label' => __('unknown', 'folio'),
                'update_failed' => __('Update failed', 'folio'),
                'updating' => __('Updating...', 'folio'),
                'use_image' => __('Use this image', 'folio'),
                'your_api_key' => __('Your API key', 'folio'),
                'ai_model_to_use' => __('AI model to use', 'folio'),
                'optimizing' => __('Optimizing...', 'folio'),
                'optimizing_assets' => __('Optimizing assets...', 'folio'),
            ),
        ));
    }
    
    /**
     * 获取选项值
     */
    public static function get_option($key, $default = '') {
        $options = get_option('folio_theme_options', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    /**
     * 判断字段是否在当前提交的标签页中
     */
    private function is_field_in_current_tab($field, $submitted_fields) {
        // 定义每个标签页包含的字段
        $tab_fields = array(
            'general' => array('theme_mode', 'show_theme_toggle', 'new_days', 'posts_per_page', 'show_brain_hexagon', 'copyright', 'site_logo', 'site_favicon'),
            'social' => array('email', 'email_icon', 'instagram', 'instagram_icon', 'linkedin', 'linkedin_icon', 'twitter', 'twitter_icon', 'facebook', 'facebook_icon', 'github', 'github_icon'),
            'seo' => array('seo_title', 'seo_keywords', 'seo_description', 'enable_og'),
            'performance' => array('lazy_load', 'minify_html', 'disable_emoji', 'cache_time', 'frontend_optimization'),
            'optimize' => array(
                'remove_category_prefix', 'auto_featured_image', 'search_title_only', 'clean_image_html',
                'editor_type', 'widget_editor_type', 'remove_admin_bar_logo', 'hide_admin_bar_frontend',
                'disable_post_revisions', 'post_revisions_limit', 'disable_autosave',
                'remove_jquery_migrate', 'disable_dashicons_frontend', 'enable_performance_monitor', 'heartbeat_mode',
                'enable_image_compression', 'jpeg_quality', 'png_quality', 'enable_webp_conversion', 'strip_image_metadata',
                'disable_google_fonts', 'remove_version_strings', 'remove_comment_styles',
                'disable_update_emails', 'remove_shortcode_p', 'restrict_rest_api', 'disable_xmlrpc_pingback'
            ),
            'enhancements' => array('enable_lazy_load', 'enable_back_to_top'),
            'ai' => array('ai_api_endpoint', 'ai_api_key', 'ai_model', 'ai_enabled', 'ai_auto_generate_default'),
            'advanced' => array('custom_css', 'header_code', 'footer_code', 'maintenance_mode', 'maintenance_message', 'maintenance_scheduled', 'maintenance_start_time', 'maintenance_end_time', 'maintenance_bypass_ips', 'maintenance_log_enabled'),
        );
        
        // 检查哪些标签页的字段在提交的数据中
        foreach ($tab_fields as $tab => $fields) {
            $intersection = array_intersect($fields, $submitted_fields);
            if (!empty($intersection)) {
                // 找到了当前标签页，检查目标字段是否在这个标签页中
                return in_array($field, $fields);
            }
        }
        
        // 如果无法确定，返回true以更新该字段（安全起见）
        return true;
    }
    
    /**
     * 渲染设置页面
     */
    public function render_options_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        $this->handle_page_actions($active_tab);
        $this->register_page_notices();

        $options = get_option($this->option_name, array());
        $tabs_without_settings_form = array('backup');
        $use_settings_form = !in_array($active_tab, $tabs_without_settings_form, true);
        ?>
        <div class="wrap folio-options-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>
            
            <!-- 标签导航 -->
            <nav class="nav-tab-wrapper">
                <a href="?page=folio-theme-options&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('General', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=social" class="nav-tab <?php echo $active_tab === 'social' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-share"></span> <?php esc_html_e('Social Links', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=seo" class="nav-tab <?php echo $active_tab === 'seo' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-search"></span> <?php esc_html_e('SEO', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=performance" class="nav-tab <?php echo $active_tab === 'performance' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-performance"></span> <?php esc_html_e('Performance', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=optimize" class="nav-tab <?php echo $active_tab === 'optimize' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('Optimization', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=enhancements" class="nav-tab <?php echo $active_tab === 'enhancements' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Enhancements', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=ai" class="nav-tab <?php echo $active_tab === 'ai' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('AI Settings', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('Advanced', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=backup" class="nav-tab <?php echo $active_tab === 'backup' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-database-export"></span> <?php esc_html_e('Backup & Restore', 'folio'); ?>
                </a>
            </nav>
            
            <?php if ($use_settings_form) : ?>
            <form method="post" action="options.php">
                <?php settings_fields('folio_theme_options_group'); ?>
            <?php endif; ?>

                <div class="folio-options-content">
                    <?php
                    switch ($active_tab) {
                        case 'social':
                            $this->render_social_tab($options);
                            break;
                        case 'seo':
                            $this->render_seo_tab($options);
                            break;
                        case 'performance':
                            $this->render_performance_tab($options);
                            break;
                        case 'optimize':
                            $this->render_optimize_tab($options);
                            break;
                        case 'enhancements':
                            $this->render_enhancements_tab($options);
                            break;
                        case 'ai':
                            $this->render_ai_tab($options);
                            break;
                        case 'advanced':
                            $this->render_advanced_tab($options);
                            break;
                        case 'backup':
                            $this->render_backup_tab($options);
                            break;
                        default:
                            $this->render_general_tab($options);
                    }
                    ?>
                </div>

            <?php if ($use_settings_form) : ?>
                <?php submit_button(__('Save Settings', 'folio'), 'primary large'); ?>
            </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * 处理设置页动作
     */
    private function handle_page_actions($active_tab) {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';

        if ($action === 'clear_maintenance_logs' && $nonce && wp_verify_nonce($nonce, 'clear_maintenance_logs')) {
            delete_option('folio_maintenance_logs');
            wp_safe_redirect(admin_url('themes.php?page=folio-theme-options&tab=advanced&cleared=1'));
            exit;
        }

        if ($active_tab !== 'backup') {
            return;
        }

        if (isset($_POST['folio_export_settings'])) {
            check_admin_referer('folio_export_settings');
            $this->export_settings();
        }

        if (isset($_POST['folio_import_settings']) && isset($_FILES['import_file'])) {
            check_admin_referer('folio_import_settings');
            $this->import_settings();
        }

        if ($action === 'reset' && $nonce && wp_verify_nonce($nonce, 'folio_reset_settings')) {
            delete_option('folio_theme_options');
            wp_safe_redirect(admin_url('themes.php?page=folio-theme-options&tab=backup&reset=success'));
            exit;
        }
    }

    /**
     * 注册设置页提示
     */
    private function register_page_notices() {
        if (isset($_GET['cleared']) && $_GET['cleared'] === '1') {
            add_settings_error('folio_theme_options', 'maintenance_logs_cleared', __('Maintenance logs have been cleared', 'folio'), 'success');
        }

        if (isset($_GET['imported']) && $_GET['imported'] === 'success') {
            add_settings_error('folio_theme_options', 'import_success', __('Settings imported successfully!', 'folio'), 'success');
        }

        if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
            add_settings_error('folio_theme_options', 'reset_success', __('Settings have been reset to defaults', 'folio'), 'success');
        }
    }
    
    /**
     * 常规设置标签
     */
    private function render_general_tab($options) {
        $this->render_view('tab-general', array(
            'option_name' => $this->option_name,
            'options' => $options,
        ));
    }
    /**
     * 生成图标选择下拉框
     */
    private function render_icon_select($field_name, $current_value = '', $platform = '') {
        $icon_library = folio_get_social_icon_library();
        $current_value = empty($current_value) ? 'default' : $current_value;
        
        echo '<select name="' . esc_attr($this->option_name) . '[' . esc_attr($field_name) . ']" class="folio-select-inline">';
        echo '<option value="default"' . selected($current_value, 'default', false) . '>' . esc_html__('Default', 'folio') . '</option>';
        
        foreach ($icon_library as $icon_key => $icon_data) {
            if ($icon_key === 'default') {
                continue;
            }
            $selected = selected($current_value, $icon_key, false);
            echo '<option value="' . esc_attr($icon_key) . '"' . $selected . '>' . esc_html($icon_data['name']) . '</option>';
        }
        
        echo '</select>';
    }
    
    /**
     * 社交链接标签
     */
    private function render_social_tab($options) {
        $this->render_view('tab-social', array(
            'option_name' => $this->option_name,
            'options' => $options,
        ));
    }
    
    /**
     * SEO优化标签
     */
    private function render_seo_tab($options) {
        $this->render_view('tab-seo', array(
            'option_name' => $this->option_name,
            'options' => $options,
        ));
    }
    
    /**
     * 性能优化标签
     */
    private function render_performance_tab($options) {
        $frontend_opt = isset($options['frontend_optimization']) ? $options['frontend_optimization'] : array();
        $stats = array();

        if (class_exists('folio_Frontend_Optimizer')) {
            $optimizer = folio_Frontend_Optimizer::get_instance();
            if (method_exists($optimizer, 'get_optimization_stats')) {
                $stats = $optimizer->get_optimization_stats();
            }
        }

        $this->render_view('tab-performance', array(
            'option_name' => $this->option_name,
            'options' => $options,
            'frontend_opt' => $frontend_opt,
            'stats' => $stats,
        ));
    }
    /**
     * 功能优化标签
     */
    private function render_optimize_tab($options) {
        $this->render_view('tab-optimize', array(
            'option_name' => $this->option_name,
            'options' => $options,
        ));
    }
    /**
     * 功能增强标签
     */
    private function render_enhancements_tab($options) {
        $this->render_view('tab-enhancements', array(
            'option_name' => $this->option_name,
            'options' => $options,
        ));
    }
    
    /**
     * AI设置标签
     */
    private function render_ai_tab($options) {
        $ai_apis = isset($options['ai_apis']) && is_array($options['ai_apis']) ? $options['ai_apis'] : array();

        if (empty($ai_apis) && !empty($options['ai_api_endpoint'])) {
            $ai_apis = array(
                array(
                    'name' => __('Default API', 'folio'),
                    'endpoint' => isset($options['ai_api_endpoint']) ? $options['ai_api_endpoint'] : '',
                    'key' => isset($options['ai_api_key']) ? $options['ai_api_key'] : '',
                    'model' => isset($options['ai_model']) ? $options['ai_model'] : 'gpt-3.5-turbo',
                    'enabled' => 1
                )
            );
        }

        if (empty($ai_apis)) {
            $ai_apis = array(
                array(
                    'name' => '',
                    'endpoint' => '',
                    'key' => '',
                    'model' => 'gpt-3.5-turbo',
                    'enabled' => 1
                )
            );
        }
        $this->render_view('tab-ai', array(
            'option_name' => $this->option_name,
            'options' => $options,
            'ai_apis' => $ai_apis,
        ));
    }
    
    /**
     * 高级设置标签
     */
    private function render_advanced_tab($options) {
        $logs = get_option('folio_maintenance_logs', array());

        $this->render_view('tab-advanced', array(
            'option_name' => $this->option_name,
            'options' => $options,
            'logs' => $logs,
        ));
    }
    
    /**
     * 备份恢复标签
     */
    private function render_backup_tab($options) {
        ?>
        <div class="folio-tab-content">
            <?php
            $this->render_backup_export_section();
            $this->render_backup_import_section();
            $this->render_backup_alt_text_section();
            $this->render_backup_image_optimization_section();
            $this->render_backup_performance_section();
            $this->render_backup_reset_section();
            $this->render_backup_info_section($options);
            ?>
        </div>
        <?php
    }

    /**
     * 渲染维护日志区块
     */
    private function render_maintenance_logs_section($options, $logs) {
        if (empty($logs) || empty($options['maintenance_log_enabled'])) {
            return;
        }
        $this->render_view('maintenance-logs', array(
            'logs' => $logs,
        ));
    }

    /**
     * 渲染导出设置区块
     */
    private function render_backup_export_section() {
        $this->render_view('backup-export');
    }

    /**
     * 渲染导入设置区块
     */
    private function render_backup_import_section() {
        $this->render_view('backup-import');
    }

    /**
     * 渲染图片 Alt 优化区块
     */
    private function render_backup_alt_text_section() {
        $this->render_view('backup-alt-text');
    }

    /**
     * 渲染图片优化区块
     */
    private function render_backup_image_optimization_section() {
        $this->render_view('backup-image-optimization');
    }

    /**
     * 渲染性能面板区块
     */
    private function render_backup_performance_section() {
        $this->render_view('backup-performance');
    }

    /**
     * 渲染重置设置区块
     */
    private function render_backup_reset_section() {
        $this->render_view('backup-reset');
    }

    /**
     * 渲染当前设置信息区块
     */
    private function render_backup_info_section($options) {
        $this->render_view('backup-info', array(
            'options' => $options,
        ));
    }

    /**
     * 渲染后台视图文件
     */
    private function render_view($view_name, $context = array()) {
        $view_path = trailingslashit(FOLIO_DIR) . 'inc/views/theme-options/' . $view_name . '.php';

        if (!file_exists($view_path)) {
            return;
        }

        if (!empty($context)) {
            extract($context, EXTR_SKIP);
        }

        include $view_path;
    }
    
    /**
     * 导出设置
     */
    private function export_settings() {
        $options = get_option('folio_theme_options', array());
        
        $export_data = array(
            'version' => FOLIO_VERSION,
            'export_date' => current_time('mysql'),
            'site_url' => get_site_url(),
            'options' => $options,
        );
        
        $filename = 'folio-settings-' . date('Y-m-d-His') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 导入设置
     */
    private function import_settings() {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            add_settings_error('folio_theme_options', 'import_error', __('File upload failed', 'folio'));
            return;
        }
        
        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error('folio_theme_options', 'import_error', __('Invalid JSON file', 'folio'));
            return;
        }
        
        if (!isset($import_data['options']) || !is_array($import_data['options'])) {
            add_settings_error('folio_theme_options', 'import_error', __('Invalid file format', 'folio'));
            return;
        }
        
        // 导入设置
        update_option('folio_theme_options', $import_data['options']);
        
        add_settings_error('folio_theme_options', 'import_success', __('Settings imported successfully!', 'folio'), 'success');
        
        // 刷新页面
        wp_safe_redirect(admin_url('themes.php?page=folio-theme-options&tab=backup&imported=success'));
        exit;
    }
    
    /**
     * 验证和清理选项
     */
    public function sanitize_options($input) {
        // 获取现有设置
        $existing_options = get_option($this->option_name, array());
        
        // 从现有设置开始，而不是空数组
        $sanitized = $existing_options;
        
        // 文本字段
        $text_fields = array('seo_title', 'seo_keywords', 'maintenance_message');
        foreach ($text_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_text_field($input[$field]);
            }
        }
        
        // 版权文本（支持 HTML）
        if (isset($input['copyright'])) {
            $sanitized['copyright'] = wp_kses_post($input['copyright']);
        }
        
        // 文本域
        $textarea_fields = array('seo_description', 'custom_css', 'header_code', 'footer_code');
        foreach ($textarea_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_textarea_field($input[$field]);
            }
        }
        
        // 图片URL字段
        $image_fields = array('site_logo', 'site_favicon');
        foreach ($image_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = esc_url_raw($input[$field]);
            }
        }
        
        // URL字段
        $url_fields = array('email', 'instagram', 'linkedin', 'twitter', 'facebook', 'github');
        foreach ($url_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = $field === 'email' ? sanitize_email($input[$field]) : esc_url_raw($input[$field]);
            }
        }
        
        // 图标字段（图标名称）
        $icon_fields = array('email_icon', 'instagram_icon', 'linkedin_icon', 'twitter_icon', 'facebook_icon', 'github_icon');
        $valid_icon_keys = array('default', 'email', 'instagram', 'linkedin', 'twitter', 'facebook', 'github', 'youtube', 'pinterest', 'tiktok', 'weibo', 'wechat', 'qq', 'telegram', 'discord', 'reddit', 'rss');
        
        // 如果函数存在，使用函数获取有效图标列表
        if (function_exists('folio_get_social_icon_library')) {
            $icon_library = folio_get_social_icon_library();
            $valid_icon_keys = array_keys($icon_library);
        }
        
        foreach ($icon_fields as $field) {
            if (isset($input[$field])) {
                // 验证图标名称是否在有效列表中
                if (in_array($input[$field], $valid_icon_keys)) {
                    $sanitized[$field] = sanitize_text_field($input[$field]);
                } else {
                    // 如果无效，设置为默认值
                    $sanitized[$field] = 'default';
                }
            }
        }
        
        // 数字字段
        if (isset($input['new_days'])) {
            $sanitized['new_days'] = absint($input['new_days']);
        }
        if (isset($input['posts_per_page'])) {
            $sanitized['posts_per_page'] = absint($input['posts_per_page']);
        }
        if (isset($input['cache_time'])) {
            $sanitized['cache_time'] = absint($input['cache_time']);
        }
        if (isset($input['jpeg_quality'])) {
            $sanitized['jpeg_quality'] = max(60, min(100, absint($input['jpeg_quality'])));
        }
        if (isset($input['png_quality'])) {
            $sanitized['png_quality'] = max(70, min(100, absint($input['png_quality'])));
        }
        
        
        // 选择字段
        if (isset($input['theme_mode'])) {
            $sanitized['theme_mode'] = in_array($input['theme_mode'], array('auto', 'light', 'dark')) ? $input['theme_mode'] : 'auto';
        }
        
        // 日出日落时间
        if (isset($input['sunrise_time'])) {
            // 验证时间格式 HH:MM
            if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $input['sunrise_time'])) {
                $sanitized['sunrise_time'] = sanitize_text_field($input['sunrise_time']);
            } else {
                $sanitized['sunrise_time'] = '06:00'; // 默认值
            }
        }
        if (isset($input['sunset_time'])) {
            // 验证时间格式 HH:MM
            if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $input['sunset_time'])) {
                $sanitized['sunset_time'] = sanitize_text_field($input['sunset_time']);
            } else {
                $sanitized['sunset_time'] = '18:00'; // 默认值
            }
        }
        if (isset($input['editor_type'])) {
            $sanitized['editor_type'] = in_array($input['editor_type'], array('gutenberg', 'classic')) ? $input['editor_type'] : 'gutenberg';
        }
        if (isset($input['widget_editor_type'])) {
            $sanitized['widget_editor_type'] = in_array($input['widget_editor_type'], array('gutenberg', 'classic')) ? $input['widget_editor_type'] : 'gutenberg';
        }
        if (isset($input['heartbeat_mode'])) {
            $sanitized['heartbeat_mode'] = in_array($input['heartbeat_mode'], array('default', 'reduce', 'disable')) ? $input['heartbeat_mode'] : 'default';
        }
        
        // 修订版本限制
        if (isset($input['post_revisions_limit'])) {
            $sanitized['post_revisions_limit'] = absint($input['post_revisions_limit']);
        }
        
        // AI设置 - 支持多个API配置
        if (isset($input['ai_apis']) && is_array($input['ai_apis'])) {
            $sanitized_apis = array();
            foreach ($input['ai_apis'] as $index => $api) {
                if (!empty($api['endpoint']) && !empty($api['key'])) {
                    $sanitized_apis[] = array(
                        'name' => sanitize_text_field(isset($api['name']) ? $api['name'] : ''),
                        'endpoint' => esc_url_raw($api['endpoint']),
                        'key' => sanitize_text_field($api['key']),
                        'model' => sanitize_text_field(isset($api['model']) && !empty($api['model']) ? $api['model'] : 'gpt-3.5-turbo'),
                        'enabled' => isset($api['enabled']) ? 1 : 0,
                    );
                }
            }
            $sanitized['ai_apis'] = $sanitized_apis;
        }
        
        // 保留旧字段以兼容（如果新配置为空）
        if (isset($input['ai_api_endpoint'])) {
            $sanitized['ai_api_endpoint'] = esc_url_raw($input['ai_api_endpoint']);
        }
        if (isset($input['ai_api_key'])) {
            $sanitized['ai_api_key'] = sanitize_text_field($input['ai_api_key']);
        }
        if (isset($input['ai_model'])) {
            $sanitized['ai_model'] = sanitize_text_field($input['ai_model']);
        }

        // 维护模式设置
        if (isset($input['maintenance_start_time'])) {
            $sanitized['maintenance_start_time'] = sanitize_text_field($input['maintenance_start_time']);
        }
        if (isset($input['maintenance_end_time'])) {
            $sanitized['maintenance_end_time'] = sanitize_text_field($input['maintenance_end_time']);
        }
        if (isset($input['maintenance_bypass_ips'])) {
            $sanitized['maintenance_bypass_ips'] = sanitize_textarea_field($input['maintenance_bypass_ips']);
        }
        
        // 前端优化配置
        if (isset($input['frontend_optimization']) && is_array($input['frontend_optimization'])) {
            $frontend_opt = array();
            $bool_fields = array(
                'enabled', 'minify_css', 'minify_js', 'combine_css', 'combine_js',
                'lazy_load_images', 'optimize_fonts', 'preload_critical',
                'defer_non_critical', 'enable_gzip', 'cache_busting', 'minify_html'
            );
            
            foreach ($bool_fields as $field) {
                $frontend_opt[$field] = isset($input['frontend_optimization'][$field]) ? 1 : 0;
            }
            
            // 保存高级选项（如果存在）
            if (isset($input['frontend_optimization']['advanced_options']) && is_array($input['frontend_optimization']['advanced_options'])) {
                $frontend_opt['advanced_options'] = array(
                    'exclude_css' => isset($input['frontend_optimization']['advanced_options']['exclude_css']) 
                        ? sanitize_textarea_field($input['frontend_optimization']['advanced_options']['exclude_css']) 
                        : '',
                    'exclude_js' => isset($input['frontend_optimization']['advanced_options']['exclude_js']) 
                        ? sanitize_textarea_field($input['frontend_optimization']['advanced_options']['exclude_js']) 
                        : '',
                    'critical_css' => isset($input['frontend_optimization']['advanced_options']['critical_css']) 
                        ? sanitize_textarea_field($input['frontend_optimization']['advanced_options']['critical_css']) 
                        : '',
                    'preload_fonts' => isset($input['frontend_optimization']['advanced_options']['preload_fonts']) && is_array($input['frontend_optimization']['advanced_options']['preload_fonts'])
                        ? array_map('esc_url_raw', $input['frontend_optimization']['advanced_options']['preload_fonts'])
                        : array()
                );
            }
            
            $sanitized['frontend_optimization'] = $frontend_opt;
            
            // 同时更新旧位置（向后兼容）
            update_option('folio_frontend_optimization', $frontend_opt);
            
            // 更新资源版本
            update_option('folio_asset_version', time());
        }
        
        // 维护模式启用时自动设置开始时间
        $old_options = get_option($this->option_name, array());
        $old_maintenance_mode = isset($old_options['maintenance_mode']) ? $old_options['maintenance_mode'] : 0;
        $new_maintenance_mode = isset($input['maintenance_mode']) ? 1 : 0;
        
        // 如果维护模式从禁用变为启用，且没有设置开始时间，则自动设置为当前时间
        if (!$old_maintenance_mode && $new_maintenance_mode) {
            if (empty($sanitized['maintenance_start_time'])) {
                $sanitized['maintenance_start_time'] = current_time('mysql');
            }
        }
        
        // 复选框 - 只更新提交的字段
        $checkbox_fields = array(
            'show_theme_toggle', 
            'show_brain_hexagon',
            'enable_og', 
            'lazy_load', 
            'minify_html', 
            'disable_emoji', 
            'maintenance_mode',
            'maintenance_scheduled',
            'maintenance_log_enabled',
            'ai_enabled', 
            'ai_auto_generate_default',
            'remove_category_prefix',
            'auto_featured_image',
            'search_title_only',
            'remove_admin_bar_logo',
            'clean_image_html',
            'restrict_rest_api',
            'disable_xmlrpc_pingback',
            'disable_post_revisions',
            'disable_autosave',
            'remove_jquery_migrate',
            'disable_dashicons_frontend',
            'enable_performance_monitor',
            'enable_image_compression',
            'enable_webp_conversion',
            'strip_image_metadata',
            'remove_comment_styles',
            'disable_update_emails',
            'remove_shortcode_p',
            'disable_google_fonts',
            'remove_version_strings',
            'hide_admin_bar_frontend',
            'enable_lazy_load',
            'enable_back_to_top'
        );
        
        // 检测当前提交的是哪个标签页
        $submitted_fields = array_keys($input);
        
        // 只更新当前标签页的复选框
        foreach ($checkbox_fields as $field) {
            // 如果这个字段在提交的数据中被提及（即使是未选中），则更新它
            // 通过检查相关字段是否在同一表单中来判断
            if ($this->is_field_in_current_tab($field, $submitted_fields)) {
                $sanitized[$field] = isset($input[$field]) ? 1 : 0;
            }
            // 否则保持原值
        }
        
        return $sanitized;
    }
}

// 初始化
new Folio_Theme_Options();

/**
 * AJAX处理：刷新重写规则
 */
add_action('wp_ajax_folio_flush_rewrite_rules', function() {
    check_ajax_referer('folio_flush_rewrite', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Insufficient permissions', 'folio')));
    }
    
    // 刷新重写规则
    flush_rewrite_rules();
    
    // 重置标记
    delete_option('folio_category_prefix_flushed');
    
    wp_send_json_success(array('message' => __('Rewrite rules refreshed', 'folio')));
});


/**
 * 添加设置快捷链接到管理栏
 */
function folio_add_admin_bar_link($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $wp_admin_bar->add_node(array(
        'id'    => 'folio-theme-options',
        'title' => '<span class="ab-icon dashicons dashicons-admin-generic"></span> ' . esc_html__('Folio Settings', 'folio'),
        'href'  => admin_url('themes.php?page=folio-theme-options'),
        'meta'  => array(
            'title' => __('Folio Theme Settings', 'folio'),
        ),
    ));
}
add_action('admin_bar_menu', 'folio_add_admin_bar_link', 100);


