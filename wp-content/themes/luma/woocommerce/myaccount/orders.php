<?php
/**
 * Orders endpoint — order cards in the same two-column layout as the overview.
 * Woo injects its notices (e.g. "confirm email address") via the before hook;
 * style.css restyles them so the action reads as a small secondary button.
 *
 * @var stdClass $customer_orders
 * @var bool     $has_orders
 * @var int      $current_page
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="acct-grid">
	<div class="acct-main">
		<?php do_action( 'woocommerce_before_account_orders', $has_orders ); ?>
		<?php luma_account_render_orders( $customer_orders, $has_orders, (int) $current_page ); ?>
		<?php do_action( 'woocommerce_after_account_orders', $has_orders ); ?>
	</div>
	<?php luma_account_sidebar(); ?>
</div>
