<?php
/**
 * Front-end catalogue: the same shape as legacy js/products.js (LUMA_PRODUCTS,
 * LUMA_CATEGORIES, LUMA_CONFIG, LUMA_VOLUME_TIERS) but built from WooCommerce,
 * so the ported site.js / productCard / vialSVG work unchanged.
 */

defined( 'ABSPATH' ) || exit;

function luma_assets_url( string $rel = '' ): string {
	return content_url( '/luma-src/' . ltrim( $rel, '/' ) );
}

function luma_catalogue_json(): array {
	$cached = wp_cache_get( 'luma_catalogue_json', 'luma' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$products = [];
	$q = wc_get_products( [ 'status' => 'publish', 'limit' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ] );
	foreach ( $q as $p ) {
		/** @var WC_Product $p */
		$slug   = $p->get_slug();
		$specs  = (array) $p->get_meta( '_luma_specs' );
		$cats   = wp_get_post_terms( $p->get_id(), 'product_cat', [ 'fields' => 'slugs' ] );
		$cat    = is_array( $cats ) && $cats ? $cats[0] : '';
		$legacy = $p->get_meta( '_luma_legacy_id' ) ?: $slug;
		$label  = luma_label_lines( $p->get_name(), (string) $p->get_meta( '_luma_strength' ) );
		$gallery = $p->get_gallery_image_ids();
		$row = [
			'id'          => $slug,
			'wc_id'       => $p->get_id(),
			'url'         => $p->get_permalink(),
			'name'        => $p->get_name(),
			'label'       => $label,
			'strength'    => (string) $p->get_meta( '_luma_strength' ),
			'category'    => $cat,
			'once'        => (float) $p->get_regular_price(),
			'subscribe'   => null,
			'stock'       => $p->is_in_stock() ? 'in' : 'out',
			'tagline'     => wp_strip_all_tags( $p->get_short_description() ),
			'description' => wp_strip_all_tags( $p->get_description() ),
			'inside'      => (string) $p->get_meta( '_luma_inside' ),
			'specs'       => $specs,
			'faqs'        => [],
			'image'       => $gallery ? wp_get_attachment_image_url( $gallery[0], 'full' ) : null,
			'imageFocus'  => '50% 55%',
			'lot'         => function_exists( 'luma_current_lot_number' ) ? luma_current_lot_number( $p ) : '',
		];
		if ( $p->is_type( 'variable' ) ) {
			$row['variants'] = [];
			$min = null;
			foreach ( $p->get_children() as $vid ) {
				$v = wc_get_product( $vid );
				if ( ! $v ) {
					continue;
				}
				$attrs = $v->get_attributes();
				$lbl   = reset( $attrs ) ?: $v->get_sku();
				$row['variants'][] = [
					'key'      => strtolower( (string) $lbl ),
					'label'    => (string) $lbl,
					'strength' => (string) ( $v->get_meta( '_luma_strength' ) ?: $lbl . ' vial' ),
					'once'     => (float) $v->get_regular_price(),
					'stock'    => $v->is_in_stock() ? 'in' : 'out',
					'sku'      => $v->get_sku(),
					'wc_id'    => $vid,
					'lot'      => function_exists( 'luma_current_lot_number' ) ? luma_current_lot_number( $v ) : '',
				];
				$min = $min === null ? (float) $v->get_regular_price() : min( $min, (float) $v->get_regular_price() );
			}
			$row['once']     = $min ?? 0;
			$row['strength'] = $row['variants'][0]['strength'] ?? '';
			$row['stock']    = array_filter( $row['variants'], fn( $v ) => $v['stock'] === 'in' ) ? 'in' : 'out';
		}
		$products[] = $row;
	}

	$cats = [ 'all' => 'All compounds' ];
	foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'orderby' => 'menu_order' ] ) as $t ) {
		if ( $t->slug === 'uncategorized' ) {
			continue;
		}
		$cats[ $t->slug ] = $t->name;
	}

	$out = [
		'products'   => $products,
		'categories' => $cats,
		'tiers'      => (array) get_option( 'luma_volume_tiers', [ [ 'min' => 3, 'pct' => 5 ], [ 'min' => 5, 'pct' => 10 ], [ 'min' => 10, 'pct' => 15 ] ] ),
		'config'     => [
			'freeShipThreshold' => 0,
			'promises'          => [ 'shipDays' => 1, 'freeShipAlways' => true, 'nextDay' => true, 'sameDayCounty' => 'Utah County', 'sameDayCutoff' => '12:00 pm MT', 'deliveryPromiseDays' => 0 ],
			'siteUrl'           => home_url(),
			'assets'            => luma_assets_url(),
			'urls'              => [
				'home'     => home_url( '/' ),
				'shop'     => wc_get_page_permalink( 'shop' ),
				'cart'     => wc_get_cart_url(),
				'checkout' => wc_get_checkout_url(),
				'account'  => wc_get_page_permalink( 'myaccount' ),
				'orders'   => wc_get_account_endpoint_url( 'orders' ),
				'verify'   => home_url( '/testing/' ),
				'contact'  => home_url( '/contact/' ),
				'faq'      => home_url( '/faq/' ),
				'about'    => home_url( '/about/' ),
				'terms'    => home_url( '/terms/' ),
				'privacy'  => home_url( '/privacy/' ),
				'shipping' => home_url( '/shipping-returns/' ),
			],
			'storeApi'          => rest_url( 'wc/store/v1/' ),
			'ajax'              => admin_url( 'admin-ajax.php' ),
		],
	];
	wp_cache_set( 'luma_catalogue_json', $out, 'luma', 300 );
	return $out;
}

/** Recreate the legacy two-line vial label ["BPC-157","10MG"] from name + strength. */
function luma_label_lines( string $name, string $strength ): array {
	$map = [
		'GLP-2 T' => [ 'GLP-2 T' ], 'GLP-3 RT' => [ 'GLP-3 RT' ], 'GLP-1 SM' => [ 'GLP-1 SM' ],
		'BPC-157 + GHK-Cu + TB-500 Blend' => [ 'BPC + GHK-CU', '+ TB-500' ],
		'BPC-157 + TB-500 Blend' => [ 'BPC-157 +', 'TB-500 BLEND' ],
		'CJC-1295 (no DAC) + Ipamorelin Blend' => [ 'CJC-1295 +', 'IPAMORELIN' ],
		'Bacteriostatic Water' => [ 'BAC WATER' ],
	];
	$lines = $map[ $name ] ?? [ strtoupper( $name ) ];
	if ( count( $lines ) === 1 && preg_match( '/^(\d+(?:\.\d+)?\s?(?:mg|ml))/i', $strength, $m ) ) {
		$lines[] = strtoupper( str_replace( ' ', '', $m[1] ) );
	}
	return $lines;
}

add_action( 'woocommerce_update_product', fn() => wp_cache_delete( 'luma_catalogue_json', 'luma' ) );
add_action( 'woocommerce_product_set_stock', fn() => wp_cache_delete( 'luma_catalogue_json', 'luma' ) );
