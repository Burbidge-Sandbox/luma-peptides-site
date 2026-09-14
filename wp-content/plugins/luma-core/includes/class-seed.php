<?php
/**
 * Catalogue seeder — creates/updates products, categories and lots from
 * data/catalogue.json + data/lots.json. Idempotent (keyed by SKU / lot number),
 * so it can be re-run after editing the JSON. Runs from WooCommerce → Luma
 * Core → Tools, because there is no SSH/WP-CLI on the host yet.
 *
 * Copy rules: descriptions come straight from the compliant catalogue in git.
 * Spec fields become product meta rendered by the theme, not editor content.
 */

namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Seed {

	/** products.js id → SKU (from Lot Code Key). Variants carry their own SKUs. */
	const SKU_MAP = [
		'glp-1-sm'        => 'SM10',
		'tesamorelin'     => 'TESA5',
		'bpc-157'         => 'BPC10',
		'wolverine-stack' => 'BPCTB',
		'cjc-ipamorelin'  => 'CJCIP',
		'ghk-cu'          => 'GHK5',
		'glow-klow'       => 'GLOW',
		'nad-500'         => 'NAD500',
		'mots-c'          => 'MOTS10',
		'epithalon'       => 'EPI10',
		'bac-water'       => 'BACW10',
	];

	/** Compound-family categories (never goal- or outcome-based). */
	const CATEGORIES = [
		'incretin-analogs' => [ 'Incretin analogs',      [ 'glp-2-t', 'glp-3-rt', 'glp-1-sm' ] ],
		'ghrh-ghs-analogs' => [ 'GHRH & GHS analogs',    [ 'tesamorelin', 'cjc-ipamorelin' ] ],
		'short-peptides'   => [ 'Short peptides',        [ 'bpc-157', 'wolverine-stack', 'glow-klow', 'ghk-cu', 'mots-c', 'epithalon' ] ],
		'cofactors'        => [ 'Cofactors',             [ 'nad-500' ] ],
		'solvents'         => [ 'Solvents',              [ 'bac-water' ] ],
	];

	/** Studio vial used as the grid image for every product (legacy/site.js behaviour). */
	const GRID_IMAGE = 'assets/products/vial-studio-1024.jpg';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 20 );
		add_action( 'admin_post_luma_seed', [ __CLASS__, 'handle' ] );
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', 'Luma Tools', 'Luma Tools', 'manage_woocommerce', 'luma-tools', [ __CLASS__, 'page' ] );
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$log = get_transient( 'luma_seed_log' );
		delete_transient( 'luma_seed_log' );
		?>
		<div class="wrap">
			<h1>Luma Tools</h1>
			<?php if ( $log ) : ?>
				<div class="notice notice-info"><pre style="white-space:pre-wrap"><?php echo esc_html( $log ); ?></pre></div>
			<?php endif; ?>
			<h2>Seed catalogue</h2>
			<p>Creates or updates products, categories and lots from <code>luma-core/data/catalogue.json</code> and <code>data/lots.json</code>. Safe to re-run; existing products are matched by SKU and updated in place. Images are sideloaded from <code>luma-src/assets/products/</code> (renamed to the product slug so no filename reveals a coded compound).</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'luma_seed' ); ?>
				<input type="hidden" name="action" value="luma_seed">
				<label><input type="checkbox" name="with_images" value="1" checked> Sideload images</label><br><br>
				<?php submit_button( 'Run seed', 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'luma_seed' );
		$with_images = ! empty( $_POST['with_images'] );
		$log         = self::run( $with_images );
		set_transient( 'luma_seed_log', $log, 300 );
		wp_safe_redirect( admin_url( 'admin.php?page=luma-tools' ) );
		exit;
	}

	/** @return string log */
	public static function run( bool $with_images = true ): string {
		$out  = [];
		$data = json_decode( (string) file_get_contents( LUMA_CORE_DIR . 'data/catalogue.json' ), true );
		$lots = json_decode( (string) file_get_contents( LUMA_CORE_DIR . 'data/lots.json' ), true );
		if ( ! $data || empty( $data['products'] ) ) {
			return 'catalogue.json missing or empty';
		}

		/* Categories */
		$cat_ids = [];
		$id_to_cat = [];
		foreach ( self::CATEGORIES as $slug => [ $name, $ids ] ) {
			$term = term_exists( $slug, 'product_cat' );
			if ( ! $term ) {
				$term = wp_insert_term( $name, 'product_cat', [ 'slug' => $slug ] );
			}
			$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
			$cat_ids[ $slug ] = $term_id;
			foreach ( $ids as $pid ) {
				$id_to_cat[ $pid ] = $term_id;
			}
		}
		/* Retire the default "Uncategorized" from the front end. */
		$unc = get_term_by( 'slug', 'uncategorized', 'product_cat' );
		if ( $unc ) {
			wp_update_term( $unc->term_id, 'product_cat', [ 'name' => 'Other' ] );
		}
		$out[] = 'Categories: ' . implode( ', ', array_keys( $cat_ids ) );

		/* Tiers (quantity breaks) stored once as an option for the pricing module. */
		update_option( 'luma_volume_tiers', $data['tiers'] ?? [ [ 'min' => 3, 'pct' => 5 ], [ 'min' => 5, 'pct' => 10 ], [ 'min' => 10, 'pct' => 15 ] ] );

		$grid_image_id = $with_images ? self::sideload( self::GRID_IMAGE, 'vial-studio' ) : 0;

		/* Products */
		foreach ( $data['products'] as $p ) {
			$has_variants = ! empty( $p['variants'] );
			$sku          = $has_variants ? 'LUMA-' . strtoupper( $p['id'] ) : ( self::SKU_MAP[ $p['id'] ] ?? 'LUMA-' . strtoupper( $p['id'] ) );
			$existing     = wc_get_product_id_by_sku( $sku );
			$product      = $existing ? wc_get_product( $existing ) : ( $has_variants ? new \WC_Product_Variable() : new \WC_Product_Simple() );

			$product->set_name( $p['name'] );
			$product->set_slug( $p['id'] );
			$product->set_sku( $sku );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'visible' );
			$product->set_short_description( wp_kses_post( $p['tagline'] ?? '' ) );
			$product->set_description( wp_kses_post( $p['description'] ?? '' ) );
			$product->set_reviews_allowed( false );
			$product->set_sold_individually( false );
			$product->set_category_ids( isset( $id_to_cat[ $p['id'] ] ) ? [ $id_to_cat[ $p['id'] ] ] : [] );
			$product->update_meta_data( '_luma_specs', $p['specs'] ?? [] );
			$product->update_meta_data( '_luma_inside', $p['inside'] ?? '' );
			$product->update_meta_data( '_luma_strength', $p['strength'] ?? '' );
			$product->update_meta_data( '_luma_legacy_id', $p['id'] );

			if ( ! $has_variants ) {
				$product->set_regular_price( (string) $p['once'] );
				$product->set_manage_stock( true );
				$product->set_stock_quantity( ( $p['stock'] ?? 'out' ) === 'in' ? 10 : 0 );
				$product->set_stock_status( ( $p['stock'] ?? 'out' ) === 'in' ? 'instock' : 'outofstock' );
				$product->set_low_stock_amount( 3 );
			}

			if ( $grid_image_id ) {
				$product->set_image_id( $grid_image_id );
				if ( $with_images && ! empty( $p['image'] ) ) {
					$detail = self::sideload( $p['image'] . '.jpg', $p['id'] );
					if ( $detail ) {
						$product->set_gallery_image_ids( [ $detail ] );
					}
				}
			}

			$product_id = $product->save();
			$out[]      = ( $existing ? 'Updated ' : 'Created ' ) . $sku . ' → #' . $product_id;

			if ( $has_variants ) {
				/* Attribute: Strength */
				$attr = new \WC_Product_Attribute();
				$attr->set_name( 'Strength' );
				$attr->set_options( array_column( $p['variants'], 'label' ) );
				$attr->set_visible( true );
				$attr->set_variation( true );
				$product->set_attributes( [ $attr ] );
				$product->save();

				foreach ( $p['variants'] as $v ) {
					$vid       = wc_get_product_id_by_sku( $v['sku'] );
					$variation = $vid ? new \WC_Product_Variation( $vid ) : new \WC_Product_Variation();
					$variation->set_parent_id( $product_id );
					$variation->set_sku( $v['sku'] );
					$variation->set_attributes( [ 'strength' => $v['label'] ] );
					$variation->set_regular_price( (string) $v['once'] );
					$variation->set_manage_stock( true );
					$variation->set_stock_quantity( ( $v['stock'] ?? 'out' ) === 'in' ? 10 : 0 );
					$variation->set_stock_status( ( $v['stock'] ?? 'out' ) === 'in' ? 'instock' : 'outofstock' );
					$variation->set_low_stock_amount( 3 );
					$variation->update_meta_data( '_luma_strength', $v['strength'] ?? $v['label'] );
					$variation->set_status( 'publish' );
					$nvid  = $variation->save();
					$out[] = '  variation ' . $v['sku'] . ' → #' . $nvid;
				}
				\WC_Product_Variable::sync( $product_id );
			}
		}

		/* Lots — real lot IDs only; analytical values stay blank until the COA arrives. */
		foreach ( (array) $lots as $lot ) {
			$post = get_page_by_title( $lot['lot'], OBJECT, Lots::CPT ) ?: null; // phpcs:ignore WordPress.WP.DeprecatedFunctions
			$args = [
				'post_type'   => Lots::CPT,
				'post_title'  => $lot['lot'],
				'post_status' => 'publish',
			];
			if ( $post ) {
				$args['ID'] = $post->ID;
			}
			$lot_id = wp_insert_post( $args );
			$pid    = wc_get_product_id_by_sku( $lot['sku'] );
			$prod   = $pid ? wc_get_product( $pid ) : null;
			$meta   = [
				'_lot_product_id'   => $prod ? ( $prod->is_type( 'variation' ) ? $prod->get_parent_id() : $pid ) : 0,
				'_lot_variation_id' => $prod && $prod->is_type( 'variation' ) ? $pid : 0,
				'_lot_lab'          => $lot['lab'] ?? 'Freedom Diagnostics',
				'_lot_status'       => $lot['status'] ?? 'PENDING',
				'_lot_tested'       => $lot['tested'] ?? '',
				'_lot_method'       => $lot['method'] ?? '',
				'_lot_purity'       => $lot['purity'] ?? '',
				'_lot_identity'     => $lot['identity'] ?? '',
				'_lot_net_content'  => $lot['net_content'] ?? '',
				'_lot_expires'      => $lot['expires'] ?? '',
				'_lot_qty_received' => (int) ( $lot['qty_received'] ?? 0 ),
				'_lot_qty_remaining'=> (int) ( $lot['qty_remaining'] ?? $lot['qty_received'] ?? 0 ),
			];
			foreach ( $meta as $k => $v ) {
				update_post_meta( $lot_id, $k, $v );
			}
			/* Current lot pointer on the product/variation it belongs to. */
			if ( $pid && ! empty( $lot['current'] ) ) {
				update_post_meta( $pid, '_luma_current_lot', $lot_id );
			}
			$out[] = 'Lot ' . $lot['lot'] . ' → #' . $lot_id . ( $pid ? ' (' . $lot['sku'] . ')' : ' (no product for ' . $lot['sku'] . ')' );
		}

		wc_delete_product_transients();
		return implode( "\n", $out );
	}

	/** Sideload an image from the git-deployed static assets, renaming it to $basename. Returns attachment ID (cached by basename). */
	private static function sideload( string $rel, string $basename ): int {
		$cached = get_option( 'luma_seed_img_' . $basename );
		if ( $cached && get_post( (int) $cached ) ) {
			return (int) $cached;
		}
		$src = WP_CONTENT_DIR . '/luma-src/' . ltrim( $rel, '/' );
		if ( ! is_file( $src ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( $basename . '.jpg' );
		copy( $src, $tmp );
		$id = media_handle_sideload( [ 'name' => $basename . '.jpg', 'tmp_name' => $tmp ], 0, $basename );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return 0;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $basename === 'vial-studio' ? 'Lyophilized peptide vial' : $basename );
		update_option( 'luma_seed_img_' . $basename, (int) $id, false );
		return (int) $id;
	}
}
