<?php
/**
 * Fulfillment: lot assignment per line item + carrier/tracking on the order.
 *
 * - Order screen meta box ("Fulfillment") with a lot dropdown per item and a
 *   carrier + tracking number field.
 * - Stored as hidden item meta `_luma_lot` (read by the account page / emails)
 *   and order meta `_luma_tracking_carrier`, `_luma_tracking`.
 * - When an order is marked Completed the lot's remaining count is reduced once.
 * - The "Completed" email carries the tracking link and lot numbers.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Fulfillment {
	const CARRIERS = [
		'usps'  => [ 'USPS',  'https://tools.usps.com/go/TrackConfirmAction?tLabels=%s' ],
		'ups'   => [ 'UPS',   'https://www.ups.com/track?tracknum=%s' ],
		'fedex' => [ 'FedEx', 'https://www.fedex.com/fedextrack/?trknbr=%s' ],
		'other' => [ 'Other', '' ],
	];

	public static function init(): void {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_box' ] );
		add_action( 'woocommerce_process_shop_order_meta', [ __CLASS__, 'save' ], 20, 1 );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'deduct_lots' ] );
		add_action( 'woocommerce_email_after_order_table', [ __CLASS__, 'email' ], 9, 4 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', [ __CLASS__, 'admin_summary' ] );
	}

	/* ---------- helpers (also used by the theme) ---------- */

	public static function lots_for_product( int $product_id, int $variation_id = 0 ): array {
		$q = new \WP_Query( [
			'post_type'      => Lots::CPT,
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'meta_query'     => [ [ 'key' => '_lot_product_id', 'value' => $product_id ] ], // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );
		$out = [];
		foreach ( $q->posts as $p ) {
			$vid = (int) get_post_meta( $p->ID, '_lot_variation_id', true );
			if ( $variation_id && $vid && $vid !== $variation_id ) {
				continue;
			}
			$out[] = $p;
		}
		return $out;
	}

	public static function tracking( \WC_Order $order ): ?array {
		$num = trim( (string) $order->get_meta( '_luma_tracking' ) );
		if ( '' === $num ) {
			return null;
		}
		$c   = (string) $order->get_meta( '_luma_tracking_carrier' ) ?: 'other';
		$def = self::CARRIERS[ $c ] ?? self::CARRIERS['other'];
		return [ 'carrier' => $def[0], 'number' => $num, 'url' => $def[1] ? sprintf( $def[1], rawurlencode( $num ) ) : '' ];
	}

	public static function order_lots( \WC_Order $order ): array {
		$lots = [];
		foreach ( $order->get_items() as $item ) {
			$l = (string) $item->get_meta( '_luma_lot' );
			if ( $l ) {
				$lots[] = $l;
			}
		}
		return array_values( array_unique( $lots ) );
	}

	/* ---------- admin ---------- */

	public static function add_box(): void {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'luma_fulfillment', 'Fulfillment — lots & tracking', [ __CLASS__, 'box' ], $screen, 'side', 'high' );
	}

	public static function box( $post_or_order ): void {
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		wp_nonce_field( 'luma_fulfillment', 'luma_fulfillment_nonce' );
		echo '<p style="margin:0 0 .6rem;color:#646970">Pick the lot that actually shipped for each item. Vials are labelled with the lot number; the customer sees it in their account and the shipped email.</p>';
		foreach ( $order->get_items() as $item_id => $item ) {
			$pid  = (int) $item->get_product_id();
			$vid  = (int) $item->get_variation_id();
			$lots = self::lots_for_product( $pid, $vid );
			$cur  = (string) $item->get_meta( '_luma_lot' );
			if ( ! $cur ) {
				$prod = $item->get_product();
				$cl   = $prod ? (int) $prod->get_meta( '_luma_current_lot' ) : 0;
				$cur  = $cl ? get_the_title( $cl ) : '';
			}
			echo '<p style="margin:.4rem 0 .6rem"><strong>' . esc_html( $item->get_quantity() . '× ' . $item->get_name() ) . '</strong><br>';
			echo '<select name="luma_lot[' . (int) $item_id . ']" style="width:100%"><option value="">— no lot —</option>';
			foreach ( $lots as $lot ) {
				$rem = (int) get_post_meta( $lot->ID, '_lot_qty_remaining', true );
				$st  = (string) get_post_meta( $lot->ID, '_lot_status', true );
				echo '<option value="' . esc_attr( $lot->post_title ) . '"' . selected( $cur, $lot->post_title, false ) . '>' . esc_html( $lot->post_title . ' · ' . $st . ' · ' . $rem . ' left' ) . '</option>';
			}
			echo '</select></p>';
		}
		$carrier = (string) $order->get_meta( '_luma_tracking_carrier' ) ?: 'usps';
		echo '<p style="margin:.8rem 0 .3rem"><strong>Tracking</strong></p>';
		echo '<select name="luma_tracking_carrier" style="width:100%;margin-bottom:.4rem">';
		foreach ( self::CARRIERS as $k => [ $label ] ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $carrier, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<input type="text" name="luma_tracking" value="' . esc_attr( (string) $order->get_meta( '_luma_tracking' ) ) . '" placeholder="Tracking number" style="width:100%">';
		echo '<p style="margin:.6rem 0 0;color:#646970;font-size:12px">Save the order, then set status to <b>Completed</b> to send the shipped email with this tracking link and reduce the lot count.</p>';
	}

	public static function save( $order_id ): void {
		if ( ! isset( $_POST['luma_fulfillment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['luma_fulfillment_nonce'] ) ), 'luma_fulfillment' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$lots = isset( $_POST['luma_lot'] ) && is_array( $_POST['luma_lot'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['luma_lot'] ) ) : [];
		foreach ( $order->get_items() as $item_id => $item ) {
			$v = strtoupper( trim( $lots[ $item_id ] ?? '' ) );
			if ( $v ) {
				$item->update_meta_data( '_luma_lot', $v );
			} else {
				$item->delete_meta_data( '_luma_lot' );
			}
			$item->save();
		}
		$carrier = sanitize_key( wp_unslash( $_POST['luma_tracking_carrier'] ?? 'usps' ) );
		$order->update_meta_data( '_luma_tracking_carrier', isset( self::CARRIERS[ $carrier ] ) ? $carrier : 'other' );
		$order->update_meta_data( '_luma_tracking', sanitize_text_field( wp_unslash( $_POST['luma_tracking'] ?? '' ) ) );
		$order->save();
	}

	public static function admin_summary( \WC_Order $order ): void {
		$t = self::tracking( $order );
		if ( $t ) {
			echo '<p><strong>Tracking:</strong> ' . esc_html( $t['carrier'] ) . ' ' . ( $t['url'] ? '<a href="' . esc_url( $t['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $t['number'] ) . '</a>' : esc_html( $t['number'] ) ) . '</p>';
		}
	}

	/** Reduce each assigned lot's remaining count once per order. */
	public static function deduct_lots( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_luma_lots_deducted' ) ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$l = (string) $item->get_meta( '_luma_lot' );
			if ( ! $l ) {
				continue;
			}
			$lot = Lots::find( $l );
			if ( $lot ) {
				$rem = (int) get_post_meta( $lot->ID, '_lot_qty_remaining', true );
				update_post_meta( $lot->ID, '_lot_qty_remaining', max( 0, $rem - $item->get_quantity() ) );
			}
		}
		$order->update_meta_data( '_luma_lots_deducted', gmdate( 'c' ) );
		$order->save();
	}

	/* ---------- customer email (runs before Emails::after_table) ---------- */

	public static function email( $order, $sent_to_admin, $plain_text, $email ): void {
		if ( ! $order instanceof \WC_Order || $sent_to_admin || ! $order->has_status( 'completed' ) ) {
			return;
		}
		$t    = self::tracking( $order );
		$lots = self::order_lots( $order );
		if ( $plain_text ) {
			if ( $t ) {
				echo "\nTracking: {$t['carrier']} {$t['number']}" . ( $t['url'] ? " {$t['url']}" : '' ) . "\n";
			}
			if ( $lots ) {
				echo 'Lot(s): ' . implode( ', ', $lots ) . "\n";
			}
			return;
		}
		if ( $t ) {
			echo '<p><b>Tracking:</b> ' . esc_html( $t['carrier'] ) . ' ' . ( $t['url'] ? '<a href="' . esc_url( $t['url'] ) . '">' . esc_html( $t['number'] ) . '</a>' : esc_html( $t['number'] ) ) . '</p>';
		}
		if ( $lots ) {
			echo '<p><b>Lot number' . ( count( $lots ) > 1 ? 's' : '' ) . ':</b> ' . esc_html( implode( ', ', $lots ) ) . '</p>';
		}
	}
}
