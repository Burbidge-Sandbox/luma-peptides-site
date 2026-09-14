<?php
/**
 * Template Name: Testing
 * The flagship page (UX-SPEC §2): lot lookup + COA library + plain-language
 * explanation of what the tests measure and do not measure.
 */
get_header();
?>
<main id="content" class="testing">
	<section class="section testing-hero">
		<div class="wrap">
			<p class="eyebrow mono"><?php esc_html_e( 'Third-party testing · Freedom Diagnostics', 'luma' ); ?></p>
			<h1><?php esc_html_e( 'Every lot, tested. Look yours up.', 'luma' ); ?></h1>
			<p class="lede"><?php esc_html_e( 'Each lot is sent to an independent laboratory for identity, assay purity and net content. Enter the lot number printed on your vial to see its certificate of analysis.', 'luma' ); ?></p>
			<?php echo do_shortcode( '[luma_lot_lookup]' ); ?>
		</div>
	</section>
	<section class="section">
		<div class="wrap">
			<h2><?php esc_html_e( 'Certificate library', 'luma' ); ?></h2>
			<?php echo do_shortcode( '[luma_coa_library]' ); ?>
		</div>
	</section>
	<section class="section testing-explainer">
		<div class="wrap">
			<h2><?php esc_html_e( 'What the tests measure', 'luma' ); ?></h2>
			<dl class="explainer">
				<dt><?php esc_html_e( 'Identity', 'luma' ); ?></dt>
				<dd><?php esc_html_e( 'Mass spectrometry confirms the measured molecular mass matches the expected compound. It answers “is this the stated peptide?” and nothing else.', 'luma' ); ?></dd>
				<dt><?php esc_html_e( 'Assay purity', 'luma' ); ?></dt>
				<dd><?php esc_html_e( 'HPLC separates the sample and reports what percentage of the peptide-containing peaks is the target compound. It does not detect non-peptide contaminants, solvents or endotoxin unless those are tested separately.', 'luma' ); ?></dd>
				<dt><?php esc_html_e( 'Net content', 'luma' ); ?></dt>
				<dd><?php esc_html_e( 'The measured mass of material in the vial against the label claim.', 'luma' ); ?></dd>
				<dt><?php esc_html_e( 'What it does not tell you', 'luma' ); ?></dt>
				<dd><?php esc_html_e( 'A certificate of analysis describes a sample from a lot at the time of testing. It is not a statement about safety, suitability, or effects in any organism. All material is supplied for in-vitro research only.', 'luma' ); ?></dd>
			</dl>
			<?php luma_ruo_notice(); ?>
		</div>
	</section>
</main>
<?php get_footer(); ?>
