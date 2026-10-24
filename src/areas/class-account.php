<?php
/**
 * Registration, lost password, network sign-up and login.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Areas;

use ShieldCraft\Engine\Fields;
use ShieldCraft\Engine\Submission;
use ShieldCraft\Settings;
use WP_Error;
use WP_User;

/**
 * The account forms of wp-login.php and wp-signup.php. Login protection is opt-in: it adds the
 * fields to wp-login.php and wp_login_form(), and only checks those forms' submissions; XML-RPC,
 * the REST API and application passwords are never affected.
 */
final class Account {

	/**
	 * Hooks.
	 */
	public static function register() {
		$settings = Settings::get();
		if ( $settings['registration'] ) {
			add_action( 'register_form', array( self::class, 'registration_fields' ) );
			add_filter( 'registration_errors', array( self::class, 'check_registration' ), 20, 3 );
		}
		if ( $settings['lost_password'] ) {
			add_action( 'lostpassword_form', array( self::class, 'lost_password_fields' ) );
			add_action( 'lostpassword_post', array( self::class, 'check_lost_password' ), 20, 2 );
		}
		if ( $settings['signup'] && is_multisite() ) {
			add_action( 'signup_extra_fields', array( self::class, 'signup_fields' ) );
			add_filter( 'wpmu_validate_user_signup', array( self::class, 'check_signup' ), 20 );
		}
		if ( $settings['login'] ) {
			add_action( 'login_form', array( self::class, 'login_fields' ) );
			add_filter( 'login_form_middle', array( self::class, 'login_form_middle' ) );
			add_filter( 'authenticate', array( self::class, 'check_login' ), 30, 2 );
		}
	}

	/**
	 * Registration form fields.
	 */
	public static function registration_fields() {
		echo Fields::render( 'registration' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Fields::render().
	}

	/**
	 * Check a registration.
	 *
	 * @param WP_Error $errors Errors.
	 * @param string   $login  Username.
	 * @param string   $email  Email.
	 * @return WP_Error
	 */
	public static function check_registration( $errors, $login, $email ) {
		if ( ! $errors instanceof WP_Error || $errors->has_errors() ) {
			return $errors;
		}
		$message = Guard::check(
			new Submission(
				'registration',
				array(
					'name'  => (string) $login,
					'email' => (string) $email,
				)
			)
		);
		if ( '' !== $message ) {
			$errors->add( 'shieldcraft_spam', $message );
		}
		return $errors;
	}

	/**
	 * Lost password form fields.
	 */
	public static function lost_password_fields() {
		echo Fields::render( 'lost_password' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Fields::render().
	}

	/**
	 * Check a password reset request (stops reset email floods).
	 *
	 * @param WP_Error           $errors    Errors.
	 * @param WP_User|false|null $user_data User the reset is for.
	 */
	public static function check_lost_password( $errors, $user_data = null ) {
		if ( ! $errors instanceof WP_Error || $errors->has_errors() ) {
			return;
		}
		$message = Guard::check(
			new Submission(
				'lost_password',
				array(
					'email'   => $user_data instanceof WP_User ? $user_data->user_email : '',
					'user_id' => 0,
				)
			)
		);
		if ( '' !== $message ) {
			$errors->add( 'shieldcraft_spam', $message );
		}
	}

	/**
	 * Network sign-up form fields.
	 *
	 * @param WP_Error|null $errors Errors of the previous attempt.
	 */
	public static function signup_fields( $errors = null ) {
		unset( $errors );
		echo Fields::render( 'signup' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Fields::render().
	}

	/**
	 * Check a network sign-up.
	 *
	 * @param array $result { user_name, user_email, errors }.
	 * @return array
	 */
	public static function check_signup( $result ) {
		// The same validation runs again when a signed-up user adds a site; only check the form.
		if ( ! isset( $_POST['stage'] ) || 'validate-user-signup' !== $_POST['stage'] || ! isset( $result['errors'] ) || ! $result['errors'] instanceof WP_Error ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- wp-signup.php checks its own nonce.
			return $result;
		}
		if ( $result['errors']->has_errors() ) {
			return $result;
		}
		$message = Guard::check(
			new Submission(
				'signup',
				array(
					'name'  => isset( $result['user_name'] ) ? (string) $result['user_name'] : '',
					'email' => isset( $result['user_email'] ) ? (string) $result['user_email'] : '',
				)
			)
		);
		if ( '' !== $message ) {
			$result['errors']->add( 'user_name', $message );
		}
		return $result;
	}

	/**
	 * Login form fields (wp-login.php).
	 */
	public static function login_fields() {
		echo Fields::render( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Fields::render().
	}

	/**
	 * Login form fields (wp_login_form()).
	 *
	 * @param string $html Markup between the fields and the button.
	 * @return string
	 */
	public static function login_form_middle( $html ) {
		return $html . Fields::render( 'login' );
	}

	/**
	 * Check a login sent from a login form. Runs after the password check, so a wrong password
	 * gets WordPress's usual error and a right one is checked for bot traits.
	 *
	 * @param WP_User|WP_Error|null $user     User so far.
	 * @param string                $username Username.
	 * @return WP_User|WP_Error|null
	 */
	public static function check_login( $user, $username ) {
		if ( ! $user instanceof WP_User || ! self::is_login_form() ) {
			return $user;
		}
		// The password is right, so allowlists by role apply; the visitor is not logged in yet.
		$message = Guard::check(
			new Submission(
				'login',
				array(
					'name'    => (string) $username,
					'email'   => $user->user_email,
					'user_id' => $user->ID,
				)
			),
			false
		);
		return '' === $message ? $user : new WP_Error( 'shieldcraft_spam', $message );
	}

	/**
	 * Whether the request is a login form sent to wp-login.php.
	 *
	 * @return bool
	 */
	private static function is_login_form() {
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || did_action( 'application_password_did_authenticate' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the login form has no nonce.
		return 'post' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) && isset( $_POST['log'], $_POST['pwd'] ) && did_action( 'login_init' );
	}
}
