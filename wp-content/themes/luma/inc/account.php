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

/** Meta strip (Option A) — replaces the old right-hand sidebar. */
function luma_account_meta_strip(): void {
	$user     = wp_get_current_user();
	$customer = new WC_Customer( $user->ID );
	$ship     = array_filter( [ $customer->get_shipping_address_1(), $customer->get_shipping_city(), trim( $customer->get_shipping_state() . ' ' . $customer->get_shipping_postcode() ) ] );
	$count    = wc_get_customer_order_count( $user->ID );
	?>
	<div class="acct-meta">
		<div class="acct-meta-cell">
			<span class="acct-meta-k">Signed in as</span>
			<span class="acct-meta-v"><?php echo esc_html( $user->user_email ); ?></span>
		</div>
		<div class="acct-meta-cell">
			<span class="acct-meta-k">Ships to</span>
			<span class="acct-meta-v"><?php echo $ship ? esc_html( implode( ', ', $ship ) ) : '<span class="muted">No address saved yet</span>'; ?></span>
		</div>
		<div class="acct-meta-cell acct-meta-count">
			<span class="acct-meta-k">Orders</span>
			<span class="acct-meta-v acct-num"><?php echo esc_html( $count ); ?></span>
		</div>
		<div class="acct-meta-links">
			<a class="chip" href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>">Addresses</a>
			<a class="chip" href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">Details</a>
			<a class="chip chip-out" href="<?php echo esc_url( wc_logout_url() ); ?>">Sign out</a>
		</div>
	</div>
	<?php
}

/** Certificates + help, side by side under the ledger. */
function luma_account_bottom_row(): void {
	$u    = luma_catalogue_json()['config']['urls'];
	$lots = [];
	foreach ( wc_get_orders( [ 'customer' => get_current_user_id(), 'limit' => 50 ] ) as $order ) {
		$lots = array_merge( $lots, luma_order_lots( $order ) );
	}
	$lots = array_values( array_unique( $lots ) );
	?>
	<div class="acct-bottom">
		<section class="acct-card acct-certs">
			<h2>Your certificates</h2>
			<?php if ( $lots ) : ?>
				<p>Independent laboratory certificates for every lot you have received.</p>
				<div class="lot-list">
					<?php foreach ( $lots as $l ) : ?>
						<a class="chip" href="<?php echo esc_url( add_query_arg( 'lot', rawurlencode( $l ), $u['verify'] ) ); ?>"><?php echo esc_html( $l ); ?> ↗</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p>Lot numbers are recorded when an order ships. Certificates for your lots will appear here, and every vial carries its lot number for <a href="<?php echo esc_url( $u['verify'] ); ?>">lookup</a>.</p>
				<span class="acct-lot-empty">No lots on file yet</span>
			<?php endif; ?>
		</section>
		<section class="acct-card acct-help">
			<h2>Need a hand?</h2>
			<p>Questions about an order, a lot or a certificate — email <a href="mailto:<?php echo esc_attr( luma_owner_email() ); ?>"><?php echo esc_html( luma_owner_email() ); ?></a> or text <a href="sms:+13855215259">(385) 521-5259</a>. We reply within one business day.</p>
			<div class="acct-side-links">
				<a href="<?php echo esc_url( $u['shop'] ); ?>">Browse the catalog →</a>
				<a href="<?php echo esc_url( $u['verify'] ); ?>">Verify a lot →</a>
				<a href="<?php echo esc_url( $u['shipping'] ?? home_url( '/shipping-returns/' ) ); ?>">Shipping &amp; returns →</a>
				<a href="<?php echo esc_url( $u['faq'] ); ?>">FAQ →</a>
			</div>
		</section>
	</div>
	<?php
}

/** Orders section (dashboard shows a short list; the orders endpoint pages). */
function luma_account_orders_section( int $page = 1, int $limit = 0, bool $is_dashboard = false ): void {
	$args = [
		'customer' => get_current_user_id(),
		'page'     => $page,
		'paginate' => true,
		'limit'    => $limit ?: 10,
	];
	$orders = wc_get_orders( apply_filters( 'woocommerce_my_account_my_orders_query', $args ) );
	luma_account_render_orders( $orders, 0 < $orders->total, $page, $is_dashboard );
}

