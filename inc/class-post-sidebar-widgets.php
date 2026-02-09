<?php
/**
 * 文章页侧边栏小工具
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 文章标签小工具
 */
class folio_Post_Tags_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'folio_post_tags_widget',
            __('文章标签', 'folio'),
            array('description' => __('显示当前文章的标签', 'folio'))
        );
    }

    /**
     * 前端显示
     */
    public function widget($args, $instance) {
        // 只在文章页显示
        if (!is_single()) {
            return;
        }

        $title = !empty($instance['title']) ? $instance['title'] : __('Tags', 'folio');
        $post_id = get_the_ID();
        
        $tags = get_the_tags($post_id);
        
        if (empty($tags) || is_wp_error($tags)) {
            return;
        }

        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        
        echo '<div class="tags-list flex flex-wrap gap-2">';
        foreach ($tags as $tag) {
            echo '<a href="' . esc_url(get_tag_link($tag->term_id)) . '" ';
            echo 'class="tag-item inline-block px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded transition-colors">';
            echo esc_html($tag->name);
            echo '</a>';
        }
        echo '</div>';

        echo $args['after_widget'];
    }

    /**
     * 后台表单
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Tags', 'folio');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('标题:', 'folio'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }

    /**
     * 更新设置
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        return $instance;
    }
}

/**
 * 最近热门文章小工具
 */
class folio_Popular_Posts_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'folio_popular_posts_widget',
            __('最近热门文章', 'folio'),
            array('description' => __('显示最近热门文章（按浏览量排序）', 'folio'))
        );
    }

    /**
     * 前端显示
     */
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Popular Posts', 'folio');
        $limit = !empty($instance['limit']) ? (int) $instance['limit'] : 5;
        $days = !empty($instance['days']) ? (int) $instance['days'] : 30;
        
        $query_args = array(
            'post_type' => 'post',
            'posts_per_page' => $limit,
            'meta_key' => 'views',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'date_query' => array(
                array(
                    'after' => $days . ' days ago',
                ),
            ),
            'ignore_sticky_posts' => true,
        );
        
        $popular_posts = new WP_Query($query_args);
        
        if (!$popular_posts->have_posts()) {
            return;
        }

        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        
        echo '<ul class="popular-posts-list space-y-4">';
        while ($popular_posts->have_posts()) {
            $popular_posts->the_post();
            ?>
            <li class="popular-post-item">
                <a href="<?php echo esc_url(get_permalink()); ?>" class="flex gap-3 group">
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="post-thumbnail flex-shrink-0 w-16 h-16 rounded overflow-hidden">
                            <?php 
                            $premium_info = folio_get_post_premium_info(get_the_ID());
                            $should_blur = $premium_info['is_premium'] && !$premium_info['can_access'];
                            $image_class = 'w-full h-full object-cover transition-transform duration-300 group-hover:scale-110';
                            if ($should_blur) {
                                $image_class .= ' folio-premium-blur';
                            }
                            echo get_the_post_thumbnail(get_the_ID(), 'thumbnail', array(
                                'class' => $image_class,
                            )); 
                            ?>
                        </div>
                    <?php else : ?>
                        <div class="post-thumbnail flex-shrink-0 w-16 h-16 rounded bg-gradient-to-br from-gray-200 to-gray-300"></div>
                    <?php endif; ?>
                    <div class="post-info flex-1 min-w-0">
                        <h4 class="post-title text-sm font-bold uppercase leading-tight mb-1 group-hover:text-gray-600 transition-colors line-clamp-2">
                            <?php echo esc_html(get_the_title()); ?>
                        </h4>
                        <div class="post-meta flex items-center gap-3 text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <?php echo esc_html(folio_get_views()); ?>
                            </span>
                            <span class="text-gray-400"><?php echo get_the_date('F j, Y'); ?></span>
                        </div>
                    </div>
                </a>
            </li>
            <?php
        }
        echo '</ul>';
        
        wp_reset_postdata();

        echo $args['after_widget'];
    }

    /**
     * 后台表单
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Popular Posts', 'folio');
        $limit = !empty($instance['limit']) ? (int) $instance['limit'] : 5;
        $days = !empty($instance['days']) ? (int) $instance['days'] : 30;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('标题:', 'folio'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>"><?php esc_html_e('显示数量:', 'folio'); ?></label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('limit')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('limit')); ?>" 
                   type="number" step="1" min="1" max="20" value="<?php echo esc_attr($limit); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('days')); ?>"><?php esc_html_e('最近天数:', 'folio'); ?></label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('days')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('days')); ?>" 
                   type="number" step="1" min="1" max="365" value="<?php echo esc_attr($days); ?>">
        </p>
        <?php
    }

    /**
     * 更新设置
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['limit'] = (!empty($new_instance['limit'])) ? (int) $new_instance['limit'] : 5;
        $instance['days'] = (!empty($new_instance['days'])) ? (int) $new_instance['days'] : 30;
        return $instance;
    }
}

/**
 * 注册小工具
 */
function folio_register_post_sidebar_widgets() {
    register_widget('folio_Post_Tags_Widget');
    register_widget('folio_Popular_Posts_Widget');
}
add_action('widgets_init', 'folio_register_post_sidebar_widgets');

