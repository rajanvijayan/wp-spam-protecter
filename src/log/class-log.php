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
		$table   = esc_sql( self::table() );
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
		$table = esc_sql( self::table() );
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
		$table  = esc_sql( self::table() );
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
		$table = esc_sql( self::table() );
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
	/**
	 * Rows, newest first.
	 *
	 * @param array $args { context, verdict, search (name or message), page, per_page }.
	 * @return array { rows, total }
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'context'  => '',
				'verdict'  => '',
				'search'   => '',
				'page'     => 1,
				'per_page' => 20,
			)
		);
		$where = array( '1 = %d' );
		$vars  = array( 1 );
		if ( '' !== $args['context'] ) {
			$where[] = 'context = %s';
			$vars[]  = (string) $args['context'];
		}
		if ( '' !== $args['verdict'] ) {
			$where[] = 'verdict = %s';
			$vars[]  = (string) $args['verdict'];
		}
		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[] = '(name LIKE %s OR excerpt LIKE %s)';
			$vars[]  = $like;
			$vars[]  = $like;
		}
		$per_page = max( 1, min( 100, (int) $args['per_page'] ) );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;
		$table    = esc_sql( self::table() );
		$sql      = implode( ' AND ', $where );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom table; $sql holds placeholders only, filled from $vars.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$sql}", $vars ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$sql} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $vars, array( $per_page, $offset ) ) ), ARRAY_A );
		// phpcs:enable
		return array(
			'rows'  => array_map( array( self::class, 'present' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * A row as the admin shows it: no hashes, reasons decoded.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public static function present( array $row ) {
		$details = json_decode( (string) $row['details'], true );
		return array(
			'id'        => (int) $row['id'],
			'created'   => mysql_to_rfc3339( $row['created'] ),
			'context'   => $row['context'],
			'form'      => $row['form'],
			'verdict'   => $row['verdict'],
			'reasons'   => is_array( $details ) ? $details : array(),
			'name'      => $row['name'],
			'excerpt'   => $row['excerpt'],
			'agent'     => $row['agent'],
			'ip'        => $row['ip'],
			'object_id' => (int) $row['object_id'],
			'status'    => $row['status'],
			'link'      => self::object_link( $row ),
		);
	}

	/**
	 * Admin link to what a row is about (the comment), or ''.
	 *
	 * @param array $row Row.
	 * @return string
	 */
	private static function object_link( array $row ) {
		if ( (int) $row['object_id'] && in_array( $row['context'], array( 'comment', 'review', 'trackback' ), true ) && get_comment( (int) $row['object_id'] ) ) {
			return admin_url( 'comment.php?action=editcomment&c=' . (int) $row['object_id'] );
		}
		return '';
	}

	/**
	 * Counts for the dashboard.
	 *
	 * @param int $days Days back.
	 * @return array { total, spam, suspicious, contexts: { context: n }, reasons: { check: n }, days: { Y-m-d: n } }
	 */
	public static function stats( $days = 30 ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$since = gmdate( 'Y-m-d H:i:s', time() - (int) $days * DAY_IN_SECONDS );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name.
		$by_context = $wpdb->get_results( $wpdb->prepare( "SELECT context, verdict, COUNT(*) AS n FROM {$table} WHERE created >= %s GROUP BY context, verdict", $since ), ARRAY_A );
		$by_day     = $wpdb->get_results( $wpdb->prepare( "SELECT SUBSTR(created, 1, 10) AS day, COUNT(*) AS n FROM {$table} WHERE created >= %s GROUP BY day", $since ), ARRAY_A );
		$reasons    = $wpdb->get_col( $wpdb->prepare( "SELECT reasons FROM {$table} WHERE created >= %s ORDER BY id DESC LIMIT 5000", $since ) );
		// phpcs:enable
		$out = array(
			'total'      => 0,
			'spam'       => 0,
			'suspicious' => 0,
			'contexts'   => array(),
			'reasons'    => array(),
			'days'       => array(),
		);
		foreach ( (array) $by_context as $row ) {
			$n                                  = (int) $row['n'];
			$out['total']                      += $n;
			$out[ $row['verdict'] ]             = ( isset( $out[ $row['verdict'] ] ) ? $out[ $row['verdict'] ] : 0 ) + $n;
			$out['contexts'][ $row['context'] ] = ( isset( $out['contexts'][ $row['context'] ] ) ? $out['contexts'][ $row['context'] ] : 0 ) + $n;
		}
		foreach ( (array) $reasons as $codes ) {
			foreach ( array_filter( explode( ',', (string) $codes ) ) as $code ) {
				$out['reasons'][ $code ] = ( isset( $out['reasons'][ $code ] ) ? $out['reasons'][ $code ] : 0 ) + 1;
			}
		}
		arsort( $out['contexts'] );
		arsort( $out['reasons'] );
		for ( $i = (int) $days - 1; $i >= 0; $i-- ) {
			$out['days'][ gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS ) ] = 0;
		}
		foreach ( (array) $by_day as $row ) {
			if ( isset( $out['days'][ $row['day'] ] ) ) {
				$out['days'][ $row['day'] ] = (int) $row['n'];
			}
		}
		$out['contexts'] = (object) $out['contexts'];
		$out['reasons']  = (object) $out['reasons'];
		$out['days']     = (object) $out['days'];
		return $out;
	}

	/**
	 * Update a row's status.
	 *
	 * @param int    $id     Row ID.
	 * @param string $status Status.
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$wpdb->update( self::table(), array( 'status' => sanitize_key( $status ) ), array( 'id' => (int) $id ) );
	}

	/**
	 * Delete a row.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}
}
