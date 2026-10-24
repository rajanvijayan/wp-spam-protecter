<?php
/**
 * Spam log.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Log;

use ShieldCraft\Engine\Request;
use ShieldCraft\Engine\Submission;
use ShieldCraft\Engine\Verdict;
use ShieldCraft\Settings;

/**
 * One row per blocked or held submission, kept for the retention period. Visitors are stored as
 * keyed hashes; the IP address itself only when the site owner turns that on.
 */
final class Log {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'shieldcraft_log';
	}

	/**
	 * Create or update the table.
	 */
	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				context varchar(40) NOT NULL DEFAULT '',
				form varchar(100) NOT NULL DEFAULT '',
				verdict varchar(12) NOT NULL DEFAULT '',
				reasons varchar(255) NOT NULL DEFAULT '',
				details text NULL,
				ip_hash char(64) NOT NULL DEFAULT '',
				ip varchar(45) NOT NULL DEFAULT '',
				email_hash char(64) NOT NULL DEFAULT '',
				name varchar(100) NOT NULL DEFAULT '',
				excerpt varchar(255) NOT NULL DEFAULT '',
				agent varchar(20) NOT NULL DEFAULT '',
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY created (created),
				KEY context_created (context, created),
				KEY email_hash (email_hash),
				KEY ip_hash (ip_hash)
			) {$charset};"
		);
	}

	/**
	 * Log a submission.
	 *
	 * @param Submission $submission Submission.
	 * @param Verdict    $verdict    Verdict.
	 * @return int Row ID (0 when the row could not be written).
	 */
	public static function insert( Submission $submission, Verdict $verdict ) {
		global $wpdb;
		$details = array();
		foreach ( $verdict->reasons as $reason ) {
			$details[] = array(
				'check'  => $reason['check'],
				'detail' => $reason['detail'],
			);
		}
		$row = array(
			'created'    => current_time( 'mysql', true ),
			'context'    => substr( $submission->context, 0, 40 ),
			'form'       => substr( (string) $submission->form, 0, 100 ),
			'verdict'    => $verdict->verdict,
			'reasons'    => substr( $verdict->codes(), 0, 255 ),
			'details'    => wp_json_encode( $details ),
			'ip_hash'    => Request::hash( $submission->ip ),
			'ip'         => Settings::get( 'store_ip' ) ? $submission->ip : '',
			'email_hash' => Request::hash( $submission->email ),
			'name'       => mb_substr( sanitize_text_field( $submission->name ), 0, 100 ),
			'excerpt'    => mb_substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $submission->message ) ) ), 0, 200 ),
			'agent'      => Request::agent(),
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
		return $wpdb->insert( self::table(), $row ) ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Link a row to what was created (a comment held or marked spam).
	 *
	 * @param int    $id        Row ID.
	 * @param int    $object_id Object ID.
	 * @param string $status    Status of the object.
	 */
	public static function attach( $id, $object_id, $status = '' ) {
		global $wpdb;
		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
			$wpdb->update(
				self::table(),
				array(
					'object_id' => (int) $object_id,
					'status'    => sanitize_key( $status ),
				),
				array( 'id' => (int) $id )
			);
		}
	}

	/**
	 * One row.
	 *
	 * @param int $id Row ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Delete rows older than the retention period.
	 *
	 * @return int Rows deleted.
	 */
	public static function purge() {
		global $wpdb;
		$table  = self::table();
		$before = gmdate( 'Y-m-d H:i:s', time() - Settings::get( 'retention_days' ) * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created < %s", $before ) );
	}

	/**
	 * Rows of an email address, newest first (personal data exports).
	 *
	 * @param string $email  Email.
	 * @param int    $limit  Rows.
	 * @param int    $offset Offset.
	 * @return array[]
	 */
	public static function for_email( $email, $limit = 100, $offset = 0 ) {
		global $wpdb;
		$hash = Request::hash( $email );
		if ( '' === $hash ) {
			return array();
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email_hash = %s ORDER BY id DESC LIMIT %d OFFSET %d", $hash, $limit, $offset ), ARRAY_A );
	}

	/**
	 * Delete the rows of an email address.
	 *
	 * @param string $email Email.
	 * @return int Rows deleted.
	 */
	public static function delete_for_email( $email ) {
		global $wpdb;
		$hash = Request::hash( $email );
		if ( '' === $hash ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		return (int) $wpdb->delete( self::table(), array( 'email_hash' => $hash ) );
	}
}
