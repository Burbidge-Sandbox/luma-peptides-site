<?php
/**
 * Orders endpoint — legacy .acct-order cards.
 *
 * @var stdClass $customer_orders
 * @var bool     $has_orders
 * @var int      $current_page
 */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_orders', $has_orders );
luma_account_render_orders( $customer_orders, $has_orders, (int) $current_page );
do_action( 'woocommerce_after_account_orders', $has_orders );
