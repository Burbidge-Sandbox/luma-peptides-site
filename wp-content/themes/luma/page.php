<?php get_header(); ?>
<main id="main">
<?php if ( function_exists( 'is_account_page' ) && is_account_page() ) : [ $title, $lede ] = luma_account_head(); ?>
<div class="wrap page-head" style="text-align:center"><span class="eyebrow">Account</span><h1 id="acctTitle"><?php echo esc_html( $title ); ?></h1><?php if ( $lede ) : ?><p id="acctLede" style="margin-inline:auto"><?php echo esc_html( $lede ); ?></p><?php endif; ?></div>
<div class="wrap woo-page account-page" style="padding-bottom:5rem">
<?php else : ?>
<div class="wrap page-head"><h1><?php the_title(); ?></h1></div>
<div class="wrap woo-page" style="padding-bottom:5rem">
<?php endif; ?>
	<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
</div>
</main>
<?php get_footer(); ?>
