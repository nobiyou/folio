<h3><?php esc_html_e('Recent Access Logs', 'folio'); ?></h3>
<div class="folio-scroll-box folio-scroll-box-lg">
    <table class="widefat folio-table-reset-margin">
        <thead>
            <tr>
                <th><?php esc_html_e('Time', 'folio'); ?></th>
                <th><?php esc_html_e('IP Address', 'folio'); ?></th>
                <th><?php esc_html_e('Page URL', 'folio'); ?></th>
                <th><?php esc_html_e('Referrer', 'folio'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_reverse(array_slice($logs, -20)) as $log) : ?>
            <tr>
                <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($log['timestamp']))); ?></td>
                <td><?php echo esc_html($log['ip']); ?></td>
                <td><?php echo esc_html($log['url']); ?></td>
                <td><?php echo esc_html($log['referer'] ? parse_url($log['referer'], PHP_URL_HOST) : '-'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p>
    <button
        type="button"
        class="button"
        id="folio-clear-maintenance-logs"
        data-confirm="<?php echo esc_attr__('Are you sure you want to clear all maintenance logs?', 'folio'); ?>"
        data-clear-url="<?php echo esc_url(wp_nonce_url(admin_url('themes.php?page=folio-theme-options&tab=advanced&action=clear_maintenance_logs'), 'clear_maintenance_logs')); ?>">
        <?php esc_html_e('Clear Logs', 'folio'); ?>
    </button>
</p>
