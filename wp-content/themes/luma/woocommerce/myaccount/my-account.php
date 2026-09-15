<?php
/**
 * My Account wrapper — legacy account.html (.acct) layout.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="acct">
	<?php do_action( 'woocommerce_account_navigation' ); ?>
	<div class="woocommerce-MyAccount-content acct-content">
		<?php do_action( 'woocommerce_account_content' ); ?>
	</div>
</div>
