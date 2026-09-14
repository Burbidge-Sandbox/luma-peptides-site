<?php
/**
 * Lots and certificates of analysis (WOO-MIGRATION §4.2).
 *
 * Step 2 of the build fills in the admin meta box, product↔lot linking,
 * lookup shortcode and COA library. This file registers the post type and
 * the meta schema so seeding can start.
 */

namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Lots {

	const CPT = 'luma_lot';

	/** Meta keys → sanitize callback. Lot number is the post title. */
	public static function schema(): array {
		return [
			'_lot_product_id'   => 'absint',
			'_lot_variation_id' => 'absint',
			'_lot_lab'          => 'sanitize_text_field',
			'_lot_tested'       => 'sanitize_text_field', // YYYY-MM-DD
			'_lot_method'       => 'sanitize_text_field', // e.g. HPLC, LC-MS
			'_lot_purity'       => 'sanitize_text_field', // e.g. 99.4%
			'_lot_identity'     => 'sanitize_text_field',
			'_lot_net_content'  => 'sanitize_text_field',
			'_lot_endotoxin'    => 'sanitize_text_field',
			'_lot_status'       => 'sanitize_text_field', // PASS / FAIL
			'_lot_expires'      => 'sanitize_text_field', // YYYY-MM
			'_lot_coa_id'       => 'absint',              // attachment (PDF)
			'_lot_photo_id'     => 'absint',              // attachment (vial photo)
			'_lot_qty_received' => 'absint',
			'_lot_qty_remaining'=> 'absint',
		];
	}

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'meta_box' ] );
		add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save_meta' ], 10, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'column' ], 10, 2 );
		add_shortcode( 'luma_lot_lookup', [ __CLASS__, 'shortcode_lookup' ] );
		add_shortcode( 'luma_coa_library', [ __CLASS__, 'shortcode_library' ] );
	}

	/* ---------- Admin: meta box ---------- */

	public static function meta_box(): void {
		add_meta_box( 'luma_lot', 'Lot details', [ __CLASS__, 'render_meta_box' ], self::CPT, 'normal', 'high' );
	}

	public static function labels(): array {
		return [
			'_lot_product_id'   => 'Product ID',
			'_lot_variation_id' => 'Variation ID (0 if simple)',
			'_lot_lab'          => 'Testing lab',
			'_lot_tested'       => 'Test date (YYYY-MM-DD)',
			'_lot_method'       => 'Method (e.g. HPLC, LC-MS)',
			'_lot_purity'       => 'Assay purity (e.g. 99.4%)',
			'_lot_identity'     => 'Identity result',
			'_lot_net_content'  => 'Net content',
			'_lot_endotoxin'    => 'Endotoxin',
			'_lot_status'       => 'Status (PENDING / PASS / FAIL)',
			'_lot_expires'      => 'Expiry (YYYY-MM)',
			'_lot_coa_id'       => 'COA attachment ID (PDF)',
			'_lot_photo_id'     => 'Vial photo attachment ID',
			'_lot_qty_received' => 'Qty received',
			'_lot_qty_remaining'=> 'Qty remaining',
		];
	}

	public static function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'luma_lot_save', 'luma_lot_nonce' );
		echo '<table class="form-table">';
		foreach ( self::labels() as $key => $label ) {
			$v = get_post_meta( $post->ID, $key, true );
			printf(
				'<tr><th><label for="%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="%1$s" name="%1$s" value="%3$s"></td></tr>',
				esc_attr( $key ), esc_html( $label ), esc_attr( (string) $v )
			);
		}
		echo '</table><p class="description">Lot number is the title, format LP-YYMM-CODE. Upload the COA PDF to the Media Library and paste its attachment ID above. Analytical values must match the COA exactly.</p>';
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['luma_lot_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['luma_lot_nonce'] ), 'luma_lot_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		foreach ( self::schema() as $key => $sanitize ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, call_user_func( $sanitize, wp_unslash( $_POST[ $key ] ) ) );
			}
		}
	}

	public static function columns( array $cols ): array {
		return [ 'cb' => $cols['cb'], 'title' => 'Lot', 'lot_product' => 'Product', 'lot_status' => 'Status', 'lot_purity' => 'Purity', 'lot_tested' => 'Tested', 'lot_qty' => 'Remaining' ];
	}

	public static function column( string $col, int $post_id ): void {
		switch ( $col ) {
			case 'lot_product':
				$pid = (int) get_post_meta( $post_id, '_lot_variation_id', true ) ?: (int) get_post_meta( $post_id, '_lot_product_id', true );
				$p   = $pid ? wc_get_product( $pid ) : null;
				echo $p ? esc_html( $p->get_name() ) : '—';
				break;
			case 'lot_status':
				echo esc_html( (string) get_post_meta( $post_id, '_lot_status', true ) );
				break;
			case 'lot_purity':
				echo esc_html( (string) get_post_meta( $post_id, '_lot_purity', true ) );
				break;
			case 'lot_tested':
				echo esc_html( (string) get_post_meta( $post_id, '_lot_tested', true ) );
				break;
			case 'lot_qty':
				echo esc_html( (string) get_post_meta( $post_id, '_lot_qty_remaining', true ) );
				break;
		}
	}

	/* ---------- Front end ---------- */

	/** Public-safe view of a lot. */
	public static function view( \WP_Post $lot ): array {
		$pid  = (int) get_post_meta( $lot->ID, '_lot_variation_id', true ) ?: (int) get_post_meta( $lot->ID, '_lot_product_id', true );
		$prod = $pid ? wc_get_product( $pid ) : null;
		$coa  = (int) get_post_meta( $lot->ID, '_lot_coa_id', true );
		$pho  = (int) get_post_meta( $lot->ID, '_lot_photo_id', true );
		$get  = fn( $k ) => (string) get_post_meta( $lot->ID, $k, true );
		return [
			'lot'         => $lot->post_title,
			'product'     => $prod ? $prod->get_name() : '',
			'product_url' => $prod ? $prod->get_permalink() : '',
			'lab'         => $get( '_lot_lab' ),
			'tested'      => $get( '_lot_tested' ),
			'method'      => $get( '_lot_method' ),
			'purity'      => $get( '_lot_purity' ),
			'identity'    => $get( '_lot_identity' ),
			'net_content' => $get( '_lot_net_content' ),
			'endotoxin'   => $get( '_lot_endotoxin' ),
			'status'      => $get( '_lot_status' ) ?: 'PENDING',
			'expires'     => $get( '_lot_expires' ),
			'coa_url'     => $coa ? wp_get_attachment_url( $coa ) : '',
			'photo_url'   => $pho ? wp_get_attachment_image_url( $pho, 'medium' ) : '',
		];
	}

	private static function rate_limited(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$key = 'luma_lookup_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 10 ) {
			return true;
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
		return false;
	}

	public static function shortcode_lookup(): string {
		$q = isset( $_GET['lot'] ) ? sanitize_text_field( wp_unslash( $_GET['lot'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$q = strtoupper( preg_replace( '/[\s]+/', '', $q ) );
		if ( $q && ! preg_match( '/^LP-/', $q ) && preg_match( '/^LP\d{4}[A-Z0-9]+$/', $q ) ) {
			$q = preg_replace( '/^LP(\d{4})([A-Z0-9]+)$/', 'LP-$1-$2', $q ); // tolerate missing hyphens
		}
		ob_start();
		echo '<form class="lot-lookup" method="get" action="">';
		echo '<label for="lot" class="mono">' . esc_html__( 'Lot number (printed on the vial)', 'luma-core' ) . '</label>';
		echo '<div class="lot-lookup-row"><input type="text" id="lot" name="lot" value="' . esc_attr( $q ) . '" placeholder="LP-2609-BPC10" autocomplete="off" class="mono" required> ';
		echo '<button class="btn btn-primary" type="submit">' . esc_html__( 'Look up', 'luma-core' ) . '</button></div></form>';
		if ( $q ) {
			if ( self::rate_limited() ) {
				echo '<p class="lot-result-none">' . esc_html__( 'Too many lookups. Try again in a minute.', 'luma-core' ) . '</p>';
			} else {
				$lot = self::find( $q );
				if ( ! $lot ) {
					echo '<p class="lot-result-none">' . esc_html__( 'No lot with that number. Check the vial label and try again.', 'luma-core' ) . '</p>';
				} else {
					self::render_card( self::view( $lot ) );
				}
			}
		}
		return (string) ob_get_clean();
	}

	public static function render_card( array $v ): void {
		$rows = [
			'Compound'      => $v['product'],
			'Lot'           => $v['lot'],
			'Testing lab'   => $v['lab'],
			'Method'        => $v['method'],
			'Assay purity'  => $v['purity'],
			'Identity'      => $v['identity'],
			'Net content'   => $v['net_content'],
			'Endotoxin'     => $v['endotoxin'],
			'Test date'     => $v['tested'],
			'Status'        => $v['status'],
			'Retest by'     => $v['expires'],
		];
		echo '<div class="lot-result"><table class="spec-table">';
		foreach ( $rows as $k => $val ) {
			if ( $val === '' ) {
				continue;
			}
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
		}
		echo '</table>';
		if ( $v['status'] === 'PENDING' ) {
			echo '<p class="ruo-notice">' . esc_html__( 'Third-party testing for this lot is in progress. The certificate of analysis is published here when it is issued.', 'luma-core' ) . '</p>';
		}
		if ( $v['coa_url'] ) {
			echo '<p><a class="btn btn-outline" href="' . esc_url( $v['coa_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open certificate of analysis (PDF)', 'luma-core' ) . '</a></p>';
		}
		if ( $v['photo_url'] ) {
			echo '<img class="lot-photo" src="' . esc_url( $v['photo_url'] ) . '" alt="' . esc_attr( 'Vial photo, lot ' . $v['lot'] ) . '" loading="lazy">';
		}
		echo '</div>';
	}

	public static function shortcode_library(): string {
		$lots = get_posts( [ 'post_type' => self::CPT, 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'DESC' ] );
		if ( ! $lots ) {
			return '<p>' . esc_html__( 'No lots published yet.', 'luma-core' ) . '</p>';
		}
		ob_start();
		echo '<div class="table-scroll"><table class="coa-table"><thead><tr><th>Lot</th><th>Compound</th><th>Lab</th><th>Tested</th><th>Purity</th><th>Status</th><th>COA</th></tr></thead><tbody>';
		foreach ( $lots as $lot ) {
			$v = self::view( $lot );
			echo '<tr><td class="mono"><a href="?lot=' . esc_attr( $v['lot'] ) . '">' . esc_html( $v['lot'] ) . '</a></td><td>' . esc_html( $v['product'] ) . '</td><td>' . esc_html( $v['lab'] ) . '</td><td class="mono">' . esc_html( $v['tested'] ?: '—' ) . '</td><td class="mono">' . esc_html( $v['purity'] ?: '—' ) . '</td><td class="mono">' . esc_html( $v['status'] ) . '</td><td>' . ( $v['coa_url'] ? '<a href="' . esc_url( $v['coa_url'] ) . '" target="_blank" rel="noopener">PDF</a>' : '—' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		return (string) ob_get_clean();
	}

	public static function register(): void {
		register_post_type( self::CPT, [
			'labels'          => [
				'name'          => 'Lots',
				'singular_name' => 'Lot',
				'add_new_item'  => 'Add lot',
				'edit_item'     => 'Edit lot',
			],
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'woocommerce',
			'show_in_rest'    => false, // lookup goes through our own rate-limited endpoint later
			'supports'        => [ 'title' ],
			'capability_type' => 'product',
			'map_meta_cap'    => true,
			'menu_icon'       => 'dashicons-clipboard',
		] );

		foreach ( self::schema() as $key => $sanitize ) {
			register_post_meta( self::CPT, $key, [
				'type'              => str_starts_with( $key, '_lot_qty' ) || str_ends_with( $key, '_id' ) ? 'integer' : 'string',
				'single'            => true,
				'sanitize_callback' => $sanitize,
				'auth_callback'     => fn() => current_user_can( 'manage_woocommerce' ),
			] );
		}
	}

	/** Exact-match lookup by lot number. Returns null when not found. */
	public static function find( string $lot_number ): ?\WP_Post {
		$lot_number = strtoupper( trim( $lot_number ) );
		if ( ! preg_match( '/^LP-\d{4}-[A-Z0-9]{2,8}$/', $lot_number ) ) {
			return null;
		}
		$q = get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'title'          => $lot_number,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		] );
		return $q[0] ?? null;
	}
}
