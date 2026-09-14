<?php get_header(); ?>
<main id="main"><div class="wrap page-head">
	<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
		<h1><?php the_title(); ?></h1><?php the_content(); ?>
	<?php endwhile; else : ?><h1>Nothing here</h1><?php endif; ?>
</div></main>
<?php get_footer(); ?>
