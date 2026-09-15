<?php
/**
 * Customer-facing order numbers: internal order id + offset so every number is
 * at least four digits (order 52 → 1052). Storage is untouched; display,
 * emails, the track-order form and admin order search understand the offset.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class OrderNumbers {
	const OFFSET = 1000;

	public static function init(): void {
		add_filter( 'woocommerce_order_number', [ __CLASS__, 'display' ], 10, 2 );
		add_filter( 'woocommerce_shortcode_order_tracking_order_id', [ __CLASS__, 'to_id' ] );
		add_filter( 'woocommerce_order_search_results', [ __CLASS__, 'legacy_search' ], 10, 3 );
		add_action( 'admin_init', [ __CLASS__, 'rewrite_admin_search' ] );
	}

	public static function display( $number, $order ) {
		$id = $order instanceof \WC_Abstract_Order ? $order->get_id() : (int) $number;
		return (string) ( $id + self::OFFSET );
	}

	/** Accept a display number (1052), a raw id (52) or an "#1052"/"LP-1052"-style string. */
	public static function to_id( $value ): int {
		$n = (int) preg_replace( '/\D/', '', (string) $value );
		if ( $n > self::OFFSET && wc_get_order( $n - self::OFFSET ) ) {
			return $n - self::OFFSET;
		}
		return $n;
	}

	/** Legacy (post-based) order search. */
	public static function legacy_search( $ids, $term, $fields ) {
		$id = self::to_id( $term );
		if ( $id && ! in_array( $id, $ids, true ) && wc_get_order( $id ) ) {
			$ids[] = $id;
		}
		return $ids;
	}

	/** HPOS orders list: map a typed display number back to the id before Woo runs its search. */
	public static function rewrite_admin_search(): void {
		if ( ! isset( $_GET['s'] ) || ( $_GET['page'] ?? '' ) !== 'wc-orders' ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$term = trim( wp_unslash( (string) $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( preg_match( '/^#?\d+$/', $term ) ) {
			$id = self::to_id( $term );
			if ( $id !== (int) ltrim( $term, '#' ) ) {
				$_GET['s'] = $_REQUEST['s'] = (string) $id;
			}
		}
	}
}
