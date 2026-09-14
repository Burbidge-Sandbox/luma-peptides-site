<?php
/**
 * Front page — placeholder for the UX-SPEC §1 running order.
 * Step 3 of the build replaces this with the full sequence. Nothing here may
 * describe an effect, a benefit, or a person.
 */
get_header();
?>
<main id="content">
	<section class="hero section">
		<div class="wrap">
			<p class="eyebrow"><?php echo esc_html( luma_legal_name() ); ?> · <?php esc_html_e( 'Research-use-only reference material', 'luma' ); ?></p>
			<h1><?php esc_html_e( 'Peptide reference material, tested by lot.', 'luma' ); ?></h1>
			<p class="lede"><?php esc_html_e( 'Lyophilized synthetic peptides supplied for in-vitro and analytical research, each lot accompanied by a third-party certificate of analysis.', 'luma' ); ?></p>
			<div class="cta-pair">
				<a class="btn btn-primary" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Shop compounds', 'luma' ); ?></a>
				<a class="btn btn-outline" href="<?php echo esc_url( home_url( '/testing/' ) ); ?>"><?php esc_html_e( 'See the testing', 'luma' ); ?></a>
			</div>
		</div>
	</section>
	<?php luma_fact_strip(); ?>
	<section class="section">
		<div class="wrap">
			<?php luma_ruo_notice(); ?>
		</div>
	</section>
</main>
<?php get_footer(); ?>
