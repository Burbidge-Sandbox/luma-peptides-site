<?php
/**
 * Shipping (policy 2026-09-18): free next-day shipping on every US order, plus
 * free same-day delivery by radius + cutoff (see class-same-day.php). Seeded
 * idempotently by SEED_VERSION; edit later in WooCommerce → Shipping.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Shipping {
	const SEED_VERSION = '3';

	/** Utah County ZIPs (explicit — a range would sweep in Salt Lake County). */
	const UTAH_COUNTY_ZIPS = [
		'84003', '84004', '84005', '84013', '84042', '84043', '84045', '84057', '84058', '84059', '84062', '84097',
		'84601', '84602', '84603', '84604', '84605', '84606', '84626', '84633', '84651', '84653', '84655', '84660', '84663', '84664',
	];

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_seed' ], 30 );
	}

	private static function set_free( \WC_Shipping_Zone $zone, string $title, array $existing_ids ): int {
		$id = $existing_ids ? array_shift( $existing_ids ) : $zone->add_shipping_method( 'free_shipping' );
		update_option( 'woocommerce_free_shipping_' . $id . '_settings', [ 'title' => $title, 'requires' => '', 'min_amount' => '0', 'ignore_discounts' => 'no' ] );
		return $id;
	}

	public static function maybe_seed(): void {
		if ( get_option( 'luma_shipping_seed' ) === self::SEED_VERSION || ! class_exists( '\WC_Shipping_Zones' ) ) {
			return;
		}
		global $wpdb;
		$us = null;
		$uc = null;
		foreach ( \WC_Shipping_Zones::get_zones() as $z ) {
			foreach ( $z['zone_locations'] as $loc ) {
				if ( 'country' === $loc->type && 'US' === $loc->code ) {
					$us = new \WC_Shipping_Zone( $z['id'] );
				}
			}
			if ( 'Utah County (same-day)' === $z['zone_name'] ) {
				$uc = new \WC_Shipping_Zone( $z['id'] );
			}
		}

		/* US: one method — free next-day. Remove the old flat rates. */
		if ( ! $us ) {
			$us = new \WC_Shipping_Zone();
			$us->set_zone_name( 'United States (US)' );
			$us->add_location( 'US', 'country' );
			$us->save();
		}
		$free = [];
		foreach ( $us->get_shipping_methods() as $m ) {
			if ( 'free_shipping' === $m->id ) {
				$free[] = (int) $m->get_instance_id();
			} elseif ( SameDay::METHOD_ID !== $m->id ) {
				$us->delete_shipping_method( $m->get_instance_id() );
			}
		}
		$us_next = self::set_free( $us, 'Next-day shipping — free', $free );
		foreach ( array_slice( $free, 1 ) as $extra ) {
			$us->delete_shipping_method( $extra );
		}

		/* Same-day by radius: one instance on the US zone; it emits a rate only when eligible. */
		$has_sd = false;
		foreach ( $us->get_shipping_methods() as $m ) {
			if ( SameDay::METHOD_ID === $m->id ) {
				$has_sd = true;
			}
		}
		if ( ! $has_sd ) {
			$sd = $us->add_shipping_method( SameDay::METHOD_ID );
			$wpdb->update( $wpdb->prefix . 'woocommerce_shipping_zone_methods', [ 'method_order' => 0, 'is_enabled' => 1 ], [ 'instance_id' => $sd ] );
			$wpdb->update( $wpdb->prefix . 'woocommerce_shipping_zone_methods', [ 'method_order' => 1 ], [ 'instance_id' => $us_next ] );
		}

		/* The old fixed Utah County zone is superseded by the radius rule. */
		if ( $uc ) {
			$uc->delete();
		}

		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		update_option( 'luma_shipping_seed', self::SEED_VERSION );
	}
}
