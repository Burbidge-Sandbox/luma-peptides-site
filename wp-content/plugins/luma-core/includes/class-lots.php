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
			'_lot_report_no'    => 'sanitize_text_field', // lab report number (e.g. KVR-2026-C03CC0)
			'_lot_access_code'  => 'sanitize_text_field', // lab verify-portal code
			'_lot_cap'          => 'sanitize_text_field', // cap colour printed on the COA
			'_lot_labeled_qty'  => 'sanitize_text_field', // e.g. 15 mg
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
		$get   = fn( $k ) => (string) get_post_meta( $post->ID, $k, true );
		$pid   = (int) $get( '_lot_variation_id' ) ?: (int) $get( '_lot_product_id' );
		$coa   = (int) $get( '_lot_coa_id' );
		$photo = (int) $get( '_lot_photo_id' );
		$text  = [ '_lot_lab' => 'Testing lab', '_lot_tested' => 'Test date (YYYY-MM-DD)', '_lot_method' => 'Method', '_lot_purity' => 'Assay purity', '_lot_identity' => 'Identity', '_lot_net_content' => 'Net content', '_lot_endotoxin' => 'Endotoxin', '_lot_expires' => 'Best before (YYYY-MM)', '_lot_report_no' => 'Lab report #', '_lot_access_code' => 'Lab access code', '_lot_cap' => 'Cap colour', '_lot_labeled_qty' => 'Labeled qty', '_lot_qty_received' => 'Qty received', '_lot_qty_remaining' => 'Qty remaining' ];
		echo '<table class="form-table"><tr><th><label for="_lot_sellable">Product</label></th><td><select id="_lot_sellable" name="_lot_sellable" style="min-width:24em"><option value="0">— none —</option>';
		foreach ( Receive::sellables() as $id => $label ) {
			echo '<option value="' . (int) $id . '"' . selected( $pid, $id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>Result</th><td><select name="_lot_status">';
		foreach ( [ 'PENDING' => 'Pending — COA not issued yet', 'PASS' => 'PASS — released', 'FAIL' => 'FAIL — quarantined' ] as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $get( '_lot_status' ) ?: 'PENDING', $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		foreach ( $text as $key => $label ) {
			printf( '<tr><th><label for="%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="%1$s" name="%1$s" value="%3$s"></td></tr>', esc_attr( $key ), esc_html( $label ), esc_attr( $get( $key ) ) );
		}
		foreach ( [ '_lot_coa_id' => [ 'Certificate (PDF)', $coa, 'application/pdf' ], '_lot_photo_id' => [ 'Vial photo', $photo, 'image' ] ] as $key => [ $label, $id, $type ] ) {
			$url = $id ? wp_get_attachment_url( $id ) : '';
			printf(
				'<tr><th>%2$s</th><td><input type="hidden" id="%1$s" name="%1$s" value="%3$d"><button type="button" class="button luma-pick" data-target="%1$s" data-type="%5$s">Choose file</button> <button type="button" class="button-link luma-clear" data-target="%1$s">Remove</button> <span class="luma-file" id="%1$s_name">%4$s</span></td></tr>',
				esc_attr( $key ), esc_html( $label ), $id, $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( basename( $url ) ) . '</a>' : '<em>none</em>', esc_attr( $type )
			);
		}
		echo '</table><p class="description">Lot number is the title (LP-YYMM-SKU). Analytical values must match the certificate exactly — customers compare them at /testing/.</p>';
		echo '<script>(function(){document.querySelectorAll(".luma-pick").forEach(function(b){b.onclick=function(e){e.preventDefault();var t=b.dataset.target,f=wp.media({title:"Choose file",library:{type:b.dataset.type},multiple:false});f.on("select",function(){var a=f.state().get("selection").first().toJSON();document.getElementById(t).value=a.id;document.getElementById(t+"_name").innerHTML="<a href=\""+a.url+"\" target=\"_blank\">"+(a.filename||a.title)+"</a>";});f.open();};});document.querySelectorAll(".luma-clear").forEach(function(b){b.onclick=function(e){e.preventDefault();var t=b.dataset.target;document.getElementById(t).value=0;document.getElementById(t+"_name").innerHTML="<em>none</em>";};});})();</script>';
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['luma_lot_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['luma_lot_nonce'] ), 'luma_lot_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( isset( $_POST['_lot_sellable'] ) ) {
			$sel = (int) $_POST['_lot_sellable'];
			$sp  = $sel ? wc_get_product( $sel ) : null;
			update_post_meta( $post_id, '_lot_product_id', $sp ? ( $sp->is_type( 'variation' ) ? $sp->get_parent_id() : $sp->get_id() ) : 0 );
			update_post_meta( $post_id, '_lot_variation_id', $sp && $sp->is_type( 'variation' ) ? $sp->get_id() : 0 );
		}
		foreach ( self::schema() as $key => $sanitize ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, call_user_func( $sanitize, wp_unslash( $_POST[ $key ] ) ) );
			}
		}
		$coa = (int) get_post_meta( $post_id, '_lot_coa_id', true );
		if ( $coa ) {
			wp_update_post( [ 'ID' => $coa, 'post_parent' => $post_id ] );
		}
	}

	public static function columns( array $cols ): array {
		return [ 'cb' => $cols['cb'], 'title' => 'Lot', 'lot_product' => 'Product', 'lot_status' => 'Status', 'lot_purity' => 'Purity', 'lot_tested' => 'Tested', 'lot_qty' => 'Remaining', 'lot_coa' => 'COA', 'lot_current' => 'Current' ];
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
			case 'lot_coa':
				$c = (int) get_post_meta( $post_id, '_lot_coa_id', true );
				echo $c ? '<a href="' . esc_url( wp_get_attachment_url( $c ) ) . '" target="_blank" rel="noopener">PDF</a>' : '<span style="color:#b32d2e">missing</span>';
				break;
			case 'lot_current':
				$pid = (int) get_post_meta( $post_id, '_lot_variation_id', true ) ?: (int) get_post_meta( $post_id, '_lot_product_id', true );
				echo $pid && (int) get_post_meta( $pid, '_luma_current_lot', true ) === $post_id ? '✔' : '';
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
			'report_no'   => $get( '_lot_report_no' ),
			'access_code' => $get( '_lot_access_code' ),
			'cap'         => $get( '_lot_cap' ),
			'labeled_qty' => $get( '_lot_labeled_qty' ),
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
		$contact = home_url( '/contact/' );
		ob_start();
		echo '<div class="verify-box"><form method="get" action=""><label class="sr-only" for="lotInput">Lot number</label><input id="lotInput" name="lot" value="' . esc_attr( $q ) . '" placeholder="LP-2609-BPC10" autocomplete="off" spellcheck="false" required><button class="btn btn-primary">Look up</button></form>';
		echo '<p class="verify-hint">The lot number is printed on the vial label, e.g. <b>LP-2609-BPC10</b>.</p></div>';
		echo '<div class="coa-result" id="coaResult" aria-live="polite">';
		if ( $q ) {
			if ( self::rate_limited() ) {
				echo '<div class="coa-card"><div class="coa-badge fail">Too many lookups</div><p style="margin:0;color:var(--ink-2)">Please wait a minute and try again.</p></div>';
			} else {
				$lot = self::find( $q );
				if ( ! $lot ) {
					echo '<div class="coa-card"><div class="coa-badge fail">✕ &nbsp;Lot not found</div><p style="margin:0;color:var(--ink-2)">We don\'t have a record of <b>' . esc_html( $q ) . '</b>. Double-check the code printed on the vial, or <a href="' . esc_url( $contact ) . '" style="color:var(--terra);text-decoration:underline">contact support</a> — a lot that is not listed here should be reported.</p></div>';
				} else {
					self::render_card( self::view( $lot ) );
				}
			}
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	public static function render_card( array $v ): void {
		$pending = $v['status'] === 'PENDING';
		$pass    = $v['status'] === 'PASS';
		$rows    = [
			[ 'Laboratory', $v['lab'], false ],
			[ 'Certified', $v['tested'], false ],
			[ 'Purity (HPLC)', $v['purity'], true ],
			[ 'Identity (LC-MS)', $v['identity'], true ],
			[ 'Net content', $v['net_content'], false ],
			[ 'Labeled quantity', $v['labeled_qty'], false ],
			[ 'Endotoxin', $v['endotoxin'], false ],
			[ 'Cap colour', $v['cap'], false ],
			[ 'Lab report #', $v['report_no'], false ],
			[ 'Best before', $v['expires'], false ],
			[ 'Overall', $v['status'], true ],
		];
		echo '<div class="coa-card">';
		echo '<div class="coa-badge' . ( $pending ? '' : ( $pass ? '' : ' fail' ) ) . '">' . ( $pending ? 'Testing in progress' : 'Certificate of analysis · ' . esc_html( $v['status'] ) ) . '</div>';
		echo '<div class="coa-head"><div><b style="font-size:1.15rem">' . esc_html( $v['product'] ) . '</b><br><span style="font-size:.85rem;color:var(--muted)">Lot ' . esc_html( $v['lot'] ) . '</span></div>' . ( $pass ? '<span class="coa-stamp">TESTED</span>' : '' ) . '</div>';
		echo '<div class="coa-grid">';
		foreach ( $rows as [ $label, $val, $ok ] ) {
			if ( '' === $val && ! $pending ) {
				continue; // released lots only show what the certificate states
			}
			echo '<div class="coa-row"><span>' . esc_html( $label ) . '</span>' . ( '' !== $val ? '<b' . ( $ok && $pass ? ' class="pass"' : '' ) . '>' . esc_html( $val ) . '</b>' : '<b style="color:var(--muted);font-weight:400">Pending</b>' ) . '</div>';
		}
		echo '</div>';
		if ( $pending ) {
			echo '<p style="font-size:.85rem;color:var(--ink-2);margin:1.2rem 0 0">Third-party testing for this lot is in progress. The certificate of analysis is published here when it is issued.</p>';
		}
		$actions = [];
		if ( $v['coa_url'] ) {
			$actions[] = '<a class="btn btn-outline btn-sm" href="' . esc_url( $v['coa_url'] ) . '" target="_blank" rel="noopener">Open certificate (PDF)</a>';
		}
		if ( $v['report_no'] && $v['access_code'] && stripos( $v['lab'], 'kovera' ) !== false ) {
			$actions[] = '<a class="btn btn-ghost btn-sm" href="https://koveralabs.com/verify" target="_blank" rel="noopener">Verify at Kovera Labs ↗</a><span style="font-size:.8rem;color:var(--muted)">Report ' . esc_html( $v['report_no'] ) . ' · access code <b style="font-family:var(--mono,monospace)">' . esc_html( $v['access_code'] ) . '</b></span>';
		}
		if ( $actions ) {
			echo '<p style="margin:1.2rem 0 0;display:flex;gap:.8rem;flex-wrap:wrap;align-items:center">' . implode( '', $actions ) . '</p>';
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
