<?php
/**
 * Runs the checks on a submission.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Engine;

use ShieldCraft\Engine\Checks\Check;
use ShieldCraft\Engine\Checks\Content;
use ShieldCraft\Engine\Checks\Form_Fields;
use ShieldCraft\Engine\Checks\Repetition;
use ShieldCraft\Log\Log;
use ShieldCraft\Settings;

/**
 * Allowlisted senders pass without checks. Otherwise every check adds its reasons, extensions can
 * change the verdict, and anything but "allow" is logged.
 */
final class Engine {

	/**
	 * Labels of the contexts ShieldCraft protects, by ID.
	 *
	 * @return string[]
	 */
	public static function contexts() {
		$contexts = array(
			'comment'       => __( 'Comments', 'wp-spam-protecter' ),
			'review'        => __( 'Product reviews', 'wp-spam-protecter' ),
			'trackback'     => __( 'Trackbacks and pingbacks', 'wp-spam-protecter' ),
			'registration'  => __( 'Registration', 'wp-spam-protecter' ),
			'lost_password' => __( 'Lost password', 'wp-spam-protecter' ),
			'login'         => __( 'Login', 'wp-spam-protecter' ),
			'signup'        => __( 'Network sign-up', 'wp-spam-protecter' ),
			'wc_register'   => __( 'WooCommerce registration', 'wp-spam-protecter' ),
			'wc_login'      => __( 'WooCommerce login', 'wp-spam-protecter' ),
			'wc_checkout'   => __( 'WooCommerce checkout', 'wp-spam-protecter' ),
		);
		/**
		 * Filters the contexts (kinds of forms) ShieldCraft knows. Add one to protect a custom form
		 * with shieldcraft_fields() and shieldcraft_check().
		 *
		 * @param string[] $contexts Labels by context ID.
		 */
		return (array) apply_filters( 'shieldcraft_contexts', $contexts );
	}

	/**
	 * Check a submission.
	 *
	 * @param Submission $submission Submission.
	 * @return Verdict
	 */
	public static function check( Submission $submission ) {
		$verdict = new Verdict();
		if ( ( defined( 'SHIELDCRAFT_DISABLE' ) && SHIELDCRAFT_DISABLE ) || self::allowlisted( $submission ) ) {
			return $verdict;
		}

		foreach ( self::checks() as $check ) {
			if ( $check instanceof Check ) {
				$check->run( $submission, $verdict );
			}
		}

		/**
		 * Filters the verdict on a submission.
		 *
		 * @param Verdict    $verdict    Verdict (add reasons with $verdict->add()).
		 * @param Submission $submission Submission.
		 */
		$filtered = apply_filters( 'shieldcraft_verdict', $verdict, $submission );
		$verdict  = $filtered instanceof Verdict ? $filtered : $verdict;

		if ( ! $verdict->is_allowed() ) {
			$verdict->log_id = Log::insert( $submission, $verdict );
			/**
			 * Fires when a submission is not allowed.
			 *
			 * @param Verdict    $verdict    Verdict.
			 * @param Submission $submission Submission.
			 */
			do_action( 'shieldcraft_blocked', $verdict, $submission );
		}
		return $verdict;
	}

	/**
	 * Checks to run.
	 *
	 * @return Check[]
	 */
	private static function checks() {
		/**
		 * Filters the checks. Add an object implementing ShieldCraft\Engine\Checks\Check.
		 *
		 * @param Check[] $checks Checks by ID.
		 */
		return (array) apply_filters(
			'shieldcraft_checks',
			array(
				'form'       => new Form_Fields(),
				'content'    => new Content(),
				'repetition' => new Repetition(),
			)
		);
	}

	/**
	 * Whether the sender is trusted: a moderator, a user with an allowed role, or an allowed IP
	 * or email address.
	 *
	 * @param Submission $submission Submission.
	 * @return bool
	 */
	public static function allowlisted( Submission $submission ) {
		if ( $submission->user_id ) {
			$user = get_userdata( $submission->user_id );
			if ( $user && ( user_can( $user, 'moderate_comments' ) || array_intersect( (array) $user->roles, Settings::get( 'allowed_roles' ) ) ) ) {
				return true;
			}
		}
		if ( Request::ip_in( $submission->ip, Settings::lines( 'allowed_ips' ) ) ) {
			return true;
		}
		return '' !== $submission->email && Content::email_in( $submission->email, Settings::lines( 'allowed_emails' ) );
	}
}
