<?php
/**
 * Enhanced Premium Content System
 * 
 * 增强版会员专属内容系统 - 主要负责短代码和编辑器功能
 * 
 * 注意：文章内容过滤和RSS/API保护已移至 Article Protection Manager
 * 此类主要负责：
 * - 短代码注册（vip_content, svip_content等）
 * - 编辑器按钮
 * - 文章元数据保存
 * - AJAX处理
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Premium_Content_Enhanced {

    // 预览模式常量
    const PREVIEW_AUTO = 'auto';
    const PREVIEW_PERCENTAGE = 'percentage';
    const PREVIEW_CUSTOM = 'custom';
    const PREVIEW_NONE = 'none';

    // 保护级别常量
    const PROTECTION_CONTENT = 'content';
    const PROTECTION_FULL = 'full';

    public function __construct() {
        // 注册增强版短代码
        add_shortcode('vip_content', array($this, 'vip_content_shortcode'));
        add_shortcode('svip_content', array($this, 'svip_content_shortcode'));
        add_shortcode('member_content', array($this, 'member_content_shortcode'));
        add_shortcode('membership_card', array($this, 'membership_card_shortcode'));
        add_shortcode('upgrade_prompt', array($this, 'upgrade_prompt_shortcode'));
        
        // 添加编辑器按钮
        add_action('media_buttons', array($this, 'add_premium_content_buttons'));
        
        // CSS样式已移动到 /assets/css/membership-content.css
        
        // 过滤文章内容 - 文章级别保护 (已禁用，使用 Article Protection Manager)
        // add_filter('the_content', array($this, 'filter_article_content'), 5);
        // add_filter('the_excerpt', array($this, 'filter_article_excerpt'), 5);
        
        // 添加文章元框 - 已被新的增强元框替代
        // add_action('add_meta_boxes', array($this, 'add_premium_content_meta_box'));
        add_action('save_post', array($this, 'save_premium_content_meta'));
        
        // AJAX处理
        add_action('wp_ajax_folio_unlock_content', array($this, 'ajax_unlock_content'));
        add_action('wp_ajax_nopriv_folio_unlock_content', array($this, 'ajax_unlock_content'));
        
        // 前端脚本
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // RSS和API保护已移至 Article Protection Manager，避免重复处理
        // add_filter('the_content_feed', array($this, 'filter_feed_content'));
        // add_filter('rest_prepare_post', array($this, 'filter_rest_content'), 10, 3);
        
        // 文章列表会员徽章样式已移动到 CSS 文件
        add_filter('post_class', array($this, 'add_premium_post_class'), 10, 3);
    }

    /**
     * VIP内容短代码
     */
    public function vip_content_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'message' => '此内容仅VIP会员可见',
            'upgrade_text' => '升级VIP',
            'upgrade_url' => home_url('/user-center/membership'),
            'preview' => 'auto', // auto, none, custom
            'preview_length' => 100
        ), $atts);

        return $this->render_premium_content($content, 'vip', $atts);
    }

    /**
     * SVIP内容短代码
     */
    public function svip_content_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'message' => '此内容仅SVIP会员可见',
            'upgrade_text' => '升级SVIP',
            'upgrade_url' => home_url('/user-center/membership'),
            'preview' => 'auto',
            'preview_length' => 100
        ), $atts);

        return $this->render_premium_content($content, 'svip', $atts);
    }

    /**
     * 通用会员内容短代码
     */
    public function member_content_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'level' => 'vip',
            'message' => '此内容仅会员可见',
            'upgrade_text' => '成为会员',
            'upgrade_url' => home_url('/user-center/membership'),
            'preview' => 'auto',
            'preview_length' => 100
        ), $atts);

        return $this->render_premium_content($content, $atts['level'], $atts);
    }

    /**
     * 会员卡片短代码
     */
    public function membership_card_shortcode($atts) {
        $atts = shortcode_atts(array(
            'level' => 'vip',
            'price' => '',
            'duration' => '',
            'features' => '',
            'highlight' => 'false'
        ), $atts);

        $level = $atts['level'];
        $levels = folio_get_membership_levels();
        $level_info = isset($levels[$level]) ? $levels[$level] : $levels['vip'];
        $user_membership = folio_get_user_membership();
        $is_current = $user_membership['level'] === $level;
        $is_highlight = $atts['highlight'] === 'true';

        // 解析功能列表
        $features = !empty($atts['features']) ? explode(',', $atts['features']) : $this->get_default_features($level);

        ob_start();
        ?>
        <div class="membership-card membership-card-<?php echo esc_attr($level); ?> <?php echo $is_current ? 'membership-card-current' : ''; ?> <?php echo $is_highlight ? 'membership-card-highlight' : ''; ?>">
            <div class="membership-card-header">
                <div class="membership-card-badge">
                    <?php echo wp_kses_post($level_info['icon']); ?>
                </div>
                <h3 class="membership-card-title"><?php echo esc_html($level_info['name']); ?></h3>
                <?php if ($atts['price']) : ?>
                <div class="membership-card-price">
                    <span class="price-amount"><?php echo esc_html($atts['price']); ?></span>
                    <?php if ($atts['duration']) : ?>
                    <span class="price-duration">/ <?php echo esc_html($atts['duration']); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="membership-card-features">
                <?php foreach ($features as $feature) : ?>
                <div class="membership-feature">
                    <svg class="feature-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo esc_html(trim($feature)); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="membership-card-action">
                <?php if ($is_current) : ?>
                <button class="membership-btn membership-btn-current" disabled>
                    <span class="dashicons dashicons-yes"></span> 当前等级
                </button>
                <?php else : ?>
                <a href="<?php echo esc_url(home_url('/user-center/membership')); ?>" class="membership-btn membership-btn-upgrade" data-level="<?php echo esc_attr($level); ?>">
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php echo $level === 'svip' ? '升级SVIP' : '升级VIP'; ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 升级提示短代码
     */
    public function upgrade_prompt_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => '升级会员享受更多权益',
            'description' => '成为VIP会员，解锁所有专属内容和功能',
            'button_text' => '立即升级',
            'style' => 'default' // default, minimal, banner
        ), $atts);

        $user_membership = folio_get_user_membership();
        
        // 如果已经是最高等级，不显示升级提示
        if ($user_membership['is_svip']) {
            return '';
        }

        ob_start();
        ?>
        <div class="upgrade-prompt upgrade-prompt-<?php echo esc_attr($atts['style']); ?>">
            <div class="upgrade-prompt-content">
                <div class="upgrade-prompt-icon">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <div class="upgrade-prompt-text">
                    <h4 class="upgrade-prompt-title"><?php echo esc_html($atts['title']); ?></h4>
                    <p class="upgrade-prompt-description"><?php echo esc_html($atts['description']); ?></p>
                </div>
                <div class="upgrade-prompt-action">
                    <a href="<?php echo esc_url(home_url('/user-center/membership')); ?>" class="upgrade-prompt-btn">
                        <?php echo esc_html($atts['button_text']); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 渲染会员专属内容
     */
    private function render_premium_content($content, $required_level, $atts) {
        if (empty($content)) {
            return '';
        }

        $user_membership = folio_get_user_membership();
        $can_access = $this->can_access_content($user_membership, $required_level);

        if ($can_access) {
            return '<div class="premium-content premium-content-unlocked" data-level="' . esc_attr($required_level) . '">' . do_shortcode($content) . '</div>';
        }

        // 生成锁定内容的HTML
        return $this->render_locked_content($content, $required_level, $atts, $user_membership);
    }

    /**
     * 检查用户是否可以访问内容
     */
    private function can_access_content($user_membership, $required_level) {
        // 管理员可以查看所有内容
        if (current_user_can('manage_options')) {
            return true;
        }

        switch ($required_level) {
            case 'vip':
                return $user_membership['is_vip'];
            case 'svip':
                return $user_membership['is_svip'];
            default:
                return false;
        }
    }

    /**
     * 渲染锁定的内容
     */
    private function render_locked_content($content, $required_level, $atts, $user_membership) {
        $content_preview = $this->get_content_preview($content, $atts);
        $level_name = $required_level === 'svip' ? 'SVIP' : 'VIP';
        $level_class = 'premium-level-' . $required_level;
        
        ob_start();
        ?>
        <div class="premium-content premium-content-locked <?php echo esc_attr($level_class); ?>" data-level="<?php echo esc_attr($required_level); ?>">
            <!-- 内容预览 -->
            <?php if ($content_preview && $atts['preview'] !== 'none') : ?>
            <div class="premium-content-preview">
                <?php echo wp_kses_post($content_preview); ?>
            </div>
            <div class="premium-content-fade"></div>
            <?php endif; ?>
            
            <!-- 锁定提示 -->
            <div class="premium-content-lock">
                <div class="premium-lock-icon">
                    <span class="dashicons dashicons-lock"></span>
                </div>
                
                <div class="premium-lock-content">
                    <h4 class="premium-lock-title"><?php echo esc_html($atts['message']); ?></h4>
                    
                    <?php if (!is_user_logged_in()) : ?>
                    <p class="premium-lock-description">请先登录查看专属内容</p>
                    <div class="premium-lock-actions">
                        <a href="<?php echo esc_url(home_url('/user-center/login')); ?>" class="premium-btn premium-btn-login">
                            <span class="dashicons dashicons-admin-users"></span> 登录
                        </a>
                        <a href="<?php echo esc_url(home_url('/user-center/register')); ?>" class="premium-btn premium-btn-register">
                            <span class="dashicons dashicons-plus"></span> 注册
                        </a>
                    </div>
                    <?php else : ?>
                    <p class="premium-lock-description">
                        当前等级：<?php echo esc_html($user_membership['name']); ?>
                        <?php if ($user_membership['level'] !== 'free') : ?>
                        <span class="premium-current-badge"><?php echo wp_kses_post($user_membership['icon']); ?></span>
                        <?php endif; ?>
                    </p>
                    <div class="premium-lock-actions">
                        <a href="<?php echo esc_url($atts['upgrade_url']); ?>" class="premium-btn premium-btn-upgrade">
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php echo esc_html($atts['upgrade_text']); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 获取内容预览
     */
    private function get_content_preview($content, $atts) {
        if ($atts['preview'] === 'none') {
            return '';
        }

        if ($atts['preview'] === 'custom' && !empty($atts['preview_text'])) {
            return $atts['preview_text'];
        }

        // 自动预览
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content);
        $length = intval($atts['preview_length']);
        
        if (mb_strlen($content) > $length) {
            return mb_substr($content, 0, $length) . '...';
        }
        
        return $content;
    }

    /**
     * 获取默认功能列表
     */
    private function get_default_features($level) {
        $features = array(
            'vip' => array(
                '查看VIP专属内容',
                '无广告浏览体验',
                '优先客服支持',
                '专属会员标识'
            ),
            'svip' => array(
                '查看所有专属内容',
                '无广告浏览体验',
                '24小时专属客服',
                '专属SVIP标识',
                '独家高清资源',
                '提前体验新功能'
            )
        );

        return isset($features[$level]) ? $features[$level] : $features['vip'];
    }

    /**
     * 添加编辑器按钮
     */
    public function add_premium_content_buttons() {
        global $post;
        if (!$post || $post->post_type !== 'post') {
            return;
        }
        ?>
        <div class="folio-premium-buttons" style="display: inline-block; margin-left: 10px;">
            <button type="button" class="button" id="folio-add-vip-content" title="插入VIP专属内容">
                <span class="dashicons dashicons-lock" style="vertical-align: middle;"></span>
                VIP内容
            </button>
            <button type="button" class="button" id="folio-add-svip-content" title="插入SVIP专属内容">
                <span class="dashicons dashicons-admin-network" style="vertical-align: middle;"></span>
                SVIP内容
            </button>
            <button type="button" class="button" id="folio-add-membership-card" title="插入会员卡片">
                <span class="dashicons dashicons-id-alt" style="vertical-align: middle;"></span>
                会员卡片
            </button>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // VIP内容按钮
            document.getElementById('folio-add-vip-content')?.addEventListener('click', function() {
                const content = '[vip_content preview="auto" preview_length="100"]在这里输入VIP专属内容[/vip_content]';
                insertContent(content);
            });
            
            // SVIP内容按钮
            document.getElementById('folio-add-svip-content')?.addEventListener('click', function() {
                const content = '[svip_content preview="auto" preview_length="100"]在这里输入SVIP专属内容[/svip_content]';
                insertContent(content);
            });
            
            // 会员卡片按钮
            document.getElementById('folio-add-membership-card')?.addEventListener('click', function() {
                const content = '[membership_card level="vip" price="¥99" duration="月" features="专属内容,无广告,优先支持" highlight="true"]';
                insertContent(content);
            });
            
            function insertContent(content) {
                if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                    tinymce.activeEditor.insertContent(content);
                } else {
                    const textarea = document.getElementById('content');
                    if (textarea) {
                        const cursorPos = textarea.selectionStart;
                        const textBefore = textarea.value.substring(0, cursorPos);
                        const textAfter = textarea.value.substring(cursorPos);
                        textarea.value = textBefore + content + textAfter;
                        textarea.selectionStart = textarea.selectionEnd = cursorPos + content.length;
                        textarea.focus();
                    }
                }
            }
        });
        </script>
        <?php
    }



    /**
     * 加载前端脚本
     */
    public function enqueue_scripts() {
        if (is_singular()) {
            wp_enqueue_script('folio-premium-content', get_template_directory_uri() . '/assets/js/premium-content.js', array('jquery'), '1.0.0', true);
            wp_localize_script('folio-premium-content', 'folioPremium', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('folio_premium_content'),
                'strings' => array(
                    'loading' => '加载中...',
                    'error' => '操作失败，请重试',
                    'success' => '操作成功'
                )
            ));
        }
    }

    /**
     * 过滤文章内容 - 文章级别保护
     */
    public function filter_article_content($content) {
        if (!is_singular('post') || is_admin()) {
            return $content;
        }
        
        global $post;
        if (!$post) {
            return $content;
        }
        
        // 使用 Article Protection Manager 的方法
        $protection_info = folio_Article_Protection_Manager::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $content;
        }
        
        $user_id = get_current_user_id();
        if (folio_Article_Protection_Manager::can_user_access($post->ID, $user_id)) {
            return $content;
        }
        
        // 生成受保护内容的显示（使用 Article Protection Manager 的方法）
        $preview_content = folio_Article_Protection_Manager::generate_preview($content, $protection_info);
        $permission_prompt = $this->get_permission_prompt($post->ID, $protection_info);
        
        ob_start();
        ?>
        <div class="folio-protected-article" data-post-id="<?php echo esc_attr($post->ID); ?>" data-level="<?php echo esc_attr($protection_info['required_level']); ?>">
            <?php if ($preview_content && $protection_info['preview_mode'] !== 'none') : ?>
            <div class="folio-content-preview">
                <?php echo wpautop($preview_content); ?>
            </div>
            <div class="folio-content-fade"></div>
            <?php endif; ?>
            
            <?php echo $permission_prompt; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 过滤文章摘要
     */
    public function filter_article_excerpt($excerpt) {
        global $post;
        if (!$post) {
            return $excerpt;
        }
        
        // 使用 Article Protection Manager 的方法
        $protection_info = folio_Article_Protection_Manager::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $excerpt;
        }
        
        $user_id = get_current_user_id();
        if (folio_Article_Protection_Manager::can_user_access($post->ID, $user_id)) {
            return $excerpt;
        }
        
        // 对于受保护的文章，返回简化的摘要
        return '此内容为会员专属，需要' . ($protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP') . '等级查看。';
    }

    /**
     * 过滤RSS内容
     * 
     * 已移至 Article Protection Manager，此方法已废弃
     * 保留代码以防需要，但不再被调用
     */
    /*
    public function filter_feed_content($content) {
        global $post;
        if (!$post) {
            return $content;
        }
        
        // 使用 Article Protection Manager 的方法
        $protection_info = folio_Article_Protection_Manager::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $content;
        }
        
        // RSS中不显示完整的受保护内容
        if ($protection_info['rss_include']) {
            return folio_Article_Protection_Manager::generate_preview($content, $protection_info);
        }
        
        return '此内容为会员专属，请访问网站查看完整内容。';
    }
    */

    /**
     * 过滤REST API内容
     * 
     * 已移至 Article Protection Manager，此方法已废弃
     * 保留代码以防需要，但不再被调用
     */
    /*
    public function filter_rest_content($response, $post, $request) {
        // 在后台编辑器中不进行过滤，允许编辑完整内容
        // 检查多种情况：后台页面、AJAX请求、REST API编辑上下文
        $is_edit_context = false;
        
        // 检查请求参数中的 context
        if (isset($_REQUEST['context']) && $_REQUEST['context'] === 'edit') {
            $is_edit_context = true;
        } elseif (is_array($request) && isset($request['context']) && $request['context'] === 'edit') {
            $is_edit_context = true;
        } elseif (is_object($request) && method_exists($request, 'get_param')) {
            $context = $request->get_param('context');
            if ($context === 'edit') {
                $is_edit_context = true;
            }
        }
        
        // 检查请求路径（Gutenberg 编辑器通常使用 /wp-json/wp/v2/posts/{id}?context=edit）
        if (!$is_edit_context && isset($_SERVER['REQUEST_URI'])) {
            $request_uri = $_SERVER['REQUEST_URI'];
            if (strpos($request_uri, '/wp-json/wp/v2/posts/') !== false && 
                (strpos($request_uri, 'context=edit') !== false || strpos($request_uri, 'context%3Dedit') !== false)) {
                $is_edit_context = true;
            }
        }
        
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST && $is_edit_context) || (defined('WP_ADMIN') && WP_ADMIN)) {
            return $response;
        }
        
        if ($post->post_type !== 'post') {
            return $response;
        }
        
        // 使用 Article Protection Manager 的方法
        $protection_info = folio_Article_Protection_Manager::get_protection_info($post->ID);
        if (!$protection_info['is_protected']) {
            return $response;
        }
        
        $user_id = get_current_user_id();
        if (folio_Article_Protection_Manager::can_user_access($post->ID, $user_id)) {
            return $response;
        }
        
        // 修改API响应中的内容
        $data = $response->get_data();
        if (isset($data['content']['rendered'])) {
            $data['content']['rendered'] = folio_Article_Protection_Manager::generate_preview($data['content']['rendered'], $protection_info);
        }
        if (isset($data['excerpt']['rendered'])) {
            $data['excerpt']['rendered'] = '此内容为会员专属，需要' . ($protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP') . '等级查看。';
        }
        
        $response->set_data($data);
        return $response;
    }
    */

    /**
     * 获取文章保护信息
     * 
     * 已统一使用 Article Protection Manager 的方法
     * 保留此方法作为兼容性包装
     */
    public function get_protection_info($post_id) {
        return folio_Article_Protection_Manager::get_protection_info($post_id);
    }

    /**
     * 检查用户是否可以访问文章
     * 
     * 已统一使用 Article Protection Manager 的方法
     * 保留此方法作为兼容性包装
     */
    public function can_user_access($post_id, $user_id = null) {
        return folio_Article_Protection_Manager::can_user_access($post_id, $user_id);
    }

    /**
     * 生成内容预览
     * 
     * 已统一使用 Article Protection Manager 的方法
     * 保留此方法作为兼容性包装
     */
    public function generate_preview($content, $protection_info) {
        return folio_Article_Protection_Manager::generate_preview($content, $protection_info);
    }

    /**
     * 渲染受保护的内容
     * 
     * 已统一使用 Article Protection Manager 的方法
     * 保留此方法作为兼容性包装
     */
    private function render_protected_content($content, $post_id, $protection_info) {
        $preview_content = folio_Article_Protection_Manager::generate_preview($content, $protection_info);
        $permission_prompt = $this->get_permission_prompt($post_id, $protection_info);
        
        ob_start();
        ?>
        <div class="folio-protected-article" data-post-id="<?php echo esc_attr($post_id); ?>" data-level="<?php echo esc_attr($protection_info['required_level']); ?>">
            <?php if ($preview_content && $protection_info['preview_mode'] !== 'none') : ?>
            <div class="folio-content-preview">
                <?php echo wpautop($preview_content); ?>
            </div>
            <div class="folio-content-fade"></div>
            <?php endif; ?>
            
            <?php echo $permission_prompt; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 获取权限提示HTML
     */
    public function get_permission_prompt($post_id, $protection_info = null) {
        if (!$protection_info) {
            $protection_info = folio_Article_Protection_Manager::get_protection_info($post_id);
        }
        
        $required_level = $protection_info['required_level'];
        $level_name = $required_level === 'svip' ? 'SVIP' : 'VIP';
        $level_class = 'folio-level-' . $required_level;
        
        $user_id = get_current_user_id();
        $user_membership = $user_id ? folio_get_user_membership($user_id) : null;
        
        ob_start();
        ?>
        <div class="folio-permission-prompt <?php echo esc_attr($level_class); ?>">
            <div class="folio-prompt-icon">
                <span class="dashicons dashicons-lock"></span>
            </div>
            
            <div class="folio-prompt-content">
                <h4 class="folio-prompt-title">此内容为<?php echo esc_html($level_name); ?>会员专属</h4>
                
                <?php if (!$user_id) : ?>
                <p class="folio-prompt-description">请先登录查看专属内容</p>
                <div class="folio-prompt-actions">
                    <a href="<?php echo esc_url(home_url('/user-center/login')); ?>" class="folio-btn folio-btn-login">
                        <span class="dashicons dashicons-admin-users"></span> 登录
                    </a>
                    <a href="<?php echo esc_url(home_url('/user-center/register')); ?>" class="folio-btn folio-btn-register">
                        <span class="dashicons dashicons-plus"></span> 注册
                    </a>
                </div>
                <?php else : ?>
                <p class="folio-prompt-description">
                    当前等级：<?php echo esc_html($user_membership['name']); ?>
                    <?php if ($user_membership['level'] !== 'free') : ?>
                    <span class="folio-current-badge"><?php echo wp_kses_post($user_membership['icon']); ?></span>
                    <?php endif; ?>
                </p>
                <div class="folio-prompt-actions">
                    <a href="<?php echo esc_url(home_url('/user-center/membership')); ?>" class="folio-btn folio-btn-upgrade">
                        <span class="dashicons dashicons-star-filled"></span>
                        升级<?php echo esc_html($level_name); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 添加文章元框
     */
    public function add_premium_content_meta_box() {
        add_meta_box(
            'folio_premium_content_enhanced',
            '会员专属内容设置',
            array($this, 'render_premium_content_meta_box'),
            'post',
            'side',
            'default'
        );
    }

    /**
     * 渲染文章元框
     */
    public function render_premium_content_meta_box($post) {
        wp_nonce_field('folio_premium_content_meta', 'folio_premium_content_nonce');
        
        $protection_info = folio_Article_Protection_Manager::get_protection_info($post->ID);
        ?>
        <div class="folio-premium-settings">
            <p>
                <label>
                    <input type="checkbox" name="folio_premium_content" value="1" <?php checked($protection_info['is_protected'], true); ?>>
                    <strong>设为会员专属文章</strong>
                </label>
            </p>
            
            <div id="folio_premium_options" style="<?php echo $protection_info['is_protected'] ? '' : 'display:none;'; ?>">
                <!-- 会员等级设置 -->
                <p>
                    <label for="folio_required_level"><strong>所需等级：</strong></label><br>
                    <select name="folio_required_level" id="folio_required_level" style="width: 100%;">
                        <option value="vip" <?php selected($protection_info['required_level'], 'vip'); ?>>VIP会员</option>
                        <option value="svip" <?php selected($protection_info['required_level'], 'svip'); ?>>SVIP会员</option>
                    </select>
                </p>
                
                <!-- 预览模式设置 -->
                <p>
                    <label for="folio_preview_mode"><strong>预览模式：</strong></label><br>
                    <select name="folio_preview_mode" id="folio_preview_mode" style="width: 100%;">
                        <option value="<?php echo self::PREVIEW_AUTO; ?>" <?php selected($protection_info['preview_mode'], self::PREVIEW_AUTO); ?>>自动预览</option>
                        <option value="<?php echo self::PREVIEW_PERCENTAGE; ?>" <?php selected($protection_info['preview_mode'], self::PREVIEW_PERCENTAGE); ?>>百分比预览</option>
                        <option value="<?php echo self::PREVIEW_CUSTOM; ?>" <?php selected($protection_info['preview_mode'], self::PREVIEW_CUSTOM); ?>>自定义预览</option>
                        <option value="<?php echo self::PREVIEW_NONE; ?>" <?php selected($protection_info['preview_mode'], self::PREVIEW_NONE); ?>>无预览</option>
                    </select>
                </p>
                
                <!-- 自动预览设置 -->
                <div id="folio_auto_preview_settings" style="<?php echo $protection_info['preview_mode'] === self::PREVIEW_AUTO ? '' : 'display:none;'; ?>">
                    <p>
                        <label for="folio_preview_length">预览长度（字符数）：</label><br>
                        <input type="number" name="folio_preview_length" id="folio_preview_length" 
                               value="<?php echo esc_attr($protection_info['preview_length']); ?>" 
                               min="50" max="1000" style="width: 100%;">
                        <small>建议：50-500字符</small>
                    </p>
                </div>
                
                <!-- 百分比预览设置 -->
                <div id="folio_percentage_preview_settings" style="<?php echo $protection_info['preview_mode'] === self::PREVIEW_PERCENTAGE ? '' : 'display:none;'; ?>">
                    <p>
                        <label for="folio_preview_percentage">预览百分比：</label><br>
                        <input type="range" name="folio_preview_percentage" id="folio_preview_percentage" 
                               value="<?php echo esc_attr($protection_info['preview_percentage']); ?>" 
                               min="10" max="80" style="width: 100%;">
                        <span id="folio_percentage_display"><?php echo esc_html($protection_info['preview_percentage']); ?>%</span>
                        <br><small>建议：20%-50%</small>
                    </p>
                </div>
                
                <!-- 自定义预览设置 -->
                <div id="folio_custom_preview_settings" style="<?php echo $protection_info['preview_mode'] === self::PREVIEW_CUSTOM ? '' : 'display:none;'; ?>">
                    <p>
                        <label for="folio_preview_custom">自定义预览内容：</label><br>
                        <textarea name="folio_preview_custom" id="folio_preview_custom" 
                                  rows="4" style="width: 100%;" placeholder="输入自定义的预览文本..."><?php echo esc_textarea($protection_info['preview_custom']); ?></textarea>
                        <small>支持HTML标签</small>
                    </p>
                </div>
                
                <!-- 高级设置 -->
                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer; font-weight: bold;">高级设置</summary>
                    <div style="margin-top: 10px;">
                        <p>
                            <label>
                                <input type="checkbox" name="folio_seo_visible" value="1" <?php checked($protection_info['seo_visible'], true); ?>>
                                搜索引擎可见（允许搜索引擎索引预览内容）
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="folio_rss_include" value="1" <?php checked($protection_info['rss_include'], true); ?>>
                                RSS订阅包含（在RSS中显示预览内容）
                            </label>
                        </p>
                    </div>
                </details>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.querySelector('input[name="folio_premium_content"]');
            const options = document.getElementById('folio_premium_options');
            const previewMode = document.getElementById('folio_preview_mode');
            const autoSettings = document.getElementById('folio_auto_preview_settings');
            const percentageSettings = document.getElementById('folio_percentage_preview_settings');
            const customSettings = document.getElementById('folio_custom_preview_settings');
            const percentageSlider = document.getElementById('folio_preview_percentage');
            const percentageDisplay = document.getElementById('folio_percentage_display');
            
            // 切换会员专属选项
            toggle.addEventListener('change', function() {
                options.style.display = this.checked ? 'block' : 'none';
            });
            
            // 切换预览模式设置
            function togglePreviewSettings() {
                const mode = previewMode.value;
                autoSettings.style.display = mode === '<?php echo self::PREVIEW_AUTO; ?>' ? 'block' : 'none';
                percentageSettings.style.display = mode === '<?php echo self::PREVIEW_PERCENTAGE; ?>' ? 'block' : 'none';
                customSettings.style.display = mode === '<?php echo self::PREVIEW_CUSTOM; ?>' ? 'block' : 'none';
            }
            
            previewMode.addEventListener('change', togglePreviewSettings);
            
            // 百分比滑块实时更新
            percentageSlider.addEventListener('input', function() {
                percentageDisplay.textContent = this.value + '%';
            });
        });
        </script>
        
        <div class="folio-shortcode-help" style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-radius: 4px;">
            <h4 style="margin: 0 0 10px 0;">快速插入短代码：</h4>
            <p style="margin: 5px 0; font-size: 12px;"><code>[vip_content]VIP专属内容[/vip_content]</code></p>
            <p style="margin: 5px 0; font-size: 12px;"><code>[membership_card level="vip" price="¥99"]</code></p>
            <p style="margin: 5px 0; font-size: 12px;"><code>[upgrade_prompt title="升级提示"]</code></p>
        </div>
        <?php
    }

    /**
     * 保存文章元数据
     */
    public function save_premium_content_meta($post_id) {
        if (!isset($_POST['folio_premium_content_nonce']) || 
            !wp_verify_nonce($_POST['folio_premium_content_nonce'], 'folio_premium_content_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // 基本设置
        $is_premium = isset($_POST['folio_premium_content']) ? 1 : 0;
        $required_level = sanitize_text_field($_POST['folio_required_level'] ?? 'vip');
        
        // 预览设置
        $preview_mode = sanitize_text_field($_POST['folio_preview_mode'] ?? self::PREVIEW_AUTO);
        $preview_length = max(50, min(1000, intval($_POST['folio_preview_length'] ?? 200)));
        $preview_percentage = max(10, min(80, intval($_POST['folio_preview_percentage'] ?? 30)));
        $preview_custom = wp_kses_post($_POST['folio_preview_custom'] ?? '');
        
        // 高级设置
        $seo_visible = isset($_POST['folio_seo_visible']) ? 1 : 0;
        $rss_include = isset($_POST['folio_rss_include']) ? 1 : 0;

        // 保存所有设置
        update_post_meta($post_id, '_folio_premium_content', $is_premium);
        update_post_meta($post_id, '_folio_required_level', $required_level);
        update_post_meta($post_id, '_folio_preview_mode', $preview_mode);
        update_post_meta($post_id, '_folio_preview_length', $preview_length);
        update_post_meta($post_id, '_folio_preview_percentage', $preview_percentage);
        update_post_meta($post_id, '_folio_preview_custom', $preview_custom);
        update_post_meta($post_id, '_folio_protection_level', self::PROTECTION_CONTENT);
        update_post_meta($post_id, '_folio_seo_visible', $seo_visible);
        update_post_meta($post_id, '_folio_rss_include', $rss_include);
        
        // 清除相关缓存
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($post_id, 'post_meta');
        }
        
        // 触发保护设置更新钩子
        do_action('folio_article_protection_updated', $post_id, $is_premium, $required_level);
    }

    /**
     * AJAX解锁内容
     */
    public function ajax_unlock_content() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_premium_content')) {
            wp_send_json_error(array('message' => '安全验证失败'));
        }

        $post_id = intval($_POST['post_id']);
        $required_level = sanitize_text_field($_POST['required_level']);
        
        $user_membership = folio_get_user_membership();
        
        if ($this->can_access_content($user_membership, $required_level)) {
            wp_send_json_success(array('message' => '内容已解锁'));
        } else {
            wp_send_json_error(array('message' => '权限不足'));
        }
    }

    /**
     * 添加文章CSS类
     */
    public function add_premium_post_class($classes, $class, $post_id) {
        $protection_info = folio_Article_Protection_Manager::get_protection_info($post_id);
        
        if ($protection_info['is_protected']) {
            $classes[] = 'folio-premium-post';
            $classes[] = 'folio-premium-' . $protection_info['required_level'];
            
            $user_id = get_current_user_id();
            if (folio_Article_Protection_Manager::can_user_access($post_id, $user_id)) {
                $classes[] = 'folio-premium-unlocked';
            } else {
                $classes[] = 'folio-premium-locked';
            }
        }
        
        return $classes;
    }



    /**
     * 渲染会员徽章（用于文章列表）
     */
    public static function render_membership_badge($post_id, $context = 'list') {
        // 使用 Article Protection Manager 的方法
        $protection_info = folio_Article_Protection_Manager::get_protection_info($post_id);
        
        if (!$protection_info['is_protected']) {
            return '';
        }
        
        $user_id = get_current_user_id();
        $can_access = folio_Article_Protection_Manager::can_user_access($post_id, $user_id);
        $required_level = $protection_info['required_level'];
        $level_name = $required_level === 'svip' ? 'SVIP' : 'VIP';
        
        $badge_class = 'folio-premium-badge folio-premium-badge-' . $required_level;
        if ($can_access) {
            $badge_class .= ' folio-premium-badge-unlocked';
            $badge_text = $level_name . ' 已解锁';
        } else {
            $badge_class .= ' folio-premium-badge-locked';
            $badge_text = '需要' . $level_name;
        }
        
        return sprintf(
            '<span class="%s" title="%s">%s</span>',
            esc_attr($badge_class),
            esc_attr($can_access ? '您可以查看此内容' : '需要' . $level_name . '等级才能查看'),
            esc_html($badge_text)
        );
    }
}

