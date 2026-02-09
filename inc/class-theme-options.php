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
            __('Folio 主题设置', 'folio'),
            __('主题设置', 'folio'),
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
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('常规设置', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=social" class="nav-tab <?php echo $active_tab === 'social' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-share"></span> <?php esc_html_e('社交链接', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=seo" class="nav-tab <?php echo $active_tab === 'seo' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-search"></span> <?php esc_html_e('SEO优化', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=performance" class="nav-tab <?php echo $active_tab === 'performance' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-performance"></span> <?php esc_html_e('性能优化', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=optimize" class="nav-tab <?php echo $active_tab === 'optimize' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('功能优化', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=enhancements" class="nav-tab <?php echo $active_tab === 'enhancements' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('功能增强', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=ai" class="nav-tab <?php echo $active_tab === 'ai' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('AI设置', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('高级设置', 'folio'); ?>
                </a>
                <a href="?page=folio-theme-options&tab=backup" class="nav-tab <?php echo $active_tab === 'backup' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-database-export"></span> <?php esc_html_e('备份恢复', 'folio'); ?>
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
                
                <?php submit_button(__('保存设置', 'folio'), 'primary large'); ?>
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
                <h2><?php esc_html_e('网站标识', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('网站LOGO', 'folio'); ?></th>
                        <td>
                            <?php
                            $logo_url = isset($options['site_logo']) ? esc_url($options['site_logo']) : '';
                            ?>
                            <div class="folio-image-upload-wrapper">
                                <div class="folio-image-preview" style="margin-bottom: 10px;">
                                    <?php if ($logo_url) : ?>
                                        <img src="<?php echo esc_url($logo_url); ?>" style="max-width: 200px; height: auto; display: block; margin-bottom: 10px;" alt="Logo预览">
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[site_logo]" id="site_logo" value="<?php echo esc_attr($logo_url); ?>">
                                <button type="button" class="button folio-upload-image-button" data-target="site_logo">
                                    <?php echo $logo_url ? esc_html__('更换LOGO', 'folio') : esc_html__('选择LOGO', 'folio'); ?>
                                </button>
                                <?php if ($logo_url) : ?>
                                    <button type="button" class="button folio-remove-image-button" data-target="site_logo" style="margin-left: 10px;">
                                        <?php esc_html_e('移除LOGO', 'folio'); ?>
                                    </button>
                                <?php endif; ?>
                                <p class="description"><?php esc_html_e('上传网站LOGO图片。如果未设置，将显示网站标题。', 'folio'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('网站Favicon图标', 'folio'); ?></th>
                        <td>
                            <?php
                            $favicon_url = isset($options['site_favicon']) ? esc_url($options['site_favicon']) : '';
                            ?>
                            <div class="folio-image-upload-wrapper">
                                <div class="folio-image-preview" style="margin-bottom: 10px;">
                                    <?php if ($favicon_url) : ?>
                                        <img src="<?php echo esc_url($favicon_url); ?>" style="max-width: 32px; height: 32px; display: block; margin-bottom: 10px;" alt="Favicon预览">
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[site_favicon]" id="site_favicon" value="<?php echo esc_attr($favicon_url); ?>">
                                <button type="button" class="button folio-upload-image-button" data-target="site_favicon">
                                    <?php echo $favicon_url ? esc_html__('更换图标', 'folio') : esc_html__('选择图标', 'folio'); ?>
                                </button>
                                <?php if ($favicon_url) : ?>
                                    <button type="button" class="button folio-remove-image-button" data-target="site_favicon" style="margin-left: 10px;">
                                        <?php esc_html_e('移除图标', 'folio'); ?>
                                    </button>
                                <?php endif; ?>
                                <p class="description"><?php esc_html_e('上传网站Favicon图标（建议尺寸：32x32px或16x16px）。', 'folio'); ?></p>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('主题模式', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('默认主题模式', 'folio'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr($this->option_name); ?>[theme_mode]" id="theme-mode-select">
                                <?php 
                                $current_theme_mode = isset($options['theme_mode']) ? $options['theme_mode'] : 'auto';
                                ?>
                                <option value="auto" <?php selected($current_theme_mode, 'auto'); ?>><?php esc_html_e('自动（根据日落时间）', 'folio'); ?></option>
                                <option value="light" <?php selected($current_theme_mode, 'light'); ?>><?php esc_html_e('亮色模式', 'folio'); ?></option>
                                <option value="dark" <?php selected($current_theme_mode, 'dark'); ?>><?php esc_html_e('暗色模式', 'folio'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('选择网站的默认显示模式。选择"自动"时，主题将根据设置的日出日落时间自动切换亮色/暗色模式。', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr class="auto-sunset-settings" style="<?php echo ($current_theme_mode === 'auto') ? '' : 'display:none;'; ?>">
                        <th scope="row"><?php esc_html_e('日出时间', 'folio'); ?></th>
                        <td>
                            <input type="time" name="<?php echo esc_attr($this->option_name); ?>[sunrise_time]" value="<?php echo esc_attr(isset($options['sunrise_time']) ? $options['sunrise_time'] : '06:00'); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('设置日出时间（24小时制），日出后自动切换到亮色模式', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr class="auto-sunset-settings" style="<?php echo ($current_theme_mode === 'auto') ? '' : 'display:none;'; ?>">
                        <th scope="row"><?php esc_html_e('日落时间', 'folio'); ?></th>
                        <td>
                            <input type="time" name="<?php echo esc_attr($this->option_name); ?>[sunset_time]" value="<?php echo esc_attr(isset($options['sunset_time']) ? $options['sunset_time'] : '18:00'); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('设置日落时间（24小时制），日落后自动切换到暗色模式', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('显示主题切换按钮', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_theme_toggle]" value="1" <?php checked(!isset($options['show_theme_toggle']) || !empty($options['show_theme_toggle']), true); ?>>
                                <?php esc_html_e('允许访客手动切换亮色/暗色模式', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('在网站头部显示主题切换按钮，访客可以手动切换亮色/暗色模式', 'folio'); ?></p>
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
                <h2><?php esc_html_e('作品集设置', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('新作品天数', 'folio'); ?></th>
                        <td>
                            <input type="number" name="<?php echo esc_attr($this->option_name); ?>[new_days]" value="<?php echo esc_attr(isset($options['new_days']) ? $options['new_days'] : 30); ?>" min="1" max="365" class="small-text">
                            <p class="description"><?php esc_html_e('发布多少天内显示 "New" 角标', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('每页显示作品数', 'folio'); ?></th>
                        <td>
                            <input type="number" name="<?php echo esc_attr($this->option_name); ?>[posts_per_page]" value="<?php echo esc_attr(isset($options['posts_per_page']) ? $options['posts_per_page'] : 12); ?>" min="1" max="100" class="small-text">
                            <p class="description"><?php esc_html_e('归档页面每页显示的作品数量', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('显示 "Brain Powered Fun" 六边形', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_brain_hexagon]" value="1" <?php checked(!isset($options['show_brain_hexagon']) || !empty($options['show_brain_hexagon']), true); ?>>
                                <?php esc_html_e('在首页作品网格末尾显示装饰性六边形', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('页脚设置', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('版权文本', 'folio'); ?></th>
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
                            <p class="description"><?php esc_html_e('留空则使用默认文本。支持 HTML。', 'folio'); ?></p>
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
        echo '<option value="default"' . selected($current_value, 'default', false) . '>' . esc_html__('默认', 'folio') . '</option>';
        
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
                <h2><?php esc_html_e('社交媒体链接', 'folio'); ?></h2>
                <p class="description"><?php esc_html_e('配置侧边栏显示的社交媒体链接和图标', 'folio'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><span class="dashicons dashicons-email"></span> <?php esc_html_e('邮箱地址', 'folio'); ?></th>
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
                <h2><?php esc_html_e('SEO 基础设置', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('首页标题', 'folio'); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr($this->option_name); ?>[seo_title]" value="<?php echo esc_attr(isset($options['seo_title']) ? $options['seo_title'] : ''); ?>" class="large-text">
                            <p class="description"><?php esc_html_e('自定义首页 SEO 标题，留空使用默认', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('首页关键词', 'folio'); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr($this->option_name); ?>[seo_keywords]" value="<?php echo esc_attr(isset($options['seo_keywords']) ? $options['seo_keywords'] : ''); ?>" class="large-text">
                            <p class="description"><?php esc_html_e('多个关键词用英文逗号分隔', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('首页描述', 'folio'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[seo_description]" rows="3" class="large-text"><?php echo esc_textarea(isset($options['seo_description']) ? $options['seo_description'] : ''); ?></textarea>
                            <p class="description"><?php esc_html_e('网站首页的 meta description，建议 150-160 字符', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('Open Graph 设置', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('启用 Open Graph', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_og]" value="1" <?php checked(isset($options['enable_og']) ? $options['enable_og'] : 1, 1); ?>>
                                <?php esc_html_e('为社交媒体分享优化（Facebook, Twitter等）', 'folio'); ?>
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
                <h2><?php esc_html_e('前端资源优化', 'folio'); ?></h2>
                <p class="description"><?php esc_html_e('优化CSS、JavaScript和图片资源，提升网站加载速度', 'folio'); ?></p>
                
                <?php if (!empty($stats)) : ?>
                <div class="folio-optimization-stats" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3><?php esc_html_e('优化统计', 'folio'); ?></h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div>
                            <h4><?php esc_html_e('CSS文件', 'folio'); ?></h4>
                            <p><?php esc_html_e('原始', 'folio'); ?>: <?php echo esc_html($stats['css']['original']); ?> <?php esc_html_e('个', 'folio'); ?></p>
                            <p><?php esc_html_e('优化后', 'folio'); ?>: <?php echo esc_html($stats['css']['optimized']); ?> <?php esc_html_e('个', 'folio'); ?></p>
                            <p><?php esc_html_e('节省', 'folio'); ?>: <?php echo esc_html($stats['css']['savings']); ?>%</p>
                        </div>
                        <div>
                            <h4><?php esc_html_e('JavaScript文件', 'folio'); ?></h4>
                            <p><?php esc_html_e('原始', 'folio'); ?>: <?php echo esc_html($stats['js']['original']); ?> <?php esc_html_e('个', 'folio'); ?></p>
                            <p><?php esc_html_e('优化后', 'folio'); ?>: <?php echo esc_html($stats['js']['optimized']); ?> <?php esc_html_e('个', 'folio'); ?></p>
                            <p><?php esc_html_e('节省', 'folio'); ?>: <?php echo esc_html($stats['js']['savings']); ?>%</p>
                        </div>
                        <div>
                            <h4><?php esc_html_e('图片优化', 'folio'); ?></h4>
                            <p><?php esc_html_e('懒加载', 'folio'); ?>: <?php echo esc_html($stats['images']['lazy_loaded']); ?> <?php esc_html_e('张', 'folio'); ?></p>
                            <p><?php esc_html_e('WebP支持', 'folio'); ?>: <?php echo $stats['images']['webp_support'] ? esc_html__('是', 'folio') : esc_html__('否', 'folio'); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('启用前端优化', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][enabled]" value="1" <?php checked(isset($frontend_opt['enabled']) ? $frontend_opt['enabled'] : true, true); ?>>
                                <?php esc_html_e('启用所有前端优化功能', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('CSS优化', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][minify_css]" value="1" <?php checked(isset($frontend_opt['minify_css']) ? $frontend_opt['minify_css'] : true, true); ?>>
                                <?php esc_html_e('压缩CSS文件', 'folio'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][combine_css]" value="1" <?php checked(isset($frontend_opt['combine_css']) ? $frontend_opt['combine_css'] : false, true); ?>>
                                <?php esc_html_e('合并CSS文件', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('注意：合并CSS可能导致FOUC（无样式内容闪烁），建议谨慎使用', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('JavaScript优化', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][minify_js]" value="1" <?php checked(isset($frontend_opt['minify_js']) ? $frontend_opt['minify_js'] : true, true); ?>>
                                <?php esc_html_e('压缩JavaScript文件', 'folio'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][combine_js]" value="1" <?php checked(isset($frontend_opt['combine_js']) ? $frontend_opt['combine_js'] : false, true); ?>>
                                <?php esc_html_e('合并JavaScript文件', 'folio'); ?> <span style="color: #d63638;"><?php esc_html_e('(实验性)', 'folio'); ?></span>
                            </label>
                            <p class="description"><?php esc_html_e('注意：合并JS可能导致兼容性问题，建议谨慎使用', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('图片优化', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][lazy_load_images]" value="1" <?php checked(isset($frontend_opt['lazy_load_images']) ? $frontend_opt['lazy_load_images'] : true, true); ?>>
                                <?php esc_html_e('启用图片懒加载', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('图片滚动到可视区域时才加载，提升页面加载速度', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('字体优化', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][optimize_fonts]" value="1" <?php checked(isset($frontend_opt['optimize_fonts']) ? $frontend_opt['optimize_fonts'] : true, true); ?>>
                                <?php esc_html_e('优化字体加载', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('优化字体加载策略，减少字体加载阻塞', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('资源加载', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][preload_critical]" value="1" <?php checked(isset($frontend_opt['preload_critical']) ? $frontend_opt['preload_critical'] : true, true); ?>>
                                <?php esc_html_e('预加载关键资源', 'folio'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][defer_non_critical]" value="1" <?php checked(isset($frontend_opt['defer_non_critical']) ? $frontend_opt['defer_non_critical'] : false, true); ?>>
                                <?php esc_html_e('延迟非关键资源', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('注意：延迟加载可能导致FOUC，建议谨慎使用', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('缓存和压缩', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][cache_busting]" value="1" <?php checked(isset($frontend_opt['cache_busting']) ? $frontend_opt['cache_busting'] : true, true); ?>>
                                <?php esc_html_e('启用缓存破坏', 'folio'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][enable_gzip]" value="1" <?php checked(isset($frontend_opt['enable_gzip']) ? $frontend_opt['enable_gzip'] : true, true); ?>>
                                <?php esc_html_e('启用Gzip压缩', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('HTML压缩', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[frontend_optimization][minify_html]" value="1" <?php checked(isset($frontend_opt['minify_html']) ? $frontend_opt['minify_html'] : false, true); ?>>
                                <?php esc_html_e('压缩HTML输出', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('移除HTML中的空白字符，减小页面大小', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div class="folio-optimization-actions" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <h3><?php esc_html_e('优化操作', 'folio'); ?></h3>
                    <p>
                        <button type="button" class="button button-primary" id="folio-optimize-assets">
                            <span class="dashicons dashicons-performance" style="margin-top: 3px;"></span>
                            <?php esc_html_e('重新优化资源', 'folio'); ?>
                        </button>
                        <button type="button" class="button" id="folio-clear-optimized-cache">
                            <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
                            <?php esc_html_e('清除优化缓存', 'folio'); ?>
                        </button>
                    </p>
                    <div id="folio-optimization-result" style="margin-top: 10px;"></div>
                </div>
            </div>
            
            <!-- 基础性能设置 -->
            <div class="folio-section">
                <h2><?php esc_html_e('基础性能设置', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('禁用 Emoji', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_emoji]" value="1" <?php checked(isset($options['disable_emoji']) ? $options['disable_emoji'] : 0, 1); ?>>
                                <?php esc_html_e('禁用 WordPress 默认的 Emoji 脚本', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- 缓存设置 -->
            <div class="folio-section">
                <h2><?php esc_html_e('缓存设置', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('浏览器缓存时间', 'folio'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr($this->option_name); ?>[cache_time]">
                                <option value="3600" <?php selected(isset($options['cache_time']) ? $options['cache_time'] : '86400', '3600'); ?>><?php esc_html_e('1小时', 'folio'); ?></option>
                                <option value="86400" <?php selected(isset($options['cache_time']) ? $options['cache_time'] : '86400', '86400'); ?>><?php esc_html_e('1天', 'folio'); ?></option>
                                <option value="604800" <?php selected(isset($options['cache_time']) ? $options['cache_time'] : '86400', '604800'); ?>><?php esc_html_e('7天', 'folio'); ?></option>
                                <option value="2592000" <?php selected(isset($options['cache_time']) ? $options['cache_time'] : '86400', '2592000'); ?>><?php esc_html_e('30天', 'folio'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('设置静态资源的浏览器缓存时间', 'folio'); ?></p>
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
                    btn.innerHTML = '<span class="dashicons dashicons-update" style="margin-top: 3px; animation: spin 1s linear infinite;"></span> <?php esc_html_e('优化中...', 'folio'); ?>';
                    resultDiv.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span> <?php esc_html_e('正在优化资源...', 'folio'); ?>';
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=folio_optimize_assets&nonce=<?php echo wp_create_nonce('folio_optimize_assets'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-performance" style="margin-top: 3px;"></span> <?php esc_html_e('重新优化资源', 'folio'); ?>';
                        if (data.success) {
                            resultDiv.innerHTML = '<div class="notice notice-success inline"><p>✓ <?php esc_html_e('资源优化完成！', 'folio'); ?></p></div>';
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            resultDiv.innerHTML = '<div class="notice notice-error inline"><p>✗ <?php esc_html_e('优化失败：', 'folio'); ?>' + (data.data || '<?php esc_html_e('未知错误', 'folio'); ?>') + '</p></div>';
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-performance" style="margin-top: 3px;"></span> <?php esc_html_e('重新优化资源', 'folio'); ?>';
                        resultDiv.innerHTML = '<div class="notice notice-error inline"><p>✗ <?php esc_html_e('请求失败', 'folio'); ?></p></div>';
                    });
                });
            }
            
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    if (!confirm('<?php esc_html_e('确定要清除优化缓存吗？', 'folio'); ?>')) {
                        return;
                    }
                    
                    const btn = this;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="dashicons dashicons-update" style="margin-top: 3px; animation: spin 1s linear infinite;"></span> <?php esc_html_e('清除中...', 'folio'); ?>';
                    resultDiv.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span> <?php esc_html_e('正在清除缓存...', 'folio'); ?>';
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=folio_clear_optimized_cache&nonce=<?php echo wp_create_nonce('folio_clear_optimized_cache'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> <?php esc_html_e('清除优化缓存', 'folio'); ?>';
                        if (data.success) {
                            resultDiv.innerHTML = '<div class="notice notice-success inline"><p>✓ <?php esc_html_e('缓存已清除！', 'folio'); ?></p></div>';
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            resultDiv.innerHTML = '<div class="notice notice-error inline"><p>✗ <?php esc_html_e('清除失败：', 'folio'); ?>' + (data.data || '<?php esc_html_e('未知错误', 'folio'); ?>') + '</p></div>';
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> <?php esc_html_e('清除优化缓存', 'folio'); ?>';
                        resultDiv.innerHTML = '<div class="notice notice-error inline"><p>✗ <?php esc_html_e('请求失败', 'folio'); ?></p></div>';
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
                <h2><?php esc_html_e('URL优化', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('移除分类前缀', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_category_prefix]" value="1" <?php checked(isset($options['remove_category_prefix']) ? $options['remove_category_prefix'] : 1, 1); ?>>
                                <?php esc_html_e('移除分类链接中的 /category/ 前缀', 'folio'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('例如：/category/design/ → /design/', 'folio'); ?><br>
                                <strong style="color: #d63638;"><?php esc_html_e('重要：', 'folio'); ?></strong>
                                <?php esc_html_e('启用后必须执行以下步骤：', 'folio'); ?><br>
                                1. <?php esc_html_e('保存此设置', 'folio'); ?><br>
                                2. <?php esc_html_e('进入"设置 > 固定链接"', 'folio'); ?><br>
                                3. <?php esc_html_e('点击"保存更改"按钮', 'folio'); ?><br>
                                4. <?php esc_html_e('或点击下方按钮刷新重写规则', 'folio'); ?>
                            </p>
                            <button type="button" class="button" id="flush-rewrite-rules">
                                <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                                <?php esc_html_e('刷新重写规则', 'folio'); ?>
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
                                    result.innerHTML = '<span style="color: #dc3545;">✗ 请求失败</span>';
                                });
                            });
                            </script>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('内容优化', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('自动特色图片', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[auto_featured_image]" value="1" <?php checked(isset($options['auto_featured_image']) ? $options['auto_featured_image'] : 1, 1); ?>>
                                <?php esc_html_e('自动将文章第一张图片设为特色图片', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('保存文章时，如果没有特色图片，自动使用文章中的第一张图片', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('搜索优化', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[search_title_only]" value="1" <?php checked(isset($options['search_title_only']) ? $options['search_title_only'] : 1, 1); ?>>
                                <?php esc_html_e('搜索时只匹配文章标题（提升搜索速度）', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('清理图片HTML', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[clean_image_html]" value="1" <?php checked(isset($options['clean_image_html']) ? $options['clean_image_html'] : 1, 1); ?>>
                                <?php esc_html_e('移除插入图片时的冗余HTML属性', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('移除 width、height、class、srcset 等属性，只保留 src 和 alt', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('编辑器设置', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('文章编辑器', 'folio'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr($this->option_name); ?>[editor_type]">
                                <option value="gutenberg" <?php selected(isset($options['editor_type']) ? $options['editor_type'] : 'gutenberg', 'gutenberg'); ?>>
                                    <?php esc_html_e('古腾堡编辑器（区块编辑器）', 'folio'); ?>
                                </option>
                                <option value="classic" <?php selected(isset($options['editor_type']) ? $options['editor_type'] : 'gutenberg', 'classic'); ?>>
                                    <?php esc_html_e('经典编辑器（TinyMCE）', 'folio'); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('选择文章编辑时使用的编辑器类型', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('小工具编辑器', 'folio'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr($this->option_name); ?>[widget_editor_type]">
                                <option value="gutenberg" <?php selected(isset($options['widget_editor_type']) ? $options['widget_editor_type'] : 'gutenberg', 'gutenberg'); ?>>
                                    <?php esc_html_e('古腾堡小工具（区块编辑器）', 'folio'); ?>
                                </option>
                                <option value="classic" <?php selected(isset($options['widget_editor_type']) ? $options['widget_editor_type'] : 'gutenberg', 'classic'); ?>>
                                    <?php esc_html_e('经典小工具', 'folio'); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('选择小工具管理时使用的编辑器类型', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('后台优化', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('移除顶部Logo', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_admin_bar_logo]" value="1" <?php checked(isset($options['remove_admin_bar_logo']) ? $options['remove_admin_bar_logo'] : 1, 1); ?>>
                                <?php esc_html_e('移除后台管理栏的 WordPress Logo', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('前端管理栏', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[hide_admin_bar_frontend]" value="1" <?php checked(isset($options['hide_admin_bar_frontend']) ? $options['hide_admin_bar_frontend'] : 0, 1); ?>>
                                <?php esc_html_e('隐藏前端顶部管理栏', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('即使登录后，前端页面也不显示顶部管理栏', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('数据库优化', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('文章修订版本', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_post_revisions]" value="1" <?php checked(isset($options['disable_post_revisions']) ? $options['disable_post_revisions'] : 0, 1); ?>>
                                <?php esc_html_e('完全禁用文章修订版本', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('减少数据库负担，但无法恢复历史版本', 'folio'); ?></p>
                            
                            <div style="margin-top: 10px;">
                                <label><?php esc_html_e('或限制修订版本数量：', 'folio'); ?></label>
                                <input type="number" name="<?php echo esc_attr($this->option_name); ?>[post_revisions_limit]" value="<?php echo esc_attr(isset($options['post_revisions_limit']) ? $options['post_revisions_limit'] : 0); ?>" min="0" max="50" class="small-text">
                                <span class="description"><?php esc_html_e('个（0=不限制）', 'folio'); ?></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('自动保存', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_autosave]" value="1" <?php checked(isset($options['disable_autosave']) ? $options['disable_autosave'] : 0, 1); ?>>
                                <?php esc_html_e('禁用编辑器自动保存', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('减少数据库写入，但可能丢失未保存的内容', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('脚本优化', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('jQuery Migrate', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_jquery_migrate]" value="1" <?php checked(isset($options['remove_jquery_migrate']) ? $options['remove_jquery_migrate'] : 1, 1); ?>>
                                <?php esc_html_e('移除jQuery Migrate脚本', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('减少JS加载，但可能影响旧插件兼容性', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Dashicons图标', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_dashicons_frontend]" value="1" <?php checked(isset($options['disable_dashicons_frontend']) ? $options['disable_dashicons_frontend'] : 1, 1); ?>>
                                <?php esc_html_e('前端禁用Dashicons（未登录用户）', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('减少CSS加载，如果前端不使用Dashicons图标', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('性能监控', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_performance_monitor]" value="1" <?php checked(isset($options['enable_performance_monitor']) ? $options['enable_performance_monitor'] : 0, 1); ?>>
                                <?php esc_html_e('启用前端性能监控', 'folio'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('监控前端页面性能指标，包括加载时间、内存使用、数据库查询次数等，并提供优化建议。仅在前端页面显示，管理员可见。', 'folio'); ?><br>
                                <strong><?php esc_html_e('快捷键：', 'folio'); ?></strong>
                                <code>Ctrl+Shift+P</code> <?php esc_html_e('显示/隐藏工具栏', 'folio'); ?>，
                                <code>Ctrl+双击</code> <?php esc_html_e('重新显示', 'folio'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Heartbeat API', 'folio'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr($this->option_name); ?>[heartbeat_mode]">
                                <option value="default" <?php selected(isset($options['heartbeat_mode']) ? $options['heartbeat_mode'] : 'default', 'default'); ?>>
                                    <?php esc_html_e('默认（15秒）', 'folio'); ?>
                                </option>
                                <option value="reduce" <?php selected(isset($options['heartbeat_mode']) ? $options['heartbeat_mode'] : 'default', 'reduce'); ?>>
                                    <?php esc_html_e('降低频率（60秒）', 'folio'); ?>
                                </option>
                                <option value="disable" <?php selected(isset($options['heartbeat_mode']) ? $options['heartbeat_mode'] : 'default', 'disable'); ?>>
                                    <?php esc_html_e('完全禁用', 'folio'); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('Heartbeat用于自动保存和实时通知，禁用可减少服务器负载', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Google字体', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_google_fonts]" value="1" <?php checked(isset($options['disable_google_fonts']) ? $options['disable_google_fonts'] : 0, 1); ?>>
                                <?php esc_html_e('禁用Google字体加载', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('提升国内访问速度，但可能影响字体显示', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('版本号', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_version_strings]" value="1" <?php checked(isset($options['remove_version_strings']) ? $options['remove_version_strings'] : 1, 1); ?>>
                                <?php esc_html_e('移除CSS/JS文件的版本号参数', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('隐藏版本信息，提升安全性', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('图片优化', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('自动压缩', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_image_compression]" value="1" <?php checked(isset($options['enable_image_compression']) ? $options['enable_image_compression'] : 1, 1); ?>>
                                <?php esc_html_e('启用图片自动压缩', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('上传时自动压缩图片，减少文件大小', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('JPEG质量', 'folio'); ?></th>
                        <td>
                            <input type="range" name="<?php echo esc_attr($this->option_name); ?>[jpeg_quality]" value="<?php echo esc_attr(isset($options['jpeg_quality']) ? $options['jpeg_quality'] : 85); ?>" min="60" max="100" step="5" oninput="this.nextElementSibling.textContent = this.value + '%'">
                            <span><?php echo esc_html(isset($options['jpeg_quality']) ? $options['jpeg_quality'] : 85); ?>%</span>
                            <p class="description"><?php esc_html_e('JPEG图片压缩质量（60-100%，推荐85%）', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('PNG质量', 'folio'); ?></th>
                        <td>
                            <input type="range" name="<?php echo esc_attr($this->option_name); ?>[png_quality]" value="<?php echo esc_attr(isset($options['png_quality']) ? $options['png_quality'] : 90); ?>" min="70" max="100" step="5" oninput="this.nextElementSibling.textContent = this.value + '%'">
                            <span><?php echo esc_html(isset($options['png_quality']) ? $options['png_quality'] : 90); ?>%</span>
                            <p class="description"><?php esc_html_e('PNG图片压缩质量（70-100%，推荐90%）', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('最大尺寸', 'folio'); ?></th>
                        <td>
                            <label>
                                <?php esc_html_e('宽度：', 'folio'); ?>
                                <input type="number" name="<?php echo esc_attr($this->option_name); ?>[max_image_width]" value="<?php echo esc_attr(isset($options['max_image_width']) ? $options['max_image_width'] : 2048); ?>" min="800" max="4000" step="100" class="small-text">px
                            </label>
                            <label style="margin-left: 20px;">
                                <?php esc_html_e('高度：', 'folio'); ?>
                                <input type="number" name="<?php echo esc_attr($this->option_name); ?>[max_image_height]" value="<?php echo esc_attr(isset($options['max_image_height']) ? $options['max_image_height'] : 2048); ?>" min="600" max="4000" step="100" class="small-text">px
                            </label>
                            <p class="description"><?php esc_html_e('超过此尺寸的图片将被自动缩放', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('WebP支持', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_webp_conversion]" value="1" <?php checked(isset($options['enable_webp_conversion']) ? $options['enable_webp_conversion'] : 0, 1); ?>>
                                <?php esc_html_e('启用WebP格式支持', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('允许上传WebP格式图片（需要服务器支持）', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('元数据', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[strip_image_metadata]" value="1" <?php checked(isset($options['strip_image_metadata']) ? $options['strip_image_metadata'] : 1, 1); ?>>
                                <?php esc_html_e('移除图片EXIF数据', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('移除相机信息、GPS位置等隐私数据，减少文件大小', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('其他优化', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('评论样式', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_comment_styles]" value="1" <?php checked(isset($options['remove_comment_styles']) ? $options['remove_comment_styles'] : 0, 1); ?>>
                                <?php esc_html_e('移除评论相关样式', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('如果不使用评论功能，可以移除相关CSS', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('更新邮件通知', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_update_emails]" value="1" <?php checked(isset($options['disable_update_emails']) ? $options['disable_update_emails'] : 1, 1); ?>>
                                <?php esc_html_e('禁用自动更新邮件通知', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('不再收到WordPress/插件/主题更新的邮件', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('短代码段落', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_shortcode_p]" value="1" <?php checked(isset($options['remove_shortcode_p']) ? $options['remove_shortcode_p'] : 1, 1); ?>>
                                <?php esc_html_e('移除短代码周围的自动段落标签', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('避免短代码输出格式问题', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('安全设置', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('限制REST API', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[restrict_rest_api]" value="1" <?php checked(isset($options['restrict_rest_api']) ? $options['restrict_rest_api'] : 1, 1); ?>>
                                <?php esc_html_e('对未登录用户关闭 REST API', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('提升安全性，防止数据泄露（登录用户仍可使用）', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('禁用XML-RPC Pingback', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_xmlrpc_pingback]" value="1" <?php checked(isset($options['disable_xmlrpc_pingback']) ? $options['disable_xmlrpc_pingback'] : 1, 1); ?>>
                                <?php esc_html_e('关闭 XML-RPC Pingback 功能', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('防止 DDoS 攻击，提升安全性', 'folio'); ?></p>
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
                <h2><?php esc_html_e('用户体验增强', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('图片懒加载', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_lazy_load]" value="1" <?php checked(isset($options['enable_lazy_load']) ? $options['enable_lazy_load'] : 1, 1); ?>>
                                <?php esc_html_e('启用图片懒加载', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('图片滚动到可视区域时才加载，提升页面加载速度', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('返回顶部按钮', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_back_to_top]" value="1" <?php checked(isset($options['enable_back_to_top']) ? $options['enable_back_to_top'] : 1, 1); ?>>
                                <?php esc_html_e('显示返回顶部按钮', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('页面滚动超过300px时显示，点击平滑滚动到顶部', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('导航和阅读', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('面包屑导航', 'folio'); ?></th>
                        <td>
                            <p class="description">
                                <?php esc_html_e('面包屑导航已启用，在模板中使用以下代码显示：', 'folio'); ?><br>
                                <code>&lt;?php folio_breadcrumbs(); ?&gt;</code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('阅读时间估算', 'folio'); ?></th>
                        <td>
                            <p class="description">
                                <?php esc_html_e('阅读时间估算已启用，在模板中使用以下代码显示：', 'folio'); ?><br>
                                <code>&lt;?php folio_reading_time(); ?&gt;</code><br>
                                <?php esc_html_e('或获取分钟数：', 'folio'); ?><br>
                                <code>&lt;?php $minutes = folio_get_reading_time(); ?&gt;</code>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('使用说明', 'folio'); ?></h2>
                <div style="background: #f0f0f1; padding: 15px; border-radius: 4px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('如何使用这些功能', 'folio'); ?></h3>
                    
                    <h4>1. <?php esc_html_e('图片懒加载', 'folio'); ?></h4>
                    <p><?php esc_html_e('自动为所有图片添加懒加载，无需额外操作。', 'folio'); ?></p>
                    
                    <h4>2. <?php esc_html_e('返回顶部按钮', 'folio'); ?></h4>
                    <p><?php esc_html_e('自动显示在页面右下角，无需额外操作。', 'folio'); ?></p>
                    
                    <h4>3. <?php esc_html_e('面包屑导航', 'folio'); ?></h4>
                    <p><?php esc_html_e('在需要显示面包屑的位置（如文章顶部）添加：', 'folio'); ?></p>
                    <pre style="background: #fff; padding: 10px; border-radius: 4px; overflow-x: auto;">&lt;?php if (function_exists('folio_breadcrumbs')) folio_breadcrumbs(); ?&gt;</pre>
                    
                    <h4>4. <?php esc_html_e('阅读时间', 'folio'); ?></h4>
                    <p><?php esc_html_e('在文章元信息区域添加：', 'folio'); ?></p>
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
                    'name' => '默认API',
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
                <h2><?php esc_html_e('AI API 配置', 'folio'); ?></h2>
                <p class="description"><?php esc_html_e('配置多个AI服务用于自动生成文章摘要和关键词。支持OpenAI兼容接口（OpenAI、Azure OpenAI、通义千问、文心一言等）。系统会自动轮询使用可用的API。', 'folio'); ?></p>
                
                <div id="folio-ai-apis-container">
                    <?php foreach ($ai_apis as $index => $api) : ?>
                        <div class="folio-ai-api-item" data-index="<?php echo esc_attr($index); ?>">
                            <div class="folio-ai-api-header">
                                <h3><?php esc_html_e('API配置', 'folio'); ?> #<?php echo esc_html($index + 1); ?></h3>
                                <button type="button" class="button button-small folio-remove-api" style="color: #dc3232;"><?php esc_html_e('删除', 'folio'); ?></button>
                            </div>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('配置名称', 'folio'); ?></th>
                                    <td>
                                        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr(isset($api['name']) ? $api['name'] : ''); ?>" class="regular-text" placeholder="<?php esc_attr_e('例如：OpenAI主账号', 'folio'); ?>">
                                        <p class="description"><?php esc_html_e('用于标识此API配置', 'folio'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('启用', 'folio'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][enabled]" value="1" <?php checked(isset($api['enabled']) ? $api['enabled'] : 1, 1); ?>>
                                            <?php esc_html_e('启用此API配置', 'folio'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('API Endpoint', 'folio'); ?></th>
                                    <td>
                                        <input type="url" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][endpoint]" value="<?php echo esc_attr(isset($api['endpoint']) ? $api['endpoint'] : ''); ?>" class="large-text" placeholder="https://api.openai.com/v1/chat/completions">
                                        <p class="description">
                                            <?php esc_html_e('API接口地址', 'folio'); ?><br>
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
                                        <p class="description"><?php esc_html_e('你的API密钥', 'folio'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('模型名称', 'folio'); ?></th>
                                    <td>
                                        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][<?php echo esc_attr($index); ?>][model]" value="<?php echo esc_attr(isset($api['model']) ? $api['model'] : 'gpt-3.5-turbo'); ?>" class="regular-text" placeholder="gpt-3.5-turbo">
                                        <p class="description">
                                            <?php esc_html_e('使用的AI模型', 'folio'); ?><br>
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
                    <button type="button" class="button" id="folio-add-api"><?php esc_html_e('+ 添加API配置', 'folio'); ?></button>
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
                    
                    // 添加API配置
                    $('#folio-add-api').on('click', function() {
                        var html = '<div class="folio-ai-api-item" data-index="' + apiIndex + '">' +
                            '<div class="folio-ai-api-header">' +
                            '<h3><?php esc_html_e('API配置', 'folio'); ?> #' + (apiIndex + 1) + '</h3>' +
                            '<button type="button" class="button button-small folio-remove-api" style="color: #dc3232;"><?php esc_html_e('删除', 'folio'); ?></button>' +
                            '</div>' +
                            '<table class="form-table">' +
                            '<tr>' +
                            '<th scope="row"><?php esc_html_e('配置名称', 'folio'); ?></th>' +
                            '<td>' +
                            '<input type="text" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][' + apiIndex + '][name]" value="" class="regular-text" placeholder="<?php esc_attr_e('例如：OpenAI主账号', 'folio'); ?>">' +
                            '<p class="description"><?php esc_html_e('用于标识此API配置', 'folio'); ?></p>' +
                            '</td>' +
                            '</tr>' +
                            '<tr>' +
                            '<th scope="row"><?php esc_html_e('启用', 'folio'); ?></th>' +
                            '<td>' +
                            '<label>' +
                            '<input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][' + apiIndex + '][enabled]" value="1" checked>' +
                            '<?php esc_html_e('启用此API配置', 'folio'); ?>' +
                            '</label>' +
                            '</td>' +
                            '</tr>' +
                            '<tr>' +
                            '<th scope="row"><?php esc_html_e('API Endpoint', 'folio'); ?></th>' +
                            '<td>' +
                            '<input type="url" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][' + apiIndex + '][endpoint]" value="" class="large-text" placeholder="https://api.openai.com/v1/chat/completions">' +
                            '<p class="description"><?php esc_html_e('API接口地址', 'folio'); ?></p>' +
                            '</td>' +
                            '</tr>' +
                            '<tr>' +
                            '<th scope="row"><?php esc_html_e('API Key', 'folio'); ?></th>' +
                            '<td>' +
                            '<input type="password" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][' + apiIndex + '][key]" value="" class="large-text" placeholder="sk-...">' +
                            '<p class="description"><?php esc_html_e('你的API密钥', 'folio'); ?></p>' +
                            '</td>' +
                            '</tr>' +
                            '<tr>' +
                            '<th scope="row"><?php esc_html_e('模型名称', 'folio'); ?></th>' +
                            '<td>' +
                            '<input type="text" name="<?php echo esc_attr($this->option_name); ?>[ai_apis][' + apiIndex + '][model]" value="gpt-3.5-turbo" class="regular-text" placeholder="gpt-3.5-turbo">' +
                            '<p class="description"><?php esc_html_e('使用的AI模型', 'folio'); ?></p>' +
                            '</td>' +
                            '</tr>' +
                            '</table>' +
                            '</div>';
                        
                        $('#folio-ai-apis-container').append(html);
                        apiIndex++;
                    });
                    
                    // 删除API配置
                    $(document).on('click', '.folio-remove-api', function() {
                        if (confirm('<?php esc_html_e('确定要删除此API配置吗？', 'folio'); ?>')) {
                            $(this).closest('.folio-ai-api-item').remove();
                        }
                    });
                });
                </script>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('功能设置', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('启用AI生成', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[ai_enabled]" value="1" <?php checked(isset($options['ai_enabled']) ? $options['ai_enabled'] : 1, 1); ?>>
                                <?php esc_html_e('在文章编辑页面显示AI生成面板', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('默认自动生成', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[ai_auto_generate_default]" value="1" <?php checked(isset($options['ai_auto_generate_default']) ? $options['ai_auto_generate_default'] : 0, 1); ?>>
                                <?php esc_html_e('新文章默认启用"保存时自动生成"', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('测试连接', 'folio'); ?></h2>
                <p class="description"><?php esc_html_e('保存设置后，可以测试所有启用的AI API连接是否正常。系统会自动测试每个API配置。', 'folio'); ?></p>
                <button type="button" class="button" id="folio-test-ai-connection">
                    <?php esc_html_e('测试所有API', 'folio'); ?>
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
                <h2><?php esc_html_e('自定义代码', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('自定义 CSS', 'folio'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[custom_css]" rows="10" class="large-text code"><?php echo esc_textarea(isset($options['custom_css']) ? $options['custom_css'] : ''); ?></textarea>
                            <p class="description"><?php esc_html_e('添加自定义 CSS 代码，无需修改主题文件', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Header 代码', 'folio'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[header_code]" rows="5" class="large-text code"><?php echo esc_textarea(isset($options['header_code']) ? $options['header_code'] : ''); ?></textarea>
                            <p class="description"><?php esc_html_e('在 &lt;head&gt; 标签内插入代码（如 Google Analytics）', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Footer 代码', 'folio'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[footer_code]" rows="5" class="large-text code"><?php echo esc_textarea(isset($options['footer_code']) ? $options['footer_code'] : ''); ?></textarea>
                            <p class="description"><?php esc_html_e('在 &lt;/body&gt; 标签前插入代码', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('维护模式', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('启用维护模式', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[maintenance_mode]" value="1" <?php checked(isset($options['maintenance_mode']) ? $options['maintenance_mode'] : 0, 1); ?>>
                                <?php esc_html_e('对非管理员用户显示维护页面', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('维护提示信息', 'folio'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[maintenance_message]" rows="3" class="large-text"><?php echo esc_textarea(isset($options['maintenance_message']) ? $options['maintenance_message'] : __('网站正在维护中，请稍后访问。', 'folio')); ?></textarea>
                            <p class="description"><?php esc_html_e('向访客显示的维护信息，支持HTML标签', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('计划维护', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('启用计划维护', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[maintenance_scheduled]" value="1" <?php checked(isset($options['maintenance_scheduled']) ? $options['maintenance_scheduled'] : 0, 1); ?>>
                                <?php esc_html_e('按时间计划自动启用/禁用维护模式', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('开始时间', 'folio'); ?></th>
                        <td>
                            <input type="datetime-local" name="<?php echo esc_attr($this->option_name); ?>[maintenance_start_time]" value="<?php echo esc_attr(isset($options['maintenance_start_time']) ? date('Y-m-d\TH:i', strtotime($options['maintenance_start_time'])) : ''); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('维护开始时间（可选，留空表示立即开始）', 'folio'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('结束时间', 'folio'); ?></th>
                        <td>
                            <input type="datetime-local" name="<?php echo esc_attr($this->option_name); ?>[maintenance_end_time]" value="<?php echo esc_attr(isset($options['maintenance_end_time']) ? date('Y-m-d\TH:i', strtotime($options['maintenance_end_time'])) : ''); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('维护结束时间（可选，留空表示手动结束）', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('高级设置', 'folio'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('IP白名单', 'folio'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[maintenance_bypass_ips]" rows="5" class="large-text" placeholder="192.168.1.1&#10;10.0.0.0/8&#10;203.0.113.0/24"><?php echo esc_textarea(isset($options['maintenance_bypass_ips']) ? $options['maintenance_bypass_ips'] : ''); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('允许绕过维护模式的IP地址，每行一个', 'folio'); ?><br>
                                <?php esc_html_e('支持单个IP（如：192.168.1.1）和CIDR格式（如：192.168.1.0/24）', 'folio'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('访问日志', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[maintenance_log_enabled]" value="1" <?php checked(isset($options['maintenance_log_enabled']) ? $options['maintenance_log_enabled'] : 0, 1); ?>>
                                <?php esc_html_e('记录维护期间的访问日志', 'folio'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('记录访问维护页面的用户信息，用于分析和安全监控', 'folio'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php
                // 显示维护日志
                $logs = get_option('folio_maintenance_logs', array());
                if (!empty($logs) && isset($options['maintenance_log_enabled']) && $options['maintenance_log_enabled']):
                ?>
                <h3><?php esc_html_e('最近访问日志', 'folio'); ?></h3>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
                    <table class="widefat" style="margin: 0;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('时间', 'folio'); ?></th>
                                <th><?php esc_html_e('IP地址', 'folio'); ?></th>
                                <th><?php esc_html_e('访问页面', 'folio'); ?></th>
                                <th><?php esc_html_e('来源', 'folio'); ?></th>
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
                    <button type="button" class="button" onclick="if(confirm('确定要清除所有维护日志吗？')) { window.location.href='<?php echo wp_nonce_url(admin_url('themes.php?page=folio-theme-options&tab=advanced&action=clear_maintenance_logs'), 'clear_maintenance_logs'); ?>'; }">
                        <?php esc_html_e('清除日志', 'folio'); ?>
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
                <h2><?php esc_html_e('导出设置', 'folio'); ?></h2>
                <p><?php esc_html_e('导出当前所有主题设置为JSON文件，可用于备份或迁移到其他网站。', 'folio'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('folio_export_settings'); ?>
                    <button type="submit" name="folio_export_settings" class="button button-primary">
                        <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                        <?php esc_html_e('导出设置', 'folio'); ?>
                    </button>
                </form>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('导入设置', 'folio'); ?></h2>
                <p><?php esc_html_e('从之前导出的JSON文件恢复设置。', 'folio'); ?></p>
                <p class="description" style="color: #d63638;">
                    <strong><?php esc_html_e('警告：', 'folio'); ?></strong>
                    <?php esc_html_e('导入将覆盖当前所有设置，建议先导出备份。', 'folio'); ?>
                </p>
                
                <form method="post" action="" enctype="multipart/form-data">
                    <?php wp_nonce_field('folio_import_settings'); ?>
                    <p>
                        <input type="file" name="import_file" accept=".json" required>
                    </p>
                    <button type="submit" name="folio_import_settings" class="button button-secondary">
                        <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
                        <?php esc_html_e('导入设置', 'folio'); ?>
                    </button>
                </form>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('图片Alt文本优化', 'folio'); ?></h2>
                <p><?php esc_html_e('为现有图片批量添加Alt文本，提升SEO和可访问性。', 'folio'); ?></p>
                
                <button type="button" class="button button-primary" id="folio-batch-update-alt">
                    <span class="dashicons dashicons-images-alt2" style="margin-top: 3px;"></span>
                    <?php esc_html_e('批量更新Alt文本', 'folio'); ?>
                </button>
                <div id="folio-alt-update-result" style="margin-top: 10px;"></div>
                
                <script>
                document.getElementById('folio-batch-update-alt').addEventListener('click', function() {
                    const btn = this;
                    const result = document.getElementById('folio-alt-update-result');
                    
                    btn.disabled = true;
                    result.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span> <?php esc_html_e('正在更新...', 'folio'); ?>';
                    
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
                            result.innerHTML = '<span style="color: #dc3545;">✗ 更新失败</span>';
                        }
                        setTimeout(function() {
                            result.innerHTML = '';
                        }, 5000);
                    })
                    .catch(error => {
                        btn.disabled = false;
                        result.innerHTML = '<span style="color: #dc3545;">✗ 请求失败</span>';
                    });
                });
                </script>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('图片优化管理', 'folio'); ?></h2>
                <p><?php esc_html_e('批量优化现有图片，查看优化统计和节省的存储空间。', 'folio'); ?></p>
                
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <button type="button" class="button button-primary" id="folio-batch-optimize-images">
                        <span class="dashicons dashicons-format-image" style="margin-top: 3px;"></span>
                        <?php esc_html_e('批量优化图片', 'folio'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="folio-get-image-stats">
                        <span class="dashicons dashicons-chart-pie" style="margin-top: 3px;"></span>
                        <?php esc_html_e('查看统计', 'folio'); ?>
                    </button>
                </div>
                
                <div id="folio-image-optimization-result" style="margin-bottom: 15px;"></div>
                
                <div id="folio-image-stats-dashboard" style="display: none;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0;">优化统计</h4>
                            <div id="folio-image-stats-summary"></div>
                        </div>
                        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0;">最近优化</h4>
                            <div id="folio-recent-optimizations" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>
                
                <script>
                document.getElementById('folio-batch-optimize-images').addEventListener('click', function() {
                    const btn = this;
                    const result = document.getElementById('folio-image-optimization-result');
                    
                    btn.disabled = true;
                    btn.textContent = '优化中...';
                    result.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span> 正在批量优化图片...';
                    
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
                        btn.innerHTML = '<span class="dashicons dashicons-format-image" style="margin-top: 3px;"></span> 继续优化';
                        
                        if (data.success) {
                            result.innerHTML = '<div style="color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px;">✓ ' + data.data.message + '</div>';
                            
                            if (data.data.remaining > 0) {
                                result.innerHTML += '<p style="margin-top: 10px;">还有 ' + data.data.remaining + ' 张图片待优化，可以继续点击优化按钮。</p>';
                            } else {
                                result.innerHTML += '<p style="margin-top: 10px; color: #28a745;">所有图片已优化完成！</p>';
                                btn.style.display = 'none';
                            }
                        } else {
                            result.innerHTML = '<div style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px;">✗ 优化失败</div>';
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-format-image" style="margin-top: 3px;"></span> 批量优化图片';
                        result.innerHTML = '<div style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px;">✗ 请求失败</div>';
                    });
                });
                
                document.getElementById('folio-get-image-stats').addEventListener('click', function() {
                    const btn = this;
                    const dashboard = document.getElementById('folio-image-stats-dashboard');
                    
                    btn.disabled = true;
                    btn.textContent = '加载中...';
                    
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
                        btn.innerHTML = '<span class="dashicons dashicons-chart-pie" style="margin-top: 3px;"></span> 刷新统计';
                        
                        if (data.success) {
                            dashboard.style.display = 'block';
                            
                            // 显示统计摘要
                            document.getElementById('folio-image-stats-summary').innerHTML = `
                                <div>已优化图片: ${data.data.total_images} 张</div>
                                <div>节省空间: ${formatBytes(data.data.total_savings)}</div>
                                <div>待优化: ${data.data.unoptimized_count} 张</div>
                            `;
                            
                            // 显示最近优化
                            const recent = data.data.recent_optimizations.reverse();
                            document.getElementById('folio-recent-optimizations').innerHTML = recent.map(opt => `
                                <div style="margin-bottom: 8px; padding: 8px; background: white; border-radius: 3px; font-size: 12px;">
                                    <div><strong>${opt.filename}</strong></div>
                                    <div>节省: ${formatBytes(opt.savings)} (${opt.percentage}%)</div>
                                </div>
                            `).join('');
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-chart-pie" style="margin-top: 3px;"></span> 加载失败';
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
                <h2><?php esc_html_e('性能仪表盘', 'folio'); ?></h2>
                <p><?php esc_html_e('监控网站性能指标，包括页面加载时间、内存使用、数据库查询等。', 'folio'); ?></p>
                
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <button type="button" class="button button-primary" id="folio-load-performance-data">
                        <span class="dashicons dashicons-chart-line" style="margin-top: 3px;"></span>
                        <?php esc_html_e('加载性能数据', 'folio'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="folio-clear-performance-logs">
                        <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
                        <?php esc_html_e('清除日志', 'folio'); ?>
                    </button>
                </div>
                
                <div id="folio-performance-dashboard" style="display: none;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0;">性能统计</h4>
                            <div id="folio-performance-stats"></div>
                        </div>
                        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0;">最近请求</h4>
                            <div id="folio-recent-requests" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                    </div>
                    
                    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
                        <h4 style="margin: 0 0 10px 0; color: #856404;">慢查询警告</h4>
                        <div id="folio-slow-queries"></div>
                    </div>
                </div>
                
                <script>
                document.getElementById('folio-load-performance-data').addEventListener('click', function() {
                    const btn = this;
                    const dashboard = document.getElementById('folio-performance-dashboard');
                    
                    btn.disabled = true;
                    btn.textContent = '加载中...';
                    
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
                        btn.innerHTML = '<span class="dashicons dashicons-chart-line" style="margin-top: 3px;"></span> 刷新数据';
                        
                        if (data.success) {
                            dashboard.style.display = 'block';
                            
                            // 显示统计数据
                            const stats = data.data.stats;
                            document.getElementById('folio-performance-stats').innerHTML = `
                                <div style="margin-bottom: 8px;"><strong>前端页面性能</strong></div>
                                <div style="margin-bottom: 8px;">
                                    <strong>性能评分: </strong>
                                    <span style="color: ${stats.performance_score >= 80 ? '#28a745' : (stats.performance_score >= 60 ? '#ffc107' : '#dc3545')}; font-size: 16px;">
                                        ${stats.performance_score || 0}/100
                                    </span>
                                </div>
                                <div>平均加载时间: <span style="color: ${stats.avg_load_time > 2 ? '#dc3545' : '#28a745'}">${stats.avg_load_time ? stats.avg_load_time.toFixed(3) : 0}s</span></div>
                                <div>最大加载时间: <span style="color: ${stats.max_load_time > 3 ? '#dc3545' : '#ffc107'}">${stats.max_load_time ? stats.max_load_time.toFixed(3) : 0}s</span></div>
                                <div>平均内存使用: ${formatBytes(stats.avg_memory || 0)}</div>
                                <div>平均查询数: <span style="color: ${stats.avg_queries > 30 ? '#dc3545' : '#28a745'}">${Math.round(stats.avg_queries || 0)}</span></div>
                                <div>移动设备访问: ${stats.mobile_percentage || 0}%</div>
                                <div>优化问题: <span style="color: ${stats.optimization_issues > 0 ? '#dc3545' : '#28a745'}">${stats.optimization_issues || 0}</span></div>
                                <div>总页面访问: ${stats.total_requests || 0}</div>
                                <div style="margin-top: 8px; font-size: 11px; color: #666;">仅统计前端页面数据</div>
                            `;
                            
                            // 显示最近请求
                            const requests = data.data.logs.slice(-10).reverse();
                            document.getElementById('folio-recent-requests').innerHTML = requests.map(log => `
                                <div style="margin-bottom: 8px; padding: 8px; background: white; border-radius: 3px; font-size: 12px; border-left: 3px solid ${log.load_time > 3 ? '#dc3545' : (log.load_time > 2 ? '#ffc107' : '#28a745')};">
                                    <div><strong>${log.url}</strong> <span style="background: #e9ecef; padding: 2px 6px; border-radius: 10px; font-size: 10px;">${log.page_type || 'unknown'}</span></div>
                                    <div>⏱️ ${log.load_time.toFixed(3)}s | 💾 ${formatBytes(log.memory_usage)} | 🗄️ ${log.db_queries} queries</div>
                                    ${log.is_mobile ? '<div style="color: #6c757d; font-size: 10px;">[移动设备]</div>' : ''}
                                    ${log.optimization_suggestions && log.optimization_suggestions.length > 0 ? 
                                        '<div style="color: #dc3545; font-size: 10px;">[' + log.optimization_suggestions.length + ' 个优化建议]</div>' : ''}
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
                                document.getElementById('folio-slow-queries').innerHTML = '<div style="color: #28a745;">暂无慢查询记录</div>';
                            }
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.innerHTML = '<span class="dashicons dashicons-chart-line" style="margin-top: 3px;"></span> 加载失败';
                    });
                });
                
                document.getElementById('folio-clear-performance-logs').addEventListener('click', function() {
                    if (!confirm('确定要清除所有性能日志吗？')) return;
                    
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
                            alert('性能日志已清除');
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
                <h2><?php esc_html_e('重置设置', 'folio'); ?></h2>
                <p><?php esc_html_e('将所有设置恢复为默认值。', 'folio'); ?></p>
                <p class="description" style="color: #d63638;">
                    <strong><?php esc_html_e('警告：', 'folio'); ?></strong>
                    <?php esc_html_e('此操作不可恢复，建议先导出备份。', 'folio'); ?>
                </p>
                
                <button type="button" class="button button-secondary" id="folio-reset-settings">
                    <span class="dashicons dashicons-image-rotate" style="margin-top: 3px;"></span>
                    <?php esc_html_e('重置所有设置', 'folio'); ?>
                </button>
                
                <script>
                document.getElementById('folio-reset-settings').addEventListener('click', function() {
                    if (confirm('<?php echo esc_js(__('确定要重置所有设置吗？此操作不可恢复！', 'folio')); ?>')) {
                        if (confirm('<?php echo esc_js(__('最后确认：真的要重置吗？', 'folio')); ?>')) {
                            window.location.href = '<?php echo wp_nonce_url(admin_url('themes.php?page=folio-theme-options&tab=backup&action=reset'), 'folio_reset_settings'); ?>';
                        }
                    }
                });
                </script>
            </div>
            
            <div class="folio-section">
                <h2><?php esc_html_e('当前设置信息', 'folio'); ?></h2>
                <table class="widefat">
                    <tr>
                        <td><strong><?php esc_html_e('选项名称', 'folio'); ?></strong></td>
                        <td><code>folio_theme_options</code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('数据库表', 'folio'); ?></strong></td>
                        <td><code>wp_options</code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('设置项数量', 'folio'); ?></strong></td>
                        <td><?php echo count($options); ?> 项</td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('数据大小', 'folio'); ?></strong></td>
                        <td><?php echo size_format(strlen(serialize($options))); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('最后更新', 'folio'); ?></strong></td>
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
            echo '<div class="notice notice-success"><p>' . esc_html__('设置已重置为默认值', 'folio') . '</p></div>';
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
            add_settings_error('folio_theme_options', 'import_error', __('文件上传失败', 'folio'));
            return;
        }
        
        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error('folio_theme_options', 'import_error', __('无效的JSON文件', 'folio'));
            return;
        }
        
        if (!isset($import_data['options']) || !is_array($import_data['options'])) {
            add_settings_error('folio_theme_options', 'import_error', __('文件格式不正确', 'folio'));
            return;
        }
        
        // 导入设置
        update_option('folio_theme_options', $import_data['options']);
        
        add_settings_error('folio_theme_options', 'import_success', __('设置导入成功！', 'folio'), 'success');
        
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
        wp_send_json_error(array('message' => __('权限不足', 'folio')));
    }
    
    // 刷新重写规则
    flush_rewrite_rules();
    
    // 重置标记
    delete_option('folio_category_prefix_flushed');
    
    wp_send_json_success(array('message' => __('重写规则已刷新', 'folio')));
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
        'title' => '<span class="ab-icon dashicons dashicons-admin-generic"></span> Folio 设置',
        'href'  => admin_url('themes.php?page=folio-theme-options'),
        'meta'  => array(
            'title' => __('Folio 主题设置', 'folio'),
        ),
    ));
}
add_action('admin_bar_menu', 'folio_add_admin_bar_link', 100);
