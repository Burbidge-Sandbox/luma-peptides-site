<?php
/**
 * Login / register — legacy .acct-login card.
 */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_customer_login_form' );
$register = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
?>
<div class="acct-card acct-login">
	<form class="woocommerce-form woocommerce-form-login login form-grid" method="post">
		<?php do_action( 'woocommerce_login_form_start' ); ?>
		<div class="field full"><label for="username">Email</label><input type="text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required></div><?php // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="field full"><label for="password">Password</label><input type="password" name="password" id="password" autocomplete="current-password" required></div>
		<?php do_action( 'woocommerce_login_form' ); ?>
		<div class="full" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
			<label class="acct-fine" style="margin:0;display:flex;gap:.4rem;align-items:center"><input type="checkbox" name="rememberme" value="forever"> Remember me</label>
			<a class="acct-fine" style="margin:0" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Lost your password?</a>
		</div>
		<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
		<div class="full"><button type="submit" class="btn btn-primary" name="login" value="Log in" style="width:100%">Sign in</button></div>
		<?php do_action( 'woocommerce_login_form_end' ); ?>
	</form>
</div>

<?php if ( $register ) : ?>
<div class="acct-sec" style="max-width:560px;margin-inline:auto">
	<div class="acct-head"><h2>New here?</h2></div>
	<form method="post" class="woocommerce-form woocommerce-form-register register acct-card form-grid" <?php do_action( 'woocommerce_register_form_tag' ); ?>>
		<?php do_action( 'woocommerce_register_form_start' ); ?>
		<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
			<div class="field full"><label for="reg_username">Username</label><input type="text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required></div><?php // phpcs:ignore WordPress.Security.NonceVerification ?>
		<?php endif; ?>
		<div class="field full"><label for="reg_email">Email</label><input type="email" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required></div><?php // phpcs:ignore WordPress.Security.NonceVerification ?>
		<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
			<div class="field full"><label for="reg_password">Password</label><input type="password" name="password" id="reg_password" autocomplete="new-password" required></div>
		<?php else : ?>
			<p class="acct-fine full" style="margin:0">A link to set a new password will be sent to your email address.</p>
		<?php endif; ?>
		<?php do_action( 'woocommerce_register_form' ); ?>
		<div class="full"><button type="submit" class="btn btn-dark" name="register" value="Register" style="width:100%">Create account</button></div>
		<?php do_action( 'woocommerce_register_form_end' ); ?>
	</form>
</div>
<?php endif; ?>
<p class="acct-fine" style="text-align:center;margin-top:2rem">For laboratory research use only. Accounts are for order history and certificates of analysis.</p>
<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
