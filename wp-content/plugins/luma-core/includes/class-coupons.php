<?php
/**
 * Discount codes. WooCommerce coupons do the work; luma-core adds the
 * "first order only" rule WooCommerce lacks, and seeds the two standing codes
 * once (amounts are owned by WooCommerce → Marketing → Coupons afterwards).
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Coupons {
	const FIRST = '_luma_first_order';
	/** Orders in these statuses count as "has ordered before". */
	const ORDERED = [ 'wc-processing', 'wc-completed', 'wc-on-hold' ];

	public static function init(): void {
		add_filter( 'pre_option_woocommerce_enable_coupons', fn() => 'yes' );
		add_action( 'init', [ __CLASS__, 'ensure' ], 50 );
		add_filter( 'woocommerce_coupon_is_valid', [ __CLASS__, 'first_order_only' ], 10, 3 );
		add_action( 'woocommerce_coupon_options_usage_restriction', [ __CLASS__, 'field' ], 10, 2 );
		add_action( 'woocommerce_coupon_options_save', [ __CLASS__, 'save' ], 10, 2 );
	}

	/** Seed FAMILY20 and WELCOME10 once; never overwrite edits made in admin. */
	public static function ensure(): void {
		if ( get_option( 'luma_coupons_v1' ) || ! class_exists( '\\WC_Coupon' ) ) {
			return;
		}
		$codes = [
			[ 'family20', 20, false, 'Friends & Family: 20% off, reusable. Share privately.' ],
			[ 'welcome10', 10, true, 'First-time buyers: 10% off their first order, once per customer (account or email).' ],
		];
		foreach ( $codes as [ $code, $pct, $first, $desc ] ) {
			if ( wc_get_coupon_id_by_code( $code ) ) {
				continue;
			}
			$c = new \WC_Coupon();
			$c->set_code( $code );
			$c->set_discount_type( 'percent' );
			$c->set_amount( $pct );
			$c->set_individual_use( true );
			$c->set_description( $desc );
			if ( $first ) {
				$c->set_usage_limit_per_user( 1 );
				$c->update_meta_data( self::FIRST, 'yes' );
			}
			$c->save();
		}
		update_option( 'luma_coupons_v1', gmdate( 'c' ), false );
	}

	/** Has this shopper (signed-in account, or the billing email entered at checkout) ordered before? */
	public static function has_ordered(): bool {
		$uid = get_current_user_id();
		if ( $uid && wc_get_orders( [ 'customer_id' => $uid, 'status' => self::ORDERED, 'limit' => 1, 'return' => 'ids' ] ) ) {
			return true;
		}
		$emails = array_filter( array_unique( [
			$uid ? (string) wp_get_current_user()->user_email : '',
			WC()->customer ? (string) WC()->customer->get_billing_email() : '',
		] ) );
		foreach ( $emails as $email ) {
			if ( wc_get_orders( [ 'billing_email' => $email, 'status' => self::ORDERED, 'limit' => 1, 'return' => 'ids' ] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function first_order_only( $valid, $coupon, $discounts ) {
		if ( ! $valid || ! $coupon instanceof \WC_Coupon || 'yes' !== $coupon->get_meta( self::FIRST ) ) {
			return $valid;
		}
		if ( self::has_ordered() ) {
			throw new \Exception( 'This code is for first orders only, and there is already an order under this account or email.', 100 );
		}
		return $valid;
	}

	public static function field( $coupon_id, $coupon ): void {
		woocommerce_wp_checkbox( [
			'id'          => 'luma_first_order',
			'label'       => 'First order only',
			'description' => 'Only valid for customers with no previous order (checked by account and by billing email).',
			'value'       => $coupon && 'yes' === $coupon->get_meta( self::FIRST ) ? 'yes' : 'no',
		] );
	}

	public static function save( $post_id, $coupon ): void {
		$coupon->update_meta_data( self::FIRST, empty( $_POST['luma_first_order'] ) ? 'no' : 'yes' ); // phpcs:ignore WordPress.Security.NonceVerification -- Woo verifies the coupon form nonce
		$coupon->save_meta_data();
	}
}
