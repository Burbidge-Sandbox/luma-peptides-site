<?php
/**
 * Plugin Name: Luma Bootstrap
 * Description: Wires the git-deployed source (wp-content/luma-src) into WordPress: registers its themes directory and loads Luma Core. Install once by hand; never needs updating.
 * Version:     1.0.0
 * Author:      Luma Peptides Co.
 */

defined( 'ABSPATH' ) || exit;

$luma_src = WP_CONTENT_DIR . '/luma-src/wp-content';

/* Themes from the deployed repo appear in Appearance → Themes. */
if ( is_dir( $luma_src . '/themes' ) ) {
	register_theme_directory( $luma_src . '/themes' );
}

/* Load Luma Core from the deployed repo unless it is also installed as a normal plugin. */
$luma_core = $luma_src . '/plugins/luma-core/luma-core.php';
if ( is_file( $luma_core ) && ! defined( 'LUMA_CORE_VERSION' ) ) {
	require_once $luma_core;
}

add_action( 'admin_notices', function () use ( $luma_src ) {
	if ( ! is_dir( $luma_src ) ) {
		echo '<div class="notice notice-warning"><p>Luma Bootstrap: <code>wp-content/luma-src</code> not found. Run a Pull in Cloudways → Deployment via GIT.</p></div>';
	}
} );
