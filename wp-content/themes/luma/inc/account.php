<?php
/**
 * My Account helpers — legacy account.html look on top of WooCommerce data.
 */
defined( 'ABSPATH' ) || exit;

/* No downloadable products; keep the menu to what the legacy page offered. */
add_filter( 'woocommerce_account_menu_items', function ( array $items ): array {
	unset( $items['downloads'], $items['payment-methods'] ); // no saved-card rail yet; Venmo is manual
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
	$first = wp_get_current_user()->first_name;
	return [ $first ? 'Welcome back, ' . $first . '.' : 'Welcome back.', '' ];
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

/** Sidebar shared by the overview and the orders endpoint. */
function luma_account_sidebar(): void {
	$user     = wp_get_current_user();
	$u        = luma_catalogue_json()['config']['urls'];
	$customer = new WC_Customer( $user->ID );
	$ship     = array_filter( [ $customer->get_shipping_address_1(), $customer->get_shipping_city(), trim( $customer->get_shipping_state() . ' ' . $customer->get_shipping_postcode() ) ] );
	$count    = wc_get_customer_order_count( $user->ID );
	?>
	<aside class="acct-side">
		<section class="acct-card acct-side-card">
			<h3>Your account</h3>
			<dl class="acct-kv">
				<div><dt>Email</dt><dd><?php echo esc_html( $user->user_email ); ?></dd></div>
				<div><dt>Shipping to</dt><dd><?php echo $ship ? esc_html( implode( ', ', $ship ) ) : '<span class="muted">No address saved yet</span>'; ?></dd></div>
				<div><dt>Orders</dt><dd><?php echo esc_html( $count ); ?></dd></div>
			</dl>
			<div class="acct-side-links">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>">Addresses →</a>
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">Details &amp; password →</a>
				<a href="<?php echo esc_url( wc_logout_url() ); ?>">Sign out</a>
			</div>
		</section>
		<section class="acct-card acct-side-card">
			<h3>Quick links</h3>
			<div class="acct-side-links">
				<a href="<?php echo esc_url( $u['shop'] ); ?>">Browse the catalog →</a>
				<a href="<?php echo esc_url( $u['verify'] ); ?>">Verify a lot →</a>
				<a href="<?php echo esc_url( $u['shipping'] ?? home_url( '/shipping-returns/' ) ); ?>">Shipping &amp; returns →</a>
				<a href="<?php echo esc_url( $u['faq'] ); ?>">FAQ →</a>
			</div>
		</section>
		<section class="acct-card acct-side-card acct-help">
			<h3>Need a hand?</h3>
			<p>Questions about an order, a lot or a certificate — email <a href="mailto:<?php echo esc_attr( luma_owner_email() ); ?>"><?php echo esc_html( luma_owner_email() ); ?></a> or text <a href="sms:+13855215259">(385) 521-5259</a>. We reply within one business day.</p>
			<p class="acct-fine" style="margin:.8rem 0 0">Free next-day shipping on every order · same-day in Utah County before 12:00 pm MT.</p>
		</section>
	</aside>
	<?php
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
			$lots = luma_order_lots( $order );
			?>
			<article class="acct-order">
				<header class="acct-order-top"><div><b>Order <?php echo esc_html( $order->get_order_number() ); ?></b><span class="acct-date"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></span></div><span class="<?php echo esc_attr( luma_order_pill_class( $order ) ); ?>"><?php echo esc_html( luma_order_pill_label( $order ) ); ?></span></header>
				<ul class="acct-order-items">
					<?php foreach ( $order->get_items() as $item ) : ?>
						<li><span class="qty"><?php echo esc_html( $item->get_quantity() ); ?>×</span><span class="name"><?php echo esc_html( $item->get_name() ); ?></span><span class="amt"><?php echo wp_kses_post( wc_price( (float) $item->get_total(), [ 'currency' => $order->get_currency() ] ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
				<?php $trk = luma_order_tracking( $order ); if ( $trk || $lots ) : ?>
				<div class="acct-order-meta"><?php if ( $trk ) : ?><span>Tracking <?php echo $trk['url'] ? '<a href="' . esc_url( $trk['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $trk['carrier'] . ' ' . $trk['number'] ) . '</a>' : esc_html( $trk['carrier'] . ' ' . $trk['number'] ); ?></span><?php endif; ?><?php if ( $lots ) : ?><span>Lots <?php foreach ( $lots as $i => $l ) : ?><?php echo $i ? ', ' : ''; ?><a href="<?php echo esc_url( add_query_arg( 'lot', rawurlencode( $l ), $u['verify'] ) ); ?>"><?php echo esc_html( $l ); ?></a><?php endforeach; ?></span><?php endif; ?></div>
				<?php endif; ?>
				<footer class="acct-order-foot">
					<div class="acct-order-total"><small>Total</small><b><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></b></div>
					<div class="acct-actions">
						<?php foreach ( wc_get_account_orders_actions( $order ) as $key => $a ) : ?>
							<a class="btn <?php echo 'pay' === $key ? 'btn-primary' : ( 'view' === $key ? 'btn-outline' : 'btn-ghost' ); ?> btn-sm" href="<?php echo esc_url( $a['url'] ); ?>"><?php echo esc_html( 'view' === $key ? 'View order' : $a['name'] ); ?></a>
						<?php endforeach; ?>
					</div>
				</footer>
			</article>
			<?php
		}
		if ( 1 < $customer_orders->max_num_pages ) {
			echo '<div class="acct-actions" style="justify-content:space-between">';
			echo 1 !== $current_page ? '<a class="btn btn-outline btn-sm" href="' . esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ) . '">← Newer</a>' : '<span></span>';
			echo (int) $customer_orders->max_num_pages !== $current_page ? '<a class="btn btn-outline btn-sm" href="' . esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ) . '">Older →</a>' : '';
			echo '</div>';
		}
	} else {
		echo '<div class="acct-card acct-empty"><p>No orders yet under this email. Every lot is independently tested and ships free next-day.</p><a class="btn btn-primary btn-sm" href="' . esc_url( $u['shop'] ) . '">Browse the catalog</a></div>';
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

/* Password bar: "medium" (2) rather than Woo's default "strong" (3); the meter still shows and the button greys out when too weak. */
add_filter( 'woocommerce_min_password_strength', fn() => 2 );
