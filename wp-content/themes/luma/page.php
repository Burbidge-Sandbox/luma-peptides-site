<?php get_header(); ?>
<main id="main">
<?php if ( function_exists( 'is_account_page' ) && is_account_page() ) : [ $title, $lede ] = luma_account_head(); ?>
<div class="acct-band"><div class="wrap page-head" style="text-align:center"><span class="eyebrow">Account</span><h1 id="acctTitle"><?php echo esc_html( $title ); ?></h1><?php if ( $lede ) : ?><p id="acctLede" style="margin-inline:auto"><?php echo esc_html( $lede ); ?></p><?php endif; ?></div></div>
<div class="wrap woo-page account-page" style="padding-bottom:5rem">
	<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
</div>

<?php elseif ( function_exists( 'is_cart' ) && is_cart() ) : $u = luma_catalogue_json()['config']['urls']; ?>
<div class="wrap page-head"><div class="breadcrumb"><a href="<?php echo esc_url( $u['home'] ); ?>">Home</a> › Cart</div><h1>Your cart</h1></div>
<div class="wrap cart-layout">
 <div class="panel" id="cartList"><p style="color:var(--muted)">Loading your cart…</p></div>
 <div class="panel summary"><h2>Order summary</h2><div id="summaryBody"></div></div>
</div>

<?php elseif ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) : $u = luma_catalogue_json()['config']['urls']; ?>
<div class="wrap page-head"><div class="breadcrumb"><a href="<?php echo esc_url( $u['cart'] ); ?>">Cart</a> › Checkout</div><h1>Checkout</h1>
 <div class="steps-bar"><span>Cart</span><span><b>Information &amp; payment</b></span><span>Confirmation</span></div></div>
<div class="wrap woo-page checkout-page" style="padding-top:0;padding-bottom:5rem">
	<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
	<div class="secure" style="margin-top:1.5rem"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Encrypted checkout · Card details never touch our server · All sales final once shipped</div>
</div>

<?php else : ?>
<div class="wrap page-head"><h1><?php the_title(); ?></h1></div>
<div class="wrap woo-page" style="padding-bottom:5rem">
	<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
</div>
<?php endif; ?>
</main>
<?php get_footer(); ?>
