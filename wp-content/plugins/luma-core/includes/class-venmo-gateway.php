<?php
/**
 * WooCommerce gateway for Venmo (manual). Loaded lazily by Venmo::register().
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class VenmoGateway extends \WC_Payment_Gateway {
	public function __construct() {
		$this->id                 = Venmo::ID;
		$this->method_title       = 'Venmo (manual)';
		$this->method_description = 'The customer places the order, then pays your Venmo handle with the order number in the note. Orders wait as "On hold" until you confirm payment on the Luma dashboard.';
		$this->has_fields         = false;
		$this->supports           = [ 'products' ];
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	public function init_form_fields() {
		$d = Venmo::settings();
		$this->form_fields = [
			'enabled'     => [ 'title' => 'Enable', 'type' => 'checkbox', 'label' => 'Offer Venmo at checkout', 'default' => 'yes' ],
			'title'       => [ 'title' => 'Name at checkout', 'type' => 'text', 'default' => $d['title'] ],
			'description' => [ 'title' => 'Description at checkout', 'type' => 'textarea', 'default' => $d['description'] ],
			'handle'      => [ 'title' => 'Venmo handle', 'type' => 'text', 'default' => $d['handle'], 'description' => 'Without the @. Customers are sent to venmo.com/<handle>.' ],
		];
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$order->update_status( 'on-hold', 'Awaiting Venmo payment to @' . Venmo::handle() . ' (note: Order #' . $order->get_order_number() . ').' );
		wc_reduce_stock_levels( $order_id );
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}
		return [ 'result' => 'success', 'redirect' => $this->get_return_url( $order ) ];
	}
}
