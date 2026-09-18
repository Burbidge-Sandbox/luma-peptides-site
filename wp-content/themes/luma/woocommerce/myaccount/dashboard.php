<?php
/**
 * Overview — Option A: meta strip, full-width order ledger, certificates + help below.
 *
 * @var WP_User $current_user
 */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_account_dashboard' );
luma_account_meta_strip();
luma_account_orders_section( 1, 5, true );
luma_account_bottom_row();
?>
<p class="acct-fine acct-legal">For laboratory research use only. To delete your account data, email <a href="mailto:<?php echo esc_attr( luma_owner_email() ); ?>"><?php echo esc_html( luma_owner_email() ); ?></a>.</p>
