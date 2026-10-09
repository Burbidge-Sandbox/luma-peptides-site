<?php
/**
 * New-lab discount (the /labs/ funnel).
 *
 * One WooCommerce coupon per lead (LAB25-XXXX): percent off, single use,
 * restricted to the claimed email, expiring after `lab_offer_days`. The coupon
 * is the only thing that discounts anything. The struck-through catalogue
 * prices are display, computed with WooCommerce's own percent-coupon rounding,
 * so catalogue, cart and checkout agree to the cent.
 *
 * The visitor carries the code in the `luma_lab_offer` cookie (readable by JS
 * so cached pages can catch up); the coupon is (re)applied to the cart session
 * from `?coupon=CODE` or that cookie on any uncached page load.
 */

namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class LabOffer {

	const COOKIE        = 'luma_lab_offer';
	const META          = '_luma_lab_offer'; // on the coupon: 'yes'
	const LEAD          = '_luma_lead_id';   // on the coupon: the luma_lead post ID
	const EXCLUDE_CATS  = [ 'supplies' ];    // bacteriostatic water etc. are not compounds
	const SESSION_MASK  = 'luma_lab_mask';   // repeat claim matched by phone only: mask the email in notices

	/** @var \WC_Coupon|false|null memo for active() */
	private static $active = null;

	public static function init(): void {
		add_action( 'wp_loaded', [ __CLASS__, 'on_request' ], 30 );
		add_action( 'woocommerce_applied_coupon', [ __CLASS__, 'on_applied' ] );
		add_filter( 'woocommerce_coupon_error', [ __CLASS__, 'coupon_error' ], 10, 3 );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', [ __CLASS__, 'reapply_after_email' ], 20 );
		add_action( 'woocommerce_checkout_update_order_review', [ __CLASS__, 'reapply_after_email' ], 20 );
		add_filter( 'woocommerce_get_price_html', [ __CLASS__, 'price_html' ], 20, 2 );
		add_filter( 'woocommerce_cart_totals_coupon_label', [ __CLASS__, 'coupon_label' ], 10, 2 );
		add_action( 'template_redirect', [ __CLASS__, 'no_cache' ], 0 );
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
	}

	/* ---------- Settings ---------- */

	public static function pct(): int {
		return max( 1, min( 90, (int) Settings::get( 'lab_offer_pct' ) ) );
	}

	public static function days(): int {
		return max( 1, min( 90, (int) Settings::get( 'lab_offer_days' ) ) );
	}

	/* ---------- Coupons ---------- */

	public static function is_lab( $coupon ): bool {
		return $coupon instanceof \WC_Coupon && $coupon->get_id() && 'yes' === $coupon->get_meta( self::META );
	}

	public static function excluded_cat_ids(): array {
		$ids = [];
		foreach ( self::EXCLUDE_CATS as $slug ) {
			$t = get_term_by( 'slug', $slug, 'product_cat' );
			if ( $t ) {
				$ids[] = (int) $t->term_id;
			}
		}
		return $ids;
	}

	/** End of the day `lab_offer_days` from today, store time zone. */
	public static function expiry_ts(): int {
		return ( new \DateTimeImmutable( 'today', wp_timezone() ) )->modify( '+' . self::days() . ' days' )->setTime( 23, 59, 59 )->getTimestamp();
	}

	public static function create_coupon( string $email, int $lead_id, string $who ): ?\WC_Coupon {
		$pct      = self::pct();
		$alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
		$code     = '';
		for ( $try = 0; $try < 10; $try++ ) {
			$code = 'LAB' . $pct . '-';
			for ( $i = 0; $i < 4; $i++ ) {
				$code .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
			}
			if ( ! wc_get_coupon_id_by_code( $code ) ) {
				break;
			}
		}
		$c = new \WC_Coupon();
		$c->set_code( $code );
		$c->set_discount_type( 'percent' );
		$c->set_amount( $pct );
		$c->set_individual_use( true );
		$c->set_usage_limit( 1 );
		$c->set_usage_limit_per_user( 1 );
		$c->set_email_restrictions( [ strtolower( $email ) ] );
		$c->set_date_expires( self::expiry_ts() );
		$c->set_minimum_amount( '' );
		$c->set_free_shipping( false ); // delivery is already free on every order
		$c->set_excluded_product_categories( self::excluded_cat_ids() );
		$c->set_description( sprintf( 'New-lab discount for %s <%s>. Created by the /labs/ page.', $who, $email ) );
		$c->update_meta_data( self::META, 'yes' );
		$c->update_meta_data( self::LEAD, $lead_id );
		$c->save();
		return $c->get_id() ? $c : null;
	}

	/** 'ok' | 'used' | 'expired' | 'missing' */
	public static function usable( $coupon ): string {
		if ( ! self::is_lab( $coupon ) || 'publish' !== get_post_status( $coupon->get_id() ) ) {
			return 'missing';
		}
		$exp = $coupon->get_date_expires();
		if ( $exp && time() > $exp->getTimestamp() ) {
			return 'expired';
		}
		if ( $coupon->get_usage_limit() && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
			return 'used';
		}
		return 'ok';
	}

	public static function from_code( string $code ): ?\WC_Coupon {
		$code = wc_format_coupon_code( $code );
		if ( '' === $code || ! wc_get_coupon_id_by_code( $code ) ) {
			return null;
		}
		$c = new \WC_Coupon( $code );
		return self::is_lab( $c ) ? $c : null;
	}

	public static function expires_label( \WC_Coupon $c ): string {
		$exp = $c->get_date_expires();
		return $exp ? wp_date( 'M j', $exp->getTimestamp() ) : '';
	}

	/* ---------- Session + cookie ---------- */

	public static function cookie_code(): string {
		return isset( $_COOKIE[ self::COOKIE ] ) ? strtoupper( wc_format_coupon_code( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) ) ) : '';
	}

	public static function set_cookie( \WC_Coupon $c ): void {
		static $sent = '';
		$exp  = $c->get_date_expires();
		$code = strtoupper( $c->get_code() );
		if ( $sent !== $code && ! headers_sent() ) {
			$sent = $code;
			wc_setcookie( self::COOKIE, $code, $exp ? $exp->getTimestamp() : time() + self::days() * DAY_IN_SECONDS, is_ssl(), false ); // not httponly: the front end reads it
		}
		$_COOKIE[ self::COOKIE ] = $code;
	}

	public static function clear_cookie(): void {
		if ( isset( $_COOKIE[ self::COOKIE ] ) && ! headers_sent() ) {
			wc_setcookie( self::COOKIE, '', time() - YEAR_IN_SECONDS, is_ssl(), false );
		}
		unset( $_COOKIE[ self::COOKIE ] );
		self::$active = null;
	}

	/** Make sure WooCommerce's session, customer and cart exist (also inside REST requests). */
	public static function load_cart(): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		return (bool) WC()->cart;
	}

	/** Apply quietly to the cart session; WooCommerce's own "Coupon applied" banner is suppressed. */
	public static function apply( \WC_Coupon $c ): bool {
		if ( ! self::load_cart() ) {
			return false;
		}
		$cart = WC()->cart;
		if ( $cart->has_discount( $c->get_code() ) ) {
			return true;
		}
		/* A guest arriving from the code email has no billing email yet; the code is theirs, so carry it. */
		$restrict = $c->get_email_restrictions();
		if ( $restrict && ! is_user_logged_in() && WC()->customer && '' === (string) WC()->customer->get_billing_email() ) {
			WC()->customer->set_billing_email( (string) $restrict[0] );
			WC()->customer->save();
		}
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		$notices = WC()->session ? WC()->session->get( 'wc_notices', [] ) : [];
		$ok      = $cart->apply_coupon( $c->get_code() );
		if ( WC()->session ) {
			WC()->session->set( 'wc_notices', $notices );
		}
		return $ok;
	}

	/** A LAB code applied anywhere (cart page, Store API) also sets the cookie. */
	public static function on_applied( $code ): void {
		$c = self::from_code( (string) $code );
		if ( $c && 'ok' === self::usable( $c ) ) {
			self::set_cookie( $c );
		}
	}

	private static function is_rest_uri(): bool {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return str_contains( $uri, '/' . rest_get_url_prefix() . '/' ) || isset( $_GET['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** Front-end page loads: `?coupon=CODE` applies a code; the cookie re-applies a lost one. */
	public static function on_request(): void {
		if ( is_admin() || wp_doing_cron() || wp_doing_ajax() || self::is_rest_uri() || ! function_exists( 'WC' ) ) {
			return;
		}
		$param  = isset( $_GET['coupon'] ) ? wc_format_coupon_code( sanitize_text_field( wp_unslash( $_GET['coupon'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$cookie = self::cookie_code();
		if ( '' === $param && '' === $cookie ) {
			return;
		}
		if ( ! self::load_cart() ) {
			return;
		}
		if ( '' !== $param ) {
			$lab = self::from_code( $param );
			if ( $lab ) {
				if ( 'ok' === self::usable( $lab ) ) {
					self::apply( $lab );
					self::set_cookie( $lab );
				} else {
					self::clear_cookie();
					$_GET['offer'] = 'used';
				}
			} elseif ( wc_get_coupon_id_by_code( $param ) && ! WC()->cart->has_discount( $param ) ) {
				WC()->cart->apply_coupon( $param ); // any other code: WooCommerce validates and explains
			}
			return;
		}
		$lab = self::from_code( $cookie );
		if ( ! $lab || 'ok' !== self::usable( $lab ) ) {
			self::clear_cookie();
			return;
		}
		$applied = WC()->cart->get_applied_coupons();
		if ( ! $applied ) {
			self::apply( $lab ); // session lost the code (expired session, new device tab): put it back
		}
	}

	/** Customer fixed the billing email at checkout: put the LAB code back if WooCommerce removed it. */
	public static function reapply_after_email(): void {
		$lab = self::from_code( self::cookie_code() );
		if ( ! $lab || 'ok' !== self::usable( $lab ) || ! WC()->cart || WC()->cart->has_discount( $lab->get_code() ) ) {
			return;
		}
		$billing = strtolower( (string) ( WC()->customer ? WC()->customer->get_billing_email() : '' ) );
		if ( $billing && in_array( $billing, array_map( 'strtolower', $lab->get_email_restrictions() ), true ) && ! WC()->cart->get_applied_coupons() ) {
			self::apply( $lab );
		}
	}

	/** The LAB coupon in this visitor's cart session, else the one in their cookie, if still usable. */
	public static function active(): ?\WC_Coupon {
		if ( null !== self::$active ) {
			return self::$active ?: null;
		}
		self::$active = false;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_applied_coupons() as $code ) {
				$c = self::from_code( $code );
				if ( $c && 'ok' === self::usable( $c ) ) {
					self::$active = $c;
					return $c;
				}
			}
		}
		$c = self::from_code( self::cookie_code() );
		if ( $c && 'ok' === self::usable( $c ) ) {
			self::$active = $c;
		}
		return self::$active ?: null;
	}

	/** Is a LAB coupon applied to this cart? (Quantity-break tiers stand down while it is.) */
	public static function in_cart( ?\WC_Cart $cart = null ): bool {
		$cart = $cart ?: ( function_exists( 'WC' ) ? WC()->cart : null );
		if ( ! $cart ) {
			return false;
		}
		foreach ( $cart->get_applied_coupons() as $code ) {
			if ( self::from_code( $code ) ) {
				return true;
			}
		}
		return false;
	}

	/* ---------- Display ---------- */

	/** Price after a $pct% coupon on one unit, with WooCommerce's own rounding (WC_Discounts::apply_coupon_percent). */
	public static function display_price( float $price, float $pct ): float {
		$cents    = wc_add_number_precision( $price );
		$discount = wc_round_discount( $cents * ( $pct / 100 ), 0 );
		return (float) wc_remove_number_precision( $cents - $discount );
	}

	/** Does this coupon actually discount this product (in stock, not excluded)? Drives the strike-through and the pill. */
	public static function discounts( \WC_Coupon $c, \WC_Product $p ): bool {
		return $p->is_in_stock() && '' !== $p->get_regular_price() && $c->is_valid_for_product( $p );
	}

	/** State for the front end: null when no offer is active for this visitor. */
	public static function client_state(): ?array {
		static $memo = [];
		$c = self::active();
		if ( ! $c ) {
			return null;
		}
		if ( isset( $memo[ $c->get_id() ] ) ) {
			return $memo[ $c->get_id() ];
		}
		$pct = (float) $c->get_amount();
		$now = [];
		foreach ( wc_get_products( [ 'status' => 'publish', 'limit' => -1 ] ) as $p ) {
			$ids = $p->is_type( 'variable' ) ? $p->get_children() : [ $p->get_id() ];
			foreach ( $ids as $id ) {
				$v = $id === $p->get_id() ? $p : wc_get_product( $id );
				if ( $v && self::discounts( $c, $v ) ) {
					$now[ $id ] = self::display_price( (float) $v->get_regular_price(), $pct );
				}
			}
		}
		return $memo[ $c->get_id() ] = [
			'code'    => strtoupper( $c->get_code() ),
			'pct'     => $pct + 0,
			'expires' => self::expires_label( $c ),
			'now'     => (object) $now,
		];
	}

	/** Classic WooCommerce price HTML (anywhere Woo renders a price itself). */
	public static function price_html( $html, $product ) {
		$c = is_admin() && ! wp_doing_ajax() ? null : self::active();
		if ( ! $c || ! $product instanceof \WC_Product ) {
			return $html;
		}
		$pct = (float) $c->get_amount();
		$fmt = fn( $was, $now ) => '<del class="was">' . wc_price( $was ) . '</del> <ins class="now">' . wc_price( $now ) . '</ins>';
		if ( $product->is_type( 'variable' ) ) {
			$was = [];
			$now = [];
			foreach ( $product->get_children() as $id ) {
				$v = wc_get_product( $id );
				if ( $v && self::discounts( $c, $v ) ) {
					$was[] = (float) $v->get_regular_price();
					$now[] = self::display_price( (float) $v->get_regular_price(), $pct );
				}
			}
			if ( ! $was ) {
				return $html;
			}
			$range = fn( $a ) => min( $a ) === max( $a ) ? wc_price( min( $a ) ) : wc_format_price_range( min( $a ), max( $a ) );
			return '<del class="was">' . $range( $was ) . '</del> <ins class="now">' . $range( $now ) . '</ins>';
		}
		if ( ! self::discounts( $c, $product ) ) {
			return $html;
		}
		$was = (float) $product->get_regular_price();
		return $fmt( $was, self::display_price( $was, $pct ) );
	}

	public static function coupon_label( $label, $coupon ) {
		return self::is_lab( $coupon ) ? sprintf( 'New lab discount (%s%%)', $coupon->get_amount() + 0 ) : $label;
	}

	/** Email mismatch at checkout: say how to keep the discount instead of WooCommerce's generic line. */
	public static function coupon_error( $err, $code, $coupon ) {
		if ( \WC_Coupon::E_WC_COUPON_NOT_YOURS_REMOVED !== (int) $code || ! self::is_lab( $coupon ) ) {
			return $err;
		}
		$email = (string) ( $coupon->get_email_restrictions()[0] ?? '' );
		if ( function_exists( 'WC' ) && WC()->session && WC()->session->get( self::SESSION_MASK ) ) {
			$email = self::mask( $email );
		}
		return sprintf( 'Use %s at checkout to keep your %s%% off.', esc_html( $email ), $coupon->get_amount() + 0 );
	}

	public static function mask( string $email ): string {
		[ $u, $d ] = array_pad( explode( '@', $email, 2 ), 2, '' );
		return mb_substr( $u, 0, 1 ) . str_repeat( '•', max( 3, mb_strlen( $u ) - 1 ) ) . '@' . $d;
	}

	/** Pages showing a visitor's discount are never stored in Breeze (or Varnish) for anyone else. */
	public static function no_cache(): void {
		if ( '' === self::cookie_code() && ! self::active() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}

	/** GET /wp-json/luma/v1/offer — lets a page that was served from cache catch up with the visitor's discount. */
	public static function routes(): void {
		register_rest_route( 'luma/v1', '/offer', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				self::load_cart();
				$state = self::client_state();
				$res   = rest_ensure_response( [ 'offer' => $state ] );
				$res->header( 'Cache-Control', 'no-store, private' );
				return $res;
			},
		] );
	}
}
