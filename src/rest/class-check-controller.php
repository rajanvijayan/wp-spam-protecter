<?php
/**
 * Spam checks for headless and custom front ends.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Rest;

use ShieldCraft\Engine\Engine;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * POST /shieldcraft/v1/check { context, token, js, honeypot, name, email, url, message, form }
 * → { verdict, spam }.
 *
 * Get the token and proof from GET /token first. The answer only helps a server that enforces
 * it: call this from the code that accepts the form, not from the visitor's browser alone. Calls
 * are logged and rate-limited like any other submission.
 */
final class Check_Controller {

	/**
	 * Register the route.
	 */
	public static function register_routes() {
		$string = array( 'type' => 'string' );
		register_rest_route(
			'shieldcraft/v1',
			'/check',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'check' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'context'  => array(
						'type'    => 'string',
						'default' => 'api',
					),
					'token'    => $string,
					'js'       => $string,
					'honeypot' => $string,
					'name'     => $string,
					'email'    => $string,
					'url'      => $string,
					'message'  => $string,
					'form'     => $string,
				),
			)
		);
	}

	/**
	 * Check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function check( WP_REST_Request $request ) {
		$context = (string) $request['context'];
		if ( ! array_key_exists( $context, Engine::contexts() ) ) {
			return new WP_Error( 'shieldcraft_unknown_context', __( 'Unknown context.', 'wp-spam-protecter' ), array( 'status' => 400 ) );
		}
		$data = array();
		foreach ( array( 'name', 'email', 'url', 'message', 'form' ) as $key ) {
			$data[ $key ] = (string) $request[ $key ];
		}
		$data['fields'] = array(
			'shieldcraft_token'   => (string) $request['token'],
			'shieldcraft_js'      => (string) $request['js'],
			'shieldcraft_website' => (string) $request['honeypot'],
		);
		$result         = shieldcraft_check( $context, $data );
		return array(
			'verdict' => $result['verdict'],
			'spam'    => $result['spam'],
		);
	}
}
