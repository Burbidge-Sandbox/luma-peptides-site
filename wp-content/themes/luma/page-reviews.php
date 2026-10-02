<?php
/**
 * Template Name: Reviews
 * Approved Google reviews (luma-core [luma_reviews]). Rules: CLAUDE.md rule 3.
 */
get_header();
?>
<main id="main">
<div class="wrap page-head" style="text-align:center"><span class="eyebrow">Reviews</span><h1>What customers say</h1><p style="margin-inline:auto">Reviews from our Google Business Profile on ordering, shipping, packaging and documentation.</p></div>
<div class="wrap reviews-page" style="padding-bottom:5rem">
	<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
</div>
</main>
<?php get_footer(); ?>
