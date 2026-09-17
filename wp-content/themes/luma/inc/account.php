<?php
/**
 * My Account helpers — legacy account.html look on top of WooCommerce data.
 */
defined( 'ABSPATH' ) || exit;

/* No downloadable products; keep the menu to what the legacy page offered. */
add_filter( 'woocommerce_account_menu_items', function ( array $items ): array {
	unset( $items['downloads'] );
	if ( isset( $items['dashboard'] ) ) {
		$items['dashboard'] = 'Overview';
	}
	if ( isset( $items['edit-address'] ) ) {
		$items['edit-address'] = 'Addresses';
	}
	if ( isset( $items['edit-account'] ) ) {
		$items['edit-account'] = 'Details';
	}
	if ( isset( $items['customer-logout'] ) ) {
		$items['customer-logout'] = 'Sign out';
	}
	return $items;
} );

/** Page-head copy for the account page (legacy #acctTitle / #acctLede). */
function luma_account_head(): array {
	if ( ! is_user_logged_in() ) {
		return [ 'Your orders and documents', 'Sign in, or create an account, to see order history, tracking and certificates of analysis for every lot you have received.' ];
	}
	if ( is_wc_endpoint_url( 'view-order' ) ) {
		return [ 'Order details', '' ];
	}
	return [ 'Welcome back.', '' ];
}

/** Lot numbers attached to an order's line items (set at shipment; falls back to nothing). */
function luma_order_lots( WC_Order $order ): array {
	$lots = [];
	foreach ( $order->get_items() as $item ) {
		$l = (string) $item->get_meta( '_luma_lot' );
		if ( $l ) {
			$lots[] = $l;
		}
	}
	return array_values( array_unique( $lots ) );
}

/** Carrier + number + URL, or null. */
function luma_order_tracking( WC_Order $order ): ?array {
	return class_exists( 'Luma\\Core\\Fulfillment' ) ? Luma\Core\Fulfillment::tracking( $order ) : null;
}

function luma_order_pill_class( WC_Order $order ): string {
	$s = $order->get_status();
	$map = [
		'pending'    => 'awaiting_payment',
		'on-hold'    => 'awaiting_payment',
		'processing' => 'paid',
		'completed'  => 'shipped',
		'cancelled'  => 'cancelled',
		'refunded'   => 'cancelled',
		'failed'     => 'cancelled',
	];
	return 'pill pill-' . ( $map[ $s ] ?? $s );
}

function luma_order_pill_label( WC_Order $order ): string {
	$map = [
		'pending'    => 'Awaiting payment',
		'on-hold'    => 'Awaiting payment',
		'processing' => 'Paid · lab release',
		'completed'  => 'Shipped',
		'cancelled'  => 'Cancelled',
		'refunded'   => 'Refunded',
		'failed'     => 'Payment failed',
	];
	return $map[ $order->get_status() ] ?? wc_get_order_status_name( $order->get_status() );
}

/** Orders section (dashboard uses a short list; the orders endpoint pages). */
function luma_account_orders_section( int $page = 1, int $limit = 0 ): void {
	$args = [
		'customer' => get_current_user_id(),
		'page'     => $page,
		'paginate' => true,
		'limit'    => $limit ?: 10,
	];
	$orders = wc_get_orders( apply_filters( 'woocommerce_my_account_my_orders_query', $args ) );
	luma_account_render_orders( $orders, 0 < $orders->total, $page );
}

