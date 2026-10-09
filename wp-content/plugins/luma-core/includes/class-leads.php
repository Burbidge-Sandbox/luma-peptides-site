<?php
/**
 * /labs/ leads: the three-field claim form behind POST /wp-json/luma/v1/claim.
 *
 * Order of operations on a valid claim: save the lead (so it exists even if
 * anything later fails) → create or reuse the lead's LAB coupon → apply it to
 * the cart session and set the cookie → prefill checkout (guests only) →
 * notify → return the shop URL. Leads are a private post type listed under
 * WooCommerce → Lab leads, with a follow-up filter and CSV export.
 */

namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Leads {

	const CPT = 'luma_lead';

	/** Shown verbatim under the form and stored on every lead (TCPA/CTIA consent record). */
	const CONSENT = 'By continuing you agree to receive order and offer messages from Luma by email and text. Msg & data rates may apply. Reply STOP to opt out.';

	const STATUSES = [
		'claimed'   => 'Claimed',
		'purchased' => 'Purchased',
		'contacted' => 'Contacted',
		'lost'      => 'Lost',
	];

	const SOURCE_KEYS = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'referrer', 'landing' ];

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( 'woocommerce_order_status_changed', [ __CLASS__, 'on_order' ], 15, 4 );

		/* Admin list: WooCommerce → Lab leads. */
		add_filter( 'manage_' . self::CPT . '_posts_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'column' ], 10, 2 );
		add_action( 'restrict_manage_posts', [ __CLASS__, 'filters' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'apply_filters' ] );
		add_filter( 'bulk_actions-edit-' . self::CPT, [ __CLASS__, 'bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-' . self::CPT, [ __CLASS__, 'handle_bulk' ], 10, 3 );
		add_filter( 'post_row_actions', [ __CLASS__, 'row_actions' ], 10, 2 );
		add_action( 'add_meta_boxes', [ __CLASS__, 'meta_box' ] );
		add_action( 'save_post_' . self::CPT, [ __CLASS__, 'save_box' ], 10, 2 );
		add_action( 'admin_post_luma_leads_csv', [ __CLASS__, 'csv' ] );
	}

	public static function register(): void {
		$cap = 'manage_woocommerce';
		register_post_type( self::CPT, [
			'labels'          => [
				'name'          => 'Lab leads',
				'singular_name' => 'Lab lead',
				'menu_name'     => 'Lab leads',
				'all_items'     => 'Lab leads',
				'edit_item'     => 'Lab lead',
				'search_items'  => 'Search leads',
				'not_found'     => 'No leads yet.',
			],
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'woocommerce',
			'show_in_rest'    => false,
			'supports'        => [ 'title' ],
			'map_meta_cap'    => false,
			'capabilities'    => [
				'edit_post'          => $cap,
				'read_post'          => $cap,
				'delete_post'        => $cap,
				'edit_posts'         => $cap,
				'edit_others_posts'  => $cap,
				'publish_posts'      => $cap,
				'read_private_posts' => $cap,
				'delete_posts'       => $cap,
				'create_posts'       => 'do_not_allow',
			],
		] );
	}

	/* ---------- Claim ---------- */

	public static function routes(): void {
		register_rest_route( 'luma/v1', '/claim', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'claim' ],
			'permission_callback' => '__return_true',
		] );
		/* Fresh form nonce for a /labs/ page that sat in a cache or an open tab. */
		register_rest_route( 'luma/v1', '/claim-nonce', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				$res = rest_ensure_response( [ 'nonce' => wp_create_nonce( 'luma_claim' ) ] );
				$res->header( 'Cache-Control', 'no-store, private' );
				return $res;
			},
		] );
	}

	public static function e164( string $digits ): string {
		$digits = preg_replace( '/\D/', '', $digits );
		if ( 10 === strlen( $digits ) ) {
			return '+1' . $digits;
		}
		if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
			return '+' . $digits;
		}
		return '+' . $digits;
	}

	/** "+13855215259" → "(385) 521-5259"; other formats are returned unchanged. */
	public static function pretty_phone( string $e164 ): string {
		return preg_match( '/^\+1(\d{3})(\d{3})(\d{4})$/', $e164, $m ) ? "({$m[1]}) {$m[2]}-{$m[3]}" : $e164;
	}

	/** Split on the last space: "Mary Ann Smith" → ["Mary Ann", "Smith"]. */
	public static function split_name( string $name ): array {
		$name = trim( preg_replace( '/\s+/', ' ', $name ) );
		$at   = strrpos( $name, ' ' );
		return false === $at ? [ $name, '' ] : [ substr( $name, 0, $at ), substr( $name, $at + 1 ) ];
	}

	private static function fail( string $code, string $msg, int $status ) {
		return new \WP_Error( $code, $msg, [ 'status' => $status ] );
	}

	public static function claim( \WP_REST_Request $r ) {
		nocache_headers();
		$p = (array) ( $r->get_json_params() ?: $r->get_body_params() );

		if ( ! wp_verify_nonce( (string) ( $p['nonce'] ?? '' ), 'luma_claim' ) ) {
			return self::fail( 'luma_nonce', 'This page has expired. Please try again.', 403 );
		}
		if ( '' !== trim( (string) ( $p['website'] ?? '' ) ) ) {
			return self::fail( 'luma_rejected', 'Request rejected.', 400 ); // honeypot: people never see this field
		}
		if ( Lots::rate_limited( 'claim', 5, 10 * MINUTE_IN_SECONDS ) ) {
			return self::fail( 'luma_slow_down', 'Too many attempts. Please wait a few minutes and try again.', 429 );
		}

		$name   = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) ( $p['name'] ?? '' ) ) ) );
		$email  = strtolower( sanitize_email( (string) ( $p['email'] ?? '' ) ) );
		$digits = preg_replace( '/\D/', '', (string) ( $p['phone'] ?? '' ) );
		if ( mb_strlen( $name ) < 2 || ! is_email( $email ) || strlen( $digits ) < 10 || strlen( $digits ) > 15 ) {
			return self::fail( 'luma_invalid', 'Please enter your name, a valid email and phone number.', 400 );
		}
		$phone           = self::e164( $digits );
		[ $first, $last ] = self::split_name( $name );
		$src             = self::source( (array) ( $p['src'] ?? [] ) );
		$shop            = wc_get_page_permalink( 'shop' );

		/* Repeat claim: same email or phone reuses that lead's code; no second coupon. */
		$lead = self::find( $email, $phone );
		if ( $lead ) {
			$coupon = new \WC_Coupon( (string) get_post_meta( $lead->ID, '_lead_coupon', true ) );
			update_post_meta( $lead->ID, '_lead_claims', (int) get_post_meta( $lead->ID, '_lead_claims', true ) + 1 );
			update_post_meta( $lead->ID, '_lead_last_claim', gmdate( 'c' ) );
			$state = LabOffer::usable( $coupon );
			self::notify_owner( $lead->ID, $name, $email, $phone, $coupon, $src, 'ok' === $state ? 'repeat claim, same code' : 'repeat claim, code ' . $state );
			if ( 'ok' !== $state ) {
				LabOffer::clear_cookie();
				return rest_ensure_response( [ 'redirect' => add_query_arg( 'offer', 'used', $shop ) ] );
			}
			$same_email = $email === strtolower( (string) get_post_meta( $lead->ID, '_lead_email', true ) );
			self::start_session( $coupon, $first, $last, $email, $phone, ! $same_email );
			if ( $same_email && strtotime( (string) get_post_meta( $lead->ID, '_lead_code_sent', true ) ) < time() - DAY_IN_SECONDS ) {
				self::email_code( $lead->ID, $coupon, $first, $email );
			}
			return rest_ensure_response( [ 'redirect' => $shop ] );
		}

		/* 1. Save the lead first. */
		$lead_id = wp_insert_post( [
			'post_type'   => self::CPT,
			'post_status' => 'publish',
			'post_title'  => $name,
		], true );
		if ( is_wp_error( $lead_id ) ) {
			return self::fail( 'luma_save', 'Could not save right now. Please try again.', 500 );
		}
		$meta = [
			'_lead_first'        => $first,
			'_lead_last'         => $last,
			'_lead_email'        => $email,
			'_lead_phone'        => $phone,
			'_lead_status'       => 'claimed',
			'_lead_consent_text' => self::CONSENT,
			'_lead_consent_ts'   => gmdate( 'c' ),
			'_lead_ip'           => Lots::client_ip(),
			'_lead_user_agent'   => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 300 ),
			'_lead_user_id'      => get_current_user_id(),
			'_lead_source'       => $src,
			'_lead_claims'       => 1,
			'_lead_last_claim'   => gmdate( 'c' ),
		];
		foreach ( $meta as $k => $v ) {
			update_post_meta( $lead_id, $k, $v );
		}

		/* 2. Coupon. */
		$coupon = LabOffer::create_coupon( $email, $lead_id, $name );
		if ( $coupon ) {
			update_post_meta( $lead_id, '_lead_coupon', strtoupper( $coupon->get_code() ) );
			/* 3 + 4. Session, cookie, checkout prefill. */
			self::start_session( $coupon, $first, $last, $email, $phone, false );
		}

		/* Notifications never block the redirect. */
		try {
			self::notify_owner( $lead_id, $name, $email, $phone, $coupon, $src, '' );
			if ( $coupon ) {
				self::email_code( $lead_id, $coupon, $first, $email );
			}
		} catch ( \Throwable $e ) {
			error_log( 'Luma lead notify failed: ' . $e->getMessage() ); // phpcs:ignore
		}

		/* 5. Where the browser goes next. */
		return rest_ensure_response( [ 'redirect' => $shop ] );
	}

	/** Apply the code, set the cookie, and prefill checkout from the claim (guests only). */
	private static function start_session( \WC_Coupon $coupon, string $first, string $last, string $email, string $phone, bool $mask ): void {
		if ( ! LabOffer::load_cart() ) {
			return;
		}
		if ( WC()->session ) {
			if ( ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}
			WC()->session->set( LabOffer::SESSION_MASK, $mask ? 1 : null );
		}
		/* Block checkout reads these from the Store API cart, so they arrive prefilled. A signed-in customer's saved details are left alone. */
		if ( ! is_user_logged_in() && WC()->customer ) {
			$c = WC()->customer;
			$c->set_billing_first_name( $first );
			$c->set_billing_last_name( $last );
			$c->set_billing_email( $email );
			$c->set_billing_phone( $phone );
			$c->set_shipping_first_name( $first );
			$c->set_shipping_last_name( $last );
			$c->set_shipping_phone( $phone ); // block checkout shows the shipping form (billing = shipping by default)
			$c->save();
		}
		LabOffer::apply( $coupon );
		LabOffer::set_cookie( $coupon );
		if ( WC()->session ) {
			WC()->session->save_data();
		}
	}

	public static function find( string $email, string $phone ): ?\WP_Post {
		$q = get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				[ 'key' => '_lead_email', 'value' => $email ],
				[ 'key' => '_lead_phone', 'value' => $phone ],
			],
		] );
		/* Prefer an exact email match when both an email lead and a phone lead exist. */
		if ( $q && strtolower( (string) get_post_meta( $q[0]->ID, '_lead_email', true ) ) !== $email ) {
			$by_email = get_posts( [ 'post_type' => self::CPT, 'post_status' => 'any', 'posts_per_page' => 1, 'meta_key' => '_lead_email', 'meta_value' => $email ] ); // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( $by_email ) {
				return $by_email[0];
			}
		}
		return $q[0] ?? null;
	}

	private static function source( array $in ): array {
		$out = [];
		foreach ( self::SOURCE_KEYS as $k ) {
			$v = isset( $in[ $k ] ) ? (string) $in[ $k ] : '';
			$v = in_array( $k, [ 'referrer', 'landing' ], true ) ? esc_url_raw( $v ) : sanitize_text_field( $v );
			if ( '' !== $v ) {
				$out[ $k ] = substr( $v, 0, 500 );
			}
		}
		return $out;
	}

	public static function source_label( array $s ): string {
		if ( ! empty( $s['utm_source'] ) ) {
			return trim( $s['utm_source'] . ( ! empty( $s['utm_medium'] ) ? ' / ' . $s['utm_medium'] : '' ) . ( ! empty( $s['utm_campaign'] ) ? ' · ' . $s['utm_campaign'] : '' ) );
		}
		if ( ! empty( $s['fbclid'] ) ) {
			return 'facebook (fbclid)';
		}
		if ( ! empty( $s['gclid'] ) ) {
			return 'google (gclid)';
		}
		if ( ! empty( $s['referrer'] ) ) {
			return (string) wp_parse_url( $s['referrer'], PHP_URL_HOST );
		}
		return 'direct';
	}

	/* ---------- Notifications ---------- */

	private static function notify_owner( int $lead_id, string $name, string $email, string $phone, ?\WC_Coupon $coupon, array $src, string $note ): void {
		$code  = $coupon && $coupon->get_id() ? strtoupper( $coupon->get_code() ) : '(no code)';
		$exp   = $coupon && $coupon->get_id() ? LabOffer::expires_label( $coupon ) : '';
		$pp    = self::pretty_phone( $phone );
		$admin = admin_url( 'post.php?post=' . $lead_id . '&action=edit' );
		$lines = [
			'Name: ' . $name,
			'Email: ' . $email,
			'Phone: ' . $pp,
			'Code: ' . $code . ( $exp ? ' (expires ' . $exp . ')' : '' ),
			'Source: ' . self::source_label( $src ),
		];
		if ( $note ) {
			$lines[] = 'Note: ' . $note;
		}
		foreach ( [ 'utm_campaign', 'utm_content', 'utm_term', 'referrer', 'landing' ] as $k ) {
			if ( ! empty( $src[ $k ] ) ) {
				$lines[] = ucfirst( str_replace( 'utm_', 'utm ', $k ) ) . ': ' . $src[ $k ];
			}
		}
		$lines[] = '';
		$lines[] = 'Lead: ' . $admin;
		wp_mail(
			(string) Settings::get( 'alert_email' ) ?: get_option( 'admin_email' ),
			'Discount claimed: ' . $name . ' · ' . $email . ' · ' . $pp,
			implode( "\n", $lines ),
			[ 'Reply-To: ' . $name . ' <' . $email . '>' ]
		);
		$o = OrderAlerts::opts();
		if ( '1' === $o['enabled'] && $o['token'] && $o['users'] ) {
			OrderAlerts::push( 'Discount claimed', $name . "\n" . $email . "\n" . $pp . "\n" . $code . ' · ' . self::source_label( $src ) . ( $note ? "\n" . $note : '' ), $admin, 'Open lead', 0 );
		}
	}

	/** The lead's way back if they leave: code, expiry, one button. */
	public static function email_code( int $lead_id, \WC_Coupon $coupon, string $first, string $email ): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		$code   = strtoupper( $coupon->get_code() );
		$pct    = $coupon->get_amount() + 0;
		$exp    = LabOffer::expires_label( $coupon );
		$url    = add_query_arg( 'coupon', rawurlencode( $code ), wc_get_page_permalink( 'shop' ) );
		$mailer = WC()->mailer();
		$body   = '<p class="luma-eyebrow">New lab discount</p>'
			. '<p>' . ( $first ? 'Hi ' . esc_html( $first ) . ',' : 'Hello,' ) . '</p>'
			. '<p>Your code is <b style="font-family:Menlo,Consolas,monospace;font-size:18px;letter-spacing:.06em">' . esc_html( $code ) . '</b>: ' . esc_html( (string) $pct ) . '% off your first order, with free next-day delivery. It expires ' . esc_html( $exp ) . '.</p>'
			. '<p style="margin:22px 0"><a class="luma-btn" href="' . esc_url( $url ) . '">Shop with ' . esc_html( (string) $pct ) . '% off</a></p>'
			. '<p class="luma-fine">The discount is applied automatically from that link. Use ' . esc_html( $email ) . ' at checkout. One discount per customer.</p>';
		$mailer->send(
			$email,
			'Your ' . $pct . '% off code: ' . $code,
			$mailer->wrap_message( 'Your ' . $pct . '% off is ready', $body ),
			[ 'Content-Type: text/html; charset=UTF-8', 'Reply-To: Luma Peptides Co. <' . ( (string) Settings::get( 'alert_email' ) ?: 'info@lumaresearchco.com' ) . '>' ]
		);
		update_post_meta( $lead_id, '_lead_code_sent', gmdate( 'c' ) );
	}

	/* ---------- Purchased ---------- */

	public static function on_order( $order_id, $from, $to, $order ): void {
		if ( ! in_array( $to, [ 'processing', 'on-hold', 'completed' ], true ) ) {
			return;
		}
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_coupon_codes() as $code ) {
			$c       = LabOffer::from_code( $code );
			$lead_id = $c ? (int) $c->get_meta( LabOffer::LEAD ) : 0;
			if ( $lead_id && self::CPT === get_post_type( $lead_id ) ) {
				update_post_meta( $lead_id, '_lead_status', 'purchased' );
				update_post_meta( $lead_id, '_lead_order', $order->get_id() );
			}
		}
	}

	/* ---------- Admin ---------- */

	public static function columns( array $cols ): array {
		return [
			'cb'          => $cols['cb'],
			'lead_date'   => 'Date',
			'title'       => 'Name',
			'lead_email'  => 'Email',
			'lead_phone'  => 'Phone',
			'lead_coupon' => 'Coupon',
			'lead_status' => 'Status',
			'lead_order'  => 'Order',
			'lead_source' => 'Source',
		];
	}

	public static function column( string $col, int $id ): void {
		$m = fn( $k ) => (string) get_post_meta( $id, $k, true );
		switch ( $col ) {
			case 'lead_date':
				echo esc_html( get_the_date( 'M j, Y g:ia', $id ) );
				break;
			case 'lead_email':
				echo '<a href="mailto:' . esc_attr( $m( '_lead_email' ) ) . '">' . esc_html( $m( '_lead_email' ) ) . '</a>';
				break;
			case 'lead_phone':
				echo '<a href="sms:' . esc_attr( $m( '_lead_phone' ) ) . '">' . esc_html( self::pretty_phone( $m( '_lead_phone' ) ) ) . '</a>';
				break;
			case 'lead_coupon':
				$code = $m( '_lead_coupon' );
				$c    = $code ? LabOffer::from_code( $code ) : null;
				echo $code ? '<code>' . esc_html( $code ) . '</code><br><small>' . esc_html( $c ? LabOffer::usable( $c ) : 'deleted' ) . '</small>' : '—';
				break;
			case 'lead_status':
				$s = $m( '_lead_status' ) ?: 'claimed';
				echo esc_html( self::STATUSES[ $s ] ?? $s );
				$n = (int) $m( '_lead_claims' );
				echo $n > 1 ? '<br><small>' . (int) $n . ' claims</small>' : '';
				break;
			case 'lead_order':
				$o = (int) $m( '_lead_order' );
				$order = $o ? wc_get_order( $o ) : null;
				echo $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>' : '—';
				break;
			case 'lead_source':
				echo esc_html( self::source_label( (array) get_post_meta( $id, '_lead_source', true ) ) );
				break;
		}
	}

	private static function is_list_screen(): bool {
		global $typenow, $pagenow;
		return is_admin() && 'edit.php' === $pagenow && self::CPT === $typenow;
	}

	public static function filters( $post_type ): void {
		if ( self::CPT !== $post_type ) {
			return;
		}
		$cur = isset( $_GET['lead_status'] ) ? sanitize_key( $_GET['lead_status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<label class="screen-reader-text" for="lead_status">Status</label><select name="lead_status" id="lead_status"><option value="">All statuses</option>';
		foreach ( [ 'claimed' => 'Claimed, no purchase' ] + array_diff_key( self::STATUSES, [ 'claimed' => 1 ] ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
		$csv = wp_nonce_url( add_query_arg( [ 'action' => 'luma_leads_csv', 'lead_status' => $cur ], admin_url( 'admin-post.php' ) ), 'luma_leads_csv' );
		echo ' <a class="button" href="' . esc_url( $csv ) . '">Export CSV</a> ';
	}

	public static function apply_filters( \WP_Query $q ): void {
		if ( ! self::is_list_screen() || ! $q->is_main_query() ) {
			return;
		}
		$cur = isset( $_GET['lead_status'] ) ? sanitize_key( $_GET['lead_status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $cur && isset( self::STATUSES[ $cur ] ) ) {
			$q->set( 'meta_query', [ [ 'key' => '_lead_status', 'value' => $cur ] ] );
		}
		/* Search covers email and phone too (titles hold the name). */
		$s = (string) $q->get( 's' );
		if ( $s && ( str_contains( $s, '@' ) || preg_match( '/\d{3}/', $s ) ) ) {
			$q->set( 's', '' );
			$q->set( 'meta_query', [ 'relation' => 'OR', [ 'key' => '_lead_email', 'value' => $s, 'compare' => 'LIKE' ], [ 'key' => '_lead_phone', 'value' => preg_replace( '/\D/', '', $s ), 'compare' => 'LIKE' ] ] );
		}
	}

	public static function bulk_actions( array $a ): array {
		unset( $a['edit'] );
		return [ 'luma_contacted' => 'Mark contacted', 'luma_lost' => 'Mark lost', 'luma_claimed' => 'Mark claimed (follow up)' ] + $a;
	}

	public static function handle_bulk( $redirect, $action, $ids ) {
		$map = [ 'luma_contacted' => 'contacted', 'luma_lost' => 'lost', 'luma_claimed' => 'claimed' ];
		if ( isset( $map[ $action ] ) && current_user_can( 'manage_woocommerce' ) ) {
			foreach ( (array) $ids as $id ) {
				update_post_meta( (int) $id, '_lead_status', $map[ $action ] );
			}
		}
		return $redirect;
	}

	public static function row_actions( array $actions, \WP_Post $post ): array {
		if ( self::CPT === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	public static function meta_box(): void {
		add_meta_box( 'luma_lead', 'Lead', [ __CLASS__, 'render_box' ], self::CPT, 'normal', 'high' );
	}

	public static function render_box( \WP_Post $post ): void {
		wp_nonce_field( 'luma_lead_save', 'luma_lead_nonce' );
		$m   = fn( $k ) => get_post_meta( $post->ID, $k, true );
		$cur = (string) $m( '_lead_status' ) ?: 'claimed';
		$o   = (int) $m( '_lead_order' );
		$ord = $o ? wc_get_order( $o ) : null;
		$rows = [
			'Email'          => (string) $m( '_lead_email' ),
			'Phone'          => self::pretty_phone( (string) $m( '_lead_phone' ) ) . ' (' . $m( '_lead_phone' ) . ')',
			'Coupon'         => (string) $m( '_lead_coupon' ),
			'Order'          => $ord ? '#' . $ord->get_order_number() : '—',
			'Claims'         => (string) ( $m( '_lead_claims' ) ?: 1 ) . ' · last ' . $m( '_lead_last_claim' ),
			'Consent'        => (string) $m( '_lead_consent_text' ),
			'Consent time'   => (string) $m( '_lead_consent_ts' ) . ' UTC · IP ' . $m( '_lead_ip' ),
			'User agent'     => (string) $m( '_lead_user_agent' ),
		];
		foreach ( (array) $m( '_lead_source' ) as $k => $v ) {
			$rows[ 'Source: ' . $k ] = (string) $v;
		}
		echo '<table class="form-table"><tr><th><label for="lead_status_f">Status</label></th><td><select id="lead_status_f" name="_lead_status">';
		foreach ( self::STATUSES as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		foreach ( $rows as $k => $v ) {
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
		}
		echo '</table>';
	}

	public static function save_box( int $id, \WP_Post $post ): void {
		if ( ! isset( $_POST['luma_lead_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['luma_lead_nonce'] ), 'luma_lead_save' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s = sanitize_key( $_POST['_lead_status'] ?? '' );
		if ( isset( self::STATUSES[ $s ] ) ) {
			update_post_meta( $id, '_lead_status', $s );
		}
	}

	public static function csv(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'luma_leads_csv' ) ) {
			wp_die( 'Not allowed.' );
		}
		$cur  = isset( $_GET['lead_status'] ) ? sanitize_key( $_GET['lead_status'] ) : '';
		$args = [ 'post_type' => self::CPT, 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' ];
		if ( $cur && isset( self::STATUSES[ $cur ] ) ) {
			$args['meta_query'] = [ [ 'key' => '_lead_status', 'value' => $cur ] ]; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=luma-lab-leads-' . ( $cur ?: 'all' ) . '-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array_merge( [ 'date_utc', 'first_name', 'last_name', 'email', 'phone', 'coupon', 'coupon_state', 'status', 'order', 'claims', 'consent_text', 'consent_ts', 'ip' ], self::SOURCE_KEYS ), ',', '"', '\\' );
		foreach ( get_posts( $args ) as $p ) {
			$m    = fn( $k ) => (string) get_post_meta( $p->ID, $k, true );
			$c    = $m( '_lead_coupon' ) ? LabOffer::from_code( $m( '_lead_coupon' ) ) : null;
			$o    = (int) $m( '_lead_order' );
			$ord  = $o ? wc_get_order( $o ) : null;
			$src  = (array) get_post_meta( $p->ID, '_lead_source', true );
			$row  = [ get_gmt_from_date( $p->post_date ), $m( '_lead_first' ), $m( '_lead_last' ), $m( '_lead_email' ), $m( '_lead_phone' ), $m( '_lead_coupon' ), $c ? LabOffer::usable( $c ) : 'deleted', $m( '_lead_status' ), $ord ? $ord->get_order_number() : '', $m( '_lead_claims' ), $m( '_lead_consent_text' ), $m( '_lead_consent_ts' ), $m( '_lead_ip' ) ];
			foreach ( self::SOURCE_KEYS as $k ) {
				$row[] = (string) ( $src[ $k ] ?? '' );
			}
			/* Neutralise spreadsheet formulas in user-supplied cells. */
			$row = array_map( fn( $v ) => preg_match( '/^[=+\-@\t\r]/', (string) $v ) && ! preg_match( '/^\+\d+$/', (string) $v ) ? "'" . $v : $v, $row );
			fputcsv( $out, $row, ',', '"', '\\' );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
}
