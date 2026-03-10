<div class="folio-section">
    <h2><?php esc_html_e('Performance Dashboard', 'folio'); ?></h2>
    <p><?php esc_html_e('Monitor site performance metrics including load time, memory usage, and DB queries.', 'folio'); ?></p>

    <div class="folio-actions-row">
        <button type="button" class="button button-primary" id="folio-load-performance-data">
            <span class="dashicons dashicons-chart-line folio-button-icon"></span>
            <?php esc_html_e('Load Performance Data', 'folio'); ?>
        </button>
        <button type="button" class="button button-secondary" id="folio-clear-performance-logs">
            <span class="dashicons dashicons-trash folio-button-icon"></span>
            <?php esc_html_e('Clear Logs', 'folio'); ?>
        </button>
    </div>

    <div id="folio-performance-dashboard" class="folio-dashboard-hidden">
        <div class="folio-grid-two">
            <div class="folio-info-card">
                <h4 class="folio-card-heading"><?php esc_html_e('Performance Stats', 'folio'); ?></h4>
                <div id="folio-performance-stats"></div>
            </div>
            <div class="folio-info-card">
                <h4 class="folio-card-heading"><?php esc_html_e('Recent Requests', 'folio'); ?></h4>
                <div id="folio-recent-requests" class="folio-scroll-box folio-scroll-box-sm"></div>
            </div>
        </div>

        <div class="folio-warning-card">
            <h4 class="folio-warning-heading"><?php esc_html_e('Slow Query Warnings', 'folio'); ?></h4>
            <div id="folio-slow-queries"></div>
        </div>
    </div>
</div>
