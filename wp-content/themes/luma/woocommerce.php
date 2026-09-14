<?php
/**
 * Wrapper for all WooCommerce views (archive, single, cart, checkout, account).
 * woocommerce_content() does not fire the before/after_main_content hooks,
 * so the layout wrapper is applied here directly.
 */
get_header();
echo '<main id="content" class="wrap section woo-main">';
woocommerce_content();
echo '</main>';
get_footer();
