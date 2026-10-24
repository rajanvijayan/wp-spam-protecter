<?php
/**
 * Admin routes, log actions, statistics, dashboard widget and comment notes.
 *
 * @package ShieldCraft
 */

use ShieldCraft\Admin\Admin;
use ShieldCraft\Admin\Reasons;
use ShieldCraft\Areas\Comments;
use ShieldCraft\Engine\Engine;
use ShieldCraft\Engine\Submission;
use ShieldCraft\Log\Log;
use ShieldCraft\Log\Senders;
use ShieldCraft\Settings;

/**
 * Admin.
 */
class Test_Admin extends ShieldCraft_TestCase {

	public function set_up() {
		parent::set_up();
		do_action( 'rest_api_init' );
		delete_option( Senders::ALLOWED );
		delete_option( Senders::BLOCKED );
		Comments::reset();
		Settings::save( array( 'duplicate' => false ) );
		remove_all_filters( 'comment_flood_filter' );
	}

	/**
	 * Dispatch a REST request.
	 *
	 * @param string $method Method.
	 * @param string $path   Path under shieldcraft/v1.
	 * @param array  $body   JSON body or query.
	 * @return WP_REST_Response
	 */
	private function rest( $method, $path, array $body = array() ) {
		$request = new WP_REST_Request( $method, '/shieldcraft/v1' . $path );
		if ( 'GET' === $method ) {
			$request->set_query_params( $body );
		} elseif ( $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Log a bot submission.
	 *
	 * @param array $data Submission data.
	 * @return int Log ID.
	 */
	private function bot( array $data = array() ) {
		return Engine::check(
			new Submission(
				isset( $data['context'] ) ? $data['context'] : 'api',
				array_merge(
					array(
						'name'    => 'Bot',
						'email'   => 'bot@example.org',
						'message' => 'Cheap pills',
						'fields'  => null,
						// A visitor, even while a test is logged in as an administrator.
						'user_id' => 0,
					),
					$data
				)
			)
		)->log_id;
	}

	private function login_admin() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function test_routes_need_an_administrator() {
		foreach ( array( array( 'GET', '/settings' ), array( 'GET', '/log' ), array( 'GET', '/stats' ), array( 'DELETE', '/senders/allowed' ) ) as $route ) {
			$this->assertSame( 401, $this->rest( $route[0], $route[1] )->get_status(), $route[1] );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, $this->rest( 'GET', '/settings' )->get_status() );
		$this->assertSame( 403, $this->rest( 'POST', '/log/1/action', array( 'action' => 'delete' ) )->get_status() );
	}

	public function test_settings_route() {
		$this->login_admin();
		$data = $this->rest( 'GET', '/settings' )->get_data();
		$this->assertSame( Settings::get(), $data['settings'] );
		$this->assertSame(
			array(
				'allowed' => 0,
				'blocked' => 0,
			),
			$data['senders']
		);
		$saved = $this->rest(
			'POST',
			'/settings',
			array(
				'login'       => true,
				'min_seconds' => -5,
			)
		)->get_data();
		$this->assertTrue( $saved['settings']['login'] );
		$this->assertSame( 0, $saved['settings']['min_seconds'] );
	}

	public function test_log_route_filters_and_hides_hashes() {
		$this->login_admin();
		$this->bot( array( 'context' => 'comment' ) );
		$this->bot( array( 'message' => 'Unique words here' ) );
		$data = $this->rest( 'GET', '/log' )->get_data();
		$this->assertSame( 2, $data['total'] );
		$this->assertArrayNotHasKey( 'ip_hash', $data['rows'][0] );
		$this->assertArrayNotHasKey( 'email_hash', $data['rows'][0] );
		$this->assertSame( 'no_fields', $data['rows'][0]['reasons'][0]['check'] );
		$this->assertSame( 1, $this->rest( 'GET', '/log', array( 'context' => 'comment' ) )->get_data()['total'] );
		$this->assertSame( 1, $this->rest( 'GET', '/log', array( 'search' => 'Unique' ) )->get_data()['total'] );
		$this->assertSame( 0, $this->rest( 'GET', '/log', array( 'verdict' => 'suspicious' ) )->get_data()['total'] );
	}

	public function test_not_spam_approves_the_comment_and_allows_the_sender() {
		$post    = self::factory()->post->create();
		$_POST   = array(
			'comment_post_ID' => $post,
			'author'          => 'Ann',
			'email'           => 'ann@example.org',
			'comment'         => 'A real comment from someone without JavaScript.',
		);
		$_POST   = array_merge( $_POST, $this->human_fields( 'comment', 20, false ) );
		$comment = wp_handle_comment_submission( wp_unslash( $_POST ) );
		$this->assertSame( '0', $comment->comment_approved );
		$id = (int) $this->log_rows()[0]['id'];

		$this->login_admin();
		$row = $this->rest( 'POST', "/log/{$id}/action", array( 'action' => 'not_spam' ) )->get_data();
		$this->assertSame( 'released', $row['status'] );
		$this->assertSame( '1', get_comment( $comment->comment_ID )->comment_approved );
		$this->assertCount( 1, Senders::all( Senders::ALLOWED ), 'Only the email address: IP addresses are often shared.' );

		wp_set_current_user( 0 );
		$this->assertTrue( Engine::check( new Submission( 'comment', array( 'email' => 'ann@example.org' ) ) )->is_allowed(), 'The sender passes from now on.' );
		$this->assertTrue( Engine::check( new Submission( 'comment', array( 'email' => 'other@example.org' ) ) )->is_allowed() === false, 'Others on the same IP address are still checked.' );
	}

	public function test_block_sender() {
		$this->login_admin();
		$id = $this->bot();
		$this->assertSame( 'blocked', $this->rest( 'POST', "/log/{$id}/action", array( 'action' => 'block_sender' ) )->get_data()['status'] );
		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.99';
		$verdict                = Engine::check(
			new Submission(
				'api',
				array(
					'email'  => 'BOT@example.org',
					'fields' => $this->human_fields( 'api' ),
				)
			)
		);
		$this->assertStringContainsString( 'blocked_sender', $verdict->codes(), 'Matched by email from another address.' );

		$this->login_admin();
		$this->assertSame( 0, $this->rest( 'DELETE', '/senders/blocked' )->get_data()['blocked'] );
	}

	public function test_delete_and_missing_rows() {
		$this->login_admin();
		$id = $this->bot();
		$this->assertTrue( $this->rest( 'POST', "/log/{$id}/action", array( 'action' => 'delete' ) )->get_data()['deleted'] );
		$this->assertSame( 404, $this->rest( 'POST', "/log/{$id}/action", array( 'action' => 'delete' ) )->get_status() );
		$this->assertSame( 400, $this->rest( 'POST', "/log/{$id}/action", array( 'action' => 'nope' ) )->get_status() );
	}

	public function test_stats() {
		$this->bot( array( 'context' => 'comment' ) );
		$this->bot( array( 'context' => 'comment' ) );
		$this->bot( array( 'fields' => $this->human_fields( 'api', 20, false ) ) );
		$stats = Log::stats( 7 );
		$this->assertSame( 3, $stats['total'] );
		$this->assertSame( 2, $stats['spam'] );
		$this->assertSame( 1, $stats['suspicious'] );
		$this->assertSame( 2, $stats['contexts']->comment );
		$this->assertSame( 2, $stats['reasons']->no_fields );
		$this->assertCount( 7, (array) $stats['days'] );
		$this->assertSame( 3, ( (array) $stats['days'] )[ gmdate( 'Y-m-d' ) ] );
	}

	public function test_dashboard_widget_and_comment_note() {
		$this->bot( array( 'context' => 'comment' ) );
		ob_start();
		Admin::render_widget();
		$widget = ob_get_clean();
		$this->assertStringContainsString( '1 submission stopped or held', $widget );
		$this->assertStringContainsString( 'Comments: <strong>1</strong>', $widget );

		$comment = self::factory()->comment->create_and_get( array( 'comment_approved' => 'spam' ) );
		update_comment_meta(
			$comment->comment_ID,
			Comments::META,
			array(
				'verdict' => 'spam',
				'reasons' => 'honeypot,links',
			)
		);
		$actions = Admin::comment_note( array(), $comment );
		$this->assertStringContainsString( 'Filled in the hidden field, Too many links', $actions['shieldcraft'] );
		$this->assertSame( 'custom_check', Reasons::describe( 'custom_check' ) );
	}

	public function test_settings_link_and_config() {
		$this->assertStringContainsString( 'options-general.php?page=shieldcraft', Admin::action_links( array() )[0] );
		$config = Admin::config();
		$this->assertArrayHasKey( 'comment', $config['contexts'] );
		$this->assertArrayHasKey( 'honeypot', $config['reasons'] );
		$this->assertArrayHasKey( 'REMOTE_ADDR', $config['headers'] );
		$this->assertSame( '203.0.113.10', $config['headers']['REMOTE_ADDR'] );
	}
}
