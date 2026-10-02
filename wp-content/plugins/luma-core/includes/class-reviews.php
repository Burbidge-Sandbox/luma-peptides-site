<?php
/**
 * Google reviews: a daily pull from the Places API into an approval queue,
 * a public /reviews/ page, and a homepage strip that switches itself on once
 * the Google profile has enough reviews.
 *
 * Compliance (CLAUDE.md rule 3): reviews about effects in people never
 * publish. Reviews about ordering, shipping, packaging and documentation do.
 * The same content rule applies at every star rating — rejecting a review
 * because it is negative is review suppression under 16 CFR 465.7. The page
 * always shows the Google rating and count and links to every review on
 * Google, so what is shown is never presented as the whole picture.
 *
 * Google Places terms: review content fetched from the API may not be stored
 * for more than 30 days, and every review shown must credit its author and
 * link to the review on Google. Content of API reviews that stop coming back
 * from the API is cleared after 30 days (the approval decision is kept, so a
 * review is never queued twice). Reviews added by hand are not API content.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Reviews {
	const CPT      = 'luma_review';
	const OPTION   = 'luma_reviews';
	const SUMMARY  = 'luma_reviews_summary';
	const CRON     = 'luma_reviews_sync';
	const PAGE     = 'luma-reviews';
	const TTL      = 30 * DAY_IN_SECONDS;

	/** Words that usually mean a review is about use or effects. A hint for the reviewer, not a filter. */
	const FLAGS = [ 'dose', 'dosing', 'dosage', 'mg', 'mcg', 'inject', 'injection', 'pin', 'subq', 'sub-q', 'cycle', 'stack', 'protocol',
		'reconstitut', 'bac water', 'units', 'weight', 'lbs', 'pounds', 'lost', 'fat', 'muscle', 'appetite', 'sleep', 'energy', 'healing',
		'healed', 'recovery', 'injury', 'pain', 'skin', 'wrinkle', 'results', 'worked', 'works', 'effect', 'side effect', 'felt', 'feel',
		'noticed', 'body', 'my wife', 'my husband', 'doctor', 'semaglutide', 'tirzepatide', 'retatrutide', 'ozempic', 'wegovy', 'mounjaro', 'zepbound' ];

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'init', [ __CLASS__, 'ensure_page' ], 20 );
		add_action( 'init', [ __CLASS__, 'schedule' ] );
		add_action( self::CRON, [ __CLASS__, 'sync' ] );
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 20 );
		add_action( 'admin_post_luma_reviews_settings', [ __CLASS__, 'handle_settings' ] );
		add_action( 'admin_post_luma_reviews_sync', [ __CLASS__, 'handle_sync' ] );
		add_action( 'admin_post_luma_reviews_decide', [ __CLASS__, 'handle_decide' ] );
		add_action( 'admin_post_luma_reviews_add', [ __CLASS__, 'handle_add' ] );
		add_shortcode( 'luma_reviews', [ __CLASS__, 'shortcode' ] );
	}

	/* ---------- settings + data ---------- */

	public static function opt( string $key ) {
		$o = wp_parse_args( (array) get_option( self::OPTION, [] ), [
			'place_id'  => '',
			'api_key'   => '',
			'strip_at'  => 50,   // homepage strip appears once Google shows this many reviews
			'strip'     => 'auto', // auto | off
		] );
		return $o[ $key ] ?? null;
	}

	public static function summary(): array {
		return wp_parse_args( (array) get_option( self::SUMMARY, [] ), [ 'rating' => 0, 'count' => 0, 'maps' => '', 'synced' => '', 'error' => '' ] );
	}

	public static function write_url(): string {
		$id = self::opt( 'place_id' );
		return $id ? 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $id ) : '';
	}

	public static function all_url(): string {
		$s = self::summary();
		if ( $s['maps'] ) {
			return $s['maps'];
		}
		$id = self::opt( 'place_id' );
		return $id ? 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode( $id ) : '';
	}

	public static function enabled(): bool {
		return (bool) self::opt( 'place_id' );
	}

	public static function page_url(): string {
		$p = get_page_by_path( 'reviews' );
		return $p ? get_permalink( $p ) : home_url( '/reviews/' );
	}

	/** Approved reviews whose content may be shown right now, newest first. */
	public static function visible( int $limit = -1 ): array {
		$posts = get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => [ [ 'key' => '_luma_rev_text', 'value' => '', 'compare' => '!=' ] ],
		] );
		return array_map( [ __CLASS__, 'row' ], $posts );
	}

	public static function row( \WP_Post $p ): array {
		$m = fn( $k ) => get_post_meta( $p->ID, $k, true );
		return [
			'id'      => $p->ID,
			'status'  => $p->post_status,
			'source'  => $m( '_luma_rev_source' ) ?: 'google',
			'rating'  => (int) $m( '_luma_rev_rating' ),
			'text'    => (string) $m( '_luma_rev_text' ),
			'author'  => (string) $m( '_luma_rev_author' ),
			'author_url' => (string) $m( '_luma_rev_author_url' ),
			'photo'   => (string) $m( '_luma_rev_photo' ),
			'link'    => (string) $m( '_luma_rev_link' ),
			'date'    => get_the_date( 'M j, Y', $p ),
			'note'    => (string) $m( '_luma_rev_note' ),
		];
	}

	public static function strip_on(): bool {
		if ( 'off' === self::opt( 'strip' ) || ! self::enabled() ) {
			return false;
		}
		return self::summary()['count'] >= (int) self::opt( 'strip_at' ) && count( self::visible( 3 ) ) >= 3;
	}

	public static function register(): void {
		register_post_type( self::CPT, [
			'label'        => 'Reviews',
			'public'       => false,
			'show_ui'      => false,
			'show_in_rest' => false,
			'supports'     => [ 'title' ],
		] );
	}

	public static function ensure_page(): void {
		if ( get_option( 'luma_reviews_page_v1' ) ) {
			return;
		}
		if ( ! get_page_by_path( 'reviews' ) ) {
			$id = wp_insert_post( [
				'post_type'    => 'page',
				'post_name'    => 'reviews',
				'post_title'   => 'Reviews',
				'post_status'  => 'publish',
				'post_content' => '[luma_reviews]',
			] );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_wp_page_template', 'page-reviews.php' );
			}
		}
		update_option( 'luma_reviews_page_v1', gmdate( 'c' ), false );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/* ---------- sync ---------- */

	public static function sync(): array {
		$place = self::opt( 'place_id' );
		$key   = self::opt( 'api_key' );
		$sum   = self::summary();
		if ( ! $place || ! $key ) {
			return [ 'ok' => false, 'msg' => 'Add the Place ID and API key first.' ];
		}
		$res = wp_remote_get( 'https://places.googleapis.com/v1/places/' . rawurlencode( $place ) . '?languageCode=en', [
			'timeout' => 15,
			'headers' => [
				'X-Goog-Api-Key'   => $key,
				'X-Goog-FieldMask' => 'rating,userRatingCount,googleMapsUri,reviews',
			],
		] );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$body = is_wp_error( $res ) ? [] : (array) json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code ) {
			$sum['error'] = is_wp_error( $res ) ? $res->get_error_message() : ( $body['error']['message'] ?? 'HTTP ' . $code );
			update_option( self::SUMMARY, $sum, false );
			return [ 'ok' => false, 'msg' => 'Google: ' . $sum['error'] ];
		}

		$new = 0;
		foreach ( (array) ( $body['reviews'] ?? [] ) as $r ) {
			$gid  = (string) ( $r['name'] ?? '' );
			$text = (string) ( $r['originalText']['text'] ?? $r['text']['text'] ?? '' );
			if ( ! $gid ) {
				continue;
			}
			$existing = get_posts( [ 'post_type' => self::CPT, 'post_status' => 'any', 'meta_key' => '_luma_rev_gid', 'meta_value' => $gid, 'posts_per_page' => 1, 'fields' => 'ids' ] );
			$id       = $existing[0] ?? 0;
			if ( ! $id ) {
				$id = wp_insert_post( [
					'post_type'   => self::CPT,
					'post_status' => 'pending',
					'post_title'  => wp_trim_words( $text ?: '(rating only)', 8, '…' ),
					'post_date'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( (string) ( $r['publishTime'] ?? 'now' ) ) ) ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( (string) ( $r['publishTime'] ?? 'now' ) ) ),
				] );
				if ( ! $id || is_wp_error( $id ) ) {
					continue;
				}
				update_post_meta( $id, '_luma_rev_gid', $gid );
				update_post_meta( $id, '_luma_rev_source', 'google' );
				$new++;
			}
			update_post_meta( $id, '_luma_rev_rating', (int) ( $r['rating'] ?? 0 ) );
			update_post_meta( $id, '_luma_rev_text', $text );
			update_post_meta( $id, '_luma_rev_author', (string) ( $r['authorAttribution']['displayName'] ?? 'Google user' ) );
			update_post_meta( $id, '_luma_rev_author_url', (string) ( $r['authorAttribution']['uri'] ?? '' ) );
			update_post_meta( $id, '_luma_rev_photo', (string) ( $r['authorAttribution']['photoUri'] ?? '' ) );
			update_post_meta( $id, '_luma_rev_link', (string) ( $r['googleMapsUri'] ?? '' ) );
			update_post_meta( $id, '_luma_rev_fetched', time() );
		}

		self::expire();

		update_option( self::SUMMARY, [
			'rating' => (float) ( $body['rating'] ?? 0 ),
			'count'  => (int) ( $body['userRatingCount'] ?? 0 ),
			'maps'   => (string) ( $body['googleMapsUri'] ?? '' ),
			'synced' => gmdate( 'c' ),
			'error'  => '',
		], false );

		if ( $new ) {
			wp_mail( get_option( 'admin_email' ), sprintf( '%d new Google review%s to approve', $new, $new > 1 ? 's' : '' ),
				"New Google reviews are waiting in the approval queue:\n" . admin_url( 'admin.php?page=' . self::PAGE ) . "\n\nNothing appears on the site until you approve it." );
		}
		return [ 'ok' => true, 'msg' => sprintf( 'Synced. %d new review%s in the queue.', $new, 1 === $new ? '' : 's' ) ];
	}

	/** Clear API review content not refreshed for 30 days (Google Places storage terms). Keeps the decision. */
	private static function expire(): void {
		$old = get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[ 'key' => '_luma_rev_source', 'value' => 'google' ],
				[ 'key' => '_luma_rev_fetched', 'value' => time() - self::TTL, 'compare' => '<', 'type' => 'NUMERIC' ],
				[ 'key' => '_luma_rev_text', 'value' => '', 'compare' => '!=' ],
			],
		] );
		foreach ( $old as $id ) {
			foreach ( [ '_luma_rev_text', '_luma_rev_author', '_luma_rev_author_url', '_luma_rev_photo' ] as $k ) {
				update_post_meta( $id, $k, '' );
			}
		}
	}

	/* ---------- admin ---------- */

	public static function menu(): void {
		$pending = (int) ( wp_count_posts( self::CPT )->pending ?? 0 );
		$badge   = $pending ? ' <span class="awaiting-mod">' . $pending . '</span>' : '';
		global $submenu;
		if ( empty( $submenu[ Dashboard::PAGE ] ) ) { // keep "Luma" itself pointing at the dashboard
			add_submenu_page( Dashboard::PAGE, 'Luma', 'Dashboard', 'manage_woocommerce', Dashboard::PAGE, [ Dashboard::class, 'page' ] );
		}
		add_submenu_page( Dashboard::PAGE, 'Reviews', 'Reviews' . $badge, 'manage_woocommerce', self::PAGE, [ __CLASS__, 'admin_page' ] );
	}

	private static function back( string $msg ): void {
		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE, 'msg' => rawurlencode( $msg ) ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_settings(): void {
		check_admin_referer( 'luma_reviews_settings' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Not allowed' );
		}
		$o             = (array) get_option( self::OPTION, [] );
		$o['place_id'] = sanitize_text_field( wp_unslash( $_POST['place_id'] ?? '' ) );
		$key           = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
		if ( '' !== $key && ! str_starts_with( $key, '••' ) ) {
			$o['api_key'] = $key;
		}
		if ( ! empty( $_POST['clear_key'] ) ) {
			$o['api_key'] = '';
		}
		$o['strip_at'] = max( 1, (int) ( $_POST['strip_at'] ?? 50 ) );
		$o['strip']    = 'off' === ( $_POST['strip'] ?? '' ) ? 'off' : 'auto';
		update_option( self::OPTION, $o, false );
		self::back( 'Settings saved.' );
	}

	public static function handle_sync(): void {
		check_admin_referer( 'luma_reviews_sync' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Not allowed' );
		}
		self::back( self::sync()['msg'] );
	}

	public static function handle_decide(): void {
		$id = (int) ( $_POST['id'] ?? 0 );
		check_admin_referer( 'luma_reviews_decide_' . $id );
		if ( ! current_user_can( 'manage_woocommerce' ) || self::CPT !== get_post_type( $id ) ) {
			wp_die( 'Not allowed' );
		}
		$do = sanitize_key( $_POST['do'] ?? '' );
		if ( 'approve' === $do ) {
			wp_update_post( [ 'ID' => $id, 'edit_date' => true, 'post_status' => 'publish' ] );
			update_post_meta( $id, '_luma_rev_note', 'Approved ' . wp_date( 'M j, Y' ) . ' by ' . wp_get_current_user()->display_name );
			$msg = 'Approved — it is on the reviews page.';
		} elseif ( 'reject' === $do ) {
			wp_update_post( [ 'ID' => $id, 'edit_date' => true, 'post_status' => 'draft' ] );
			update_post_meta( $id, '_luma_rev_note', 'Not shown: describes use or effects (' . wp_date( 'M j, Y' ) . ')' );
			$msg = 'Kept off the site.';
		} elseif ( 'delete' === $do && 'manual' === get_post_meta( $id, '_luma_rev_source', true ) ) {
			wp_delete_post( $id, true );
			$msg = 'Deleted.';
		} else {
			$msg = 'Nothing changed.';
		}
		self::back( $msg );
	}

	public static function handle_add(): void {
		check_admin_referer( 'luma_reviews_add' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Not allowed' );
		}
		$text = sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) );
		$auth = sanitize_text_field( wp_unslash( $_POST['author'] ?? '' ) );
		$link = esc_url_raw( wp_unslash( $_POST['link'] ?? '' ) );
		$date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
		if ( ! $text || ! $auth || ! $link ) {
			self::back( 'Reviewer name, review text and the link to the review on Google are all required.' );
		}
		$id = wp_insert_post( [
			'post_type'   => self::CPT,
			'post_status' => 'pending',
			'post_title'  => wp_trim_words( $text, 8, '…' ),
			'post_date'     => $date ? $date . ' 12:00:00' : current_time( 'mysql' ),
			'post_date_gmt' => get_gmt_from_date( $date ? $date . ' 12:00:00' : current_time( 'mysql' ) ),
		] );
		foreach ( [ '_luma_rev_source' => 'manual', '_luma_rev_text' => $text, '_luma_rev_author' => $auth, '_luma_rev_link' => $link,
			'_luma_rev_rating' => min( 5, max( 1, (int) ( $_POST['rating'] ?? 5 ) ) ) ] as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		self::back( 'Added to the queue — approve it below to publish.' );
	}

	/** Highlight flag words so use/effect language is easy to spot. */
	private static function flagged( string $text ): array {
		$html = esc_html( $text );
		$hits = [];
		foreach ( self::FLAGS as $w ) {
			$re = '/\b(' . preg_quote( $w, '/' ) . ')/i';
			if ( preg_match( $re, $text ) ) {
				$hits[] = $w;
				$html   = preg_replace( $re, '<mark>$1</mark>', $html );
			}
		}
		return [ $html, $hits ];
	}

	private static function stars( int $n ): string {
		return str_repeat( '★', $n ) . str_repeat( '☆', max( 0, 5 - $n ) );
	}

	public static function admin_page(): void {
		$s    = self::summary();
		$key  = self::opt( 'api_key' );
		$msg  = isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : '';
		$list = fn( $status ) => array_map( [ __CLASS__, 'row' ], get_posts( [ 'post_type' => self::CPT, 'post_status' => $status, 'posts_per_page' => 100 ] ) );
		$post = admin_url( 'admin-post.php' );
		echo '<div class="wrap luma-dash luma-rev"><h1>Reviews <small>Google reviews → approval queue → <a href="' . esc_url( self::page_url() ) . '" target="_blank">reviews page</a></small></h1>';
		echo '<style>.luma-rev mark{background:#F3E2DC;color:#9A3A26;padding:0 2px;border-radius:3px}.luma-rev .rv{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px;margin:0 0 10px;display:grid;grid-template-columns:minmax(0,1fr) 230px;gap:16px}.luma-rev .rv.flag{border-left:4px solid #B4432C}.luma-rev .st{color:#C9A66B;letter-spacing:2px}.luma-rev .meta{color:#646970;font-size:12px}.luma-rev .rv form{display:inline}.luma-rev .rule{background:#fff;border:1px solid #dcdcde;border-left:4px solid #7E8C74;border-radius:8px;padding:12px 16px;max-width:900px}.luma-rev details{margin:18px 0}.luma-rev summary{cursor:pointer;font-weight:600}@media(max-width:900px){.luma-rev .rv{grid-template-columns:1fr}}</style>';
		if ( $msg ) {
			echo '<div class="notice notice-info"><p>' . esc_html( $msg ) . '</p></div>';
		}
		if ( $s['error'] ) {
			echo '<div class="notice notice-error"><p>Last sync failed: ' . esc_html( $s['error'] ) . '</p></div>';
		}

		echo '<div class="luma-cards">';
		echo '<div class="luma-card"><b>' . ( $s['count'] ? esc_html( number_format( $s['rating'], 1 ) ) . ' ★' : '—' ) . '</b><span>Google rating</span></div>';
		echo '<div class="luma-card"><b>' . (int) $s['count'] . '</b><span>Reviews on Google</span></div>';
		echo '<div class="luma-card"><b>' . count( self::visible() ) . '</b><span>Shown on the site</span></div>';
		echo '<div class="luma-card"><b>' . ( self::strip_on() ? 'On' : 'Off' ) . '</b><span>Homepage strip (at ' . (int) self::opt( 'strip_at' ) . ' Google reviews)</span></div>';
		echo '</div>';

		echo '<div class="rule"><p><b>The approval rule — same for every star rating.</b> Approve reviews about ordering, shipping, packaging, communication and documentation. Keep off any review that describes using a product or its effects on a person or animal (results, dosing, how someone felt). Don\'t reject a review for being critical: if a 2-star shipping complaint passes the rule, it gets approved too. Highlighted words are hints, not verdicts.</p></div>';

		$pending = $list( 'pending' );
		echo '<h2>Waiting for approval (' . count( $pending ) . ')</h2>';
		if ( ! $pending ) {
			echo '<div class="luma-empty">Nothing to review. ' . ( self::enabled() ? 'New Google reviews are pulled in once a day.' : 'Add your Google Place ID and API key below to start pulling reviews.' ) . '</div>';
		}
		foreach ( $pending as $r ) {
			self::admin_row( $r, $post, [ 'approve' => 'Approve', 'reject' => 'Keep off site' ] );
		}

		echo '<details><summary>On the site (' . count( $list( 'publish' ) ) . ')</summary>';
		foreach ( $list( 'publish' ) as $r ) {
			self::admin_row( $r, $post, [ 'reject' => 'Take down' ] );
		}
		echo '</details><details><summary>Kept off the site (' . count( $list( 'draft' ) ) . ')</summary>';
		foreach ( $list( 'draft' ) as $r ) {
			self::admin_row( $r, $post, [ 'approve' => 'Approve after all' ] );
		}
		echo '</details>';

		echo '<details><summary>Add a review by hand</summary><p class="meta">For a Google review the daily pull missed (the API only returns your five most recent). Copy it exactly — don\'t edit the reviewer\'s words.</p>';
		echo '<form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="luma_reviews_add">' . wp_nonce_field( 'luma_reviews_add', '_wpnonce', true, false );
		echo '<table class="form-table"><tr><th>Reviewer name</th><td><input class="regular-text" name="author" required></td></tr><tr><th>Stars</th><td><select name="rating">';
		foreach ( [ 5, 4, 3, 2, 1 ] as $n ) {
			echo '<option value="' . $n . '">' . $n . '</option>';
		}
		echo '</select></td></tr><tr><th>Review text</th><td><textarea name="text" rows="4" class="large-text" required></textarea></td></tr><tr><th>Link to the review on Google</th><td><input class="regular-text" type="url" name="link" required><p class="description">On Google Maps: open the review → Share → Copy link.</p></td></tr><tr><th>Date posted</th><td><input type="date" name="date"></td></tr></table>';
		submit_button( 'Add to queue', 'secondary' );
		echo '</form></details>';

		echo '<h2>Settings</h2><form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="luma_reviews_settings">' . wp_nonce_field( 'luma_reviews_settings', '_wpnonce', true, false );
		echo '<table class="form-table"><tr><th>Google Place ID</th><td><input class="regular-text code" name="place_id" value="' . esc_attr( self::opt( 'place_id' ) ) . '" placeholder="ChIJ…"><p class="description">Find it with Google\'s Place ID Finder once your Business Profile is live. The footer link and the page\'s review buttons appear once this is set.</p></td></tr>';
		echo '<tr><th>Places API key</th><td><input class="regular-text code" name="api_key" value="' . ( $key ? '••••••••' . esc_attr( substr( $key, -4 ) ) : '' ) . '" autocomplete="off"> ' . ( $key ? '<label><input type="checkbox" name="clear_key" value="1"> Remove key</label>' : '' ) . '<p class="description">Google Cloud key restricted to the Places API (New). Stored in the database, never in the repo.</p></td></tr>';
		echo '<tr><th>Homepage strip</th><td><select name="strip"><option value="auto"' . selected( self::opt( 'strip' ), 'auto', false ) . '>Show automatically</option><option value="off"' . selected( self::opt( 'strip' ), 'off', false ) . '>Never</option></select> once Google shows <input type="number" min="1" name="strip_at" value="' . (int) self::opt( 'strip_at' ) . '" style="width:70px"> reviews (and at least 3 are approved)</td></tr></table>';
		submit_button( 'Save settings' );
		echo '</form>';
		echo '<form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="luma_reviews_sync">' . wp_nonce_field( 'luma_reviews_sync', '_wpnonce', true, false );
		submit_button( 'Pull from Google now', 'secondary', 'submit', false );
		echo ' <span class="meta">' . ( $s['synced'] ? 'Last pulled ' . esc_html( wp_date( 'M j, g:i a', strtotime( $s['synced'] ) ) ) : 'Never pulled' ) . '</span></form></div>';
	}

	private static function admin_row( array $r, string $post, array $actions ): void {
		[ $html, $hits ] = self::flagged( $r['text'] );
		echo '<div class="rv' . ( $hits ? ' flag' : '' ) . '"><div><span class="st">' . esc_html( self::stars( $r['rating'] ) ) . '</span> <b>' . esc_html( $r['author'] ?: '(expired from Google cache)' ) . '</b> <span class="meta">' . esc_html( $r['date'] ) . ' · ' . ( 'manual' === $r['source'] ? 'added by hand' : 'from Google' ) . ( $r['link'] ? ' · <a href="' . esc_url( $r['link'] ) . '" target="_blank" rel="noopener">view on Google</a>' : '' ) . '</span>';
		echo '<p>' . ( $html ? nl2br( $html ) : '<i class="meta">(rating only, no text — not shown on the site)</i>' ) . '</p>';
		if ( $hits ) {
			echo '<p class="meta">Check for use/effect language: ' . esc_html( implode( ', ', $hits ) ) . '</p>';
		}
		if ( $r['note'] ) {
			echo '<p class="meta">' . esc_html( $r['note'] ) . '</p>';
		}
		echo '</div><div>';
		if ( 'manual' === $r['source'] ) {
			$actions['delete'] = 'Delete';
		}
		foreach ( $actions as $do => $label ) {
			echo '<form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="luma_reviews_decide"><input type="hidden" name="id" value="' . (int) $r['id'] . '"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">' . wp_nonce_field( 'luma_reviews_decide_' . $r['id'], '_wpnonce', true, false );
			submit_button( $label, 'approve' === $do ? 'primary' : 'secondary', 'submit', false );
			echo '</form> ';
		}
		echo '</div></div>';
	}

	/* ---------- front end ---------- */

	public static function card( array $r ): string {
		$name  = esc_html( $r['author'] );
		$who   = $r['author_url'] ? '<a href="' . esc_url( $r['author_url'] ) . '" target="_blank" rel="noopener nofollow">' . $name . '</a>' : $name;
		$photo = $r['photo'] ? '<img class="rev-avatar" src="' . esc_url( $r['photo'] ) . '" alt="" width="36" height="36" loading="lazy" referrerpolicy="no-referrer">' : '<span class="rev-avatar rev-initial" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( $r['author'], 0, 1 ) ) ) . '</span>';
		$out   = '<figure class="rev-card"><div class="rev-stars" role="img" aria-label="' . (int) $r['rating'] . ' out of 5 stars">' . esc_html( self::stars( $r['rating'] ) ) . '</div>';
		$out  .= '<blockquote>' . wpautop( esc_html( $r['text'] ) ) . '</blockquote>';
		$out  .= '<figcaption>' . $photo . '<span><b>' . $who . '</b><small>' . esc_html( $r['date'] ) . ( $r['link'] ? ' · <a href="' . esc_url( $r['link'] ) . '" target="_blank" rel="noopener">on Google</a>' : '' ) . '</small></span></figcaption></figure>';
		return $out;
	}

	public static function summary_html(): string {
		$s = self::summary();
		if ( ! $s['count'] ) {
			return '';
		}
		$label = sprintf( '%s out of 5 from %d Google review%s', number_format( $s['rating'], 1 ), $s['count'], 1 === $s['count'] ? '' : 's' );
		return '<div class="rev-summary"><span class="rev-score">' . esc_html( number_format( $s['rating'], 1 ) ) . '</span><span><span class="rev-stars" role="img" aria-label="' . esc_attr( $label ) . '">' . esc_html( self::stars( (int) round( $s['rating'] ) ) ) . '</span><small>' . (int) $s['count'] . ' review' . ( 1 === $s['count'] ? '' : 's' ) . ' on Google</small></span></div>';
	}

	public static function shortcode(): string {
		$reviews = self::visible();
		$write   = self::write_url();
		$all     = self::all_url();
		$out     = self::summary_html();
		$btns    = '';
		if ( $write ) {
			$btns .= '<a class="btn btn-primary" href="' . esc_url( $write ) . '" target="_blank" rel="noopener">Leave a review on Google</a>';
		}
		if ( $all && self::summary()['count'] ) {
			$btns .= '<a class="btn btn-outline" href="' . esc_url( $all ) . '" target="_blank" rel="noopener">See all reviews on Google ↗</a>';
		}
		$out .= $btns ? '<div class="rev-actions">' . $btns . '</div>' : '';
		if ( $reviews ) {
			$out .= '<div class="rev-grid">' . implode( '', array_map( [ __CLASS__, 'card' ], $reviews ) ) . '</div>';
		} else {
			$out .= '<div class="rev-empty"><p>No reviews yet. ' . ( $write ? 'Ordered from us? We\'d be grateful if you <a href="' . esc_url( $write ) . '" target="_blank" rel="noopener">left one on Google</a>.' : 'Check back soon.' ) . '</p></div>';
		}
		$out .= '<p class="rev-note">Reviews come from our Google Business Profile and are shown with each reviewer\'s name and a link to the original. We publish reviews about ordering, shipping, packaging and documentation, whatever the rating. Because our products are for laboratory research use only, reviews describing use of a product are not published here; ' . ( $all && self::summary()['count'] ? '<a href="' . esc_url( $all ) . '" target="_blank" rel="noopener">every review is on Google</a>.' : 'every review remains on Google.' ) . '</p>';
		return $out;
	}

	/** Homepage strip: rating + three most recent approved reviews. Renders nothing until strip_on(). */
	public static function strip(): string {
		if ( ! self::strip_on() ) {
			return '';
		}
		$out  = '<section class="section rev-strip" aria-label="Customer reviews"><div class="wrap"><div class="section-heading"><div><span class="eyebrow">Reviews</span><h2>From our customers.</h2></div>' . self::summary_html() . '</div>';
		$out .= '<div class="rev-grid">' . implode( '', array_map( [ __CLASS__, 'card' ], self::visible( 3 ) ) ) . '</div>';
		$out .= '<p class="rev-more"><a class="text-link" href="' . esc_url( self::page_url() ) . '">Read more reviews ↗</a></p></div></section>';
		return $out;
	}
}
