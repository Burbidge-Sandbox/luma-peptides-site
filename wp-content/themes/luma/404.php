<?php get_header(); ?>
<main id="content" class="wrap section">
	<h1><?php esc_html_e( 'Page not found', 'luma' ); ?></h1>
	<p><a class="btn btn-outline" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the catalogue', 'luma' ); ?></a></p>
</main>
<?php get_footer(); ?>
