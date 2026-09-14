<?php
/**
 * Wrapper for the remaining WooCommerce views (cart, checkout, account,
 * category archives). Catalog and product pages have their own templates.
 */
get_header();
echo '<main id="main"><div class="wrap woo-page">';
woocommerce_content();
echo '</div></main>';
get_footer();
