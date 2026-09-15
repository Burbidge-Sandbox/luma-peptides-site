<?php
/**
 * Sales tax: Utah-only at launch (physical nexus). Seeded once; edit later in
 * WooCommerce → Settings → Tax. Out-of-state stays untaxed until an economic-nexus
 * threshold is crossed — revisit with TaxJar/Avalara when volume warrants.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Tax {
	const SEED_VERSION = '1';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_seed' ], 31 );
	}

	public static function maybe_seed(): void {
		if ( get_option( 'luma_tax_seed' ) === self::SEED_VERSION || ! class_exists( '\WC_Tax' ) ) {
			return;
		}
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_based_on', 'shipping' );
		update_option( 'woocommerce_shipping_tax_class', 'inherit' );
		update_option( 'woocommerce_tax_round_at_subtotal', 'no' );
		update_option( 'woocommerce_tax_display_shop', 'excl' );
		update_option( 'woocommerce_tax_display_cart', 'excl' );
		update_option( 'woocommerce_tax_total_display', 'itemized' );
		update_option( 'woocommerce_price_display_suffix', '' );

		$have_ut = false;
		foreach ( \WC_Tax::get_rates_for_tax_class( '' ) as $rate ) {
			if ( 'US' === $rate->tax_rate_country && 'UT' === $rate->tax_rate_state ) {
				$have_ut = true;
			}
		}
		if ( ! $have_ut ) {
			\WC_Tax::_insert_tax_rate( [
				'tax_rate_country'  => 'US',
				'tax_rate_state'    => 'UT',
				'tax_rate'          => '7.2500', // Utah County combined (Provo/Lindon). Adjust per locality if needed.
				'tax_rate_name'     => 'Sales tax',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1, // Utah taxes delivery charges on taxable sales.
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			] );
		}
		\WC_Cache_Helper::invalidate_cache_group( 'taxes' );
		update_option( 'luma_tax_seed', self::SEED_VERSION );
	}
}
