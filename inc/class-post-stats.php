<?php
/**
 * Post Statistics
 * 
 * 文章统计功能：阅读数、点赞数、收藏数
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_Post_Stats {

    public function __construct() {
        // 在单篇文章页面增加阅读数
        add_action('wp_head', array($this, 'track_post_views'));
        
        // 注册 AJAX 处理
        add_action('wp_ajax_folio_like_post', array($this, 'ajax_like_post'));
        add_action('wp_ajax_nopriv_folio_like_post', array($this, 'ajax_like_post'));
        
        // 收藏功能 AJAX（需要注册 nopriv 版本以便未登录用户能收到友好错误提示）
        add_action('wp_ajax_folio_favorite_post', array($this, 'ajax_favorite_post'));
        add_action('wp_ajax_nopriv_folio_favorite_post', array($this, 'ajax_favorite_post'));
        
        // 在后台显示统计列
        add_filter('manage_portfolio_posts_columns', array($this, 'add_stats_columns'));
        add_action('manage_portfolio_posts_custom_column', array($this, 'display_stats_columns'), 10, 2);
        add_filter('manage_edit-portfolio_sortable_columns', array($this, 'sortable_stats_columns'));
        
        // 添加用户收藏页面短代码
        add_shortcode('folio_user_favorites', array($this, 'render_user_favorites'));
    }

    /**
     * 追踪文章阅读数
     */
    public function track_post_views() {
        if (!is_singular('post')) {
            return;
        }

        // 排除管理员和编辑
        if (current_user_can('edit_posts')) {
            return;
        }

        $post_id = get_the_ID();
        $this->increment_views($post_id);
    }

    /**
     * 增加阅读数
     */
    public function increment_views($post_id) {
        $views = (int) get_post_meta($post_id, 'views', true);
        update_post_meta($post_id, 'views', $views + 1);
    }

    /**
     * 获取阅读数
     */
    public static function get_views($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $views = (int) get_post_meta($post_id, 'views', true);
        
        // 格式化大数字
        if ($views >= 1000) {
            return sprintf('%.1fK', $views / 1000);
        }
        
        return $views;
    }

    /**
     * 获取点赞数
     */
    public static function get_likes($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $likes = (int) get_post_meta($post_id, 'likes', true);
        
        if ($likes >= 1000) {
            return sprintf('%.1fK', $likes / 1000);
        }
        
        return $likes;
    }

    /**
     * 增加点赞数
     */
    public static function increment_likes($post_id) {
        $likes = (int) get_post_meta($post_id, 'likes', true);
        update_post_meta($post_id, 'likes', $likes + 1);
        return $likes + 1;
    }

    /**
     * 检查用户是否已点赞
     */
    public static function has_liked($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $cookie_name = 'folio_liked_' . $post_id;
        return isset($_COOKIE[$cookie_name]);
    }

    /**
     * AJAX 点赞处理
     */
    public function ajax_like_post() {
        // 验证 nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_ajax_nonce')) {
            wp_send_json_error(array('message' => __('安全验证失败', 'folio')));
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        
        if (!$post_id || get_post_type($post_id) !== 'post') {
            wp_send_json_error(array('message' => __('无效的文章', 'folio')));
        }

        // 检查是否已点赞（通过 cookie）
        $cookie_name = 'folio_liked_' . $post_id;
        if (isset($_COOKIE[$cookie_name])) {
            wp_send_json_error(array('message' => __('您已经点赞过了', 'folio')));
        }

        // 增加点赞数
        $new_likes = self::increment_likes($post_id);

        // 设置 cookie（30天有效）
        setcookie($cookie_name, '1', time() + (30 * DAY_IN_SECONDS), '/');

        wp_send_json_success(array(
            'likes' => $new_likes,
            'message' => __('感谢您的点赞！', 'folio')
        ));
    }

    // ========================================
    // 收藏功能
    // ========================================

    /**
     * 获取收藏数
     */
    public static function get_favorites($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $favorites = (int) get_post_meta($post_id, 'follow_num', true);
        
        if ($favorites >= 1000) {
            return sprintf('%.1fK', $favorites / 1000);
        }
        
        return $favorites;
    }

    /**
     * 检查用户是否已收藏
     */
    public static function has_favorited($post_id = null, $user_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $favorites = get_user_meta($user_id, 'folio_favorite_posts', true);
        
        if (empty($favorites) || !is_array($favorites)) {
            return false;
        }
        
        return in_array($post_id, $favorites);
    }

    /**
     * 添加收藏
     */
    public static function add_favorite($post_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        // 获取用户收藏列表
        $favorites = get_user_meta($user_id, 'folio_favorite_posts', true);
        
        if (empty($favorites) || !is_array($favorites)) {
            $favorites = array();
        }
        
        // 检查是否已收藏
        if (in_array($post_id, $favorites)) {
            return false;
        }
        
        // 添加到收藏列表
        $favorites[] = $post_id;
        update_user_meta($user_id, 'folio_favorite_posts', $favorites);
        
        // 增加文章收藏数
        $count = (int) get_post_meta($post_id, 'follow_num', true);
        update_post_meta($post_id, 'follow_num', $count + 1);
        
        return true;
    }

    /**
     * 取消收藏
     */
    public static function remove_favorite($post_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        // 获取用户收藏列表
        $favorites = get_user_meta($user_id, 'folio_favorite_posts', true);
        
        if (empty($favorites) || !is_array($favorites)) {
            return false;
        }
        
        // 检查是否已收藏
        $key = array_search($post_id, $favorites);
        if ($key === false) {
            return false;
        }
        
        // 从收藏列表移除
        unset($favorites[$key]);
        $favorites = array_values($favorites); // 重新索引
        update_user_meta($user_id, 'folio_favorite_posts', $favorites);
        
        // 减少文章收藏数
        $count = (int) get_post_meta($post_id, 'follow_num', true);
        if ($count > 0) {
            update_post_meta($post_id, 'follow_num', $count - 1);
        }
        
        return true;
    }

    /**
     * 获取用户收藏的文章列表
     */
    public static function get_user_favorites($user_id = null, $limit = -1) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return array();
        }
        
        $favorites = get_user_meta($user_id, 'folio_favorite_posts', true);
        
        if (empty($favorites) || !is_array($favorites)) {
            return array();
        }
        
        // 反转数组，最新收藏的在前面
        $favorites = array_reverse($favorites);
        
        if ($limit > 0) {
            $favorites = array_slice($favorites, 0, $limit);
        }
        
        return $favorites;
    }

    /**
     * AJAX 收藏处理
     */
    public function ajax_favorite_post() {
        // 验证 nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_ajax_nonce')) {
            wp_send_json_error(array('message' => __('安全验证失败', 'folio')));
        }

        // 检查用户是否登录
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('请先登录', 'folio'),
                'need_login' => true
            ));
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'add';
        
        if (!$post_id || get_post_type($post_id) !== 'post') {
            wp_send_json_error(array('message' => __('无效的文章', 'folio')));
        }

        if ($action_type === 'remove') {
            // 取消收藏
            $result = self::remove_favorite($post_id);
            if ($result) {
                wp_send_json_success(array(
                    'favorites' => self::get_favorites($post_id),
                    'is_favorited' => false,
                    'message' => __('已取消收藏', 'folio')
                ));
            } else {
                wp_send_json_error(array('message' => __('取消收藏失败', 'folio')));
            }
        } else {
            // 添加收藏
            if (self::has_favorited($post_id)) {
                wp_send_json_error(array('message' => __('您已经收藏过了', 'folio')));
            }
            
            $result = self::add_favorite($post_id);
            if ($result) {
                wp_send_json_success(array(
                    'favorites' => self::get_favorites($post_id),
                    'is_favorited' => true,
                    'message' => __('收藏成功！', 'folio')
                ));
            } else {
                wp_send_json_error(array('message' => __('收藏失败', 'folio')));
            }
        }
    }

    /**
     * 渲染用户收藏列表（短代码）
     */
    public function render_user_favorites($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('请先登录查看收藏', 'folio') . '</p>';
        }

        $atts = shortcode_atts(array(
            'limit' => 12,
            'columns' => 4,
        ), $atts);

        $favorites = self::get_user_favorites(null, (int) $atts['limit']);

        if (empty($favorites)) {
            return '<p>' . __('暂无收藏的作品', 'folio') . '</p>';
        }

        ob_start();
        ?>
        <div class="mpb-favorites-grid grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-<?php echo esc_attr($atts['columns']); ?> gap-6">
            <?php
            foreach ($favorites as $post_id) {
                $post = get_post($post_id);
                if (!$post || $post->post_status !== 'publish') {
                    continue;
                }
                
                setup_postdata($post);
                get_template_part('template-parts/content', 'portfolio');
            }
            wp_reset_postdata();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 添加后台统计列
     */
    public function add_stats_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'date') {
                $new_columns['views'] = __('浏览量', 'folio');
                $new_columns['likes'] = __('点赞', 'folio');
                $new_columns['follow_num'] = __('收藏', 'folio');
            }
        }
        
        return $new_columns;
    }

    /**
     * 显示统计列内容
     */
    public function display_stats_columns($column, $post_id) {
        switch ($column) {
            case 'views':
                echo '<span class="folio-post-stat views">' . esc_html(self::get_views($post_id)) . '</span>';
                break;
            case 'likes':
                echo '<span class="folio-post-stat likes">' . esc_html(self::get_likes($post_id)) . '</span>';
                break;
            case 'follow_num':
                echo '<span class="folio-post-stat favorites">' . esc_html(self::get_favorites($post_id)) . '</span>';
                break;
        }
    }

    /**
     * 设置可排序列
     */
    public function sortable_stats_columns($columns) {
        $columns['views'] = 'views';
        $columns['likes'] = 'likes';
        $columns['follow_num'] = 'follow_num';
        return $columns;
    }

}

// 初始化
new folio_Post_Stats();

/**
 * 模板标签函数
 */
if (!function_exists('folio_get_views')) {
    function folio_get_views($post_id = null) {
        return folio_Post_Stats::get_views($post_id);
    }
}

if (!function_exists('folio_get_likes')) {
    function folio_get_likes($post_id = null) {
        return folio_Post_Stats::get_likes($post_id);
    }
}

if (!function_exists('folio_has_liked')) {
    function folio_has_liked($post_id = null) {
        return folio_Post_Stats::has_liked($post_id);
    }
}

if (!function_exists('folio_get_favorites')) {
    function folio_get_favorites($post_id = null) {
        return folio_Post_Stats::get_favorites($post_id);
    }
}

if (!function_exists('folio_has_favorited')) {
    function folio_has_favorited($post_id = null) {
        return folio_Post_Stats::has_favorited($post_id);
    }
}

if (!function_exists('folio_get_user_favorites')) {
    function folio_get_user_favorites($user_id = null, $limit = -1) {
        return folio_Post_Stats::get_user_favorites($user_id, $limit);
    }
}
