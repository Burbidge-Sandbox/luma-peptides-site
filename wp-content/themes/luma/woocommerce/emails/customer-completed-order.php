<?php
/**
 * Customer "order shipped" email (WooCommerce: customer-completed-order).
 *
 * Luma override: leads with the carrier, tracking number and a Track button
 * instead of Woo's default "We have finished processing your order".
 * Tracking comes from luma-core (Fulfillment::tracking), which ShipStation fills
 * when the label is bought. Falls back to a plain "shipped" line without it.
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $additional_content
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

$luma_track = class_exists( '\\Luma\\Core\\Fulfillment' ) ? \Luma\Core\Fulfillment::tracking( $order ) : null;
if ( class_exists( '\\Luma\\Core\\Fulfillment' ) ) {
	\Luma\Core\Fulfillment::$tracking_in_intro = false; // reset per email (bulk status changes send several in one request)
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php printf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $order->get_billing_first_name() ) ); ?></p>

<?php if ( $luma_track ) : ?>
	<?php \Luma\Core\Fulfillment::$tracking_in_intro = true; ?>
	<p>Your order shipped via <?php echo esc_html( $luma_track['carrier'] ); ?>.</p>
	<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 22px;border:1px solid #E6DCD2;border-radius:10px;background:#F7F2EC">
		<tr>
			<td style="padding:16px 18px">
				<p style="margin:0 0 4px;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#6e625b;font-weight:600"><?php echo esc_html( $luma_track['carrier'] ); ?> tracking number</p>
				<p style="margin:0 0 14px;font-size:17px;color:#2A2523;font-family:Menlo,Consolas,monospace;letter-spacing:.02em"><?php echo esc_html( $luma_track['number'] ); ?></p>
				<?php if ( $luma_track['url'] ) : ?>
					<a class="luma-btn" href="<?php echo esc_url( $luma_track['url'] ); ?>" style="display:inline-block;background:#B4432C;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;font-weight:600">Track your package</a>
				<?php endif; ?>
			</td>
		</tr>
	</table>
<?php else : ?>
	<p>Your order has shipped. You can follow it from your account at any time.</p>
<?php endif; ?>

<p>Here’s what’s in the package:</p>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
