<?php
/**
 * Frontend Resource Optimizer
 * 
 * 前端资源优化器 - 压缩CSS/JS、优化图片、延迟加载等
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Frontend_Optimizer {

    // 优化选项
    private $options;
    
    // 资源版本
    private static $asset_version;

    public function __construct() {
        // 获取优化选项 - 从主题设置中读取
        $theme_options = get_option('folio_theme_options', array());
        $this->options = isset($theme_options['frontend_optimization']) 
            ? $theme_options['frontend_optimization'] 
            : array();
        
        // 如果没有配置，尝试从旧位置读取（向后兼容）
        if (empty($this->options)) {
            $old_options = get_option('folio_frontend_optimization', array());
            if (!empty($old_options)) {
                $this->options = $old_options;
            }
        }
        
        // 设置默认值
        $defaults = array(
            'enabled' => true, // 总开关
            'minify_css' => true,
            'minify_js' => true,
            'combine_css' => false, // 默认关闭CSS合并，避免FOUC问题
            'combine_js' => false, // 默认关闭JS合并，避免兼容性问题
            'lazy_load_images' => true,
            'optimize_fonts' => true,
            'preload_critical' => true,
            'defer_non_critical' => false, // 默认关闭延迟加载，避免FOUC
            'enable_gzip' => true,
            'cache_busting' => true
        );
        
        $this->options = wp_parse_args($this->options, $defaults);
        
        // 设置资源版本（需要在 AJAX 处理中使用）
        self::$asset_version = get_option('folio_asset_version', time());
        
        // AJAX处理（必须在后台可用，所以放在 is_admin() 检查之前）
        add_action('wp_ajax_folio_optimize_assets', array($this, 'ajax_optimize_assets'));
        add_action('wp_ajax_folio_clear_optimized_cache', array($this, 'ajax_clear_optimized_cache'));
        
        // 如果优化被禁用，直接返回（但 AJAX handlers 已注册）
        if (!$this->options['enabled'] || defined('FOLIO_DISABLE_OPTIMIZATION') && FOLIO_DISABLE_OPTIMIZATION) {
            return;
        }
        
        // 只在非后台页面运行前端优化
        if (is_admin()) {
            return;
        }
        
        // 初始化优化功能
        add_action('init', array($this, 'init_optimization'));
        
        // CSS优化
        add_action('wp_enqueue_scripts', array($this, 'optimize_css'), 999);
        add_filter('style_loader_src', array($this, 'add_cache_busting'), 10, 2);
        
        // JavaScript优化
        add_action('wp_enqueue_scripts', array($this, 'optimize_js'), 999);
        add_filter('script_loader_src', array($this, 'add_cache_busting'), 10, 2);
        
        // 图片优化
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_lazy_loading'), 10, 3);
        add_filter('the_content', array($this, 'add_content_image_lazy_loading'));
        
        // 字体优化
        add_action('wp_head', array($this, 'optimize_fonts'), 1);
        
        // 关键资源预加载
        add_action('wp_head', array($this, 'preload_critical_resources'), 2);
        
        // 内联关键CSS以防止FOUC
        add_action('wp_head', array($this, 'inline_critical_css'), 1);
        
        // 非关键资源延迟
        add_filter('script_loader_tag', array($this, 'defer_non_critical_scripts'), 10, 3);
        add_filter('style_loader_tag', array($this, 'defer_non_critical_styles'), 10, 4);
        
        // 压缩HTML输出
        if ($this->options['minify_html'] ?? false) {
            add_action('template_redirect', array($this, 'start_html_compression'));
        }
    }

    /**
     * 初始化优化功能
     */
    public function init_optimization() {
        // 创建优化文件目录
        $upload_dir = wp_upload_dir();
        $optimize_dir = $upload_dir['basedir'] . '/folio-optimized';
        
        if (!file_exists($optimize_dir)) {
            wp_mkdir_p($optimize_dir);
            wp_mkdir_p($optimize_dir . '/css');
            wp_mkdir_p($optimize_dir . '/js');
        }
        
        // 添加.htaccess规则（如果支持）
        $this->add_htaccess_rules();
    }

    /**
     * 添加.htaccess优化规则
     */
    private function add_htaccess_rules() {
        if (!function_exists('insert_with_markers')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
        
        $htaccess_file = ABSPATH . '.htaccess';
        
        if (is_writable($htaccess_file)) {
            $rules = array(
                '# Folio Frontend Optimization',
                '<IfModule mod_expires.c>',
                'ExpiresActive On',
                'ExpiresByType text/css "access plus 1 year"',
                'ExpiresByType application/javascript "access plus 1 year"',
                'ExpiresByType image/png "access plus 1 year"',
                'ExpiresByType image/jpg "access plus 1 year"',
                'ExpiresByType image/jpeg "access plus 1 year"',
                'ExpiresByType image/gif "access plus 1 year"',
                'ExpiresByType image/webp "access plus 1 year"',
                'ExpiresByType font/woff2 "access plus 1 year"',
                '</IfModule>',
                '',
                '<IfModule mod_deflate.c>',
                'AddOutputFilterByType DEFLATE text/plain',
                'AddOutputFilterByType DEFLATE text/html',
                'AddOutputFilterByType DEFLATE text/xml',
                'AddOutputFilterByType DEFLATE text/css',
                'AddOutputFilterByType DEFLATE application/xml',
                'AddOutputFilterByType DEFLATE application/xhtml+xml',
                'AddOutputFilterByType DEFLATE application/rss+xml',
                'AddOutputFilterByType DEFLATE application/javascript',
                'AddOutputFilterByType DEFLATE application/x-javascript',
                '</IfModule>',
                '',
                '<IfModule mod_headers.c>',
                '<FilesMatch "\.(css|js|png|jpg|jpeg|gif|webp|woff2)$">',
                'Header set Cache-Control "public, max-age=31536000"',
                '</FilesMatch>',
                '</IfModule>'
            );
            
            insert_with_markers($htaccess_file, 'Folio Frontend Optimization', $rules);
        }
    }

    /**
     * 优化CSS
     */
    public function optimize_css() {
        // 只在非后台页面运行
        if (is_admin()) {
            return;
        }
        
        global $wp_styles;
        
        if (!$this->options['minify_css'] && !$this->options['combine_css']) {
            return;
        }
        
        $styles_to_combine = array();
        $critical_styles = array('mpb-style', 'mpb-theme', 'folio-style', 'theme-style');
        
        foreach ($wp_styles->queue as $handle) {
            $style = $wp_styles->registered[$handle];
            
            if ($style && $this->should_optimize_style($handle, $style->src)) {
                // 跳过关键样式，避免FOUC
                if (in_array($handle, $critical_styles)) {
                    continue;
                }
                
                $styles_to_combine[] = array(
                    'handle' => $handle,
                    'src' => $style->src,
                    'deps' => $style->deps,
                    'media' => $style->args
                );
            }
        }
        
        if (!empty($styles_to_combine) && $this->options['combine_css']) {
            $combined_file = $this->combine_css_files($styles_to_combine);
            
            if ($combined_file) {
                // 移除原始样式
                foreach ($styles_to_combine as $style) {
                    wp_dequeue_style($style['handle']);
                }
                
                // 添加合并后的样式，确保在关键样式之后加载
                wp_enqueue_style(
                    'folio-combined-styles',
                    $combined_file['url'],
                    $critical_styles, // 依赖关键样式
                    $combined_file['version'],
                    'all'
                );
            }
        }
    }

    /**
     * 判断是否应该优化样式
     */
    private function should_optimize_style($handle, $src) {
        // 跳过外部资源
        if (strpos($src, home_url()) === false && strpos($src, '/') === 0) {
            return false;
        }
        
        // 跳过管理员样式
        if (strpos($handle, 'admin') !== false) {
            return false;
        }
        
        // 跳过某些插件样式
        $skip_handles = array('contact-form-7', 'woocommerce', 'elementor');
        foreach ($skip_handles as $skip) {
            if (strpos($handle, $skip) !== false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 合并CSS文件
     */
    private function combine_css_files($styles) {
        $upload_dir = wp_upload_dir();
        $optimize_dir = $upload_dir['basedir'] . '/folio-optimized/css';
        
        // 生成缓存键
        $cache_key = md5(serialize($styles) . self::$asset_version);
        $combined_file = $optimize_dir . '/combined-' . $cache_key . '.css';
        $combined_url = $upload_dir['baseurl'] . '/folio-optimized/css/combined-' . $cache_key . '.css';
        
        // 检查缓存文件是否存在
        if (file_exists($combined_file)) {
            return array(
                'file' => $combined_file,
                'url' => $combined_url,
                'version' => self::$asset_version
            );
        }
        
        $combined_content = '';
        
        foreach ($styles as $style) {
            $file_path = $this->get_local_file_path($style['src']);
            
            if ($file_path && file_exists($file_path)) {
                $css_content = file_get_contents($file_path);
                
                // 处理相对路径
                $css_content = $this->fix_css_relative_paths($css_content, dirname($style['src']));
                
                // 压缩CSS
                if ($this->options['minify_css']) {
                    $css_content = $this->minify_css($css_content);
                }
                
                $combined_content .= "/* {$style['handle']} */\n" . $css_content . "\n\n";
            }
        }
        
        // 保存合并文件
        if (!empty($combined_content)) {
            file_put_contents($combined_file, $combined_content);
            
            return array(
                'file' => $combined_file,
                'url' => $combined_url,
                'version' => self::$asset_version
            );
        }
        
        return false;
    }

    /**
     * 压缩CSS
     */
    private function minify_css($css) {
        // 移除注释
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // 移除多余的空白
        $css = str_replace(array("\r\n", "\r", "\n", "\t"), '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        
        // 移除不必要的分号和空格
        $css = str_replace(array('; ', ' {', '{ ', ' }', '} ', ': ', ' :', ' ,', ', '), array(';', '{', '{', '}', '}', ':', ':', ',', ','), $css);
        
        return trim($css);
    }

    /**
     * 修复CSS中的相对路径
     */
    private function fix_css_relative_paths($css, $base_path) {
        return preg_replace_callback('/url\([\'"]?([^\'")]+)[\'"]?\)/', function($matches) use ($base_path) {
            $url = $matches[1];
            
            // 跳过绝对URL和data URI
            if (strpos($url, 'http') === 0 || strpos($url, 'data:') === 0 || strpos($url, '/') === 0) {
                return $matches[0];
            }
            
            // 转换相对路径为绝对路径
            $absolute_url = rtrim($base_path, '/') . '/' . ltrim($url, '/');
            return 'url(' . $absolute_url . ')';
        }, $css);
    }

    /**
     * 优化JavaScript
     */
    public function optimize_js() {
        // 只在非后台页面运行
        if (is_admin()) {
            return;
        }
        
        global $wp_scripts;
        
        if (!$this->options['minify_js'] && !$this->options['combine_js']) {
            return;
        }
        
        // JavaScript合并比CSS更复杂，需要考虑依赖关系
        // 这里只实现基本的压缩功能
        foreach ($wp_scripts->queue as $handle) {
            $script = $wp_scripts->registered[$handle];
            
            if ($script && $this->should_optimize_script($handle, $script->src)) {
                $minified_file = $this->minify_js_file($script->src, $handle);
                
                if ($minified_file) {
                    // 替换为压缩版本
                    $wp_scripts->registered[$handle]->src = $minified_file['url'];
                    $wp_scripts->registered[$handle]->ver = $minified_file['version'];
                }
            }
        }
    }

    /**
     * 判断是否应该优化脚本
     */
    private function should_optimize_script($handle, $src) {
        // 跳过jQuery和其他关键库
        $skip_handles = array('jquery', 'jquery-core', 'jquery-migrate', 'wp-');
        foreach ($skip_handles as $skip) {
            if (strpos($handle, $skip) !== false) {
                return false;
            }
        }
        
        return $this->should_optimize_style($handle, $src);
    }

    /**
     * 压缩JavaScript文件
     */
    private function minify_js_file($src, $handle) {
        $file_path = $this->get_local_file_path($src);
        
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $optimize_dir = $upload_dir['basedir'] . '/folio-optimized/js';
        
        $cache_key = md5($file_path . filemtime($file_path) . self::$asset_version);
        $minified_file = $optimize_dir . '/' . $handle . '-' . $cache_key . '.min.js';
        $minified_url = $upload_dir['baseurl'] . '/folio-optimized/js/' . $handle . '-' . $cache_key . '.min.js';
        
        // 检查缓存文件
        if (file_exists($minified_file)) {
            return array(
                'file' => $minified_file,
                'url' => $minified_url,
                'version' => self::$asset_version
            );
        }
        
        $js_content = file_get_contents($file_path);
        $minified_content = $this->minify_js($js_content);
        
        if (file_put_contents($minified_file, $minified_content)) {
            return array(
                'file' => $minified_file,
                'url' => $minified_url,
                'version' => self::$asset_version
            );
        }
        
        return false;
    }

    /**
     * 压缩JavaScript
     */
    private function minify_js($js) {
        // 简单的JavaScript压缩
        // 移除单行注释
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // 移除多行注释
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // 移除多余的空白
        $js = preg_replace('/\s+/', ' ', $js);
        
        // 移除行末分号前的空格
        $js = str_replace(' ;', ';', $js);
        
        return trim($js);
    }

    /**
     * 获取本地文件路径
     */
    private function get_local_file_path($src) {
        // 移除查询参数
        $src = strtok($src, '?');
        
        // 转换URL为本地路径
        $home_url = home_url();
        if (strpos($src, $home_url) === 0) {
            $relative_path = str_replace($home_url, '', $src);
            return ABSPATH . ltrim($relative_path, '/');
        } elseif (strpos($src, '/') === 0) {
            return ABSPATH . ltrim($src, '/');
        }
        
        return false;
    }

    /**
     * 添加缓存破坏参数
     */
    public function add_cache_busting($src, $handle) {
        // 只在非后台页面添加缓存破坏参数
        if (is_admin()) {
            return $src;
        }
        
        if (!$this->options['cache_busting']) {
            return $src;
        }
        
        // 跳过外部资源
        if (strpos($src, home_url()) === false && strpos($src, '/') !== 0) {
            return $src;
        }
        
        // 添加版本参数
        $separator = strpos($src, '?') !== false ? '&' : '?';
        return $src . $separator . 'v=' . self::$asset_version;
    }

    /**
     * 添加图片懒加载
     */
    public function add_lazy_loading($attr, $attachment, $size) {
        // 只在非后台页面运行
        if (is_admin() || is_feed()) {
            return $attr;
        }
        
        if (!$this->options['lazy_load_images']) {
            return $attr;
        }
        
        // 跳过关键图片
        if (isset($attr['class']) && strpos($attr['class'], 'no-lazy') !== false) {
            return $attr;
        }
        
        // 使用原生懒加载，浏览器原生支持，不需要JavaScript处理
        // 如果图片已经有loading属性，保持原样
        if (!isset($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }
        
        // 添加decoding属性以优化性能
        if (!isset($attr['decoding'])) {
            $attr['decoding'] = 'async';
        }
        
        return $attr;
    }

    /**
     * 为内容中的图片添加懒加载
     */
    public function add_content_image_lazy_loading($content) {
        if (!$this->options['lazy_load_images'] || is_admin() || is_feed()) {
            return $content;
        }
        
        // 使用正则表达式处理img标签
        $content = preg_replace_callback('/<img([^>]+)>/i', function($matches) {
            $img_tag = $matches[0];
            
            // 跳过已经有懒加载的图片
            if (strpos($img_tag, 'loading=') !== false || strpos($img_tag, 'data-src=') !== false) {
                return $img_tag;
            }
            
            // 跳过关键图片
            if (strpos($img_tag, 'no-lazy') !== false) {
                return $img_tag;
            }
            
            // 添加懒加载属性
            $img_tag = str_replace('<img', '<img loading="lazy"', $img_tag);
            
            return $img_tag;
        }, $content);
        
        return $content;
    }

    /**
     * 优化字体加载
     */
    public function optimize_fonts() {
        if (!$this->options['optimize_fonts']) {
            return;
        }
        
        // 预连接到Google Fonts
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        
        // 字体显示优化
        echo '<style>@font-face{font-display:swap;}</style>' . "\n";
    }

    /**
     * 预加载关键资源
     */
    public function preload_critical_resources() {
        if (!$this->options['preload_critical']) {
            return;
        }
        
        // 预加载关键CSS
        $critical_styles = array('folio-style', 'theme-style');
        global $wp_styles;
        
        foreach ($critical_styles as $handle) {
            if (isset($wp_styles->registered[$handle])) {
                $style = $wp_styles->registered[$handle];
                echo '<link rel="preload" href="' . esc_url($style->src) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
            }
        }
        
        // 预加载关键字体
        $font_files = array(
            get_template_directory_uri() . '/assets/fonts/main.woff2'
        );
        
        foreach ($font_files as $font) {
            if (file_exists(str_replace(get_template_directory_uri(), get_template_directory(), $font))) {
                echo '<link rel="preload" href="' . esc_url($font) . '" as="font" type="font/woff2" crossorigin>' . "\n";
            }
        }
    }

    /**
     * 内联关键CSS以防止FOUC
     */
    public function inline_critical_css() {
        if (!$this->options['preload_critical']) {
            return;
        }
        
        // 内联基础样式以防止FOUC
        echo '<style id="folio-critical-css">';
        echo 'body{margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}';
        echo '.site-header{background:#fff;border-bottom:1px solid #e5e5e5;}';
        echo '.site-content{min-height:50vh;}';
        echo '.premium-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:bold;color:#fff;}';
        echo '.premium-badge-vip{background:#f59e0b;}';
        echo '.premium-badge-svip{background:#ef4444;}';
        echo '.membership-protection{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin:20px 0;}';
        echo '</style>';
    }

    /**
     * 延迟非关键脚本
     */
    public function defer_non_critical_scripts($tag, $handle, $src) {
        // 只在非后台页面运行
        if (is_admin()) {
            return $tag;
        }
        
        if (!$this->options['defer_non_critical']) {
            return $tag;
        }
        
        // 关键脚本不延迟
        $critical_scripts = array('jquery', 'jquery-core', 'folio-critical');
        if (in_array($handle, $critical_scripts)) {
            return $tag;
        }
        
        // 添加defer属性
        return str_replace(' src', ' defer src', $tag);
    }

    /**
     * 延迟非关键样式
     */
    public function defer_non_critical_styles($html, $handle, $href, $media) {
        // 只在非后台页面运行
        if (is_admin()) {
            return $html;
        }
        
        if (!$this->options['defer_non_critical']) {
            return $html;
        }
        
        // 关键样式不延迟
        $critical_styles = array('folio-style', 'theme-style');
        if (in_array($handle, $critical_styles)) {
            return $html;
        }
        
        // 使用媒体查询延迟加载
        return str_replace("media='$media'", "media='print' onload=\"this.media='$media'\"", $html);
    }

    /**
     * 开始HTML压缩
     */
    public function start_html_compression() {
        if (!is_admin()) {
            ob_start(array($this, 'compress_html'));
        }
    }

    /**
     * 压缩HTML
     */
    public function compress_html($html) {
        // 保护pre和textarea标签
        $pre_blocks = array();
        $html = preg_replace_callback('/<(pre|textarea|script)[^>]*>.*?<\/\1>/is', function($matches) use (&$pre_blocks) {
            $placeholder = '<!--FOLIO_PRE_' . count($pre_blocks) . '-->';
            $pre_blocks[] = $matches[0];
            return $placeholder;
        }, $html);
        
        // 压缩HTML
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        
        // 恢复保护的内容
        foreach ($pre_blocks as $index => $block) {
            $html = str_replace('<!--FOLIO_PRE_' . $index . '-->', $block, $html);
        }
        
        return $html;
    }

    /**
     * 添加管理菜单（已移除 - 功能已整合到主题设置中）
     * 保留此方法用于向后兼容，但不再注册菜单
     */
    public function add_admin_menu() {
        // 功能已整合到主题设置页面，不再需要独立菜单
    }

    /**
     * 管理页面
     */
    public function admin_page() {
        if (isset($_POST['submit'])) {
            $this->save_options();
        }
        
        $stats = $this->get_optimization_stats();
        ?>
        <div class="wrap">
            <h1>前端优化设置</h1>
            
            <div class="folio-optimization-stats" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2>优化统计</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <h3>CSS文件</h3>
                        <p>原始: <?php echo $stats['css']['original']; ?> 个</p>
                        <p>优化后: <?php echo $stats['css']['optimized']; ?> 个</p>
                        <p>节省: <?php echo $stats['css']['savings']; ?>%</p>
                    </div>
                    <div>
                        <h3>JavaScript文件</h3>
                        <p>原始: <?php echo $stats['js']['original']; ?> 个</p>
                        <p>优化后: <?php echo $stats['js']['optimized']; ?> 个</p>
                        <p>节省: <?php echo $stats['js']['savings']; ?>%</p>
                    </div>
                    <div>
                        <h3>图片优化</h3>
                        <p>懒加载: <?php echo $stats['images']['lazy_loaded']; ?> 张</p>
                        <p>WebP支持: <?php echo $stats['images']['webp_support'] ? '是' : '否'; ?></p>
                    </div>
                </div>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('folio_optimization_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">CSS优化</th>
                        <td>
                            <label>
                                <input type="checkbox" name="minify_css" value="1" <?php checked($this->options['minify_css']); ?>>
                                压缩CSS文件
                            </label><br>
                            <label>
                                <input type="checkbox" name="combine_css" value="1" <?php checked($this->options['combine_css']); ?>>
                                合并CSS文件
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">JavaScript优化</th>
                        <td>
                            <label>
                                <input type="checkbox" name="minify_js" value="1" <?php checked($this->options['minify_js']); ?>>
                                压缩JavaScript文件
                            </label><br>
                            <label>
                                <input type="checkbox" name="combine_js" value="1" <?php checked($this->options['combine_js']); ?>>
                                合并JavaScript文件（实验性功能）
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">图片优化</th>
                        <td>
                            <label>
                                <input type="checkbox" name="lazy_load_images" value="1" <?php checked($this->options['lazy_load_images']); ?>>
                                启用图片懒加载
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">字体优化</th>
                        <td>
                            <label>
                                <input type="checkbox" name="optimize_fonts" value="1" <?php checked($this->options['optimize_fonts']); ?>>
                                优化字体加载
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">资源加载</th>
                        <td>
                            <label>
                                <input type="checkbox" name="preload_critical" value="1" <?php checked($this->options['preload_critical']); ?>>
                                预加载关键资源
                            </label><br>
                            <label>
                                <input type="checkbox" name="defer_non_critical" value="1" <?php checked($this->options['defer_non_critical']); ?>>
                                延迟非关键资源
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">缓存</th>
                        <td>
                            <label>
                                <input type="checkbox" name="cache_busting" value="1" <?php checked($this->options['cache_busting']); ?>>
                                启用缓存破坏
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('保存设置'); ?>
            </form>
            
            <div class="folio-optimization-actions" style="margin-top: 30px;">
                <h2>优化操作</h2>
                <p>
                    <button type="button" class="button button-primary" id="optimize-assets">重新优化资源</button>
                    <button type="button" class="button" id="clear-optimized-cache">清除优化缓存</button>
                </p>
            </div>
        </div>
        
        <script>
        document.getElementById('optimize-assets').addEventListener('click', function() {
            this.disabled = true;
            this.textContent = '优化中...';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=folio_optimize_assets&nonce=<?php echo wp_create_nonce('folio_optimize_assets'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('资源优化完成！');
                    location.reload();
                } else {
                    alert('优化失败：' + data.data);
                }
            })
            .finally(() => {
                this.disabled = false;
                this.textContent = '重新优化资源';
            });
        });
        
        document.getElementById('clear-optimized-cache').addEventListener('click', function() {
            if (confirm('确定要清除优化缓存吗？')) {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=folio_clear_optimized_cache&nonce=<?php echo wp_create_nonce('folio_clear_optimized_cache'); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('缓存已清除！');
                        location.reload();
                    } else {
                        alert('清除失败：' + data.data);
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * 保存选项
     */
    private function save_options() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'folio_optimization_settings')) {
            return;
        }
        
        $options = array(
            'enabled' => isset($_POST['enabled']),
            'minify_css' => isset($_POST['minify_css']),
            'minify_js' => isset($_POST['minify_js']),
            'combine_css' => isset($_POST['combine_css']),
            'combine_js' => isset($_POST['combine_js']),
            'lazy_load_images' => isset($_POST['lazy_load_images']),
            'optimize_fonts' => isset($_POST['optimize_fonts']),
            'preload_critical' => isset($_POST['preload_critical']),
            'defer_non_critical' => isset($_POST['defer_non_critical']),
            'enable_gzip' => isset($_POST['enable_gzip']),
            'cache_busting' => isset($_POST['cache_busting']),
            'minify_html' => isset($_POST['minify_html'])
        );
        
        // 保存到主题设置中
        $theme_options = get_option('folio_theme_options', array());
        $theme_options['frontend_optimization'] = $options;
        update_option('folio_theme_options', $theme_options);
        
        // 同时更新旧位置（向后兼容）
        update_option('folio_frontend_optimization', $options);
        
        $this->options = $options;
        
        // 更新资源版本
        update_option('folio_asset_version', time());
        self::$asset_version = time();
        
        echo '<div class="notice notice-success"><p>设置已保存！</p></div>';
    }

    /**
     * 获取优化统计
     */
    public function get_optimization_stats() {
        $upload_dir = wp_upload_dir();
        $optimize_dir = $upload_dir['basedir'] . '/folio-optimized';
        
        $css_files = glob($optimize_dir . '/css/*.css');
        $js_files = glob($optimize_dir . '/js/*.js');
        
        return array(
            'css' => array(
                'original' => 10, // 示例数据
                'optimized' => count($css_files),
                'savings' => 35
            ),
            'js' => array(
                'original' => 8,
                'optimized' => count($js_files),
                'savings' => 28
            ),
            'images' => array(
                'lazy_loaded' => 50,
                'webp_support' => function_exists('imagewebp')
            )
        );
    }

    /**
     * AJAX优化资源
     */
    public function ajax_optimize_assets() {
        if (!wp_verify_nonce($_POST['nonce'], 'folio_optimize_assets')) {
            wp_send_json_error('安全验证失败');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
        }
        
        // 清除现有优化文件
        $this->clear_optimized_files();
        
        // 更新资源版本
        update_option('folio_asset_version', time());
        
        wp_send_json_success('资源优化完成');
    }

    /**
     * AJAX清除优化缓存
     */
    public function ajax_clear_optimized_cache() {
        // 检查 nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'folio_clear_optimized_cache')) {
            wp_send_json_error('安全验证失败');
        }
        
        // 检查权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
        }
        
        try {
            // 清除优化文件
        $this->clear_optimized_files();
            
            // 更新资源版本号，确保浏览器加载新文件
            $new_version = time();
            update_option('folio_asset_version', $new_version);
            self::$asset_version = $new_version;
            
        wp_send_json_success('缓存已清除');
        } catch (Exception $e) {
            wp_send_json_error('清除缓存时出错：' . $e->getMessage());
        }
    }

    /**
     * 清除优化文件
     */
    private function clear_optimized_files() {
        $upload_dir = wp_upload_dir();
        $optimize_dir = $upload_dir['basedir'] . '/folio-optimized';
        
        if (!is_dir($optimize_dir)) {
            return;
        }
        
        // 分别处理 CSS 和 JS 目录，避免使用 GLOB_BRACE（在某些系统上不可用）
        $css_dir = $optimize_dir . '/css';
        $js_dir = $optimize_dir . '/js';
        
        // 清除 CSS 文件
        if (is_dir($css_dir)) {
            $css_files = glob($css_dir . '/*');
            if ($css_files !== false) {
                foreach ($css_files as $file) {
                if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
        
        // 清除 JS 文件
        if (is_dir($js_dir)) {
            $js_files = glob($js_dir . '/*');
            if ($js_files !== false) {
                foreach ($js_files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
    }
}

// 初始化前端优化器
new folio_Frontend_Optimizer();