<div class="folio-tab-content">
    <div class="folio-section">
        <h2><?php esc_html_e('URL Optimization', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Remove Category Prefix', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[remove_category_prefix]" value="1" <?php checked(isset($options['remove_category_prefix']) ? $options['remove_category_prefix'] : 1, 1); ?>>
                        <?php esc_html_e('Remove /category/ prefix from category URLs', 'folio'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Example: /category/design/ -> /design/', 'folio'); ?><br>
                        <strong class="folio-text-danger"><?php esc_html_e('Important:', 'folio'); ?></strong>
                        <?php esc_html_e('After enabling, complete the following steps:', 'folio'); ?><br>
                        1. <?php esc_html_e('Save this setting', 'folio'); ?><br>
                        2. <?php esc_html_e('Go to "Settings > Permalinks"', 'folio'); ?><br>
                        3. <?php esc_html_e('Click "Save Changes"', 'folio'); ?><br>
                        4. <?php esc_html_e('Or click the button below to refresh rewrite rules', 'folio'); ?>
                    </p>
                    <button type="button" class="button" id="flush-rewrite-rules">
                        <span class="dashicons dashicons-update folio-button-icon"></span>
                        <?php esc_html_e('Refresh Rewrite Rules', 'folio'); ?>
                    </button>
                    <span id="flush-result" class="folio-inline-result"></span>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Content Optimization', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Auto Featured Image', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[auto_featured_image]" value="1" <?php checked(isset($options['auto_featured_image']) ? $options['auto_featured_image'] : 1, 1); ?>>
                        <?php esc_html_e('Automatically set the first image in post as featured image', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('When saving a post without featured image, use the first image in content automatically.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Search Optimization', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[search_title_only]" value="1" <?php checked(isset($options['search_title_only']) ? $options['search_title_only'] : 1, 1); ?>>
                        <?php esc_html_e('Search by post title only (improves search speed)', 'folio'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Clean Image HTML', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[clean_image_html]" value="1" <?php checked(isset($options['clean_image_html']) ? $options['clean_image_html'] : 1, 1); ?>>
                        <?php esc_html_e('Remove redundant HTML attributes from inserted images', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Remove width, height, class, srcset, etc. Keep only src and alt.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Editor Settings', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Post Editor', 'folio'); ?></th>
                <td>
                    <select name="<?php echo esc_attr($option_name); ?>[editor_type]">
                        <option value="gutenberg" <?php selected(isset($options['editor_type']) ? $options['editor_type'] : 'gutenberg', 'gutenberg'); ?>>
                            <?php esc_html_e('Gutenberg Editor (Block Editor)', 'folio'); ?>
                        </option>
                        <option value="classic" <?php selected(isset($options['editor_type']) ? $options['editor_type'] : 'gutenberg', 'classic'); ?>>
                            <?php esc_html_e('Classic Editor (TinyMCE)', 'folio'); ?>
                        </option>
                    </select>
                    <p class="description"><?php esc_html_e('Choose which editor is used for post editing.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Widget Editor', 'folio'); ?></th>
                <td>
                    <select name="<?php echo esc_attr($option_name); ?>[widget_editor_type]">
                        <option value="gutenberg" <?php selected(isset($options['widget_editor_type']) ? $options['widget_editor_type'] : 'gutenberg', 'gutenberg'); ?>>
                            <?php esc_html_e('Gutenberg Widgets (Block Editor)', 'folio'); ?>
                        </option>
                        <option value="classic" <?php selected(isset($options['widget_editor_type']) ? $options['widget_editor_type'] : 'gutenberg', 'classic'); ?>>
                            <?php esc_html_e('Classic Widgets', 'folio'); ?>
                        </option>
                    </select>
                    <p class="description"><?php esc_html_e('Choose which editor is used for widget management.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Admin Optimization', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Remove Top Logo', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[remove_admin_bar_logo]" value="1" <?php checked(isset($options['remove_admin_bar_logo']) ? $options['remove_admin_bar_logo'] : 1, 1); ?>>
                        <?php esc_html_e('Remove WordPress logo from admin bar', 'folio'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Frontend Admin Bar', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[hide_admin_bar_frontend]" value="1" <?php checked(isset($options['hide_admin_bar_frontend']) ? $options['hide_admin_bar_frontend'] : 0, 1); ?>>
                        <?php esc_html_e('Hide frontend admin bar', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Do not show top admin bar on frontend even when logged in.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Database Optimization', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Post Revisions', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[disable_post_revisions]" value="1" <?php checked(isset($options['disable_post_revisions']) ? $options['disable_post_revisions'] : 0, 1); ?>>
                        <?php esc_html_e('Disable post revisions completely', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Reduce database load, but you cannot restore old revisions.', 'folio'); ?></p>

                    <div class="folio-mt-10">
                        <label><?php esc_html_e('Or limit revisions count:', 'folio'); ?></label>
                        <input type="number" name="<?php echo esc_attr($option_name); ?>[post_revisions_limit]" value="<?php echo esc_attr(isset($options['post_revisions_limit']) ? $options['post_revisions_limit'] : 0); ?>" min="0" max="50" class="small-text">
                        <span class="description"><?php esc_html_e('items (0 = unlimited)', 'folio'); ?></span>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Autosave', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[disable_autosave]" value="1" <?php checked(isset($options['disable_autosave']) ? $options['disable_autosave'] : 0, 1); ?>>
                        <?php esc_html_e('Disable editor autosave', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Reduce database writes, but unsaved content may be lost.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Script Optimization', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('jQuery Migrate', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[remove_jquery_migrate]" value="1" <?php checked(isset($options['remove_jquery_migrate']) ? $options['remove_jquery_migrate'] : 1, 1); ?>>
                        <?php esc_html_e('Remove jQuery Migrate script', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Reduce JS loading, but old plugin compatibility may be affected.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Dashicons', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[disable_dashicons_frontend]" value="1" <?php checked(isset($options['disable_dashicons_frontend']) ? $options['disable_dashicons_frontend'] : 1, 1); ?>>
                        <?php esc_html_e('Disable Dashicons on frontend (for logged-out users)', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Reduce CSS loading when frontend does not use Dashicons.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Performance Monitoring', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[enable_performance_monitor]" value="1" <?php checked(isset($options['enable_performance_monitor']) ? $options['enable_performance_monitor'] : 0, 1); ?>>
                        <?php esc_html_e('Enable frontend performance monitoring', 'folio'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Monitor frontend performance metrics (load time, memory usage, DB queries) and provide optimization suggestions. Visible to admins on frontend only.', 'folio'); ?><br>
                        <strong><?php esc_html_e('Shortcuts:', 'folio'); ?></strong>
                        <code>Ctrl+Shift+P</code> <?php esc_html_e('Show/Hide toolbar', 'folio'); ?>,
                        <code>Ctrl+Double Click</code> <?php esc_html_e('Show again', 'folio'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Heartbeat API', 'folio'); ?></th>
                <td>
                    <select name="<?php echo esc_attr($option_name); ?>[heartbeat_mode]">
                        <option value="default" <?php selected(isset($options['heartbeat_mode']) ? $options['heartbeat_mode'] : 'default', 'default'); ?>>
                            <?php esc_html_e('Default (15s)', 'folio'); ?>
                        </option>
                        <option value="reduce" <?php selected(isset($options['heartbeat_mode']) ? $options['heartbeat_mode'] : 'default', 'reduce'); ?>>
                            <?php esc_html_e('Reduced frequency (60s)', 'folio'); ?>
                        </option>
                        <option value="disable" <?php selected(isset($options['heartbeat_mode']) ? $options['heartbeat_mode'] : 'default', 'disable'); ?>>
                            <?php esc_html_e('Disable completely', 'folio'); ?>
                        </option>
                    </select>
                    <p class="description"><?php esc_html_e('Heartbeat is used for autosave and real-time notifications. Disabling it reduces server load.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Google Fonts', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[disable_google_fonts]" value="1" <?php checked(isset($options['disable_google_fonts']) ? $options['disable_google_fonts'] : 0, 1); ?>>
                        <?php esc_html_e('Disable Google Fonts loading', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Improve regional access speed, but may affect font rendering.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Version Strings', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[remove_version_strings]" value="1" <?php checked(isset($options['remove_version_strings']) ? $options['remove_version_strings'] : 1, 1); ?>>
                        <?php esc_html_e('Remove version query strings from CSS/JS files', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Hide version info for better security.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Image Optimization', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Auto Compression', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[enable_image_compression]" value="1" <?php checked(isset($options['enable_image_compression']) ? $options['enable_image_compression'] : 1, 1); ?>>
                        <?php esc_html_e('Enable automatic image compression', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Automatically compress images on upload to reduce file size.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('JPEG Quality', 'folio'); ?></th>
                <td>
                    <input
                        type="range"
                        name="<?php echo esc_attr($option_name); ?>[jpeg_quality]"
                        value="<?php echo esc_attr(isset($options['jpeg_quality']) ? $options['jpeg_quality'] : 85); ?>"
                        min="60"
                        max="100"
                        step="5"
                        class="folio-range-input"
                        data-output-selector="#folio-jpeg-quality-value">
                    <span id="folio-jpeg-quality-value"><?php echo esc_html(isset($options['jpeg_quality']) ? $options['jpeg_quality'] : 85); ?>%</span>
                    <p class="description"><?php esc_html_e('JPEG compression quality (60-100%, recommended 85%).', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('PNG Quality', 'folio'); ?></th>
                <td>
                    <input
                        type="range"
                        name="<?php echo esc_attr($option_name); ?>[png_quality]"
                        value="<?php echo esc_attr(isset($options['png_quality']) ? $options['png_quality'] : 90); ?>"
                        min="70"
                        max="100"
                        step="5"
                        class="folio-range-input"
                        data-output-selector="#folio-png-quality-value">
                    <span id="folio-png-quality-value"><?php echo esc_html(isset($options['png_quality']) ? $options['png_quality'] : 90); ?>%</span>
                    <p class="description"><?php esc_html_e('PNG compression quality (70-100%, recommended 90%).', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Maximum Size', 'folio'); ?></th>
                <td>
                    <label>
                        <?php esc_html_e('Width:', 'folio'); ?>
                        <input type="number" name="<?php echo esc_attr($option_name); ?>[max_image_width]" value="<?php echo esc_attr(isset($options['max_image_width']) ? $options['max_image_width'] : 2048); ?>" min="800" max="4000" step="100" class="small-text">px
                    </label>
                    <label class="folio-inline-label-gap">
                        <?php esc_html_e('Height:', 'folio'); ?>
                        <input type="number" name="<?php echo esc_attr($option_name); ?>[max_image_height]" value="<?php echo esc_attr(isset($options['max_image_height']) ? $options['max_image_height'] : 2048); ?>" min="600" max="4000" step="100" class="small-text">px
                    </label>
                    <p class="description"><?php esc_html_e('Images larger than this will be auto-resized.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('WebP Support', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[enable_webp_conversion]" value="1" <?php checked(isset($options['enable_webp_conversion']) ? $options['enable_webp_conversion'] : 0, 1); ?>>
                        <?php esc_html_e('Enable WebP format support', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Allow uploading WebP images (server support required).', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Metadata', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[strip_image_metadata]" value="1" <?php checked(isset($options['strip_image_metadata']) ? $options['strip_image_metadata'] : 1, 1); ?>>
                        <?php esc_html_e('Remove image EXIF metadata', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Remove camera/GPS privacy data and reduce file size.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Other Optimizations', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Comment Styles', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[remove_comment_styles]" value="1" <?php checked(isset($options['remove_comment_styles']) ? $options['remove_comment_styles'] : 0, 1); ?>>
                        <?php esc_html_e('Remove comment-related styles', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('If comments are disabled, related CSS can be removed.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Update Email Notifications', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[disable_update_emails]" value="1" <?php checked(isset($options['disable_update_emails']) ? $options['disable_update_emails'] : 1, 1); ?>>
                        <?php esc_html_e('Disable auto-update email notifications', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Stop receiving WordPress/plugin/theme update emails.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Shortcode Paragraphs', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[remove_shortcode_p]" value="1" <?php checked(isset($options['remove_shortcode_p']) ? $options['remove_shortcode_p'] : 1, 1); ?>>
                        <?php esc_html_e('Remove automatic paragraph tags around shortcodes', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Avoid formatting issues in shortcode output.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Security Settings', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Restrict REST API', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[restrict_rest_api]" value="1" <?php checked(isset($options['restrict_rest_api']) ? $options['restrict_rest_api'] : 1, 1); ?>>
                        <?php esc_html_e('Disable REST API for logged-out users', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Improve security and reduce data exposure (still available for logged-in users).', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Disable XML-RPC Pingback', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[disable_xmlrpc_pingback]" value="1" <?php checked(isset($options['disable_xmlrpc_pingback']) ? $options['disable_xmlrpc_pingback'] : 1, 1); ?>>
                        <?php esc_html_e('Turn off XML-RPC Pingback functionality', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Help prevent DDoS abuse and improve security.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>
</div>
