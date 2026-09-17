<?php
/**
 * Dashboard — orders + certificates on the left, account/help cards on the right.
 *
 * @var WP_User $current_user
 */
defined( 'ABSPATH' ) || exit;

$u        = luma_catalogue_json()['config']['urls'];
$customer = new WC_Customer( $current_user->ID );
$ship     = array_filter( [ $customer->get_shipping_address_1(), $customer->get_shipping_city(), trim( $customer->get_shipping_state() . ' ' . $customer->get_shipping_postcode() ) ] );
$count    = wc_get_customer_order_count( $current_user->ID );
?>
<div class="acct-grid">
	<div class="acct-main">
		<?php do_action( 'woocommerce_account_dashboard' ); ?>
		<?php luma_account_orders_section( 1, 5 ); ?>
		<?php luma_account_certificates_section(); ?>
	</div>
	<aside class="acct-side">
		<section class="acct-card acct-side-card">
			<h3>Your account</h3>
			<dl class="acct-kv">
				<div><dt>Email</dt><dd><?php echo esc_html( $current_user->user_email ); ?></dd></div>
				<div><dt>Shipping to</dt><dd><?php echo $ship ? esc_html( implode( ', ', $ship ) ) : '<span class="muted">No address saved yet</span>'; ?></dd></div>
				<div><dt>Orders</dt><dd><?php echo esc_html( $count ); ?></dd></div>
			</dl>
			<div class="acct-side-links">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>">Addresses →</a>
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">Details &amp; password →</a>
				<a href="<?php echo esc_url( wc_logout_url() ); ?>">Sign out</a>
			</div>
		</section>
		<section class="acct-card acct-side-card">
			<h3>Quick links</h3>
			<div class="acct-side-links">
				<a href="<?php echo esc_url( $u['shop'] ); ?>">Browse the catalog →</a>
				<a href="<?php echo esc_url( $u['verify'] ); ?>">Verify a lot →</a>
				<a href="<?php echo esc_url( $u['shipping'] ?? home_url( '/shipping-returns/' ) ); ?>">Shipping &amp; returns →</a>
				<a href="<?php echo esc_url( $u['faq'] ); ?>">FAQ →</a>
			</div>
		</section>
		<section class="acct-card acct-side-card acct-help">
			<h3>Need a hand?</h3>
			<p>Questions about an order, a lot or a certificate — email <a href="mailto:<?php echo esc_attr( luma_owner_email() ); ?>"><?php echo esc_html( luma_owner_email() ); ?></a>. We reply within one business day.</p>
			<p class="acct-fine" style="margin:.8rem 0 0">Free next-day shipping on every order · same-day in Utah County before 12:00 pm MT.</p>
		</section>
	</aside>
</div>
<p class="acct-fine acct-legal">For laboratory research use only. To delete your account data, email <a href="mailto:<?php echo esc_attr( luma_owner_email() ); ?>"><?php echo esc_html( luma_owner_email() ); ?></a>.</p>
