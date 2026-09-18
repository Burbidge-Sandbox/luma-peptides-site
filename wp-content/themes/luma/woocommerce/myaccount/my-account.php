<?php
/**
 * My Account wrapper — Option A "ledger" layout.
 * The overview carries its own meta strip (email / ship-to / orders + account links),
 * so the chip nav is rendered only on the inner endpoints.
 */
defined( 'ABSPATH' ) || exit;

$luma_endpoint = WC()->query ? WC()->query->get_current_endpoint() : '';
?>
<div class="acct acct-ledger-page">
	<?php if ( $luma_endpoint ) : ?>
		<?php do_action( 'woocommerce_account_navigation' ); ?>
	<?php endif; ?>
	<div class="woocommerce-MyAccount-content acct-content">
		<?php do_action( 'woocommerce_account_content' ); ?>
	</div>
</div>
