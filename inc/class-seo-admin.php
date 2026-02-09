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
            'SEO优化',
            'SEO优化',
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
                    
                    button.text("处理中...").prop("disabled", true);
                    
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
                    
                    button.text("测试中...").prop("disabled", true);
                    
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
            <h1>会员内容SEO优化</h1>
            
            <!-- SEO统计概览 -->
            <div class="folio-seo-stats">
                <div class="folio-seo-stat-card">
                    <div class="folio-seo-stat-number"><?php echo esc_html($seo_stats['protected_posts']); ?></div>
                    <div class="folio-seo-stat-label">受保护文章总数</div>
                </div>
                
                <div class="folio-seo-stat-card">
                    <div class="folio-seo-stat-number"><?php echo esc_html($seo_stats['seo_visible_posts']); ?></div>
                    <div class="folio-seo-stat-label">SEO可见文章</div>
                </div>
                
                <div class="folio-seo-stat-card">
                    <div class="folio-seo-stat-number"><?php echo esc_html($seo_stats['vip_posts']); ?></div>
                    <div class="folio-seo-stat-label">VIP文章</div>
                </div>
                
                <div class="folio-seo-stat-card">
                    <div class="folio-seo-stat-number"><?php echo esc_html($seo_stats['svip_posts']); ?></div>
                    <div class="folio-seo-stat-label">SVIP文章</div>
                </div>
            </div>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- SEO分布图表 -->
                <div class="folio-seo-chart-container">
                    <h3>内容分布</h3>
                    <canvas id="seoStatsChart" width="300" height="300"></canvas>
                </div>

                <!-- 站点地图状态 -->
                <div class="folio-seo-chart-container" style="flex: 1;">
                    <h3>站点地图状态</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>站点地图类型</th>
                                <th>状态</th>
                                <th>URL数量</th>
                                <th>最后更新</th>
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
                <h3>SEO优化工具</h3>
                
                <div class="folio-seo-tool-item">
                    <div class="folio-seo-tool-title">批量SEO设置更新</div>
                    <div class="folio-seo-tool-description">
                        为所有受保护文章批量更新SEO设置，确保搜索引擎友好性。
                    </div>
                    <button id="bulk-seo-update" class="button button-primary">执行批量更新</button>
                    <div id="bulk-update-result"></div>
                </div>

                <div class="folio-seo-tool-item">
                    <div class="folio-seo-tool-title">搜索引擎爬虫测试</div>
                    <div class="folio-seo-tool-description">
                        测试搜索引擎爬虫对会员内容的访问情况，验证SEO配置。
                    </div>
                    <button id="test-crawlers" class="button">测试爬虫访问</button>
                    <div id="crawler-test-result"></div>
                </div>

                <div class="folio-seo-tool-item">
                    <div class="folio-seo-tool-title">站点地图管理</div>
                    <div class="folio-seo-tool-description">
                        管理和优化XML站点地图，确保会员内容正确索引。
                    </div>
                    <a href="<?php echo esc_url(home_url('/wp-sitemap.xml')); ?>" class="button" target="_blank">查看站点地图</a>
                    <a href="<?php echo esc_url(home_url('/?sitemap=membership')); ?>" class="button" target="_blank">会员内容站点地图</a>
                </div>
            </div>

            <!-- 最近的SEO问题 -->
            <?php if (!empty($recent_issues)): ?>
            <div class="folio-seo-tools">
                <h3>需要注意的SEO问题</h3>
                <?php foreach ($recent_issues as $issue): ?>
                <div class="notice notice-<?php echo esc_attr($issue['type']); ?>">
                    <p><strong><?php echo esc_html($issue['title']); ?></strong></p>
                    <p><?php echo esc_html($issue['description']); ?></p>
                    <?php if (!empty($issue['action_url'])): ?>
                    <p><a href="<?php echo esc_url($issue['action_url']); ?>" class="button">解决问题</a></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- SEO最佳实践建议 -->
            <div class="folio-seo-tools">
                <h3>SEO最佳实践建议</h3>
                <ul>
                    <li><strong>内容预览优化：</strong>为受保护文章设置合适的预览内容，让搜索引擎了解文章价值。</li>
                    <li><strong>元数据完善：</strong>确保所有文章都有适当的标题、描述和关键词。</li>
                    <li><strong>结构化数据：</strong>使用Schema.org标记帮助搜索引擎理解付费内容结构。</li>
                    <li><strong>站点地图优化：</strong>定期更新XML站点地图，确保新内容及时被索引。</li>
                    <li><strong>爬虫友好：</strong>为搜索引擎爬虫提供适当的内容预览，避免完全阻止访问。</li>
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
            wp_die('安全验证失败');
        }

        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
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
                error_log('SEO批量更新错误: ' . $e->getMessage());
            }
        }

        wp_send_json_success(array(
            'message' => sprintf('批量更新完成！成功更新 %d 篇文章，%d 个错误。', $updated_count, $error_count)
        ));
    }

    /**
     * 测试爬虫访问
     */
    public function test_crawler_access() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'folio_seo_test_crawlers')) {
            wp_die('安全验证失败');
        }

        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
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
                'message' => '没有找到受保护的文章进行测试。'
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
                $test_results[] = $name . ': 访问失败 - ' . $response->get_error_message();
            } else {
                $status_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                
                $has_preview = strpos($body, 'folio-content-preview') !== false;
                $has_paywall = strpos($body, 'folio-permission-prompt') !== false;
                
                $test_results[] = sprintf(
                    '%s: HTTP %d, 预览内容: %s, 付费提示: %s',
                    $name,
                    $status_code,
                    $has_preview ? '是' : '否',
                    $has_paywall ? '是' : '否'
                );
            }
        }

        wp_send_json_success(array(
            'message' => '爬虫测试完成：<br>' . implode('<br>', $test_results)
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
            $description .= ' [' . $level_name . '专属内容]';
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
            'labels' => array('普通文章', 'VIP文章', 'SVIP文章'),
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
                'name' => '主站点地图',
                'status' => 'active',
                'status_text' => '正常',
                'url_count' => wp_count_posts()->publish,
                'last_updated' => date('Y-m-d H:i:s')
            ),
            array(
                'name' => '会员内容站点地图',
                'status' => 'active',
                'status_text' => '正常',
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
                'title' => 'SEO可见性问题',
                'description' => sprintf('有 %d 篇受保护文章未设置为SEO可见，可能影响搜索引擎索引。', $invisible_count),
                'action_url' => admin_url('edit.php?post_type=post')
            );
        }

        // 检查是否启用了站点地图
        if (!get_option('blog_public')) {
            $issues[] = array(
                'type' => 'error',
                'title' => '搜索引擎可见性',
                'description' => '网站设置为不被搜索引擎索引，这将影响SEO效果。',
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
