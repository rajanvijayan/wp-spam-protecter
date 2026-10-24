<?php
/**
 * Decision for forms that refuse spam with an error.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Areas;

use ShieldCraft\Engine\Engine;
use ShieldCraft\Engine\Submission;
use ShieldCraft\Engine\Verdict;
use ShieldCraft\Settings;

/**
 * Registration, password and checkout forms cannot hold a submission for moderation: spam is
 * refused, and suspicious submissions are refused or let through (setting). Login forms never
 * refuse suspicious ones, so nobody is locked out for browsing without JavaScript.
 */
final class Guard {

	/**
	 * Check a submission.
	 *
	 * @param Submission $submission       Submission.
	 * @param bool       $block_suspicious Whether suspicious submissions may be refused.
	 * @return string Error message, or '' to let it through.
	 */
	public static function check( Submission $submission, $block_suspicious = true ) {
		$verdict = Engine::check( $submission );
		if ( $verdict->is_spam() ) {
			return self::message();
		}
		if ( Verdict::SUSPICIOUS === $verdict->verdict && $block_suspicious && 'block' === Settings::get( 'forms_suspicious' ) ) {
			return self::message();
		}
		return '';
	}

	/**
	 * Error shown to the sender.
	 *
	 * @return string
	 */
	public static function message() {
		/**
		 * Filters the error shown when a form is refused as spam.
		 *
		 * @param string $message Message.
		 */
		return (string) apply_filters( 'shieldcraft_blocked_message', __( 'Your request could not be sent because it looks like spam. Please reload the page and try again.', 'wp-spam-protecter' ) );
	}
}
