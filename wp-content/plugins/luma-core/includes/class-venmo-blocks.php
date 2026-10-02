<?php
/**
 * Block-checkout integration for the Venmo gateway.
 */
namespace Luma\Core;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class VenmoBlocks extends AbstractPaymentMethodType {
	protected $name = Venmo::ID;

	public function initialize() {
		$this->settings = Venmo::settings();
	}

	public function is_active() {
		return 'yes' === ( $this->settings['enabled'] ?? 'yes' );
	}

	public function get_payment_method_script_handles() {
		wp_register_script( 'luma-venmo-blocks', Venmo::asset_url( 'assets/venmo-blocks.js' ), [ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ], LUMA_CORE_VERSION, true );
		return [ 'luma-venmo-blocks' ];
	}

	public function get_payment_method_data() {
		return [
			'title'       => $this->settings['title'],
			'description' => $this->settings['description'],
			'supports'    => [ 'products' ],
		];
	}
}
