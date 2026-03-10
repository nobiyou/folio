<div class="folio-tab-content">
    <div class="folio-section">
        <h2><?php esc_html_e('Frontend Resource Optimization', 'folio'); ?></h2>
        <p class="description"><?php esc_html_e('Optimize CSS, JavaScript, and image assets to improve loading speed.', 'folio'); ?></p>

        <?php if (!empty($stats)) : ?>
        <div class="folio-optimization-stats folio-stats-panel">
            <h3><?php esc_html_e('Optimization Stats', 'folio'); ?></h3>
            <div class="folio-grid-auto">
                <div>
                    <h4><?php esc_html_e('CSS Files', 'folio'); ?></h4>
                    <p><?php esc_html_e('Original', 'folio'); ?>: <?php echo esc_html($stats['css']['original']); ?> <?php esc_html_e('items', 'folio'); ?></p>
                    <p><?php esc_html_e('Optimized', 'folio'); ?>: <?php echo esc_html($stats['css']['optimized']); ?> <?php esc_html_e('items', 'folio'); ?></p>
                    <p><?php esc_html_e('Savings', 'folio'); ?>: <?php echo esc_html($stats['css']['savings']); ?>%</p>
                </div>
                <div>
                    <h4><?php esc_html_e('JavaScript Files', 'folio'); ?></h4>
                    <p><?php esc_html_e('Original', 'folio'); ?>: <?php echo esc_html($stats['js']['original']); ?> <?php esc_html_e('items', 'folio'); ?></p>
                    <p><?php esc_html_e('Optimized', 'folio'); ?>: <?php echo esc_html($stats['js']['optimized']); ?> <?php esc_html_e('items', 'folio'); ?></p>
                    <p><?php esc_html_e('Savings', 'folio'); ?>: <?php echo esc_html($stats['js']['savings']); ?>%</p>
                </div>
                <div>
                    <h4><?php esc_html_e('Image Optimization', 'folio'); ?></h4>
                    <p><?php esc_html_e('Lazy Loaded', 'folio'); ?>: <?php echo esc_html($stats['images']['lazy_loaded']); ?> <?php esc_html_e('images', 'folio'); ?></p>
                    <p><?php esc_html_e('WebP Support', 'folio'); ?>: <?php echo $stats['images']['webp_support'] ? esc_html__('Yes', 'folio') : esc_html__('No', 'folio'); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable Frontend Optimization', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[frontend_optimization][enabled]" value="1" <?php checked(isset($frontend_opt['enabled']) ? $frontend_opt['enabled'] : true, true); ?>>
                        <?php esc_html_e('Enable all frontend optimization features', 'folio'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('CSS Optimization', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[frontend_optimization][minify_css]" value="1" <?php checked(isset($frontend_opt['minify_css']) ? $frontend_opt['minify_css'] : true, true); ?>>
                        <?php esc_html_e('Minify CSS files', 'folio'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[frontend_optimization][combine_css]" value="1" <?php checked(isset($frontend_opt['combine_css']) ? $frontend_opt['combine_css'] : false, true); ?>>
                        <?php esc_html_e('Combine CSS files', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Note: Combining CSS may cause FOUC (flash of unstyled content). Use with caution.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('JavaScript Optimization', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[frontend_optimization][minify_js]" value="1" <?php checked(isset($frontend_opt['minify_js']) ? $frontend_opt['minify_js'] : true, true); ?>>
                        <?php esc_html_e('Minify JavaScript files', 'folio'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[frontend_optimization][combine_js]" value="1" <?php checked(isset($frontend_opt['combine_js']) ? $frontend_opt['combine_js'] : false, true); ?>>
                        <?php esc_html_e('Combine JavaScript files', 'folio'); ?> <span class="folio-text-danger"><?php esc_html_e('(Experimental)', 'folio'); ?></span>
                    </label>
                    <p class="description"><?php esc_html_e('Note: Combining JS may cause compatibility issues. Use with caution.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Image Optimization', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[frontend_optimization][lazy_load_images]" value="1" <?php checked(isset($frontend_opt['lazy_load_images']) ? $frontend_opt['lazy_load_images'] : true, true); ?>>
                        <?php esc_html_e('Enable image lazy loading', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Load images only when they enter the viewport to improve page speed.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Font Optimization', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[frontend_optimization][optimize_fonts]" value="1" <?php checked(isset($frontend_opt['optimize_fonts']) ? $frontend_opt['optimize_fonts'] : true, true); ?>>
                        <?php esc_html_e('Optimize font loading', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Optimize font loading strategy to reduce render-blocking.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Resource Loading', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[frontend_optimization][preload_critical]" value="1" <?php checked(isset($frontend_opt['preload_critical']) ? $frontend_opt['preload_critical'] : true, true); ?>>
                        <?php esc_html_e('Preload critical resources', 'folio'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[frontend_optimization][defer_non_critical]" value="1" <?php checked(isset($frontend_opt['defer_non_critical']) ? $frontend_opt['defer_non_critical'] : false, true); ?>>
                        <?php esc_html_e('Defer non-critical resources', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Note: Deferring resources may cause FOUC. Use with caution.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Caching and Compression', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[frontend_optimization][cache_busting]" value="1" <?php checked(isset($frontend_opt['cache_busting']) ? $frontend_opt['cache_busting'] : true, true); ?>>
                        <?php esc_html_e('Enable cache busting', 'folio'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[frontend_optimization][enable_gzip]" value="1" <?php checked(isset($frontend_opt['enable_gzip']) ? $frontend_opt['enable_gzip'] : true, true); ?>>
                        <?php esc_html_e('Enable Gzip compression', 'folio'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('HTML Minification', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[frontend_optimization][minify_html]" value="1" <?php checked(isset($frontend_opt['minify_html']) ? $frontend_opt['minify_html'] : false, true); ?>>
                        <?php esc_html_e('Minify HTML output', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Remove whitespace in HTML output to reduce page size.', 'folio'); ?></p>
                </td>
            </tr>
        </table>

        <div class="folio-optimization-actions folio-actions-block">
            <h3><?php esc_html_e('Optimization Actions', 'folio'); ?></h3>
            <p>
                <button type="button" class="button button-primary" id="folio-optimize-assets">
                    <span class="dashicons dashicons-performance folio-button-icon"></span>
                    <?php esc_html_e('Re-optimize Assets', 'folio'); ?>
                </button>
                <button type="button" class="button" id="folio-clear-optimized-cache">
                    <span class="dashicons dashicons-trash folio-button-icon"></span>
                    <?php esc_html_e('Clear Optimized Cache', 'folio'); ?>
                </button>
            </p>
            <div id="folio-optimization-result" class="folio-result-margin-top"></div>
        </div>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Basic Performance Settings', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Disable Emoji', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[disable_emoji]" value="1" <?php checked(isset($options['disable_emoji']) ? $options['disable_emoji'] : 0, 1); ?>>
                        <?php esc_html_e('Disable default WordPress Emoji scripts', 'folio'); ?>
                    </label>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Cache Settings', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Browser Cache Duration', 'folio'); ?></th>
                <td>
                    <select name="<?php echo esc_attr($option_name); ?>[cache_time]">
                        <option value="3600" <?php selected(isset($options['cache_time']) ? $options['cache_time'] : '86400', '3600'); ?>><?php esc_html_e('1 Hour', 'folio'); ?></option>
                        <option value="86400" <?php selected(isset($options['cache_time']) ? $options['cache_time'] : '86400', '86400'); ?>><?php esc_html_e('1 Day', 'folio'); ?></option>
                        <option value="604800" <?php selected(isset($options['cache_time']) ? $options['cache_time'] : '86400', '604800'); ?>><?php esc_html_e('7 Days', 'folio'); ?></option>
                        <option value="2592000" <?php selected(isset($options['cache_time']) ? $options['cache_time'] : '86400', '2592000'); ?>><?php esc_html_e('30 Days', 'folio'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Set browser cache duration for static assets.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>
</div>
