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
            'i18n' => array(
                'ai_response' => __('AI Response', 'folio'),
                'api_config_prefix' => __('API Config #', 'folio'),
                'api_test_details' => __('API test details:', 'folio'),
                'cannot_open_media_uploader' => __('Unable to open media uploader. Please refresh and try again.', 'folio'),
                'connected' => __('Connected', 'folio'),
                'connection_failed' => __('Connection failed', 'folio'),
                'error_message' => __('Error', 'folio'),
                'fill_required_fields' => __('Please fill all required fields', 'folio'),
                'icon' => __('Icon', 'folio'),
                'media_uploader_not_loaded' => __('Media uploader is not loaded. Please refresh the page and try again.', 'folio'),
                'model' => __('Model', 'folio'),
                'no_response_content' => __('No response content', 'folio'),
                'not_set' => __('Not set', 'folio'),
                'preview' => __('Preview', 'folio'),
                'remove_prefix' => __('Remove ', 'folio'),
                'replace_prefix' => __('Replace ', 'folio'),
                'request_failed_retry' => __('Request failed, please try again', 'folio'),
                'select_image' => __('Select Image', 'folio'),
                'select_prefix' => __('Select ', 'folio'),
                'selection' => __('Selection', 'folio'),
                'settings_saved' => __('Settings saved!', 'folio'),
                'status' => __('Status', 'folio'),
                'test_connection' => __('Test Connection', 'folio'),
                'testing' => __('Testing...', 'folio'),
                'testing_all_api_connections' => __('Testing all API connections...', 'folio'),
                'unknown_error' => __('Unknown error', 'folio'),
                'use_image' => __('Use this image', 'folio'),
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
        // 处理清除维护日志
        if (isset($_GET['action']) && $_GET['action'] === 'clear_maintenance_logs' && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'clear_maintenance_logs')) {
                delete_option('folio_maintenance_logs');
                wp_redirect(admin_url('themes.php?page=folio-theme-options&tab=advanced&cleared=1'));
                exit;
            }
        }

        $options = get_option($this->option_name, array());
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
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
            
            <form method="post" action="options.php">
                <?php settings_fields('folio_theme_options_group'); ?>
                
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
                
                <?php submit_button(__('Save Settings', 'folio'), 'primary large'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * 常规设置标签
     */
    private function render_general_tab($options) {
        ?>
        <div class="folio-tab-content">
            <div class="folio-section">
                <h2><?php esc_html_e('Site Identity', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Site Logo', 'folio'); ?></th>
                        <td>
                            <?php
                            $logo_url = isset($options['site_logo']) ? esc_url($options['site_logo']) : '';
                            ?>
                            <div class="folio-image-upload-wrapper">
                                <div class="folio-image-preview" style="margin-bottom: 10px;">
                                    <?php if ($logo_url) : ?>
                                        <img src="<?php echo esc_url($logo_url); ?>" style="max-width: 200px; height: auto; display: block; margin-bottom: 10px;" alt="<?php echo esc_attr__('Logo Preview', 'folio'); ?>">
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[site_logo]" id="site_logo" value="<?php echo esc_attr($logo_url); ?>">
                                <button type="button" class="button folio-upload-image-button" data-target="site_logo">
                                    <?php echo $logo_url ? esc_html__('Replace Logo', 'folio') : esc_html__('Select Logo', 'folio'); ?>
                                </button>
                                <?php if ($logo_url) : ?>
                                    <button type="button" class="button folio-remove-image-button" data-target="site_logo" style="margin-left: 10px;">
                                        <?php esc_html_e('Remove Logo', 'folio'); ?>
                                    </button>
                                <?php endif; ?>
                                <p class="description"><?php esc_html_e('Upload a site logo image. If not set, the site title will be displayed.', 'folio'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Site Favicon', 'folio'); ?></th>
                        <td>
                            <?php
                            $favicon_url = isset($options['site_favicon']) ? esc_url($options['site_favicon']) : '';
                            ?>
                            <div class="folio-image-upload-wrapper">
                                <div class="folio-image-preview" style="margin-bottom: 10px;">
                                    <?php if ($favicon_url) : ?>
                                        <img src="<?php echo esc_url($favicon_url); ?>" style="max-width: 32px; height: 32px; display: block; margin-bottom: 10px;" alt="<?php echo esc_attr__('Favicon Preview', 'folio'); ?>">
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[site_favicon]" id="site_favicon" value="<?php echo esc_attr($favicon_url); ?>">
                                <button type="button" class="button folio-upload-image-button" data-target="site_favicon">
                                    <?php echo $favicon_url ? esc_html__('Replace Icon', 'folio') : esc_html__('Select Icon', 'folio'); ?>
                                </button>
                                <?php if ($favicon_url) : ?>
                                    <button type="button" class="button folio-remove-image-button" data-target="site_favicon" style="margin-left: 10px;">
                                        <?php esc_html_e('Remove Icon', 'folio'); ?>
                                    </button>
                                <?php endif; ?>
                                <p class="description"><?php esc_html_e('Upload a site favicon (recommended size: 32x32px or 16x16px).', 'folio'); ?></p>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Theme Mode', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Default Theme Mode', 'folio'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr($this->option_name); ?>[theme_mode]" id="theme-mode-select">
                                <?php 
                                $current_theme_mode = isset($options['theme_mode']) ? $options['theme_mode'] : 'auto';
                                ?>
                                <option value="auto" <?php selected($current_theme_mode, 'auto'); ?>><?php esc_html_e('Auto (based on sunrise/sunset)', 'folio'); ?></option>
                                <option value="light" <?php selected($current_theme_mode, 'light'); ?>><?php esc_html_e('Light Mode', 'folio'); ?></option>
                                <option value="dark" <?php selected($current_theme_mode, 'dark'); ?>><?php esc_html_e('Dark Mode', 'folio'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose the default display mode. In "Auto", the theme switches between light/dark based on sunrise and sunset times.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr class="auto-sunset-settings" style="<?php echo ($current_theme_mode === 'auto') ? '' : 'display:none;'; ?>">
                        <th scope="row"><?php esc_html_e('Sunrise Time', 'folio'); ?></th>
                        <td>
                            <input type="time" name="<?php echo esc_attr($this->option_name); ?>[sunrise_time]" value="<?php echo esc_attr(isset($options['sunrise_time']) ? $options['sunrise_time'] : '06:00'); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Set sunrise time (24h). Theme switches to light mode after sunrise.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr class="auto-sunset-settings" style="<?php echo ($current_theme_mode === 'auto') ? '' : 'display:none;'; ?>">
                        <th scope="row"><?php esc_html_e('Sunset Time', 'folio'); ?></th>
                        <td>
                            <input type="time" name="<?php echo esc_attr($this->option_name); ?>[sunset_time]" value="<?php echo esc_attr(isset($options['sunset_time']) ? $options['sunset_time'] : '18:00'); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Set sunset time (24h). Theme switches to dark mode after sunset.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Show Theme Toggle', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_theme_toggle]" value="1" <?php checked(!isset($options['show_theme_toggle']) || !empty($options['show_theme_toggle']), true); ?>>
                                <?php esc_html_e('Allow visitors to switch light/dark mode manually', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Display a theme toggle in the header so visitors can switch modes manually.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <script>
                    jQuery(document).ready(function($) {
                        $('#theme-mode-select').on('change', function() {
                            if ($(this).val() === 'auto') {
                                $('.auto-sunset-settings').show();
                            } else {
                                $('.auto-sunset-settings').hide();
                            }
                        });
                    });
                    </script>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Content Display Settings', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('New Badge Days', 'folio'); ?></th>
                        <td>
                            <input type="number" name="<?php echo esc_attr($this->option_name); ?>[new_days]" value="<?php echo esc_attr(isset($options['new_days']) ? $options['new_days'] : 30); ?>" min="1" max="365" class="small-text">
                            <p class="description"><?php esc_html_e('Show the "New" badge for this many days after publish.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Posts Per Page', 'folio'); ?></th>
                        <td>
                            <input type="number" name="<?php echo esc_attr($this->option_name); ?>[posts_per_page]" value="<?php echo esc_attr(isset($options['posts_per_page']) ? $options['posts_per_page'] : 12); ?>" min="1" max="100" class="small-text">
                            <p class="description"><?php esc_html_e('Number of posts shown per archive page.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Show "Brain Powered Fun" Hexagon', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_brain_hexagon]" value="1" <?php checked(!isset($options['show_brain_hexagon']) || !empty($options['show_brain_hexagon']), true); ?>>
                                <?php esc_html_e('Show a decorative hexagon at the end of the homepage post grid.', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Footer Settings', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Copyright Text', 'folio'); ?></th>
                        <td>
                            <?php
                            // 如果为空，显示默认文本便于修改
                            $default_copyright = sprintf(
                                __('&copy; %s Design & build by %s.', 'folio'),
                                date('Y'),
                                get_bloginfo('name')
                            );
                            $copyright_value = isset($options['copyright']) && !empty($options['copyright']) 
                                ? $options['copyright'] 
                                : $default_copyright;
                            ?>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[copyright]" rows="3" class="large-text"><?php echo esc_textarea($copyright_value); ?></textarea>
                            <p class="description"><?php esc_html_e('Leave blank to use default text. HTML is supported.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * 生成图标选择下拉框
     */
    private function render_icon_select($field_name, $current_value = '', $platform = '') {
        $icon_library = folio_get_social_icon_library();
        $current_value = empty($current_value) ? 'default' : $current_value;
        
        echo '<select name="' . esc_attr($this->option_name) . '[' . esc_attr($field_name) . ']" style="width: 150px; margin-right: 10px;">';
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
        ?>
        <div class="folio-tab-content">
            <div class="folio-section">
                <h2><?php esc_html_e('Social Media Links', 'folio'); ?></h2>
                <p class="description"><?php esc_html_e('Configure social links and icons displayed in the sidebar.', 'folio'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><span class="dashicons dashicons-email"></span> <?php esc_html_e('Email Address', 'folio'); ?></th>
                        <td>
                            <?php $this->render_icon_select('email_icon', isset($options['email_icon']) ? $options['email_icon'] : '', 'email'); ?>
                            <input type="email" name="<?php echo esc_attr($this->option_name); ?>[email]" value="<?php echo esc_attr(isset($options['email']) ? $options['email'] : ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><span class="dashicons dashicons-instagram"></span> <?php esc_html_e('Instagram', 'folio'); ?></th>
                        <td>
                            <?php $this->render_icon_select('instagram_icon', isset($options['instagram_icon']) ? $options['instagram_icon'] : '', 'instagram'); ?>
                            <input type="url" name="<?php echo esc_attr($this->option_name); ?>[instagram]" value="<?php echo esc_attr(isset($options['instagram']) ? $options['instagram'] : ''); ?>" class="regular-text" placeholder="https://instagram.com/username">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><span class="dashicons dashicons-linkedin"></span> <?php esc_html_e('LinkedIn', 'folio'); ?></th>
                        <td>
                            <?php $this->render_icon_select('linkedin_icon', isset($options['linkedin_icon']) ? $options['linkedin_icon'] : '', 'linkedin'); ?>
                            <input type="url" name="<?php echo esc_attr($this->option_name); ?>[linkedin]" value="<?php echo esc_attr(isset($options['linkedin']) ? $options['linkedin'] : ''); ?>" class="regular-text" placeholder="https://linkedin.com/in/username">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><span class="dashicons dashicons-twitter"></span> <?php esc_html_e('Twitter/X', 'folio'); ?></th>
                        <td>
                            <?php $this->render_icon_select('twitter_icon', isset($options['twitter_icon']) ? $options['twitter_icon'] : '', 'twitter'); ?>
                            <input type="url" name="<?php echo esc_attr($this->option_name); ?>[twitter]" value="<?php echo esc_attr(isset($options['twitter']) ? $options['twitter'] : ''); ?>" class="regular-text" placeholder="https://twitter.com/username">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><span class="dashicons dashicons-facebook"></span> <?php esc_html_e('Facebook', 'folio'); ?></th>
                        <td>
                            <?php $this->render_icon_select('facebook_icon', isset($options['facebook_icon']) ? $options['facebook_icon'] : '', 'facebook'); ?>
                            <input type="url" name="<?php echo esc_attr($this->option_name); ?>[facebook]" value="<?php echo esc_attr(isset($options['facebook']) ? $options['facebook'] : ''); ?>" class="regular-text" placeholder="https://facebook.com/username">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><span class="dashicons dashicons-admin-site"></span> <?php esc_html_e('GitHub', 'folio'); ?></th>
                        <td>
                            <?php $this->render_icon_select('github_icon', isset($options['github_icon']) ? $options['github_icon'] : '', 'github'); ?>
                            <input type="url" name="<?php echo esc_attr($this->option_name); ?>[github]" value="<?php echo esc_attr(isset($options['github']) ? $options['github'] : ''); ?>" class="regular-text" placeholder="https://github.com/username">
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * SEO优化标签
     */
    private function render_seo_tab($options) {
        ?>
        <div class="folio-tab-content">
            <div class="folio-section">
                <h2><?php esc_html_e('Basic SEO Settings', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Homepage Title', 'folio'); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr($this->option_name); ?>[seo_title]" value="<?php echo esc_attr(isset($options['seo_title']) ? $options['seo_title'] : ''); ?>" class="large-text">
                            <p class="description"><?php esc_html_e('Custom homepage SEO title. Leave blank to use default.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Homepage Keywords', 'folio'); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr($this->option_name); ?>[seo_keywords]" value="<?php echo esc_attr(isset($options['seo_keywords']) ? $options['seo_keywords'] : ''); ?>" class="large-text">
                            <p class="description"><?php esc_html_e('Separate multiple keywords with commas.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Homepage Description', 'folio'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[seo_description]" rows="3" class="large-text"><?php echo esc_textarea(isset($options['seo_description']) ? $options['seo_description'] : ''); ?></textarea>
                            <p class="description"><?php esc_html_e('Meta description for homepage, recommended length 150-160 characters.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Open Graph Settings', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Open Graph', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_og]" value="1" <?php checked(isset($options['enable_og']) ? $options['enable_og'] : 1, 1); ?>>
                                <?php esc_html_e('Optimize social sharing (Facebook, Twitter, etc.)', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * 性能优化标签
     */
    private function render_performance_tab($options) {
        // 获取前端优化配置
        $frontend_opt = isset($options['frontend_optimization']) ? $options['frontend_optimization'] : array();
        
        // 获取优化统计（如果前端优化器类存在）
        $stats = array();
        if (class_exists('folio_Frontend_Optimizer')) {
            $optimizer = new folio_Frontend_Optimizer();
            if (method_exists($optimizer, 'get_optimization_stats')) {
                $stats = $optimizer->get_optimization_stats();
            }
        }
        ?>
        <div class="folio-tab-content">
            <!-- 前端优化设置 -->
            <div class="folio-section">
                <h2><?php esc_html_e('Frontend Resource Optimization', 'folio'); ?></h2>
                <p class="description"><?php esc_html_e('Optimize CSS, JavaScript, and image assets to improve loading speed.', 'folio'); ?></p>
                
                <?php if (!empty($stats)) : ?>
                <div class="folio-optimization-stats" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3><?php esc_html_e('Optimization Stats', 'folio'); ?></h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div>
                            <h4><?php esc_html_e('CSS Files', 'folio'); ?></h4>
                            <p><?php esc_html_e('Original', 'folio'); ?>: <?php echo esc_html($stats['css']['original']); ?> <?php esc_html_e('items', 'folio'); ?></p>
                            <p><?php esc_html_e('Optimized', 'folio'); ?>: <?php echo esc_html($stats['css']['optimized']); ?> <?php esc_html_e('items', 'folio'); ?></p>
                            <p><?php esc_html_e('Savings', 'folio'); ?>: <?php echo esc_html($stats['css']['savings']); ?>%</p>
                        </div>
                        <div>
                            <h4><?php esc_html_e('JavaScript Files', 'folio'); ?></h4>
                            <p><?php esc_html_e('Original', 'folio'); ?>: <?php echo esc_html($stats['js']['original']); ?> <?php esc_html_e('items', 'folio'); ?></p>
                            <p><?php esc_html_e('Optimized', 'folio'); ?>: <?php echo esc_html($stats['js']['optimized']); ?> <?php esc_html_e('items', 'folio'); ?></p>
                            <p><?php esc_html_e('Savings', 'folio'); ?>: <?php echo esc_html($stats['js']['savings']); ?>%</p>
                        </div>
                        <div>
                            <h4><?php esc_html_e('Image Optimization', 'folio'); ?></h4>
                            <p><?php esc_html_e('Lazy Loaded', 'folio'); ?>: <?php echo esc_html($stats['images']['lazy_loaded']); ?> <?php esc_html_e('images', 'folio'); ?></p>
                            <p><?php esc_html_e('WebP Support', 'folio'); ?>: <?php echo $stats['images']['webp_support'] ? esc_html__('Yes', 'folio') : esc_html__('No', 'folio'); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Frontend Optimization', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][enabled]" value="1" <?php checked(isset($frontend_opt['enabled']) ? $frontend_opt['enabled'] : true, true); ?>>
                                <?php esc_html_e('Enable all frontend optimization features', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('CSS Optimization', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][minify_css]" value="1" <?php checked(isset($frontend_opt['minify_css']) ? $frontend_opt['minify_css'] : true, true); ?>>
                                <?php esc_html_e('Minify CSS files', 'folio'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][combine_css]" value="1" <?php checked(isset($frontend_opt['combine_css']) ? $frontend_opt['combine_css'] : false, true); ?>>
                                <?php esc_html_e('Combine CSS files', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Note: Combining CSS may cause FOUC (flash of unstyled content). Use with caution.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('JavaScript Optimization', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][minify_js]" value="1" <?php checked(isset($frontend_opt['minify_js']) ? $frontend_opt['minify_js'] : true, true); ?>>
                                <?php esc_html_e('Minify JavaScript files', 'folio'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][combine_js]" value="1" <?php checked(isset($frontend_opt['combine_js']) ? $frontend_opt['combine_js'] : false, true); ?>>
                                <?php esc_html_e('Combine JavaScript files', 'folio'); ?> <span style="color: #d63638;"><?php esc_html_e('(Experimental)', 'folio'); ?></span>
                            </label>
                            <p class="description"><?php esc_html_e('Note: Combining JS may cause compatibility issues. Use with caution.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Image Optimization', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][lazy_load_images]" value="1" <?php checked(isset($frontend_opt['lazy_load_images']) ? $frontend_opt['lazy_load_images'] : true, true); ?>>
                                <?php esc_html_e('Enable image lazy loading', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Load images only when they enter the viewport to improve page speed.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Font Optimization', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][optimize_fonts]" value="1" <?php checked(isset($frontend_opt['optimize_fonts']) ? $frontend_opt['optimize_fonts'] : true, true); ?>>
                                <?php esc_html_e('Optimize font loading', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Optimize font loading strategy to reduce render-blocking.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Resource Loading', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][preload_critical]" value="1" <?php checked(isset($frontend_opt['preload_critical']) ? $frontend_opt['preload_critical'] : true, true); ?>>
                                <?php esc_html_e('Preload critical resources', 'folio'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][defer_non_critical]" value="1" <?php checked(isset($frontend_opt['defer_non_critical']) ? $frontend_opt['defer_non_critical'] : false, true); ?>>
                                <?php esc_html_e('Defer non-critical resources', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Note: Deferring resources may cause FOUC. Use with caution.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Caching and Compression', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][cache_busting]" value="1" <?php checked(isset($frontend_opt['cache_busting']) ? $frontend_opt['cache_busting'] : true, true); ?>>
                                <?php esc_html_e('Enable cache busting', 'folio'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][enable_gzip]" value="1" <?php checked(isset($frontend_opt['enable_gzip']) ? $frontend_opt['enable_gzip'] : true, true); ?>>
                                <?php esc_html_e('Enable Gzip compression', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('HTML Minification', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][minify_html]" value="1" <?php checked(isset($frontend_opt['minify_html']) ? $frontend_opt['minify_html'] : false, true); ?>>
                                <?php esc_html_e('Minify HTML output', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Remove whitespace in HTML output to reduce page size.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div class="folio-optimization-actions" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <h3><?php esc_html_e('Optimization Actions', 'folio'); ?></h3>
                    <p>
                        <button type="button" class="button button-primary" id="folio-optimize-assets">
                            <span class="dashicons dashicons-performance" style="margin-top: 3px;"></span>
                            <?php esc_html_e('Re-optimize Assets', 'folio'); ?>
                        </button>
                        <button type="button" class="button" id="folio-clear-optimized-cache">
                            <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
                            <?php esc_html_e('Clear Optimized Cache', 'folio'); ?>
                        </button>
                    </p>
                    <div id="folio-optimization-result" style="margin-top: 10px;"></div>
                </div>
            </div>
            
            <!-- 基础性能设置 -->
            <div class="folio-section">
                <h2><?php esc_html_e('Basic Performance Settings', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Disable Emoji', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_emoji]" value="1" <?php checked(isset($options['disable_emoji']) ? $options['disable_emoji'] : 0, 1); ?>>
                                <?php esc_html_e('Disable default WordPress Emoji scripts', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- 缓存设置 -->
            <div class="folio-section">
                <h2><?php esc_html_e('Cache Settings', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Browser Cache Duration', 'folio'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr($this->option_name); ?>[cache_time]">
                                <option value="3600" <?php selected(isset($options['cache_time']) ? $options['cache_time'] : '86400', '3600'); ?>><?php esc_html_e('1 Hour', 'folio'); ?></option>
                                <option value="86400" <?php selected(isset($options['cache_time']) ? $options['cache_time'] : '86400', '86400'); ?>><?php esc_html_e('1 Day', 'folio'); ?></option>
                                <option value="604800" <?php selected(isset($options['cache_time']) ? $options['cache_time'] : '86400', '604800'); ?>><?php esc_html_e('7 Days', 'folio'); ?></option>
                                <option value="2592000" <?php selected(isset($options['cache_time']) ? $options['cache_time'] : '86400', '2592000'); ?>><?php esc_html_e('30 Days', 'folio'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Set browser cache duration for static assets.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const optimizeBtn = document.getElementById('folio-optimize-assets');
            const clearBtn = document.getElementById('folio-clear-optimized-cache');
            const resultDiv = document.getElementById('folio-optimization-result');
            
            if (optimizeBtn) {
                optimizeBtn.addEventListener('click', function() {
                    const btn = this;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="dashicons dashicons-update" style="margin-top: 3px; animation: spin 1s linear infinite;"></span> <?php echo esc_js(__('Optimizing...', 'folio')); ?>';
                    resultDiv.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span> <?php echo esc_js(__('Optimizing assets...', 'folio')); ?>';
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=folio_optimize_assets&nonce=<?php echo wp_create_nonce('folio_optimize_assets'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-performance" style="margin-top: 3px;"></span> <?php echo esc_js(__('Re-optimize Assets', 'folio')); ?>';
                        if (data.success) {
                            resultDiv.innerHTML = '<div class="notice notice-success inline"><p>✓ <?php echo esc_js(__('Asset optimization completed!', 'folio')); ?></p></div>';
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            resultDiv.innerHTML = '<div class="notice notice-error inline"><p>✗ <?php echo esc_js(__('Optimization failed:', 'folio')); ?> ' + (data.data || '<?php echo esc_js(__('Unknown error', 'folio')); ?>') + '</p></div>';
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-performance" style="margin-top: 3px;"></span> <?php echo esc_js(__('Re-optimize Assets', 'folio')); ?>';
                        resultDiv.innerHTML = '<div class="notice notice-error inline"><p>✗ <?php echo esc_js(__('Request failed', 'folio')); ?></p></div>';
                    });
                });
            }
            
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    if (!confirm('<?php echo esc_js(__('Are you sure you want to clear optimized cache?', 'folio')); ?>')) {
                        return;
                    }
                    
                    const btn = this;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="dashicons dashicons-update" style="margin-top: 3px; animation: spin 1s linear infinite;"></span> <?php echo esc_js(__('Clearing...', 'folio')); ?>';
                    resultDiv.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span> <?php echo esc_js(__('Clearing cache...', 'folio')); ?>';
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=folio_clear_optimized_cache&nonce=<?php echo wp_create_nonce('folio_clear_optimized_cache'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> <?php echo esc_js(__('Clear Optimized Cache', 'folio')); ?>';
                        if (data.success) {
                            resultDiv.innerHTML = '<div class="notice notice-success inline"><p>✓ <?php echo esc_js(__('Cache cleared!', 'folio')); ?></p></div>';
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            resultDiv.innerHTML = '<div class="notice notice-error inline"><p>✗ <?php echo esc_js(__('Clear failed:', 'folio')); ?> ' + (data.data || '<?php echo esc_js(__('Unknown error', 'folio')); ?>') + '</p></div>';
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> <?php echo esc_js(__('Clear Optimized Cache', 'folio')); ?>';
                        resultDiv.innerHTML = '<div class="notice notice-error inline"><p>✗ <?php echo esc_js(__('Request failed', 'folio')); ?></p></div>';
                    });
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * 功能优化标签
     */
    private function render_optimize_tab($options) {
        ?>
        <div class="folio-tab-content">
            <div class="folio-section">
                <h2><?php esc_html_e('URL Optimization', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Remove Category Prefix', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_category_prefix]" value="1" <?php checked(isset($options['remove_category_prefix']) ? $options['remove_category_prefix'] : 1, 1); ?>>
                                <?php esc_html_e('Remove /category/ prefix from category URLs', 'folio'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Example: /category/design/ -> /design/', 'folio'); ?><br>
                                <strong style="color: #d63638;"><?php esc_html_e('Important:', 'folio'); ?></strong>
                                <?php esc_html_e('After enabling, complete the following steps:', 'folio'); ?><br>
                                1. <?php esc_html_e('Save this setting', 'folio'); ?><br>
                                2. <?php esc_html_e('Go to "Settings > Permalinks"', 'folio'); ?><br>
                                3. <?php esc_html_e('Click "Save Changes"', 'folio'); ?><br>
                                4. <?php esc_html_e('Or click the button below to refresh rewrite rules', 'folio'); ?>
                            </p>
                            <button type="button" class="button" id="flush-rewrite-rules">
                                <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                                <?php esc_html_e('Refresh Rewrite Rules', 'folio'); ?>
                            </button>
                            <span id="flush-result" style="margin-left: 10px;"></span>
                            
                            <script>
                            document.getElementById('flush-rewrite-rules').addEventListener('click', function() {
                                var btn = this;
                                var result = document.getElementById('flush-result');
                                
                                btn.disabled = true;
                                result.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span>';
                                
                                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: 'action=folio_flush_rewrite_rules&nonce=<?php echo wp_create_nonce('folio_flush_rewrite'); ?>'
                                })
                                .then(response => response.json())
                                .then(data => {
                                    btn.disabled = false;
                                    if (data.success) {
                                        result.innerHTML = '<span style="color: #28a745;">✓ ' + data.data.message + '</span>';
                                    } else {
                                        result.innerHTML = '<span style="color: #dc3545;">✗ ' + data.data.message + '</span>';
                                    }
                                    setTimeout(function() {
                                        result.innerHTML = '';
                                    }, 3000);
                                })
                                .catch(error => {
                                    btn.disabled = false;
                                    result.innerHTML = '<span style="color: #dc3545;">✗ <?php echo esc_js(__('Request failed', 'folio')); ?></span>';
                                });
                            });
                            </script>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Content Optimization', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto Featured Image', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[auto_featured_image]" value="1" <?php checked(isset($options['auto_featured_image']) ? $options['auto_featured_image'] : 1, 1); ?>>
                                <?php esc_html_e('Automatically set the first image in post as featured image', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('When saving a post without featured image, use the first image in content automatically.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Search Optimization', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[search_title_only]" value="1" <?php checked(isset($options['search_title_only']) ? $options['search_title_only'] : 1, 1); ?>>
                                <?php esc_html_e('Search by post title only (improves search speed)', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Clean Image HTML', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[clean_image_html]" value="1" <?php checked(isset($options['clean_image_html']) ? $options['clean_image_html'] : 1, 1); ?>>
                                <?php esc_html_e('Remove redundant HTML attributes from inserted images', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Remove width, height, class, srcset, etc. Keep only src and alt.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Editor Settings', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Post Editor', 'folio'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr($this->option_name); ?>[editor_type]">
                                <option value="gutenberg" <?php selected(isset($options['editor_type']) ? $options['editor_type'] : 'gutenberg', 'gutenberg'); ?>>
                                    <?php esc_html_e('Gutenberg Editor (Block Editor)', 'folio'); ?>
                                </option>
                                <option value="classic" <?php selected(isset($options['editor_type']) ? $options['editor_type'] : 'gutenberg', 'classic'); ?>>
                                    <?php esc_html_e('Classic Editor (TinyMCE)', 'folio'); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose which editor is used for post editing.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Widget Editor', 'folio'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr($this->option_name); ?>[widget_editor_type]">
                                <option value="gutenberg" <?php selected(isset($options['widget_editor_type']) ? $options['widget_editor_type'] : 'gutenberg', 'gutenberg'); ?>>
                                    <?php esc_html_e('Gutenberg Widgets (Block Editor)', 'folio'); ?>
                                </option>
                                <option value="classic" <?php selected(isset($options['widget_editor_type']) ? $options['widget_editor_type'] : 'gutenberg', 'classic'); ?>>
                                    <?php esc_html_e('Classic Widgets', 'folio'); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose which editor is used for widget management.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Admin Optimization', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Remove Top Logo', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_admin_bar_logo]" value="1" <?php checked(isset($options['remove_admin_bar_logo']) ? $options['remove_admin_bar_logo'] : 1, 1); ?>>
                                <?php esc_html_e('Remove WordPress logo from admin bar', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Frontend Admin Bar', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[hide_admin_bar_frontend]" value="1" <?php checked(isset($options['hide_admin_bar_frontend']) ? $options['hide_admin_bar_frontend'] : 0, 1); ?>>
                                <?php esc_html_e('Hide frontend admin bar', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Do not show top admin bar on frontend even when logged in.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Database Optimization', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Post Revisions', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_post_revisions]" value="1" <?php checked(isset($options['disable_post_revisions']) ? $options['disable_post_revisions'] : 0, 1); ?>>
                                <?php esc_html_e('Disable post revisions completely', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Reduce database load, but you cannot restore old revisions.', 'folio'); ?></p>
                            
                            <div style="margin-top: 10px;">
                                <label><?php esc_html_e('Or limit revisions count:', 'folio'); ?></label>
                                <input type="number" name="<?php echo esc_attr($this->option_name); ?>[post_revisions_limit]" value="<?php echo esc_attr(isset($options['post_revisions_limit']) ? $options['post_revisions_limit'] : 0); ?>" min="0" max="50" class="small-text">
                                <span class="description"><?php esc_html_e('items (0 = unlimited)', 'folio'); ?></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Autosave', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_autosave]" value="1" <?php checked(isset($options['disable_autosave']) ? $options['disable_autosave'] : 0, 1); ?>>
                                <?php esc_html_e('Disable editor autosave', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Reduce database writes, but unsaved content may be lost.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Script Optimization', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('jQuery Migrate', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_jquery_migrate]" value="1" <?php checked(isset($options['remove_jquery_migrate']) ? $options['remove_jquery_migrate'] : 1, 1); ?>>
                                <?php esc_html_e('Remove jQuery Migrate script', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Reduce JS loading, but old plugin compatibility may be affected.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Dashicons', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_dashicons_frontend]" value="1" <?php checked(isset($options['disable_dashicons_frontend']) ? $options['disable_dashicons_frontend'] : 1, 1); ?>>
                                <?php esc_html_e('Disable Dashicons on frontend (for logged-out users)', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Reduce CSS loading when frontend does not use Dashicons.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Performance Monitoring', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_performance_monitor]" value="1" <?php checked(isset($options['enable_performance_monitor']) ? $options['enable_performance_monitor'] : 0, 1); ?>>
                                <?php esc_html_e('Enable frontend performance monitoring', 'folio'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Monitor frontend performance metrics (load time, memory usage, DB queries) and provide optimization suggestions. Visible to admins on frontend only.', 'folio'); ?><br>
                                <strong><?php esc_html_e('Shortcuts:', 'folio'); ?></strong>
                                <code>Ctrl+Shift+P</code> <?php esc_html_e('Show/Hide toolbar', 'folio'); ?>,
                                <code>Ctrl+Double Click</code> <?php esc_html_e('Show again', 'folio'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Heartbeat API', 'folio'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr($this->option_name); ?>[heartbeat_mode]">
                                <option value="default" <?php selected(isset($options['heartbeat_mode']) ? $options['heartbeat_mode'] : 'default', 'default'); ?>>
                                    <?php esc_html_e('Default (15s)', 'folio'); ?>
                                </option>
                                <option value="reduce" <?php selected(isset($options['heartbeat_mode']) ? $options['heartbeat_mode'] : 'default', 'reduce'); ?>>
                                    <?php esc_html_e('Reduced frequency (60s)', 'folio'); ?>
                                </option>
                                <option value="disable" <?php selected(isset($options['heartbeat_mode']) ? $options['heartbeat_mode'] : 'default', 'disable'); ?>>
                                    <?php esc_html_e('Disable completely', 'folio'); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('Heartbeat is used for autosave and real-time notifications. Disabling it reduces server load.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Google Fonts', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_google_fonts]" value="1" <?php checked(isset($options['disable_google_fonts']) ? $options['disable_google_fonts'] : 0, 1); ?>>
                                <?php esc_html_e('Disable Google Fonts loading', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Improve regional access speed, but may affect font rendering.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Version Strings', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_version_strings]" value="1" <?php checked(isset($options['remove_version_strings']) ? $options['remove_version_strings'] : 1, 1); ?>>
                                <?php esc_html_e('Remove version query strings from CSS/JS files', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Hide version info for better security.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Image Optimization', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto Compression', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_image_compression]" value="1" <?php checked(isset($options['enable_image_compression']) ? $options['enable_image_compression'] : 1, 1); ?>>
                                <?php esc_html_e('Enable automatic image compression', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Automatically compress images on upload to reduce file size.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('JPEG Quality', 'folio'); ?></th>
                        <td>
                            <input type="range" name="<?php echo esc_attr($this->option_name); ?>[jpeg_quality]" value="<?php echo esc_attr(isset($options['jpeg_quality']) ? $options['jpeg_quality'] : 85); ?>" min="60" max="100" step="5" oninput="this.nextElementSibling.textContent = this.value + '%'">
                            <span><?php echo esc_html(isset($options['jpeg_quality']) ? $options['jpeg_quality'] : 85); ?>%</span>
                            <p class="description"><?php esc_html_e('JPEG compression quality (60-100%, recommended 85%).', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('PNG Quality', 'folio'); ?></th>
                        <td>
                            <input type="range" name="<?php echo esc_attr($this->option_name); ?>[png_quality]" value="<?php echo esc_attr(isset($options['png_quality']) ? $options['png_quality'] : 90); ?>" min="70" max="100" step="5" oninput="this.nextElementSibling.textContent = this.value + '%'">
                            <span><?php echo esc_html(isset($options['png_quality']) ? $options['png_quality'] : 90); ?>%</span>
                            <p class="description"><?php esc_html_e('PNG compression quality (70-100%, recommended 90%).', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Maximum Size', 'folio'); ?></th>
                        <td>
                            <label>
                                <?php esc_html_e('Width:', 'folio'); ?>
                                <input type="number" name="<?php echo esc_attr($this->option_name); ?>[max_image_width]" value="<?php echo esc_attr(isset($options['max_image_width']) ? $options['max_image_width'] : 2048); ?>" min="800" max="4000" step="100" class="small-text">px
                            </label>
                            <label style="margin-left: 20px;">
                                <?php esc_html_e('Height:', 'folio'); ?>
                                <input type="number" name="<?php echo esc_attr($this->option_name); ?>[max_image_height]" value="<?php echo esc_attr(isset($options['max_image_height']) ? $options['max_image_height'] : 2048); ?>" min="600" max="4000" step="100" class="small-text">px
                            </label>
                            <p class="description"><?php esc_html_e('Images larger than this will be auto-resized.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('WebP Support', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_webp_conversion]" value="1" <?php checked(isset($options['enable_webp_conversion']) ? $options['enable_webp_conversion'] : 0, 1); ?>>
                                <?php esc_html_e('Enable WebP format support', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Allow uploading WebP images (server support required).', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Metadata', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[strip_image_metadata]" value="1" <?php checked(isset($options['strip_image_metadata']) ? $options['strip_image_metadata'] : 1, 1); ?>>
                                <?php esc_html_e('Remove image EXIF metadata', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Remove camera/GPS privacy data and reduce file size.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Other Optimizations', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Comment Styles', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_comment_styles]" value="1" <?php checked(isset($options['remove_comment_styles']) ? $options['remove_comment_styles'] : 0, 1); ?>>
                                <?php esc_html_e('Remove comment-related styles', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('If comments are disabled, related CSS can be removed.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Update Email Notifications', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_update_emails]" value="1" <?php checked(isset($options['disable_update_emails']) ? $options['disable_update_emails'] : 1, 1); ?>>
                                <?php esc_html_e('Disable auto-update email notifications', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Stop receiving WordPress/plugin/theme update emails.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Shortcode Paragraphs', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_shortcode_p]" value="1" <?php checked(isset($options['remove_shortcode_p']) ? $options['remove_shortcode_p'] : 1, 1); ?>>
                                <?php esc_html_e('Remove automatic paragraph tags around shortcodes', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Avoid formatting issues in shortcode output.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Security Settings', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Restrict REST API', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[restrict_rest_api]" value="1" <?php checked(isset($options['restrict_rest_api']) ? $options['restrict_rest_api'] : 1, 1); ?>>
                                <?php esc_html_e('Disable REST API for logged-out users', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Improve security and reduce data exposure (still available for logged-in users).', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Disable XML-RPC Pingback', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_xmlrpc_pingback]" value="1" <?php checked(isset($options['disable_xmlrpc_pingback']) ? $options['disable_xmlrpc_pingback'] : 1, 1); ?>>
                                <?php esc_html_e('Turn off XML-RPC Pingback functionality', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Help prevent DDoS abuse and improve security.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * 功能增强标签
     */
    private function render_enhancements_tab($options) {
        ?>
        <div class="folio-tab-content">
            <div class="folio-section">
                <h2><?php esc_html_e('UX Enhancements', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Image Lazy Load', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_lazy_load]" value="1" <?php checked(isset($options['enable_lazy_load']) ? $options['enable_lazy_load'] : 1, 1); ?>>
                                <?php esc_html_e('Enable image lazy loading', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Load images only when visible to improve page speed.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Back to Top Button', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_back_to_top]" value="1" <?php checked(isset($options['enable_back_to_top']) ? $options['enable_back_to_top'] : 1, 1); ?>>
                                <?php esc_html_e('Show back-to-top button', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Show after scrolling 300px; click to smoothly scroll to top.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Navigation and Reading', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Breadcrumbs', 'folio'); ?></th>
                        <td>
                            <p class="description">
                                <?php esc_html_e('Breadcrumbs are enabled. Use this code in templates:', 'folio'); ?><br>
                                <code>&lt;?php folio_breadcrumbs(); ?&gt;</code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Reading Time Estimate', 'folio'); ?></th>
                        <td>
                            <p class="description">
                                <?php esc_html_e('Reading time estimation is enabled. Use this code in templates:', 'folio'); ?><br>
                                <code>&lt;?php folio_reading_time(); ?&gt;</code><br>
                                <?php esc_html_e('Or get minutes value:', 'folio'); ?><br>
                                <code>&lt;?php $minutes = folio_get_reading_time(); ?&gt;</code>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Usage Guide', 'folio'); ?></h2>
                <div style="background: #f0f0f1; padding: 15px; border-radius: 4px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('How to use these features', 'folio'); ?></h3>
                    
                    <h4>1. <?php esc_html_e('Image Lazy Load', 'folio'); ?></h4>
                    <p><?php esc_html_e('Lazy loading is applied automatically to images.', 'folio'); ?></p>
                    
                    <h4>2. <?php esc_html_e('Back to Top Button', 'folio'); ?></h4>
                    <p><?php esc_html_e('Shown automatically at the bottom-right of pages.', 'folio'); ?></p>
                    
                    <h4>3. <?php esc_html_e('Breadcrumbs', 'folio'); ?></h4>
                    <p><?php esc_html_e('Add this where breadcrumbs should appear (e.g., post header):', 'folio'); ?></p>
                    <pre style="background: #fff; padding: 10px; border-radius: 4px; overflow-x: auto;">&lt;?php if (function_exists('folio_breadcrumbs')) folio_breadcrumbs(); ?&gt;</pre>
                    
                    <h4>4. <?php esc_html_e('Reading Time', 'folio'); ?></h4>
                    <p><?php esc_html_e('Add this in post meta area:', 'folio'); ?></p>
                    <pre style="background: #fff; padding: 10px; border-radius: 4px; overflow-x: auto;">&lt;?php if (function_exists('folio_reading_time')) folio_reading_time(); ?&gt;</pre>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AI设置标签
     */
    private function render_ai_tab($options) {
        // 获取API配置列表
        $ai_apis = isset($options['ai_apis']) && is_array($options['ai_apis']) ? $options['ai_apis'] : array();
        
        // 兼容旧版本：如果有旧的单个API配置，转换为新格式
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
        
        // 如果没有API配置，添加一个空的
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
        ?>
        <div class="folio-tab-content">
            <div class="folio-section">
                <h2><?php esc_html_e('AI API Configuration', 'folio'); ?></h2>
                <p class="description"><?php esc_html_e('Configure multiple AI services for auto-generating summaries and keywords. Supports OpenAI-compatible APIs (OpenAI, Azure OpenAI, Qwen, ERNIE, etc.). The system automatically rotates available APIs.', 'folio'); ?></p>
                
                <div id="folio-ai-apis-container">
                    <?php foreach ($ai_apis as $index => $api) : ?>
                        <div class="folio-ai-api-item" data-index="<?php echo esc_attr($index); ?>">
                            <div class="folio-ai-api-header">
                                <h3><?php esc_html_e('API Config', 'folio'); ?> #<?php echo esc_html($index + 1); ?></h3>
                                <button type="button" class="button button-small folio-remove-api" style="color: #dc3232;"><?php esc_html_e('Delete', 'folio'); ?></button>
                            </div>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Config Name', 'folio'); ?></th>
                                    <td>
                                        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr(isset($api['name']) ? $api['name'] : ''); ?>" class="regular-text" placeholder="<?php esc_attr_e('Example: OpenAI Primary', 'folio'); ?>">
                                        <p class="description"><?php esc_html_e('Used to identify this API config.', 'folio'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enabled', 'folio'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][enabled]" value="1" <?php checked(isset($api['enabled']) ? $api['enabled'] : 1, 1); ?>>
                                            <?php esc_html_e('Enable this API config', 'folio'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('API Endpoint', 'folio'); ?></th>
                                    <td>
                                        <input type="url" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][endpoint]" value="<?php echo esc_attr(isset($api['endpoint']) ? $api['endpoint'] : ''); ?>" class="large-text" placeholder="https://api.openai.com/v1/chat/completions">
                                        <p class="description">
                                            <?php esc_html_e('API endpoint URL', 'folio'); ?><br>
                                            <strong>OpenAI:</strong> https://api.openai.com/v1/chat/completions<br>
                                            <strong>通义千问:</strong> https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions<br>
                                            <strong>文心一言:</strong> https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/completions
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('API Key', 'folio'); ?></th>
                                    <td>
                                        <input type="password" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr(isset($api['key']) ? $api['key'] : ''); ?>" class="large-text" placeholder="sk-...">
                                        <p class="description"><?php esc_html_e('Your API key', 'folio'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Model Name', 'folio'); ?></th>
                                    <td>
                                        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][model]" value="<?php echo esc_attr(isset($api['model']) ? $api['model'] : 'gpt-3.5-turbo'); ?>" class="regular-text" placeholder="gpt-3.5-turbo">
                                        <p class="description">
                                            <?php esc_html_e('AI model to use', 'folio'); ?><br>
                                            <strong>OpenAI:</strong> gpt-3.5-turbo, gpt-4<br>
                                            <strong>通义千问:</strong> qwen-turbo, qwen-plus<br>
                                            <strong>文心一言:</strong> ernie-bot-turbo, ernie-bot
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <p>
                    <button type="button" class="button" id="folio-add-api"><?php esc_html_e('+ Add API Config', 'folio'); ?></button>
                </p>
                
                <style>
                .folio-ai-api-item {
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .folio-ai-api-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 15px;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #ddd;
                }
                .folio-ai-api-header h3 {
                    margin: 0;
                    font-size: 14px;
                    font-weight: 600;
                }
                </style>
                
                <script>
                jQuery(document).ready(function($) {
                    var apiIndex = <?php echo count($ai_apis); ?>;
                    
                    // Add API config
                    $('#folio-add-api').on('click', function() {
                        var html = '<div class="folio-ai-api-item" data-index="' + apiIndex + '">' +
                            '<div class="folio-ai-api-header">' +
                            '<h3><?php esc_html_e('API Config', 'folio'); ?> #' + (apiIndex + 1) + '</h3>' +
                            '<button type="button" class="button button-small folio-remove-api" style="color: #dc3232;"><?php esc_html_e('Delete', 'folio'); ?></button>' +
                            '</div>' +
                            '<table class="form-table">' +
                            '<tr>' +
                            '<th scope="row"><?php esc_html_e('Config Name', 'folio'); ?></th>' +
                            '<td>' +
                            '<input type="text" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][' + apiIndex + '][name]" value="" class="regular-text" placeholder="<?php esc_attr_e('Example: OpenAI Primary', 'folio'); ?>">' +
                            '<p class="description"><?php esc_html_e('Used to identify this API config.', 'folio'); ?></p>' +
                            '</td>' +
                            '</tr>' +
                            '<tr>' +
                            '<th scope="row"><?php esc_html_e('Enabled', 'folio'); ?></th>' +
                            '<td>' +
                            '<label>' +
                            '<input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][' + apiIndex + '][enabled]" value="1" checked>' +
                            '<?php esc_html_e('Enable this API config', 'folio'); ?>' +
                            '</label>' +
                            '</td>' +
                            '</tr>' +
                            '<tr>' +
                            '<th scope="row"><?php esc_html_e('API Endpoint', 'folio'); ?></th>' +
                            '<td>' +
                            '<input type="url" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][' + apiIndex + '][endpoint]" value="" class="large-text" placeholder="https://api.openai.com/v1/chat/completions">' +
                            '<p class="description"><?php esc_html_e('API endpoint URL', 'folio'); ?></p>' +
                            '</td>' +
                            '</tr>' +
                            '<tr>' +
                            '<th scope="row"><?php esc_html_e('API Key', 'folio'); ?></th>' +
                            '<td>' +
                            '<input type="password" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][' + apiIndex + '][key]" value="" class="large-text" placeholder="sk-...">' +
                            '<p class="description"><?php esc_html_e('Your API key', 'folio'); ?></p>' +
                            '</td>' +
                            '</tr>' +
                            '<tr>' +
                            '<th scope="row"><?php esc_html_e('Model Name', 'folio'); ?></th>' +
                            '<td>' +
                            '<input type="text" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][' + apiIndex + '][model]" value="gpt-3.5-turbo" class="regular-text" placeholder="gpt-3.5-turbo">' +
                            '<p class="description"><?php esc_html_e('AI model to use', 'folio'); ?></p>' +
                            '</td>' +
                            '</tr>' +
                            '</table>' +
                            '</div>';
                        
                        $('#folio-ai-apis-container').append(html);
                        apiIndex++;
                    });
                    
                    // Delete API config
                    $(document).on('click', '.folio-remove-api', function() {
                        if (confirm('<?php esc_html_e('Are you sure you want to delete this API config?', 'folio'); ?>')) {
                            $(this).closest('.folio-ai-api-item').remove();
                        }
                    });
                });
                </script>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Feature Settings', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable AI Generation', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[ai_enabled]" value="1" <?php checked(isset($options['ai_enabled']) ? $options['ai_enabled'] : 1, 1); ?>>
                                <?php esc_html_e('Show AI generation panel on post editor page', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-generate by Default', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[ai_auto_generate_default]" value="1" <?php checked(isset($options['ai_auto_generate_default']) ? $options['ai_auto_generate_default'] : 0, 1); ?>>
                                <?php esc_html_e('Enable "auto-generate on save" by default for new posts', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Connection Test', 'folio'); ?></h2>
                <p class="description"><?php esc_html_e('After saving settings, test all enabled AI API connections. Each API config will be tested automatically.', 'folio'); ?></p>
                <button type="button" class="button" id="folio-test-ai-connection">
                    <?php esc_html_e('Test All APIs', 'folio'); ?>
                </button>
                <div id="folio-ai-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 高级设置标签
     */
    private function render_advanced_tab($options) {
        ?>
        <div class="folio-tab-content">
            <div class="folio-section">
                <h2><?php esc_html_e('Custom Code', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Custom CSS', 'folio'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[custom_css]" rows="10" class="large-text code"><?php echo esc_textarea(isset($options['custom_css']) ? $options['custom_css'] : ''); ?></textarea>
                            <p class="description"><?php esc_html_e('Add custom CSS without editing theme files.', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Header Code', 'folio'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[header_code]" rows="5" class="large-text code"><?php echo esc_textarea(isset($options['header_code']) ? $options['header_code'] : ''); ?></textarea>
                            <p class="description"><?php esc_html_e('Insert code inside &lt;head&gt; (e.g., Google Analytics).', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Footer Code', 'folio'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[footer_code]" rows="5" class="large-text code"><?php echo esc_textarea(isset($options['footer_code']) ? $options['footer_code'] : ''); ?></textarea>
                            <p class="description"><?php esc_html_e('Insert code before &lt;/body&gt;.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Maintenance Mode', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Maintenance Mode', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[maintenance_mode]" value="1" <?php checked(isset($options['maintenance_mode']) ? $options['maintenance_mode'] : 0, 1); ?>>
                                <?php esc_html_e('Show maintenance page to non-admin users', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Maintenance Message', 'folio'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[maintenance_message]" rows="3" class="large-text"><?php echo esc_textarea(isset($options['maintenance_message']) ? $options['maintenance_message'] : __('The site is under maintenance, please visit later.', 'folio')); ?></textarea>
                            <p class="description"><?php esc_html_e('Maintenance message displayed to visitors. HTML tags are supported.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Scheduled Maintenance', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Scheduled Maintenance', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[maintenance_scheduled]" value="1" <?php checked(isset($options['maintenance_scheduled']) ? $options['maintenance_scheduled'] : 0, 1); ?>>
                                <?php esc_html_e('Automatically enable/disable maintenance mode by schedule', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Start Time', 'folio'); ?></th>
                        <td>
                            <input type="datetime-local" name="<?php echo esc_attr($this->option_name); ?>[maintenance_start_time]" value="<?php echo esc_attr(isset($options['maintenance_start_time']) ? date('Y-m-d\TH:i', strtotime($options['maintenance_start_time'])) : ''); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Maintenance start time (optional, leave blank to start immediately).', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('End Time', 'folio'); ?></th>
                        <td>
                            <input type="datetime-local" name="<?php echo esc_attr($this->option_name); ?>[maintenance_end_time]" value="<?php echo esc_attr(isset($options['maintenance_end_time']) ? date('Y-m-d\TH:i', strtotime($options['maintenance_end_time'])) : ''); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Maintenance end time (optional, leave blank to end manually).', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Advanced Settings', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('IP Whitelist', 'folio'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[maintenance_bypass_ips]" rows="5" class="large-text" placeholder="192.168.1.1&#10;10.0.0.0/8&#10;203.0.113.0/24"><?php echo esc_textarea(isset($options['maintenance_bypass_ips']) ? $options['maintenance_bypass_ips'] : ''); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('IP addresses allowed to bypass maintenance mode, one per line.', 'folio'); ?><br>
                                <?php esc_html_e('Supports single IP (e.g. 192.168.1.1) and CIDR format (e.g. 192.168.1.0/24).', 'folio'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Access Logs', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[maintenance_log_enabled]" value="1" <?php checked(isset($options['maintenance_log_enabled']) ? $options['maintenance_log_enabled'] : 0, 1); ?>>
                                <?php esc_html_e('Record access logs during maintenance', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Log visitor info on maintenance page for analysis and security monitoring.', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php
                // 显示维护日志
                $logs = get_option('folio_maintenance_logs', array());
                if (!empty($logs) && isset($options['maintenance_log_enabled']) && $options['maintenance_log_enabled']):
                ?>
                <h3><?php esc_html_e('Recent Access Logs', 'folio'); ?></h3>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
                    <table class="widefat" style="margin: 0;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Time', 'folio'); ?></th>
                                <th><?php esc_html_e('IP Address', 'folio'); ?></th>
                                <th><?php esc_html_e('Page URL', 'folio'); ?></th>
                                <th><?php esc_html_e('Referrer', 'folio'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse(array_slice($logs, -20)) as $log): ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($log['timestamp']))); ?></td>
                                <td><?php echo esc_html($log['ip']); ?></td>
                                <td><?php echo esc_html($log['url']); ?></td>
                                <td><?php echo esc_html($log['referer'] ? parse_url($log['referer'], PHP_URL_HOST) : '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p>
                    <button type="button" class="button" onclick="if(confirm('<?php echo esc_js(__('Are you sure you want to clear all maintenance logs?', 'folio')); ?>')) { window.location.href='<?php echo wp_nonce_url(admin_url('themes.php?page=folio-theme-options&tab=advanced&action=clear_maintenance_logs'), 'clear_maintenance_logs'); ?>'; }">
                        <?php esc_html_e('Clear Logs', 'folio'); ?>
                    </button>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * 备份恢复标签
     */
    private function render_backup_tab($options) {
        // 处理导出
        if (isset($_POST['folio_export_settings'])) {
            check_admin_referer('folio_export_settings');
            $this->export_settings();
        }
        
        // 处理导入
        if (isset($_POST['folio_import_settings']) && isset($_FILES['import_file'])) {
            check_admin_referer('folio_import_settings');
            $this->import_settings();
        }
        
        ?>
        <div class="folio-tab-content">
            <div class="folio-section">
                <h2><?php esc_html_e('Export Settings', 'folio'); ?></h2>
                <p><?php esc_html_e('Export all current theme settings as JSON for backup or migration.', 'folio'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('folio_export_settings'); ?>
                    <button type="submit" name="folio_export_settings" class="button button-primary">
                        <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Export Settings', 'folio'); ?>
                    </button>
                </form>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Import Settings', 'folio'); ?></h2>
                <p><?php esc_html_e('Restore settings from a previously exported JSON file.', 'folio'); ?></p>
                <p class="description" style="color: #d63638;">
                    <strong><?php esc_html_e('Warning:', 'folio'); ?></strong>
                    <?php esc_html_e('Import will overwrite all current settings. Export a backup first.', 'folio'); ?>
                </p>
                
                <form method="post" action="" enctype="multipart/form-data">
                    <?php wp_nonce_field('folio_import_settings'); ?>
                    <p>
                        <input type="file" name="import_file" accept=".json" required>
                    </p>
                    <button type="submit" name="folio_import_settings" class="button button-secondary">
                        <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Import Settings', 'folio'); ?>
                    </button>
                </form>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Image Alt Text Optimization', 'folio'); ?></h2>
                <p><?php esc_html_e('Batch-generate Alt text for existing images to improve SEO and accessibility.', 'folio'); ?></p>
                
                <button type="button" class="button button-primary" id="folio-batch-update-alt">
                    <span class="dashicons dashicons-images-alt2" style="margin-top: 3px;"></span>
                    <?php esc_html_e('Batch Update Alt Text', 'folio'); ?>
                </button>
                <div id="folio-alt-update-result" style="margin-top: 10px;"></div>
                
                <script>
                document.getElementById('folio-batch-update-alt').addEventListener('click', function() {
                    const btn = this;
                    const result = document.getElementById('folio-alt-update-result');
                    
                    btn.disabled = true;
                    result.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span> <?php esc_html_e('Updating...', 'folio'); ?>';
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=folio_batch_update_alt&_ajax_nonce=<?php echo wp_create_nonce('folio_batch_update_alt'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false;
                        if (data.success) {
                            result.innerHTML = '<span style="color: #28a745;">✓ ' + data.data.message + '</span>';
                        } else {
                            result.innerHTML = '<span style="color: #dc3545;">✗ <?php echo esc_js(__('Update failed', 'folio')); ?></span>';
                        }
                        setTimeout(function() {
                            result.innerHTML = '';
                        }, 5000);
                    })
                    .catch(error => {
                        btn.disabled = false;
                        result.innerHTML = '<span style="color: #dc3545;">✗ <?php echo esc_js(__('Request failed', 'folio')); ?></span>';
                    });
                });
                </script>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Image Optimization Management', 'folio'); ?></h2>
                <p><?php esc_html_e('Batch-optimize existing images and review optimization stats and storage savings.', 'folio'); ?></p>
                
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <button type="button" class="button button-primary" id="folio-batch-optimize-images">
                        <span class="dashicons dashicons-format-image" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Batch Optimize Images', 'folio'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="folio-get-image-stats">
                        <span class="dashicons dashicons-chart-pie" style="margin-top: 3px;"></span>
                        <?php esc_html_e('View Stats', 'folio'); ?>
                    </button>
                </div>
                
                <div id="folio-image-optimization-result" style="margin-bottom: 15px;"></div>
                
                <div id="folio-image-stats-dashboard" style="display: none;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0;"><?php esc_html_e('Optimization Stats', 'folio'); ?></h4>
                            <div id="folio-image-stats-summary"></div>
                        </div>
                        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0;"><?php esc_html_e('Recent Optimizations', 'folio'); ?></h4>
                            <div id="folio-recent-optimizations" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>
                
                <script>
                document.getElementById('folio-batch-optimize-images').addEventListener('click', function() {
                    const btn = this;
                    const result = document.getElementById('folio-image-optimization-result');
                    
                    btn.disabled = true;
                    btn.textContent = '<?php echo esc_js(__('Optimizing...', 'folio')); ?>';
                    result.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span> <?php echo esc_js(__('Batch optimizing images...', 'folio')); ?>';
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=folio_batch_optimize_images&_ajax_nonce=<?php echo wp_create_nonce('folio_batch_optimize_images'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-format-image" style="margin-top: 3px;"></span> <?php echo esc_js(__('Continue Optimization', 'folio')); ?>';
                        
                        if (data.success) {
                            result.innerHTML = '<div style="color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px;">✓ ' + data.data.message + '</div>';
                            
                            if (data.data.remaining > 0) {
                                result.innerHTML += '<p style="margin-top: 10px;"><?php echo esc_js(__('There are still', 'folio')); ?> ' + data.data.remaining + ' <?php echo esc_js(__('images left to optimize. You can continue by clicking the optimize button.', 'folio')); ?></p>';
                            } else {
                                result.innerHTML += '<p style="margin-top: 10px; color: #28a745;"><?php echo esc_js(__('All images have been optimized!', 'folio')); ?></p>';
                                btn.style.display = 'none';
                            }
                        } else {
                            result.innerHTML = '<div style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px;">✗ <?php echo esc_js(__('Optimization failed', 'folio')); ?></div>';
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-format-image" style="margin-top: 3px;"></span> <?php echo esc_js(__('Batch Optimize Images', 'folio')); ?>';
                        result.innerHTML = '<div style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px;">✗ <?php echo esc_js(__('Request failed', 'folio')); ?></div>';
                    });
                });
                
                document.getElementById('folio-get-image-stats').addEventListener('click', function() {
                    const btn = this;
                    const dashboard = document.getElementById('folio-image-stats-dashboard');
                    
                    btn.disabled = true;
                    btn.textContent = '<?php echo esc_js(__('Loading...', 'folio')); ?>';
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=folio_get_optimization_stats&_ajax_nonce=<?php echo wp_create_nonce('folio_get_optimization_stats'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-chart-pie" style="margin-top: 3px;"></span> <?php echo esc_js(__('Refresh Stats', 'folio')); ?>';
                        
                        if (data.success) {
                            dashboard.style.display = 'block';
                            
                            // 显示统计摘要
                            document.getElementById('folio-image-stats-summary').innerHTML = `
                                <div><?php echo esc_js(__('Optimized Images:', 'folio')); ?> ${data.data.total_images}</div>
                                <div><?php echo esc_js(__('Space Saved:', 'folio')); ?> ${formatBytes(data.data.total_savings)}</div>
                                <div><?php echo esc_js(__('Pending Optimization:', 'folio')); ?> ${data.data.unoptimized_count}</div>
                            `;
                            
                            // 显示最近优化
                            const recent = data.data.recent_optimizations.reverse();
                            document.getElementById('folio-recent-optimizations').innerHTML = recent.map(opt => `
                                <div style="margin-bottom: 8px; padding: 8px; background: white; border-radius: 3px; font-size: 12px;">
                                    <div><strong>${opt.filename}</strong></div>
                                    <div><?php echo esc_js(__('Saved:', 'folio')); ?> ${formatBytes(opt.savings)} (${opt.percentage}%)</div>
                                </div>
                            `).join('');
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-chart-pie" style="margin-top: 3px;"></span> <?php echo esc_js(__('Load Failed', 'folio')); ?>';
                    });
                });
                
                function formatBytes(bytes) {
                    if (bytes === 0) return '0 B';
                    const k = 1024;
                    const sizes = ['B', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                }
                </script>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Performance Dashboard', 'folio'); ?></h2>
                <p><?php esc_html_e('Monitor site performance metrics including load time, memory usage, and DB queries.', 'folio'); ?></p>
                
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <button type="button" class="button button-primary" id="folio-load-performance-data">
                        <span class="dashicons dashicons-chart-line" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Load Performance Data', 'folio'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="folio-clear-performance-logs">
                        <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Clear Logs', 'folio'); ?>
                    </button>
                </div>
                
                <div id="folio-performance-dashboard" style="display: none;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0;"><?php esc_html_e('Performance Stats', 'folio'); ?></h4>
                            <div id="folio-performance-stats"></div>
                        </div>
                        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0;"><?php esc_html_e('Recent Requests', 'folio'); ?></h4>
                            <div id="folio-recent-requests" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                    </div>
                    
                    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
                        <h4 style="margin: 0 0 10px 0; color: #856404;"><?php esc_html_e('Slow Query Warnings', 'folio'); ?></h4>
                        <div id="folio-slow-queries"></div>
                    </div>
                </div>
                
                <script>
                document.getElementById('folio-load-performance-data').addEventListener('click', function() {
                    const btn = this;
                    const dashboard = document.getElementById('folio-performance-dashboard');
                    
                    btn.disabled = true;
                    btn.textContent = '<?php echo esc_js(__('Loading...', 'folio')); ?>';
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=folio_get_performance_data&_ajax_nonce=<?php echo wp_create_nonce('folio_get_performance_data'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-chart-line" style="margin-top: 3px;"></span> <?php echo esc_js(__('Refresh Data', 'folio')); ?>';
                        
                        if (data.success) {
                            dashboard.style.display = 'block';
                            
                            // 显示统计数据
                            const stats = data.data.stats;
                            document.getElementById('folio-performance-stats').innerHTML = `
                                <div style="margin-bottom: 8px;"><strong><?php echo esc_js(__('Frontend Page Performance', 'folio')); ?></strong></div>
                                <div style="margin-bottom: 8px;">
                                    <strong><?php echo esc_js(__('Performance Score:', 'folio')); ?> </strong>
                                    <span style="color: ${stats.performance_score >= 80 ? '#28a745' : (stats.performance_score >= 60 ? '#ffc107' : '#dc3545')}; font-size: 16px;">
                                        ${stats.performance_score || 0}/100
                                    </span>
                                </div>
                                <div><?php echo esc_js(__('Average Load Time:', 'folio')); ?> <span style="color: ${stats.avg_load_time > 2 ? '#dc3545' : '#28a745'}">${stats.avg_load_time ? stats.avg_load_time.toFixed(3) : 0}s</span></div>
                                <div><?php echo esc_js(__('Max Load Time:', 'folio')); ?> <span style="color: ${stats.max_load_time > 3 ? '#dc3545' : '#ffc107'}">${stats.max_load_time ? stats.max_load_time.toFixed(3) : 0}s</span></div>
                                <div><?php echo esc_js(__('Average Memory Usage:', 'folio')); ?> ${formatBytes(stats.avg_memory || 0)}</div>
                                <div><?php echo esc_js(__('Average Query Count:', 'folio')); ?> <span style="color: ${stats.avg_queries > 30 ? '#dc3545' : '#28a745'}">${Math.round(stats.avg_queries || 0)}</span></div>
                                <div><?php echo esc_js(__('Mobile Visits:', 'folio')); ?> ${stats.mobile_percentage || 0}%</div>
                                <div><?php echo esc_js(__('Optimization Issues:', 'folio')); ?> <span style="color: ${stats.optimization_issues > 0 ? '#dc3545' : '#28a745'}">${stats.optimization_issues || 0}</span></div>
                                <div><?php echo esc_js(__('Total Page Visits:', 'folio')); ?> ${stats.total_requests || 0}</div>
                                <div style="margin-top: 8px; font-size: 11px; color: #666;"><?php echo esc_js(__('Frontend page data only', 'folio')); ?></div>
                            `;
                            
                            // 显示最近请求
                            const requests = data.data.logs.slice(-10).reverse();
                            document.getElementById('folio-recent-requests').innerHTML = requests.map(log => `
                                <div style="margin-bottom: 8px; padding: 8px; background: white; border-radius: 3px; font-size: 12px; border-left: 3px solid ${log.load_time > 3 ? '#dc3545' : (log.load_time > 2 ? '#ffc107' : '#28a745')};">
                                    <div><strong>${log.url}</strong> <span style="background: #e9ecef; padding: 2px 6px; border-radius: 10px; font-size: 10px;">${log.page_type || 'unknown'}</span></div>
                                    <div>⏱️ ${log.load_time.toFixed(3)}s | 💾 ${formatBytes(log.memory_usage)} | 🗄️ ${log.db_queries} queries</div>
                                    ${log.is_mobile ? '<div style="color: #6c757d; font-size: 10px;">[<?php echo esc_js(__('Mobile Device', 'folio')); ?>]</div>' : ''}
                                    ${log.optimization_suggestions && log.optimization_suggestions.length > 0 ? 
                                        '<div style="color: #dc3545; font-size: 10px;">[' + log.optimization_suggestions.length + ' <?php echo esc_js(__('Optimization Suggestions', 'folio')); ?>]</div>' : ''}
                                </div>
                            `).join('');
                            
                            // 显示慢查询
                            const slowQueries = data.data.slow_queries;
                            if (slowQueries.length > 0) {
                                document.getElementById('folio-slow-queries').innerHTML = slowQueries.map(query => `
                                    <div style="margin-bottom: 8px; padding: 8px; background: rgba(255, 193, 7, 0.1); border-radius: 3px; font-size: 12px;">
                                        <div style="color: #856404;"><strong>${query.time.toFixed(3)}s</strong> - ${query.url}</div>
                                        <div style="font-family: monospace; color: #6c757d;">${query.sql.substring(0, 100)}...</div>
                                    </div>
                                `).join('');
                            } else {
                                document.getElementById('folio-slow-queries').innerHTML = '<div style="color: #28a745;"><?php echo esc_js(__('No slow query records', 'folio')); ?></div>';
                            }
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-chart-line" style="margin-top: 3px;"></span> <?php echo esc_js(__('Load Failed', 'folio')); ?>';
                    });
                });
                
                document.getElementById('folio-clear-performance-logs').addEventListener('click', function() {
                    if (!confirm('<?php echo esc_js(__('Are you sure you want to clear all performance logs?', 'folio')); ?>')) return;
                    
                    const btn = this;
                    btn.disabled = true;
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=folio_clear_performance_logs&_ajax_nonce=<?php echo wp_create_nonce('folio_clear_performance_logs'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false;
                        if (data.success) {
                            document.getElementById('folio-performance-dashboard').style.display = 'none';
                            alert('<?php echo esc_js(__('Performance logs have been cleared', 'folio')); ?>');
                        }
                    });
                });
                
                function formatBytes(bytes) {
                    if (bytes === 0) return '0 B';
                    const k = 1024;
                    const sizes = ['B', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                }
                </script>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Reset Settings', 'folio'); ?></h2>
                <p><?php esc_html_e('Reset all settings to defaults.', 'folio'); ?></p>
                <p class="description" style="color: #d63638;">
                    <strong><?php esc_html_e('Warning:', 'folio'); ?></strong>
                    <?php esc_html_e('This action cannot be undone. Export a backup first.', 'folio'); ?>
                </p>
                
                <button type="button" class="button button-secondary" id="folio-reset-settings">
                    <span class="dashicons dashicons-image-rotate" style="margin-top: 3px;"></span>
                    <?php esc_html_e('Reset All Settings', 'folio'); ?>
                </button>
                
                <script>
                document.getElementById('folio-reset-settings').addEventListener('click', function() {
                    if (confirm('<?php echo esc_js(__('Are you sure you want to reset all settings? This cannot be undone!', 'folio')); ?>')) {
                        if (confirm('<?php echo esc_js(__('Final confirmation: really reset?', 'folio')); ?>')) {
                            window.location.href = '<?php echo wp_nonce_url(admin_url('themes.php?page=folio-theme-options&tab=backup&action=reset'), 'folio_reset_settings'); ?>';
                        }
                    }
                });
                </script>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Current Settings Info', 'folio'); ?></h2>
                <table class="widefat">
                    <tr>
                        <td><strong><?php esc_html_e('Option Name', 'folio'); ?></strong></td>
                        <td><code>folio_theme_options</code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Database Table', 'folio'); ?></strong></td>
                        <td><code>wp_options</code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Settings Count', 'folio'); ?></strong></td>
                        <td><?php echo count($options); ?> <?php esc_html_e('items', 'folio'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Data Size', 'folio'); ?></strong></td>
                        <td><?php echo size_format(strlen(serialize($options))); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Last Updated', 'folio'); ?></strong></td>
                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
        
        // 处理重置
        if (isset($_GET['action']) && $_GET['action'] === 'reset' && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'folio_reset_settings')) {
                delete_option('folio_theme_options');
                wp_redirect(admin_url('themes.php?page=folio-theme-options&tab=backup&reset=success'));
                exit;
            }
        }
        
        // 显示重置成功消息
        if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings have been reset to defaults', 'folio') . '</p></div>';
        }
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
        wp_redirect(admin_url('themes.php?page=folio-theme-options&tab=backup&imported=success'));
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
