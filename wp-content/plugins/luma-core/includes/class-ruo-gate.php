<?php
/**
 * RUO acknowledgement gate at checkout (CLAUDE.md "Always present").
 *
 * Independent of payment method. Rendered on classic checkout and registered
 * as a block-checkout field; validated server-side in both paths; stored on
 * the order as `_luma_ruo_ack` (ISO timestamp + terms version) so the record
 * exists for every sale — this is also what payment processors ask to see.
 */

namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class RuoGate {

	const FIELD = 'luma_ruo_ack';
	const META  = '_luma_ruo_ack';

	public static function init(): void {
		/* Classic checkout. */
		add_action( 'woocommerce_review_order_before_submit', [ __CLASS__, 'render_classic' ], 5 );
		add_action( 'woocommerce_checkout_process', [ __CLASS__, 'validate_classic' ] );
		add_action( 'woocommerce_checkout_create_order', [ __CLASS__, 'store_classic' ], 10, 2 );

		/* Block checkout (Woo 8.9+ additional checkout fields API). */
		add_action( 'woocommerce_init', [ __CLASS__, 'register_block_field' ] );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ __CLASS__, 'store_block' ], 10, 2 );

		/* Surface the record: admin order screen + customer emails. */
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ __CLASS__, 'admin_display' ] );
		/* Email line is rendered by Luma\Core\Emails (single, status-aware). */
		add_action( 'woocommerce_store_api_checkout_order_processed', [ __CLASS__, 'scrub_block_field' ] );
	}

	public static function label(): string {
		return __( 'I confirm I am 21 or older, ordering on behalf of a laboratory or research organization, and that these materials are for in-vitro research only and will not be administered to any human or animal. I accept the Terms.', 'luma-core' );
	}

	private static function stamp(): string {
		return gmdate( 'c' ) . ' v' . LUMA_CORE_TERMS_VERSION;
	}

	/* ----- Classic ----- */

	public static function render_classic(): void {
		if ( function_exists( 'luma_ruo_notice' ) ) {
			luma_ruo_notice();
		}
		woocommerce_form_field( self::FIELD, [
			'type'     => 'checkbox',
			'class'    => [ 'form-row', 'luma-ruo-ack' ],
			'label'    => self::label(),
			'required' => true,
		], (int) ( $_POST[ self::FIELD ] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification -- Woo verifies
	}

	public static function validate_classic(): void {
		if ( empty( $_POST[ self::FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wc_add_notice( __( 'The research-use acknowledgement is required to place an order.', 'luma-core' ), 'error' );
		}
	}

	public static function store_classic( \WC_Order $order, array $data ): void {
		if ( ! empty( $_POST[ self::FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$order->update_meta_data( self::META, self::stamp() );
		}
	}

	/* ----- Block checkout ----- */

	public static function register_block_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		woocommerce_register_additional_checkout_field( [
			'id'                => 'luma/ruo-ack',
			'label'             => self::label(),
			'location'          => 'order',
			'type'              => 'checkbox',
			'required'          => true,
			'error_message'     => __( 'The research-use acknowledgement is required to place an order.', 'luma-core' ),
		] );
	}

	public static function store_block( \WC_Order $order, $request ): void {
		$fields = $request->get_param( 'additional_fields' );
		if ( is_array( $fields ) && ! empty( $fields['luma/ruo-ack'] ) ) {
			$order->update_meta_data( self::META, self::stamp() );
		}
	}

	/**
	 * Woo stores the block field as `_wc_other/luma/ruo-ack` and prints it under an
	 * "Additional information" heading in emails and order views. The timestamped
	 * `_luma_ruo_ack` is the record we keep; drop the raw field so it isn't shown twice.
	 */
	public static function scrub_block_field( \WC_Order $order ): void {
		if ( $order->get_meta( self::META ) && $order->meta_exists( '_wc_other/luma/ruo-ack' ) ) {
			$order->delete_meta_data( '_wc_other/luma/ruo-ack' );
			$order->save();
		}
	}

	/* ----- Display ----- */

	public static function admin_display( \WC_Order $order ): void {
		$v = $order->get_meta( self::META );
		echo '<p><strong>RUO acknowledgement:</strong> ' . ( $v ? esc_html( $v ) : '<span style="color:#b32d2e">MISSING</span>' ) . '</p>';
	}

	public static function email_display( \WC_Order $order ): void {
		$v = $order->get_meta( self::META );
		if ( $v ) {
			echo '<p style="font-size:12px;color:#555">Research-use acknowledgement recorded ' . esc_html( $v ) . '. For laboratory research use only. Not for human or veterinary use.</p>';
		}
	}
}
