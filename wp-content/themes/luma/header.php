<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#F7F2EC">
<link rel="icon" href="<?php echo esc_url( luma_assets_url( 'assets/favicon.svg' ) ); ?>" type="image/svg+xml">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$luma_urls = luma_catalogue_json()['config']['urls'];
$luma_nav  = [
	[ $luma_urls['home'],    'Home',        is_front_page() ],
	[ $luma_urls['shop'],    'Catalog',     is_shop() || is_product_category() || is_product() ],
	[ $luma_urls['faq'],     'FAQ',         is_page( 'faq' ) ],
	[ $luma_urls['verify'],  'Verify a Lot', is_page( 'testing' ) ],
	[ $luma_urls['orders'],  'Track Order', is_account_page() && is_user_logged_in() && ( is_wc_endpoint_url( 'orders' ) || is_wc_endpoint_url( 'view-order' ) ) ],
	[ $luma_urls['contact'], 'Contact',     is_page( 'contact' ) ],
];
?>
<a class="skip" href="#main">Skip to content</a>
<div class="announce"><span class="full">For laboratory research use only &nbsp;·&nbsp; Not for human or animal use &nbsp;·&nbsp; Every lot independently tested</span><span class="short">For laboratory research use only · Not for human use</span></div>
<header class="header" id="header"><div class="wrap nav">
 <a class="logo" href="<?php echo esc_url( $luma_urls['home'] ); ?>" aria-label="Luma Peptides Co. home"><span>luma</span><span>peptides</span><span>co.</span></a>
 <ul class="nav-links"><?php foreach ( $luma_nav as [ $h, $t, $cur ] ) : ?><li><a href="<?php echo esc_url( $h ); ?>"<?php echo $cur ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $t ); ?></a></li><?php endforeach; ?></ul>
 <div class="nav-actions">
  <a class="btn btn-primary btn-sm" href="<?php echo esc_url( $luma_urls['shop'] ); ?>">Catalog</a>
  <a class="icon-btn" href="<?php echo esc_url( $luma_urls['account'] ); ?>" aria-label="Account"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></a>
  <button class="icon-btn" id="cartBtn" aria-label="Open cart"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l1 14H5z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg><span class="cart-count" id="cartCount">0</span></button>
  <button class="icon-btn burger" id="burger" aria-label="Open menu" aria-controls="mobileMenu" aria-expanded="false"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
 </div>
</div></header>
<nav class="mobile-menu" id="mobileMenu" aria-label="Mobile">
 <button class="icon-btn close" id="closeMenu" aria-label="Close menu"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
 <?php foreach ( $luma_nav as [ $h, $t ] ) : ?><a href="<?php echo esc_url( $h ); ?>"><?php echo esc_html( $t ); ?></a><?php endforeach; ?>
 <a href="<?php echo esc_url( $luma_urls['account'] ); ?>">Account</a>
 <a href="<?php echo esc_url( $luma_urls['cart'] ); ?>">Cart</a>
</nav>
