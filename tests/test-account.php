<?php
/**
 * Registration, lost password, login, network sign-up and WooCommerce forms.
 *
 * @package ShieldCraft
 */

use ShieldCraft\Areas\Account;
use ShieldCraft\Integrations\WooCommerce;
use ShieldCraft\Settings;

/**
 * Account forms.
 */
class Test_Account extends ShieldCraft_TestCase {

	/**
	 * Output of an action.
	 *
	 * @param string $hook Action.
	 * @return string
	 */
	private function output( $hook ) {
		ob_start();
		do_action( $hook );
		return ob_get_clean();
	}

	public function test_registration() {
		$this->assertStringContainsString( 'data-shieldcraft="registration"', $this->output( 'register_form' ) );

		$result = register_new_user( 'botuser', 'bot@example.org' );
		$this->assertWPError( $result );
		$this->assertSame( 'shieldcraft_spam', $result->get_error_code() );

		$_POST = $this->human_fields( 'registration' );
		$this->assertIsInt( register_new_user( 'realuser', 'real@example.org' ) );
	}

	public function test_registration_keeps_core_errors_first() {
		$result = register_new_user( '', 'not-an-email' );
		$this->assertWPError( $result );
		$this->assertNotContains( 'shieldcraft_spam', $result->get_error_codes(), 'Nothing is logged for a form with mistakes.' );
		$this->assertSame( array(), $this->log_rows() );
	}

	public function test_suspicious_registration_follows_the_setting() {
		$_POST = $this->human_fields( 'registration', 20, false );
		$this->assertWPError( register_new_user( 'noscript', 'noscript@example.org' ) );
		Settings::save( array( 'forms_suspicious' => 'allow' ) );
		$this->assertIsInt( register_new_user( 'noscript2', 'noscript2@example.org' ) );
	}

	public function test_lost_password() {
		self::factory()->user->create(
			array(
				'user_login' => 'forgetful',
				'user_email' => 'forgetful@example.org',
			)
		);
		$this->assertStringContainsString( 'data-shieldcraft="lost_password"', $this->output( 'lostpassword_form' ) );
		$result = retrieve_password( 'forgetful' );
		$this->assertWPError( $result );
		$this->assertSame( 'shieldcraft_spam', $result->get_error_code() );

		$_POST = $this->human_fields( 'lost_password' );
		$this->assertTrue( retrieve_password( 'forgetful' ) );
	}

	public function test_login_is_off_by_default() {
		$this->assertFalse( has_filter( 'authenticate', array( Account::class, 'check_login' ) ) );
	}

	public function test_login_form() {
		Settings::save( array( 'login' => true ) );
		Account::register();
		$this->assertStringContainsString( 'data-shieldcraft="login"', $this->output( 'login_form' ) );
		$this->assertStringContainsString( 'data-shieldcraft="login"', wp_login_form( array( 'echo' => false ) ) );

		$subscriber                = self::factory()->user->create(
			array(
				'user_login' => 'reader',
				'user_pass'  => 'right-password',
			)
		);
		$boss                      = self::factory()->user->create(
			array(
				'user_login' => 'boss',
				'user_pass'  => 'boss-password',
				'role'       => 'administrator',
			)
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';
		// Core's own login_init callbacks send headers.
		remove_all_actions( 'login_init' );
		do_action( 'login_init' );

		$_POST = array(
			'log' => 'reader',
			'pwd' => 'right-password',
		);
		$this->assertSame( 'shieldcraft_spam', wp_authenticate( 'reader', 'right-password' )->get_error_code(), 'A bot with the right password but none of the fields.' );
		$this->assertSame( 'incorrect_password', wp_authenticate( 'reader', 'wrong' )->get_error_code(), 'Wrong passwords get the usual error.' );
		$this->assertSame( $boss, wp_authenticate( 'boss', 'boss-password' )->ID, 'Administrators are never locked out.' );

		$_POST = array_merge( $_POST, $this->human_fields( 'login', 2, false ) );
		$this->assertSame( $subscriber, wp_authenticate( 'reader', 'right-password' )->ID, 'Without JavaScript a login still works.' );

		unset( $_SERVER['REQUEST_METHOD'] );
		$_POST = array();
		$this->assertSame( $subscriber, wp_authenticate( 'reader', 'right-password' )->ID, 'Logins not sent from a login form are not checked.' );
	}

	public function test_network_signup() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
		$_POST  = array( 'stage' => 'validate-user-signup' );
		$result = wpmu_validate_user_signup( 'signupbot', 'signupbot@example.org' );
		$this->assertContains( 'user_name', $result['errors']->get_error_codes() );
	}

	public function test_woocommerce_forms() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'Needs WooCommerce (WC_DIR).' );
		}
		$this->assertStringContainsString( 'data-shieldcraft="wc_register"', $this->output( 'woocommerce_register_form' ) );
		$this->assertStringContainsString( 'data-shieldcraft="wc_checkout"', $this->output( 'woocommerce_after_order_notes' ) );
		$this->assertSame( '', $this->output( 'woocommerce_login_form' ), 'Login is opt-in.' );

		$errors = apply_filters( 'woocommerce_process_registration_errors', new WP_Error(), 'shopbot', 'pass', 'shopbot@example.org' );
		$this->assertSame( 'shieldcraft_spam', $errors->get_error_code() );

		$errors = new WP_Error();
		$_POST  = $this->human_fields( 'wc_checkout' );
		do_action( 'woocommerce_after_checkout_validation', array( 'billing_email' => 'buyer@example.org' ), $errors );
		$this->assertFalse( $errors->has_errors() );
		$_POST = array();
		do_action( 'woocommerce_after_checkout_validation', array( 'billing_email' => 'buyer@example.org' ), $errors );
		$this->assertSame( 'shieldcraft_spam', $errors->get_error_code() );
	}

	public function test_block_checkout_gets_rules_and_rate_limit() {
		if ( ! class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			$this->markTestSkipped( 'Needs WooCommerce (WC_DIR).' );
		}
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_param( 'billing_address', array( 'email' => 'buyer@example.org' ) );
		$request->set_param( 'customer_note', '' );
		WooCommerce::check_block_checkout( null, $request );
		$this->assertSame( array(), $this->log_rows(), 'No hidden fields needed.' );

		Settings::save( array( 'blocked_emails' => 'buyer@example.org' ) );
		$this->expectException( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' );
		WooCommerce::check_block_checkout( null, $request );
	}
}
