<?php
/**
 * Dashboard — orders + certificates on the left, the shared sidebar (inc/account.php) on the right.
 *
 * @var WP_User $current_user
 */
defined( 'ABSPATH' ) || exit;

?>
<div class="acct-grid">
	<div class="acct-main">
		<?php do_action( 'woocommerce_account_dashboard' ); ?>
		<?php luma_account_orders_section( 1, 5 ); ?>
		<?php luma_account_certificates_section(); ?>
	</div>
	<?php luma_account_sidebar(); ?>
</div>
<p class="acct-fine acct-legal">For laboratory research use only. To delete your account data, email <a href="mailto:<?php echo esc_attr( luma_owner_email() ); ?>"><?php echo esc_html( luma_owner_email() ); ?></a>.</p>
