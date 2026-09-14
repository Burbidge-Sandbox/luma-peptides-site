<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#content"><?php esc_html_e( 'Skip to content', 'luma' ); ?></a>
<header class="site-header">
	<div class="wrap">
		<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( luma_legal_name() ); ?>">
			<span>luma</span><span>peptides</span><span>co.</span>
		</a>
		<nav class="site-nav" aria-label="<?php esc_attr_e( 'Primary', 'luma' ); ?>">
			<?php wp_nav_menu( [ 'theme_location' => 'primary', 'container' => false, 'depth' => 1 ] ); ?>
		</nav>
		<div class="header-tools">
			<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
				<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="cart-link">
					<?php esc_html_e( 'Cart', 'luma' ); ?>
					<?php if ( WC()->cart && WC()->cart->get_cart_contents_count() ) : ?>
						<span class="mono">(<?php echo (int) WC()->cart->get_cart_contents_count(); ?>)</span>
					<?php endif; ?>
				</a>
				<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php esc_html_e( 'Account', 'luma' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</header>
