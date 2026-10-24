<?php
/**
 * The visitor behind a submission.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Engine;

use ShieldCraft\Installer;
use ShieldCraft\Settings;

/**
 * IP address (REMOTE_ADDR, or the proxy header chosen in the settings), keyed hashes, and IP
 * range matching.
 */
final class Request {

	/**
	 * Visitor's IP address, or '' when it is not a valid address.
	 *
	 * @return string
	 */
	public static function ip() {
		$header = Settings::get( 'ip_header' );
		$raw    = '';
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as an IP below.
		if ( $header && ! empty( $_SERVER[ $header ] ) ) {
			// X-Forwarded-For lists the client first.
			$parts = explode( ',', wp_unslash( $_SERVER[ $header ] ) );
			$raw   = trim( $parts[0] );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$raw = wp_unslash( $_SERVER['REMOTE_ADDR'] );
		}
		// phpcs:enable
		$ip = filter_var( $raw, FILTER_VALIDATE_IP ) ? $raw : '';
		/**
		 * Filters the visitor's IP address.
		 *
		 * @param string $ip IP address, or ''.
		 */
		return (string) apply_filters( 'shieldcraft_client_ip', $ip );
	}

	/**
	 * Short description of the visitor's browser, for the log.
	 *
	 * @return string
	 */
	public static function agent() {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( '' === $agent ) {
			return 'none';
		}
		$families = array(
			'bot'     => '/bot|crawl|spider|slurp|python|curl|wget|httpclient|go-http|java\/|libwww|scrapy|headless/i',
			'Edge'    => '/Edg\//',
			'Opera'   => '/OPR\//',
			'Firefox' => '/Firefox\//',
			'Chrome'  => '/Chrome\//',
			'Safari'  => '/Safari\//',
		);
		foreach ( $families as $family => $pattern ) {
			if ( preg_match( $pattern, $agent ) ) {
				return $family;
			}
		}
		return 'other';
	}

	/**
	 * Keyed hash of a value (IP or lower-cased email), so the log can match visitors without
	 * storing who they are.
	 *
	 * @param string $value Value.
	 * @return string 64 hex characters, or '' for an empty value.
	 */
	public static function hash( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return '' === $value ? '' : hash_hmac( 'sha256', $value, Installer::secret() );
	}

	/**
	 * Whether an IP matches an entry: an address, a CIDR range (IPv4 or IPv6) or a prefix ending
	 * in "*" ("203.0.113.*").
	 *
	 * @param string $ip    IP address.
	 * @param string $entry Entry.
	 * @return bool
	 */
	public static function ip_matches( $ip, $entry ) {
		$entry = trim( $entry );
		if ( '' === $ip || '' === $entry ) {
			return false;
		}
		if ( '*' === substr( $entry, -1 ) ) {
			return 0 === strpos( $ip, substr( $entry, 0, -1 ) );
		}
		if ( false === strpos( $entry, '/' ) ) {
			return inet_pton( $ip ) === @inet_pton( $entry ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an invalid entry simply does not match.
		}
		list( $subnet, $bits ) = explode( '/', $entry, 2 );
		$ip_bin                = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$subnet_bin            = @inet_pton( $subnet ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) || ! is_numeric( $bits ) ) {
			return false;
		}
		$bits = (int) $bits;
		if ( $bits < 0 || $bits > strlen( $ip_bin ) * 8 ) {
			return false;
		}
		$bytes = intdiv( $bits, 8 );
		if ( substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
			return false;
		}
		$rest = $bits % 8;
		if ( 0 === $rest ) {
			return true;
		}
		$mask = 0xff << ( 8 - $rest ) & 0xff;
		return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $subnet_bin[ $bytes ] ) & $mask );
	}

	/**
	 * Whether an IP matches any entry.
	 *
	 * @param string   $ip      IP address.
	 * @param string[] $entries Entries.
	 * @return bool
	 */
	public static function ip_in( $ip, array $entries ) {
		foreach ( $entries as $entry ) {
			if ( self::ip_matches( $ip, $entry ) ) {
				return true;
			}
		}
		return false;
	}
}
