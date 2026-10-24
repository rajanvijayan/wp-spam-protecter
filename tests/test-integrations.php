<?php
/**
 * Form plugins and the public API.
 *
 * @package ShieldCraft
 */

use ShieldCraft\Engine\Token;
use ShieldCraft\Integrations\Form_Data;
use ShieldCraft\Integrations\Form_Plugins;
use ShieldCraft\Settings;

/**
 * Stand-in for WPCF7_Submission.
 */
class ShieldCraft_Test_CF7_Submission {

	/**
	 * Spam log.
	 *
	 * @var array
	 */
	public $log = array();

	/**
	 * Posted data.
	 *
	 * @return array
	 */
	public function get_posted_data() {
		return array(
			'your-name'    => 'Ann',
			'your-email'   => 'ann@example.org',
			'your-message' => 'Hello, I would like a quote.',
		);
	}

	/**
	 * Add to the spam log.
	 *
	 * @param array $data Entry.
	 */
	public function add_spam_log( $data ) {
		$this->log[] = $data;
	}
}

/**
 * Stand-in for Elementor Pro's form record and Ajax handler.
 */
class ShieldCraft_Test_Elementor {

	/**
	 * Errors added.
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * Record data.
	 *
	 * @param string $key Key.
	 * @return array
	 */
	public function get( $key ) {
		return 'fields' === $key ? array(
			'email'   => array(
				'title' => 'Email',
				'value' => 'ann@example.org',
			),
			'message' => array(
				'title' => 'Message',
				'value' => 'Hello there, nice site.',
			),
		) : array( 'id' => 'abc123' );
	}

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'form';
	}

	/**
	 * Add an error message.
	 *
	 * @param string $message Message.
	 */
	public function add_error_message( $message ) {
		$this->errors[] = $message;
	}
}

/**
 * Integrations.
 */
class Test_Integrations extends ShieldCraft_TestCase {

	public function set_up() {
		parent::set_up();
		Settings::save( array( 'duplicate' => false ) );
	}

	public function test_sender_and_message_are_guessed_from_fields() {
		$data = Form_Data::from_values(
			array(
				'First name'   => 'Ann',
				'last_name'    => 'Lee',
				'Your email'   => 'ann@example.org',
				'website'      => 'https://ann.example',
				'Message'      => 'Hello',
				'Topics'       => array( 'Pricing', 'Support' ),
				'_wpcf7'       => '12',
				'shieldcraft_' => 'x',
			)
		);
		$this->assertSame( 'Ann Lee', $data['name'] );
		$this->assertSame( 'ann@example.org', $data['email'] );
		$this->assertSame( 'https://ann.example', $data['url'] );
		$this->assertSame( "Hello\nPricing, Support", $data['message'] );
	}

	public function test_contact_form_7() {
		$this->assertStringContainsString( 'data-shieldcraft="cf7"', Form_Plugins::cf7_fields( '<p>fields</p>' ) );

		$submission = new ShieldCraft_Test_CF7_Submission();
		$this->assertTrue( Form_Plugins::cf7_check( false, $submission ) );
		$this->assertSame( 'shieldcraft', $submission->log[0]['agent'] );
		$this->assertSame( 'cf7', $this->log_rows()[0]['context'] );

		$_POST = $this->human_fields( 'cf7' );
		$this->assertFalse( Form_Plugins::cf7_check( false, new ShieldCraft_Test_CF7_Submission() ) );
		$this->assertTrue( Form_Plugins::cf7_check( true, new ShieldCraft_Test_CF7_Submission() ), 'Spam found by others stays spam.' );
	}

	public function test_gravity_forms() {
		$this->assertStringContainsString( 'data-shieldcraft="gravityforms"', Form_Plugins::gravity_fields( '<input type="submit">', array() ) );
		$form  = array(
			'id'     => 3,
			'fields' => array(
				array(
					'id'    => 1,
					'label' => 'Name',
				),
				array(
					'id'    => 2,
					'label' => 'Comments',
				),
			),
		);
		$entry = array(
			'1.3' => 'Ann',
			'1.6' => 'Lee',
			'2'   => 'Buy at https://a.example https://b.example https://c.example',
		);
		$_POST = $this->human_fields( 'gravityforms' );
		$this->assertTrue( Form_Plugins::gravity_check( false, $form, $entry ), 'Too many links.' );
		$row = $this->log_rows()[0];
		$this->assertSame( 'links', $row['reasons'] );
		$this->assertSame( 'Ann Lee', $row['name'] );
		$this->assertSame( '3', $row['form'] );

		$entry['2'] = 'A normal question about opening hours.';
		$this->assertFalse( Form_Plugins::gravity_check( false, $form, $entry ) );
	}

