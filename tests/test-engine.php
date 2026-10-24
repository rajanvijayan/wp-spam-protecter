<?php
/**
 * Detection engine: tokens, hidden fields, content rules, repetition, allowlist, log.
 *
 * @package ShieldCraft
 */

use ShieldCraft\Engine\Checks\Content;
use ShieldCraft\Engine\Engine;
use ShieldCraft\Engine\Fields;
use ShieldCraft\Engine\Request;
use ShieldCraft\Engine\Submission;
use ShieldCraft\Engine\Token;
use ShieldCraft\Engine\Verdict;
use ShieldCraft\Settings;

/**
 * Engine.
 */
class Test_Engine extends ShieldCraft_TestCase {

	public function set_up() {
		parent::set_up();
		// Most tests send the same message from the same address many times.
		Settings::save(
			array(
				'rate_limit' => false,
				'duplicate'  => false,
			)
		);
	}

	/**
	 * Check a submission with the given hidden fields.
	 *
	 * @param array|null $fields Hidden fields (null: none sent).
	 * @param array      $data   Submission data.
	 * @return Verdict
	 */
	private function check( $fields, array $data = array() ) {
		$data['fields'] = null === $fields ? null : Fields::from_request( $fields );
		return Engine::check(
			new Submission(
				'comment',
				array_merge(
					array(
						'name'    => 'Ann',
						'email'   => 'ann@example.org',
						'message' => 'A thoughtful reply about the article.',
					),
					$data
				)
			)
		);
	}

	public function test_tokens_are_signed_for_one_context() {
		$token = Token::create( 'comment', time() - 30 );
		$this->assertSame( 30, Token::age( $token, 'comment' ) );
		$this->assertFalse( Token::age( $token, 'registration' ), 'Not valid for another kind of form.' );
		$this->assertFalse( Token::age( preg_replace( '/^\d+/', (string) ( time() - 3600 ), $token ), 'comment' ), 'Cannot be back-dated.' );
		$this->assertFalse( Token::age( Token::create( 'comment', time() + 600 ), 'comment' ), 'Not from the future.' );
		$this->assertFalse( Token::age( 'garbage', 'comment' ) );
		$this->assertTrue( Token::proof_valid( $token, Token::proof( $token ) ) );
		$this->assertFalse( Token::proof_valid( $token, Token::proof( Token::create( 'comment' ) ) ) );
	}

	public function test_a_person_passes() {
		$verdict = $this->check( $this->human_fields( 'comment' ) );
		$this->assertTrue( $verdict->is_allowed(), $verdict->codes() );
		$this->assertSame( array(), $this->log_rows(), 'Allowed submissions are not logged.' );
	}

