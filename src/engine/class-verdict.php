<?php
/**
 * Result of a check.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Engine;

/**
 * Allow, suspicious or spam, with the reasons. The worst reason decides.
 */
final class Verdict {

	const ALLOW      = 'allow';
	const SUSPICIOUS = 'suspicious';
	const SPAM       = 'spam';

	/**
	 * Verdict.
	 *
	 * @var string
	 */
	public $verdict = self::ALLOW;

	/**
	 * Reasons: [ [ 'check' => id, 'verdict' => ..., 'detail' => text ] ].
	 *
	 * @var array[]
	 */
	public $reasons = array();

	/**
	 * Log row ID, once logged.
	 *
	 * @var int
	 */
	public $log_id = 0;

	/**
	 * Add a reason.
	 *
	 * @param string $check   Check ID.
	 * @param string $verdict suspicious or spam.
	 * @param string $detail  Detail for the log.
	 * @return $this
	 */
	public function add( $check, $verdict, $detail = '' ) {
		$this->reasons[] = array(
			'check'   => (string) $check,
			'verdict' => $verdict,
			'detail'  => (string) $detail,
		);
		if ( self::SPAM === $verdict || ( self::SUSPICIOUS === $verdict && self::ALLOW === $this->verdict ) ) {
			$this->verdict = $verdict;
		}
		return $this;
	}

	/**
	 * Whether it is spam.
	 *
	 * @return bool
	 */
	public function is_spam() {
		return self::SPAM === $this->verdict;
	}

	/**
	 * Whether it passed.
	 *
	 * @return bool
	 */
	public function is_allowed() {
		return self::ALLOW === $this->verdict;
	}

	/**
	 * Check IDs, for the log ("honeypot,links").
	 *
	 * @return string
	 */
	public function codes() {
		return implode( ',', array_unique( wp_list_pluck( $this->reasons, 'check' ) ) );
	}

	/**
	 * Array form (API responses).
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'verdict' => $this->verdict,
			'reasons' => $this->reasons,
		);
	}
}
