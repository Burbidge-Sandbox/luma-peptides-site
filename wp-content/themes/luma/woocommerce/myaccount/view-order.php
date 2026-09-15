<?php
/**
 * View order — legacy order box (same markup as the confirmation page).
 *
 * @var WC_Order $order
 * @var int      $order_id
 */
defined( 'ABSPATH' ) || exit;

$u     = luma_catalogue_json()['config']['urls'];
$paid  = $order->is_paid();
$money = fn( $n ) => wc_price( $n, [ 'currency' => $order->get_currency() ] );
$ship  = $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address();
$ack   = (string) $order->get_meta( '_luma_ruo_ack' );
$lots  = luma_order_lots( $order );
?>
<div class="acct-sec" style="margin-top:0">
	<div class="acct-head"><h2>Order <?php echo esc_html( $order->get_order_number() ); ?></h2><span class="<?php echo esc_attr( luma_order_pill_class( $order ) ); ?>"><?php echo esc_html( luma_order_pill_label( $order ) ); ?></span></div>
	<p class="acct-fine" style="margin:0 0 1rem">Placed <?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?><?php echo $order->get_date_paid() ? ' · paid ' . esc_html( wc_format_datetime( $order->get_date_paid() ) ) : ''; ?></p>
	<div class="order-box" style="margin:0">
		<?php foreach ( $order->get_items() as $item ) : ?>
			<?php $p = $item->get_product(); $strength = $p ? (string) $p->get_meta( '_luma_strength' ) : ''; ?>
			<div class="coa-row"><span><?php echo esc_html( $item->get_quantity() ); ?>× <?php echo esc_html( $item->get_name() ); ?> <?php if ( $strength ) : ?><small style="color:var(--muted)"><?php echo esc_html( $strength ); ?></small><?php endif; ?></span><b><?php echo wp_kses_post( $money( $item->get_total() ) ); ?></b></div>
		<?php endforeach; ?>
		<div class="coa-row"><span>Subtotal</span><b><?php echo wp_kses_post( $money( $order->get_subtotal() ) ); ?></b></div>
		<?php if ( (float) $order->get_total_discount() > 0 ) : ?><div class="coa-row"><span>Volume discount</span><b class="pass">−<?php echo wp_kses_post( $money( $order->get_total_discount() ) ); ?></b></div><?php endif; ?>
		<?php if ( (float) $order->get_total_tax() > 0 ) : ?><div class="coa-row"><span>Sales tax</span><b><?php echo wp_kses_post( $money( $order->get_total_tax() ) ); ?></b></div><?php endif; ?>
		<div class="coa-row"><span>Shipping<?php echo $order->get_shipping_method() ? ' · ' . esc_html( $order->get_shipping_method() ) : ''; ?></span><b><?php echo (float) $order->get_shipping_total() > 0 ? wp_kses_post( $money( $order->get_shipping_total() ) ) : 'Free'; ?></b></div>
		<?php if ( (float) $order->get_total_refunded() > 0 ) : ?><div class="coa-row"><span>Refunded</span><b class="pass">−<?php echo wp_kses_post( $money( $order->get_total_refunded() ) ); ?></b></div><?php endif; ?>
		<div class="coa-row"><span><b>Total</b><?php echo $order->get_payment_method_title() ? ' · ' . esc_html( $order->get_payment_method_title() ) : ''; ?></span><b><?php echo wp_kses_post( $money( $order->get_total() ) ); ?></b></div>
		<?php if ( $ship ) : ?><p style="font-size:.85rem;color:var(--muted);margin:1rem 0 0"><b>Ships to:</b> <?php echo wp_kses_post( str_replace( '<br/>', ', ', $ship ) ); ?></p><?php endif; ?>
		<?php $trk = luma_order_tracking( $order ); if ( $trk ) : ?><p style="font-size:.85rem;color:var(--muted);margin:.5rem 0 0"><b>Tracking:</b> <?php echo esc_html( $trk['carrier'] ); ?> <?php echo $trk['url'] ? '<a href="' . esc_url( $trk['url'] ) . '" target="_blank" rel="noopener" style="color:var(--terra);text-decoration:underline">' . esc_html( $trk['number'] ) . '</a>' : esc_html( $trk['number'] ); ?></p><?php endif; ?>
		<?php if ( $order->get_customer_note() ) : ?><p style="font-size:.85rem;color:var(--muted);margin:.5rem 0 0"><b>Note:</b> <?php echo esc_html( $order->get_customer_note() ); ?></p><?php endif; ?>
		<?php if ( $lots ) : ?><p style="font-size:.85rem;color:var(--muted);margin:.5rem 0 0"><b>Lots:</b> <?php foreach ( $lots as $i => $l ) : ?><?php echo $i ? ', ' : ''; ?><a href="<?php echo esc_url( add_query_arg( 'lot', rawurlencode( $l ), $u['verify'] ) ); ?>" style="color:var(--terra);text-decoration:underline"><?php echo esc_html( $l ); ?></a><?php endforeach; ?></p><?php endif; ?>
		<?php if ( $ack ) : ?><p style="font-size:.78rem;color:var(--muted);margin:.5rem 0 0">Research-use acknowledgement recorded <?php echo esc_html( substr( $ack, 0, 16 ) ); ?> UTC.</p><?php endif; ?>
		<?php if ( ! $order->has_status( [ 'cancelled', 'refunded', 'failed' ] ) ) : ?>
		<div class="timeline"><div class="done">Order placed</div><div class="<?php echo $paid ? 'done' : ''; ?>">Payment received</div><div class="<?php echo $order->has_status( 'completed' ) ? 'done' : ''; ?>">Lab release</div><div class="<?php echo $order->has_status( 'completed' ) ? 'done' : ''; ?>">Shipped</div></div>
		<?php endif; ?>
	</div>
	<div class="acct-actions" style="margin-top:1rem">
		<?php foreach ( wc_get_account_orders_actions( $order ) as $key => $a ) : if ( 'view' === $key ) continue; ?>
			<a class="btn <?php echo 'pay' === $key ? 'btn-primary' : 'btn-outline'; ?> btn-sm" href="<?php echo esc_url( $a['url'] ); ?>"><?php echo esc_html( $a['name'] ); ?></a>
		<?php endforeach; ?>
		<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">← All orders</a>
	</div>
</div>
<?php /* Woo's default order-details table is hooked on woocommerce_view_order; the legacy box above replaces it. */ ?>
