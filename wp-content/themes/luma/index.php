<?php get_header(); ?>
<main id="content" class="wrap section">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<h1><?php the_title(); ?></h1>
				<?php the_content(); ?>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Nothing here', 'luma' ); ?></h1>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
