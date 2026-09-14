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
		return $out;
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
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
