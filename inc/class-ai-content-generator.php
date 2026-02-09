<?php
/**
 * AI Content Generator
 * 
 * AI内容生成器 - 自动生成摘要和关键词
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class Folio_AI_Content_Generator {
    
    private $api_manager;
    
    public function __construct() {
        // 使用统一的API管理器
        $this->api_manager = Folio_AI_API_Manager::get_instance();
        
        // 注册钩子
        add_action('add_meta_boxes', array($this, 'add_ai_meta_box'));
        add_action('save_post', array($this, 'auto_generate_on_save'), 20, 2);
        add_action('wp_ajax_folio_ai_generate', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_folio_test_ai_connection', array($this, 'ajax_test_connection'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * 添加AI生成元框到编辑器侧边栏
     */
    public function add_ai_meta_box() {
        $post_types = get_post_types(array('public' => true), 'names');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'folio_ai_generator',
                '<span class="dashicons dashicons-admin-generic"></span> ' . __('AI 内容生成', 'folio'),
                array($this, 'render_ai_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * 渲染AI生成元框
     */
    public function render_ai_meta_box($post) {
        if (!$this->api_manager->has_apis()) {
            ?>
            <div class="folio-ai-notice notice-warning">
                <p><?php esc_html_e('请先在主题设置中配置 AI API', 'folio'); ?></p>
                <a href="<?php echo admin_url('themes.php?page=folio-theme-options&tab=ai'); ?>" class="button button-small">
                    <?php esc_html_e('前往设置', 'folio'); ?>
                </a>
            </div>
            <?php
            return;
        }
        
        wp_nonce_field('folio_ai_generate', 'folio_ai_nonce');
        ?>
        <div class="folio-ai-generator">
            <div class="folio-ai-options">
                <label>
                    <input type="checkbox" name="folio_ai_auto_generate" value="1" <?php checked(get_post_meta($post->ID, '_folio_ai_auto_generate', true), '1'); ?>>
                    <?php esc_html_e('保存时自动生成', 'folio'); ?>
                </label>
            </div>
            
            <div class="folio-ai-actions">
                <button type="button" class="button button-primary button-large folio-ai-generate-btn" data-action="all">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('生成摘要和关键词', 'folio'); ?>
                </button>
                
                <div class="folio-ai-separate-actions">
                    <button type="button" class="button folio-ai-generate-btn" data-action="excerpt">
                        <?php esc_html_e('仅生成摘要', 'folio'); ?>
                    </button>
                    <button type="button" class="button folio-ai-generate-btn" data-action="tags">
                        <?php esc_html_e('仅生成关键词', 'folio'); ?>
                    </button>
                </div>
            </div>
            
            <div class="folio-ai-status" style="display: none;">
                <div class="folio-ai-loading">
                    <span class="spinner is-active"></span>
                    <span class="folio-ai-status-text"><?php esc_html_e('AI 正在生成中...', 'folio'); ?></span>
                </div>
            </div>
            
            <div class="folio-ai-result" style="display: none;"></div>
        </div>
        
        <style>
        .folio-ai-generator {
            padding: 12px 0;
        }
        .folio-ai-notice {
            padding: 10px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            margin: 0 0 12px 0;
        }
        .folio-ai-notice p {
            margin: 0 0 8px 0;
            font-size: 13px;
        }
        .folio-ai-options {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #ddd;
        }
        .folio-ai-options label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        .folio-ai-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .folio-ai-actions .button-large {
            height: auto;
            padding: 8px 12px;
            font-size: 13px;
        }
        .folio-ai-actions .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
            margin-right: 4px;
        }
        .folio-ai-separate-actions {
            display: flex;
            gap: 6px;
        }
        .folio-ai-separate-actions .button {
            flex: 1;
            font-size: 12px;
            padding: 4px 8px;
            height: auto;
        }
        .folio-ai-status {
            margin-top: 12px;
            padding: 10px;
            background: #f0f0f1;
            border-radius: 4px;
        }
        .folio-ai-loading {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .folio-ai-loading .spinner {
            float: none;
            margin: 0;
        }
        .folio-ai-status-text {
            font-size: 13px;
            color: #646970;
        }
        .folio-ai-result {
            margin-top: 12px;
            padding: 10px;
            background: #d4edda;
            border-left: 4px solid #28a745;
            border-radius: 4px;
            font-size: 13px;
        }
        .folio-ai-result.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        </style>
        <?php
    }
    
    /**
     * 加载管理脚本
     */
    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        wp_enqueue_script(
            'folio-ai-generator',
            FOLIO_URI . '/assets/js/ai-generator.js',
            array('jquery'),
            FOLIO_VERSION,
            true
        );
        
        wp_localize_script('folio-ai-generator', 'folioAI', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('folio_ai_generate'),
            'post_id' => get_the_ID(),
            'strings' => array(
                'generating' => __('AI 正在生成中...', 'folio'),
                'success' => __('生成成功！', 'folio'),
                'error' => __('生成失败，请重试', 'folio'),
                'no_content' => __('请先输入标题和内容', 'folio'),
            ),
        ));
    }
    
    /**
     * 保存时自动生成
     */
    public function auto_generate_on_save($post_id, $post) {
        // 检查是否启用自动生成
        if (!isset($_POST['folio_ai_auto_generate'])) {
            delete_post_meta($post_id, '_folio_ai_auto_generate');
            return;
        }
        
        // 保存自动生成设置
        update_post_meta($post_id, '_folio_ai_auto_generate', '1');
        
        // 跳过自动保存和修订版本
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // 检查权限
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // 如果摘要为空，生成摘要
        if (empty($post->post_excerpt)) {
            $excerpt = $this->generate_excerpt($post->post_title, $post->post_content);
            if ($excerpt) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_excerpt' => $excerpt,
                ));
            }
        }
        
        // 如果没有标签，生成标签
        $tags = wp_get_post_tags($post_id);
        if (empty($tags)) {
            $generated_tags = $this->generate_tags($post->post_title, $post->post_content);
            if ($generated_tags) {
                wp_set_post_tags($post_id, $generated_tags, false);
            }
        }
    }
    
    /**
     * AJAX处理生成请求
     */
    public function ajax_generate_content() {
        check_ajax_referer('folio_ai_generate', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('权限不足', 'folio')));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'all';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        
        if (empty($title) && empty($content)) {
            wp_send_json_error(array('message' => __('请先输入标题和内容', 'folio')));
        }
        
        $result = array();
        
        // 生成摘要
        if (in_array($action, array('all', 'excerpt'))) {
            $excerpt = $this->generate_excerpt($title, $content);
            if ($excerpt) {
                $result['excerpt'] = $excerpt;
                // 更新文章摘要
                if ($post_id) {
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_excerpt' => $excerpt,
                    ));
                }
            }
        }
        
        // 生成标签：用 AI 原始返回解析为数组并保存，不做编码转换
        if (in_array($action, array('all', 'tags'))) {
            $raw = $this->get_tags_raw($title, $content);
            if ($raw !== false) {
                $tags = $this->parse_tags_response($raw);
                if (!empty($tags)) {
                    $result['tags'] = $tags;
                    if ($post_id) {
                        wp_set_post_tags($post_id, $tags, false);
                    }
                }
            }
        }
        
        if (!empty($result)) {
            wp_send_json(array('success' => true, 'raw'=>$raw,'data' => $result), 200, JSON_UNESCAPED_UNICODE);
        } else {
            wp_send_json_error(array('message' => __('AI生成失败，请检查API配置或查看站点错误日志', 'folio')));
        }
    }
    
    /**
     * 构建统一的AI提示词（与class-ai-seo.php保持一致）
     */
    private function build_prompt($title, $content, $type = 'excerpt') {
        $language = get_option('folio_ai_language', 'zh');
        
        // PHP 8.2 兼容性：确保 $title 是字符串
        if (!is_string($title)) {
            $title = '';
        }
        
        // 检测标题长度，判断内容类型
        $title_length = mb_strlen($title, 'UTF-8');
        $is_short_title = $title_length <= 20;
        
        if ($language === 'zh') {
            $content_type_hint = $is_short_title 
                ? "【⚠️ 关键提示：这是时尚产品展示内容】

这是一个短标题（不超过20字符），**这是时尚商品的展示页面**，不是其他任何类型的内容。

【重要：标题和内容的解读方式】
1. **标题解读**：
   - 标题中的人名（如'小美'、'Lisa'、'美咲'）、地名（如'东京'、'上海'、'巴黎'）以及常用词语（如'夏日'、'清新'、'浪漫'）等，**都不是字面意思，而是商品的名称或系列名称**
   - 例如：标题'东京'是指名为'东京'的商品，不是日本东京这个城市
   - 例如：标题'小美'是指名为'小美'的商品，不是人名

2. **文章内容解读（⚠️ 非常重要）**：
   - **文章内容只是辅助信息，用于了解商品的风格、特点、主题等**
   - **绝对不要按照文章内容的字面意思来理解或生成**
   - 即使文章内容看起来像描述人物、地点、学术研究、故事等，**也要理解为这是商品的命名方式、风格描述或主题设定**
   - 例如：如果文章内容提到'人工智能研究者'，这不是指真的研究者，而是名为'人工智能研究者'的商品系列
   - 例如：如果文章内容提到'神经科学'，这不是指真的科学领域，而是商品的主题或命名方式
   - **文章内容中的所有描述性文字，都要理解为是在描述商品的特点、风格、主题，而不是字面意思**

3. **生成要求**：
   - 所有生成的内容都必须围绕'时尚产品'这个核心
   - 要突出商品的特点、风格、魅力
   - 使用符合男性用户搜索习惯的自然、口语化表达"
                : "注意：这是写真图片类内容，标题较长，可以结合标题和内容来生成。

【⚠️ 重要提示：人名提取】
- **必须直接提取标题和内容中出现的人名**（包括中文、英文、日文、韩文姓名）
- 人名要原样保留，不能翻译或修改
- 例如：标题或内容中出现'小美'、'Lisa'、'美咲'、'김미영'等，必须直接提取为标签
- 人名标签优先提取，确保出现在关键词列表中";
            
            if ($type === 'excerpt') {
                return "你是一位专业的内容编辑，专门为时尚内容网站（包括写真图片和时尚商品展示）生成内容摘要。

【⚠️ 核心任务】
根据以下文章的标题和内容，生成专业的摘要。**如果是时尚商品类内容，必须以产品的角度来解读，绝不能按照字面意思理解。**

【文章标题】
{$title}

【文章内容摘要】
{$content}

【内容类型提示】
{$content_type_hint}

【输出格式】
- **必须只返回 JSON 格式**，格式为：{\"excerpt\": \"摘要内容\"}
- 不要其他文字、说明或 markdown，只返回 JSON 对象

【任务要求】
- 生成100-150字的简洁摘要
- **对于时尚商品类内容（短标题）：**
  - **摘要必须以商品为核心，绝不能按照标题或内容的字面意思来编写**
  - 即使标题看起来像人名、地名，文章内容看起来像描述人物、地点、学术研究等，也要理解为这是商品的命名方式或主题设定
  - 摘要要突出商品的特色、风格、魅力点
  - 例如：'这是一款名为XX的性感商品，采用XX风格设计，展现XX魅力...'
  - 不要写成人物传记、学术研究、地点介绍等，而要写成商品展示
- 语言要流畅自然，符合目标用户阅读习惯
- **如果标题或内容中包含英文、日文、韩文等非中文字符，必须原样保留，不能翻译或替换**

【⚠️ 重要要求】
- **【最关键】对于时尚商品类内容（短标题）：**
  - **标题中的人名、地名、常用词都是商品名称，必须以产品的角度来解读**
  - **文章内容只是参考信息，用于了解商品风格，绝不能按照内容的字面意思来理解或生成**
  - **即使文章内容看起来像描述人物、地点、学术研究、故事等，也要理解为这是商品的命名方式、主题设定或风格描述**
  - **所有生成的内容都必须围绕'时尚产品'来生成**
  - **如果生成的内容看起来不像商品，那就是错误的，必须重新理解**

只返回 JSON 对象，不要其他内容。";
            } else { // tags
                return "你是一位专业的SEO优化专家，专门为时尚内容网站（包括写真图片和时尚商品展示）提取关键词。

【⚠️ 核心任务】
根据以下文章的标题和内容，提取5-8个关键词标签。**如果是时尚商品类内容，必须以产品的角度来解读，绝不能按照字面意思理解。**

【文章标题】
{$title}

【文章内容摘要】
{$content}

【内容类型提示】
{$content_type_hint}

【任务要求】
- 提取5-8个关键词标签，用中文逗号分隔
- **对于写真图片类内容（长标题）：**
  - **必须直接提取标题和内容中出现的人名**（包括中文、英文、日文、韩文姓名）
  - 人名要原样保留，不能翻译或修改，例如：'小美'、'Lisa'、'美咲'、'김미영'等
  - 人名标签优先提取，确保出现在关键词列表中
  - **避免重复出现'写真'标签**，如果已经有人名或其他相关标签，就不要添加'写真'标签
  - 可以提取其他相关关键词，如风格、主题、场景等
- **对于时尚商品类内容（短标题）：**
  - **关键词必须围绕'时尚产品'来生成，绝不能按照标题或内容的字面意思来理解**
  - 标题中的词语（人名、地名、常用词）都是商品名称，要以产品的角度来解读
  - 文章内容只是参考，用于了解商品风格和特点，不要按照内容的字面意思来生成关键词
  - 关键词要突出商品的特点、风格、魅力，例如：性感内衣、蕾丝内衣、情趣内衣、内衣套装等
  - 可以结合标题中的商品名称，但必须以产品的角度来理解
- 使用自然、口语化的表达方式，符合男性用户的搜索习惯
- 避免使用生硬、官方化的词汇，优先使用用户常用的搜索词
- **如果标题或内容中包含英文、日文、韩文等非中文字符，必须原样保留，不能翻译或替换**

【⚠️ 重要要求】
- **【最关键】对于时尚商品类内容（短标题）：**
  - **标题中的人名、地名、常用词都是商品名称，必须以产品的角度来解读**
  - **文章内容只是参考信息，用于了解商品风格，绝不能按照内容的字面意思来理解或生成**
  - **即使文章内容看起来像描述人物、地点、学术研究、故事等，也要理解为这是商品的命名方式、主题设定或风格描述**
  - **所有关键词都必须围绕'时尚产品'来生成，例如：性感内衣、蕾丝内衣、情趣内衣、内衣套装、商品展示等**
  - **如果生成的关键词看起来不像商品相关，那就是错误的，必须重新理解**

只返回关键词，用中文逗号分隔，不要其他说明。";
            }
        } else {
            // 英文版本（完整版，与中文版本保持一致）
            $content_type_hint = $is_short_title 
                ? "【⚠️ Critical Hint: This is Fashion Product Showcase Content】

This is a short title (20 characters or less), **this is a fashion product showcase page**, not any other type of content.

【Important: Title and Content Interpretation】
1. **Title Interpretation**:
   - Names (like 'Xiaomei', 'Lisa', 'Misaki'), place names (like 'Tokyo', 'Shanghai', 'Paris'), and common words (like 'Summer', 'Fresh', 'Romantic') in the title are **NOT literal meanings, but rather product names or series names**
   - Example: Title 'Tokyo' refers to a product named 'Tokyo', not the city Tokyo
   - Example: Title 'Xiaomei' refers to a product named 'Xiaomei', not a person's name

2. **Article Content Interpretation (⚠️ Very Important)**:
   - **Article content is only auxiliary information, used to understand the style, features, and themes of products**
   - **Absolutely do NOT interpret or generate according to the literal meaning of article content**
   - Even if article content looks like describing people, places, academic research, stories, etc., **understand it as product naming methods, style descriptions, or theme settings**
   - Example: If article content mentions 'AI researcher', this does not refer to a real researcher, but a product series named 'AI Researcher'
   - Example: If article content mentions 'neuroscience', this does not refer to a real scientific field, but a product theme or naming method
   - **All descriptive text in article content should be understood as describing product features, styles, and themes, not literal meanings**

3. **Generation Requirements**:
   - All generated content must focus on the core of 'fashion products'
   - Highlight product features, style, and appeal
   - Use natural, conversational expressions matching male users' search habits"
                : "Note: This is photography/image content with a longer title. You can combine title and content to generate.

【⚠️ Important Hint: Name Extraction】
- **Must directly extract names appearing in title and content** (including Chinese, English, Japanese, Korean names)
- Names must be preserved exactly as they appear, do not translate or modify
- Example: If '小美', 'Lisa', '美咲', '김미영' appear in title or content, they must be directly extracted as tags
- Name tags should be prioritized and ensured to appear in the keyword list";
            
            if ($type === 'excerpt') {
                return "You are a professional content editor specializing in generating summaries for fashion websites (including photography/images and fashion product showcases).

【⚠️ Core Task】
Generate a professional summary (100-150 words) based on the following article title and content. **If it's fashion product content, you must interpret it from the product perspective, never according to literal meanings.**

【Article Title】
{$title}

【Content Excerpt】
{$content}

【Content Type Hint】
{$content_type_hint}

【Output Format】
- **Must return JSON only**, in this exact format: {\"excerpt\": \"summary content\"}
- No other text, explanations, or markdown - only the JSON object

【Requirements】
- Generate a concise 100-150 word summary
- **For fashion product content (short title):**
  - **Summary must focus on products as the core, never interpret according to literal meanings of title or content**
  - Even if the title looks like a name or place name, and content looks like describing people, places, academic research, etc., understand it as product naming or theme setting
  - Summary should highlight product features, style, and appeal points
  - Example: 'This is a sexy product named XX, featuring XX style design, showcasing XX appeal...'
  - Do NOT write as biography, academic research, location introduction, etc., but as product showcase
- Language should be fluent and natural, matching target audience reading habits
- **Preserve English, Japanese, Korean, or other non-Chinese characters exactly as they appear, do not translate or replace them**

【⚠️ Important Requirements】
- **【Critical】For fashion product content (short title):**
  - **Names, place names, and common words in the title are product names - must interpret from product perspective**
  - **Article content is only reference information for understanding product style - never interpret or generate according to literal meanings**
  - **Even if article content looks like describing people, places, academic research, stories, etc., understand it as product naming methods, theme settings, or style descriptions**
  - **All generated content must focus on 'fashion products'**
  - **If generated content doesn't look like products, it's wrong and must be reinterpreted**

Return only the JSON object, no other content.";
            } else {
                return "You are a professional SEO expert specializing in extracting keywords for fashion websites (including photography/images and fashion product showcases).

【⚠️ Core Task】
Extract 5-8 keyword tags based on the following article title and content. **If it's fashion product content, you must interpret it from the product perspective, never according to literal meanings.**

【Article Title】
{$title}

【Content Excerpt】
{$content}

【Content Type Hint】
{$content_type_hint}

【Output Format】
- **Must return JSON only**, in this exact format: {\"tags\": [\"tag1\", \"tag2\", \"tag3\", ...]}
- No other text, explanations, or markdown - only the JSON object

【Requirements】
- Extract 5-8 keyword tags
- **For photography/image content (long title):**
  - **Must directly extract names appearing in title and content** (including Chinese, English, Japanese, Korean names)
  - Names must be preserved exactly as they appear, do not translate or modify, e.g., '小美', 'Lisa', '美咲', '김미영', etc.
  - Name tags should be prioritized and ensured to appear in the keyword list
  - **Avoid repeating 'photography' or '写真' tags** - if names or other relevant tags already exist, do not add 'photography' tag
  - Can extract other relevant keywords such as style, theme, scene, etc.
- **For fashion product content (short title):**
  - **Keywords must focus on 'fashion products', never interpret according to literal meanings of title or content**
  - Words in the title (names, place names, common words) are product names - interpret from product perspective
  - Content is only reference for understanding product style and features - do NOT generate keywords according to literal meanings
  - Keywords should highlight product features, style, appeal, e.g., sexy lingerie, lace lingerie, lingerie sets, etc.
  - Can combine product names from title, but must interpret from product perspective
- Use natural, conversational expressions matching male users' search habits
- Avoid stiff, official vocabulary; prioritize commonly used search terms
- **Preserve English, Japanese, Korean, or other non-Chinese characters exactly as they appear, do not translate or replace them**

【⚠️ Important Requirements】
- **【Critical】For fashion product content (short title):**
  - **Names, place names, and common words in the title are product names - must interpret from product perspective**
  - **Article content is only reference information for understanding product style - never interpret or generate according to literal meanings**
  - **Even if article content looks like describing people, places, academic research, stories, etc., understand it as product naming methods, theme settings, or style descriptions**
  - **All keywords must focus on 'fashion products', e.g., sexy lingerie, lace lingerie, lingerie sets, product showcase, etc.**
  - **If generated keywords don't look related to products, it's wrong and must be reinterpreted**

Return only the JSON object, no other content.";
            }
        }
    }
    
    /**
     * 生成摘要
     * AI 直接返回 {"excerpt": "摘要内容"} 格式
     */
    private function generate_excerpt($title, $content) {
        $content = wp_strip_all_tags($content);
        if (!is_string($content)) {
            $content = '';
        }
        $content = mb_substr($content, 0, 2000, 'UTF-8');
        
        $prompt = $this->build_prompt($title, $content, 'excerpt');
        $response = $this->call_ai_api($prompt);
        
        if (!$response) {
            return false;
        }
        return $this->parse_excerpt_response($response);
    }
    
    /**
     * 解析 AI 返回的 JSON 摘要数据
     * AI 直接返回 {"excerpt": "摘要内容"} 格式
     *
     * @param string $response AI 原始响应
     * @return string|false 摘要内容，失败返回 false
     */
    private function parse_excerpt_response($response) {
        $trimmed = trim($response);
        if (preg_match('/\{[\s\S]*\}/', $trimmed, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $text = isset($decoded['excerpt']) ? $decoded['excerpt'] : (isset($decoded['summary']) ? $decoded['summary'] : null);
                if (is_string($text) && trim($text) !== '') {
                    return trim($text);
                }
            }
        }
        return $trimmed ?: false;
    }
    
    /**
     * 解析 AI 返回的标签：仅按 JSON 或逗号拆分，不格式化、不过滤
     *
     * @param string $response AI 原始响应
     * @return array 标签数组
     */
    private function parse_tags_response($response) {
        $tags = array();
        $trimmed = trim($response);
        
        if (preg_match('/\{[\s\S]*\}/u', $trimmed, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['tags']) && is_array($decoded['tags'])) {
                $tags = $decoded['tags'];
            } elseif (json_last_error() === JSON_ERROR_NONE && isset($decoded['keywords']) && is_array($decoded['keywords'])) {
                $tags = $decoded['keywords'];
            }
        }
        if (empty($tags)) {
            // 使用 /u 修饰符确保 UTF-8 下正确按逗号分割，避免多字节字符被拆成问号
            $tags = preg_split('/[,，]/u', $trimmed);
        }
        
        $tags = array_map('trim', $tags);
        $tags = array_values(array_filter($tags));
        
        return $tags;
    }
    
    /**
     * 获取 AI 标签原始返回（不解析、不处理，直接返回）
     */
    private function get_tags_raw($title, $content) {
        $content = wp_strip_all_tags($content);
        if (!is_string($content)) {
            $content = '';
        }
        $content = mb_substr($content, 0, 2000, 'UTF-8');
        $prompt = $this->build_prompt($title, $content, 'tags');
        return $this->call_ai_api($prompt) ?: false;
    }
    
    /**
     * 生成标签（解析后用于保存时自动生成）
     */
    private function generate_tags($title, $content) {
        $raw = $this->get_tags_raw($title, $content);
        return $raw ? $this->parse_tags_response($raw) : array();
    }
    
    /**
     * AJAX测试连接
     */
    public function ajax_test_connection() {
        check_ajax_referer('folio_admin_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'folio')));
        }
        
        // 重新加载API管理器
        $this->api_manager = Folio_AI_API_Manager::get_instance();
        
        if (!$this->api_manager->has_apis()) {
            wp_send_json_error(array('message' => __('请先配置API', 'folio')));
        }
        
        // 测试所有API
        $results = $this->api_manager->test_connection();
        
        $success_count = 0;
        $messages = array();
        foreach ($results as $index => $result) {
            if ($result['success']) {
                $success_count++;
                $messages[] = sprintf(__('%s: 连接成功', 'folio'), $result['name'] ?: 'API #' . ($index + 1));
            } else {
                $error_msg = isset($result['error']) ? $result['error'] : (isset($result['message']) ? $result['message'] : '未知错误');
                $messages[] = sprintf(__('%s: 连接失败 - %s', 'folio'), $result['name'] ?: 'API #' . ($index + 1), $error_msg);
            }
        }
        
        if ($success_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(__('测试完成：%d/%d 个API连接成功', 'folio'), $success_count, count($results)),
                'details' => $messages,
                'results' => $results,
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('所有API连接都失败，请检查配置', 'folio'),
                'details' => $messages,
                'results' => $results,
            ));
        }
    }
    
    /**
     * 调用AI API（使用API管理器）
     */
    private function call_ai_api($prompt, $options = array()) {
        return $this->api_manager->call_api($prompt, $options);
    }
}

// 初始化
new Folio_AI_Content_Generator();
