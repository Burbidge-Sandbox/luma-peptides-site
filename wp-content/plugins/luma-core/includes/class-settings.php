<?php
/**
 * Operational facts with an expiry date (CLAUDE.md rule 11) live here, not in
 * templates. Verify before every deploy. Empty = not shown.
 */

namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Settings {

	const OPTION = 'luma_core_settings';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register' ] );
	}

	public static function defaults(): array {
		return [
			'facts'           => "Third-party tested\nCOA per lot\nLot-numbered vials\nShips in 1 business day",
			'facts_verified'  => '',
			'alert_email'     => 'info@lumaresearchco.com',
			'ga4_id'          => '',
			'meta_pixel_id'   => '',
			/* Same-day delivery by radius — origin is entered here, never committed. */
			'same_day_enabled'      => '0',
			'same_day_origin'       => '',
			'same_day_lat'          => '',
			'same_day_lng'          => '',
			'same_day_place'        => 'Provo',
			'same_day_radius'       => '28',
			'same_day_cutoff'       => '13:00',
			'same_day_days'         => '1,2,3,4,5,6',
			'same_day_holidays'     => '1',
			'same_day_closed_dates' => '',
		];
	}

	public static function get( string $key ) {
		$opts = wp_parse_args( (array) get_option( self::OPTION, [] ), self::defaults() );
		return $opts[ $key ] ?? null;
	}

	/** Fact strip lines; suppressed entirely if not re-verified within 30 days. */
	public static function facts(): array {
		$verified = (string) self::get( 'facts_verified' );
		if ( ! $verified || strtotime( $verified ) < strtotime( '-30 days' ) ) {
			return []; // stale facts are worse than no facts
		}
		return array_filter( array_map( 'trim', explode( "\n", (string) self::get( 'facts' ) ) ) );
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', 'Luma Core', 'Luma Core', 'manage_woocommerce', 'luma-core', [ __CLASS__, 'page' ] );
	}

	public static function register(): void {
		register_setting( 'luma_core', self::OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [ __CLASS__, 'sanitize' ],
		] );
	}

	public static function sanitize( $in ): array {
		$in  = (array) $in;
		$out = [];
		$out['facts']          = sanitize_textarea_field( $in['facts'] ?? '' );
		$out['facts_verified'] = ! empty( $in['facts_verified'] ) ? sanitize_text_field( $in['facts_verified'] ) : '';
		$out['alert_email']    = sanitize_email( $in['alert_email'] ?? '' );
		$out['ga4_id']         = preg_replace( '/[^A-Z0-9\-]/', '', strtoupper( $in['ga4_id'] ?? '' ) );
		$out['meta_pixel_id']  = preg_replace( '/\D/', '', $in['meta_pixel_id'] ?? '' );

		$prev                          = wp_parse_args( (array) get_option( self::OPTION, [] ), self::defaults() );
		$out['same_day_enabled']       = empty( $in['same_day_enabled'] ) ? '0' : '1';
		$out['same_day_origin']        = sanitize_text_field( $in['same_day_origin'] ?? '' );
		$out['same_day_place']         = sanitize_text_field( $in['same_day_place'] ?? 'Provo' ) ?: 'Provo';
		$out['same_day_radius']        = (string) max( 1, min( 100, (float) ( $in['same_day_radius'] ?? 28 ) ) );
		$out['same_day_cutoff']        = preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $in['same_day_cutoff'] ?? '' ) ? $in['same_day_cutoff'] : '13:00';
		$days                          = array_filter( array_map( 'intval', (array) ( $in['same_day_days'] ?? [] ) ), fn( $d ) => $d >= 1 && $d <= 7 );
		$out['same_day_days']          = implode( ',', $days );
		$out['same_day_holidays']      = empty( $in['same_day_holidays'] ) ? '0' : '1';
		$out['same_day_closed_dates']  = sanitize_textarea_field( $in['same_day_closed_dates'] ?? '' );
		$out['same_day_lat']           = $prev['same_day_lat'];
		$out['same_day_lng']           = $prev['same_day_lng'];
		if ( $out['same_day_origin'] && $out['same_day_origin'] !== $prev['same_day_origin'] ) {
			$pt = self::geocode( $out['same_day_origin'] );
			if ( $pt ) {
				$out['same_day_lat'] = (string) $pt['lat'];
				$out['same_day_lng'] = (string) $pt['lng'];
			} else {
				$out['same_day_lat'] = '';
				$out['same_day_lng'] = '';
				add_settings_error( 'luma_core', 'luma_geocode', 'Could not locate that origin address. Same-day delivery stays off until it resolves — check the address and save again.', 'error' );
			}
		} elseif ( ! $out['same_day_origin'] ) {
			$out['same_day_lat'] = '';
			$out['same_day_lng'] = '';
		}
		return $out;
	}

	/** One-off geocode of the same-day origin (OpenStreetMap Nominatim; no key, ~1 req/s, attribution in the admin UI). */
	public static function geocode( string $address ): ?array {
		$res = wp_remote_get(
			add_query_arg( [ 'format' => 'json', 'limit' => 1, 'countrycodes' => 'us', 'q' => $address ], 'https://nominatim.openstreetmap.org/search' ),
			[ 'timeout' => 10, 'user-agent' => 'luma-core/' . LUMA_CORE_VERSION . ' (' . home_url() . ')' ]
		);
		if ( is_wp_error( $res ) ) {
			return null;
		}
		$rows = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $rows[0]['lat'] ) || empty( $rows[0]['lon'] ) ) {
			return null;
		}
		return [ 'lat' => (float) $rows[0]['lat'], 'lng' => (float) $rows[0]['lon'] ];
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$o = wp_parse_args( (array) get_option( self::OPTION, [] ), self::defaults() );
		?>
		<div class="wrap">
			<h1>Luma Core</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'luma_core' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="luma_facts">Fact strip (one per line)</label></th>
						<td>
							<textarea id="luma_facts" name="<?php echo esc_attr( self::OPTION ); ?>[facts]" rows="5" class="large-text"><?php echo esc_textarea( $o['facts'] ); ?></textarea>
							<p class="description">Operational facts only — shipping, testing, documentation. Never outcomes. These are hidden automatically if not re-verified within 30 days.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="luma_facts_verified">Facts verified on</label></th>
						<td>
							<input type="date" id="luma_facts_verified" name="<?php echo esc_attr( self::OPTION ); ?>[facts_verified]" value="<?php echo esc_attr( $o['facts_verified'] ); ?>">
							<p class="description">Set this to today's date after confirming every line above is currently true.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="luma_alert_email">Owner alert email</label></th>
						<td><input type="email" id="luma_alert_email" name="<?php echo esc_attr( self::OPTION ); ?>[alert_email]" value="<?php echo esc_attr( $o['alert_email'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="luma_ga4">GA4 measurement ID</label></th>
						<td><input type="text" id="luma_ga4" name="<?php echo esc_attr( self::OPTION ); ?>[ga4_id]" value="<?php echo esc_attr( $o['ga4_id'] ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX"></td>
					</tr>
					<tr>
						<th scope="row"><label for="luma_pixel">Meta pixel ID</label></th>
						<td><input type="text" id="luma_pixel" name="<?php echo esc_attr( self::OPTION ); ?>[meta_pixel_id]" value="<?php echo esc_attr( $o['meta_pixel_id'] ); ?>" class="regular-text"></td>
					</tr>
				</table>

				<h2>Same-day delivery</h2>
				<p class="description">Appears at checkout only when the ZIP centroid is within the radius <em>and</em> the order is placed before the cutoff on an open day (America/Denver clock). The origin address is stored here only. Geocoding by OpenStreetMap Nominatim.</p>
				<?php $days = array_map( 'intval', explode( ',', (string) $o['same_day_days'] ) ); $names = [ 1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ]; ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Enabled</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[same_day_enabled]" value="1" <?php checked( '1', $o['same_day_enabled'] ); ?>> Offer same-day delivery</label>
						<?php if ( $o['same_day_lat'] ) : ?><p class="description">Origin resolved to <?php echo esc_html( $o['same_day_lat'] . ', ' . $o['same_day_lng'] ); ?>.</p><?php elseif ( $o['same_day_origin'] ) : ?><p class="description" style="color:#b32d2e">Origin not resolved — same-day is off.</p><?php endif; ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="luma_sd_origin">Origin address</label></th>
						<td><input type="text" id="luma_sd_origin" name="<?php echo esc_attr( self::OPTION ); ?>[same_day_origin]" value="<?php echo esc_attr( $o['same_day_origin'] ); ?>" class="large-text" placeholder="Street, City, ST ZIP"><p class="description">Full street address. Re-geocoded whenever it changes.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="luma_sd_place">Place name in copy</label></th>
						<td><input type="text" id="luma_sd_place" name="<?php echo esc_attr( self::OPTION ); ?>[same_day_place]" value="<?php echo esc_attr( $o['same_day_place'] ); ?>" class="regular-text"><p class="description">Shown publicly as "within about N miles of <em>Place</em>". Use a city, not the street.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="luma_sd_radius">Radius (miles)</label></th>
						<td><input type="number" id="luma_sd_radius" name="<?php echo esc_attr( self::OPTION ); ?>[same_day_radius]" value="<?php echo esc_attr( $o['same_day_radius'] ); ?>" min="1" max="100" step="0.5" class="small-text"><p class="description">Straight line from the origin to the ZIP code's centre point.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="luma_sd_cutoff">Order cutoff (Mountain time)</label></th>
						<td><input type="time" id="luma_sd_cutoff" name="<?php echo esc_attr( self::OPTION ); ?>[same_day_cutoff]" value="<?php echo esc_attr( $o['same_day_cutoff'] ); ?>"><p class="description">Orders placed at or after this time get next-day instead.</p></td>
					</tr>
					<tr>
						<th scope="row">Open days</th>
						<td><?php foreach ( $names as $n => $lbl ) : ?><label style="margin-right:1em"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[same_day_days][]" value="<?php echo (int) $n; ?>" <?php checked( in_array( $n, $days, true ) ); ?>> <?php echo esc_html( $lbl ); ?></label><?php endforeach; ?></td>
					</tr>
					<tr>
						<th scope="row">Holidays</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[same_day_holidays]" value="1" <?php checked( '1', $o['same_day_holidays'] ); ?>> Closed on observed US federal holidays and Pioneer Day</label>
						<p><label for="luma_sd_closed">Extra closed dates (one per line, YYYY-MM-DD)</label><br><textarea id="luma_sd_closed" name="<?php echo esc_attr( self::OPTION ); ?>[same_day_closed_dates]" rows="3" class="regular-text"><?php echo esc_textarea( $o['same_day_closed_dates'] ); ?></textarea></p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
