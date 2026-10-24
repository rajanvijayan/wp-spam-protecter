<?php
/**
 * Privacy tools.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Log;

use ShieldCraft\Engine\Engine;
use ShieldCraft\Settings;

/**
 * Suggested privacy policy text, and the personal data exporter and eraser (Tools → Export /
 * Erase Personal Data) for log rows, found by the keyed hash of the email address.
 */
final class Privacy {

	const PAGE = 100;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_init', array( self::class, 'policy' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'erasers' ) );
	}

	/**
	 * Suggested privacy policy text.
	 */
	public static function policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<p>' . __( 'When you send a comment or a form, this site checks it for spam on its own server; nothing is sent to other services. A hidden field and a short code written by your browser show that a person sent it.', 'wp-spam-protecter' ) . '</p>'
			. '<p>' . sprintf(
				/* translators: %d: days. */
				_n( 'If a message is blocked or held as spam, the site keeps a record for up to %d day: the time, the form, why it was stopped, your name, the first 200 characters of the message, the type of browser, and coded (hashed) forms of your email and IP address from which the original cannot be read back. The site owner can choose to keep the IP address itself.', 'If a message is blocked or held as spam, the site keeps a record for up to %d days: the time, the form, why it was stopped, your name, the first 200 characters of the message, the type of browser, and coded (hashed) forms of your email and IP address from which the original cannot be read back. The site owner can choose to keep the IP address itself.', Settings::get( 'retention_days' ), 'wp-spam-protecter' ),
				Settings::get( 'retention_days' )
			) . '</p>';
		wp_add_privacy_policy_content( 'ShieldCraft', wp_kses_post( $text ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function exporters( $exporters ) {
		$exporters['shieldcraft'] = array(
			'exporter_friendly_name' => __( 'Spam log (ShieldCraft)', 'wp-spam-protecter' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function erasers( $erasers ) {
		$erasers['shieldcraft'] = array(
			'eraser_friendly_name' => __( 'Spam log (ShieldCraft)', 'wp-spam-protecter' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export the log rows of an email address.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page (from 1).
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		$page     = max( 1, (int) $page );
		$rows     = Log::for_email( $email, self::PAGE, ( $page - 1 ) * self::PAGE );
		$contexts = Engine::contexts();
		$items    = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'group_id'    => 'shieldcraft-log',
				'group_label' => __( 'Spam log', 'wp-spam-protecter' ),
				'item_id'     => 'shieldcraft-log-' . $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Date (UTC)', 'wp-spam-protecter' ),
						'value' => $row['created'],
					),
					array(
						'name'  => __( 'Form', 'wp-spam-protecter' ),
						'value' => isset( $contexts[ $row['context'] ] ) ? $contexts[ $row['context'] ] : $row['context'],
					),
					array(
						'name'  => __( 'Result', 'wp-spam-protecter' ),
						'value' => $row['verdict'],
					),
					array(
						'name'  => __( 'Reasons', 'wp-spam-protecter' ),
						'value' => $row['reasons'],
					),
					array(
						'name'  => __( 'Name', 'wp-spam-protecter' ),
						'value' => $row['name'],
					),
					array(
						'name'  => __( 'Message (start)', 'wp-spam-protecter' ),
						'value' => $row['excerpt'],
					),
					array(
						'name'  => __( 'IP address', 'wp-spam-protecter' ),
						'value' => $row['ip'],
					),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => count( $rows ) < self::PAGE,
		);
	}

	/**
	 * Erase the log rows of an email address.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function erase( $email ) {
		$removed = Log::delete_for_email( $email );
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
