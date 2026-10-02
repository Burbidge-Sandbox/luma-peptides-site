<?php
/**
 * Venmo (manual) — the stopgap rail named in CLAUDE.md. The order is placed
 * "On hold · awaiting payment"; the customer pays @handle with the order
 * number in the note; the owner confirms it on the Luma dashboard.
 * No card data, no API, nothing sensitive stored.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Venmo {
	const ID = 'luma_venmo';

	public static function init(): void {
		add_filter( 'woocommerce_payment_gateways', [ __CLASS__, 'register' ] );
		add_action( 'woocommerce_blocks_payment_method_type_registration', [ __CLASS__, 'register_blocks' ] );
		add_action( 'woocommerce_thankyou_' . self::ID, [ __CLASS__, 'thankyou' ] );
		add_action( 'woocommerce_email_before_order_table', [ __CLASS__, 'email' ], 5, 4 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_action( 'luma_venmo_expire', [ __CLASS__, 'expire' ] );
		add_action( 'init', function () {
			if ( ! wp_next_scheduled( 'luma_venmo_expire' ) ) {
				wp_schedule_event( time() + 300, 'hourly', 'luma_venmo_expire' );
			}
		} );
	}

	/** Cancel Venmo orders still unpaid after 48 hours; WooCommerce returns the stock on cancel. */
	public static function expire(): void {
		$stale = wc_get_orders( [
			'status'         => [ 'on-hold' ],
			'payment_method' => self::ID,
			'date_created'   => '<' . ( time() - 48 * HOUR_IN_SECONDS ),
			'limit'          => 50,
		] );
		foreach ( $stale as $order ) {
			$order->update_status( 'cancelled', 'Unpaid Venmo order cancelled automatically after 48 hours.' );
		}
	}

	/** Owner confirms a Venmo payment (Luma dashboard). */
	public static function mark_paid( \WC_Order $order ): void {
		$order->add_order_note( 'Venmo payment confirmed by ' . wp_get_current_user()->display_name . '.' );
		$order->payment_complete();
	}

	public static function register( array $gateways ): array {
		require_once LUMA_CORE_DIR . 'includes/class-venmo-gateway.php';
		$gateways[] = 'Luma\\Core\\VenmoGateway';
		return $gateways;
	}

	public static function register_blocks( $registry ): void {
		if ( ! class_exists( 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
			return;
		}
		require_once LUMA_CORE_DIR . 'includes/class-venmo-blocks.php';
		$registry->register( new VenmoBlocks() );
	}

	public static function settings(): array {
		return wp_parse_args( (array) get_option( 'woocommerce_' . self::ID . '_settings', [] ), [
			'enabled'     => 'yes',
			'title'       => 'Venmo',
			'description' => 'Place the order, then pay @LumaResearchCo on Venmo with your order number in the note. Orders are released once payment is received.',
			'handle'      => 'LumaResearchCo',
		] );
	}

	public static function handle(): string {
		return ltrim( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) self::settings()['handle'] ), '@' ) ?: 'LumaResearchCo';
	}

	/** Public URL for a file in this plugin, wherever luma-bootstrap loads it from. */
	public static function asset_url( string $rel ): string {
		return str_replace( wp_normalize_path( WP_CONTENT_DIR ), content_url(), wp_normalize_path( LUMA_CORE_DIR . $rel ) );
	}

	public static function pay_url( \WC_Order $order ): string {
		return add_query_arg(
			[ 'txn' => 'pay', 'amount' => wc_format_decimal( $order->get_total(), 2 ), 'note' => 'Order #' . $order->get_order_number() ],
			'https://venmo.com/' . rawurlencode( self::handle() )
		);
	}

	public static function assets(): void {
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			wp_enqueue_script( 'qrcode-generator', 'https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js', [], '1.4.4', true );
		}
	}

	/** Pay box on the order-received page (theme renders it inside .pay-box while the order is unpaid). */
	public static function thankyou( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->is_paid() ) {
			return;
		}
		$h    = self::handle();
		$url  = self::pay_url( $order );
		$note = 'Order #' . $order->get_order_number();
		?>
		<div class="pay-step"><span class="step-n">1</span><div>
			<b>Pay <?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?> on Venmo</b>
			<p>Send to <b>@<?php echo esc_html( $h ); ?></b> and put <b><?php echo esc_html( $note ); ?></b> in the note so the payment is matched to this order.</p>
			<div class="pay-row">
				<div><a class="btn btn-primary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><span class="venmo-mark">venmo</span>&nbsp; Open Venmo</a><p class="pay-qr-hint">On a computer? Scan the code with your phone.</p></div>
				<div class="pay-qr" id="lumaVenmoQr" data-url="<?php echo esc_attr( $url ); ?>" aria-label="QR code to pay on Venmo"></div>
			</div>
		</div></div>
		<div class="pay-step"><span class="step-n">2</span><div>
			<b>We confirm and release your order</b>
			<p>Payments are matched by order number, usually within a few business hours. You'll get an email when it's confirmed, and again when it ships.</p>
		</div></div>
		<p class="pay-fine">Unpaid orders are held for 48 hours, then cancelled. Questions: email info@lumaresearchco.com or text (385) 521-5259.</p>
		<script>window.addEventListener('load',function(){var el=document.getElementById('lumaVenmoQr');if(!el)return;try{var q=qrcode(0,'M');q.addData(el.dataset.url);q.make();el.innerHTML=q.createSvgTag({cellSize:3,margin:2,scalable:true});}catch(e){el.remove();}});</script>
		<?php
	}

	/** Payment instructions at the top of the customer's order email while unpaid. */
	public static function email( $order, $sent_to_admin, $plain_text, $email ): void {
		if ( ! $order instanceof \WC_Order || $sent_to_admin || self::ID !== $order->get_payment_method() || $order->is_paid() || ! $order->has_status( [ 'on-hold', 'pending' ] ) ) {
			return;
		}
		$h    = self::handle();
		$url  = self::pay_url( $order );
		$note = 'Order #' . $order->get_order_number();
		$amt  = wp_strip_all_tags( wc_price( $order->get_total() ) );
		if ( $plain_text ) {
			echo "\nTO COMPLETE YOUR ORDER\nPay {$amt} to @{$h} on Venmo with \"{$note}\" in the note:\n{$url}\nUnpaid orders are held for 48 hours.\n\n";
			return;
		}
		echo '<div style="border:1.5px solid #B4432C;border-radius:10px;padding:16px 18px;margin:0 0 20px">';
		echo '<p style="margin:0 0 6px;font-weight:700;font-size:16px">To complete your order, pay ' . esc_html( $amt ) . ' on Venmo</p>';
		echo '<p style="margin:0 0 12px">Send to <b>@' . esc_html( $h ) . '</b> with <b>' . esc_html( $note ) . '</b> in the note so it is matched to this order.</p>';
		echo '<p style="margin:0 0 10px"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#008CFF;color:#fff;text-decoration:none;font-weight:700;padding:10px 18px;border-radius:6px">Pay with Venmo</a></p>';
		echo '<p style="margin:0;color:#6e625b;font-size:13px">Unpaid orders are held for 48 hours, then cancelled.</p></div>';
	}
}