/** The ledger: one full-width row per order. */
function luma_account_render_orders( $customer_orders, bool $has_orders, int $current_page, bool $is_dashboard = false ): void {
	$u = luma_catalogue_json()['config']['urls'];
	?>
	<section class="acct-sec acct-sec-orders">
		<div class="acct-head">
			<h2>Orders</h2>
			<span class="acct-head-note">Most recent first</span>
			<span class="acct-head-links">
				<?php if ( $is_dashboard && $has_orders ) : ?>
					<a class="text-link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">All orders →</a>
				<?php endif; ?>
				<a class="text-link" href="<?php echo esc_url( $u['shop'] ); ?>">Browse the catalog →</a>
			</span>
		</div>

		<?php if ( $has_orders ) : ?>
			<div class="acct-ledger">
				<div class="acct-ledger-head">
					<span>Order</span><span>Date</span><span>Items</span><span>Status</span><span class="num">Total</span><span></span>
				</div>
				<?php foreach ( $customer_orders->orders as $order ) :
					$order = wc_get_order( $order );
					$lots  = luma_order_lots( $order );
					$trk   = luma_order_tracking( $order );
					$view  = $order->get_view_order_url();
					?>
					<article class="acct-ledger-row">
						<span class="lg-id" data-k="Order"><a href="<?php echo esc_url( $view ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></span>
						<span class="lg-date" data-k="Date"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></span>
						<span class="lg-items" data-k="Items">
							<?php foreach ( $order->get_items() as $item ) : ?>
								<span class="lg-item"><span class="qty"><?php echo esc_html( $item->get_quantity() ); ?>×</span> <?php echo esc_html( $item->get_name() ); ?></span>
							<?php endforeach; ?>
							<?php if ( $trk || $lots ) : ?>
								<span class="lg-sub">
									<?php if ( $trk ) : ?>
										<?php echo $trk['url'] ? '<a href="' . esc_url( $trk['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $trk['carrier'] . ' ' . $trk['number'] ) . '</a>' : esc_html( $trk['carrier'] . ' ' . $trk['number'] ); ?>
									<?php endif; ?>
									<?php if ( $lots ) : ?>
										<?php echo $trk ? ' · ' : ''; ?>Lot <?php foreach ( $lots as $i => $l ) : ?><?php echo $i ? ', ' : ''; ?><a href="<?php echo esc_url( add_query_arg( 'lot', rawurlencode( $l ), $u['verify'] ) ); ?>"><?php echo esc_html( $l ); ?></a><?php endforeach; ?>
									<?php endif; ?>
								</span>
							<?php endif; ?>
						</span>
						<span class="lg-status" data-k="Status"><span class="<?php echo esc_attr( luma_order_pill_class( $order ) ); ?>"><?php echo esc_html( luma_order_pill_label( $order ) ); ?></span></span>
						<span class="lg-total num" data-k="Total"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
						<span class="lg-act">
							<?php
							$actions = wc_get_account_orders_actions( $order );
							if ( isset( $actions['pay'] ) ) :
								?>
								<a class="btn btn-primary btn-sm" href="<?php echo esc_url( $actions['pay']['url'] ); ?>">Pay</a>
							<?php endif; ?>
							<a class="btn btn-outline btn-sm" href="<?php echo esc_url( $view ); ?>">View</a>
						</span>
					</article>
				<?php endforeach; ?>
			</div>

			<?php if ( ! $is_dashboard && 1 < $customer_orders->max_num_pages ) : ?>
				<div class="acct-actions acct-pager">
					<?php echo 1 !== $current_page ? '<a class="btn btn-outline btn-sm" href="' . esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ) . '">← Newer</a>' : '<span></span>'; ?>
					<?php echo (int) $customer_orders->max_num_pages !== $current_page ? '<a class="btn btn-outline btn-sm" href="' . esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ) . '">Older →</a>' : ''; ?>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<div class="acct-card acct-empty">
				<p>No orders yet under this email. Every lot is independently tested by an outside laboratory, and the certificate is published before the lot ships.</p>
				<a class="btn btn-primary btn-sm" href="<?php echo esc_url( $u['shop'] ); ?>">Browse the catalog</a>
			</div>
		<?php endif; ?>
	</section>
	<?php
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
