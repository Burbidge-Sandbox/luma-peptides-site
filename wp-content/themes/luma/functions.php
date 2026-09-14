<?php
/**
 * Luma theme bootstrap.
 *
 * Presentation only. Every rule that must be *enforced* (reviews off, no
 * upsells, RUO gate, lots) lives in the luma-core plugin so it survives a
 * theme switch. See docs/WOO-MIGRATION.md §3–§4.
 */

defined( 'ABSPATH' ) || exit;

define( 'LUMA_THEME_VERSION', '0.1.0' );

require_once get_template_directory() . '/inc/template-tags.php';

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'gallery', 'caption', 'style', 'script' ] );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );

	register_nav_menus( [
		'primary' => __( 'Primary (header)', 'luma' ),
		'footer'  => __( 'Footer', 'luma' ),
	] );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'luma-fonts',
		'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap',
		[],
		null
	);
	wp_enqueue_style( 'luma', get_stylesheet_uri(), [ 'luma-fonts' ], LUMA_THEME_VERSION );
} );

/* Default menu when none is assigned yet — compounds and formats only, never goals. */
add_filter( 'wp_nav_menu_args', function ( $args ) {
	if ( empty( $args['theme_location'] ) ) {
		return $args;
	}
	if ( ! has_nav_menu( $args['theme_location'] ) ) {
		$args['fallback_cb'] = 'luma_default_menu';
	}
	return $args;
} );

function luma_default_menu( $args ) {
	$items = [
		[ 'Compounds',     function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ],
		[ 'Testing',       home_url( '/testing/' ) ],
		[ 'Documentation', home_url( '/documentation/' ) ],
		[ 'Contact',       home_url( '/contact/' ) ],
	];
	echo '<ul class="menu">';
	foreach ( $items as [ $label, $url ] ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}

/* Woo wrappers: render Woo pages inside the theme's layout. */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
add_action( 'woocommerce_before_main_content', function () {
	echo '<main id="content" class="wrap section">';
}, 10 );
add_action( 'woocommerce_after_main_content', function () {
	echo '</main>';
}, 10 );

/* No breadcrumbs, no result count/ordering widgets — the catalogue is short and sorted by compound. */
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

/* RUO statement inside the buy box, above add-to-cart (CLAUDE.md "Always present"). */
add_action( 'woocommerce_before_add_to_cart_form', 'luma_ruo_notice', 5 );
