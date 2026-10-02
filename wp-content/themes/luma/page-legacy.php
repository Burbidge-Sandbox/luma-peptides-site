<?php
/**
 * Template Name: Legacy page
 * Renders imported static-site markup (about, faq, contact, shipping-returns,
 * privacy, terms) exactly as authored — no wpautop, no block wrappers.
 */
get_header();
?>
<main id="main">
<?php
if ( is_page( 'faq' ) && is_readable( __DIR__ . '/parts/faq-v2.php' ) ) :
	include __DIR__ . '/parts/faq-v2.php'; // v2 layout; legacy FAQ copy stays in the database
else :
while ( have_posts() ) :
	the_post();
	echo luma_legacy_content_patch( get_the_content() ); // phpcs:ignore WordPress.Security.EscapeOutput -- authored HTML from the repo
endwhile;
endif;
?>
</main>
<?php get_footer(); ?>
