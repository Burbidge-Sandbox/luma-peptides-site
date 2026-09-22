<?php
/**
 * Receive a batch: one screen that creates the lot, files the COA PDF against
 * it, records the analytical results, adds the vials to stock and makes the
 * lot the product's current lot. Runs 2–3 times a month; every field the
 * customer can later verify is captured here once.
 *
 * Traceability chain: lot (COA) → product current lot → order line `_luma_lot`
 * (stamped at checkout, overridable in the order's Fulfillment box) → shipped
 * email + account + /testing/?lot=… lookup.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Receive {
	const PAGE = 'luma-receive';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 21 );
		add_action( 'admin_post_luma_receive_lot', [ __CLASS__, 'handle' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
		/* Trace: stamp the product's current lot on every order line at checkout. */
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'stamp_line' ], 10, 4 );
		/* When a lot is used up, roll the product's pointer to the next released lot. */
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'roll_current' ], 20 );
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', 'Receive batch', 'Receive batch', 'manage_woocommerce', self::PAGE, [ __CLASS__, 'page' ] );
	}

	public static function assets( string $hook ): void {
		if ( str_contains( $hook, self::PAGE ) || get_current_screen()?->post_type === Lots::CPT ) {
			wp_enqueue_media();
		}
	}

	/* ---------- helpers ---------- */

	/** Every sellable SKU: [ id => label ], variations listed under their parent. */
	public static function sellables(): array {
		$out = [];
		foreach ( wc_get_products( [ 'limit' => -1, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ] ) as $p ) {
			if ( $p->is_type( 'variable' ) ) {
				foreach ( $p->get_children() as $vid ) {
					$v = wc_get_product( $vid );
					if ( $v ) {
						$out[ $vid ] = $p->get_name() . ' — ' . wc_get_formatted_variation( $v, true, false, false ) . ( $v->get_sku() ? ' [' . $v->get_sku() . ']' : '' );
					}
				}
			} else {
				$out[ $p->get_id() ] = $p->get_name() . ( $p->get_sku() ? ' [' . $p->get_sku() . ']' : '' );
			}
		}
		return $out;
	}

	/** LP-YYMM-SKU, with -2, -3… when that lot already exists this month. */
	public static function next_lot_number( \WC_Product $p, ?string $ym = null ): string {
		$ym   = $ym ?: gmdate( 'ym' );
		$code = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $p->get_sku() ?: $p->get_slug() ) );
		$code = substr( $code, 0, 6 ) ?: 'LOT';
		$base = "LP-{$ym}-{$code}";
		$n    = $base;
		for ( $i = 2; Lots::find( $n ) || self::exists_any_status( $n ); $i++ ) {
			$n = $base . $i;
		}
		return $n;
	}

	private static function exists_any_status( string $title ): bool {
		return (bool) get_posts( [ 'post_type' => Lots::CPT, 'post_status' => 'any', 'title' => $title, 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true ] );
	}

	public static function current_lot_for( \WC_Product $p ): ?\WP_Post {
		$id = (int) $p->get_meta( '_luma_current_lot' );
		if ( ! $id && $p->is_type( 'variation' ) ) {
			$parent = wc_get_product( $p->get_parent_id() );
			$id     = $parent ? (int) $parent->get_meta( '_luma_current_lot' ) : 0;
		}
		$post = $id ? get_post( $id ) : null;
		return $post && Lots::CPT === $post->post_type ? $post : null;
	}

	/* ---------- trace hooks ---------- */

	public static function stamp_line( $item, $cart_item_key, $values, $order ): void {
		if ( $item->get_meta( '_luma_lot' ) ) {
			return;
		}
		$prod = $item->get_product();
		$lot  = $prod ? self::current_lot_for( $prod ) : null;
		if ( $lot ) {
			$item->update_meta_data( '_luma_lot', $lot->post_title );
		}
	}

	public static function roll_current( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$prod = $item->get_product();
			$lot  = $prod ? self::current_lot_for( $prod ) : null;
			if ( ! $lot || (int) get_post_meta( $lot->ID, '_lot_qty_remaining', true ) > 0 ) {
				continue;
			}
			$next = null;
			foreach ( Fulfillment::lots_for_product( $prod->is_type( 'variation' ) ? $prod->get_parent_id() : $prod->get_id(), $prod->is_type( 'variation' ) ? $prod->get_id() : 0 ) as $cand ) {
				if ( $cand->ID !== $lot->ID && (int) get_post_meta( $cand->ID, '_lot_qty_remaining', true ) > 0 && 'FAIL' !== get_post_meta( $cand->ID, '_lot_status', true ) ) {
					$next = $cand;
					break;
				}
			}
			if ( $next ) {
				$prod->update_meta_data( '_luma_current_lot', $next->ID );
				$prod->save_meta_data();
				$order->add_order_note( sprintf( '%s: lot %s exhausted, current lot is now %s.', $prod->get_name(), $lot->post_title, $next->post_title ) );
			}
		}
	}

	/* ---------- admin screen ---------- */

	public static function page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$log   = get_transient( 'luma_receive_log' );
		delete_transient( 'luma_receive_log' );
		$items = self::sellables();
		$opt   = esc_attr( Settings::OPTION );
		?>
		<div class="wrap">
			<h1>Receive batch</h1>
			<?php if ( $log ) : ?><div class="notice <?php echo str_starts_with( $log, 'Error' ) ? 'notice-error' : 'notice-success'; ?>"><p><?php echo wp_kses_post( $log ); ?></p></div><?php endif; ?>
			<p class="description" style="max-width:60em">One form per lot. It creates the lot record, files the COA PDF against it, adds the vials to stock and makes this the product's current lot, so every order from now on carries this lot number and its certificate. Copy the analytical values exactly as printed on the certificate — customers compare them.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="lumaReceive">
				<?php wp_nonce_field( 'luma_receive_lot' ); ?>
				<input type="hidden" name="action" value="luma_receive_lot">
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="rc_product">Product</label></th>
						<td><select id="rc_product" name="product_id" required style="min-width:24em"><option value="">— choose —</option>
							<?php foreach ( $items as $id => $label ) : ?><option value="<?php echo (int) $id; ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
						</select><p class="description">Lot number is generated as LP-YYMM-SKU (a suffix is added if this SKU already has a lot this month).</p></td></tr>
					<tr><th scope="row"><label for="rc_qty">Vials received</label></th>
						<td><input type="number" id="rc_qty" name="qty" min="1" required class="small-text"> <label style="margin-left:1em"><input type="checkbox" name="boxes" value="1"> quantity is boxes of <input type="number" name="per_box" value="10" min="1" style="width:4em"> vials</label>
						<p class="description">Added to the product's stock on save.</p></td></tr>
					<tr><th scope="row"><label for="rc_coa">Certificate of analysis (PDF)</label></th>
						<td><input type="file" id="rc_coa" name="lot_coa" accept="application/pdf"> <span style="margin:0 .6em;color:#646970">or</span> <button type="button" class="button" id="rc_pick">Choose from Media Library</button> <input type="hidden" name="coa_id" id="rc_coa_id"><span id="rc_coa_name" style="margin-left:.6em"></span>
						<p class="description">Filed against the lot and linked from the public lookup. Leave empty if the lab has not issued it yet — the lot shows "Testing in progress" until you add it.</p></td></tr>
					<tr><th scope="row"><label for="rc_lab">Testing lab</label></th><td><input type="text" id="rc_lab" name="lab" value="Kovera Labs" class="regular-text"></td></tr>
					<tr><th scope="row"><label for="rc_tested">Test date</label></th><td><input type="date" id="rc_tested" name="tested"></td></tr>
					<tr><th scope="row"><label for="rc_method">Method</label></th><td><input type="text" id="rc_method" name="method" value="HPLC, LC-MS" class="regular-text"></td></tr>
					<tr><th scope="row"><label for="rc_purity">Assay purity</label></th><td><input type="text" id="rc_purity" name="purity" placeholder="99.4%" class="regular-text"></td></tr>
					<tr><th scope="row"><label for="rc_identity">Identity</label></th><td><input type="text" id="rc_identity" name="identity" placeholder="Confirmed (LC-MS)" class="regular-text"></td></tr>
					<tr><th scope="row"><label for="rc_net">Net content</label></th><td><input type="text" id="rc_net" name="net_content" placeholder="10.2 mg" class="regular-text"></td></tr>
					<tr><th scope="row"><label for="rc_endo">Endotoxin</label></th><td><input type="text" id="rc_endo" name="endotoxin" placeholder="< 0.5 EU/mg" class="regular-text"></td></tr>
					<tr><th scope="row"><label for="rc_expires">Best before (YYYY-MM)</label></th><td><input type="month" id="rc_expires" name="expires"></td></tr>
					<tr><th scope="row"><label for="rc_status">Result</label></th>
						<td><select id="rc_status" name="status"><option value="PENDING">Pending — COA not issued yet</option><option value="PASS">PASS — released for sale</option><option value="FAIL">FAIL — quarantine (not sold, stock not added)</option></select></td></tr>
					<tr><th scope="row">Make current</th><td><label><input type="checkbox" name="make_current" value="1" checked> Ship this lot on new orders from now on</label></td></tr>
				</table>
				<?php submit_button( 'Receive lot' ); ?>
			</form>

			<h2 style="margin-top:2.5rem">Lots on hand</h2>
			<?php self::on_hand_table(); ?>
		</div>
		<script>
		(function(){var b=document.getElementById('rc_pick');if(!b||!window.wp||!wp.media)return;var f;b.onclick=function(e){e.preventDefault();f=f||wp.media({title:'Choose the COA PDF',library:{type:'application/pdf'},button:{text:'Use this file'},multiple:false});f.off('select').on('select',function(){var a=f.state().get('selection').first().toJSON();document.getElementById('rc_coa_id').value=a.id;document.getElementById('rc_coa_name').textContent=a.filename||a.title;document.getElementById('rc_coa').value='';});f.open();};})();
		</script>
		<?php
	}

	private static function on_hand_table(): void {
		$lots = get_posts( [ 'post_type' => Lots::CPT, 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC' ] );
		if ( ! $lots ) {
			echo '<p>No lots yet.</p>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:70em"><thead><tr><th>Lot</th><th>Product</th><th>Result</th><th>Received</th><th>Remaining</th><th>COA</th><th>Current</th></tr></thead><tbody>';
		foreach ( $lots as $lot ) {
			$v   = Lots::view( $lot );
			$pid = (int) get_post_meta( $lot->ID, '_lot_variation_id', true ) ?: (int) get_post_meta( $lot->ID, '_lot_product_id', true );
			$cur = $pid && (int) get_post_meta( $pid, '_luma_current_lot', true ) === $lot->ID;
			printf(
				'<tr><td><a href="%s"><code>%s</code></a></td><td>%s</td><td>%s</td><td>%d</td><td>%d</td><td>%s</td><td>%s</td></tr>',
				esc_url( get_edit_post_link( $lot->ID ) ), esc_html( $v['lot'] ), esc_html( $v['product'] ), esc_html( $v['status'] ),
				(int) get_post_meta( $lot->ID, '_lot_qty_received', true ), (int) get_post_meta( $lot->ID, '_lot_qty_remaining', true ),
				$v['coa_url'] ? '<a href="' . esc_url( $v['coa_url'] ) . '" target="_blank" rel="noopener">PDF</a>' : '<span style="color:#b32d2e">missing</span>',
				$cur ? '✔' : ''
			);
		}
		echo '</tbody></table>';
	}

	public static function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'luma_receive_lot' );
		$back = admin_url( 'admin.php?page=' . self::PAGE );
		$fail = static function ( string $msg ) use ( $back ): void {
			set_transient( 'luma_receive_log', 'Error: ' . $msg, 300 );
			wp_safe_redirect( $back );
			exit;
		};

		$pid  = (int) ( $_POST['product_id'] ?? 0 );
		$prod = $pid ? wc_get_product( $pid ) : null;
		if ( ! $prod ) {
			$fail( 'choose a product.' );
		}
		$qty = (int) ( $_POST['qty'] ?? 0 );
		if ( ! empty( $_POST['boxes'] ) ) {
			$qty *= max( 1, (int) ( $_POST['per_box'] ?? 10 ) );
		}
		if ( $qty < 1 ) {
			$fail( 'enter the number of vials received.' );
		}
		$t      = fn( $k ) => sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );
		$status = in_array( $t( 'status' ), [ 'PENDING', 'PASS', 'FAIL' ], true ) ? $t( 'status' ) : 'PENDING';

		/* COA: uploaded file wins over a library pick. */
		$coa_id = (int) ( $_POST['coa_id'] ?? 0 );
		if ( ! empty( $_FILES['lot_coa']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$check = wp_check_filetype_and_ext( $_FILES['lot_coa']['tmp_name'], $_FILES['lot_coa']['name'] ); // phpcs:ignore
			if ( 'application/pdf' !== ( $check['type'] ?? '' ) ) {
				$fail( 'the certificate must be a PDF.' );
			}
			$up = media_handle_upload( 'lot_coa', 0 );
			if ( is_wp_error( $up ) ) {
				$fail( 'COA upload failed — ' . $up->get_error_message() );
			}
			$coa_id = (int) $up;
		}

		$number = self::next_lot_number( $prod );
		$lot_id = wp_insert_post( [ 'post_type' => Lots::CPT, 'post_title' => $number, 'post_status' => 'publish' ], true );
		if ( is_wp_error( $lot_id ) ) {
			$fail( $lot_id->get_error_message() );
		}
		$meta = [
			'_lot_product_id'    => $prod->is_type( 'variation' ) ? $prod->get_parent_id() : $prod->get_id(),
			'_lot_variation_id'  => $prod->is_type( 'variation' ) ? $prod->get_id() : 0,
			'_lot_lab'           => $t( 'lab' ),
			'_lot_tested'        => $t( 'tested' ),
			'_lot_method'        => $t( 'method' ),
			'_lot_purity'        => $t( 'purity' ),
			'_lot_identity'      => $t( 'identity' ),
			'_lot_net_content'   => $t( 'net_content' ),
			'_lot_endotoxin'     => $t( 'endotoxin' ),
			'_lot_status'        => $status,
			'_lot_expires'       => $t( 'expires' ),
			'_lot_coa_id'        => $coa_id,
			'_lot_qty_received'  => $qty,
			'_lot_qty_remaining' => 'FAIL' === $status ? 0 : $qty,
			'_lot_received_on'   => gmdate( 'Y-m-d' ),
		];
		foreach ( $meta as $k => $v ) {
			update_post_meta( $lot_id, $k, $v );
		}
		if ( $coa_id ) {
			wp_update_post( [ 'ID' => $coa_id, 'post_parent' => $lot_id, 'post_title' => 'COA ' . $number ] );
		}

		$msg = sprintf( 'Lot <b>%s</b> created for %s (%d vials).', esc_html( $number ), esc_html( $prod->get_name() ), $qty );
		if ( 'FAIL' !== $status ) {
			$before = (int) $prod->get_stock_quantity();
			$prod->set_manage_stock( true );
			$prod->set_stock_quantity( $before + $qty );
			$prod->set_stock_status( 'instock' );
			if ( ! empty( $_POST['make_current'] ) ) {
				$prod->update_meta_data( '_luma_current_lot', $lot_id );
			}
			$prod->save();
			$msg .= sprintf( ' Stock %d → %d.', $before, $before + $qty ) . ( ! empty( $_POST['make_current'] ) ? ' It is now the current lot for new orders.' : '' );
		} else {
			$msg .= ' Marked FAIL — quarantined, stock unchanged.';
		}
		$msg .= $coa_id ? ' COA filed.' : ' No COA yet — the lot shows "Testing in progress" until one is added.';
		$msg .= ' <a href="' . esc_url( get_edit_post_link( $lot_id, 'raw' ) ) . '">Edit lot</a> · <a href="' . esc_url( home_url( '/testing/?lot=' . rawurlencode( $number ) ) ) . '" target="_blank" rel="noopener">View public certificate</a>';
		wc_delete_product_transients( $prod->get_id() );
		wp_cache_delete( 'luma_catalogue_json', 'luma' );
		set_transient( 'luma_receive_log', $msg, 300 );
		wp_safe_redirect( $back );
		exit;
	}
}
