<?php
/**
 * Site settings, stored in one option.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft;

/**
 * Settings are read through get() (defaults merged in) and written through save(), which sanitizes
 * every value against the defaults.
 */
final class Settings {

	const OPTION = 'shieldcraft_settings';

	/**
	 * Allowed values of the choice settings.
	 */
	const CHOICES = array(
		'javascript'          => array( 'off', 'suspicious', 'spam' ),
		'comments_spam'       => array( 'spam', 'reject' ),
		'comments_suspicious' => array( 'hold', 'spam', 'allow' ),
		'trackbacks'          => array( 'check', 'block', 'allow' ),
		'ip_header'           => array( '', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ),
	);

	/**
	 * Writing systems the "block scripts" rule knows (Unicode script names).
	 */
	const SCRIPTS = array( 'Arabic', 'Cyrillic', 'Devanagari', 'Greek', 'Han', 'Hangul', 'Hebrew', 'Hiragana', 'Katakana', 'Thai' );

	/**
	 * Numeric settings: [ min, max ].
	 */
	const RANGES = array(
		'min_seconds'       => array( 0, 60 ),
		'token_hours'       => array( 1, 168 ),
		'max_links'         => array( 0, 50 ),
		'duplicate_minutes' => array( 1, 1440 ),
		'rate_per_minute'   => array( 1, 100 ),
		'retention_days'    => array( 1, 365 ),
	);

	/**
	 * Text settings holding one entry per line.
	 */
	const LISTS = array( 'blocked_words', 'blocked_emails', 'blocked_ips', 'allowed_ips', 'allowed_emails' );

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Checks.
			'honeypot'            => true,
			'time_trap'           => true,
			// Submissions sent sooner than this after the form was loaded are spam.
			'min_seconds'         => 3,
			// Tokens older than this (a page left open, or cached without the script) are suspicious.
			'token_hours'         => 12,
			// What a submission without the script's proof is.
			'javascript'          => 'suspicious',
			'links'               => true,
			'max_links'           => 2,
			'blocked_words'       => '',
			// Also use Settings → Discussion → "Disallowed Comment Keys".
			'disallowed_keys'     => true,
			'blocked_emails'      => '',
			'blocked_ips'         => '',
			'blocked_scripts'     => array(),
			'duplicate'           => true,
			'duplicate_minutes'   => 10,
			'rate_limit'          => true,
			'rate_per_minute'     => 5,
			// Allowlist.
			'allowed_ips'         => '',
			'allowed_emails'      => '',
			'allowed_roles'       => array( 'administrator', 'editor' ),
			// Comments.
			'comments'            => true,
			'comments_spam'       => 'spam',
			'comments_suspicious' => 'hold',
			'trackbacks'          => 'check',
			// Log and privacy.
			'retention_days'      => 30,
			'store_ip'            => false,
			// Server variable holding the visitor's address behind a proxy ('' uses REMOTE_ADDR).
			'ip_header'           => '',
		);
	}

	/**
	 * All settings, or one.
	 *
	 * @param string $key Key (optional).
	 * @return mixed
	 */
	public static function get( $key = '' ) {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = self::sanitize( is_array( $stored ) ? $stored : array() );
		}
		if ( '' === $key ) {
			return self::$cache;
		}
		return isset( self::$cache[ $key ] ) ? self::$cache[ $key ] : null;
	}

	/**
	 * Save settings.
	 *
	 * @param array $settings Any subset.
	 * @return array Saved settings.
	 */
	public static function save( array $settings ) {
		update_option( self::OPTION, self::sanitize( array_merge( self::get(), $settings ) ) );
		self::$cache = null;
		return self::get();
	}

	/**
	 * Forget the cached copy.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Entries of a one-per-line setting, trimmed, lower-cased and without empty lines or comments.
	 *
	 * @param string $key Setting.
	 * @return string[]
	 */
	public static function lines( $key ) {
		$out = array();
		foreach ( preg_split( '/\R/', (string) self::get( $key ) ) as $line ) {
			$line = strtolower( trim( $line ) );
			if ( '' !== $line && '#' !== $line[0] ) {
				$out[] = $line;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Sanitize against the defaults.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function sanitize( array $settings ) {
		$out = array();
		foreach ( self::defaults() as $key => $default ) {
			$value = array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
			if ( isset( self::CHOICES[ $key ] ) ) {
				$value = in_array( $value, self::CHOICES[ $key ], true ) ? $value : $default;
			} elseif ( isset( self::RANGES[ $key ] ) ) {
				$value = max( self::RANGES[ $key ][0], min( self::RANGES[ $key ][1], (int) $value ) );
			} elseif ( in_array( $key, self::LISTS, true ) ) {
				$value = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
			} elseif ( 'blocked_scripts' === $key ) {
				$value = array_values( array_intersect( self::SCRIPTS, is_array( $value ) ? $value : array() ) );
			} elseif ( 'allowed_roles' === $key ) {
				$value = array_values( array_unique( array_filter( array_map( 'sanitize_key', is_array( $value ) ? $value : array() ) ) ) );
			} else {
				$value = (bool) rest_sanitize_boolean( $value );
			}
			$out[ $key ] = $value;
		}
		return $out;
	}
}
