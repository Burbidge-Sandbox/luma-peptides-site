<?php
/**
 * Transactional email branding: Luma palette on WooCommerce's email templates,
 * legacy confirmation copy, RUO statement in every footer, lot-verify pointer.
 * Delivery (SMTP) is configured separately — see docs/WOO-MIGRATION.md.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class Emails {
	const TERRA = '#B4432C';
	const INK   = '#2A2523';
	const INK2  = '#4F4744';
	const CREAM = '#F7F2EC';
	const PAPER = '#FDFBF8';
	const LINE  = '#E6DCD2';
	const MUTED = '#6e625b';

	public static function init(): void {
		add_filter( 'woocommerce_email_from_name', fn() => 'Luma Peptides Co.' );
		add_filter( 'woocommerce_email_footer_text', [ __CLASS__, 'footer' ] );
		add_filter( 'woocommerce_email_styles', [ __CLASS__, 'styles' ] );

		// Palette defaults (only if the admin has not changed them from Woo's stock values).
		add_filter( 'option_woocommerce_email_base_color', fn( $v ) => in_array( $v, [ '#720eec', '#7f54b3', '' ], true ) ? self::TERRA : $v );
		add_filter( 'option_woocommerce_email_background_color', fn( $v ) => in_array( $v, [ '#f7f7f7', '' ], true ) ? self::CREAM : $v );
		add_filter( 'option_woocommerce_email_body_background_color', fn( $v ) => in_array( $v, [ '#ffffff', '' ], true ) ? self::PAPER : $v );
		add_filter( 'option_woocommerce_email_text_color', fn( $v ) => in_array( $v, [ '#3c3c3c', '' ], true ) ? self::INK : $v );

		// Subjects + headings (legacy confirmation voice).
		add_filter( 'woocommerce_email_subject_customer_processing_order', fn( $s, $o ) => sprintf( 'Order %s confirmed — Luma Peptides Co.', $o->get_order_number() ), 10, 2 );
		add_filter( 'woocommerce_email_heading_customer_processing_order', fn() => 'Thank you. Your order is in.' );
		add_filter( 'woocommerce_email_subject_customer_on_hold_order', fn( $s, $o ) => sprintf( 'Order %s reserved — payment pending', $o->get_order_number() ), 10, 2 );
		add_filter( 'woocommerce_email_heading_customer_on_hold_order', fn() => 'One more step to complete your order.' );
		add_filter( 'woocommerce_email_subject_customer_completed_order', fn( $s, $o ) => sprintf( 'Order %s has shipped — Luma Peptides Co.', $o->get_order_number() ), 10, 2 );
		add_filter( 'woocommerce_email_heading_customer_completed_order', fn() => 'Your order is on its way.' );
		add_filter( 'woocommerce_email_subject_customer_refunded_order', fn( $s, $o ) => sprintf( 'Order %s refunded — Luma Peptides Co.', $o->get_order_number() ), 10, 2 );
		add_filter( 'woocommerce_email_subject_new_order', fn( $s, $o ) => sprintf( '[Luma] New order %s · %s', $o->get_order_number(), html_entity_decode( wp_strip_all_tags( $o->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ) ), 10, 2 );

		add_action( 'woocommerce_email_order_details', [ __CLASS__, 'intro' ], 5, 4 );
		add_action( 'woocommerce_email_after_order_table', [ __CLASS__, 'after_table' ], 10, 4 );
	}

	public static function footer( $text ): string {
		$verify = function_exists( 'luma_catalogue_json' ) ? luma_catalogue_json()['config']['urls']['verify'] : home_url( '/testing/' );
		return 'For laboratory research use only. Not for human or animal use. Every lot is independently tested; look up a certificate of analysis at ' . esc_url( $verify ) . ". \n"
			. 'Luma Peptides Co. · ' . esc_html( Settings::get( 'alert_email' ) );
	}

	public static function styles( $css ): string {
		return $css . '
#wrapper { background-color:' . self::CREAM . '; padding:48px 0; }
#template_container { box-shadow:none !important; border:1px solid ' . self::LINE . '; border-radius:14px; overflow:hidden; background:' . self::PAPER . '; }
#template_header { background-color:' . self::PAPER . ' !important; border-bottom:1px solid ' . self::LINE . '; border-radius:0; }
#template_header h1 { color:' . self::INK . ' !important; font-family:Georgia,"Times New Roman",serif !important; font-weight:500 !important; font-size:30px !important; letter-spacing:-.01em; text-shadow:none !important; }
#template_header_image { display:none; }
#body_content_inner, #body_content_inner p, #body_content_inner td, #body_content_inner th { color:' . self::INK2 . ' !important; font-family:Inter,-apple-system,"Segoe UI",Helvetica,Arial,sans-serif !important; font-size:15px !important; line-height:1.55 !important; }
#body_content_inner h2 { color:' . self::INK . ' !important; font-family:Georgia,"Times New Roman",serif !important; font-weight:500 !important; font-size:20px !important; }
#body_content_inner h2 a, #body_content_inner a { color:' . self::TERRA . ' !important; }
.td { border-color:' . self::LINE . ' !important; }
table.td th { font-size:11px !important; letter-spacing:.1em; text-transform:uppercase; color:' . self::MUTED . ' !important; }
#template_footer { background:' . self::CREAM . '; }
#template_footer #credit { color:' . self::MUTED . ' !important; font-size:12px !important; font-family:Inter,-apple-system,"Segoe UI",Helvetica,Arial,sans-serif !important; line-height:1.5; }
.luma-eyebrow { font-size:11px; letter-spacing:.14em; text-transform:uppercase; color:' . self::TERRA . '; font-weight:600; margin:0 0 6px; }
.luma-fine { font-size:12px !important; color:' . self::MUTED . ' !important; margin-top:18px; }
#body_content_inner a[style*="background-color"] { color:#fff !important; background-color:' . self::TERRA . ' !important; border-radius:8px !important; font-size:12px !important; letter-spacing:.08em; text-transform:uppercase; font-weight:600 !important; padding:12px 22px !important; }
.luma-btn { display:inline-block; background:' . self::TERRA . '; color:#fff !important; text-decoration:none; padding:12px 22px; border-radius:8px; font-size:12px; letter-spacing:.08em; text-transform:uppercase; font-weight:600; }
';
	}

	/** Eyebrow above the order table on customer emails (mirrors the confirmation page). */
	public static function intro( $order, $sent_to_admin, $plain_text, $email ): void {
		if ( $sent_to_admin || $plain_text || ! $order instanceof \WC_Order ) {
			return;
		}
		echo '<p class="luma-eyebrow">' . esc_html( self::eyebrow( $order ) ) . ' · ' . esc_html( $order->get_order_number() ) . '</p>';
	}

	public static function eyebrow( \WC_Order $order ): string {
		switch ( $order->get_status() ) {
			case 'refunded':
				return 'Order refunded';
			case 'cancelled':
				return 'Order cancelled';
			case 'failed':
				return 'Payment not completed';
			case 'completed':
				return 'Order shipped';
			case 'processing':
				return 'Order confirmed · payment received';
			default:
				return $order->is_paid() ? 'Order confirmed · payment received' : 'Order reserved · payment pending';
		}
	}

	/** RUO acknowledgement + next steps under the order table. */
	public static function after_table( $order, $sent_to_admin, $plain_text, $email ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$urls   = function_exists( 'luma_catalogue_json' ) ? luma_catalogue_json()['config']['urls'] : [];
		$verify = $urls['verify'] ?? home_url( '/testing/' );
		$orders = $urls['orders'] ?? wc_get_account_endpoint_url( 'orders' );
		$ack    = (string) $order->get_meta( '_luma_ruo_ack' );

		if ( $plain_text ) {
			echo "\n" . ( $ack ? 'Research-use acknowledgement recorded ' . $ack . ".\n" : '' )
				. "Each vial is labelled with its lot number; look up the certificate of analysis at {$verify}\n"
				. "Track this order: {$orders}\n\n";
			return;
		}
		if ( $sent_to_admin ) {
			echo '<p class="luma-fine">' . ( $ack ? 'RUO acknowledgement ' . esc_html( substr( $ack, 0, 16 ) ) . ' UTC · ' : '<b>No RUO acknowledgement stored.</b> · ' ) . 'Stock was reduced at checkout. Assign the lot on the order before marking it Completed.</p>';
			return;
		}
		$closed = $order->has_status( [ 'refunded', 'cancelled', 'failed' ] );
		if ( $order->has_status( 'refunded' ) ) {
			echo '<p>The refund has been sent back to your original payment method. Depending on your bank it can take 5–10 business days to appear.</p>';
		} elseif ( $order->has_status( 'completed' ) ) {
			echo '<p>Each vial is labelled with its lot number. Enter it on the lot lookup page to see the independent certificate of analysis for exactly what you received.</p>';
		} elseif ( $order->has_status( 'processing' ) ) {
			echo '<p>Your order is queued for lab release. You will get a second email with tracking when it ships, usually within one business day.</p>';
		}
		if ( ! $closed ) {
			echo '<p style="margin:18px 0"><a class="luma-btn" href="' . esc_url( $orders ) . '">Track this order</a>&nbsp;&nbsp;<a href="' . esc_url( $verify ) . '" style="font-size:13px">Verify a lot →</a></p>';
		}
		if ( $ack ) {
			echo '<p class="luma-fine">Research-use acknowledgement recorded ' . esc_html( $ack ) . '. For laboratory research use only. Not for human or animal use.' . ( $closed ? '' : ' All sales final once shipped.' ) . '</p>';
		}
	}
}
