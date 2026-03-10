<div class="folio-tab-content">
    <div class="folio-section">
        <h2><?php esc_html_e('Basic SEO Settings', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Homepage Title', 'folio'); ?></th>
                <td>
                    <input type="text" name="<?php echo esc_attr($option_name); ?>[seo_title]" value="<?php echo esc_attr(isset($options['seo_title']) ? $options['seo_title'] : ''); ?>" class="large-text">
                    <p class="description"><?php esc_html_e('Custom homepage SEO title. Leave blank to use default.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Homepage Keywords', 'folio'); ?></th>
                <td>
                    <input type="text" name="<?php echo esc_attr($option_name); ?>[seo_keywords]" value="<?php echo esc_attr(isset($options['seo_keywords']) ? $options['seo_keywords'] : ''); ?>" class="large-text">
                    <p class="description"><?php esc_html_e('Separate multiple keywords with commas.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Homepage Description', 'folio'); ?></th>
                <td>
                    <textarea name="<?php echo esc_attr($option_name); ?>[seo_description]" rows="3" class="large-text"><?php echo esc_textarea(isset($options['seo_description']) ? $options['seo_description'] : ''); ?></textarea>
                    <p class="description"><?php esc_html_e('Meta description for homepage, recommended length 150-160 characters.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Open Graph Settings', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable Open Graph', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[enable_og]" value="1" <?php checked(isset($options['enable_og']) ? $options['enable_og'] : 1, 1); ?>>
                        <?php esc_html_e('Optimize social sharing (Facebook, Twitter, etc.)', 'folio'); ?>
                    </label>
                </td>
            </tr>
        </table>
    </div>
</div>
