<?php
/**
 * Sidebar Template
 *
 * @package Folio
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_active_sidebar('footer-widgets')) {
    return;
}
?>

<aside id="secondary" class="widget-area" role="complementary">
    <?php dynamic_sidebar('footer-widgets'); ?>
</aside>
