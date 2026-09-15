<?php
/**
 * Dashboard = recent orders + certificates (legacy account view).
 *
 * @var WP_User $current_user
 */
defined( 'ABSPATH' ) || exit;

$u = luma_catalogue_json()['config']['urls'];
?>
<p class="acct-fine" style="margin:0 0 1.4rem">Signed in as <b><?php echo esc_html( $current_user->user_email ); ?></b> · <a class="linkbtn" href="<?php echo esc_url( wc_logout_url() ); ?>">Sign out</a></p>
<?php
do_action( 'woocommerce_account_dashboard' );
luma_account_orders_section( 1, 5 );
luma_account_certificates_section();
?>
<p class="acct-fine" style="text-align:center;margin-top:2rem">To delete your account data, email <a href="mailto:<?php echo esc_attr( luma_owner_email() ); ?>"><?php echo esc_html( luma_owner_email() ); ?></a>.</p>
