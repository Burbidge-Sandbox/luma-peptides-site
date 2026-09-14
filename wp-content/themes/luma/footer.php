<footer class="site-footer">
	<div class="wrap">
		<div class="footer-grid">
			<div>
				<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span>luma</span><span>peptides</span><span>co.</span></a>
				<p><?php esc_html_e( 'Peptide reference material, third-party tested by lot. For laboratory research use only.', 'luma' ); ?></p>
				<p class="mono"><a href="mailto:info@lumaresearchco.com">info@lumaresearchco.com</a><br>30 N Gould St, Sheridan, WY 82801</p>
			</div>
			<div>
				<h4><?php esc_html_e( 'Catalogue', 'luma' ); ?></h4>
				<?php wp_nav_menu( [ 'theme_location' => 'footer', 'container' => false, 'depth' => 1, 'fallback_cb' => 'luma_default_menu' ] ); ?>
			</div>
			<div>
				<h4><?php esc_html_e( 'Documentation', 'luma' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/testing/' ) ); ?>"><?php esc_html_e( 'Certificates of analysis', 'luma' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/shipping-returns/' ) ); ?>"><?php esc_html_e( 'Shipping & returns', 'luma' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>"><?php esc_html_e( 'Terms', 'luma' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'luma' ); ?></a></li>
				</ul>
			</div>
		</div>
		<div class="disclaimer">
			<?php luma_ruo_notice(); ?>
			<p><?php echo esc_html( luma_legal_name() ); ?> <?php esc_html_e( 'is a chemical supplier, not a pharmacy or clinic. Purchasers must be 21 or older and ordering on behalf of a laboratory or research organization.', 'luma' ); ?></p>
		</div>
		<div class="footer-bottom">
			<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( luma_legal_name() ); ?></span>
			<span>lumaresearchco.com is operated by <?php echo esc_html( luma_legal_name() ); ?></span>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