// 初始化增强版会员专属内容系统
new folio_Premium_Content_Enhanced();

/**
 * 全局辅助函数
 */

// 获取文章保护信息的全局函数
// 已统一使用 Article Protection Manager 的方法
if (!function_exists('folio_get_article_protection_info')) {
    function folio_get_article_protection_info($post_id) {
        return folio_Article_Protection_Manager::get_protection_info($post_id);
    }
}

// 检查用户是否可以访问文章的全局函数
// 已统一使用 Article Protection Manager 的方法
if (!function_exists('folio_can_user_access_article')) {
    function folio_can_user_access_article($post_id, $user_id = null) {
        return folio_Article_Protection_Manager::can_user_access($post_id, $user_id);
    }
}

// 渲染文章会员徽章的全局函数
if (!function_exists('folio_render_article_badge')) {
    function folio_render_article_badge($post_id, $context = 'list') {
        return folio_Premium_Content_Enhanced::render_membership_badge($post_id, $context);
    }
}

// 文章徽章功能已移至 assets/js/theme.js 中的 initArticleBadges() 函数
// 不再需要在 wp_head 中输出内联脚本

// AJAX处理器：获取文章徽章
add_action('wp_ajax_folio_get_article_badge', 'folio_ajax_get_article_badge');
add_action('wp_ajax_nopriv_folio_get_article_badge', 'folio_ajax_get_article_badge');

function folio_ajax_get_article_badge() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_article_badge')) {
        wp_send_json_error(array('message' => '安全验证失败'));
    }
    
    $post_id = intval($_POST['post_id']);
    if (!$post_id) {
        wp_send_json_error(array('message' => '无效的文章ID'));
    }
    
    $badge = folio_Premium_Content_Enhanced::render_membership_badge($post_id);
    
    wp_send_json_success(array(
        'badge' => $badge,
        'post_id' => $post_id
    ));
}

// 兼容性函数已移动到 functions.php 以确保早期加载
