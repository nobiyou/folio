<div class="folio-tab-content">
    <div class="folio-section">
        <h2><?php esc_html_e('UX Enhancements', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Image Lazy Load', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[enable_lazy_load]" value="1" <?php checked(isset($options['enable_lazy_load']) ? $options['enable_lazy_load'] : 1, 1); ?>>
                        <?php esc_html_e('Enable image lazy loading', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Load images only when visible to improve page speed.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Back to Top Button', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[enable_back_to_top]" value="1" <?php checked(isset($options['enable_back_to_top']) ? $options['enable_back_to_top'] : 1, 1); ?>>
                        <?php esc_html_e('Show back-to-top button', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Show after scrolling 300px; click to smoothly scroll to top.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Navigation and Reading', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Breadcrumbs', 'folio'); ?></th>
                <td>
                    <p class="description">
                        <?php esc_html_e('Breadcrumbs are enabled. Use this code in templates:', 'folio'); ?><br>
                        <code>&lt;?php folio_breadcrumbs(); ?&gt;</code>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Reading Time Estimate', 'folio'); ?></th>
                <td>
                    <p class="description">
                        <?php esc_html_e('Reading time estimation is enabled. Use this code in templates:', 'folio'); ?><br>
                        <code>&lt;?php folio_reading_time(); ?&gt;</code><br>
                        <?php esc_html_e('Or get minutes value:', 'folio'); ?><br>
                        <code>&lt;?php $minutes = folio_get_reading_time(); ?&gt;</code>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Usage Guide', 'folio'); ?></h2>
        <div class="folio-guide-box">
            <h3 class="folio-card-title"><?php esc_html_e('How to use these features', 'folio'); ?></h3>

            <h4>1. <?php esc_html_e('Image Lazy Load', 'folio'); ?></h4>
            <p><?php esc_html_e('Lazy loading is applied automatically to images.', 'folio'); ?></p>

            <h4>2. <?php esc_html_e('Back to Top Button', 'folio'); ?></h4>
            <p><?php esc_html_e('Shown automatically at the bottom-right of pages.', 'folio'); ?></p>

            <h4>3. <?php esc_html_e('Breadcrumbs', 'folio'); ?></h4>
            <p><?php esc_html_e('Add this where breadcrumbs should appear (e.g., post header):', 'folio'); ?></p>
            <pre class="folio-code-block">&lt;?php if (function_exists('folio_breadcrumbs')) folio_breadcrumbs(); ?&gt;</pre>

            <h4>4. <?php esc_html_e('Reading Time', 'folio'); ?></h4>
            <p><?php esc_html_e('Add this in post meta area:', 'folio'); ?></p>
            <pre class="folio-code-block">&lt;?php if (function_exists('folio_reading_time')) folio_reading_time(); ?&gt;</pre>
        </div>
    </div>
</div>
