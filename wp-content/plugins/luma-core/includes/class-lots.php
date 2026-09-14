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
