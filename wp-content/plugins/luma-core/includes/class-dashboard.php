<?php
/**
 * The Luma dashboard: the four daily jobs on one screen — orders to ship,
 * orders in transit, lots on hand, and a link to receive a batch. Everything
 * else in wp-admin is hidden behind a "Full admin" switch.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Dashboard {
	const PAGE = 'luma';
	const META = 'luma_full_admin';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 5 );
		add_action( 'admin_menu', [ __CLASS__, 'simplify_menu' ], 999 );
		add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar' ], 100 );
		add_action( 'admin_post_luma_ship', [ __CLASS__, 'handle_ship' ] );
		add_action( 'admin_post_luma_delivered', [ __CLASS__, 'handle_delivered' ] );
		add_action( 'admin_post_luma_toggle_admin', [ __CLASS__, 'handle_toggle' ] );
		add_filter( 'login_redirect', [ __CLASS__, 'login_redirect' ], 10, 3 );
		add_action( 'admin_init', [ __CLASS__, 'land_here' ] );
		add_action( 'admin_head', [ __CLASS__, 'styles' ] );
	}

	public static function full_admin(): bool {
		return (bool) get_user_meta( get_current_user_id(), self::META, true );
	}

	public static function menu(): void {
		add_menu_page( 'Luma', 'Luma', 'manage_woocommerce', self::PAGE, [ __CLASS__, 'page' ], 'dashicons-store', 2 );
	}

	/** Simple mode: only what the daily job needs. */
	public static function simplify_menu(): void {
		if ( self::full_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		global $menu, $submenu;
		$keep = [ self::PAGE, 'woocommerce', 'edit.php?post_type=product' ];
		foreach ( (array) $menu as $i => $item ) {
			$slug = $item[2] ?? '';
			if ( ! in_array( $slug, $keep, true ) && 'separator' !== substr( (string) ( $item[4] ?? '' ), 0, 9 ) ) {
				remove_menu_page( $slug );
			}
		}
		/* WooCommerce submenu: Orders, Lots, Receive batch, Luma Core only. */
		$keep_sub = [ 'wc-orders', 'edit.php?post_type=shop_order', 'edit.php?post_type=luma_lot', 'luma-receive', 'luma-core', 'wc-admin&path=/customers', 'woocommerce' ];
		if ( ! empty( $submenu['woocommerce'] ) ) {
			foreach ( $submenu['woocommerce'] as $i => $sub ) {
				$ok = false;
				foreach ( $keep_sub as $k ) {
					if ( str_starts_with( (string) $sub[2], $k ) ) {
						$ok = true;
					}
				}
				if ( ! $ok ) {
					unset( $submenu['woocommerce'][ $i ] );
				}
			}
		}
	}

	public static function admin_bar( \WP_Admin_Bar $bar ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=luma_toggle_admin' ), 'luma_toggle_admin' );
		$bar->add_node( [ 'id' => 'luma-mode', 'title' => self::full_admin() ? 'Simple view' : 'Full admin', 'href' => $url, 'parent' => 'top-secondary' ] );
	}

	public static function handle_toggle(): void {
		check_admin_referer( 'luma_toggle_admin' );
		update_user_meta( get_current_user_id(), self::META, self::full_admin() ? '' : '1' );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	public static function login_redirect( $to, $requested, $user ) {
		return ( $user instanceof \WP_User && user_can( $user, 'manage_woocommerce' ) && ! $requested ) ? admin_url( 'admin.php?page=' . self::PAGE ) : $to;
	}

	public static function land_here(): void {
		global $pagenow;
		if ( 'index.php' === $pagenow && current_user_can( 'manage_woocommerce' ) && ! self::full_admin() && empty( $_GET ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
			exit;
		}
	}

	public static function styles(): void {
		echo '<style>.luma-dash{max-width:1100px}.luma-dash h1{display:flex;align-items:center;gap:1rem}.luma-dash h1 small{font-size:13px;color:#646970;font-weight:400}.luma-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:12px 0 24px}.luma-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px}.luma-card b{display:block;font-size:26px;line-height:1.1}.luma-card span{color:#646970;font-size:12px}.luma-order{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px;margin:0 0 12px;display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:16px}.luma-order.sd{border-left:4px solid #B4432C}.luma-order h3{margin:0 0 4px;font-size:15px}.luma-order .who{color:#646970;font-size:13px}.luma-order ul{margin:8px 0 0;padding:0;list-style:none;font-size:13px}.luma-order form{display:grid;gap:6px}.luma-order form label{font-size:12px;color:#1d2327}.luma-order form select,.luma-order form input[type=text]{width:100%}.luma-row{display:flex;gap:6px}.luma-tag{display:inline-block;font-size:11px;letter-spacing:.06em;text-transform:uppercase;padding:2px 8px;border-radius:999px;background:#f0f0f1;color:#50575e;margin-left:6px}.luma-tag.sd{background:#F3E2DC;color:#B4432C}.luma-empty{background:#fff;border:1px dashed #dcdcde;border-radius:8px;padding:18px;color:#646970}.luma-dash table.widefat td,.luma-dash table.widefat th{vertical-align:middle}@media(max-width:900px){.luma-order{grid-template-columns:1fr}}</style>';
	}

	/* ---------- data ---------- */

	private static function orders( array $statuses, int $limit = 50 ): array {
		return wc_get_orders( [ 'status' => $statuses, 'limit' => $limit, 'orderby' => 'date', 'order' => 'ASC' ] );
	}

	/* ---------- page ---------- */

	public static function page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$log = get_transient( 'luma_dash_log_' . get_current_user_id() );
		delete_transient( 'luma_dash_log_' . get_current_user_id() );
		$to_ship    = self::orders( [ 'processing' ] );
		$in_transit = array_filter( self::orders( [ 'completed' ], 100 ), fn( $o ) => ! $o->get_meta( '_luma_delivered' ) && strtotime( (string) $o->get_date_completed() ) > strtotime( '-21 days' ) );
		$awaiting   = self::orders( [ 'pending', 'on-hold' ] );
		$lots       = get_posts( [ 'post_type' => Lots::CPT, 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => 'title', 'order' => 'ASC' ] );
		$low        = array_filter( $lots, fn( $l ) => (int) get_post_meta( $l->ID, '_lot_qty_remaining', true ) <= 5 );
		$sd_open    = class_exists( 'Luma\\Core\\SameDay' ) && SameDay::window_open();
		?>
		<div class="wrap luma-dash">
			<h1>Luma <small><?php echo esc_html( wp_date( 'l, F j' ) ); ?> · <?php echo $sd_open ? 'same-day window open until ' . esc_html( SameDay::cutoff_label() ) : 'same-day window closed'; ?></small></h1>
			<?php if ( $log ) : ?><div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post( $log ); ?></p></div><?php endif; ?>

			<div class="luma-cards">
				<div class="luma-card"><b><?php echo count( $to_ship ); ?></b><span>To ship</span></div>
				<div class="luma-card"><b><?php echo count( $in_transit ); ?></b><span>In transit</span></div>
				<div class="luma-card"><b><?php echo count( $awaiting ); ?></b><span>Awaiting payment</span></div>
				<div class="luma-card"><b><?php echo count( $low ); ?></b><span>Lots at 5 or fewer</span></div>
				<div class="luma-card"><a class="button button-primary" style="margin-top:6px" href="<?php echo esc_url( admin_url( 'admin.php?page=luma-receive' ) ); ?>">Receive a batch</a></div>
			</div>

			<h2>To ship</h2>
			<?php if ( ! $to_ship ) : ?>
				<p class="luma-empty">Nothing waiting. Paid orders appear here the moment they come in.</p>
			<?php endif; ?>
			<?php foreach ( $to_ship as $o ) : $sd = (bool) $o->get_meta( '_luma_same_day' ); ?>
				<div class="luma-order<?php echo $sd ? ' sd' : ''; ?>">
					<div>
						<h3><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a> <?php echo $sd ? '<span class="luma-tag sd">Same-day</span>' : ''; ?><span class="luma-tag"><?php echo esc_html( wc_format_datetime( $o->get_date_created(), 'M j, g:i a' ) ); ?></span></h3>
						<div class="who"><?php echo esc_html( $o->get_formatted_shipping_full_name() ); ?> · <?php echo esc_html( $o->get_shipping_city() . ', ' . $o->get_shipping_state() . ' ' . $o->get_shipping_postcode() ); ?> · <?php echo esc_html( $o->get_billing_email() ); ?></div>
						<ul>
							<?php foreach ( $o->get_items() as $item ) : ?>
								<li><?php echo esc_html( $item->get_quantity() . '× ' . $item->get_name() ); ?></li>
							<?php endforeach; ?>
						</ul>
						<?php if ( $o->get_customer_note() ) : ?><p class="who"><b>Note:</b> <?php echo esc_html( $o->get_customer_note() ); ?></p><?php endif; ?>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'luma_ship_' . $o->get_id() ); ?>
						<input type="hidden" name="action" value="luma_ship"><input type="hidden" name="order_id" value="<?php echo (int) $o->get_id(); ?>">
						<?php foreach ( $o->get_items() as $item_id => $item ) :
							$prod = $item->get_product();
							$lots_for = $prod ? Fulfillment::lots_for_product( $prod->is_type( 'variation' ) ? $prod->get_parent_id() : $prod->get_id(), $prod->is_type( 'variation' ) ? $prod->get_id() : 0 ) : [];
							$cur = (string) $item->get_meta( '_luma_lot' );
							?>
							<label>Lot for <?php echo esc_html( $item->get_name() ); ?>
								<select name="luma_lot[<?php echo (int) $item_id; ?>]">
									<option value="">— no lot —</option>
									<?php foreach ( $lots_for as $lot ) : ?>
										<option value="<?php echo esc_attr( $lot->post_title ); ?>"<?php selected( $cur, $lot->post_title ); ?>><?php echo esc_html( $lot->post_title . ' · ' . ( get_post_meta( $lot->ID, '_lot_status', true ) ?: 'PENDING' ) . ' · ' . (int) get_post_meta( $lot->ID, '_lot_qty_remaining', true ) . ' left' ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endforeach; ?>
						<?php if ( ! $sd ) : ?>
							<label>Tracking
								<div class="luma-row">
									<select name="luma_tracking_carrier" style="width:auto"><?php foreach ( Fulfillment::CARRIERS as $k => [ $label ] ) : ?><option value="<?php echo esc_attr( $k ); ?>"<?php selected( (string) $o->get_meta( '_luma_tracking_carrier' ) ?: 'usps', $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
									<input type="text" name="luma_tracking" value="<?php echo esc_attr( (string) $o->get_meta( '_luma_tracking' ) ); ?>" placeholder="Tracking number">
								</div>
							</label>
						<?php else : ?>
							<label><input type="checkbox" name="luma_delivered" value="1"> Delivered on drop-off</label>
						<?php endif; ?>
						<button class="button button-primary">Mark shipped</button>
						<span style="font-size:11px;color:#646970">Sends the shipped email with tracking and lot certificates, and reduces the lot count.</span>
					</form>
				</div>
			<?php endforeach; ?>

			<h2>In transit</h2>
			<?php if ( ! $in_transit ) : ?><p class="luma-empty">No shipped orders waiting on delivery.</p><?php else : ?>
			<table class="widefat striped"><thead><tr><th>Order</th><th>Customer</th><th>Shipped</th><th>Tracking</th><th></th></tr></thead><tbody>
			<?php foreach ( $in_transit as $o ) : $t = Fulfillment::tracking( $o ); ?>
				<tr>
					<td><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a><?php echo $o->get_meta( '_luma_same_day' ) ? ' <span class="luma-tag sd">Same-day</span>' : ''; ?></td>
					<td><?php echo esc_html( $o->get_formatted_shipping_full_name() ); ?></td>
					<td><?php echo esc_html( $o->get_date_completed() ? wc_format_datetime( $o->get_date_completed(), 'M j' ) : '—' ); ?></td>
					<td><?php echo $t ? esc_html( $t['carrier'] ) . ' ' . ( $t['url'] ? '<a href="' . esc_url( $t['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $t['number'] ) . '</a>' : esc_html( $t['number'] ) ) : '<span style="color:#646970">none</span>'; ?></td>
					<td><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=luma_delivered&order_id=' . $o->get_id() ), 'luma_delivered_' . $o->get_id() ) ); ?>">Delivered</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php endif; ?>

			<?php if ( $awaiting ) : ?>
			<h2>Awaiting payment</h2>
			<table class="widefat striped"><thead><tr><th>Order</th><th>Customer</th><th>Placed</th><th>Total</th></tr></thead><tbody>
			<?php foreach ( $awaiting as $o ) : ?>
				<tr><td><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a></td><td><?php echo esc_html( $o->get_formatted_billing_full_name() ); ?></td><td><?php echo esc_html( wc_format_datetime( $o->get_date_created(), 'M j, g:i a' ) ); ?></td><td><?php echo wp_kses_post( $o->get_formatted_order_total() ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php endif; ?>

			<h2>Lots on hand</h2>
			<table class="widefat striped"><thead><tr><th>Lot</th><th>Product</th><th>Result</th><th>Remaining</th><th>COA</th></tr></thead><tbody>
			<?php foreach ( $lots as $l ) : $v = Lots::view( $l ); $rem = (int) get_post_meta( $l->ID, '_lot_qty_remaining', true ); ?>
				<tr><td><a href="<?php echo esc_url( get_edit_post_link( $l->ID ) ); ?>"><code><?php echo esc_html( $v['lot'] ); ?></code></a></td><td><?php echo esc_html( $v['product'] ); ?></td><td><?php echo esc_html( $v['status'] ); ?></td><td<?php echo $rem <= 5 ? ' style="color:#b32d2e;font-weight:600"' : ''; ?>><?php echo $rem; ?></td><td><?php echo $v['coa_url'] ? '<a href="' . esc_url( $v['coa_url'] ) . '" target="_blank" rel="noopener">PDF</a>' : '<span style="color:#b32d2e">missing</span>'; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p style="color:#646970;font-size:12px;margin-top:18px">Need something else? Use <b>Full admin</b> in the top bar. Questions or a stuck order: text (385) 521-5259 is the customer line; the store's help is at WooCommerce → Luma Core.</p>
		</div>
		<?php
	}

	/* ---------- actions ---------- */

	public static function handle_ship(): void {
		$id = (int) ( $_POST['order_id'] ?? 0 );
		check_admin_referer( 'luma_ship_' . $id );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Forbidden' );
		}
		$order = wc_get_order( $id );
		if ( ! $order ) {
			wp_die( 'Order not found' );
		}
		$lots = isset( $_POST['luma_lot'] ) && is_array( $_POST['luma_lot'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['luma_lot'] ) ) : [];
		foreach ( $order->get_items() as $item_id => $item ) {
			$v = strtoupper( trim( $lots[ $item_id ] ?? '' ) );
			if ( $v ) {
				$item->update_meta_data( '_luma_lot', $v );
			}
			$item->save();
		}
		$carrier = sanitize_key( wp_unslash( $_POST['luma_tracking_carrier'] ?? 'usps' ) );
		$order->update_meta_data( '_luma_tracking_carrier', isset( Fulfillment::CARRIERS[ $carrier ] ) ? $carrier : 'other' );
		$order->update_meta_data( '_luma_tracking', sanitize_text_field( wp_unslash( $_POST['luma_tracking'] ?? '' ) ) );
		if ( ! empty( $_POST['luma_delivered'] ) ) {
			$order->update_meta_data( '_luma_delivered', gmdate( 'c' ) );
		}
		$order->save();
		$order->update_status( 'completed', 'Marked shipped from the Luma dashboard.' );
		set_transient( 'luma_dash_log_' . get_current_user_id(), sprintf( 'Order #%s marked shipped. The customer has been emailed.', esc_html( $order->get_order_number() ) ), 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	public static function handle_delivered(): void {
		$id = (int) ( $_GET['order_id'] ?? 0 );
		check_admin_referer( 'luma_delivered_' . $id );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Forbidden' );
		}
		$order = wc_get_order( $id );
		if ( $order && ! $order->get_meta( '_luma_delivered' ) ) {
			$order->update_meta_data( '_luma_delivered', gmdate( 'c' ) );
			$order->add_order_note( 'Marked delivered from the Luma dashboard.' );
			$order->save();
		}
		set_transient( 'luma_dash_log_' . get_current_user_id(), sprintf( 'Order #%s marked delivered.', esc_html( $order ? $order->get_order_number() : $id ) ), 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}
}
