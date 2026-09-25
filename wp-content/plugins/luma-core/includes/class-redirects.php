<?php
/**
 * 301s from the old static-site URLs (lumaresearchco.com/*.html) to WordPress.
 * Kept so search results, shared links and the printed packaging keep working.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Redirects {
	public static function init(): void {
		add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect' ], 1 );
	}

	private static function page_url( string $slug ): string {
		$p = get_page_by_path( $slug );
		return $p ? get_permalink( $p ) : home_url( '/' );
	}

	public static function maybe_redirect(): void {
		$path = wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $path || ! preg_match( '#^/([a-z0-9-]+)\.html$#i', $path, $m ) ) {
			return;
		}
		$name = strtolower( $m[1] );
		$q    = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
		$to   = null;

		switch ( $name ) {
			case 'index':
				$to = home_url( '/' );
				break;
			case 'shop':
				$to = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/catalog/' );
				if ( ! empty( $q['cat'] ) ) {
					$to = add_query_arg( 'cat', sanitize_key( $q['cat'] ), $to );
				}
				break;
			case 'product':
				$id = sanitize_title( $q['id'] ?? '' );
				$id = Housekeeping::RENAMED_PRODUCTS[ $id ] ?? $id;
				$p  = $id ? get_page_by_path( $id, OBJECT, 'product' ) : null;
				$to = $p ? get_permalink( $p ) : wc_get_page_permalink( 'shop' );
				$size = $q['size'] ?? $q['dose'] ?? '';
				if ( $p && $size ) {
					$to = add_query_arg( 'size', sanitize_key( $size ), $to );
				}
				break;
			case 'verify':
				$to = self::page_url( 'testing' );
				if ( ! empty( $q['lot'] ) ) {
					$to = add_query_arg( 'lot', sanitize_text_field( $q['lot'] ), $to );
				}
				break;
			case 'cart':
				$to = wc_get_page_permalink( 'cart' );
				break;
			case 'checkout':
				$to = wc_get_page_permalink( 'checkout' );
				break;
			case 'account':
			case 'confirmation':
			case 'status':
				$to = wc_get_account_endpoint_url( 'orders' );
				break;
			case 'about':
			case 'faq':
			case 'contact':
			case 'shipping-returns':
			case 'privacy':
			case 'terms':
				$to = self::page_url( $name );
				break;
		}
		if ( $to ) {
			wp_safe_redirect( $to, 301 );
			exit;
		}
	}
}
