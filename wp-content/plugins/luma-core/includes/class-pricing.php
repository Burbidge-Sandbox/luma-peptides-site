<?php
/**
 * Quantity-break pricing (WOO-MIGRATION §4.3). Ordinary lab-supply volume
 * pricing applied per cart line — the approved alternative to subscriptions.
 * Tiers live in option `luma_volume_tiers` as [{min, pct}, …].
 */

namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Pricing {

	public static function init(): void {
		add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'apply' ], 20 );
		add_filter( 'woocommerce_cart_item_price', [ __CLASS__, 'cart_item_price' ], 10, 2 );
	}

	/** @return array<int, array{min:int,pct:float}> sorted by min ascending */
	public static function tiers(): array {
		$t = (array) get_option( 'luma_volume_tiers', [ [ 'min' => 3, 'pct' => 5 ], [ 'min' => 5, 'pct' => 10 ], [ 'min' => 10, 'pct' => 15 ] ] );
		usort( $t, fn( $a, $b ) => (int) $a['min'] <=> (int) $b['min'] );
		return $t;
	}

	public static function pct_for_qty( int $qty ): float {
		$pct = 0;
		foreach ( self::tiers() as $tier ) {
			if ( $qty >= (int) $tier['min'] ) {
				$pct = (float) $tier['pct'];
			}
		}
		return $pct;
	}

	/** Per-unit price at a given quantity, from the product's regular price. */
	public static function unit_price( \WC_Product $product, int $qty ): float {
		$base = (float) $product->get_regular_price();
		return round( $base * ( 1 - self::pct_for_qty( $qty ) / 100 ), 2 );
	}

	/** Rows for the price ladder: [qty => unit price] for 1 and each tier min. */
	public static function ladder( \WC_Product $product ): array {
		$rows = [ 1 => self::unit_price( $product, 1 ) ];
		foreach ( self::tiers() as $tier ) {
			$rows[ (int) $tier['min'] ] = self::unit_price( $product, (int) $tier['min'] );
		}
		return $rows;
	}

	public static function apply( \WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			/** @var \WC_Product $product */
			$product = $item['data'];
			if ( ! $product || $product->get_regular_price() === '' ) {
				continue;
			}
			$product->set_price( (string) self::unit_price( $product, (int) $item['quantity'] ) );
		}
	}

	public static function cart_item_price( string $html, array $item ): string {
		$product = $item['data'] ?? null;
		if ( ! $product instanceof \WC_Product ) {
			return $html;
		}
		$pct = self::pct_for_qty( (int) $item['quantity'] );
		if ( $pct > 0 ) {
			$html .= ' <small class="mono">(' . esc_html( (string) $pct ) . '% volume price)</small>';
		}
		return $html;
	}
}
