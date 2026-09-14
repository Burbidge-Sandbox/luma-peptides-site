<?php get_header(); ?>
<main id="main">
<div class="wrap page-head"><h1><?php the_title(); ?></h1></div>
<div class="wrap woo-page" style="padding-bottom:5rem">
	<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
</div>
</main>
<?php get_footer(); ?>
