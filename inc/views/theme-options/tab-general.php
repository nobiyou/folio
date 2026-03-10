<div class="folio-tab-content">
    <div class="folio-section">
        <h2><?php esc_html_e('Site Identity', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Site Logo', 'folio'); ?></th>
                <td>
                    <?php $logo_url = isset($options['site_logo']) ? esc_url($options['site_logo']) : ''; ?>
                    <div class="folio-image-upload-wrapper">
                        <div class="folio-image-preview">
                            <?php if ($logo_url) : ?>
                                <img src="<?php echo esc_url($logo_url); ?>" class="folio-image-preview-image folio-image-preview-image--logo" alt="<?php echo esc_attr__('Logo Preview', 'folio'); ?>">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="<?php echo esc_attr($option_name); ?>[site_logo]" id="site_logo" value="<?php echo esc_attr($logo_url); ?>">
                        <button type="button" class="button folio-upload-image-button" data-target="site_logo">
                            <?php echo $logo_url ? esc_html__('Replace Logo', 'folio') : esc_html__('Select Logo', 'folio'); ?>
                        </button>
                        <?php if ($logo_url) : ?>
                            <button type="button" class="button folio-remove-image-button folio-button-spacing" data-target="site_logo">
                                <?php esc_html_e('Remove Logo', 'folio'); ?>
                            </button>
                        <?php endif; ?>
                        <p class="description"><?php esc_html_e('Upload a site logo image. If not set, the site title will be displayed.', 'folio'); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Site Favicon', 'folio'); ?></th>
                <td>
                    <?php $favicon_url = isset($options['site_favicon']) ? esc_url($options['site_favicon']) : ''; ?>
                    <div class="folio-image-upload-wrapper">
                        <div class="folio-image-preview">
                            <?php if ($favicon_url) : ?>
                                <img src="<?php echo esc_url($favicon_url); ?>" class="folio-image-preview-image folio-image-preview-image--icon" alt="<?php echo esc_attr__('Favicon Preview', 'folio'); ?>">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="<?php echo esc_attr($option_name); ?>[site_favicon]" id="site_favicon" value="<?php echo esc_attr($favicon_url); ?>">
                        <button type="button" class="button folio-upload-image-button" data-target="site_favicon">
                            <?php echo $favicon_url ? esc_html__('Replace Icon', 'folio') : esc_html__('Select Icon', 'folio'); ?>
                        </button>
                        <?php if ($favicon_url) : ?>
                            <button type="button" class="button folio-remove-image-button folio-button-spacing" data-target="site_favicon">
                                <?php esc_html_e('Remove Icon', 'folio'); ?>
                            </button>
                        <?php endif; ?>
                        <p class="description"><?php esc_html_e('Upload a site favicon (recommended size: 32x32px or 16x16px).', 'folio'); ?></p>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Theme Mode', 'folio'); ?></h2>
        <table class="form-table">
            <?php $current_theme_mode = isset($options['theme_mode']) ? $options['theme_mode'] : 'auto'; ?>
            <tr>
                <th scope="row"><?php esc_html_e('Default Theme Mode', 'folio'); ?></th>
                <td>
                    <select name="<?php echo esc_attr($option_name); ?>[theme_mode]" id="theme-mode-select">
                        <option value="auto" <?php selected($current_theme_mode, 'auto'); ?>><?php esc_html_e('Auto (based on sunrise/sunset)', 'folio'); ?></option>
                        <option value="light" <?php selected($current_theme_mode, 'light'); ?>><?php esc_html_e('Light Mode', 'folio'); ?></option>
                        <option value="dark" <?php selected($current_theme_mode, 'dark'); ?>><?php esc_html_e('Dark Mode', 'folio'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Choose the default display mode. In "Auto", the theme switches between light/dark based on sunrise and sunset times.', 'folio'); ?></p>
                </td>
            </tr>
            <tr class="auto-sunset-settings <?php echo ($current_theme_mode === 'auto') ? '' : 'folio-is-hidden'; ?>">
                <th scope="row"><?php esc_html_e('Sunrise Time', 'folio'); ?></th>
                <td>
                    <input type="time" name="<?php echo esc_attr($option_name); ?>[sunrise_time]" value="<?php echo esc_attr(isset($options['sunrise_time']) ? $options['sunrise_time'] : '06:00'); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Set sunrise time (24h). Theme switches to light mode after sunrise.', 'folio'); ?></p>
                </td>
            </tr>
            <tr class="auto-sunset-settings <?php echo ($current_theme_mode === 'auto') ? '' : 'folio-is-hidden'; ?>">
                <th scope="row"><?php esc_html_e('Sunset Time', 'folio'); ?></th>
                <td>
                    <input type="time" name="<?php echo esc_attr($option_name); ?>[sunset_time]" value="<?php echo esc_attr(isset($options['sunset_time']) ? $options['sunset_time'] : '18:00'); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Set sunset time (24h). Theme switches to dark mode after sunset.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Show Theme Toggle', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[show_theme_toggle]" value="1" <?php checked(!isset($options['show_theme_toggle']) || !empty($options['show_theme_toggle']), true); ?>>
                        <?php esc_html_e('Allow visitors to switch light/dark mode manually', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Display a theme toggle in the header so visitors can switch modes manually.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Content Display Settings', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('New Badge Days', 'folio'); ?></th>
                <td>
                    <input type="number" name="<?php echo esc_attr($option_name); ?>[new_days]" value="<?php echo esc_attr(isset($options['new_days']) ? $options['new_days'] : 30); ?>" min="1" max="365" class="small-text">
                    <p class="description"><?php esc_html_e('Show the "New" badge for this many days after publish.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Posts Per Page', 'folio'); ?></th>
                <td>
                    <input type="number" name="<?php echo esc_attr($option_name); ?>[posts_per_page]" value="<?php echo esc_attr(isset($options['posts_per_page']) ? $options['posts_per_page'] : 12); ?>" min="1" max="100" class="small-text">
                    <p class="description"><?php esc_html_e('Number of posts shown per archive page.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Show "Brain Powered Fun" Hexagon', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[show_brain_hexagon]" value="1" <?php checked(!isset($options['show_brain_hexagon']) || !empty($options['show_brain_hexagon']), true); ?>>
                        <?php esc_html_e('Show a decorative hexagon at the end of the homepage post grid.', 'folio'); ?>
                    </label>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Footer Settings', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Copyright Text', 'folio'); ?></th>
                <td>
                    <?php
                    $default_copyright = sprintf(
                        __('&copy; %s Design & build by %s.', 'folio'),
                        date('Y'),
                        get_bloginfo('name')
                    );
                    $copyright_value = isset($options['copyright']) && !empty($options['copyright'])
                        ? $options['copyright']
                        : $default_copyright;
                    ?>
                    <textarea name="<?php echo esc_attr($option_name); ?>[copyright]" rows="3" class="large-text"><?php echo esc_textarea($copyright_value); ?></textarea>
                    <p class="description"><?php esc_html_e('Leave blank to use default text. HTML is supported.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>
</div>
