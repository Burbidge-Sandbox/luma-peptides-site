<?php
/**
 * Shipping rates exactly as the legacy storefront promised:
 * Standard (3–5 days) $8 · Express (1–2 days) $24 · Standard free at $150+.
 * Seeded once into the US zone; edit later in WooCommerce → Shipping.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Shipping {
	const SEED_VERSION = '1';
	const THRESHOLD    = 150;

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_seed' ], 30 );
		// When Standard is free (>= $150) hide the paid Standard rate so the customer sees Free + Express only.
		add_filter( 'woocommerce_package_rates', [ __CLASS__, 'collapse_rates' ], 10, 2 );
	}

	public static function maybe_seed(): void {
		if ( get_option( 'luma_shipping_seed' ) === self::SEED_VERSION || ! class_exists( '\WC_Shipping_Zones' ) ) {
			return;
		}
		$zone = null;
		foreach ( \WC_Shipping_Zones::get_zones() as $z ) {
			foreach ( $z['zone_locations'] as $loc ) {
				if ( 'country' === $loc->type && 'US' === $loc->code ) {
					$zone = new \WC_Shipping_Zone( $z['id'] );
					break 2;
				}
			}
		}
		if ( ! $zone ) {
			$zone = new \WC_Shipping_Zone();
			$zone->set_zone_name( 'United States (US)' );
			$zone->add_location( 'US', 'country' );
			$zone->save();
		}

		$have = [];
		foreach ( $zone->get_shipping_methods() as $m ) {
			$have[ $m->id ][] = (int) $m->get_instance_id();
		}

		// Free shipping: reuse the existing instance, gate it at the threshold.
		$free_id = $have['free_shipping'][0] ?? $zone->add_shipping_method( 'free_shipping' );
		update_option( 'woocommerce_free_shipping_' . $free_id . '_settings', [
			'title'            => 'Standard (3–5 days) — free',
			'requires'         => 'min_amount',
			'min_amount'       => (string) self::THRESHOLD,
			'ignore_discounts' => 'no',
		] );

		$flat = $have['flat_rate'] ?? [];
		$std  = $flat[0] ?? $zone->add_shipping_method( 'flat_rate' );
		update_option( 'woocommerce_flat_rate_' . $std . '_settings', [ 'title' => 'Standard (3–5 days)', 'tax_status' => 'none', 'cost' => '8' ] );
		$exp = $flat[1] ?? $zone->add_shipping_method( 'flat_rate' );
		update_option( 'woocommerce_flat_rate_' . $exp . '_settings', [ 'title' => 'Express (1–2 days)', 'tax_status' => 'none', 'cost' => '24' ] );

		// Order: Free, Standard, Express.
		global $wpdb;
		foreach ( [ $free_id => 1, $std => 2, $exp => 3 ] as $id => $order ) {
			$wpdb->update( $wpdb->prefix . 'woocommerce_shipping_zone_methods', [ 'method_order' => $order, 'is_enabled' => 1 ], [ 'instance_id' => $id ] );
		}
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		update_option( 'luma_shipping_seed', self::SEED_VERSION );
	}

	public static function collapse_rates( array $rates, array $package ): array {
		$has_free = false;
		foreach ( $rates as $r ) {
			if ( 'free_shipping' === $r->get_method_id() ) {
				$has_free = true;
			}
		}
		if ( $has_free ) {
			foreach ( $rates as $k => $r ) {
				if ( 'flat_rate' === $r->get_method_id() && str_starts_with( $r->get_label(), 'Standard' ) ) {
					unset( $rates[ $k ] );
				}
			}
		}
		return $rates;
	}
}
