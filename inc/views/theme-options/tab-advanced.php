<div class="folio-tab-content">
    <div class="folio-section">
        <h2><?php esc_html_e('Custom Code', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Custom CSS', 'folio'); ?></th>
                <td>
                    <textarea name="<?php echo esc_attr($option_name); ?>[custom_css]" rows="10" class="large-text code"><?php echo esc_textarea(isset($options['custom_css']) ? $options['custom_css'] : ''); ?></textarea>
                    <p class="description"><?php esc_html_e('Add custom CSS without editing theme files.', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Header Code', 'folio'); ?></th>
                <td>
                    <textarea name="<?php echo esc_attr($option_name); ?>[header_code]" rows="5" class="large-text code"><?php echo esc_textarea(isset($options['header_code']) ? $options['header_code'] : ''); ?></textarea>
                    <p class="description"><?php esc_html_e('Insert code inside &lt;head&gt; (e.g., Google Analytics).', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Footer Code', 'folio'); ?></th>
                <td>
                    <textarea name="<?php echo esc_attr($option_name); ?>[footer_code]" rows="5" class="large-text code"><?php echo esc_textarea(isset($options['footer_code']) ? $options['footer_code'] : ''); ?></textarea>
                    <p class="description"><?php esc_html_e('Insert code before &lt;/body&gt;.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Maintenance Mode', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable Maintenance Mode', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[maintenance_mode]" value="1" <?php checked(isset($options['maintenance_mode']) ? $options['maintenance_mode'] : 0, 1); ?>>
                        <?php esc_html_e('Show maintenance page to non-admin users', 'folio'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Maintenance Message', 'folio'); ?></th>
                <td>
                    <textarea name="<?php echo esc_attr($option_name); ?>[maintenance_message]" rows="3" class="large-text"><?php echo esc_textarea(isset($options['maintenance_message']) ? $options['maintenance_message'] : __('The site is under maintenance, please visit later.', 'folio')); ?></textarea>
                    <p class="description"><?php esc_html_e('Maintenance message displayed to visitors. HTML tags are supported.', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Scheduled Maintenance', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable Scheduled Maintenance', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[maintenance_scheduled]" value="1" <?php checked(isset($options['maintenance_scheduled']) ? $options['maintenance_scheduled'] : 0, 1); ?>>
                        <?php esc_html_e('Automatically enable/disable maintenance mode by schedule', 'folio'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Start Time', 'folio'); ?></th>
                <td>
                    <input type="datetime-local" name="<?php echo esc_attr($option_name); ?>[maintenance_start_time]" value="<?php echo esc_attr(isset($options['maintenance_start_time']) ? date('Y-m-d\TH:i', strtotime($options['maintenance_start_time'])) : ''); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Maintenance start time (optional, leave blank to start immediately).', 'folio'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('End Time', 'folio'); ?></th>
                <td>
                    <input type="datetime-local" name="<?php echo esc_attr($option_name); ?>[maintenance_end_time]" value="<?php echo esc_attr(isset($options['maintenance_end_time']) ? date('Y-m-d\TH:i', strtotime($options['maintenance_end_time'])) : ''); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Maintenance end time (optional, leave blank to end manually).', 'folio'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="folio-section">
        <h2><?php esc_html_e('Advanced Settings', 'folio'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('IP Whitelist', 'folio'); ?></th>
                <td>
                    <textarea name="<?php echo esc_attr($option_name); ?>[maintenance_bypass_ips]" rows="5" class="large-text" placeholder="192.168.1.1&#10;10.0.0.0/8&#10;203.0.113.0/24"><?php echo esc_textarea(isset($options['maintenance_bypass_ips']) ? $options['maintenance_bypass_ips'] : ''); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('IP addresses allowed to bypass maintenance mode, one per line.', 'folio'); ?><br>
                        <?php esc_html_e('Supports single IP (e.g. 192.168.1.1) and CIDR format (e.g. 192.168.1.0/24).', 'folio'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Access Logs', 'folio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[maintenance_log_enabled]" value="1" <?php checked(isset($options['maintenance_log_enabled']) ? $options['maintenance_log_enabled'] : 0, 1); ?>>
                        <?php esc_html_e('Record access logs during maintenance', 'folio'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Log visitor info on maintenance page for analysis and security monitoring.', 'folio'); ?></p>
                </td>
            </tr>
        </table>

        <?php $this->render_maintenance_logs_section($options, $logs); ?>
    </div>
</div>
