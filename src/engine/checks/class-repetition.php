<?php
/**
 * Rate limit and duplicate messages.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Engine\Checks;

use ShieldCraft\Engine\Request;
use ShieldCraft\Engine\Submission;
use ShieldCraft\Engine\Verdict;
use ShieldCraft\Settings;

/**
 * Counts submissions per visitor (by IP hash) and context in one-minute windows, and remembers
 * recent messages, in transients.
 */
final class Repetition implements Check {

	/**
	 * Run.
	 *
	 * @param Submission $submission Submission.
	 * @param Verdict    $verdict    Verdict.
	 */
	public function run( Submission $submission, Verdict $verdict ) {
		$settings = Settings::get();
		$visitor  = Request::hash( $submission->ip );

		if ( $settings['rate_limit'] && '' !== $visitor ) {
			$key    = 'shieldcraft_rate_' . substr( md5( $submission->context . $visitor ), 0, 20 );
			$window = get_transient( $key );
			$now    = time();
			if ( ! is_array( $window ) || $now - $window[0] >= MINUTE_IN_SECONDS ) {
				$window = array( $now, 0 );
			}
			++$window[1];
			set_transient( $key, $window, MINUTE_IN_SECONDS );
			if ( $window[1] > $settings['rate_per_minute'] ) {
				/* translators: %d: submissions in a minute. */
				$verdict->add( 'rate_limit', Verdict::SPAM, sprintf( __( '%d in a minute', 'wp-spam-protecter' ), $window[1] ) );
			}
		}

		$message = strtolower( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $submission->message ) ) ) );
		$sender  = '' !== $visitor ? $visitor : Request::hash( $submission->email );
		if ( $settings['duplicate'] && strlen( $message ) >= 10 && '' !== $sender ) {
			$key = 'shieldcraft_dup_' . substr( md5( $submission->context . $sender . $message ), 0, 20 );
			if ( get_transient( $key ) ) {
				$verdict->add( 'duplicate', Verdict::SPAM );
			}
			set_transient( $key, 1, $settings['duplicate_minutes'] * MINUTE_IN_SECONDS );
		}
	}
}
