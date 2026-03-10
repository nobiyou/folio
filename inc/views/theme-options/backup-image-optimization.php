<div class="folio-section">
    <h2><?php esc_html_e('Image Optimization Management', 'folio'); ?></h2>
    <p><?php esc_html_e('Batch-optimize existing images and review optimization stats and storage savings.', 'folio'); ?></p>

    <div class="folio-actions-row">
        <button type="button" class="button button-primary" id="folio-batch-optimize-images">
            <span class="dashicons dashicons-format-image folio-button-icon"></span>
            <?php esc_html_e('Batch Optimize Images', 'folio'); ?>
        </button>
        <button type="button" class="button button-secondary" id="folio-get-image-stats">
            <span class="dashicons dashicons-chart-pie folio-button-icon"></span>
            <?php esc_html_e('View Stats', 'folio'); ?>
        </button>
    </div>

    <div id="folio-image-optimization-result" class="folio-result-margin-bottom"></div>

    <div id="folio-image-stats-dashboard" class="folio-dashboard-hidden">
        <div class="folio-grid-two">
            <div class="folio-info-card">
                <h4 class="folio-card-heading"><?php esc_html_e('Optimization Stats', 'folio'); ?></h4>
                <div id="folio-image-stats-summary"></div>
            </div>
            <div class="folio-info-card">
                <h4 class="folio-card-heading"><?php esc_html_e('Recent Optimizations', 'folio'); ?></h4>
                <div id="folio-recent-optimizations" class="folio-scroll-box folio-scroll-box-sm"></div>
            </div>
        </div>
    </div>
</div>
