<?php
/**
 * Signed form tokens.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Engine;

use ShieldCraft\Installer;

/**
 * A token is "time.random.signature", signed with the site secret for one context, so it cannot be
 * forged, back-dated or reused on another kind of form. The front-end script swaps the token
 * printed in the page for a fresh one (with a proof that the script ran), which keeps cached pages
 * working.
 */
final class Token {

	/**
	 * New token for a context.
	 *
	 * @param string   $context Context.
	 * @param int|null $time    Issue time (tests).
	 * @return string
	 */
	public static function create( $context, $time = null ) {
		$time   = null === $time ? time() : (int) $time;
		$random = wp_generate_password( 12, false, false );
		return $time . '.' . $random . '.' . self::sign( $context, $time . '.' . $random );
	}

	/**
	 * Check a token.
	 *
	 * @param string $token   Token.
	 * @param string $context Context it must be signed for.
	 * @return int|false Age in seconds, or false when it is not a valid token for this context.
	 */
	public static function age( $token, $context ) {
		if ( ! is_string( $token ) || ! preg_match( '/^(\d{9,11})\.([A-Za-z0-9]{12})\.([a-f0-9]{64})$/', $token, $parts ) ) {
			return false;
		}
		if ( ! hash_equals( self::sign( $context, $parts[1] . '.' . $parts[2] ), $parts[3] ) ) {
			return false;
		}
		$age = time() - (int) $parts[1];
		// A token from the future (clock skew allowed) is forged or broken.
		return $age < -60 ? false : max( 0, $age );
	}

	/**
	 * Value the front-end script writes next to a fresh token.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function proof( $token ) {
		return substr( hash_hmac( 'sha256', 'js|' . $token, Installer::secret() ), 0, 32 );
	}

	/**
	 * Whether a proof belongs to a token.
	 *
	 * @param string $token Token.
	 * @param string $proof Proof.
	 * @return bool
	 */
	public static function proof_valid( $token, $proof ) {
		return is_string( $token ) && is_string( $proof ) && '' !== $proof && hash_equals( self::proof( $token ), $proof );
	}

	/**
	 * Signature.
	 *
	 * @param string $context Context.
	 * @param string $payload "time.random".
	 * @return string
	 */
	private static function sign( $context, $payload ) {
		return hash_hmac( 'sha256', $context . '|' . $payload, Installer::secret() );
	}
}
