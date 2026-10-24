<?php
/**
 * Base test case.
 *
 * @package ShieldCraft
 */

use ShieldCraft\Engine\Fields;
use ShieldCraft\Engine\Token;
use ShieldCraft\Settings;

/**
 * Default settings for every test, a clean request, and helpers for the hidden fields.
 */
abstract class ShieldCraft_TestCase extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION );
		Settings::flush();
		$_POST                  = array();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
		unset( $_SERVER['HTTP_USER_AGENT'] );
	}

	public function tear_down() {
		$_POST = array();
		Settings::flush();
		parent::tear_down();
	}

	/**
	 * Hidden field values a person's browser would send: a token issued $age seconds ago, the
	 * script's proof and an empty honeypot.
	 *
	 * @param string $context Context.
	 * @param int    $age     Seconds since the form was opened.
	 * @param bool   $js      Whether the script ran.
	 * @return array
	 */
	protected function human_fields( $context, $age = 20, $js = true ) {
		$token = Token::create( $context, time() - $age );
		return array(
			Fields::TOKEN    => $token,
			Fields::PROOF    => $js ? Token::proof( $token ) : '',
			Fields::HONEYPOT => '',
		);
	}

	/**
	 * Log rows.
	 *
	 * @return array[]
	 */
	protected function log_rows() {
		global $wpdb;
		$table = ShieldCraft\Log\Log::table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id", ARRAY_A ); // phpcs:ignore
	}
}
