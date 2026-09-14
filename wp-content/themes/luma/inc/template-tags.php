<?php
/**
 * Reusable template tags. Copy here is the canonical RUO wording; change it
 * in one place. Vocabulary per CLAUDE.md "Allowed vocabulary" only.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The RUO notice component (UX-SPEC §4 `.ruo-notice`).
 * Used in the product buy box, checkout, and footer.
 */
function luma_ruo_notice( bool $echo = true ): string {
	$html = '<div class="ruo-notice" role="note">'
		. '<b>For laboratory research use only.</b> Not for human or veterinary use. '
		. 'Not a drug, cosmetic, or dietary supplement. Supplied as reference material for in-vitro research.'
		. '</div>';
	if ( $echo ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- static markup
	}
	return $html;
}

/**
 * Operational facts for the fact strip. Facts with an expiry date must come
 * from luma-core settings (verified before each deploy), never be hardcoded
 * in templates. Falls back to nothing rather than a stale claim.
 */
function luma_fact_strip(): void {
	$facts = function_exists( 'luma_core_facts' ) ? luma_core_facts() : [];
	$facts = array_values( array_filter( array_map( 'trim', (array) $facts ) ) );
	if ( ! $facts ) {
		return;
	}
	echo '<section class="fact-strip" aria-label="Operational facts"><div class="wrap"><ul>';
	foreach ( $facts as $f ) {
		echo '<li>' . esc_html( $f ) . '</li>';
	}
	echo '</ul></div></section>';
}

/** Legal and marketing names, single source. */
function luma_legal_name(): string {
	return 'Luma Peptides Co.';
}
function luma_marketing_name(): string {
	return 'Luma Research Co';
}
