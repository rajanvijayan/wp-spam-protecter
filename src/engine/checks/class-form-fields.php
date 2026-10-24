<?php
/**
 * Honeypot, signed time trap and the script's proof.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Engine\Checks;

use ShieldCraft\Engine\Submission;
use ShieldCraft\Engine\Token;
use ShieldCraft\Engine\Verdict;
use ShieldCraft\Settings;

/**
 * Bots that post straight to a form's URL send none of the hidden fields; bots that fill in every
 * field fill the honeypot; scripts submit within a second of loading the page; simple bots do not
 * run JavaScript.
 */
final class Form_Fields implements Check {

	/**
	 * Run.
	 *
	 * @param Submission $submission Submission.
	 * @param Verdict    $verdict    Verdict.
	 */
	public function run( Submission $submission, Verdict $verdict ) {
		if ( $submission->content_only ) {
			return;
		}
		$fields = $submission->fields;
		if ( null === $fields ) {
			$verdict->add( 'no_fields', Verdict::SPAM );
			return;
		}
		if ( ! isset( $fields['token'] ) ) {
			// Only the 1.0 field: an old cached page. Hold it rather than lose a real message.
			$verdict->add( 'legacy_form', Verdict::SUSPICIOUS );
			return;
		}
		$settings = Settings::get();

		if ( $settings['honeypot'] && '' !== $fields['honeypot'] ) {
			$verdict->add( 'honeypot', Verdict::SPAM );
		}

		$age = Token::age( $fields['token'], $submission->context );
		if ( false === $age ) {
			$verdict->add( 'token_invalid', Verdict::SPAM );
			return;
		}
		if ( $settings['time_trap'] ) {
			/**
			 * Filters the minimum time, in seconds, between loading a form and sending it.
			 *
			 * @param int    $seconds Seconds (from the settings).
			 * @param string $context Context.
			 */
			$min = (int) apply_filters( 'shieldcraft_min_seconds', $settings['min_seconds'], $submission->context );
			if ( $age < $min ) {
				/* translators: %d: seconds. */
				$verdict->add( 'too_fast', Verdict::SPAM, sprintf( __( 'Sent after %d seconds', 'wp-spam-protecter' ), $age ) );
			}
		}

		$has_proof = Token::proof_valid( $fields['token'], $fields['js'] );
		if ( ! $has_proof && 'off' !== $settings['javascript'] ) {
			$verdict->add( 'no_javascript', $settings['javascript'] );
		}
		// The script refreshes tokens, so only a page without it can send an old one.
		if ( ! $has_proof && $age > $settings['token_hours'] * HOUR_IN_SECONDS ) {
			$verdict->add( 'token_expired', Verdict::SUSPICIOUS );
		}
	}
}
