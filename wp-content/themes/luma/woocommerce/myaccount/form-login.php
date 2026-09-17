<?php
/**
 * Login / register — two cards side by side (sign in · create account).
 */
defined( 'ABSPATH' ) || exit;

echo '<div class="acct-notices">';
do_action( 'woocommerce_before_customer_login_form' );
echo '</div>';
$register = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
?>
<div class="acct-auth<?php echo $register ? ' has-register' : ''; ?>">
	<section class="acct-card acct-login">
		<h2>Sign in</h2>
		<p class="acct-lede">Order history, tracking and certificates for every lot you have received.</p>
		<form class="woocommerce-form woocommerce-form-login login form-grid" method="post">
			<?php do_action( 'woocommerce_login_form_start' ); ?>
			<div class="field full"><label for="username">Email</label><input type="text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required></div><?php // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="field full"><label for="password">Password</label><input type="password" name="password" id="password" autocomplete="current-password" required></div>
			<?php do_action( 'woocommerce_login_form' ); ?>
			<div class="full acct-row">
				<label class="acct-fine"><input type="checkbox" name="rememberme" value="forever"> Remember me</label>
				<a class="acct-fine" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Lost your password?</a>
			</div>
			<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
			<div class="full"><button type="submit" class="btn btn-primary" name="login" value="Log in">Sign in</button></div>
			<?php do_action( 'woocommerce_login_form_end' ); ?>
		</form>
	</section>

	<?php if ( $register ) : ?>
	<section class="acct-card acct-register">
		<h2>Create an account</h2>
		<p class="acct-lede">Faster checkout next time, and your lots and certificates in one place.</p>
		<form method="post" class="woocommerce-form woocommerce-form-register register form-grid" <?php do_action( 'woocommerce_register_form_tag' ); ?>>
			<?php do_action( 'woocommerce_register_form_start' ); ?>
			<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
				<div class="field full"><label for="reg_username">Username</label><input type="text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required></div><?php // phpcs:ignore WordPress.Security.NonceVerification ?>
			<?php endif; ?>
			<div class="field full"><label for="reg_email">Email</label><input type="email" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required></div><?php // phpcs:ignore WordPress.Security.NonceVerification ?>
			<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
				<div class="field full"><label for="reg_password">Password</label><input type="password" name="password" id="reg_password" autocomplete="new-password" required></div>
			<?php else : ?>
				<p class="acct-fine full">A link to set your password will be sent to your email address.</p>
			<?php endif; ?>
			<?php do_action( 'woocommerce_register_form' ); ?>
			<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
			<div class="full"><button type="submit" class="btn btn-dark" name="register" value="Register">Create account</button></div>
			<?php do_action( 'woocommerce_register_form_end' ); ?>
		</form>
	</section>
	<?php endif; ?>
</div>
<p class="acct-fine acct-legal">For laboratory research use only. Accounts are for order history and certificates of analysis.</p>
<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