	public function test_posting_without_the_fields_is_spam() {
		$verdict = $this->check( null );
		$this->assertTrue( $verdict->is_spam() );
		$this->assertSame( 'no_fields', $verdict->codes() );
		$rows = $this->log_rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'spam', $rows[0]['verdict'] );
		$this->assertSame( 'comment', $rows[0]['context'] );
		$this->assertSame( Request::hash( '203.0.113.10' ), $rows[0]['ip_hash'] );
		$this->assertSame( '', $rows[0]['ip'], 'IP addresses are not stored by default.' );
		$this->assertSame( 'A thoughtful reply about the article.', $rows[0]['excerpt'] );
		$this->assertSame( 'none', $rows[0]['agent'] );
	}

	public function test_old_cached_1_0_form_is_held_not_lost() {
		$verdict = $this->check( array( 'wp-spam-protecter-year' => gmdate( 'Y' ) ) );
		$this->assertSame( Verdict::SUSPICIOUS, $verdict->verdict );
		$this->assertSame( 'legacy_form', $verdict->codes() );
	}

	public function test_honeypot_speed_and_forged_tokens() {
		$fields                     = $this->human_fields( 'comment' );
		$fields[ Fields::HONEYPOT ] = 'https://spam.example';
		$this->assertSame( 'honeypot', $this->check( $fields )->codes() );

		$this->assertSame( 'too_fast', $this->check( $this->human_fields( 'comment', 1 ) )->codes() );
		Settings::save( array( 'min_seconds' => 0 ) );
		$this->assertTrue( $this->check( $this->human_fields( 'comment', 1 ) )->is_allowed() );

		$fields                  = $this->human_fields( 'comment' );
		$fields[ Fields::TOKEN ] = Token::create( 'registration', time() - 20 );
		$this->assertSame( 'token_invalid', $this->check( $fields )->codes() );
	}

	public function test_without_javascript() {
		$verdict = $this->check( $this->human_fields( 'comment', 20, false ) );
		$this->assertSame( Verdict::SUSPICIOUS, $verdict->verdict );
		$this->assertSame( 'no_javascript', $verdict->codes() );

		Settings::save( array( 'javascript' => 'spam' ) );
		$this->assertTrue( $this->check( $this->human_fields( 'comment', 20, false ) )->is_spam() );
		Settings::save( array( 'javascript' => 'off' ) );
		$this->assertTrue( $this->check( $this->human_fields( 'comment', 20, false ) )->is_allowed() );

		// A page left open (or cached) for longer than the token lifetime, without the script.
		$this->assertSame( 'token_expired', $this->check( $this->human_fields( 'comment', 13 * HOUR_IN_SECONDS, false ) )->codes() );
		$this->assertTrue( $this->check( $this->human_fields( 'comment', 13 * HOUR_IN_SECONDS ) )->is_allowed(), 'The script refreshed it.' );
	}

	public function test_links() {
		$this->assertSame( 3, Content::count_links( 'See https://a.example, www.b.example and [url=c.example]c[/url].' ) );
		$this->assertSame( 1, Content::count_links( '[url]https://a.example[/url]' ) );
		$this->assertSame( 1, Content::count_links( '[url=https://a.example]a[/url]' ) );
		$verdict = $this->check( $this->human_fields( 'comment' ), array( 'message' => 'https://a.example https://b.example https://c.example' ) );
		$this->assertSame( 'links', $verdict->codes() );
		Settings::save( array( 'max_links' => 5 ) );
		$this->assertTrue( $this->check( $this->human_fields( 'comment' ), array( 'message' => 'https://a.example https://b.example https://c.example' ) )->is_allowed() );
	}

	public function test_blocked_words_emails_and_ips() {
		Settings::save( array( 'blocked_words' => "casino\n# a comment\n" ) );
		$this->assertSame( 'blocked_word', $this->check( $this->human_fields( 'comment' ), array( 'message' => 'Best CASINO bonus' ) )->codes() );

		update_option( 'disallowed_keys', 'viagra' );
		$this->assertSame( 'blocked_word', $this->check( $this->human_fields( 'comment' ), array( 'name' => 'Viagra Shop' ) )->codes() );
		Settings::save( array( 'disallowed_keys' => false ) );
		$this->assertTrue( $this->check( $this->human_fields( 'comment' ), array( 'name' => 'Viagra Shop' ) )->is_allowed() );

		Settings::save( array( 'blocked_emails' => "@spam.example\n*bot*@*" ) );
		$this->assertSame( 'blocked_email', $this->check( $this->human_fields( 'comment' ), array( 'email' => 'x@SPAM.example' ) )->codes() );
		$this->assertSame( 'blocked_email', $this->check( $this->human_fields( 'comment' ), array( 'email' => 'mybot1@mail.example' ) )->codes() );

		Settings::save( array( 'blocked_ips' => '203.0.113.0/24' ) );
		$this->assertSame( 'blocked_ip', $this->check( $this->human_fields( 'comment' ) )->codes() );
	}

	public function test_ip_matching() {
		$this->assertTrue( Request::ip_matches( '203.0.113.10', '203.0.113.10' ) );
		$this->assertTrue( Request::ip_matches( '203.0.113.10', '203.0.113.0/24' ) );
		$this->assertTrue( Request::ip_matches( '203.0.113.10', '203.0.112.0/23' ) );
		$this->assertFalse( Request::ip_matches( '203.0.114.10', '203.0.112.0/23' ) );
		$this->assertTrue( Request::ip_matches( '203.0.113.10', '203.0.113.*' ) );
		$this->assertTrue( Request::ip_matches( '2001:db8::1', '2001:db8::/32' ) );
		$this->assertFalse( Request::ip_matches( '2001:db9::1', '2001:db8::/32' ) );
		$this->assertFalse( Request::ip_matches( '203.0.113.10', '2001:db8::/32' ) );
		$this->assertFalse( Request::ip_matches( '203.0.113.10', 'not an ip/8' ) );
		$this->assertFalse( Request::ip_matches( '', '0.0.0.0/0' ) );
	}

	public function test_proxy_header_is_used_only_when_chosen() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 10.0.0.1';
		$this->assertSame( '203.0.113.10', Request::ip() );
		Settings::save( array( 'ip_header' => 'HTTP_X_FORWARDED_FOR' ) );
		$this->assertSame( '198.51.100.7', Request::ip() );
		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
		$this->assertSame( '', Request::ip() );
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	public function test_writing_systems() {
		Settings::save( array( 'blocked_scripts' => array( 'Cyrillic', 'Klingon' ) ) );
		$this->assertSame( array( 'Cyrillic' ), Settings::get( 'blocked_scripts' ) );
		$verdict = $this->check( $this->human_fields( 'comment' ), array( 'message' => 'Привет, лучшие предложения здесь' ) );
		$this->assertSame( 'script', $verdict->codes() );
		$this->assertTrue( $this->check( $this->human_fields( 'comment' ), array( 'message' => 'Thanks! I said привет to the author.' ) )->is_allowed(), 'A few words are fine.' );
	}

	public function test_rate_limit_and_duplicates() {
		Settings::save(
			array(
				'rate_limit'      => true,
				'duplicate'       => true,
				'rate_per_minute' => 2,
			)
		);
		$this->assertTrue( $this->check( $this->human_fields( 'comment' ), array( 'message' => 'First message here' ) )->is_allowed() );
		$this->assertTrue( $this->check( $this->human_fields( 'comment' ), array( 'message' => 'Second message here' ) )->is_allowed() );
		$this->assertSame( 'rate_limit', $this->check( $this->human_fields( 'comment' ), array( 'message' => 'Third message here' ) )->codes() );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.20';
		$this->assertTrue( $this->check( $this->human_fields( 'comment' ), array( 'message' => 'Same words again' ) )->is_allowed() );
		$this->assertSame( 'duplicate', $this->check( $this->human_fields( 'comment' ), array( 'message' => "  same WORDS\nagain " ) )->codes() );
	}

	public function test_allowlist_skips_every_check() {
		$this->assertTrue( $this->check( null, array( 'user_id' => self::factory()->user->create( array( 'role' => 'editor' ) ) ) )->is_allowed() );
		$this->assertTrue( $this->check( null, array( 'user_id' => self::factory()->user->create( array( 'role' => 'administrator' ) ) ) )->is_allowed() );
		$this->assertTrue( $this->check( null, array( 'user_id' => self::factory()->user->create( array( 'role' => 'subscriber' ) ) ) )->is_spam() );

		Settings::save( array( 'allowed_ips' => '203.0.113.0/24' ) );
		$this->assertTrue( $this->check( null )->is_allowed() );
		Settings::save(
			array(
				'allowed_ips'    => '',
				'allowed_emails' => 'example.org',
			)
		);
		$this->assertTrue( $this->check( null )->is_allowed() );
		$this->assertCount( 1, $this->log_rows(), 'Only the subscriber was logged.' );
	}

	public function test_extensions_can_add_reasons() {
		$filter = static function ( Verdict $verdict, Submission $submission ) {
			if ( false !== strpos( $submission->message, 'crypto' ) ) {
				$verdict->add( 'custom', Verdict::SPAM, 'crypto' );
			}
			return $verdict;
		};
		add_filter( 'shieldcraft_verdict', $filter, 10, 2 );
		$blocked = 0;
		add_action(
			'shieldcraft_blocked',
			static function () use ( &$blocked ) {
				++$blocked;
			}
		);
		$this->assertSame( 'custom', $this->check( $this->human_fields( 'comment' ), array( 'message' => 'Buy crypto now please' ) )->codes() );
		$this->assertSame( 1, $blocked );
		remove_filter( 'shieldcraft_verdict', $filter );
	}

	public function test_settings_are_sanitized() {
		$saved = Settings::save(
			array(
				'min_seconds'   => 500,
				'javascript'    => 'maybe',
				'blocked_words' => array( 'x' ),
				'allowed_roles' => array( 'Editor', '<b>', '' ),
				'honeypot'      => 'false',
			)
		);
		$this->assertSame( 60, $saved['min_seconds'] );
		$this->assertSame( 'suspicious', $saved['javascript'] );
		$this->assertSame( '', $saved['blocked_words'] );
		$this->assertSame( array( 'editor', 'b' ), $saved['allowed_roles'] );
		$this->assertFalse( $saved['honeypot'] );
		$this->assertSame( array_keys( Settings::defaults() ), array_keys( $saved ) );
	}
}
