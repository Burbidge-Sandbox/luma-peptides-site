<?php
/**
 * Phone alerts for new orders via Pushover (pushover.net).
 *
 * Fires once per order when it first reaches Processing (paid) or On hold
 * (Venmo awaiting payment). Keys live in the database (WooCommerce → Order
 * alerts), never in the repo. Default priority is Emergency: the alert
 * repeats on the phone until someone taps Acknowledge, and it breaks through
 * Do Not Disturb / quiet hours.
 */

namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class OrderAlerts {

	const OPTION = 'luma_order_alerts';
	const SENT   = '_luma_phone_alert_sent';
	const API    = 'https://api.pushover.net/1/messages.json';

	public static function init(): void {
		add_action( 'woocommerce_order_status_changed', [ __CLASS__, 'on_status' ], 20, 4 );
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 60 );
		add_action( 'admin_init', fn() => register_setting( 'luma_order_alerts', self::OPTION, [ 'type' => 'array', 'sanitize_callback' => [ __CLASS__, 'sanitize' ] ] ) );
		add_action( 'admin_post_luma_order_alert_test', [ __CLASS__, 'send_test' ] );
	}

	public static function opts(): array {
		return wp_parse_args( (array) get_option( self::OPTION, [] ), [
			'enabled'  => '0',
			'token'    => '',
			'users'    => '',
			'priority' => '2',
			'sound'    => 'cashregister',
		] );
	}

	public static function sanitize( $in ): array {
		$in    = (array) $in;
		$users = preg_split( '/[\s,]+/', (string) ( $in['users'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
		return [
			'enabled'  => empty( $in['enabled'] ) ? '0' : '1',
			'token'    => preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $in['token'] ?? '' ) ),
			'users'    => implode( "\n", array_map( fn( $u ) => preg_replace( '/[^A-Za-z0-9]/', '', $u ), $users ) ),
			'priority' => in_array( (string) ( $in['priority'] ?? '2' ), [ '0', '1', '2' ], true ) ? (string) $in['priority'] : '2',
			'sound'    => sanitize_key( $in['sound'] ?? 'cashregister' ) ?: 'cashregister',
		];
	}

	/* ---------- Trigger ---------- */

	public static function on_status( $order_id, $from, $to, $order ): void {
		if ( ! in_array( $to, [ 'processing', 'on-hold' ], true ) ) {
			return;
		}
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::SENT ) ) {
			return;
		}
		$o = self::opts();
		if ( '1' !== $o['enabled'] || ! $o['token'] || ! $o['users'] ) {
			return;
		}
		[ $title, $msg ] = self::compose( $order, $to );
		$ok = self::push( $title, $msg, $order->get_edit_order_url(), 'Open order' );
		$order->update_meta_data( self::SENT, $ok ? gmdate( 'c' ) : '' );
		$order->add_order_note( $ok ? 'Phone alert sent (Pushover).' : 'Phone alert FAILED (Pushover) — check WooCommerce → Order alerts.' );
		$order->save();
	}

	public static function compose( \WC_Order $order, string $to ): array {
		$total  = html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ) ) );
		$title  = 'New order #' . $order->get_order_number() . ' · ' . $total;
		$lines  = [];
		foreach ( $order->get_items() as $item ) {
			$lines[] = $item->get_quantity() . '× ' . $item->get_name();
		}
		$ship   = $order->get_shipping_method();
		$where  = trim( $order->get_shipping_city() . ', ' . $order->get_shipping_state(), ', ' );
		$who    = trim( $order->get_formatted_shipping_full_name() ?: $order->get_formatted_billing_full_name() );
		$lines[] = '';
		$lines[] = 'Ship: ' . $who . ( $where ? ' — ' . $where : '' );
		if ( $ship ) {
			$lines[] = 'Method: ' . $ship . ( false !== stripos( $ship, 'same' ) ? ' ⚡' : '' );
		}
		$lines[] = 'Payment: ' . $order->get_payment_method_title() . ( 'on-hold' === $to ? ' — AWAITING PAYMENT' : '' );
		return [ $title, implode( "\n", $lines ) ];
	}

	public static function push( string $title, string $msg, string $url = '', string $url_title = '' ): bool {
		$o  = self::opts();
		$ok = false;
		foreach ( preg_split( '/\s+/', $o['users'], -1, PREG_SPLIT_NO_EMPTY ) as $user ) {
			$body = [
				'token'     => $o['token'],
				'user'      => $user,
				'title'     => $title,
				'message'   => $msg,
				'priority'  => (int) $o['priority'],
				'sound'     => $o['sound'],
				'url'       => $url,
				'url_title' => $url_title,
			];
			if ( 2 === (int) $o['priority'] ) {
				$body['retry']  = 60;   // repeat every minute…
				$body['expire'] = 3600; // …for up to an hour, until acknowledged
			}
			$res = wp_remote_post( self::API, [ 'timeout' => 8, 'body' => $body ] );
			$ok  = ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) || $ok;
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				error_log( 'Luma order alert failed: ' . ( is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_body( $res ) ) ); // phpcs:ignore
			}
		}
		return $ok;
	}

	/* ---------- Admin ---------- */

	public static function menu(): void {
		add_submenu_page( 'woocommerce', 'Order alerts', 'Order alerts', 'manage_woocommerce', 'luma-order-alerts', [ __CLASS__, 'page' ] );
	}

	public static function send_test(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'luma_order_alert_test' ) ) {
			wp_die( 'Not allowed.' );
		}
		$ok = self::push( 'Test alert · Luma', "This is how a new order will reach your phone.\nTap Acknowledge to stop the repeat.", admin_url( 'admin.php?page=luma-order-alerts' ), 'Order alerts' );
		wp_safe_redirect( add_query_arg( 'luma_test', $ok ? 'ok' : 'fail', admin_url( 'admin.php?page=luma-order-alerts' ) ) );
		exit;
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$o = self::opts();
		$n = esc_attr( self::OPTION );
		?>
		<div class="wrap">
			<h1>Order alerts</h1>
			<?php if ( isset( $_GET['luma_test'] ) ) : // phpcs:ignore ?>
				<div class="notice notice-<?php echo 'ok' === $_GET['luma_test'] ? 'success' : 'error'; // phpcs:ignore ?>"><p><?php echo 'ok' === $_GET['luma_test'] ? 'Test alert sent — check your phone.' : 'Test failed. Check the API token and user key, then save and try again.'; // phpcs:ignore ?></p></div>
			<?php endif; ?>
			<p>Sends a phone alert through <a href="https://pushover.net" target="_blank" rel="noopener">Pushover</a> the first time each order is paid (Processing) or placed with Venmo (On hold). Each order gets an order note saying whether the alert went out.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'luma_order_alerts' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row">Enabled</th><td><label><input type="checkbox" name="<?php echo $n; ?>[enabled]" value="1" <?php checked( '1', $o['enabled'] ); ?>> Send a phone alert for every new order</label></td></tr>
					<tr><th scope="row"><label for="la_token">Application API token</label></th><td><input type="password" id="la_token" name="<?php echo $n; ?>[token]" value="<?php echo esc_attr( $o['token'] ); ?>" class="regular-text" autocomplete="off"><p class="description">pushover.net → Your Applications → Create an Application ("Luma Orders").</p></td></tr>
					<tr><th scope="row"><label for="la_users">User key(s)</label></th><td><textarea id="la_users" name="<?php echo $n; ?>[users]" rows="2" class="regular-text"><?php echo esc_textarea( $o['users'] ); ?></textarea><p class="description">Shown at the top of pushover.net after you log in. One per line to alert more than one person.</p></td></tr>
					<tr><th scope="row"><label for="la_pri">Priority</label></th><td><select id="la_pri" name="<?php echo $n; ?>[priority]">
						<option value="2" <?php selected( '2', $o['priority'] ); ?>>Emergency — repeats every minute until acknowledged, bypasses Do Not Disturb</option>
						<option value="1" <?php selected( '1', $o['priority'] ); ?>>High — one alert, bypasses quiet hours</option>
						<option value="0" <?php selected( '0', $o['priority'] ); ?>>Normal — one alert</option>
					</select></td></tr>
					<tr><th scope="row"><label for="la_sound">Sound</label></th><td><input type="text" id="la_sound" name="<?php echo $n; ?>[sound]" value="<?php echo esc_attr( $o['sound'] ); ?>" class="regular-text"><p class="description">Pushover sound name, e.g. cashregister, siren, persistent.</p></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="luma_order_alert_test"><?php wp_nonce_field( 'luma_order_alert_test' ); ?>
				<?php submit_button( 'Send test alert', 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
