<?php
/**
 * "Same-day delivery — free": a zone method that only produces a rate when the
 * destination ZIP is in radius and the order window is open right now.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

class SameDayMethod extends \WC_Shipping_Method {
	public function __construct( $instance_id = 0 ) {
		$this->id                 = SameDay::METHOD_ID;
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = 'Same-day delivery (Luma)';
		$this->method_description = 'Free same-day delivery when the ZIP is within the configured radius and the order is placed before the cutoff. Configure on WooCommerce → Luma Core.';
		$this->supports           = [ 'shipping-zones', 'instance-settings' ];
		$this->title              = 'Same-day delivery — free';
		$this->enabled            = 'yes';
		$this->init_form_fields();
		$this->init_settings();
		$this->title = $this->get_option( 'title', $this->title );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	public function init_form_fields() {
		$this->instance_form_fields = [
			'title' => [ 'title' => 'Label at checkout', 'type' => 'text', 'default' => 'Same-day delivery — free' ],
		];
	}

	public function calculate_shipping( $package = [] ) {
		$zip = (string) ( $package['destination']['postcode'] ?? '' );
		if ( 'US' !== ( $package['destination']['country'] ?? 'US' ) || ! SameDay::eligible( $zip ) ) {
			return;
		}
		$this->add_rate( [
			'id'      => $this->get_rate_id(),
			'label'   => $this->title . ' (order by ' . SameDay::cutoff_label() . ')',
			'cost'    => 0,
			'package' => $package,
		] );
	}
}
