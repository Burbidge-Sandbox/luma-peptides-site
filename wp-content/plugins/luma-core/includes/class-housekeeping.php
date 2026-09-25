<?php
/**
 * Storefront housekeeping: US-only selling, no default WordPress leftovers,
 * no user enumeration, guest order tracking, and 301s for renamed products.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Housekeeping {
	const RENAMED_PRODUCTS = [
		'wolverine-stack' => 'bpc-157-tb-500-blend',
		'glow-klow'       => 'bpc-157-ghk-cu-tb-500-blend',
	];

	public static function init(): void {
		/* 4. United States only — enforced in code so a settings change cannot reopen it. */
		add_filter( 'pre_option_woocommerce_allowed_countries', fn() => 'specific' );
		add_filter( 'pre_option_woocommerce_specific_allowed_countries', fn() => [ 'US' ] );
		add_filter( 'pre_option_woocommerce_ship_to_countries', fn() => 'specific' );
		add_filter( 'pre_option_woocommerce_specific_ship_to_countries', fn() => [ 'US' ] );
		add_filter( 'pre_option_woocommerce_default_country', fn() => 'US:UT' );

		/* 6. Default WordPress content is never public. */
		add_action( 'init', [ __CLASS__, 'trash_defaults' ], 40 );

		/* 7. No user enumeration: author archives, REST users, sitemap users. */
		add_action( 'template_redirect', [ __CLASS__, 'block_author_archive' ] );
		add_filter( 'rest_endpoints', [ __CLASS__, 'hide_rest_users' ] );
		add_filter( 'wp_sitemaps_add_provider', fn( $provider, $name ) => 'users' === $name ? false : $provider, 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', fn( $t ) => array_diff_key( $t, [ 'category' => 1, 'post_tag' => 1 ] ) );
		add_filter( 'wp_sitemaps_post_types', fn( $t ) => array_diff_key( $t, [ 'post' => 1 ] ) );
		add_filter( 'author_link', fn() => home_url( '/' ) );

		/* 2. Guest order tracking page. */
		add_action( 'init', [ __CLASS__, 'ensure_track_page' ], 41 );
		add_shortcode( 'luma_track_order', [ __CLASS__, 'track_shortcode' ] );
		add_action( 'woocommerce_order_details_after_order_table', [ __CLASS__, 'tracking_after_table' ] );

		/* 9. Old product slugs keep working. */
		add_action( 'template_redirect', [ __CLASS__, 'redirect_renamed' ], 0 );
	}

	public static function trash_defaults(): void {
		if ( get_option( 'luma_housekeeping_v1' ) ) {
			return;
		}
		foreach ( [ [ 'sample-page', 'page' ], [ 'hello-world', 'post' ] ] as [ $slug, $type ] ) {
			$p = get_page_by_path( $slug, OBJECT, $type );
			if ( $p && 'trash' !== $p->post_status ) {
				wp_trash_post( $p->ID );
			}
		}
		update_option( 'luma_housekeeping_v1', gmdate( 'c' ), false );
	}

	public static function block_author_archive(): void {
		if ( is_author() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	public static function hide_rest_users( array $endpoints ): array {
		if ( current_user_can( 'list_users' ) ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( str_starts_with( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/* ---------- guest tracking ---------- */

	public static function ensure_track_page(): void {
		if ( get_option( 'luma_track_page_v1' ) || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		if ( ! get_page_by_path( 'track-order' ) ) {
			$id = wp_insert_post( [
				'post_type'    => 'page',
				'post_name'    => 'track-order',
				'post_title'   => 'Track your order',
				'post_status'  => 'publish',
				'post_content' => '[luma_track_order]',
			] );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_wp_page_template', 'page-track.php' );
			}
		}
		update_option( 'luma_track_page_v1', gmdate( 'c' ), false );
	}

	public static function track_url(): string {
		$p = get_page_by_path( 'track-order' );
		return $p ? get_permalink( $p ) : wc_get_account_endpoint_url( 'orders' );
	}

	public static function track_shortcode(): string {
		$out  = '<p class="track-lede">Enter the order number from your confirmation email and the email address you used at checkout.</p>';
		$out .= do_shortcode( '[woocommerce_order_tracking]' );
		$out .= '<p class="track-help">Signed in? Your <a href="' . esc_url( wc_get_account_endpoint_url( 'orders' ) ) . '">account</a> lists every order with tracking and certificates. Questions: email <a href="mailto:info@lumaresearchco.com">info@lumaresearchco.com</a> or text <a href="sms:+13855215259">(385) 521-5259</a>.</p>';
		return $out;
	}

	/** Tracking number, lots and delivery mark under the order table (tracking page and account). */
	public static function tracking_after_table( $order ): void {
		if ( ! $order instanceof \WC_Order || is_admin() ) {
			return;
		}
		$t    = Fulfillment::tracking( $order );
		$lots = Fulfillment::order_lots( $order );
		$d    = (string) $order->get_meta( '_luma_delivered' );
		if ( ! $t && ! $lots && ! $d ) {
			return;
		}
		echo '<div class="track-status">';
		if ( $t ) {
			echo '<p><b>Tracking:</b> ' . esc_html( $t['carrier'] ) . ' ' . ( $t['url'] ? '<a href="' . esc_url( $t['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $t['number'] ) . '</a>' : esc_html( $t['number'] ) ) . '</p>';
		}
		if ( $lots ) {
			$links = array_map( fn( $l ) => '<a href="' . esc_url( home_url( '/testing/?lot=' . rawurlencode( $l ) ) ) . '">' . esc_html( $l ) . '</a>', $lots );
			echo '<p><b>Lot' . ( count( $lots ) > 1 ? 's' : '' ) . ':</b> ' . implode( ', ', $links ) . '</p>';
		}
		if ( $d ) {
			echo '<p><b>Delivered:</b> ' . esc_html( gmdate( 'M j, Y', strtotime( $d ) ) ) . '</p>';
		}
		echo '</div>';
	}

	/* ---------- renamed products ---------- */

	public static function redirect_renamed(): void {
		$path = wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $path || ! preg_match( '#^/product/([a-z0-9-]+)/?$#', $path, $m ) || ! isset( self::RENAMED_PRODUCTS[ $m[1] ] ) ) {
			return;
		}
		$new = get_page_by_path( self::RENAMED_PRODUCTS[ $m[1] ], OBJECT, 'product' );
		if ( $new ) {
			$q = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
			$to = get_permalink( $new );
			if ( ! empty( $q['size'] ) || ! empty( $q['dose'] ) ) {
				$to = add_query_arg( 'size', sanitize_key( $q['size'] ?? $q['dose'] ), $to );
			}
			wp_safe_redirect( $to, 301 );
			exit;
		}
	}
}
