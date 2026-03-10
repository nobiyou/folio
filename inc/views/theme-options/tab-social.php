<div class="folio-tab-content">
    <div class="folio-section">
        <h2><?php esc_html_e('Social Media Links', 'folio'); ?></h2>
        <p class="description"><?php esc_html_e('Configure social links and icons displayed in the sidebar.', 'folio'); ?></p>

        <table class="form-table">
            <tr>
                <th scope="row"><span class="dashicons dashicons-email"></span> <?php esc_html_e('Email Address', 'folio'); ?></th>
                <td>
                    <?php $this->render_icon_select('email_icon', isset($options['email_icon']) ? $options['email_icon'] : '', 'email'); ?>
                    <input type="email" name="<?php echo esc_attr($option_name); ?>[email]" value="<?php echo esc_attr(isset($options['email']) ? $options['email'] : ''); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><span class="dashicons dashicons-instagram"></span> <?php esc_html_e('Instagram', 'folio'); ?></th>
                <td>
                    <?php $this->render_icon_select('instagram_icon', isset($options['instagram_icon']) ? $options['instagram_icon'] : '', 'instagram'); ?>
                    <input type="url" name="<?php echo esc_attr($option_name); ?>[instagram]" value="<?php echo esc_attr(isset($options['instagram']) ? $options['instagram'] : ''); ?>" class="regular-text" placeholder="https://instagram.com/username">
                </td>
            </tr>
            <tr>
                <th scope="row"><span class="dashicons dashicons-linkedin"></span> <?php esc_html_e('LinkedIn', 'folio'); ?></th>
                <td>
                    <?php $this->render_icon_select('linkedin_icon', isset($options['linkedin_icon']) ? $options['linkedin_icon'] : '', 'linkedin'); ?>
                    <input type="url" name="<?php echo esc_attr($option_name); ?>[linkedin]" value="<?php echo esc_attr(isset($options['linkedin']) ? $options['linkedin'] : ''); ?>" class="regular-text" placeholder="https://linkedin.com/in/username">
                </td>
            </tr>
            <tr>
                <th scope="row"><span class="dashicons dashicons-twitter"></span> <?php esc_html_e('Twitter/X', 'folio'); ?></th>
                <td>
                    <?php $this->render_icon_select('twitter_icon', isset($options['twitter_icon']) ? $options['twitter_icon'] : '', 'twitter'); ?>
                    <input type="url" name="<?php echo esc_attr($option_name); ?>[twitter]" value="<?php echo esc_attr(isset($options['twitter']) ? $options['twitter'] : ''); ?>" class="regular-text" placeholder="https://twitter.com/username">
                </td>
            </tr>
            <tr>
                <th scope="row"><span class="dashicons dashicons-facebook"></span> <?php esc_html_e('Facebook', 'folio'); ?></th>
                <td>
                    <?php $this->render_icon_select('facebook_icon', isset($options['facebook_icon']) ? $options['facebook_icon'] : '', 'facebook'); ?>
                    <input type="url" name="<?php echo esc_attr($option_name); ?>[facebook]" value="<?php echo esc_attr(isset($options['facebook']) ? $options['facebook'] : ''); ?>" class="regular-text" placeholder="https://facebook.com/username">
                </td>
            </tr>
            <tr>
                <th scope="row"><span class="dashicons dashicons-admin-site"></span> <?php esc_html_e('GitHub', 'folio'); ?></th>
                <td>
                    <?php $this->render_icon_select('github_icon', isset($options['github_icon']) ? $options['github_icon'] : '', 'github'); ?>
                    <input type="url" name="<?php echo esc_attr($option_name); ?>[github]" value="<?php echo esc_attr(isset($options['github']) ? $options['github'] : ''); ?>" class="regular-text" placeholder="https://github.com/username">
                </td>
            </tr>
        </table>
    </div>
</div>
