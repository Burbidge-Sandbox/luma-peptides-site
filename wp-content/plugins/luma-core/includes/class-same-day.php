<?php
/**
 * Same-day delivery by radius (policy 2026-09-18).
 *
 * Rule: the destination ZIP's centroid lies within `same_day_radius` miles
 * (straight line) of the origin set on the Luma Core settings screen, AND the
 * order is placed before the cutoff on an open day (Mon–Sat, not a holiday),
 * measured on the America/Denver clock. Both halves are evaluated server-side
 * at rate-calculation time, so the option appears at checkout only when true.
 *
 * The origin address lives in wp_options, never in the repo. The ZIP table is
 * GeoNames (CC BY 4.0), Utah rows only — a radius from anywhere in Utah cannot
 * reach another state, so nothing else is needed.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class SameDay {
	const METHOD_ID = 'luma_same_day';
	const TZ        = 'America/Denver';

	private static ?array $zips = null;

	public static function init(): void {
		add_filter( 'woocommerce_shipping_methods', [ __CLASS__, 'register_method' ] );
		add_filter( 'woocommerce_cart_shipping_packages', [ __CLASS__, 'bust_rate_cache' ] );
		add_action( 'woocommerce_checkout_create_order', [ __CLASS__, 'stamp_order' ], 10, 2 );
		add_action( 'wp_ajax_luma_same_day_check', [ __CLASS__, 'ajax_check' ] );
		add_action( 'wp_ajax_nopriv_luma_same_day_check', [ __CLASS__, 'ajax_check' ] );
		add_shortcode( 'luma_same_day_check', [ __CLASS__, 'shortcode' ] );
	}

	/* ---------- Configuration ---------- */

	public static function enabled(): bool {
		return '1' === (string) Settings::get( 'same_day_enabled' ) && null !== self::origin();
	}

	/** Origin point, or null until the address has been geocoded on the settings screen. */
	public static function origin(): ?array {
		$lat = (float) Settings::get( 'same_day_lat' );
		$lng = (float) Settings::get( 'same_day_lng' );
		return ( $lat && $lng ) ? [ 'lat' => $lat, 'lng' => $lng ] : null;
	}

	public static function radius(): float {
		return max( 1, (float) Settings::get( 'same_day_radius' ) );
	}

	/** [hour, minute] of the cutoff on the local clock. */
	public static function cutoff(): array {
		$parts = explode( ':', (string) Settings::get( 'same_day_cutoff' ) );
		return [ (int) ( $parts[0] ?? 13 ), (int) ( $parts[1] ?? 0 ) ];
	}

	/** ISO weekday numbers (1 = Monday … 7 = Sunday) on which same-day runs. */
	public static function open_days(): array {
		return array_map( 'intval', array_filter( explode( ',', (string) Settings::get( 'same_day_days' ) ), 'is_numeric' ) );
	}

	/** Human label for copy — "within about 28 miles of Provo". */
	public static function area_label(): string {
		return sprintf( 'within about %d miles of %s', (int) self::radius(), (string) Settings::get( 'same_day_place' ) ?: 'our Provo office' );
	}

	public static function cutoff_label(): string {
		[ $h, $m ] = self::cutoff();
		$dt = ( new \DateTimeImmutable( 'today', new \DateTimeZone( self::TZ ) ) )->setTime( $h, $m );
		return $dt->format( 'g:i a' ) . ' MT';
	}

	public static function days_label(): string {
		$names = [ 1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
		$days  = self::open_days();
		sort( $days );
		if ( $days === [ 1, 2, 3, 4, 5 ] ) {
			return 'Monday to Friday';
		}
		if ( $days === [ 1, 2, 3, 4, 5, 6 ] ) {
			return 'Monday to Saturday';
		}
		if ( count( $days ) === 7 ) {
			return 'every day';
		}
		return implode( ', ', array_map( fn( $d ) => $names[ $d ] ?? '', $days ) );
	}

	/** One sentence, true by construction, for the promise tile / FAQ / shipping page. */
	public static function promise(): string {
		if ( ! self::enabled() ) {
			return '';
		}
		return sprintf( 'Free same-day delivery %s, %s, for orders placed before %s. Holidays excluded.', self::area_label(), self::days_label(), self::cutoff_label() );
	}

	/* ---------- Geometry ---------- */

	private static function zips(): array {
		if ( null === self::$zips ) {
			$raw        = file_get_contents( LUMA_CORE_DIR . 'data/ut-zips.json' );
			self::$zips = $raw ? (array) json_decode( $raw, true ) : [];
		}
		return self::$zips;
	}

	public static function miles( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
		$r    = 3958.7613;
		$dlat = deg2rad( $lat2 - $lat1 );
		$dlng = deg2rad( $lng2 - $lng1 );
		$h    = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) ** 2;
		return 2 * $r * asin( sqrt( $h ) );
	}

	/** Distance from origin to the ZIP centroid, or null if unknown / no origin. */
	public static function zip_distance( string $zip ): ?float {
		$zip = substr( preg_replace( '/\D/', '', $zip ), 0, 5 );
		$o   = self::origin();
		$z   = self::zips()[ $zip ] ?? null;
		if ( ! $o || ! $z ) {
			return null;
		}
		return self::miles( $o['lat'], $o['lng'], (float) $z['lat'], (float) $z['lng'] );
	}

	public static function zip_in_radius( string $zip ): bool {
		$d = self::zip_distance( $zip );
		return null !== $d && $d <= self::radius();
	}

	/* ---------- Calendar ---------- */

	public static function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', new \DateTimeZone( self::TZ ) );
	}

	/** Observed US federal holidays plus Utah's Pioneer Day, as Y-m-d strings. */
	public static function holidays( int $year ): array {
		$fixed = [ "$year-01-01", "$year-06-19", "$year-07-04", "$year-07-24", "$year-11-11", "$year-12-25" ];
		$nth   = static function ( string $spec ) use ( $year ): string {
			return ( new \DateTimeImmutable( "$spec $year", new \DateTimeZone( self::TZ ) ) )->format( 'Y-m-d' );
		};
		$floating = [
			$nth( 'third monday of january' ),
			$nth( 'third monday of february' ),
			$nth( 'last monday of may' ),
			$nth( 'first monday of september' ),
			$nth( 'second monday of october' ),
			$nth( 'fourth thursday of november' ),
		];
		$out = [];
		foreach ( $fixed as $d ) {
			$dt = new \DateTimeImmutable( $d, new \DateTimeZone( self::TZ ) );
			$w  = (int) $dt->format( 'N' );
			if ( 6 === $w ) {
				$dt = $dt->modify( '-1 day' );
			} elseif ( 7 === $w ) {
				$dt = $dt->modify( '+1 day' );
			}
			$out[] = $dt->format( 'Y-m-d' );
		}
		$out = array_merge( $out, $floating );
		foreach ( preg_split( '/\s+/', (string) Settings::get( 'same_day_closed_dates' ) ) as $extra ) {
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $extra ) ) {
				$out[] = $extra;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function is_open_day( \DateTimeImmutable $day ): bool {
		$day = $day->setTimezone( new \DateTimeZone( self::TZ ) );
		if ( ! in_array( (int) $day->format( 'N' ), self::open_days(), true ) ) {
			return false;
		}
		if ( '1' === (string) Settings::get( 'same_day_holidays' ) && in_array( $day->format( 'Y-m-d' ), self::holidays( (int) $day->format( 'Y' ) ), true ) ) {
			return false;
		}
		return true;
	}

	/** True while orders placed right now would go out today. */
	public static function window_open( ?\DateTimeImmutable $now = null ): bool {
		if ( ! self::enabled() ) {
			return false;
		}
		$now = ( $now ?? self::now() )->setTimezone( new \DateTimeZone( self::TZ ) );
		if ( ! self::is_open_day( $now ) ) {
			return false;
		}
		[ $h, $m ] = self::cutoff();
		return ( (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' ) ) < ( $h * 60 + $m );
	}

	/** Next moment the window opens, for "order by …" copy. */
	public static function next_open( ?\DateTimeImmutable $now = null ): ?\DateTimeImmutable {
		if ( ! self::enabled() ) {
			return null;
		}
		$now = ( $now ?? self::now() )->setTimezone( new \DateTimeZone( self::TZ ) );
		[ $h, $m ] = self::cutoff();
		$day = $now->setTime( 0, 0 );
		for ( $i = 0; $i < 14; $i++ ) {
			$cut = $day->setTime( $h, $m );
			if ( self::is_open_day( $day ) && $cut > $now ) {
				return $cut;
			}
			$day = $day->modify( '+1 day' );
		}
		return null;
	}

	public static function eligible( string $zip, ?\DateTimeImmutable $now = null ): bool {
		return self::zip_in_radius( $zip ) && self::window_open( $now );
	}

	/* ---------- WooCommerce wiring ---------- */

	public static function register_method( array $methods ): array {
		require_once LUMA_CORE_DIR . 'includes/class-same-day-method.php';
		$methods[ self::METHOD_ID ] = 'Luma\\Core\\SameDayMethod';
		return $methods;
	}

	/** Woo caches rates per package hash; fold the window state in so the rate flips at the cutoff. */
	public static function bust_rate_cache( array $packages ): array {
		$state = self::window_open() ? 'open-' . self::now()->format( 'Y-m-d' ) : 'closed-' . self::now()->format( 'Y-m-d-H' );
		foreach ( $packages as &$p ) {
			$p['luma_same_day'] = $state;
		}
		return $packages;
	}

	public static function stamp_order( \WC_Order $order, array $data ): void {
		foreach ( $order->get_shipping_methods() as $ship ) {
			if ( self::METHOD_ID === $ship->get_method_id() ) {
				$order->update_meta_data( '_luma_same_day', self::now()->format( 'Y-m-d' ) );
				$order->add_order_note( 'Same-day delivery selected — deliver today (' . self::now()->format( 'D M j' ) . ').' );
			}
		}
	}

	/* ---------- Public checker ---------- */

	public static function check_message( string $zip ): array {
		$zip = substr( preg_replace( '/\D/', '', $zip ), 0, 5 );
		if ( strlen( $zip ) !== 5 ) {
			return [ 'ok' => false, 'state' => 'invalid', 'title' => 'Enter a five-digit ZIP code.', 'message' => '' ];
		}
		if ( ! self::enabled() ) {
			return [ 'ok' => false, 'state' => 'off', 'title' => 'Good news: every order ships free.', 'message' => 'Same-day delivery is paused right now, but your order ships free by next-day service as soon as it is released. No minimum, no code.' ];
		}
		if ( ! self::zip_in_radius( $zip ) ) {
			return [ 'ok' => false, 'state' => 'outside', 'title' => 'Good news: your order still ships free, right away.', 'message' => sprintf( 'ZIP %s is outside our current same-day area, but every order ships free by next-day service the moment it is released. No minimum, no code.', $zip ) ];
		}
		if ( self::window_open() ) {
			return [ 'ok' => true, 'state' => 'open', 'title' => '🎉 Congratulations — you qualify for same-day delivery!', 'message' => sprintf( 'Order by %s today and it is delivered today, free. No minimum, no code.', self::cutoff_label() ) ];
		}
		$next = self::next_open();
		return [ 'ok' => true, 'state' => 'later', 'title' => '🎉 Congratulations — your address qualifies for same-day delivery!', 'message' => sprintf( 'Today\'s window has closed. Order before %s and it is delivered that same day, free. Order now and it ships free next-day instead.', $next ? self::cutoff_label() . ' on ' . $next->format( 'l, F j' ) : self::cutoff_label() . ' on the next open day' ) ];
	}

	public static function ajax_check(): void {
		$zip = sanitize_text_field( wp_unslash( $_REQUEST['zip'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		wp_send_json( self::check_message( $zip ) );
	}

	/** Markup only; the theme's site.js binds every .sd-check form (works for markup it injects too). */
	public static function shortcode(): string {
		if ( ! self::enabled() ) {
			return '';
		}
		return '<div class="sd-check" id="same-day" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '">'
			. '<p class="sd-check-kicker">Same-day delivery</p>'
			. '<p class="sd-check-lede">' . esc_html( self::promise() ) . '</p>'
			. '<form class="sd-check-form" autocomplete="postal-code"><label class="sd-check-label" for="sdZip">See if your address qualifies</label>'
			. '<div class="sd-check-row"><input id="sdZip" class="sd-check-input" inputmode="numeric" maxlength="5" placeholder="ZIP code" required><button class="btn btn-primary" type="submit">Check my ZIP</button></div></form>'
			. '<div class="sd-check-out" role="status" aria-live="polite"></div></div>';
	}
}
