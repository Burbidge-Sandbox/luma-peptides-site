<?php
/**
 * Plugin Name: Luma Core
 * Plugin URI:  https://lumaresearchco.com
 * Description: Luma Peptides Co. store logic — RUO acknowledgement gate, catalogue rules, lots & certificates of analysis, operational facts. Enforces CLAUDE.md; not optional.
 * Version:     0.3.1
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author:      Luma Peptides Co.
 * Text Domain: luma-core
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'LUMA_CORE_VERSION', '0.3.1' );
define( 'LUMA_CORE_FILE', __FILE__ );
define( 'LUMA_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUMA_CORE_TERMS_VERSION', '2026-09-14' ); // bump when RUO terms wording changes

require_once LUMA_CORE_DIR . 'includes/class-settings.php';
require_once LUMA_CORE_DIR . 'includes/class-catalogue-rules.php';
require_once LUMA_CORE_DIR . 'includes/class-ruo-gate.php';
require_once LUMA_CORE_DIR . 'includes/class-lots.php';
require_once LUMA_CORE_DIR . 'includes/class-pricing.php';
require_once LUMA_CORE_DIR . 'includes/class-seed.php';
require_once LUMA_CORE_DIR . 'includes/class-order-numbers.php';

add_action( 'plugins_loaded', function () {
	Luma\Core\Settings::init();
	Luma\Core\Lots::init();

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>Luma Core: WooCommerce is not active. The store cannot run.</p></div>';
		} );
		return;
	}
	Luma\Core\CatalogueRules::init();
	Luma\Core\RuoGate::init();
	Luma\Core\Pricing::init();
	Luma\Core\Seed::init();
	Luma\Core\OrderNumbers::init();
} );

/* Rewrite flush without relying on activation hooks (the plugin may be loaded by luma-bootstrap). */
add_action( 'init', function () {
	if ( get_option( 'luma_core_version' ) !== LUMA_CORE_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'luma_core_version', LUMA_CORE_VERSION );
	}
}, 99 );

/* HPOS (custom order tables) compatibility declaration. */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', LUMA_CORE_FILE, true );
	}
} );

/** Theme-facing helper: operational facts for the fact strip. */
function luma_core_facts(): array {
	return Luma\Core\Settings::facts();
}
