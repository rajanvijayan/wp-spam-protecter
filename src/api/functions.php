<?php
/**
 * Public API for custom forms.
 *
 * Print shieldcraft_fields() inside your form, then call shieldcraft_check() (or
 * shieldcraft_is_spam()) where you handle the submission:
 *
 *     <form method="post">
 *         ...
 *         <?php shieldcraft_fields( 'api' ); ?>
 *     </form>
 *
 *     if ( shieldcraft_is_spam( 'api', array( 'email' => $email, 'message' => $message ) ) ) {
 *         // Refuse it.
 *     }
 *
 * The context must be one ShieldCraft knows: "api", or one added with the shieldcraft_contexts
 * filter. A token is only valid for the context it was printed for.
 *
 * @package ShieldCraft
 */

defined( 'ABSPATH' ) || exit;

use ShieldCraft\Engine\Engine;
use ShieldCraft\Engine\Fields;
use ShieldCraft\Engine\Submission;
use ShieldCraft\Engine\Verdict;
use ShieldCraft\Settings;

if ( ! function_exists( 'shieldcraft_fields' ) ) {
	/**
	 * Print (or return) ShieldCraft's hidden fields for a form.
	 *
	 * @param string $context Context.
	 * @param bool   $display Whether to print them.
	 * @return string Markup.
	 */
	function shieldcraft_fields( $context = 'api', $display = true ) {
		$html = Fields::render( (string) $context );
		if ( $display ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Fields::render().
		}
		return $html;
	}
}

if ( ! function_exists( 'shieldcraft_check' ) ) {
	/**
	 * Check a submission.
	 *
	 * @param string $context Context.
	 * @param array  $data    Any of: name, email, url, message, form (an ID for the log), and
	 *                        fields (the hidden field values; read from $_POST when left out).
	 * @return array { verdict: allow|suspicious|spam, reasons: array, spam: bool }
	 */
	function shieldcraft_check( $context = 'api', array $data = array() ) {
		$allowed = array_flip( array( 'name', 'email', 'url', 'message', 'form' ) );
		$clean   = array_map( 'strval', array_intersect_key( array_filter( $data, 'is_scalar' ), $allowed ) );
		if ( array_key_exists( 'fields', $data ) ) {
			$clean['fields'] = is_array( $data['fields'] ) ? Fields::from_request( $data['fields'] ) : null;
		}
		$verdict = Engine::check( new Submission( (string) $context, $clean ) );
		$result  = $verdict->to_array();
		// "spam" follows the setting for suspicious submissions, like ShieldCraft's own forms.
		$result['spam'] = $verdict->is_spam() || ( Verdict::SUSPICIOUS === $verdict->verdict && 'block' === Settings::get( 'forms_suspicious' ) );
		return $result;
	}
}

if ( ! function_exists( 'shieldcraft_is_spam' ) ) {
	/**
	 * Whether a submission should be refused.
	 *
	 * @param string $context Context.
	 * @param array  $data    See shieldcraft_check().
	 * @return bool
	 */
	function shieldcraft_is_spam( $context = 'api', array $data = array() ) {
		return shieldcraft_check( $context, $data )['spam'];
	}
}
