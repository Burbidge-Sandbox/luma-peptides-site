<?php
/**
 * Order received — port of legacy confirmation.html / confirmation.js onto a
 * real WooCommerce order. Same .confirm / .order-box / .timeline markup.
 *
 * @var WC_Order $order
 */
defined( 'ABSPATH' ) || exit;

$u = luma_catalogue_json()['config']['urls'];
if ( ! $order ) {
	echo '<div class="wrap confirm"><p style="color:var(--muted)">We couldn\'t find that order. <a href="' . esc_url( $u['shop'] ) . '" style="color:var(--terra)">Continue shopping →</a></p></div>';
	return;
}
$failed  = $order->has_status( 'failed' );
$paid    = $order->is_paid();
$pending = $order->has_status( [ 'pending', 'on-hold' ] );
$num     = $order->get_order_number();
$ack     = (string) $order->get_meta( '_luma_ruo_ack' );
$ship    = $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address();
$money   = fn( $n ) => wc_price( $n, [ 'currency' => $order->get_currency() ] );
?>
<div class="wrap confirm">
 <?php if ( $failed ) : ?>
  <div class="check" style="background:#F6E4E1"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></div>
  <span class="eyebrow">Payment not completed</span>
  <h1 style="font-size:clamp(2.2rem,4vw,3.2rem)">That payment didn't go through.</h1>
  <p style="color:var(--ink-2)">Order <b><?php echo esc_html( $num ); ?></b> was saved but the card was declined or the payment was cancelled. Nothing has been charged.</p>
  <div style="display:flex;gap:.8rem;justify-content:center;margin-top:1rem;flex-wrap:wrap"><a class="btn btn-primary" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>">Try again</a><a class="btn btn-outline" href="<?php echo esc_url( $u['contact'] ); ?>">Contact Luma</a></div>
 <?php else : ?>
  <div class="check"><svg viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></div>
  <span class="eyebrow"><?php echo $paid ? 'Order confirmed · payment received' : 'Order reserved · payment pending'; ?></span>
  <h1 style="font-size:clamp(2.2rem,4vw,3.2rem)"><?php echo $paid ? 'Thank you. Your order is in.' : 'One more step to complete your order.'; ?></h1>
  <p style="color:var(--ink-2)">Order <b><?php echo esc_html( $num ); ?></b><?php echo $paid ? ' is confirmed and queued for lab release. A receipt has been sent to <b>' . esc_html( $order->get_billing_email() ) . '</b>.' : ' is reserved. Complete the payment to release it to the lab-release queue.'; ?></p>

  <?php if ( $pending ) : ?>
   <div class="pay-box"><?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?></div>
  <?php endif; ?>

  <div class="order-box">
   <?php foreach ( $order->get_items() as $item ) : ?>
    <?php $p = $item->get_product(); $strength = $p ? (string) $p->get_meta( '_luma_strength' ) : ''; ?>
    <div class="coa-row"><span><?php echo esc_html( $item->get_quantity() ); ?>× <?php echo esc_html( $item->get_name() ); ?> <?php if ( $strength ) : ?><small style="color:var(--muted)"><?php echo esc_html( $strength ); ?></small><?php endif; ?></span><b><?php echo wp_kses_post( $money( $item->get_total() ) ); ?></b></div>
   <?php endforeach; ?>
   <div class="coa-row"><span>Subtotal</span><b><?php echo wp_kses_post( $money( $order->get_subtotal() ) ); ?></b></div>
   <?php if ( (float) $order->get_total_discount() > 0 ) : ?><div class="coa-row"><span>Volume discount</span><b class="pass">−<?php echo wp_kses_post( $money( $order->get_total_discount() ) ); ?></b></div><?php endif; ?>
   <?php if ( (float) $order->get_total_tax() > 0 ) : ?><div class="coa-row"><span>Sales tax</span><b><?php echo wp_kses_post( $money( $order->get_total_tax() ) ); ?></b></div><?php endif; ?>
   <div class="coa-row"><span>Shipping<?php echo $order->get_shipping_method() ? ' · ' . esc_html( $order->get_shipping_method() ) : ''; ?></span><b><?php echo (float) $order->get_shipping_total() > 0 ? wp_kses_post( $money( $order->get_shipping_total() ) ) : 'Free'; ?></b></div>
   <div class="coa-row"><span><b>Total</b> · <?php echo esc_html( $order->get_payment_method_title() ); ?><?php echo $paid ? '' : ', payment pending'; ?></span><b><?php echo wp_kses_post( $money( $order->get_total() ) ); ?></b></div>
   <?php if ( $ship ) : ?><p style="font-size:.85rem;color:var(--muted);margin:1rem 0 0"><b>Ships to:</b> <?php echo wp_kses_post( str_replace( '<br/>', ', ', $ship ) ); ?></p><?php endif; ?>
   <?php if ( $ack ) : ?><p style="font-size:.78rem;color:var(--muted);margin:.5rem 0 0">Research-use acknowledgement recorded <?php echo esc_html( substr( $ack, 0, 16 ) ); ?> UTC.</p><?php endif; ?>
   <div class="timeline"><div class="done">Order placed</div><div class="<?php echo $paid ? 'done' : ''; ?>">Payment received</div><div class="<?php echo $order->has_status( 'completed' ) ? 'done' : ''; ?>">Lab release</div><div class="<?php echo $order->has_status( 'completed' ) ? 'done' : ''; ?>">Shipped</div></div>
  </div>
  <p style="font-size:.8rem;color:var(--muted);margin-top:1.5rem">For laboratory research use only. Not for human or animal use. All sales final once shipped. Track this order any time from <a href="<?php echo esc_url( $u['orders'] ); ?>" style="color:var(--terra);text-decoration:underline">your account</a>.</p>
  <div style="display:flex;gap:.8rem;justify-content:center;margin-top:1rem;flex-wrap:wrap"><a class="btn btn-primary" href="<?php echo esc_url( $u['shop'] ); ?>">Back to catalog</a><a class="btn btn-outline" href="<?php echo esc_url( $u['verify'] ); ?>">Verify a lot</a></div>
 <?php endif; ?>
</div>
