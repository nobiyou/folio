<div class="folio-section">
    <h2><?php esc_html_e('Export Settings', 'folio'); ?></h2>
    <p><?php esc_html_e('Export all current theme settings as JSON for backup or migration.', 'folio'); ?></p>

    <form method="post" action="">
        <?php wp_nonce_field('folio_export_settings'); ?>
        <button type="submit" name="folio_export_settings" class="button button-primary">
            <span class="dashicons dashicons-download folio-button-icon"></span>
            <?php esc_html_e('Export Settings', 'folio'); ?>
        </button>
    </form>
</div>
