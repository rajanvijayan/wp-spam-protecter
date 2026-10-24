<?php
/**
 * Comments, product reviews, trackbacks and pingbacks.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Areas;

use ShieldCraft\Engine\Engine;
use ShieldCraft\Engine\Fields;
use ShieldCraft\Engine\Submission;
use ShieldCraft\Engine\Verdict;
use ShieldCraft\Log\Log;
use ShieldCraft\Settings;
use WP_Error;

/**
 * The fields go in through the comment_form action, which runs for visitors and logged-in users
 * alike (1.0 used comment_form_after_fields, which logged-in users never get, so their comments
 * were all marked spam). Only comments sent through the comment form are checked; comments that
 * plugins add with wp_new_comment() are left alone.
 */
final class Comments {

	const META = '_shieldcraft';

	/**
	 * Whether the current request is a comment form submission.
	 *
	 * @var bool
	 */
	private static $from_form = false;

	/**
	 * Verdict of the comment being saved.
	 *
	 * @var Verdict|null
	 */
	private static $verdict = null;

	/**
	 * Comment the verdict belongs to (WordPress checks a comment twice: before and after its
	 * filters run).
	 *
	 * @var string
	 */
	private static $verdict_for = '';

	/**
	 * Hooks.
	 */
	public static function register() {
		if ( ! Settings::get( 'comments' ) ) {
			return;
		}
		add_action( 'comment_form', array( self::class, 'fields' ) );
		add_action( 'pre_comment_on_post', array( self::class, 'from_form' ) );
		add_filter( 'pre_comment_approved', array( self::class, 'check' ), 20, 2 );
		add_action( 'comment_post', array( self::class, 'saved' ), 10, 2 );
	}

	/**
	 * Context of comments on a post: product reviews or comments.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function context( $post_id ) {
		return 'product' === get_post_type( $post_id ) ? 'review' : 'comment';
	}

	/**
	 * Print the hidden fields in the comment form.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function fields( $post_id ) {
		echo Fields::render( self::context( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Fields::render().
	}

	/**
	 * The comment form is being submitted (wp-comments-post.php).
	 */
	public static function from_form() {
		self::$from_form = true;
	}

	/**
	 * Check a new comment.
	 *
	 * @param int|string|WP_Error $approved    Approval status so far.
	 * @param array               $commentdata Comment data.
	 * @return int|string|WP_Error
	 */
	public static function check( $approved, $commentdata ) {
		if ( is_wp_error( $approved ) || in_array( $approved, array( 'spam', 'trash' ), true ) ) {
			return $approved;
		}
		$type    = isset( $commentdata['comment_type'] ) ? $commentdata['comment_type'] : '';
		$is_ping = in_array( $type, array( 'pingback', 'trackback' ), true );
		if ( ! $is_ping && ! self::$from_form ) {
			return $approved;
		}
		$post_id = isset( $commentdata['comment_post_ID'] ) ? (int) $commentdata['comment_post_ID'] : 0;
		$email   = isset( $commentdata['comment_author_email'] ) ? (string) $commentdata['comment_author_email'] : '';
		$key     = $post_id . '|' . $type . '|' . strtolower( $email );
		if ( self::$verdict && self::$verdict_for === $key ) {
			return self::result( self::$verdict, $approved, $is_ping );
		}

		$submission = new Submission(
			$is_ping ? 'trackback' : self::context( $post_id ),
			array(
				'form'         => (string) $post_id,
				'name'         => isset( $commentdata['comment_author'] ) ? (string) $commentdata['comment_author'] : '',
				'email'        => $email,
				'url'          => isset( $commentdata['comment_author_url'] ) ? (string) $commentdata['comment_author_url'] : '',
				'message'      => isset( $commentdata['comment_content'] ) ? (string) $commentdata['comment_content'] : '',
				'user_id'      => isset( $commentdata['user_id'] ) ? (int) $commentdata['user_id'] : 0,
				'content_only' => $is_ping,
			)
		);

		if ( $is_ping && 'allow' === Settings::get( 'trackbacks' ) ) {
			return $approved;
		}
		if ( $is_ping && 'block' === Settings::get( 'trackbacks' ) ) {
			$verdict         = ( new Verdict() )->add( 'trackback', Verdict::SPAM );
			$verdict->log_id = Log::insert( $submission, $verdict );
		} else {
			$verdict = Engine::check( $submission );
		}
		self::$verdict     = $verdict;
		self::$verdict_for = $key;
		return self::result( $verdict, $approved, $is_ping );
	}

	/**
	 * Approval status for a verdict, following the settings.
	 *
	 * @param Verdict             $verdict  Verdict.
	 * @param int|string|WP_Error $approved Approval status so far.
	 * @param bool                $is_ping  Whether it is a trackback or pingback.
	 * @return int|string|WP_Error
	 */
	private static function result( Verdict $verdict, $approved, $is_ping ) {
		if ( $verdict->is_spam() ) {
			if ( 'reject' === Settings::get( 'comments_spam' ) && ! $is_ping ) {
				self::reset();
				return new WP_Error( 'shieldcraft_spam', __( 'Your comment could not be posted because it looks like spam.', 'wp-spam-protecter' ), 403 );
			}
			return 'spam';
		}
		if ( Verdict::SUSPICIOUS === $verdict->verdict ) {
			$action = Settings::get( 'comments_suspicious' );
			if ( 'spam' === $action ) {
				return 'spam';
			}
			if ( 'hold' === $action ) {
				return 0;
			}
		}
		return $approved;
	}

	/**
	 * Forget the form submission once its comment is saved or refused, so comments that code
	 * adds later in the same request are not checked.
	 */
	public static function reset() {
		self::$from_form   = false;
		self::$verdict     = null;
		self::$verdict_for = '';
	}

	/**
	 * Link the log row to the saved comment and note the reasons on it.
	 *
	 * @param int        $comment_id Comment ID.
	 * @param int|string $approved   Approval status.
	 */
	public static function saved( $comment_id, $approved ) {
		$verdict = self::$verdict;
		self::reset();
		if ( ! $verdict || $verdict->is_allowed() ) {
			return;
		}
		Log::attach( $verdict->log_id, $comment_id, 'spam' === $approved ? 'spam' : ( 1 === (int) $approved ? 'approved' : 'held' ) );
		update_comment_meta(
			$comment_id,
			self::META,
			array(
				'verdict' => $verdict->verdict,
				'reasons' => $verdict->codes(),
				'log'     => $verdict->log_id,
			)
		);
	}
}
