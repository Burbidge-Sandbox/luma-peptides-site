<?php
/**
 * Catalogue rules — CLAUDE.md rules 3, 9, 10 enforced in code, not in copy.
 *  - No reviews or ratings anywhere.
 *  - No related / upsell / cross-sell output.
 *  - No subscriptions: refuse to run alongside WooCommerce Subscriptions.
 *  - Catalogue sorted by title (compound), never by popularity or rating.
 */

namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class CatalogueRules {

	public static function init(): void {
		/* Reviews and ratings. */
		add_filter( 'woocommerce_product_tabs', [ __CLASS__, 'tabs' ], 98 );
		add_filter( 'woocommerce_product_get_reviews_allowed', '__return_false' );
		add_filter( 'comments_open', [ __CLASS__, 'comments_open' ], 10, 2 );
		add_filter( 'woocommerce_product_get_rating_html', '__return_empty_string' );
		add_filter( 'woocommerce_review_ratings_enabled', '__return_false' );
		add_filter( 'woocommerce_enable_review_rating', '__return_false' );
		add_filter( 'pre_option_woocommerce_enable_reviews', fn() => 'no' );
		add_filter( 'pre_option_woocommerce_enable_review_rating', fn() => 'no' );
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );

		/* Related / upsells / cross-sells. */
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
		remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
		add_filter( 'woocommerce_related_products', '__return_empty_array' );
		add_filter( 'woocommerce_product_get_upsell_ids', '__return_empty_array' );
		add_filter( 'woocommerce_product_get_cross_sell_ids', '__return_empty_array' );

		/* Sale badge framed as urgency. Quantity breaks are pricing, not a "sale". */
		add_filter( 'woocommerce_sale_flash', '__return_empty_string' );

		/* Sorting: by compound name only. Remove popularity/rating options. */
		add_filter( 'woocommerce_default_catalog_orderby', fn() => 'title' );
		add_filter( 'woocommerce_catalog_orderby', [ __CLASS__, 'orderby_options' ] );

		/* Subscriptions are a hard no (rule 10). */
		add_action( 'admin_init', [ __CLASS__, 'refuse_subscriptions' ] );
	}

	public static function tabs( array $tabs ): array {
		unset( $tabs['reviews'] );
		if ( isset( $tabs['additional_information'] ) ) {
			$tabs['additional_information']['title'] = __( 'Specifications', 'luma-core' );
		}
		return $tabs;
	}

	public static function comments_open( bool $open, int $post_id ): bool {
		return get_post_type( $post_id ) === 'product' ? false : $open;
	}

	public static function orderby_options( array $opts ): array {
		return array_intersect_key( $opts, array_flip( [ 'title', 'price', 'price-desc' ] ) );
	}

	public static function refuse_subscriptions(): void {
		$offenders = array_filter( [
			'woocommerce-subscriptions/woocommerce-subscriptions.php',
			'woocommerce-subscriptions-core/woocommerce-subscriptions-core.php',
			'subscriptio/subscriptio.php',
			'yith-woocommerce-subscription/init.php',
		], 'is_plugin_active' );
		if ( ! $offenders ) {
			return;
		}
		deactivate_plugins( array_values( $offenders ) );
		add_action( 'admin_notices', function () use ( $offenders ) {
			echo '<div class="notice notice-error"><p><strong>Luma Core deactivated: '
				. esc_html( implode( ', ', $offenders ) )
				. '</strong>. Subscriptions and auto-ship imply a personal regimen (CLAUDE.md rule 10). Use quantity-break pricing instead.</p></div>';
		} );
	}
}
