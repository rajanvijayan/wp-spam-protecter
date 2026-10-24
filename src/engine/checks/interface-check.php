<?php
/**
 * A spam check.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Engine\Checks;

use ShieldCraft\Engine\Submission;
use ShieldCraft\Engine\Verdict;

/**
 * Checks add reasons to the verdict; they never remove any.
 */
interface Check {

	/**
	 * Run the check.
	 *
	 * @param Submission $submission Submission.
	 * @param Verdict    $verdict    Verdict to add reasons to.
	 */
	public function run( Submission $submission, Verdict $verdict );
}
