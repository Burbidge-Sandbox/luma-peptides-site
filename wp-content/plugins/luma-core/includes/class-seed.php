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
	/** Categories exactly as the live static site had them (products.js LUMA_CATEGORIES). */
	const CATEGORIES = [
		'metabolic' => [ 'Metabolic Research', [ 'glp-2-t', 'glp-3-rt', 'glp-1-sm', 'tesamorelin' ] ],
		'tissue'    => [ 'Tissue Research',    [ 'bpc-157', 'wolverine-stack' ] ],
		'dermal'    => [ 'Dermal Research',    [ 'ghk-cu', 'glow-klow' ] ],
		'longevity' => [ 'Longevity Research', [ 'cjc-ipamorelin', 'nad-500', 'mots-c', 'epithalon' ] ],
		'supplies'  => [ 'Solvents',           [ 'bac-water' ] ],
	];
	/** Earlier scaffold categories, removed on the next seed run. */
	const RETIRED_CATEGORIES = [ 'incretin-analogs', 'ghrh-ghs-analogs', 'short-peptides', 'cofactors' ];

	/** Static pages imported verbatim from the legacy site (main inner HTML). */
	const LEGACY_PAGES = [ 'about', 'faq', 'contact', 'shipping-returns', 'privacy', 'terms' ];

	/** Studio vial used as the grid image for every product (legacy/site.js behaviour). */
	const GRID_IMAGE = 'assets/products/vial-studio-1024.jpg';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 20 );
		add_action( 'admin_post_luma_seed', [ __CLASS__, 'handle' ] );
		add_action( 'admin_post_luma_receive', [ __CLASS__, 'handle_receive' ] );
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

			<hr style="margin:2rem 0">
			<h2>Receive inventory</h2>
			<p>Paste the supplier's packing list, one item per line, e.g. <code>RT30=7 boxes</code>, <code>WA10 = 5</code> or <code>BC10 50</code>. Supplier codes are mapped to store SKUs (<code>WA10</code> → bac water, <code>BC10</code> → BPC-157, <code>BBG70</code> → Glow blend). Repeated codes are summed.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'luma_receive' ); ?>
				<input type="hidden" name="action" value="luma_receive">
				<textarea name="lines" rows="8" cols="50" class="large-text code" placeholder="RT30=7 boxes&#10;TR15=2 boxes"></textarea><br>
				<label><input type="checkbox" name="boxes" value="1" checked> Quantities are boxes of <input type="number" name="per_box" value="10" min="1" style="width:4em"> vials</label><br>
				<label><input type="radio" name="mode" value="set" checked> Set stock to these counts (full recount)</label><br>
				<label><input type="radio" name="mode" value="add"> Add to current stock (new shipment)</label><br>
				<label><input type="checkbox" name="dry" value="1"> Preview only (no changes)</label><br><br>
				<?php submit_button( 'Apply inventory', 'secondary', 'submit', false ); ?>
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

	/** Supplier packing-list code → store SKU (variation SKU where one exists). */
	const SUPPLIER_CODES = [
		'RT15' => 'RT15', 'RT30' => 'RT30', 'TR15' => 'TR15', 'TR30' => 'TR30', 'SM10' => 'SM10',
		'BC10' => 'BPC10', 'BPC10' => 'BPC10', 'BPC' => 'BPC10',
		'BBG70' => 'GLOW', 'BBG' => 'GLOW', 'GLOW' => 'GLOW', 'GLOW70' => 'GLOW',
		'BT10' => 'BPCTB', 'BPCTB' => 'BPCTB',
		'WA10' => 'BACW10', 'WA' => 'BACW10', 'BW10' => 'BACW10', 'BACW10' => 'BACW10', 'BW' => 'BACW10',
		'GHK5' => 'GHK5', 'GHK' => 'GHK5', 'TESA5' => 'TESA5', 'TESA' => 'TESA5', 'CJCIP' => 'CJCIP', 'CI5' => 'CJCIP',
		'NAD500' => 'NAD500', 'NAD' => 'NAD500', 'MOTS10' => 'MOTS10', 'MOTS' => 'MOTS10', 'EPI10' => 'EPI10', 'EPI' => 'EPI10',
	];

	public static function handle_receive(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'luma_receive' );
		$per   = ! empty( $_POST['boxes'] ) ? max( 1, (int) ( $_POST['per_box'] ?? 10 ) ) : 1;
		$mode  = ( $_POST['mode'] ?? 'set' ) === 'add' ? 'add' : 'set';
		$dry   = ! empty( $_POST['dry'] );
		$lines = (string) wp_unslash( $_POST['lines'] ?? '' );
		set_transient( 'luma_seed_log', self::receive( $lines, $per, $mode, $dry ), 300 );
		wp_safe_redirect( admin_url( 'admin.php?page=luma-tools' ) );
		exit;
	}

	/** Parse "CODE=N boxes" lines and apply stock. @return string log */
	public static function receive( string $lines, int $per_unit, string $mode, bool $dry ): string {
		$counts = [];
		$out    = [ ( $dry ? 'PREVIEW — ' : '' ) . ( 'add' === $mode ? 'Adding to' : 'Setting' ) . ' stock (×' . $per_unit . ' per line unit)' ];
		foreach ( preg_split( '/\r?\n/', $lines ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || ! preg_match( '/^([A-Za-z]+\d*)\s*[=:\-]?\s*(\d+)/', $line, $m ) ) {
				if ( '' !== $line ) {
					$out[] = 'skip: "' . $line . '"';
				}
				continue;
			}
			$code = strtoupper( preg_replace( '/\s+/', '', $m[1] ) );
			$sku  = self::SUPPLIER_CODES[ $code ] ?? null;
			if ( ! $sku ) {
				$out[] = 'UNKNOWN code ' . $code . ' — not applied';
				continue;
			}
			$counts[ $sku ] = ( $counts[ $sku ] ?? 0 ) + (int) $m[2] * $per_unit;
		}
		foreach ( $counts as $sku => $qty ) {
			$pid = wc_get_product_id_by_sku( $sku );
			$p   = $pid ? wc_get_product( $pid ) : null;
			if ( ! $p ) {
				$out[] = $sku . ': no product with this SKU';
				continue;
			}
			$before = (int) $p->get_stock_quantity();
			$new    = 'add' === $mode ? $before + $qty : $qty;
			$out[]  = sprintf( '%s (%s): %d → %d', $sku, $p->get_name() . ( $p->is_type( 'variation' ) ? ' ' . implode( ' ', $p->get_attributes() ) : '' ), $before, $new );
			if ( $dry ) {
				continue;
			}
			$p->set_manage_stock( true );
			$p->set_stock_quantity( $new );
			$p->set_stock_status( $new > 0 ? 'instock' : 'outofstock' );
			$p->save();
			$lot_id = (int) $p->get_meta( '_luma_current_lot' );
			if ( $lot_id ) {
				$received = 'add' === $mode ? (int) get_post_meta( $lot_id, '_lot_qty_received', true ) + $qty : $qty;
				update_post_meta( $lot_id, '_lot_qty_received', $received );
				update_post_meta( $lot_id, '_lot_qty_remaining', $new );
			}
		}
		if ( ! $dry ) {
			wc_delete_product_transients();
			wp_cache_delete( 'luma_catalogue_json', 'luma' );
		}
		return implode( "\n", $out );
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
		foreach ( self::RETIRED_CATEGORIES as $slug ) {
			$t = get_term_by( 'slug', $slug, 'product_cat' );
			if ( $t ) {
				wp_delete_term( $t->term_id, 'product_cat' );
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
				if ( ! $existing ) { // stock is live data after creation — never reset it on a re-run
					$product->set_stock_quantity( ( $p['stock'] ?? 'out' ) === 'in' ? 10 : 0 );
					$product->set_stock_status( ( $p['stock'] ?? 'out' ) === 'in' ? 'instock' : 'outofstock' );
					$product->set_low_stock_amount( 3 );
				}
			}

			if ( $with_images ) {
				/* Featured image = the labelled vial (same composite the catalog draws), so cart/checkout/emails/admin all show the product. */
				$label_id = self::sideload( 'assets/products/labelled/' . $p['id'] . '.jpg', $p['id'] . '-label' );
				$product->set_image_id( $label_id ?: $grid_image_id );
				$detail = ! empty( $p['image'] ) ? self::sideload( $p['image'] . '.jpg', $p['id'] ) : 0;
				$product->set_gallery_image_ids( $detail ? [ $detail ] : [] );
			} elseif ( $grid_image_id && ! $product->get_image_id() ) {
				$product->set_image_id( $grid_image_id );
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
					if ( ! $vid ) {
						$variation->set_stock_quantity( ( $v['stock'] ?? 'out' ) === 'in' ? 10 : 0 );
						$variation->set_stock_status( ( $v['stock'] ?? 'out' ) === 'in' ? 'instock' : 'outofstock' );
						$variation->set_low_stock_amount( 3 );
					}
					$variation->update_meta_data( '_luma_strength', $v['strength'] ?? $v['label'] );
					if ( $with_images ) {
						$vimg = self::sideload( 'assets/products/labelled/' . $p['id'] . '--' . strtolower( $v['key'] ?? $v['label'] ) . '.jpg', $p['id'] . '-' . strtolower( $v['key'] ?? $v['label'] ) . '-label' );
						if ( $vimg ) {
							$variation->set_image_id( $vimg );
						}
					}
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
				'_lot_lab'          => $lot['lab'] ?? 'Kovera Labs',
				'_lot_status'       => $lot['status'] ?? 'PENDING',
				'_lot_tested'       => $lot['tested'] ?? '',
				'_lot_method'       => $lot['method'] ?? '',
				'_lot_purity'       => $lot['purity'] ?? '',
				'_lot_identity'     => $lot['identity'] ?? '',
				'_lot_net_content'  => $lot['net_content'] ?? '',
				'_lot_expires'      => $lot['expires'] ?? '',
			];
			if ( ! $post ) { // quantities are maintained by Receive inventory after first creation
				$meta['_lot_qty_received']  = (int) ( $lot['qty_received'] ?? 0 );
				$meta['_lot_qty_remaining'] = (int) ( $lot['qty_remaining'] ?? $lot['qty_received'] ?? 0 );
			}
			foreach ( $meta as $k => $v ) {
				update_post_meta( $lot_id, $k, $v );
			}
			/* Current lot pointer on the product/variation it belongs to. */
			if ( $pid && ! empty( $lot['current'] ) ) {
				update_post_meta( $pid, '_luma_current_lot', $lot_id );
			}
			$out[] = 'Lot ' . $lot['lot'] . ' → #' . $lot_id . ( $pid ? ' (' . $lot['sku'] . ')' : ' (no product for ' . $lot['sku'] . ')' );
		}

		/* Legacy static pages */
		foreach ( self::LEGACY_PAGES as $slug ) {
			$r = self::import_page( $slug );
			$out[] = 'Page ' . $slug . ' → ' . $r;
		}

		wc_delete_product_transients();
		wp_cache_delete( 'luma_catalogue_json', 'luma' );
		return implode( "\n", $out );
	}

	/** Import legacy/<slug>.html <main> inner HTML as a page using the Legacy template, links rewritten. */
	private static function import_page( string $slug ): string {
		$src = WP_CONTENT_DIR . '/luma-src/' . $slug . '.html';
		if ( ! is_file( $src ) ) {
			return 'missing ' . $slug . '.html';
		}
		$html = (string) file_get_contents( $src );
		if ( ! preg_match( '#<main[^>]*>(.*)</main>#s', $html, $m ) ) {
			return 'no <main>';
		}
		$body = $m[1];
		$body = preg_replace( '#<script\b[^>]*>.*?</script>#s', '', $body );
		$map  = [
			'index.html'            => home_url( '/' ),
			'shop.html'             => wc_get_page_permalink( 'shop' ),
			'verify.html'           => home_url( '/testing/' ),
			'status.html'           => wc_get_account_endpoint_url( 'orders' ),
			'account.html'          => wc_get_page_permalink( 'myaccount' ),
			'cart.html'             => wc_get_cart_url(),
			'checkout.html'         => wc_get_checkout_url(),
			'contact.html'          => home_url( '/contact/' ),
			'faq.html'              => home_url( '/faq/' ),
			'about.html'            => home_url( '/about/' ),
			'terms.html'            => home_url( '/terms/' ),
			'privacy.html'          => home_url( '/privacy/' ),
			'shipping-returns.html' => home_url( '/shipping-returns/' ),
		];
		$body = preg_replace_callback( '#href="([a-z\-]+\.html)(\#[^"]*)?"#', function ( $mm ) use ( $map ) {
			return 'href="' . ( $map[ $mm[1] ] ?? $mm[1] ) . ( $mm[2] ?? '' ) . '"';
		}, $body );
		$body = str_replace( 'src="assets/', 'src="' . content_url( '/luma-src/assets/' ), $body );
		$body = preg_replace( '#href="product\.html\?id=([a-z0-9\-]+)[^"]*"#', 'href="' . home_url( '/product/' ) . '$1/"', $body );
		$titles = [ 'about' => 'About', 'faq' => 'FAQ', 'contact' => 'Contact', 'shipping-returns' => 'Shipping & Returns', 'privacy' => 'Privacy Policy', 'terms' => 'Terms' ];
		$page   = get_page_by_path( $slug );
		$args   = [
			'post_type'    => 'page',
			'post_name'    => $slug,
			'post_title'   => $titles[ $slug ] ?? ucfirst( $slug ),
			'post_status'  => 'publish',
			'post_content' => $body,
		];
		if ( $page ) {
			$args['ID'] = $page->ID;
		}
		$id = wp_insert_post( wp_slash( $args ) );
		update_post_meta( $id, '_wp_page_template', 'page-legacy.php' );
		return '#' . $id;
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
