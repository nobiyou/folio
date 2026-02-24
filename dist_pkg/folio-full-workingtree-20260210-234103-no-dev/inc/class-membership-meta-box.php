<?php
/**
 * Enhanced Membership Meta Box
 * 
 * 增强的文章会员设置元框 - 提供完整的文章级别会员保护设置界面
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Membership_Meta_Box {
    
    /**
     * 预览模式常量
     */
    const PREVIEW_AUTO = 'auto';
    const PREVIEW_PERCENTAGE = 'percentage';
    const PREVIEW_CUSTOM = 'custom';
    const PREVIEW_NONE = 'none';
    
    /**
     * 保护级别常量
     */
    const PROTECTION_CONTENT = 'content';
    const PROTECTION_FULL = 'full';
    
    /**
     * 构造函数
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_folio_preview_content', array($this, 'ajax_preview_content'));
        add_action('wp_ajax_folio_bulk_protection', array($this, 'ajax_bulk_protection'));
        add_action('wp_ajax_folio_load_recent_posts', array($this, 'ajax_load_recent_posts'));
        add_action('admin_footer', array($this, 'add_bulk_protection_modal'));
    }
    
    /**
     * 添加元框
     */
    public function add_meta_boxes() {
        add_meta_box(
            'folio_membership_protection',
            sprintf(
                '<span class="dashicons dashicons-shield-alt"></span> %s',
                esc_html__('Membership Protection Settings', 'folio')
            ),
            array($this, 'render_meta_box'),
            'post',
            'side',
            'high'
        );
    }
    
    /**
     * 加载管理脚本和样式
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        wp_enqueue_script(
            'folio-membership-metabox',
            get_template_directory_uri() . '/assets/js/membership-metabox.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('folio-membership-metabox', 'folioMetaBox', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('folio_membership_metabox'),
            'strings' => array(
                'preview_loading' => __('Generating preview...', 'folio'),
                'preview_error' => __('Failed to generate preview', 'folio'),
                'bulk_success' => __('Bulk settings applied', 'folio'),
                'bulk_error' => __('Bulk settings failed', 'folio'),
                'chars' => __('chars', 'folio'),
                'original_length' => __('Original length', 'folio'),
                'preview_length' => __('Preview length', 'folio'),
                'preview_result' => __('Preview Result', 'folio'),
                'generate_preview' => __('Generate preview', 'folio'),
                'loading' => __('Loading...', 'folio'),
                'load_failed' => __('Load failed', 'folio'),
                'membership_level' => __('Membership level', 'folio'),
                'vip_member' => __('VIP Member', 'folio'),
                'svip_member' => __('SVIP Member', 'folio'),
                'new_membership_level' => __('New membership level', 'folio'),
                'preview_mode' => __('Preview mode', 'folio'),
                'preview_auto' => __('Auto preview', 'folio'),
                'preview_percentage' => __('Percentage preview', 'folio'),
                'preview_custom' => __('Custom preview', 'folio'),
                'preview_none' => __('No preview', 'folio'),
                'preview_length_auto' => __('Preview length (auto mode)', 'folio'),
                'preview_percentage_mode' => __('Preview percentage (percentage mode)', 'folio'),
                'select_action_type' => __('Please select an action type', 'folio'),
                'select_posts' => __('Please select posts to operate', 'folio'),
                'executing' => __('Executing...', 'folio'),
                'execute_action' => __('Execute action', 'folio'),
                'preset_applied_prefix' => __('Applied ', 'folio'),
                'preset_applied_suffix' => __(' protection preset', 'folio'),
            )
        ));
        
        wp_enqueue_style(
            'folio-membership-metabox',
            get_template_directory_uri() . '/assets/css/membership-metabox.css',
            array(),
            '1.0.0'
        );
    }
    
    /**
     * 渲染元框界面
     */
    public function render_meta_box($post) {
        wp_nonce_field('folio_membership_meta', 'folio_membership_nonce');
        
        $protection_info = $this->get_protection_info($post->ID);
        ?>
        <div class="folio-membership-metabox">
            <!-- 主要设置 -->
            <div class="folio-setting-group">
                <label class="folio-toggle-label">
                    <input type="checkbox" name="folio_premium_content" value="1" 
                           <?php checked($protection_info['is_protected'], true); ?>
                           class="folio-protection-toggle">
                    <span class="folio-toggle-slider"></span>
                    <strong><?php esc_html_e('Enable Membership Protection', 'folio'); ?></strong>
                </label>
                <p class="description"><?php esc_html_e('Mark this post as membership-only content', 'folio'); ?></p>
            </div>
            
            <!-- 保护设置面板 -->
            <div id="folio-protection-panel" class="folio-protection-panel" 
                 style="<?php echo $protection_info['is_protected'] ? '' : 'display:none;'; ?>">
                
                <!-- 会员等级设置 -->
                <div class="folio-setting-group">
                    <label for="folio_required_level"><strong><?php esc_html_e('Required Membership Level', 'folio'); ?></strong></label>
                    <div class="folio-level-selector">
                        <label class="folio-level-option <?php echo $protection_info['required_level'] === 'vip' ? 'active' : ''; ?>">
                            <input type="radio" name="folio_required_level" value="vip" 
                                   <?php checked($protection_info['required_level'], 'vip'); ?>>
                            <span class="folio-level-badge vip">
                                <span class="dashicons dashicons-star-filled"></span>
                                VIP
                            </span>
                        </label>
                        <label class="folio-level-option <?php echo $protection_info['required_level'] === 'svip' ? 'active' : ''; ?>">
                            <input type="radio" name="folio_required_level" value="svip" 
                                   <?php checked($protection_info['required_level'], 'svip'); ?>>
                            <span class="folio-level-badge svip">
                                <span class="dashicons dashicons-awards"></span>
                                SVIP
                            </span>
                        </label>
                    </div>
                </div>
                
                <!-- 预览模式设置 -->
                <div class="folio-setting-group">
                    <label for="folio_preview_mode"><strong><?php esc_html_e('Content Preview Mode', 'folio'); ?></strong></label>
                    <select name="folio_preview_mode" id="folio_preview_mode" class="folio-select">
                        <option value="<?php echo self::PREVIEW_AUTO; ?>" <?php selected($protection_info['preview_mode'], self::PREVIEW_AUTO); ?>>
                            <?php esc_html_e('🔤 Auto Preview - Show first N characters', 'folio'); ?>
                        </option>
                        <option value="<?php echo self::PREVIEW_PERCENTAGE; ?>" <?php selected($protection_info['preview_mode'], self::PREVIEW_PERCENTAGE); ?>>
                            <?php esc_html_e('📊 Percentage Preview - Show X% of content', 'folio'); ?>
                        </option>
                        <option value="<?php echo self::PREVIEW_CUSTOM; ?>" <?php selected($protection_info['preview_mode'], self::PREVIEW_CUSTOM); ?>>
                            <?php esc_html_e('✏️ Custom Preview - Use custom preview text', 'folio'); ?>
                        </option>
                        <option value="<?php echo self::PREVIEW_NONE; ?>" <?php selected($protection_info['preview_mode'], self::PREVIEW_NONE); ?>>
                            <?php esc_html_e('🚫 No Preview - Hide content completely', 'folio'); ?>
                        </option>
                    </select>
                </div>
                
                <!-- 预览设置详情 -->
                <div class="folio-preview-settings">
                    <!-- 自动预览设置 -->
                    <div id="folio-auto-settings" class="folio-preview-option" 
                         style="<?php echo $protection_info['preview_mode'] === self::PREVIEW_AUTO ? '' : 'display:none;'; ?>">
                        <label for="folio_preview_length"><?php esc_html_e('Preview Length', 'folio'); ?></label>
                        <div class="folio-range-input">
                            <input type="range" name="folio_preview_length" id="folio_preview_length" 
                                   value="<?php echo esc_attr($protection_info['preview_length']); ?>" 
                                   min="50" max="1000" step="50" class="folio-range">
                            <span class="folio-range-value"><?php echo esc_html($protection_info['preview_length']); ?> <?php esc_html_e('chars', 'folio'); ?></span>
                        </div>
                        <p class="description"><?php esc_html_e('Recommended: 200-500 characters', 'folio'); ?></p>
                    </div>
                    
                    <!-- 百分比预览设置 -->
                    <div id="folio-percentage-settings" class="folio-preview-option" 
                         style="<?php echo $protection_info['preview_mode'] === self::PREVIEW_PERCENTAGE ? '' : 'display:none;'; ?>">
                        <label for="folio_preview_percentage"><?php esc_html_e('Preview Percentage', 'folio'); ?></label>
                        <div class="folio-range-input">
                            <input type="range" name="folio_preview_percentage" id="folio_preview_percentage" 
                                   value="<?php echo esc_attr($protection_info['preview_percentage']); ?>" 
                                   min="10" max="80" step="5" class="folio-range">
                            <span class="folio-range-value"><?php echo esc_html($protection_info['preview_percentage']); ?>%</span>
                        </div>
                        <p class="description"><?php esc_html_e('Recommended: 20%-50%', 'folio'); ?></p>
                    </div>
                    
                    <!-- 自定义预览设置 -->
                    <div id="folio-custom-settings" class="folio-preview-option" 
                         style="<?php echo $protection_info['preview_mode'] === self::PREVIEW_CUSTOM ? '' : 'display:none;'; ?>">
                        <label for="folio_preview_custom"><?php esc_html_e('Custom Preview Content', 'folio'); ?></label>
                        <textarea name="folio_preview_custom" id="folio_preview_custom" 
                                  rows="4" class="folio-textarea" 
                                  placeholder="<?php echo esc_attr__('Enter custom preview text, HTML is supported...', 'folio'); ?>"><?php echo esc_textarea($protection_info['preview_custom']); ?></textarea>
                        <p class="description"><?php esc_html_e('HTML is supported. Recommended: 100-300 characters', 'folio'); ?></p>
                    </div>
                </div>
                
                <!-- 实时预览 -->
                <div class="folio-setting-group">
                    <button type="button" id="folio-preview-btn" class="button button-secondary">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e('Generate Preview', 'folio'); ?>
                    </button>
                    <div id="folio-preview-result" class="folio-preview-result" style="display:none;">
                        <h4><?php esc_html_e('Preview Result:', 'folio'); ?></h4>
                        <div class="folio-preview-content"></div>
                    </div>
                </div>
                
                <!-- 高级选项 -->
                <details class="folio-advanced-settings">
                    <summary>
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_html_e('Advanced Settings', 'folio'); ?>
                    </summary>
                    <div class="folio-advanced-content">
                        <label class="folio-checkbox-label">
                            <input type="checkbox" name="folio_seo_visible" value="1" 
                                   <?php checked($protection_info['seo_visible'], true); ?>>
                            <span class="checkmark"></span>
                            <?php esc_html_e('Visible to Search Engines', 'folio'); ?>
                            <p class="description"><?php esc_html_e('Allow search engines to index preview content', 'folio'); ?></p>
                        </label>
                        
                        <label class="folio-checkbox-label">
                            <input type="checkbox" name="folio_rss_include" value="1" 
                                   <?php checked($protection_info['rss_include'], true); ?>>
                            <span class="checkmark"></span>
                            <?php esc_html_e('Include in RSS Feed', 'folio'); ?>
                            <p class="description"><?php esc_html_e('Show preview content in RSS feed', 'folio'); ?></p>
                        </label>
                        
                        <label class="folio-checkbox-label">
                            <input type="checkbox" name="folio_api_accessible" value="1" 
                                   <?php checked($protection_info['api_accessible'] ?? false, true); ?>>
                            <span class="checkmark"></span>
                            <?php esc_html_e('API Access Control', 'folio'); ?>
                            <p class="description"><?php esc_html_e('Apply protection rules to REST API as well', 'folio'); ?></p>
                        </label>
                    </div>
                </details>
                
                <!-- 快速操作 -->
                <div class="folio-quick-actions">
                    <button type="button" class="button button-small" onclick="folioSetPreset('light')">
                        <?php esc_html_e('Light Protection', 'folio'); ?>
                    </button>
                    <button type="button" class="button button-small" onclick="folioSetPreset('medium')">
                        <?php esc_html_e('Medium Protection', 'folio'); ?>
                    </button>
                    <button type="button" class="button button-small" onclick="folioSetPreset('strict')">
                        <?php esc_html_e('Strict Protection', 'folio'); ?>
                    </button>
                </div>
            </div>
            
            <!-- 批量操作入口 -->
            <div class="folio-bulk-actions">
                <button type="button" id="folio-bulk-btn" class="button button-link">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php esc_html_e('Bulk Protection Settings', 'folio'); ?>
                </button>
            </div>
            
            <!-- 帮助信息 -->
            <div class="folio-help-section">
                <details>
                    <summary>
                        <span class="dashicons dashicons-editor-help"></span>
                        <?php esc_html_e('Usage Help', 'folio'); ?>
                    </summary>
                    <div class="folio-help-content">
                        <h4><?php esc_html_e('Shortcode Usage:', 'folio'); ?></h4>
                        <code>[vip_content]<?php esc_html_e('VIP exclusive content', 'folio'); ?>[/vip_content]</code><br>
                        <code>[membership_prompt level="vip"]</code><br>
                        <code>[upgrade_button]</code>
                        
                        <h4><?php esc_html_e('Preview Mode Description:', 'folio'); ?></h4>
                        <ul>
                            <li><strong><?php esc_html_e('Auto Preview:', 'folio'); ?></strong><?php esc_html_e('Show a specified number of characters from the beginning of the post', 'folio'); ?></li>
                            <li><strong><?php esc_html_e('Percentage Preview:', 'folio'); ?></strong><?php esc_html_e('Show a specified percentage of post content', 'folio'); ?></li>
                            <li><strong><?php esc_html_e('Custom Preview:', 'folio'); ?></strong><?php esc_html_e('Show your custom preview text', 'folio'); ?></li>
                            <li><strong><?php esc_html_e('No Preview:', 'folio'); ?></strong><?php esc_html_e('Hide post content completely', 'folio'); ?></li>
                        </ul>
                    </div>
                </details>
            </div>
        </div>
        
        <script>
        // 内联JavaScript用于基本交互
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.querySelector('.folio-protection-toggle');
            const panel = document.getElementById('folio-protection-panel');
            const previewMode = document.getElementById('folio_preview_mode');
            
            // 切换保护面板
            if (toggle && panel) {
                toggle.addEventListener('change', function() {
                    panel.style.display = this.checked ? 'block' : 'none';
                });
            }
            
            // 切换预览设置
            if (previewMode) {
                previewMode.addEventListener('change', function() {
                    const mode = this.value;
                    document.querySelectorAll('.folio-preview-option').forEach(function(el) {
                        el.style.display = 'none';
                    });
                    
                    const targetId = 'folio-' + mode.replace('_', '-') + '-settings';
                    const target = document.getElementById(targetId);
                    if (target) {
                        target.style.display = 'block';
                    }
                });
            }
            
            // 范围滑块实时更新
            document.querySelectorAll('.folio-range').forEach(function(slider) {
                    const valueSpan = slider.parentNode.querySelector('.folio-range-value');
                    if (valueSpan) {
                        slider.addEventListener('input', function() {
                        const unit = this.id.includes('length') ? ' <?php echo esc_js(__('chars', 'folio')); ?>' : '%';
                        valueSpan.textContent = this.value + unit;
                    });
                }
            });
            
            // 会员等级选择
            document.querySelectorAll('.folio-level-option input').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.folio-level-option').forEach(function(option) {
                        option.classList.remove('active');
                    });
                    if (this.checked) {
                        this.closest('.folio-level-option').classList.add('active');
                    }
                });
            });
        });
        
        // 预设配置函数
        function folioSetPreset(type) {
            const previewMode = document.getElementById('folio_preview_mode');
            const previewLength = document.getElementById('folio_preview_length');
            const previewPercentage = document.getElementById('folio_preview_percentage');
            const seoVisible = document.querySelector('input[name="folio_seo_visible"]');
            const rssInclude = document.querySelector('input[name="folio_rss_include"]');
            
            switch(type) {
                case 'light':
                    previewMode.value = '<?php echo self::PREVIEW_PERCENTAGE; ?>';
                    previewPercentage.value = 50;
                    seoVisible.checked = true;
                    rssInclude.checked = true;
                    break;
                case 'medium':
                    previewMode.value = '<?php echo self::PREVIEW_AUTO; ?>';
                    previewLength.value = 300;
                    seoVisible.checked = true;
                    rssInclude.checked = false;
                    break;
                case 'strict':
                    previewMode.value = '<?php echo self::PREVIEW_NONE; ?>';
                    seoVisible.checked = false;
                    rssInclude.checked = false;
                    break;
            }
            
            // 触发change事件更新UI
            previewMode.dispatchEvent(new Event('change'));
            if (previewLength) previewLength.dispatchEvent(new Event('input'));
            if (previewPercentage) previewPercentage.dispatchEvent(new Event('input'));
        }
        </script>
        <?php
    }
    
    /**
     * 获取文章保护信息
     */
    private function get_protection_info($post_id) {
        return array(
            'is_protected' => get_post_meta($post_id, '_folio_premium_content', true) == '1',
            'required_level' => get_post_meta($post_id, '_folio_required_level', true) ?: 'vip',
            'preview_mode' => get_post_meta($post_id, '_folio_preview_mode', true) ?: self::PREVIEW_AUTO,
            'preview_length' => get_post_meta($post_id, '_folio_preview_length', true) ?: 200,
            'preview_percentage' => get_post_meta($post_id, '_folio_preview_percentage', true) ?: 30,
            'preview_custom' => get_post_meta($post_id, '_folio_preview_custom', true) ?: '',
            'protection_level' => get_post_meta($post_id, '_folio_protection_level', true) ?: self::PROTECTION_CONTENT,
            'seo_visible' => get_post_meta($post_id, '_folio_seo_visible', true) == '1',
            'rss_include' => get_post_meta($post_id, '_folio_rss_include', true) == '1',
            'api_accessible' => get_post_meta($post_id, '_folio_api_accessible', true) == '1'
        );
    }
    
    /**
     * 保存元数据
     */
    public function save_meta_data($post_id) {
        // 验证nonce
        if (!isset($_POST['folio_membership_nonce']) || 
            !wp_verify_nonce($_POST['folio_membership_nonce'], 'folio_membership_meta')) {
            return;
        }

        // 检查自动保存
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 检查权限
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // 保存基本设置
        $is_premium = isset($_POST['folio_premium_content']) ? 1 : 0;
        $required_level = sanitize_text_field($_POST['folio_required_level'] ?? 'vip');
        
        // 保存预览设置
        $preview_mode = sanitize_text_field($_POST['folio_preview_mode'] ?? self::PREVIEW_AUTO);
        $preview_length = max(50, min(1000, intval($_POST['folio_preview_length'] ?? 200)));
        $preview_percentage = max(10, min(80, intval($_POST['folio_preview_percentage'] ?? 30)));
        $preview_custom = wp_kses_post($_POST['folio_preview_custom'] ?? '');
        
        // 保存高级设置
        $seo_visible = isset($_POST['folio_seo_visible']) ? 1 : 0;
        $rss_include = isset($_POST['folio_rss_include']) ? 1 : 0;
        $api_accessible = isset($_POST['folio_api_accessible']) ? 1 : 0;

        // 更新所有元数据
        update_post_meta($post_id, '_folio_premium_content', $is_premium);
        update_post_meta($post_id, '_folio_required_level', $required_level);
        update_post_meta($post_id, '_folio_preview_mode', $preview_mode);
        update_post_meta($post_id, '_folio_preview_length', $preview_length);
        update_post_meta($post_id, '_folio_preview_percentage', $preview_percentage);
        update_post_meta($post_id, '_folio_preview_custom', $preview_custom);
        update_post_meta($post_id, '_folio_protection_level', self::PROTECTION_CONTENT);
        update_post_meta($post_id, '_folio_seo_visible', $seo_visible);
        update_post_meta($post_id, '_folio_rss_include', $rss_include);
        update_post_meta($post_id, '_folio_api_accessible', $api_accessible);
        
        // 清除相关缓存
        wp_cache_delete($post_id, 'post_meta');
        delete_transient('folio_protection_' . $post_id);
        
        // 触发钩子
        do_action('folio_membership_protection_saved', $post_id, $is_premium, $required_level);
    }
    
    /**
     * AJAX: 生成内容预览
     */
    public function ajax_preview_content() {
        check_ajax_referer('folio_membership_metabox', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $preview_mode = sanitize_text_field($_POST['preview_mode'] ?? self::PREVIEW_AUTO);
        $preview_length = intval($_POST['preview_length'] ?? 200);
        $preview_percentage = intval($_POST['preview_percentage'] ?? 30);
        $preview_custom = wp_kses_post($_POST['preview_custom'] ?? '');
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(__('Post not found', 'folio'));
        }
        
        $content = $post->post_content;
        $preview_html = $this->generate_preview_html($content, $preview_mode, array(
            'length' => $preview_length,
            'percentage' => $preview_percentage,
            'custom' => $preview_custom
        ));
        
        wp_send_json_success(array(
            'preview' => $preview_html,
            'original_length' => mb_strlen(strip_tags($content)),
            'preview_length' => mb_strlen(strip_tags($preview_html))
        ));
    }
    
    /**
     * 生成预览HTML
     */
    private function generate_preview_html($content, $mode, $settings) {
        switch ($mode) {
            case self::PREVIEW_AUTO:
                $text = strip_tags($content);
                $preview = mb_substr($text, 0, $settings['length']);
                if (mb_strlen($text) > $settings['length']) {
                    $preview .= '...';
                }
                return '<p>' . esc_html($preview) . '</p>';
                
            case self::PREVIEW_PERCENTAGE:
                $text = strip_tags($content);
                $length = intval(mb_strlen($text) * $settings['percentage'] / 100);
                $preview = mb_substr($text, 0, $length);
                if ($length < mb_strlen($text)) {
                    $preview .= '...';
                }
                return '<p>' . esc_html($preview) . '</p>';
                
            case self::PREVIEW_CUSTOM:
                return wpautop($settings['custom']);
                
            case self::PREVIEW_NONE:
            default:
                return '<p class="folio-no-preview">' . esc_html__('This post is exclusive to members. Please log in and upgrade your membership to view it.', 'folio') . '</p>';
        }
    }
    
    /**
     * AJAX: 批量保护设置
     */
    public function ajax_bulk_protection() {
        check_ajax_referer('folio_membership_metabox', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $post_ids = array_map('intval', $_POST['post_ids'] ?? array());
        $settings = $_POST['settings'] ?? array();
        
        if (empty($post_ids)) {
            wp_send_json_error(__('Please select posts to operate on', 'folio'));
        }
        
        $updated = 0;
        foreach ($post_ids as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                continue;
            }
            
            switch ($action) {
                case 'enable_protection':
                    update_post_meta($post_id, '_folio_premium_content', 1);
                    update_post_meta($post_id, '_folio_required_level', $settings['level'] ?? 'vip');
                    $updated++;
                    break;
                    
                case 'disable_protection':
                    update_post_meta($post_id, '_folio_premium_content', 0);
                    $updated++;
                    break;
                    
                case 'change_level':
                    update_post_meta($post_id, '_folio_required_level', $settings['level'] ?? 'vip');
                    $updated++;
                    break;
                    
                case 'set_preview_mode':
                    update_post_meta($post_id, '_folio_preview_mode', $settings['preview_mode'] ?? self::PREVIEW_AUTO);
                    if (isset($settings['preview_length'])) {
                        update_post_meta($post_id, '_folio_preview_length', intval($settings['preview_length']));
                    }
                    if (isset($settings['preview_percentage'])) {
                        update_post_meta($post_id, '_folio_preview_percentage', intval($settings['preview_percentage']));
                    }
                    $updated++;
                    break;
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully updated settings for %d posts', 'folio'), $updated),
            'updated' => $updated
        ));
    }
    
    /**
     * AJAX: 加载最近文章
     */
    public function ajax_load_recent_posts() {
        check_ajax_referer('folio_membership_metabox', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'folio'));
        }
        
        $posts = get_posts(array(
            'numberposts' => 20,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $html = '';
        foreach ($posts as $post) {
            $is_protected = get_post_meta($post->ID, '_folio_premium_content', true) == '1';
            $required_level = get_post_meta($post->ID, '_folio_required_level', true) ?: 'vip';
            
            $status_text = $is_protected ? sprintf(__('Protected (%s)', 'folio'), strtoupper($required_level)) : __('Not Protected', 'folio');
            $status_class = $is_protected ? 'protected' : 'unprotected';
            
            $html .= '<div class="folio-post-item">';
            $html .= '<input type="checkbox" value="' . $post->ID . '">';
            $html .= '<div>';
            $html .= '<div class="folio-post-title">' . esc_html($post->post_title) . '</div>';
            $html .= '<div class="folio-post-meta">' . esc_html($status_text) . ' | ' . get_the_date('Y-m-d', $post) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        if (empty($html)) {
            $html = '<div class="folio-no-posts">' . esc_html__('No posts found', 'folio') . '</div>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * 添加批量操作模态框
     */
    public function add_bulk_protection_modal() {
        $screen = get_current_screen();
        if ($screen->id !== 'post') {
            return;
        }
        ?>
        <div id="folio-bulk-modal" class="folio-modal" style="display:none;">
            <div class="folio-modal-content">
                <div class="folio-modal-header">
                    <h3><?php esc_html_e('Bulk Membership Protection Settings', 'folio'); ?></h3>
                    <button type="button" class="folio-modal-close">&times;</button>
                </div>
                <div class="folio-modal-body">
                    <p><?php esc_html_e('Select posts and settings for bulk operation:', 'folio'); ?></p>
                    
                    <div class="folio-bulk-section">
                        <h4><?php esc_html_e('Select Posts', 'folio'); ?></h4>
                        <div class="folio-post-selector">
                            <button type="button" class="button" onclick="folioLoadRecentPosts()"><?php esc_html_e('Load Recent Posts', 'folio'); ?></button>
                            <div id="folio-post-list"></div>
                        </div>
                    </div>
                    
                    <div class="folio-bulk-section">
                        <h4><?php esc_html_e('Bulk Actions', 'folio'); ?></h4>
                        <select id="folio-bulk-action">
                            <option value=""><?php esc_html_e('Select action...', 'folio'); ?></option>
                            <option value="enable_protection"><?php esc_html_e('Enable Membership Protection', 'folio'); ?></option>
                            <option value="disable_protection"><?php esc_html_e('Disable Membership Protection', 'folio'); ?></option>
                            <option value="change_level"><?php esc_html_e('Change Membership Level', 'folio'); ?></option>
                            <option value="set_preview_mode"><?php esc_html_e('Set Preview Mode', 'folio'); ?></option>
                        </select>
                        
                        <div id="folio-bulk-settings" style="display:none; margin-top:15px;">
                            <!-- 动态设置选项 -->
                        </div>
                    </div>
                </div>
                <div class="folio-modal-footer">
                    <button type="button" class="button button-primary" onclick="folioExecuteBulkAction()"><?php esc_html_e('Execute Action', 'folio'); ?></button>
                    <button type="button" class="button" onclick="folioCloseBulkModal()"><?php esc_html_e('Cancel', 'folio'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}

// 初始化增强元框
new folio_Membership_Meta_Box();
