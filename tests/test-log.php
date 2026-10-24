<?php
/**
 * Token route, log retention, privacy tools, installer.
 *
 * @package ShieldCraft
 */

use ShieldCraft\Engine\Engine;
use ShieldCraft\Engine\Submission;
use ShieldCraft\Engine\Token;
use ShieldCraft\Installer;
use ShieldCraft\Log\Log;
use ShieldCraft\Log\Privacy;
use ShieldCraft\Settings;

/**
 * Log and REST.
 */
class Test_Log extends ShieldCraft_TestCase {

	/**
	 * Log a blocked submission from an email address.
	 *
	 * @param string $email Email.
	 * @return int Row ID.
	 */
	private function blocked( $email ) {
		return Engine::check(
			new Submission(
				'comment',
				array(
					'email'   => $email,
					'name'    => 'Spammer',
					'message' => 'Cheap pills',
					'fields'  => null,
				)
			)
		)->log_id;
	}

	public function test_token_route() {
		do_action( 'rest_api_init' );
		$request = new WP_REST_Request( 'GET', '/shieldcraft/v1/token' );
		$request->set_param( 'context', 'comment,nope,review' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$tokens = (array) $response->get_data()['tokens'];
		$this->assertSame( array( 'comment', 'review' ), array_keys( $tokens ) );
		$this->assertSame( 0, Token::age( $tokens['comment']['token'], 'comment' ) );
		$this->assertTrue( Token::proof_valid( $tokens['comment']['token'], $tokens['comment']['proof'] ) );
		$this->assertArrayHasKey( 'Cache-Control', $response->get_headers() );
	}

	public function test_purge_keeps_recent_rows() {
		global $wpdb;
		$old = $this->blocked( 'a@example.org' );
		$this->blocked( 'b@example.org' );
		$wpdb->update( Log::table(), array( 'created' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) ), array( 'id' => $old ) ); // phpcs:ignore
		$this->assertSame( 1, Log::purge() );
		$this->assertNull( Log::get( $old ) );
		$this->assertCount( 1, $this->log_rows() );
	}

	public function test_ip_is_stored_only_when_enabled() {
		Settings::save( array( 'store_ip' => true ) );
		$this->assertSame( '203.0.113.10', Log::get( $this->blocked( 'a@example.org' ) )['ip'] );
	}

	public function test_privacy_export_and_erase() {
		$this->blocked( 'Ann@Example.org' );
		$this->blocked( 'other@example.org' );
		$export = Privacy::export( 'ann@example.org' );
		$this->assertTrue( $export['done'] );
		$this->assertCount( 1, $export['data'] );
		$this->assertSame( 'Cheap pills', wp_list_pluck( $export['data'][0]['data'], 'value', 'name' )['Message (start)'] );

		$erased = Privacy::erase( 'ann@example.org' );
		$this->assertTrue( $erased['items_removed'] );
		$this->assertSame( array(), Log::for_email( 'ann@example.org' ) );
		$this->assertCount( 1, $this->log_rows() );
		$this->assertArrayHasKey( 'shieldcraft', apply_filters( 'wp_privacy_personal_data_erasers', array() ) );
	}

	public function test_installer() {
		$secret = Installer::secret();
		$this->assertGreaterThanOrEqual( 32, strlen( $secret ) );
		$this->assertSame( $secret, Installer::secret() );
		$this->assertNotFalse( wp_next_scheduled( Installer::CRON ) );
		$this->assertSame( Installer::DB_VERSION, (int) get_option( Installer::DB_VERSION_OPTION ) );
	}
}
