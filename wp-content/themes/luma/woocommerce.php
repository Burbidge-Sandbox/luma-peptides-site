<?php
/**
 * Wrapper for all WooCommerce views (archive, single, cart, checkout, account).
 * Layout only; templates under woocommerce/ override specific parts later.
 */
get_header();
woocommerce_content();
get_footer();
