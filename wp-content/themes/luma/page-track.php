<?php
/**
 * Template Name: Track order
 * Guest order tracking: order number + email (WooCommerce's own lookup), styled like the account.
 */
get_header();
?>
<main id="main">
<div class="wrap page-head" style="text-align:center"><span class="eyebrow">Orders</span><h1>Track your order</h1><p style="margin-inline:auto">See payment, lab release, shipping and the certificate for every lot in an order — no account needed.</p></div>
<div class="wrap woo-page track-page" style="padding-bottom:5rem">
	<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
</div>
</main>
<?php get_footer(); ?>
