<?php
/**
 * Settings, log and statistics for the admin app.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Rest;

use ShieldCraft\Log\Log;
use ShieldCraft\Log\Senders;
use ShieldCraft\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Routes under shieldcraft/v1 for administrators (manage_options):
 * GET|POST /settings, GET /log, POST /log/{id}/action, GET /stats, DELETE /senders/{list}.
 */
final class Admin_Controller {

	const ACTIONS = array( 'not_spam', 'block_sender', 'delete' );

	/**
	 * Register the routes.
	 */
	public static function register_routes() {
		$admin = array( self::class, 'permission' );
		register_rest_route(
			'shieldcraft/v1',
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_settings' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'save_settings' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			'shieldcraft/v1',
			'/log',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'log' ),
				'permission_callback' => $admin,
				'args'                => array(
					'context'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'verdict'  => array(
						'type'    => 'string',
						'enum'    => array( '', 'spam', 'suspicious' ),
						'default' => '',
					),
					'search'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'page'     => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					),
				),
			)
		);
		register_rest_route(
			'shieldcraft/v1',
			'/log/(?P<id>\d+)/action',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'action' ),
				'permission_callback' => $admin,
				'args'                => array(
					'action' => array(
						'type'     => 'string',
						'enum'     => self::ACTIONS,
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			'shieldcraft/v1',
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $request ) {
					return Log::stats( (int) $request['days'] );
				},
				'permission_callback' => $admin,
				'args'                => array(
					'days' => array(
						'type'    => 'integer',
						'enum'    => array( 7, 30 ),
						'default' => 30,
					),
				),
			)
		);
		register_rest_route(
			'shieldcraft/v1',
			'/senders/(?P<list>allowed|blocked)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( self::class, 'clear_senders' ),
				'permission_callback' => $admin,
			)
		);
	}

	/**
	 * Administrators only.
	 *
	 * @return bool
	 */
	public static function permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Settings with the sizes of the sender lists.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return array(
			'settings' => Settings::get(),
			'senders'  => array(
				'allowed' => count( Senders::all( Senders::ALLOWED ) ),
				'blocked' => count( Senders::all( Senders::BLOCKED ) ),
			),
		);
	}

	/**
	 * Save any subset of the settings.
	 *
	 * @param WP_REST_Request $request Request with a JSON body.
	 * @return array
	 */
	public static function save_settings( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		Settings::save( is_array( $data ) ? $data : array() );
		return self::get_settings();
	}

	/**
	 * Log rows.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function log( WP_REST_Request $request ) {
		return Log::query(
			array(
				'context'  => sanitize_key( $request['context'] ),
				'verdict'  => $request['verdict'],
				'search'   => sanitize_text_field( $request['search'] ),
				'page'     => $request['page'],
				'per_page' => $request['per_page'],
			)
		);
	}

	/**
	 * Act on a log row.
	 *
	 * Not spam: approve the comment it held or marked spam, and let the sender's email address
	 * through from now on. Block sender: refuse the sender's email and IP address from now on (and
	 * move the comment to spam). Delete: remove the row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error Updated row (or { deleted: true }).
	 */
	public static function action( WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$row = Log::get( $id );
		if ( ! $row ) {
			return new WP_Error( 'shieldcraft_not_found', __( 'That log entry no longer exists.', 'wp-spam-protecter' ), array( 'status' => 404 ) );
		}
		if ( 'delete' === $request['action'] ) {
			Log::delete( $id );
			return array( 'deleted' => true );
		}
		$comment = in_array( $row['context'], array( 'comment', 'review', 'trackback' ), true ) && (int) $row['object_id'] ? get_comment( (int) $row['object_id'] ) : null;
		if ( 'not_spam' === $request['action'] ) {
			// Trust the email address only: an IP address is often shared (offices, mobile networks).
			Senders::add( Senders::ALLOWED, array( $row['email_hash'] ) );
			if ( $comment && current_user_can( 'edit_comment', $comment->comment_ID ) ) {
				wp_set_comment_status( $comment, 'approve' );
			}
			Log::set_status( $id, 'released' );
		} else {
			Senders::add( Senders::BLOCKED, array( $row['ip_hash'], $row['email_hash'] ) );
			if ( $comment && current_user_can( 'edit_comment', $comment->comment_ID ) && 'spam' !== $comment->comment_approved ) {
				wp_spam_comment( $comment );
			}
			Log::set_status( $id, 'blocked' );
		}
		return Log::present( Log::get( $id ) );
	}

	/**
	 * Empty a sender list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function clear_senders( WP_REST_Request $request ) {
		Senders::clear( 'allowed' === $request['list'] ? Senders::ALLOWED : Senders::BLOCKED );
		return self::get_settings()['senders'];
	}
}
