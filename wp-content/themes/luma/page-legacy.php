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
while ( have_posts() ) :
	the_post();
	echo get_the_content(); // phpcs:ignore WordPress.Security.EscapeOutput -- authored HTML from the repo
endwhile;
?>
</main>
<?php get_footer(); ?>
