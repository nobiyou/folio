<?php
/**
 * SEO Admin Interface
 * 
 * SEO管理界面 - 为会员内容SEO优化提供管理和监控功能
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

class folio_SEO_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_folio_seo_bulk_update', array($this, 'handle_bulk_seo_update'));
        add_action('wp_ajax_folio_seo_test_crawlers', array($this, 'test_crawler_access'));
        add_action('wp_ajax_folio_seo_generate_sitemap', array($this, 'generate_custom_sitemap'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'folio-membership',
            __('SEO Optimization', 'folio'),
            __('SEO Optimization', 'folio'),
            'manage_options',
            'folio-seo',
            array($this, 'render_admin_page')
        );
    }

    /**
     * 加载管理脚本
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'folio-seo') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                // SEO统计图表
                if ($("#seoStatsChart").length) {
                    const ctx = document.getElementById("seoStatsChart").getContext("2d");
                    const seoData = ' . json_encode($this->get_seo_chart_data()) . ';
                    
                    new Chart(ctx, {
                        type: "doughnut",
                        data: seoData,
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: "bottom"
                                }
                            }
                        }
                    });
                }

                // 批量SEO更新
                $("#bulk-seo-update").on("click", function(e) {
                    e.preventDefault();
                    
                    const button = $(this);
                    const originalText = button.text();
                    
                    button.text("' . esc_js(__('Processing...', 'folio')) . '").prop("disabled", true);
                    
                    $.post(ajaxurl, {
                        action: "folio_seo_bulk_update",
                        nonce: "' . wp_create_nonce('folio_seo_bulk_update') . '"
                    }, function(response) {
                        if (response.success) {
                            $("#bulk-update-result").html("<div class=\"notice notice-success\"><p>" + response.data.message + "</p></div>");
                            location.reload();
                        } else {
                            $("#bulk-update-result").html("<div class=\"notice notice-error\"><p>" + response.data.message + "</p></div>");
                        }
                    }).always(function() {
                        button.text(originalText).prop("disabled", false);
                    });
                });

                // 测试爬虫访问
                $("#test-crawlers").on("click", function(e) {
                    e.preventDefault();
                    
                    const button = $(this);
                    const originalText = button.text();
                    
                    button.text("' . esc_js(__('Testing...', 'folio')) . '").prop("disabled", true);
                    
                    $.post(ajaxurl, {
                        action: "folio_seo_test_crawlers",
                        nonce: "' . wp_create_nonce('folio_seo_test_crawlers') . '"
                    }, function(response) {
                        if (response.success) {
                            $("#crawler-test-result").html("<div class=\"notice notice-success\"><p>" + response.data.message + "</p></div>");
                        } else {
                            $("#crawler-test-result").html("<div class=\"notice notice-error\"><p>" + response.data.message + "</p></div>");
                        }
                    }).always(function() {
                        button.text(originalText).prop("disabled", false);
                    });
                });
            });
        ');

        wp_add_inline_style('wp-admin', '
            .folio-seo-admin {
                max-width: 1200px;
            }
            
            .folio-seo-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            
            .folio-seo-stat-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                text-align: center;
            }
            
            .folio-seo-stat-number {
                font-size: 2.5em;
                font-weight: bold;
                color: #0073aa;
                margin: 10px 0;
            }
            
            .folio-seo-stat-label {
                color: #666;
                font-size: 0.9em;
            }
            
            .folio-seo-chart-container {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
                max-width: 400px;
            }
            
            .folio-seo-tools {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
            }
            
            .folio-seo-tool-item {
                margin: 15px 0;
                padding: 15px;
                border: 1px solid #e1e1e1;
                border-radius: 4px;
            }
            
            .folio-seo-tool-title {
                font-weight: bold;
                margin-bottom: 10px;
            }
            
            .folio-seo-tool-description {
                color: #666;
                margin-bottom: 15px;
            }
        ');
    }

    /**
     * 渲染管理页面
     */
    public function render_admin_page() {
        $seo_stats = folio_get_membership_seo_stats();
        $sitemap_status = $this->get_sitemap_status();
        $recent_issues = $this->get_recent_seo_issues();
        
        ?>
        <div class="wrap folio-seo-admin">
            <h1><?php esc_html_e('Membership Content SEO Optimization', 'folio'); ?></h1>
            
            <!-- SEO统计概览 -->
            <div class="folio-seo-stats">
                <div class="folio-seo-stat-card">
                    <div class="folio-seo-stat-number"><?php echo esc_html($seo_stats['protected_posts']); ?></div>
                    <div class="folio-seo-stat-label"><?php esc_html_e('Total Protected Posts', 'folio'); ?></div>
                </div>
                
                <div class="folio-seo-stat-card">
                    <div class="folio-seo-stat-number"><?php echo esc_html($seo_stats['seo_visible_posts']); ?></div>
                    <div class="folio-seo-stat-label"><?php esc_html_e('SEO Visible Posts', 'folio'); ?></div>
                </div>
                
                <div class="folio-seo-stat-card">
                    <div class="folio-seo-stat-number"><?php echo esc_html($seo_stats['vip_posts']); ?></div>
                    <div class="folio-seo-stat-label"><?php esc_html_e('VIP Posts', 'folio'); ?></div>
                </div>
                
                <div class="folio-seo-stat-card">
                    <div class="folio-seo-stat-number"><?php echo esc_html($seo_stats['svip_posts']); ?></div>
                    <div class="folio-seo-stat-label"><?php esc_html_e('SVIP Posts', 'folio'); ?></div>
                </div>
            </div>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- SEO分布图表 -->
                <div class="folio-seo-chart-container">
                    <h3><?php esc_html_e('Content Distribution', 'folio'); ?></h3>
                    <canvas id="seoStatsChart" width="300" height="300"></canvas>
                </div>

                <!-- 站点地图状态 -->
                <div class="folio-seo-chart-container" style="flex: 1;">
                    <h3><?php esc_html_e('Sitemap Status', 'folio'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Sitemap Type', 'folio'); ?></th>
                                <th><?php esc_html_e('Status', 'folio'); ?></th>
                                <th><?php esc_html_e('URL Count', 'folio'); ?></th>
                                <th><?php esc_html_e('Last Updated', 'folio'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sitemap_status as $sitemap): ?>
                            <tr>
                                <td><?php echo esc_html($sitemap['name']); ?></td>
                                <td>
                                    <span class="dashicons dashicons-<?php echo $sitemap['status'] === 'active' ? 'yes-alt' : 'warning'; ?>"></span>
                                    <?php echo esc_html($sitemap['status_text']); ?>
                                </td>
                                <td><?php echo esc_html($sitemap['url_count']); ?></td>
                                <td><?php echo esc_html($sitemap['last_updated']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SEO工具 -->
            <div class="folio-seo-tools">
                <h3><?php esc_html_e('SEO Optimization Tools', 'folio'); ?></h3>
                
                <div class="folio-seo-tool-item">
                    <div class="folio-seo-tool-title"><?php esc_html_e('Bulk SEO Settings Update', 'folio'); ?></div>
                    <div class="folio-seo-tool-description">
                        <?php esc_html_e('Bulk update SEO settings for all protected posts to improve search engine friendliness.', 'folio'); ?>
                    </div>
                    <button id="bulk-seo-update" class="button button-primary"><?php esc_html_e('Run Bulk Update', 'folio'); ?></button>
                    <div id="bulk-update-result"></div>
                </div>

                <div class="folio-seo-tool-item">
                    <div class="folio-seo-tool-title"><?php esc_html_e('Search Crawler Test', 'folio'); ?></div>
                    <div class="folio-seo-tool-description">
                        <?php esc_html_e('Test crawler access to membership content and verify SEO configuration.', 'folio'); ?>
                    </div>
                    <button id="test-crawlers" class="button"><?php esc_html_e('Test Crawler Access', 'folio'); ?></button>
                    <div id="crawler-test-result"></div>
                </div>

                <div class="folio-seo-tool-item">
                    <div class="folio-seo-tool-title"><?php esc_html_e('Sitemap Management', 'folio'); ?></div>
                    <div class="folio-seo-tool-description">
                        <?php esc_html_e('Manage and optimize XML sitemaps to ensure membership content is indexed correctly.', 'folio'); ?>
                    </div>
                    <a href="<?php echo esc_url(home_url('/wp-sitemap.xml')); ?>" class="button" target="_blank"><?php esc_html_e('View Sitemap', 'folio'); ?></a>
                    <a href="<?php echo esc_url(home_url('/?sitemap=membership')); ?>" class="button" target="_blank"><?php esc_html_e('Membership Sitemap', 'folio'); ?></a>
                </div>
            </div>

            <!-- 最近的SEO问题 -->
            <?php if (!empty($recent_issues)): ?>
            <div class="folio-seo-tools">
                <h3><?php esc_html_e('SEO Issues Requiring Attention', 'folio'); ?></h3>
                <?php foreach ($recent_issues as $issue): ?>
                <div class="notice notice-<?php echo esc_attr($issue['type']); ?>">
                    <p><strong><?php echo esc_html($issue['title']); ?></strong></p>
                    <p><?php echo esc_html($issue['description']); ?></p>
                    <?php if (!empty($issue['action_url'])): ?>
                    <p><a href="<?php echo esc_url($issue['action_url']); ?>" class="button"><?php esc_html_e('Resolve Issue', 'folio'); ?></a></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- SEO最佳实践建议 -->
            <div class="folio-seo-tools">
                <h3><?php esc_html_e('SEO Best Practice Suggestions', 'folio'); ?></h3>
                <ul>
                    <li><strong><?php esc_html_e('Preview Optimization:', 'folio'); ?></strong> <?php esc_html_e('Set proper previews for protected posts so search engines can understand content value.', 'folio'); ?></li>
                    <li><strong><?php esc_html_e('Metadata Completion:', 'folio'); ?></strong> <?php esc_html_e('Ensure every post has proper title, description, and keywords.', 'folio'); ?></li>
                    <li><strong><?php esc_html_e('Structured Data:', 'folio'); ?></strong> <?php esc_html_e('Use Schema.org markup to help search engines understand paid content structure.', 'folio'); ?></li>
                    <li><strong><?php esc_html_e('Sitemap Optimization:', 'folio'); ?></strong> <?php esc_html_e('Update XML sitemaps regularly so new content gets indexed in time.', 'folio'); ?></li>
                    <li><strong><?php esc_html_e('Crawler Friendly:', 'folio'); ?></strong> <?php esc_html_e('Provide proper previews for crawlers instead of completely blocking access.', 'folio'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * 处理批量SEO更新
     */
    public function handle_bulk_seo_update() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_seo_bulk_update')) {
            wp_die(esc_html__('Security verification failed', 'folio'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'folio'));
        }

        $updated_count = 0;
        $error_count = 0;

        // 获取所有受保护的文章
        $protected_posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => '_folio_premium_content',
                    'value' => '1',
                    'compare' => '='
                )
            )
        ));

        foreach ($protected_posts as $post) {
            try {
                // 确保SEO可见性设置存在
                $seo_visible = get_post_meta($post->ID, '_folio_seo_visible', true);
                if ($seo_visible === '') {
                    update_post_meta($post->ID, '_folio_seo_visible', '1');
                }

                // 生成或更新SEO描述
                $this->update_post_seo_description($post->ID);
                
                $updated_count++;
            } catch (Exception $e) {
                $error_count++;
                error_log('SEO bulk update error: ' . $e->getMessage());
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: 1: updated posts count, 2: error count. */
                __('Bulk update completed. %1$d posts updated, %2$d errors.', 'folio'),
                $updated_count,
                $error_count
            )
        ));
    }

    /**
     * 测试爬虫访问
     */
    public function test_crawler_access() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_seo_test_crawlers')) {
            wp_die(esc_html__('Security verification failed', 'folio'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'folio'));
        }

        $test_results = array();

        // 获取一篇受保护的文章进行测试
        $test_post = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => 1,
            'meta_query' => array(
                array(
                    'key' => '_folio_premium_content',
                    'value' => '1',
                    'compare' => '='
                )
            )
        ));

        if (empty($test_post)) {
            wp_send_json_error(array(
                'message' => __('No protected posts found for testing.', 'folio')
            ));
        }

        $post = $test_post[0];
        $post_url = get_permalink($post->ID);

        // 模拟不同爬虫的访问
        $crawlers = array(
            'Googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Bingbot' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
            'Baiduspider' => 'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)'
        );

        foreach ($crawlers as $name => $user_agent) {
            $response = wp_remote_get($post_url, array(
                'headers' => array(
                    'User-Agent' => $user_agent
                ),
                'timeout' => 10
            ));

            if (is_wp_error($response)) {
                $test_results[] = sprintf(
                    /* translators: 1: crawler name, 2: error message. */
                    __('%1$s: Access failed - %2$s', 'folio'),
                    $name,
                    $response->get_error_message()
                );
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                
                $has_preview = strpos($body, 'folio-content-preview') !== false;
                $has_paywall = strpos($body, 'folio-permission-prompt') !== false;
                
                $test_results[] = sprintf(
                    /* translators: 1: crawler name, 2: HTTP status code, 3: preview available text, 4: paywall prompt available text. */
                    __('%1$s: HTTP %2$d, Preview: %3$s, Paywall Prompt: %4$s', 'folio'),
                    $name,
                    $status_code,
                    $has_preview ? __('Yes', 'folio') : __('No', 'folio'),
                    $has_paywall ? __('Yes', 'folio') : __('No', 'folio')
                );
            }
        }

        wp_send_json_success(array(
            'message' => __('Crawler test completed:', 'folio') . '<br>' . implode('<br>', $test_results)
        ));
    }

    /**
     * 更新文章SEO描述
     */
    private function update_post_seo_description($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        $protection_info = folio_get_article_protection_info($post_id);
        
        // 生成SEO友好的描述
        $description = '';
        
        if (has_excerpt($post_id)) {
            $description = get_the_excerpt($post);
        } else {
            $content = wp_strip_all_tags($post->post_content);
            $description = wp_trim_words($content, 30, '...');
        }

        // 如果是受保护内容，添加会员标识
        if ($protection_info['is_protected']) {
            $level_name = $protection_info['required_level'] === 'svip' ? 'SVIP' : 'VIP';
            $description .= ' [' . sprintf(
                /* translators: %s: membership level. */
                __('%s exclusive content', 'folio'),
                $level_name
            ) . ']';
        }

        // 更新SEO描述元数据
        update_post_meta($post_id, '_folio_seo_description', $description);
        
        return true;
    }

    /**
     * 获取SEO图表数据
     */
    private function get_seo_chart_data() {
        $stats = folio_get_membership_seo_stats();
        
        return array(
            'labels' => array(
                __('Regular Posts', 'folio'),
                __('VIP Posts', 'folio'),
                __('SVIP Posts', 'folio'),
            ),
            'datasets' => array(
                array(
                    'data' => array(
                        wp_count_posts()->publish - $stats['protected_posts'],
                        $stats['vip_posts'],
                        $stats['svip_posts']
                    ),
                    'backgroundColor' => array('#36A2EB', '#FFCE56', '#FF6384'),
                    'borderWidth' => 2
                )
            )
        );
    }

    /**
     * 获取站点地图状态
     */
    private function get_sitemap_status() {
        return array(
            array(
                'name' => __('Main Sitemap', 'folio'),
                'status' => 'active',
                'status_text' => __('Normal', 'folio'),
                'url_count' => wp_count_posts()->publish,
                'last_updated' => date('Y-m-d H:i:s')
            ),
            array(
                'name' => __('Membership Content Sitemap', 'folio'),
                'status' => 'active',
                'status_text' => __('Normal', 'folio'),
                'url_count' => folio_get_membership_seo_stats()['seo_visible_posts'],
                'last_updated' => date('Y-m-d H:i:s')
            )
        );
    }

    /**
     * 获取最近的SEO问题
     */
    private function get_recent_seo_issues() {
        $issues = array();
        $stats = folio_get_membership_seo_stats();

        // 检查是否有受保护文章没有设置SEO可见性
        $invisible_count = $stats['protected_posts'] - $stats['seo_visible_posts'];
        if ($invisible_count > 0) {
            $issues[] = array(
                'type' => 'warning',
                'title' => __('SEO Visibility Issue', 'folio'),
                'description' => sprintf(
                    /* translators: %d: number of protected posts missing SEO visibility. */
                    __('%d protected posts are not marked as SEO-visible, which may affect indexing.', 'folio'),
                    $invisible_count
                ),
                'action_url' => admin_url('edit.php?post_type=post')
            );
        }

        // 检查是否启用了站点地图
        if (!get_option('blog_public')) {
            $issues[] = array(
                'type' => 'error',
                'title' => __('Search Engine Visibility', 'folio'),
                'description' => __('The site is currently set to discourage search engine indexing, which will impact SEO.', 'folio'),
                'action_url' => admin_url('options-reading.php')
            );
        }

        return $issues;
    }
}

// 初始化SEO管理界面
if (is_admin()) {
    new folio_SEO_Admin();
}