	public function test_fluent_forms_reads_its_serialised_data() {
		ob_start();
		Form_Plugins::fluent_fields( (object) array( 'id' => 1 ) );
		$this->assertStringContainsString( 'data-shieldcraft="fluentforms"', ob_get_clean() );

		$form   = (object) array( 'id' => 4 );
		$errors = Form_Plugins::fluent_check( array(), array( 'email' => 'ann@example.org' ), $form );
		$this->assertArrayHasKey( 'shieldcraft', $errors );

		$data = array_merge( array( 'email' => 'ann@example.org' ), $this->human_fields( 'fluentforms' ) );
		$this->assertSame( array(), Form_Plugins::fluent_check( array(), $data, $form ) );
		$this->assertSame( array( 'email' => array( 'Invalid' ) ), Form_Plugins::fluent_check( array( 'email' => array( 'Invalid' ) ), array(), $form ), 'Forms with mistakes are not checked.' );

		// Fluent Forms passes only its own fields to the filter; the rest is in the raw form data.
		$_POST = array( 'data' => http_build_query( array_merge( array( 'email' => 'ann@example.org' ), $this->human_fields( 'fluentforms' ) ) ) );
		$this->assertSame( array(), Form_Plugins::fluent_check( array(), array( 'email' => 'ann@example.org' ), $form ) );
	}

	public function test_elementor() {
		$widget = new ShieldCraft_Test_Elementor();
		$html   = Form_Plugins::elementor_fields( '<div><form><input name="a"></form></div>', $widget );
		$this->assertMatchesRegularExpression( '~data-shieldcraft="elementor".*</form></div>$~s', $html );

		$handler = new ShieldCraft_Test_Elementor();
		Form_Plugins::elementor_check( new ShieldCraft_Test_Elementor(), $handler );
		$this->assertCount( 1, $handler->errors );

		$_POST   = $this->human_fields( 'elementor' );
		$handler = new ShieldCraft_Test_Elementor();
		Form_Plugins::elementor_check( new ShieldCraft_Test_Elementor(), $handler );
		$this->assertSame( array(), $handler->errors );
	}

	public function test_wpforms_fields() {
		ob_start();
		Form_Plugins::wpforms_fields( array( 'id' => 9 ) );
		$this->assertStringContainsString( 'data-shieldcraft="wpforms"', ob_get_clean() );
	}

	public function test_only_active_plugins_are_hooked() {
		$supported = Form_Plugins::supported();
		$this->assertSame( array( 'cf7', 'wpforms', 'gravityforms', 'fluentforms', 'elementor' ), array_keys( $supported ) );
		$this->assertSame( defined( 'WPCF7_VERSION' ), (bool) has_filter( 'wpcf7_spam', array( Form_Plugins::class, 'cf7_check' ) ) );
		$this->assertSame( class_exists( 'GFForms' ), (bool) has_filter( 'gform_entry_is_spam', array( Form_Plugins::class, 'gravity_check' ) ) );
	}

	public function test_php_api() {
		$html = shieldcraft_fields( 'api', false );
		$this->assertStringContainsString( 'data-shieldcraft="api"', $html );

		$result = shieldcraft_check( 'api', array( 'email' => 'ann@example.org' ) );
		$this->assertSame( 'spam', $result['verdict'] );
		$this->assertTrue( $result['spam'] );
		$this->assertSame( 'no_fields', $result['reasons'][0]['check'] );

		$this->assertFalse( shieldcraft_is_spam( 'api', array( 'fields' => $this->human_fields( 'api' ) ) ) );
		$this->assertTrue( shieldcraft_is_spam( 'api', array( 'fields' => $this->human_fields( 'api', 20, false ) ) ), 'Suspicious counts as spam by default.' );
		Settings::save( array( 'forms_suspicious' => 'allow' ) );
		$this->assertFalse( shieldcraft_is_spam( 'api', array( 'fields' => $this->human_fields( 'api', 20, false ) ) ) );
	}

	public function test_rest_check() {
		do_action( 'rest_api_init' );
		$token = Token::create( 'api', time() - 20 );
		$send  = static function ( array $body ) {
			$request = new WP_REST_Request( 'POST', '/shieldcraft/v1/check' );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
			return rest_get_server()->dispatch( $request );
		};
		$ok    = $send(
			array(
				'token'   => $token,
				'js'      => Token::proof( $token ),
				'email'   => 'ann@example.org',
				'message' => 'Hello from a headless form.',
			)
		)->get_data();
		$this->assertSame(
			array(
				'verdict' => 'allow',
				'spam'    => false,
			),
			$ok
		);
		$this->assertTrue( $send( array( 'email' => 'bot@example.org' ) )->get_data()['spam'] );
		$this->assertSame( 400, $send( array( 'context' => 'nope' ) )->get_status() );
	}
}
