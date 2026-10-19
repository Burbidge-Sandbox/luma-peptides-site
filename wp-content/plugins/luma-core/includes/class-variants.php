<?php
/**
 * Landing-page split test: four versions of the Research labs page on the
 * landing domain, one per ad set.
 *
 *   /           claim-25  25% off, name + email + phone form (the original page)
 *   /claim-10   claim-10  10% off, same form
 *   /offer-25   offer-25  25% off, no form: one tap and the discount is applied
 *   /offer-10   offer-10  10% off, no form
 *
 * Every version gives the visitor their own single-use code, so a sale is
 * attributed to the page that issued the code that was used. Form pages issue
 * LAB25-/LAB10- codes tied to the lead's email (Leads::claim). No-form pages
 * issue GO25-/GO10- codes: single use, first order only, expiring after
 * `lab_offer_days`, not tied to an email (POST /wp-json/luma/v1/go).
 *
 * Results: WooCommerce → Landing tests (page views, codes issued, orders,
 * revenue per version).
 */

namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Variants {

	const META   = '_luma_variant';       // on the coupon (and on leads as _lead_variant)
	const VIEWS  = 'luma_variant_views';  // option: [ variant => [ 'Y-m-d' => n ] ]
	const START  = 'luma_variants_start'; // option: date the test began (report default)
	const GO_SRC = '_luma_go_src';        // on GO coupons: UTMs at the click
	const ISSUED = 'luma_variant_issued'; // option: [ variant => [ 'Y-m-d' => n ] ] codes created

	const VARIANTS = [
		'claim-25' => [ 'pct' => 25, 'form' => true, 'path' => '', 'label' => '25% off · lead form' ],
		'claim-10' => [ 'pct' => 10, 'form' => true, 'path' => 'claim-10', 'label' => '10% off · lead form' ],
		'offer-25' => [ 'pct' => 25, 'form' => false, 'path' => 'offer-25', 'label' => '25% off · no form' ],
		'offer-10' => [ 'pct' => 10, 'form' => false, 'path' => 'offer-10', 'label' => '10% off · no form' ],
	];

	/** Extra paths that show a version (the original page is also reachable by name). */
	const ALIASES = [ 'claim-25' => 'claim-25' ];

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 60 );
		add_action( 'luma_variants_cleanup', [ __CLASS__, 'cleanup' ] );
		add_action( 'init', function () {
			if ( ! get_option( self::START ) ) {
				update_option( self::START, wp_date( 'Y-m-d' ), false );
			}
			if ( ! wp_next_scheduled( 'luma_variants_cleanup' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'luma_variants_cleanup' );
			}
		} );
	}

	/* ---------- Which version ---------- */

	public static function get( string $key ): array {
		$key = isset( self::VARIANTS[ $key ] ) ? $key : 'claim-25';
		return [ 'key' => $key ] + self::VARIANTS[ $key ];
	}

	/** Version for a request path ('' = landing root); null when the path is not a landing page. */
	public static function for_path( string $path ): ?string {
		$path = strtolower( trim( $path, '/' ) );
		foreach ( self::VARIANTS as $k => $v ) {
			if ( $v['path'] === $path ) {
				return $k;
			}
		}
		return self::ALIASES[ $path ] ?? null;
	}

	/** The version this request is showing. On the store, /labs/?v=offer-25 previews one. */
	public static function current(): array {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$key  = LabDomain::is_lab_host() ? self::for_path( $path ) : null;
		if ( ! $key && isset( $_GET['v'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$key = sanitize_key( wp_unslash( $_GET['v'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return self::get( (string) $key );
	}

	/** Server-side view counter (the landing page is never cached, so every view reaches PHP). Bots and prefetches are skipped. */
	public static function count_view( string $key ): void {
		$ua = strtolower( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
		if ( '' === $ua || preg_match( '/bot|crawl|spider|facebookexternalhit|preview|headless|lighthouse|curl|wget/', $ua ) || is_user_logged_in() ) {
			return;
		}
		$purpose = strtolower( (string) ( $_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? '' ) );
		if ( str_contains( $purpose, 'prefetch' ) ) {
			return;
		}
		$all = (array) get_option( self::VIEWS, [] );
		$day = wp_date( 'Y-m-d' );
		$all[ $key ][ $day ] = (int) ( $all[ $key ][ $day ] ?? 0 ) + 1;
		update_option( self::VIEWS, $all, false );
	}

	public static function count_issued( string $key ): void {
		$all = (array) get_option( self::ISSUED, [] );
		$day = wp_date( 'Y-m-d' );
		$all[ $key ][ $day ] = (int) ( $all[ $key ][ $day ] ?? 0 ) + 1;
		update_option( self::ISSUED, $all, false );
	}

	/** Which version issued this coupon. LAB codes made before the test began were all the 25% form page. */
	public static function of_coupon( \WC_Coupon $c ): string {
		$k = (string) $c->get_meta( self::META );
		if ( isset( self::VARIANTS[ $k ] ) ) {
			return $k;
		}
		return LabOffer::is_lab( $c ) ? 'claim-25' : '';
	}

	/* ---------- No-form pages: POST /wp-json/luma/v1/go ---------- */

	public static function routes(): void {
		register_rest_route( 'luma/v1', '/go', [
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => [ __CLASS__, 'go' ],
		] );
	}

	const GO_COOKIE = 'luma_go'; // on the landing domain: "variant:CODE", so a second tap reuses the code

	public static function go( \WP_REST_Request $r ) {
		nocache_headers();
		$p   = (array) ( $r->get_json_params() ?: $r->get_body_params() );
		$key = sanitize_key( (string) ( $p['variant'] ?? '' ) );
		if ( ! isset( self::VARIANTS[ $key ] ) || self::VARIANTS[ $key ]['form'] ) {
			return new \WP_Error( 'luma_variant', 'Unknown offer.', [ 'status' => 400 ] );
		}
		$v = self::get( $key );

		/* Same browser tapping again: hand back the code it already has. */
		$held = isset( $_COOKIE[ self::GO_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::GO_COOKIE ] ) ) : '';
		if ( $held && str_starts_with( $held, $key . ':' ) ) {
			$c = LabOffer::from_code( substr( $held, strlen( $key ) + 1 ) );
			if ( $c && 'ok' === LabOffer::usable( $c ) ) {
				return rest_ensure_response( [ 'redirect' => self::shop_url( $c, (array) ( $p['src'] ?? [] ) ) ] );
			}
		}

		if ( Lots::rate_limited( 'go', 8, 10 * MINUTE_IN_SECONDS ) ) {
			return new \WP_Error( 'luma_slow_down', 'Too many attempts. Please wait a few minutes and try again.', [ 'status' => 429 ] );
		}
		$c = LabOffer::create_coupon( '', 0, '', (int) $v['pct'], $key );
		if ( ! $c ) {
			return new \WP_Error( 'luma_save', 'Could not apply the discount right now. Please try again.', [ 'status' => 500 ] );
		}
		$src = [];
		foreach ( (array) ( $p['src'] ?? [] ) as $k => $val ) {
			if ( in_array( $k, Leads::SOURCE_KEYS, true ) ) {
				$src[ $k ] = substr( in_array( $k, [ 'referrer', 'landing' ], true ) ? esc_url_raw( (string) $val ) : sanitize_text_field( (string) $val ), 0, 500 );
			}
		}
		$c->update_meta_data( self::GO_SRC, $src );
		$c->update_meta_data( '_luma_go_ip', Lots::client_ip() );
		$c->save();

		if ( ! headers_sent() ) {
			$exp = $c->get_date_expires();
			setcookie( self::GO_COOKIE, $key . ':' . strtoupper( $c->get_code() ), [
				'expires'  => $exp ? $exp->getTimestamp() : time() + 14 * DAY_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			] );
		}
		return rest_ensure_response( [ 'redirect' => self::shop_url( $c, $src ) ] );
	}

	/** The store's shop page with the code applied on arrival (LabOffer::on_request), UTMs carried for the store's own pixel. */
	private static function shop_url( \WC_Coupon $c, array $src ): string {
		$args = [ 'coupon' => strtoupper( $c->get_code() ) ];
		foreach ( [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'fbclid' ] as $k ) {
			if ( ! empty( $src[ $k ] ) ) {
				$args[ $k ] = rawurlencode( sanitize_text_field( (string) $src[ $k ] ) );
			}
		}
		return LabDomain::store_shop_url( $args );
	}

	/** Daily: trash no-form codes that expired a week ago unused (taps that never bought). Used codes stay for the report. */
	public static function cleanup(): void {
		$ids = get_posts( [
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[ 'key' => self::META, 'value' => [ 'offer-25', 'offer-10' ], 'compare' => 'IN' ],
				[ 'key' => 'usage_count', 'value' => '0' ],
				[ 'key' => 'date_expires', 'value' => time() - WEEK_IN_SECONDS, 'compare' => '<', 'type' => 'NUMERIC' ],
			],
		] );
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true ); // the report counts codes issued separately (ISSUED), so nothing is lost
		}
	}

	/* ---------- Report: WooCommerce → Landing tests ---------- */

	public static function menu(): void {
		add_submenu_page( 'woocommerce', 'Landing tests', 'Landing tests', 'manage_woocommerce', 'luma-landing-tests', [ __CLASS__, 'report' ] );
	}

	/** @return array<string,array{views:int,issued:int,leads:int,orders:int,revenue:float}> */
	public static function stats( string $since ): array {
		$from = strtotime( $since . ' 00:00:00' ) ?: 0;
		$out  = [];
		foreach ( array_keys( self::VARIANTS ) as $k ) {
			$out[ $k ] = [ 'views' => 0, 'issued' => 0, 'leads' => 0, 'orders' => 0, 'revenue' => 0.0 ];
		}
		foreach ( (array) get_option( self::VIEWS, [] ) as $k => $days ) {
			foreach ( (array) $days as $day => $n ) {
				if ( isset( $out[ $k ] ) && $day >= $since ) {
					$out[ $k ]['views'] += (int) $n;
				}
			}
		}
		foreach ( (array) get_option( self::ISSUED, [] ) as $k => $days ) {
			foreach ( (array) $days as $day => $n ) {
				if ( isset( $out[ $k ] ) && $day >= $since ) {
					$out[ $k ]['issued'] += (int) $n;
				}
			}
		}
		/* Leads (form pages). */
		foreach ( get_posts( [ 'post_type' => Leads::CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'date_query' => [ [ 'after' => $since . ' 00:00:00', 'inclusive' => true ] ] ] ) as $lid ) {
			$k = (string) get_post_meta( $lid, '_lead_variant', true ) ?: 'claim-25';
			if ( isset( $out[ $k ] ) ) {
				$out[ $k ]['leads']++;
			}
		}
		/* Paid orders that used one of those codes. */
		$orders = wc_get_orders( [ 'status' => [ 'processing', 'completed', 'on-hold' ], 'limit' => -1, 'date_created' => '>=' . $from ] );
		foreach ( $orders as $o ) {
			foreach ( $o->get_coupon_codes() as $code ) {
				$c = LabOffer::from_code( $code );
				$k = $c ? self::of_coupon( $c ) : '';
				if ( $k ) {
					$out[ $k ]['orders']++;
					$out[ $k ]['revenue'] += (float) $o->get_total() - (float) $o->get_total_refunded();
					break;
				}
			}
		}
		return $out;
	}

	public static function report(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$since = isset( $_GET['since'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['since'] ) ? (string) $_GET['since'] : (string) get_option( self::START, wp_date( 'Y-m-d' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$s     = self::stats( $since );
		$pct   = fn( $a, $b ) => $b ? number_format( 100 * $a / $b, 1 ) . '%' : '—';
		?>
		<div class="wrap">
			<h1>Landing tests</h1>
			<p>Each ad set points at one page. A sale counts for the page that issued the code used at checkout. Page views are counted on the server (bots and signed-in admins skipped); compare them with Meta's landing-page views for each ad set.</p>
			<form method="get" style="margin:12px 0"><input type="hidden" name="page" value="luma-landing-tests"><label>Since <input type="date" name="since" value="<?php echo esc_attr( $since ); ?>"></label> <button class="button">Update</button></form>
			<table class="widefat striped" style="max-width:1100px">
				<thead><tr><th>Page</th><th>URL</th><th>Views</th><th>Codes issued</th><th>Leads</th><th>Orders</th><th>Revenue</th><th>Orders / views</th><th>Orders / codes</th><th>Revenue / view</th></tr></thead>
				<tbody>
				<?php foreach ( self::VARIANTS as $k => $v ) : $r = $s[ $k ]; ?>
					<tr>
						<td><b><?php echo esc_html( $v['label'] ); ?></b></td>
						<td><code><?php echo esc_html( LabDomain::host() ? LabDomain::host() . '/' . $v['path'] : '/labs/?v=' . $k ); ?></code></td>
						<td><?php echo (int) $r['views']; ?></td>
						<td><?php echo (int) $r['issued']; ?></td>
						<td><?php echo $v['form'] ? (int) $r['leads'] : '—'; ?></td>
						<td><b><?php echo (int) $r['orders']; ?></b></td>
						<td><?php echo wp_kses_post( wc_price( $r['revenue'] ) ); ?></td>
						<td><b><?php echo esc_html( $pct( $r['orders'], $r['views'] ) ); ?></b></td>
						<td><?php echo esc_html( $pct( $r['orders'], $r['issued'] ) ); ?></td>
						<td><?php echo $r['views'] ? wp_kses_post( wc_price( $r['revenue'] / $r['views'] ) ) : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">Revenue is order total after refunds (paid, processing, on-hold and completed orders). Codes issued: form pages = claims, no-form pages = taps on the button. Unused no-form codes are deleted a week after they expire.</p>
		</div>
		<?php
	}
}
