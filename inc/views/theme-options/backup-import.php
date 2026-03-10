<div class="folio-section">
    <h2><?php esc_html_e('Import Settings', 'folio'); ?></h2>
    <p><?php esc_html_e('Restore settings from a previously exported JSON file.', 'folio'); ?></p>
    <p class="description folio-description-danger">
        <strong><?php esc_html_e('Warning:', 'folio'); ?></strong>
        <?php esc_html_e('Import will overwrite all current settings. Export a backup first.', 'folio'); ?>
    </p>

    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('folio_import_settings'); ?>
        <p>
            <input type="file" name="import_file" accept=".json" required>
        </p>
        <button type="submit" name="folio_import_settings" class="button button-secondary">
            <span class="dashicons dashicons-upload folio-button-icon"></span>
            <?php esc_html_e('Import Settings', 'folio'); ?>
        </button>
    </form>
</div>
