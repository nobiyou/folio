<?php
/**
 * AI Batch Generator
 * 
 * AI批量生成器 - 批量为文章生成摘要和标签
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class Folio_AI_Batch_Generator {
    
    private $api_manager;
    private $content_generator;
    
    public function __construct() {
        // 使用统一的API管理器
        $this->api_manager = Folio_AI_API_Manager::get_instance();
        
        // 复用现有的内容生成器
        $this->content_generator = Folio_AI_Content_Generator::get_instance();
        
        // 注册管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'), 30);
        
        // 注册AJAX处理
        add_action('wp_ajax_folio_ai_batch_scan', array($this, 'ajax_scan_posts'));
        add_action('wp_ajax_folio_ai_batch_generate', array($this, 'ajax_batch_generate'));
        
        // 注册管理脚本
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'themes.php',  // 外观菜单
            __('AI Batch Generator', 'folio'),
            __('AI Batch Generator', 'folio'),
            'manage_options',
            'folio-ai-batch',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * 渲染管理页面
     */
    public function render_admin_page() {
        ?>
        <div class="wrap folio-ai-batch-page">
            <h1><?php esc_html_e('AI Batch Generator', 'folio'); ?></h1>
            <p class="description">
                <?php esc_html_e('Automatically generate excerpts and tags for posts that are missing them.', 'folio'); ?>
            </p>
            
            <?php if (!$this->api_manager->has_apis()): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('Please configure AI API in Theme Settings first.', 'folio'); ?>
                        <a href="<?php echo admin_url('themes.php?page=folio-theme-options&tab=ai'); ?>" class="button button-small">
                            <?php esc_html_e('Go to Settings', 'folio'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                
                <div class="folio-ai-batch-container">
                    <!-- 扫描设置 -->
                    <div class="folio-ai-batch-section">
                        <h2><?php esc_html_e('Scan Settings', 'folio'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="post-type"><?php esc_html_e('Post Type', 'folio'); ?></label>
                                </th>
                                <td>
                                    <select id="post-type" name="post_type">
                                        <option value="post"><?php esc_html_e('Posts', 'folio'); ?></option>
                                        <option value="page"><?php esc_html_e('Pages', 'folio'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Generate For', 'folio'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="missing-excerpt" name="missing_excerpt" value="1" checked>
                                        <?php esc_html_e('Posts without excerpt', 'folio'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" id="missing-tags" name="missing_tags" value="1" checked>
                                        <?php esc_html_e('Posts without tags', 'folio'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="button" id="scan-posts-btn" class="button button-primary">
                                <span class="dashicons dashicons-search"></span>
                                <?php esc_html_e('Scan Posts', 'folio'); ?>
                            </button>
                        </p>
                    </div>
                    
                    <!-- 扫描结果 -->
                    <div id="scan-results" class="folio-ai-batch-section" style="display: none;">
                        <h2><?php esc_html_e('Scan Results', 'folio'); ?></h2>
                        <div id="scan-results-content"></div>
                        
                        <div id="batch-actions" style="display: none;">
                            <p class="submit">
                                <button type="button" id="start-batch-btn" class="button button-primary button-large">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php esc_html_e('Start Batch Generation', 'folio'); ?>
                                </button>
                            </p>
                        </div>
                    </div>
                    
                    <!-- 生成进度 -->
                    <div id="generation-progress" class="folio-ai-batch-section" style="display: none;">
                        <h2><?php esc_html_e('Generation Progress', 'folio'); ?></h2>
                        <div class="progress-bar-container">
                            <div class="progress-bar">
                                <div class="progress-bar-fill" style="width: 0%;"></div>
                            </div>
                            <div class="progress-text">0 / 0</div>
                        </div>
                        
                        <div id="generation-log"></div>
                        
                        <div id="generation-complete" style="display: none;">
                            <div class="notice notice-success inline">
                                <p><?php esc_html_e('Batch generation completed!', 'folio'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
        
        <style>
        .folio-ai-batch-page {
            max-width: 1200px;
        }
        .folio-ai-batch-container {
            background: #fff;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .folio-ai-batch-section {
            margin-bottom: 30px;
        }
        .folio-ai-batch-section h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .folio-ai-batch-section .button .dashicons {
            line-height: 28px;
            margin-right: 5px;
        }
        .scan-results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .scan-results-table th,
        .scan-results-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .scan-results-table th {
            background: #f0f0f1;
            font-weight: 600;
        }
        .scan-results-table .post-title {
            font-weight: 500;
        }
        .scan-results-table .missing-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #dc3545;
            color: #fff;
            border-radius: 3px;
            font-size: 11px;
            margin-right: 5px;
        }
        .progress-bar-container {
            margin: 20px 0;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #f0f0f1;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #00a0d2);
            transition: width 0.3s ease;
        }
        .progress-text {
            text-align: center;
            margin-top: 10px;
            font-weight: 600;
            font-size: 14px;
        }
        #generation-log {
            max-height: 400px;
            overflow-y: auto;
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 15px;
        }
        .log-entry {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        .log-entry:last-child {
            border-bottom: none;
        }
        .log-entry.success {
            color: #28a745;
        }
        .log-entry.error {
            color: #dc3545;
        }
        .log-entry .post-title {
            font-weight: 500;
        }
        .log-entry .timestamp {
            color: #666;
            font-size: 11px;
            margin-right: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * 加载管理脚本
     */
    public function enqueue_admin_scripts($hook) {
        // 修正：themes.php 的子页面 hook 格式是 appearance_page_{menu_slug}
        if ($hook !== 'appearance_page_folio-ai-batch') {
            return;
        }
        
        wp_enqueue_script(
            'folio-ai-batch',
            FOLIO_URI . '/assets/js/ai-batch-generator.js',
            array('jquery'),
            FOLIO_VERSION,
            true
        );
        
        wp_localize_script('folio-ai-batch', 'folioAIBatch', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('folio_ai_batch'),
            'strings' => array(
                'scanning' => __('Scanning posts...', 'folio'),
                'found_posts' => __('Found %d posts that need generation:', 'folio'),
                'no_posts' => __('No posts found that need generation.', 'folio'),
                'generating' => __('Generating...', 'folio'),
                'success' => __('Success', 'folio'),
                'error' => __('Error', 'folio'),
                'completed' => __('Completed', 'folio'),
                'excerpt_generated' => __('Excerpt generated', 'folio'),
                'tags_generated' => __('Tags generated', 'folio'),
                'post' => __('Post', 'folio'),
                'missing_excerpt' => __('Missing Excerpt', 'folio'),
                'missing_tags' => __('Missing Tags', 'folio'),
            ),
        ));
    }
    
    /**
     * AJAX扫描文章
     */
    public function ajax_scan_posts() {
        check_ajax_referer('folio_ai_batch', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'folio')));
        }
        
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
        $missing_excerpt = isset($_POST['missing_excerpt']) && $_POST['missing_excerpt'] === '1';
        $missing_tags = isset($_POST['missing_tags']) && $_POST['missing_tags'] === '1';
        
        if (!$missing_excerpt && !$missing_tags) {
            wp_send_json_error(array('message' => __('Please select at least one option', 'folio')));
        }
        
        // 查询文章
        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        );
        
        $all_posts = get_posts($args);
        $posts_need_generation = array();
        
        foreach ($all_posts as $post_id) {
            $post = get_post($post_id);
            $needs_excerpt = $missing_excerpt && empty($post->post_excerpt);
            $needs_tags = $missing_tags && empty(wp_get_post_tags($post_id));
            
            if ($needs_excerpt || $needs_tags) {
                $posts_need_generation[] = array(
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'needs_excerpt' => $needs_excerpt,
                    'needs_tags' => $needs_tags,
                );
            }
        }
        
        wp_send_json_success(array(
            'posts' => $posts_need_generation,
            'total' => count($posts_need_generation),
        ));
    }
    
    /**
     * AJAX批量生成
     */
    public function ajax_batch_generate() {
        check_ajax_referer('folio_ai_batch', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'folio')));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $generate_excerpt = isset($_POST['generate_excerpt']) && $_POST['generate_excerpt'] === '1';
        $generate_tags = isset($_POST['generate_tags']) && $_POST['generate_tags'] === '1';
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'folio')));
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'folio')));
        }
        
        $result = array(
            'post_id' => $post_id,
            'excerpt_generated' => false,
            'tags_generated' => false,
        );
        
        // 生成摘要
        if ($generate_excerpt) {
            $excerpt = $this->content_generator->generate_excerpt_content($post->post_title, $post->post_content);
            
            if ($excerpt) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_excerpt' => $excerpt,
                ));
                $result['excerpt_generated'] = true;
                $result['excerpt'] = $excerpt;
            }
        }
        
        // 生成标签
        if ($generate_tags) {
            $tags = $this->content_generator->generate_tags_content($post->post_title, $post->post_content);
            
            if (!empty($tags)) {
                wp_set_post_tags($post_id, $tags, false);
                $result['tags_generated'] = true;
                $result['tags'] = $tags;
            }
        }
        
        if ($result['excerpt_generated'] || $result['tags_generated']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('message' => __('Generation failed', 'folio')));
        }
    }
}

// 初始化
new Folio_AI_Batch_Generator();
