<?php
/**
 * Sender and message from a form plugin's fields.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Integrations;

use ShieldCraft\Areas\Guard;
use ShieldCraft\Engine\Fields;
use ShieldCraft\Engine\Submission;
use ShieldCraft\Settings;

/**
 * Form plugins name their fields freely, so the sender is guessed: the first email address, the
 * fields whose name looks like a name, and every other text as the message.
 */
final class Form_Data {

	/**
	 * Submission data from field values.
	 *
	 * @param array $values Field name or label => value.
	 * @return array { name, email, url, message }
	 */
	public static function from_values( array $values ) {
		$out   = array(
			'name'    => '',
			'email'   => '',
			'url'     => '',
			'message' => '',
		);
		$names = array();
		$texts = array();
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_filter( array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) );
			}
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$key   = strtolower( (string) $key );
			$value = trim( (string) $value );
			if ( '' === $value || 0 === strpos( $key, 'shieldcraft_' ) || 0 === strpos( $key, '_' ) ) {
				continue;
			}
			if ( '' === $out['email'] && is_email( $value ) ) {
				$out['email'] = $value;
			} elseif ( '' === $out['url'] && preg_match( '~^https?://\S+$~i', $value ) && preg_match( '/url|website|site|web/', $key ) ) {
				$out['url'] = $value;
			} elseif ( preg_match( '/name/', $key ) && strlen( $value ) <= 100 ) {
				$names[] = $value;
			} else {
				$texts[] = $value;
			}
		}
		$out['name']    = implode( ' ', $names );
		$out['message'] = implode( "\n", $texts );
		return $out;
	}

	/**
	 * Check a form plugin's submission.
	 *
	 * @param string     $context Context.
	 * @param string     $form    Form ID.
	 * @param array      $values  Field values.
	 * @param array|null $source  Where the hidden fields are (default $_POST).
	 * @return string Error message, or ''.
	 */
	public static function check( $context, $form, array $values, $source = null ) {
		$data           = self::from_values( $values );
		$data['form']   = (string) $form;
		$data['fields'] = Fields::from_request( $source );
		return Guard::check( new Submission( $context, $data ) );
	}

	/**
	 * Whether protection for a form plugin is on.
	 *
	 * @param string $key Setting.
	 * @return bool
	 */
	public static function enabled( $key ) {
		return (bool) Settings::get( $key );
	}
}
