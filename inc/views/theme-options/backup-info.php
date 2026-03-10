<div class="folio-section">
    <h2><?php esc_html_e('Current Settings Info', 'folio'); ?></h2>
    <table class="widefat">
        <tr>
            <td><strong><?php esc_html_e('Option Name', 'folio'); ?></strong></td>
            <td><code>folio_theme_options</code></td>
        </tr>
        <tr>
            <td><strong><?php esc_html_e('Database Table', 'folio'); ?></strong></td>
            <td><code>wp_options</code></td>
        </tr>
        <tr>
            <td><strong><?php esc_html_e('Settings Count', 'folio'); ?></strong></td>
            <td><?php echo count($options); ?> <?php esc_html_e('items', 'folio'); ?></td>
        </tr>
        <tr>
            <td><strong><?php esc_html_e('Data Size', 'folio'); ?></strong></td>
            <td><?php echo size_format(strlen(serialize($options))); ?></td>
        </tr>
        <tr>
            <td><strong><?php esc_html_e('Last Updated', 'folio'); ?></strong></td>
            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?></td>
        </tr>
    </table>
</div>
