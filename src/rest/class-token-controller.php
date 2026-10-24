<?php
/**
 * Fresh tokens for the front-end script.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Rest;

use ShieldCraft\Engine\Engine;
use ShieldCraft\Engine\Token;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /shieldcraft/v1/token?context=comment,registration → { tokens: { context: { token, proof } } }.
 *
 * Public: the token only proves when a form was opened and that a script asked for it. Responses
 * are never cached, so pages served from a cache still get fresh tokens.
 */
final class Token_Controller {

	/**
	 * Register the route.
	 */
	public static function register_routes() {
		register_rest_route(
			'shieldcraft/v1',
			'/token',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'tokens' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'context' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Tokens for the requested contexts (unknown ones are left out).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function tokens( WP_REST_Request $request ) {
		$known  = Engine::contexts();
		$tokens = array();
		foreach ( array_slice( array_unique( array_map( 'trim', explode( ',', (string) $request['context'] ) ) ), 0, 10 ) as $context ) {
			if ( isset( $known[ $context ] ) ) {
				$token              = Token::create( $context );
				$tokens[ $context ] = array(
					'token' => $token,
					'proof' => Token::proof( $token ),
				);
			}
		}
		$response = new WP_REST_Response( array( 'tokens' => (object) $tokens ) );
		foreach ( wp_get_nocache_headers() as $name => $value ) {
			$response->header( $name, $value );
		}
		return $response;
	}
}
