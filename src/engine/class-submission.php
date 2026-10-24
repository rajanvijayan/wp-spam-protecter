<?php
/**
 * One form submission to check.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Engine;

/**
 * What the checks look at. Integrations fill in the sender and message; the hidden fields are read
 * from the request unless given.
 */
final class Submission {

	/**
	 * Context ("comment", "registration", "cf7"...).
	 *
	 * @var string
	 */
	public $context;

	/**
	 * Form identifier within the context (form ID, post ID), for the log.
	 *
	 * @var string
	 */
	public $form = '';

	/**
	 * Sender name.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Sender email.
	 *
	 * @var string
	 */
	public $email = '';

	/**
	 * Sender website.
	 *
	 * @var string
	 */
	public $url = '';

	/**
	 * Message text (comment, form fields joined).
	 *
	 * @var string
	 */
	public $message = '';

	/**
	 * Sender IP.
	 *
	 * @var string
	 */
	public $ip = '';

	/**
	 * Logged-in user ID (0 for visitors).
	 *
	 * @var int
	 */
	public $user_id = 0;

	/**
	 * Hidden fields: token, proof (js) and honeypot. Null when the form had none of them.
	 *
	 * @var array|null
	 */
	public $fields = null;

	/**
	 * Whether the form is a kind without ShieldCraft's fields (trackbacks): only content rules run.
	 *
	 * @var bool
	 */
	public $content_only = false;

	/**
	 * Submission.
	 *
	 * @param string $context Context.
	 * @param array  $data    Any of the public properties.
	 */
	public function __construct( $context, array $data = array() ) {
		$this->context = (string) $context;
		$this->ip      = Request::ip();
		$this->user_id = get_current_user_id();
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) && 'context' !== $key ) {
				$this->$key = $value;
			}
		}
		if ( null === $this->fields && ! $this->content_only ) {
			$this->fields = Fields::from_request();
		}
	}
}