function luma_account_render_orders( $customer_orders, bool $has_orders, int $current_page ): void {
	$u = luma_catalogue_json()['config']['urls'];
	echo '<section class="acct-sec" style="margin-top:0"><div class="acct-head"><h2>Orders</h2><a class="text-link" href="' . esc_url( $u['shop'] ) . '">Browse the catalog →</a></div>';
	if ( $has_orders ) {
		foreach ( $customer_orders->orders as $order ) {
			$order = wc_get_order( $order );
			$items = [];
			foreach ( $order->get_items() as $item ) {
				$items[] = $item->get_quantity() . '× ' . $item->get_name();
			}
			$lots = luma_order_lots( $order );
			?>
			<div class="acct-order">
				<div class="acct-order-top"><div><b>Order <?php echo esc_html( $order->get_order_number() ); ?></b><span class="acct-date"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></span></div><span class="<?php echo esc_attr( luma_order_pill_class( $order ) ); ?>"><?php echo esc_html( luma_order_pill_label( $order ) ); ?></span></div>
				<p class="acct-items"><?php echo esc_html( implode( ' · ', $items ) ); ?></p>
				<?php $trk = luma_order_tracking( $order ); ?>
				<div class="acct-order-meta"><span><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span><?php if ( $trk ) : ?><span>Tracking <?php echo $trk['url'] ? '<a href="' . esc_url( $trk['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $trk['carrier'] . ' ' . $trk['number'] ) . '</a>' : esc_html( $trk['carrier'] . ' ' . $trk['number'] ); ?></span><?php endif; ?><?php if ( $lots ) : ?><span>Lots <?php foreach ( $lots as $i => $l ) : ?><?php echo $i ? ', ' : ''; ?><a href="<?php echo esc_url( add_query_arg( 'lot', rawurlencode( $l ), $u['verify'] ) ); ?>"><?php echo esc_html( $l ); ?></a><?php endforeach; ?></span><?php endif; ?></div>
				<div class="acct-actions">
					<?php foreach ( wc_get_account_orders_actions( $order ) as $key => $a ) : ?>
						<a class="btn <?php echo 'pay' === $key ? 'btn-primary' : ( 'view' === $key ? 'btn-outline' : 'btn-ghost' ); ?> btn-sm" href="<?php echo esc_url( $a['url'] ); ?>"><?php echo esc_html( $a['name'] ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
		}
		if ( 1 < $customer_orders->max_num_pages ) {
			echo '<div class="acct-actions" style="justify-content:space-between">';
			echo 1 !== $current_page ? '<a class="btn btn-outline btn-sm" href="' . esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ) . '">← Newer</a>' : '<span></span>';
			echo (int) $customer_orders->max_num_pages !== $current_page ? '<a class="btn btn-outline btn-sm" href="' . esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ) . '">Older →</a>' : '';
			echo '</div>';
		}
	} else {
		echo '<div class="acct-card"><p style="margin:0;color:var(--ink-2)">No orders yet under this email.</p></div>';
	}
	echo '</section>';
}

/** Certificates section: every lot across the customer's orders. */
function luma_account_certificates_section(): void {
	$u    = luma_catalogue_json()['config']['urls'];
	$lots = [];
	foreach ( wc_get_orders( [ 'customer' => get_current_user_id(), 'limit' => 50 ] ) as $order ) {
		$lots = array_merge( $lots, luma_order_lots( $order ) );
	}
	$lots = array_values( array_unique( $lots ) );
	echo '<section class="acct-sec"><div class="acct-head"><h2>Your certificates</h2></div>';
	if ( $lots ) {
		echo '<div class="acct-card"><p style="margin:0 0 .6rem;color:var(--ink-2);font-size:.92rem">Independent laboratory certificates for every lot you have received.</p><div class="lot-list">';
		foreach ( $lots as $l ) {
			echo '<a class="chip" href="' . esc_url( add_query_arg( 'lot', rawurlencode( $l ), $u['verify'] ) ) . '">' . esc_html( $l ) . ' ↗</a>';
		}
		echo '</div></div>';
	} else {
		echo '<div class="acct-card"><p style="margin:0;color:var(--ink-2)">Lot numbers are recorded when an order ships. Certificates for your lots will appear here, and every vial carries its lot number for <a href="' . esc_url( $u['verify'] ) . '" style="color:var(--terra);text-decoration:underline">lookup</a>.</p></div>';
	}
	echo '</section>';
}

/* Don't reveal whether an email exists: one neutral message for any bad email/password combination. */
add_filter( 'authenticate', function ( $user ) {
	if ( is_wp_error( $user ) && in_array( $user->get_error_code(), [ 'invalid_email', 'invalid_username', 'incorrect_password' ], true ) ) {
		return new WP_Error( 'luma_login', 'That email and password don\'t match our records. Check them and try again, or use "Lost your password?" below.' );
	}
	return $user;
}, 99 );
