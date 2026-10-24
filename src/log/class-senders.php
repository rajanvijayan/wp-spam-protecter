<?php
/**
 * Senders allowed or blocked from the log.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Log;

/**
 * "Not spam" and "Block sender" in the log work on the keyed hashes the log stores, so they need
 * no email or IP address: each list holds hashes (IP and email), newest first.
 */
final class Senders {

	const ALLOWED = 'shieldcraft_allowed_senders';
	const BLOCKED = 'shieldcraft_blocked_senders';
	const MAX     = 1000;

	/**
	 * Hashes in a list.
	 *
	 * @param string $name self::ALLOWED or self::BLOCKED.
	 * @return string[]
	 */
	public static function all( $name ) {
		$hashes = get_option( $name, array() );
		return is_array( $hashes ) ? array_values( array_filter( $hashes, 'is_string' ) ) : array();
	}

	/**
	 * Whether any of the hashes is in a list.
	 *
	 * @param string   $name   List.
	 * @param string[] $hashes Hashes.
	 * @return bool
	 */
	public static function has( $name, array $hashes ) {
		$hashes = array_filter( $hashes );
		return $hashes && array_intersect( $hashes, self::all( $name ) );
	}

	/**
	 * Add hashes to a list (and take them off the other one).
	 *
	 * @param string   $name   List.
	 * @param string[] $hashes Hashes.
	 */
	public static function add( $name, array $hashes ) {
		$hashes = array_values( array_filter( $hashes, static fn( $hash ) => is_string( $hash ) && preg_match( '/^[a-f0-9]{64}$/', $hash ) ) );
		if ( ! $hashes ) {
			return;
		}
		$other = self::ALLOWED === $name ? self::BLOCKED : self::ALLOWED;
		update_option( $other, array_values( array_diff( self::all( $other ), $hashes ) ), false );
		update_option( $name, array_slice( array_values( array_unique( array_merge( $hashes, self::all( $name ) ) ) ), 0, self::MAX ), false );
	}

	/**
	 * Empty a list.
	 *
	 * @param string $name List.
	 */
	public static function clear( $name ) {
		delete_option( $name );
	}
}
