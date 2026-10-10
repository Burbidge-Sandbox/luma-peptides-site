<?php
/**
 * The /labs/ landing page on its own domain (Luma Core setting `lab_domain`,
 * e.g. lumapeptidelabs.com, added in Cloudways as an alias of this app).
 *
 * On the landing domain: "/" renders the Research labs page, every other page
 * path 301s to the same path on the store, and home_url() is that domain so
 * the claim form posts same-origin. On the store: /labs/ 301s to the landing
 * domain (query string kept, so UTMs survive).
 *
 * Browser sessions don't cross domains, so a claim hands off to the store
 * with a one-time token (see Leads::handoff_url / consume_handoff); nothing
 * personal is in the URL.
 */

namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class LabDomain {

	/** Store home captured before any filtering, e.g. https://lumaresearchco.com */
	private static string $store_home = '';

	public static function init(): void {
		self::$store_home = untrailingslashit( (string) get_option( 'home' ) );
		if ( ! self::host() ) {
			return;
		}
		if ( self::is_lab_host() ) {
			add_filter( 'option_home', [ __CLASS__, 'lab_home' ] );
			add_filter( 'admin_url', [ __CLASS__, 'lab_ajax_url' ], 10, 2 ); // PixelYourSite etc. call admin-ajax; keep it same-origin
			add_filter( 'redirect_canonical', '__return_false' );
			add_filter( 'request', [ __CLASS__, 'route' ] );
			add_action( 'template_redirect', [ __CLASS__, 'lab_template_redirect' ], 1 );
			add_action( 'admin_init', [ __CLASS__, 'to_store_admin' ], 1 );
			add_action( 'login_init', [ __CLASS__, 'to_store_admin' ], 1 );
		} else {
			add_action( 'template_redirect', [ __CLASS__, 'store_template_redirect' ], 1 );
		}
	}

	/** Configured landing domain, normalised (no scheme, no www, lower case); '' = feature off. */
	public static function host(): string {
		$h = strtolower( trim( (string) Settings::get( 'lab_domain' ) ) );
		$h = preg_replace( '#^https?://#', '', $h );
		$h = preg_replace( '#^www\.#', '', rtrim( $h, '/' ) );
		return preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/', $h ) ? $h : '';
	}

	public static function request_host(): string {
		$h = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		return preg_replace( '#^www\.#', '', preg_replace( '/:\d+$/', '', $h ) );
	}

	public static function is_lab_host(): bool {
		return '' !== self::host() && self::request_host() === self::host();
	}

	public static function lab_url( string $path = '/' ): string {
		return 'https://' . self::host() . '/' . ltrim( $path, '/' );
	}

	/** Absolute URL on the store, whichever domain this request came in on. */
	public static function store_url( string $path = '/' ): string {
		return ( self::$store_home ?: untrailingslashit( (string) get_option( 'home' ) ) ) . '/' . ltrim( $path, '/' );
	}

	public static function store_shop_url( array $args = [] ): string {
		$path = (string) wp_parse_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '/shop/', PHP_URL_PATH );
		return add_query_arg( $args, self::store_url( $path ?: '/shop/' ) );
	}

	public static function lab_ajax_url( $url, $path ) {
		return 0 === strpos( ltrim( (string) $path, '/' ), 'admin-ajax.php' ) ? self::lab_home() . '/wp-admin/' . ltrim( (string) $path, '/' ) : $url;
	}

	public static function lab_home(): string {
		return 'https://' . self::host();
	}

	/** The published page using the Research labs template. */
	public static function page_id(): int {
		$id = (int) get_option( 'luma_labs_page_id' );
		if ( $id && 'publish' === get_post_status( $id ) && 'page-labs.php' === get_page_template_slug( $id ) ) {
			return $id;
		}
		$pages = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_wp_page_template', 'meta_value' => 'page-labs.php', 'fields' => 'ids' ] ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$id    = (int) ( $pages[0] ?? 0 );
		update_option( 'luma_labs_page_id', $id, false );
		return $id;
	}

	private static function path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return (string) wp_parse_url( $uri, PHP_URL_PATH );
	}

	/** Landing domain "/" → the Research labs page. */
	public static function route( array $vars ): array {
		$path = trim( self::path(), '/' );
		$id   = self::page_id();
		if ( '' === $path && $id ) {
			return [ 'page_id' => $id ];
		}
		return $vars;
	}

	/** Landing domain: anything but the landing page goes to the store. */
	public static function lab_template_redirect(): void {
		$id = self::page_id();
		if ( $id && is_page( $id ) && '' === trim( self::path(), '/' ) ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		wp_redirect( self::store_url( $uri ), 301 ); // phpcs:ignore WordPress.Security.SafeRedirect -- our own store
		exit;
	}

	public static function to_store_admin(): void {
		if ( wp_doing_ajax() ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/wp-admin/';
		wp_redirect( self::store_url( $uri ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	/** Store: /labs/ lives on the landing domain now. */
	public static function store_template_redirect(): void {
		$id = self::page_id();
		if ( ! $id || ! is_page( $id ) || is_preview() ) {
			return;
		}
		$qs = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : '';
		wp_redirect( self::lab_url( '/' ) . ( '' !== $qs ? '?' . $qs : '' ), 301 ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}
}
