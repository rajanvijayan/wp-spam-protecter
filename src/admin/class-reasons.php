<?php
/**
 * Readable names of the reasons a submission is stopped.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Admin;

/**
 * Labels for the check IDs stored in the log.
 */
final class Reasons {

	/**
	 * Labels by check ID.
	 *
	 * @return string[]
	 */
	public static function labels() {
		$labels = array(
			'no_fields'      => __( 'Sent without the form (straight to its address)', 'wp-spam-protecter' ),
			'legacy_form'    => __( 'Sent from an old cached page', 'wp-spam-protecter' ),
			'honeypot'       => __( 'Filled in the hidden field', 'wp-spam-protecter' ),
			'token_invalid'  => __( 'Forged or reused form token', 'wp-spam-protecter' ),
			'too_fast'       => __( 'Sent too fast', 'wp-spam-protecter' ),
			'no_javascript'  => __( 'Browser without JavaScript', 'wp-spam-protecter' ),
			'token_expired'  => __( 'Form open for too long', 'wp-spam-protecter' ),
			'links'          => __( 'Too many links', 'wp-spam-protecter' ),
			'blocked_word'   => __( 'Blocked word', 'wp-spam-protecter' ),
			'blocked_email'  => __( 'Blocked email address', 'wp-spam-protecter' ),
			'blocked_ip'     => __( 'Blocked IP address', 'wp-spam-protecter' ),
			'blocked_sender' => __( 'Sender blocked from the log', 'wp-spam-protecter' ),
			'script'         => __( 'Blocked writing system', 'wp-spam-protecter' ),
			'duplicate'      => __( 'Same message sent again', 'wp-spam-protecter' ),
			'rate_limit'     => __( 'Too many submissions in a minute', 'wp-spam-protecter' ),
			'trackback'      => __( 'Trackbacks are blocked', 'wp-spam-protecter' ),
		);
		/**
		 * Filters the labels of the reasons (add one for a custom check).
		 *
		 * @param string[] $labels Labels by check ID.
		 */
		return (array) apply_filters( 'shieldcraft_reason_labels', $labels );
	}

	/**
	 * Readable list of check IDs ("honeypot,links").
	 *
	 * @param string $codes Comma-separated IDs.
	 * @return string
	 */
	public static function describe( $codes ) {
		$labels = self::labels();
		$out    = array();
		foreach ( array_filter( explode( ',', (string) $codes ) ) as $code ) {
			$out[] = isset( $labels[ $code ] ) ? $labels[ $code ] : $code;
		}
		return implode( ', ', $out );
	}
}
