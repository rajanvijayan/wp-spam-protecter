<?php
/**
 * WooCommerce: My Account registration and login, checkout. Product reviews are comments.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Integrations;

use ShieldCraft\Areas\Guard;
use ShieldCraft\Engine\Fields;
use ShieldCraft\Engine\Submission;
use ShieldCraft\Settings;
use WP_Error;

/**
 * The classic checkout gets the fields after the order notes (outside the part WooCommerce
 * reloads). The block checkout sends no extra fields, so its orders get the content rules and the
 * rate limit, which also slows down card-testing bots.
 */
final class WooCommerce {

	/**
	 * Hooks.
	 */
	public static function register() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		$settings = Settings::get();
		if ( $settings['wc_register'] ) {
			add_action( 'woocommerce_register_form', array( self::class, 'register_fields' ) );
			add_filter( 'woocommerce_process_registration_errors', array( self::class, 'check_registration' ), 20, 4 );
		}
		if ( $settings['wc_login'] ) {
			add_action( 'woocommerce_login_form', array( self::class, 'login_fields' ) );
			add_filter( 'woocommerce_process_login_errors', array( self::class, 'check_login' ), 20, 2 );
		}
		if ( $settings['wc_checkout'] ) {
			add_action( 'woocommerce_after_order_notes', array( self::class, 'checkout_fields' ) );
			add_action( 'woocommerce_after_checkout_validation', array( self::class, 'check_checkout' ), 20, 2 );
			add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( self::class, 'check_block_checkout' ), 20, 2 );
		}
	}

	/**
	 * My Account registration fields.
	 */
	public static function register_fields() {
		echo Fields::render( 'wc_register' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Fields::render().
	}

	/**
	 * Check a My Account registration.
	 *
	 * @param WP_Error $errors   Errors.
	 * @param string   $username Username.
	 * @param string   $password Password.
	 * @param string   $email    Email.
	 * @return WP_Error
	 */
	public static function check_registration( $errors, $username, $password, $email ) {
		unset( $password );
		if ( ! $errors instanceof WP_Error || $errors->has_errors() ) {
			return $errors;
		}
		$message = Guard::check(
			new Submission(
				'wc_register',
				array(
					'name'  => (string) $username,
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
	 * My Account login fields.
	 */
	public static function login_fields() {
		echo Fields::render( 'wc_login' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Fields::render().
	}

	/**
	 * Check a My Account login (suspicious ones are never refused).
	 *
	 * @param WP_Error $errors   Errors.
	 * @param string   $username Username or email.
	 * @return WP_Error
	 */
	public static function check_login( $errors, $username ) {
		if ( ! $errors instanceof WP_Error || $errors->has_errors() ) {
			return $errors;
		}
		$user    = get_user_by( is_email( $username ) ? 'email' : 'login', (string) $username );
		$message = Guard::check(
			new Submission(
				'wc_login',
				array(
					'name'    => (string) $username,
					'email'   => $user ? $user->user_email : '',
					'user_id' => $user ? $user->ID : 0,
				)
			),
			false
		);
		if ( '' !== $message ) {
			$errors->add( 'shieldcraft_spam', $message );
		}
		return $errors;
	}

	/**
	 * Classic checkout fields.
	 */
	public static function checkout_fields() {
		echo Fields::render( 'wc_checkout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Fields::render().
	}

	/**
	 * Check a classic checkout.
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Errors.
	 */
	public static function check_checkout( $data, $errors ) {
		if ( ! $errors instanceof WP_Error || $errors->has_errors() ) {
			return;
		}
		$message = Guard::check( new Submission( 'wc_checkout', self::order_details( (array) $data ) ) );
		if ( '' !== $message ) {
			$errors->add( 'shieldcraft_spam', $message );
		}
	}

	/**
	 * Check a block checkout order (content rules and rate limit only).
	 *
	 * @param \WC_Order        $order   Order.
	 * @param \WP_REST_Request $request Request.
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When it looks like spam.
	 */
	public static function check_block_checkout( $order, $request ) {
		unset( $order );
		$billing                 = (array) $request['billing_address'];
		$data                    = array(
			'billing_first_name' => isset( $billing['first_name'] ) ? $billing['first_name'] : '',
			'billing_last_name'  => isset( $billing['last_name'] ) ? $billing['last_name'] : '',
			'billing_email'      => isset( $billing['email'] ) ? $billing['email'] : '',
			'order_comments'     => (string) $request['customer_note'],
		);
		$details                 = self::order_details( $data );
		$details['content_only'] = true;
		$message                 = Guard::check( new Submission( 'wc_checkout', $details ) );
		if ( '' !== $message && class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'shieldcraft_spam', esc_html( $message ), 400 );
		}
	}

	/**
	 * Sender and message of a checkout.
	 *
	 * @param array $data Checkout data.
	 * @return array Submission data.
	 */
	private static function order_details( array $data ) {
		$name = trim( ( isset( $data['billing_first_name'] ) ? $data['billing_first_name'] : '' ) . ' ' . ( isset( $data['billing_last_name'] ) ? $data['billing_last_name'] : '' ) );
		return array(
			'name'    => (string) $name,
			'email'   => isset( $data['billing_email'] ) ? (string) $data['billing_email'] : '',
			'message' => isset( $data['order_comments'] ) ? (string) $data['order_comments'] : '',
		);
	}
}
