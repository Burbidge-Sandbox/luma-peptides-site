<?php
/**
 * Luma theme — a faithful port of the static lumaresearchco.com site onto
 * WooCommerce. The legacy stylesheet (css/styles.css in the repo) is used
 * verbatim; templates reproduce the original markup; the cart is WooCommerce.
 *
 * Everything that must be *enforced* (reviews off, RUO gate, lots, pricing)
 * lives in luma-core so it survives a theme switch.
 */

defined( 'ABSPATH' ) || exit;

define( 'LUMA_THEME_VERSION', '0.4.7' );

require_once get_template_directory() . '/inc/template-tags.php';
require_once get_template_directory() . '/inc/catalogue-json.php';
require_once get_template_directory() . '/inc/account.php';

/* ---------- WooCommerce template overrides ----------
 * Woo caches located template paths in the persistent object cache
 * (Object Cache Pro), so a newly added theme override can be ignored until
 * the cache is cleared. Two guards: always prefer a theme file when one
 * exists, and clear Woo's template cache whenever the theme version changes.
 */
add_filter( 'wc_get_template', function ( $template, $template_name ) {
	$override = get_template_directory() . '/woocommerce/' . ltrim( $template_name, '/' );
	return file_exists( $override ) ? $override : $template;
}, 20, 2 );

add_action( 'init', function () {
	if ( get_option( 'luma_theme_version' ) !== LUMA_THEME_VERSION ) {
		if ( function_exists( 'wc_clear_template_cache' ) ) {
			wc_clear_template_cache();
		}
		update_option( 'luma_theme_version', LUMA_THEME_VERSION );
	}
}, 99 );

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'gallery', 'caption', 'style', 'script' ] );
	add_theme_support( 'woocommerce' );
} );

/* ---------- Assets: legacy CSS verbatim + ported JS ---------- */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'luma-fonts', 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Inter:wght@400;500;600;700&display=swap', [], null );
	wp_enqueue_style( 'luma-legacy', luma_assets_url( 'css/styles.css' ), [ 'luma-fonts' ], LUMA_THEME_VERSION );
	wp_enqueue_style( 'luma', get_stylesheet_uri(), [ 'luma-legacy' ], LUMA_THEME_VERSION );
	wp_enqueue_script( 'luma-site', get_template_directory_uri() . '/assets/site.js', [], LUMA_THEME_VERSION, [ 'strategy' => 'defer' ] );
	wp_localize_script( 'luma-site', 'LUMA', luma_catalogue_json() );
	/* Woo's default stylesheets fight the legacy design on the pages we template ourselves. */
	if ( is_shop() || is_product() || is_front_page() || is_page() ) {
		wp_dequeue_style( 'woocommerce-general' );
		wp_dequeue_style( 'woocommerce-layout' );
		wp_dequeue_style( 'woocommerce-smallscreen' );
	}
}, 20 );

/* Legacy body classes (index.html used home-editorial). */
add_filter( 'body_class', function ( array $c ): array {
	if ( is_front_page() ) {
		$c[] = 'home-editorial';
	}
	return $c;
} );

/* Static-site meta: theme-color and description on the front page. */
add_action( 'wp_head', function () {
	if ( is_front_page() ) {
		echo '<meta name="description" content="Research-grade peptides for laboratory use, independently tested by lot with published certificates of analysis.">' . "\n";
	}
}, 1 );

/* ---------- WooCommerce: strip everything the legacy pages don't use ---------- */
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
add_filter( 'woocommerce_enqueue_styles', fn( $s ) => $s ); // keep Woo CSS on cart/checkout/account only (see dequeue above)

/* RUO statement in the checkout/cart context (product page carries it in-template). */
add_action( 'woocommerce_before_cart', 'luma_ruo_notice', 5 );

/* Order-received page: hide the page-head title so the legacy .confirm block stands alone. */
add_filter( 'body_class', function ( array $c ): array {
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		$c[] = 'order-received';
	}
	return $c;
} );

/* Shop page title (used by Woo for <title>). */
add_filter( 'woocommerce_page_title', fn( $t ) => is_shop() ? 'Catalog' : $t );
add_filter( 'document_title_parts', function ( array $parts ): array {
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$parts['title'] = 'Catalog';
	}
	return $parts;
} );

/* ---------- Product helpers used by catalogue-json ---------- */
function luma_current_lot_number( WC_Product $product ): string {
	$lot_id = (int) $product->get_meta( '_luma_current_lot' );
	$lot    = $lot_id ? get_post( $lot_id ) : null;
	return $lot ? $lot->post_title : '';
}

/* ---------- AJAX: contact form + waitlist (replace the old Apps Script capture) ---------- */
function luma_owner_email(): string {
	return class_exists( 'Luma\Core\Settings' ) ? (string) Luma\Core\Settings::get( 'alert_email' ) : get_option( 'admin_email' );
}
add_action( 'wp_ajax_nopriv_luma_contact', 'luma_ajax_contact' );
add_action( 'wp_ajax_luma_contact', 'luma_ajax_contact' );
function luma_ajax_contact(): void {
	$f = fn( $k ) => sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$msg = sanitize_textarea_field( wp_unslash( $_POST['cmsg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! is_email( $f( 'cemail' ) ) || ! $msg ) {
		wp_send_json_error( 'invalid', 400 );
	}
	$body = "Name: {$f('cname')}\nEmail: {$f('cemail')}\nOrganization: {$f('corg')}\nTopic: {$f('ctopic')}\n\n{$msg}";
	wp_mail( luma_owner_email(), '[Luma] Contact form: ' . $f( 'ctopic' ), $body, [ 'Reply-To: ' . $f( 'cemail' ) ] );
	wp_send_json_success();
}
add_action( 'wp_ajax_nopriv_luma_waitlist', 'luma_ajax_waitlist' );
add_action( 'wp_ajax_luma_waitlist', 'luma_ajax_waitlist' );
function luma_ajax_waitlist(): void {
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$pid   = sanitize_title( wp_unslash( $_POST['product'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! is_email( $email ) || ! $pid ) {
		wp_send_json_error( 'invalid', 400 );
	}
	$list   = (array) get_option( 'luma_waitlist', [] );
	$list[] = [ 'product' => $pid, 'email' => $email, 'at' => gmdate( 'c' ) ];
	update_option( 'luma_waitlist', array_slice( $list, -2000 ), false );
	wp_send_json_success();
}
