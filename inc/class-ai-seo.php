<?php
/**
 * AI-Powered SEO Generation
 * 
 * 使用 AI 智能生成 SEO 关键词和描述
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_AI_SEO {

    private $api_manager;

    public function __construct() {
        // 使用统一的API管理器
        $this->api_manager = Folio_AI_API_Manager::get_instance();

        // 在文章保存时生成 SEO
        add_action('save_post_portfolio', array($this, 'generate_seo_on_save'), 10, 3);

        // 添加后台设置页面
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));

        // 添加文章编辑页面的 Meta Box
        add_action('add_meta_boxes', array($this, 'add_seo_meta_box'));
        add_action('save_post', array($this, 'save_seo_meta'));

        // AJAX 手动生成
        add_action('wp_ajax_folio_generate_ai_seo', array($this, 'ajax_generate_seo'));
    }

    /**
     * 文章保存时自动生成 SEO
     */
    public function generate_seo_on_save($post_id, $post, $update) {
        // 检查是否启用自动生成
        if (!get_option('folio_ai_auto_generate', false)) {
            return;
        }

        // 检查是否有可用的API
        if (!$this->api_manager->has_apis()) {
            return;
        }

        // 避免自动保存和修订版本
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        // 检查是否已有 AI 生成的 SEO（避免重复生成）
        // 但如果提示词版本号不匹配，则强制重新生成
        $existing_seo = get_post_meta($post_id, '_folio_ai_seo_generated', true);
        $current_prompt_version = '3.0'; // 当前提示词版本号
        $saved_prompt_version = get_post_meta($post_id, '_folio_ai_seo_prompt_version', true);
        
        // 如果已有SEO且版本号匹配，且没有强制重新生成标记，则跳过
        if ($existing_seo && $saved_prompt_version === $current_prompt_version && !isset($_POST['folio_regenerate_seo'])) {
            return;
        }

        // 生成 SEO（如果版本号不匹配，会强制重新生成）
        $this->generate_seo_for_post($post_id, $saved_prompt_version !== $current_prompt_version);
    }

    /**
     * 为文章生成 SEO
     * 
     * @param int $post_id 文章ID
     * @param bool $force_regenerate 是否强制重新生成（忽略已有标记）
     * @return bool 是否生成成功
     */
    public function generate_seo_for_post($post_id, $force_regenerate = false) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // 获取文章内容
        $title = $post->post_title;
        // 移除HTML标签但保留所有字符（包括英文、日文、韩文等）
        $content = wp_strip_all_tags($post->post_content);
        // PHP 8.2 兼容性：确保 $content 是字符串且不为 null
        if (!is_string($content)) {
            $content = '';
        }
        // 使用mb_substr确保正确处理多字节字符（日文、韩文、中文等）
        $content = mb_substr($content, 0, 2000, 'UTF-8'); // 限制内容长度

        // 构建提示词（每次都会使用最新的提示词）
        $prompt = $this->build_prompt($title, $content);

        // 调用 AI API
        $result = $this->call_ai_api($prompt);

        if ($result && isset($result['keywords']) && isset($result['description'])) {
            // 保存结果 - 使用更宽松的清理，保留英文、日文、韩文等字符
            // 只移除危险字符，保留所有正常的文本内容
            $keywords = $this->sanitize_multilingual_text($result['keywords']);
            $description = $this->sanitize_multilingual_text($result['description'], true);
            
            update_post_meta($post_id, '_folio_seo_keywords', $keywords);
            update_post_meta($post_id, '_folio_seo_description', $description);
            // 保存生成时间和提示词版本号（用于检测提示词是否更新）
            update_post_meta($post_id, '_folio_ai_seo_generated', current_time('mysql'));
            update_post_meta($post_id, '_folio_ai_seo_prompt_version', '3.0'); // 提示词版本号，更新提示词时修改此版本号
            
            return true;
        }

        return false;
    }

    /**
     * 构建 AI 提示词
     */
    private function build_prompt($title, $content) {
        $language = get_option('folio_ai_language', 'zh');
        
        // PHP 8.2 兼容性：确保 $title 是字符串
        if (!is_string($title)) {
            $title = '';
        }
        
        if ($language === 'zh') {
            // 检测标题长度，判断内容类型
            $title_length = mb_strlen($title, 'UTF-8');
            $is_short_title = $title_length <= 20;
            
            $content_type_hint = $is_short_title 
                ? "【⚠️ 关键提示：这是时尚产品展示内容】

这是一个短标题（不超过20字符），**这是时尚商品的展示页面**，不是其他任何类型的内容。

【重要：标题和内容的解读方式】
1. **标题解读**：
   - 标题中的人名（如'小美'、'Lisa'、'美咲'）、地名（如'东京'、'上海'、'巴黎'）以及常用词语（如'夏日'、'清新'、'浪漫'）等，**都不是字面意思，而是产品的名称或系列名称**
   - 例如：标题'东京'是指名为'东京'的商品，不是日本东京这个城市
   - 例如：标题'小美'是指名为'小美'的商品，不是人名

2. **文章内容解读（⚠️ 非常重要）**：
   - **文章内容只是辅助信息，用于了解商品的风格、特点、主题等**
   - **绝对不要按照文章内容的字面意思来理解或生成关键词和描述**
   - 即使文章内容看起来像描述人物、地点、学术研究、故事等，**也要理解为这是商品的命名方式、风格描述或主题设定**
   - 例如：如果文章内容提到'人工智能研究者'，这不是指真的研究者，而是名为'人工智能研究者'的商品系列
   - 例如：如果文章内容提到'神经科学'，这不是指真的科学领域，而是商品的主题或命名方式
   - **文章内容中的所有描述性文字，都要理解为是在描述商品的特点、风格、主题，而不是字面意思**

3. **生成要求**：
   - 所有关键词和描述都必须围绕'时尚产品'这个核心来生成
   - 关键词要突出商品的特点、风格、魅力
   - 描述要以商品为核心，突出产品的吸引力和特色
   - 使用符合男性用户搜索习惯的自然、口语化表达"
                : "注意：这是写真图片类内容，标题较长，可以结合标题和内容来生成关键词和描述。";
            
            return "你是一位专业的SEO优化专家，专门为时尚内容网站（包括写真图片和时尚商品展示）进行内容优化。

【⚠️ 核心任务】
根据以下文章的标题和内容，生成专业的SEO关键词和描述。**如果是时尚商品类内容，必须以产品的角度来解读，绝不能按照字面意思理解。**

【文章标题】
{$title}

【文章内容摘要】
{$content}

【内容类型提示】
{$content_type_hint}

【任务要求】
1. **关键词生成（5个关键词）**：
   - 必须严格生成5个关键词，不多不少
   - **对于时尚商品类内容（短标题）：**
     - **关键词必须围绕'时尚产品'来生成，绝不能按照标题或内容的字面意思来理解**
     - 标题中的词语（人名、地名、常用词）都是商品名称，要以产品的角度来解读
     - 文章内容只是参考，用于了解商品风格和特点，不要按照内容的字面意思来生成关键词
     - 关键词要突出商品的特点、风格、魅力，例如：性感内衣、蕾丝内衣、情趣内衣、内衣套装等
     - 可以结合标题中的商品名称，但必须以产品的角度来理解
   - 使用自然、口语化的表达方式，符合男性用户的搜索习惯
   - 避免使用生硬、官方化的词汇，优先使用用户常用的搜索词
   - 关键词要具有搜索价值和商业价值
   - 可以包含长尾关键词，但要自然流畅

2. **SEO描述生成（150-160字符）**：
   - 描述要专业、吸引人，能有效提高点击率
   - 必须包含核心关键词，但要自然融入，不要堆砌
   - **对于时尚商品类内容（短标题）：**
     - **描述必须以商品为核心，绝不能按照标题或内容的字面意思来编写**
     - 即使标题看起来像人名、地名，文章内容看起来像描述人物、地点、学术研究等，也要理解为这是商品的命名方式或主题设定
     - 描述要突出商品的特色、风格、魅力点，吸引用户点击
     - 例如：'这是一款名为XX的性感商品，采用XX风格设计，展现XX魅力...'
     - 不要描述成人物传记、学术研究、地点介绍等，而要描述成商品展示
   - 突出内容亮点和吸引力
   - 语言要流畅自然，符合目标用户阅读习惯

【输出格式】
请严格按照以下JSON格式返回，不要包含任何其他文字说明：
{
    \"keywords\": \"关键词1,关键词2,关键词3,关键词4,关键词5\",
    \"description\": \"150-160字符的SEO描述内容\"
}

【⚠️ 重要要求（必须严格遵守）】
- 关键词必须用中文逗号分隔
- 关键词要自然、口语化，避免生硬表达
- 描述要专业且吸引人，能激发用户点击欲望
- **如果标题或内容中包含英文、日文、韩文等非中文字符，必须原样保留，不能翻译或替换**
- **关键词中可以包含英文、日文、韩文等字符，只要它们出现在原文中，就要直接使用**
- **描述中如果原文有英文、日文、韩文等字符，也要原样保留**

- **【⚠️ 最关键】对于时尚商品类内容（短标题）：**
  - **标题中的人名、地名、常用词都是商品名称，必须以产品的角度来解读**
  - **文章内容只是参考信息，用于了解商品风格，绝不能按照内容的字面意思来理解或生成**
  - **即使文章内容看起来像描述人物、地点、学术研究、故事等，也要理解为这是商品的命名方式、主题设定或风格描述**
  - **所有关键词和描述都必须围绕'时尚产品'来生成，例如：性感内衣、蕾丝内衣、情趣内衣、内衣套装、商品展示等**
  - **描述要写成商品展示的形式，例如：'这是一款名为XX的性感商品...'，绝不能写成人物传记、学术介绍、地点介绍等形式**
  - **如果生成的内容看起来不像商品，那就是错误的，必须重新理解**

- 确保输出的是有效的JSON格式";
        } else {
            // 检测标题长度，判断内容类型
            $title_length = mb_strlen($title, 'UTF-8');
            $is_short_title = $title_length <= 20;
            
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
                : "Note: This is photography/image content with a longer title. You can combine title and content to generate keywords and description.";
            
            return "You are a professional SEO expert specializing in optimizing content for fashion websites (including photography/images and fashion product showcases). Please generate professional SEO keywords and description based on the following article title and content.

【⚠️ Core Task】
Generate professional SEO keywords and description based on the following article title and content. **If it's fashion product content, you must interpret it from the product perspective, never according to literal meanings.**

【Article Title】
{$title}

【Content Excerpt】
{$content}

【Content Type Hint】
{$content_type_hint}

【Requirements】
1. **Keywords Generation (5 keywords exactly)**:
   - Must generate exactly 5 keywords, no more, no less
   - Keywords should closely combine title and content, extracting core themes
   - **If the title is very short (20 characters or less), focus on extracting keywords from the content, as the title has limited information**
   - **For fashion product content (short title):**
     - **Keywords must focus on 'fashion products', never interpret according to literal meanings of title or content**
     - Words in the title (names, place names, common words) are product names - interpret from product perspective
     - Content is only reference for understanding product style and features - do NOT generate keywords according to literal meanings
     - Keywords should highlight product features, style, appeal, e.g., sexy lingerie, lace lingerie, lingerie sets, etc.
     - Can combine product names from title, but must interpret from product perspective
   - Use natural, conversational expressions that match male users' search habits
   - Avoid stiff, official vocabulary; prioritize commonly used search terms
   - Keywords should have search value and commercial value
   - Can include long-tail keywords, but must be natural and fluent

2. **SEO Description Generation (150-160 characters)**:
   - Description should be professional and attractive, effectively improving click-through rate
   - Must include core keywords, but naturally integrated, avoid keyword stuffing
   - **If the title is short, the description should provide more detailed information to help users understand the content highlights**
   - **For fashion product content (short title):**
     - **Description must focus on products as the core, never interpret according to literal meanings of title or content**
     - Even if the title looks like a name or place name, and content looks like describing people, places, academic research, etc., understand it as product naming or theme setting
     - Description should highlight product features, style, and appeal points
     - Example: 'This is a sexy product named XX, featuring XX style design, showcasing XX appeal...'
     - Do NOT write as biography, academic research, location introduction, etc., but as product showcase
   - Highlight content highlights and appeal
   - Language should be fluent and natural, matching target audience reading habits

【Output Format】
Please return strictly in the following JSON format, without any other text:
{
    \"keywords\": \"keyword1,keyword2,keyword3,keyword4,keyword5\",
    \"description\": \"150-160 character SEO description\"
}

【Important Requirements】
- Keywords must be separated by commas
- Keywords should be natural and conversational, avoid stiff expressions
- Description should be professional and attractive, able to stimulate user click desire
- **If the title or content contains English, Japanese, Korean, or other non-Chinese characters, they must be preserved exactly as they appear, do not translate or replace them**
- **Keywords can include English, Japanese, Korean, or other characters if they appear in the original text, use them directly**
- **Descriptions should also preserve English, Japanese, Korean, or other characters exactly as they appear in the original**
- **For short title content (fashion products), make full use of the article content to supplement information and generate richer, more attractive keywords and descriptions**
- **【Critical】For fashion product content (short title):**
  - **Names, place names, and common words in the title are product names - must interpret from product perspective**
  - **Article content is only reference information for understanding product style - never interpret or generate according to literal meanings**
  - **Even if article content looks like describing people, places, academic research, stories, etc., understand it as product naming methods, theme settings, or style descriptions**
  - **All keywords and descriptions must focus on 'fashion products', e.g., sexy lingerie, lace lingerie, lingerie sets, product showcase, etc.**
  - **Description should be written as product showcase format, e.g., 'This is a sexy product named XX...', never write as biography, academic introduction, location introduction, etc.**
  - **If generated content doesn't look like products, it's wrong and must be reinterpreted**
- Ensure output is valid JSON format";
        }
    }

    /**
     * 安全清理多语言文本
     * 保留英文、日文、韩文、中文等所有正常字符，只移除危险字符
     * 
     * @param string $text 要清理的文本
     * @param bool $allow_newlines 是否允许换行符（用于描述）
     * @return string 清理后的文本
     */
    private function sanitize_multilingual_text($text, $allow_newlines = false) {
        if (empty($text)) {
            return '';
        }
        
        // 移除HTML标签和脚本
        $text = wp_strip_all_tags($text);
        
        // 移除控制字符（保留换行符如果允许）
        if ($allow_newlines) {
            // 保留换行符、回车符、制表符
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        } else {
            // 移除所有控制字符包括换行符
            $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
        }
        
        // 移除零宽字符
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);
        
        // 保留所有可见字符（包括英文、日文、韩文、中文、数字、标点等）
        // 只移除真正危险的字符，保留所有正常的文本内容
        
        return trim($text);
    }

    /**
     * 调用 AI API（使用API管理器）
     */
    private function call_ai_api($prompt) {
        // 使用统一的API管理器调用
        $content = $this->api_manager->call_api($prompt, array(
            'temperature' => 0.7,
            'max_tokens' => 500,
        ));
        
        if (empty($content)) {
            return false;
        }
        
        // 解析响应
        return $this->parse_ai_response($content);
    }

    /**
     * 解析 AI 响应
     */
    private function parse_ai_response($content) {
        if (empty($content)) {
            return false;
        }

        // 尝试解析 JSON
        // 提取 JSON 部分（AI 可能返回额外的文本）
        if (preg_match('/\{[^{}]*"keywords"[^{}]*"description"[^{}]*\}/s', $content, $matches)) {
            $json = $matches[0];
            $result = json_decode($json, true);
            if ($result && isset($result['keywords']) && isset($result['description'])) {
                // 处理关键词：确保是5个关键词
                $keywords = $result['keywords'];
                if (is_string($keywords)) {
                    // 分割关键词，处理中文和英文逗号
                    $keywords_array = preg_split('/[,，]/', $keywords);
                    $keywords_array = array_map('trim', $keywords_array);
                    $keywords_array = array_filter($keywords_array); // 移除空值
                    $keywords_array = array_values($keywords_array); // 重新索引
                    
                    // 如果关键词数量不是5个，尝试调整
                    if (count($keywords_array) > 5) {
                        // 取前5个
                        $keywords_array = array_slice($keywords_array, 0, 5);
                    } elseif (count($keywords_array) < 5 && count($keywords_array) > 0) {
                        // 如果少于5个，保持原样（AI可能认为这些关键词已经足够）
                    }
                    
                    $result['keywords'] = implode(',', $keywords_array);
                }
                
                return $result;
            }
        }

        return false;
    }

    /**
     * 添加设置页面
     */
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=portfolio',
            __('AI SEO 设置', 'folio'),
            __('AI SEO 设置', 'folio'),
            'manage_options',
            'mpb-ai-seo',
            array($this, 'render_settings_page')
        );
    }

    /**
     * 注册设置
     */
    public function register_settings() {
        register_setting('folio_ai_seo_settings', 'folio_ai_provider');
        register_setting('folio_ai_seo_settings', 'folio_ai_api_key');
        register_setting('folio_ai_seo_settings', 'folio_ai_endpoint');
        register_setting('folio_ai_seo_settings', 'folio_ai_model');
        register_setting('folio_ai_seo_settings', 'folio_ai_auto_generate');
        register_setting('folio_ai_seo_settings', 'folio_ai_language');
    }

    /**
     * 渲染设置页面
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI SEO 设置', 'folio'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('folio_ai_seo_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('AI 服务提供商', 'folio'); ?></th>
                        <td>
                            <select name="folio_ai_provider">
                                <option value="openai" <?php selected(get_option('folio_ai_provider'), 'openai'); ?>>OpenAI (GPT)</option>
                                <option value="claude" <?php selected(get_option('folio_ai_provider'), 'claude'); ?>>Anthropic (Claude)</option>
                                <option value="deepseek" <?php selected(get_option('folio_ai_provider'), 'deepseek'); ?>>DeepSeek</option>
                                <option value="qwen" <?php selected(get_option('folio_ai_provider'), 'qwen'); ?>>通义千问 (Qwen)</option>
                            </select>
                            <p class="description"><?php esc_html_e('选择 AI 服务提供商', 'folio'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('API Key', 'folio'); ?></th>
                        <td>
                            <input type="password" name="folio_ai_api_key" value="<?php echo esc_attr(get_option('folio_ai_api_key')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('输入您的 API Key', 'folio'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('自定义 API 端点', 'folio'); ?></th>
                        <td>
                            <input type="url" name="folio_ai_endpoint" value="<?php echo esc_attr(get_option('folio_ai_endpoint')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('可选，用于代理或自定义端点', 'folio'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('模型', 'folio'); ?></th>
                        <td>
                            <input type="text" name="folio_ai_model" value="<?php echo esc_attr(get_option('folio_ai_model', 'gpt-3.5-turbo')); ?>" class="regular-text">
                            <p class="description">
                                <?php esc_html_e('OpenAI: gpt-3.5-turbo, gpt-4 | Claude: claude-3-haiku-20240307 | DeepSeek: deepseek-chat | Qwen: qwen-turbo', 'folio'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('生成语言', 'folio'); ?></th>
                        <td>
                            <select name="folio_ai_language">
                                <option value="zh" <?php selected(get_option('folio_ai_language', 'zh'), 'zh'); ?>>中文</option>
                                <option value="en" <?php selected(get_option('folio_ai_language'), 'en'); ?>>English</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('自动生成', 'folio'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="folio_ai_auto_generate" value="1" <?php checked(get_option('folio_ai_auto_generate'), 1); ?>>
                                <?php esc_html_e('发布文章时自动生成 SEO 内容', 'folio'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * 添加 SEO Meta Box
     */
    public function add_seo_meta_box() {
        add_meta_box(
            'folio_seo_meta_box',
            __('SEO 设置', 'folio'),
            array($this, 'render_seo_meta_box'),
            'portfolio',
            'normal',
            'high'
        );
    }

    /**
     * 渲染 SEO Meta Box
     */
    public function render_seo_meta_box($post) {
        wp_nonce_field('folio_seo_meta_box', 'folio_seo_meta_box_nonce');
        
        $keywords = get_post_meta($post->ID, '_folio_seo_keywords', true);
        $description = get_post_meta($post->ID, '_folio_seo_description', true);
        $generated_time = get_post_meta($post->ID, '_folio_ai_seo_generated', true);
        ?>
        <div class="mpb-seo-meta-box">
            <p>
                <label for="folio_seo_keywords"><strong><?php esc_html_e('SEO 关键词', 'folio'); ?></strong></label>
                <input type="text" id="folio_seo_keywords" name="folio_seo_keywords" value="<?php echo esc_attr($keywords); ?>" class="widefat">
                <span class="description"><?php esc_html_e('建议5个关键词，用英文逗号分隔。关键词应自然、口语化，符合用户搜索习惯', 'folio'); ?></span>
            </p>
            
            <p>
                <label for="folio_seo_description"><strong><?php esc_html_e('SEO 描述', 'folio'); ?></strong></label>
                <textarea id="folio_seo_description" name="folio_seo_description" rows="3" class="widefat"><?php echo esc_textarea($description); ?></textarea>
                <span class="description"><?php esc_html_e('建议 150-160 字符', 'folio'); ?></span>
            </p>
            
            <?php if ($this->api_manager->has_apis()) : ?>
            <p>
                <button type="button" id="mpb-generate-ai-seo" class="button button-secondary">
                    <span class="dashicons dashicons-admin-generic" style="vertical-align: middle;"></span>
                    <?php esc_html_e('AI 生成 SEO', 'folio'); ?>
                </button>
                <span id="mpb-ai-seo-status"></span>
                
                <?php if ($generated_time) : ?>
                <span class="description" style="margin-left: 10px;">
                    <?php printf(esc_html__('上次 AI 生成: %s', 'folio'), $generated_time); ?>
                    <br>
                    <strong style="color: #d63638;"><?php esc_html_e('提示：点击按钮将使用最新提示词重新生成，会覆盖现有内容', 'folio'); ?></strong>
                </span>
                <?php endif; ?>
            </p>
            
            <script>
            jQuery(document).ready(function($) {
                $('#mpb-generate-ai-seo').on('click', function() {
                    var btn = $(this);
                    var status = $('#mpb-ai-seo-status');
                    
                    btn.prop('disabled', true);
                    status.text('<?php esc_html_e('正在生成...', 'folio'); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'folio_generate_ai_seo',
                            post_id: <?php echo $post->ID; ?>,
                            force_regenerate: '1', // 强制重新生成，使用最新提示词
                            nonce: '<?php echo wp_create_nonce('folio_ai_seo_nonce'); ?>'
                        },
                        success: function(response) {
                            btn.prop('disabled', false);
                            if (response.success) {
                                $('#folio_seo_keywords').val(response.data.keywords);
                                $('#folio_seo_description').val(response.data.description);
                                status.text('<?php esc_html_e('生成成功！', 'folio'); ?>').css('color', 'green');
                            } else {
                                status.text(response.data.message || '<?php esc_html_e('生成失败', 'folio'); ?>').css('color', 'red');
                            }
                        },
                        error: function() {
                            btn.prop('disabled', false);
                            status.text('<?php esc_html_e('请求失败', 'folio'); ?>').css('color', 'red');
                        }
                    });
                });
            });
            </script>
            <?php else : ?>
            <p class="description">
                <?php 
                printf(
                    esc_html__('请先在 %s 中配置 AI API Key 以启用 AI 生成功能', 'folio'),
                    '<a href="' . admin_url('edit.php?post_type=portfolio&page=mpb-ai-seo') . '">' . esc_html__('AI SEO 设置', 'folio') . '</a>'
                );
                ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * 保存 SEO Meta
     */
    public function save_seo_meta($post_id) {
        if (!isset($_POST['folio_seo_meta_box_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['folio_seo_meta_box_nonce'], 'folio_seo_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['folio_seo_keywords'])) {
            // 使用多语言安全的清理方法，保留英文、日文、韩文等字符
            update_post_meta($post_id, '_folio_seo_keywords', $this->sanitize_multilingual_text($_POST['folio_seo_keywords']));
        }

        if (isset($_POST['folio_seo_description'])) {
            // 使用多语言安全的清理方法，保留英文、日文、韩文等字符
            update_post_meta($post_id, '_folio_seo_description', $this->sanitize_multilingual_text($_POST['folio_seo_description'], true));
        }
    }

    /**
     * AJAX 手动生成 SEO
     */
    public function ajax_generate_seo() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_ai_seo_nonce')) {
            wp_send_json_error(array('message' => __('安全验证失败', 'folio')));
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $force_regenerate = isset($_POST['force_regenerate']) && $_POST['force_regenerate'] === '1';

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('权限不足', 'folio')));
        }

        // AJAX 手动生成时总是强制重新生成，使用最新的提示词
        $result = $this->generate_seo_for_post($post_id, true);

        if ($result) {
            // 直接返回原始数据，确保多语言字符不被过滤
            $keywords = get_post_meta($post_id, '_folio_seo_keywords', true);
            $description = get_post_meta($post_id, '_folio_seo_description', true);
            
            wp_send_json_success(array(
                'keywords' => $keywords,
                'description' => $description,
            ));
        } else {
            wp_send_json_error(array('message' => __('AI 生成失败，请检查 API 配置', 'folio')));
        }
    }
}

// 初始化
new folio_AI_SEO();
