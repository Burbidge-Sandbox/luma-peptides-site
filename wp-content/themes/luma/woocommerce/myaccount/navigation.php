<?php
/**
 * Account navigation as legacy chips.
 */
defined( 'ABSPATH' ) || exit;
do_action( 'woocommerce_before_account_navigation' );
?>
<nav class="woocommerce-MyAccount-navigation acct-nav" aria-label="Account pages">
	<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
		<?php $active = str_contains( wc_get_account_menu_item_classes( $endpoint ), 'is-active' ); ?>
		<a class="chip" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"<?php echo $active ? ' aria-pressed="true"' : ''; ?>><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>
<?php do_action( 'woocommerce_after_account_navigation' ); ?>
