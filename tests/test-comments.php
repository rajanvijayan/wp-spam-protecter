<?php
/**
 * Comments, reviews, trackbacks.
 *
 * @package ShieldCraft
 */

use ShieldCraft\Areas\Comments;
use ShieldCraft\Settings;

/**
 * Comments.
 */
class Test_Comments extends ShieldCraft_TestCase {

	/**
	 * Post to comment on.
	 *
	 * @var int
	 */
	private $post;

	public function set_up() {
		parent::set_up();
		$this->post = self::factory()->post->create();
		update_option( 'comment_moderation', 0 );
		update_option( 'comment_previously_approved', 0 );
		update_option( 'require_name_email', 1 );
		Settings::save( array( 'duplicate' => false ) );
		// Core's flood check would stop the second comment of a test.
		remove_all_filters( 'comment_flood_filter' );
		Comments::reset();
	}

	/**
	 * Send the comment form, as wp-comments-post.php does.
	 *
	 * @param array $fields  Hidden fields.
	 * @param array $comment Comment fields.
	 * @return WP_Comment|WP_Error
	 */
	private function submit( array $fields, array $comment = array() ) {
		$_POST = array_merge(
			array(
				'comment_post_ID' => $this->post,
				'author'          => 'Ann',
				'email'           => 'ann@example.org',
				'url'             => '',
				'comment'         => 'A thoughtful reply about the article ' . wp_generate_password( 6, false ),
			),
			$comment,
			$fields
		);
		return wp_handle_comment_submission( wp_unslash( $_POST ) );
	}

	/**
	 * The comment form markup.
	 *
	 * @param int $post Post ID.
	 * @return string
	 */
	private function form( $post ) {
		ob_start();
		comment_form( array(), $post );
		return ob_get_clean();
	}

	public function test_fields_are_in_the_form_for_visitors_and_logged_in_users() {
		$this->assertStringContainsString( 'name="shieldcraft_token"', $this->form( $this->post ) );
		$this->assertStringContainsString( 'data-shieldcraft="comment"', $this->form( $this->post ) );
		// 1.0 printed its fields in comment_form_after_fields, which logged-in users never get.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertStringContainsString( 'name="shieldcraft_token"', $this->form( $this->post ) );
		$this->assertTrue( wp_script_is( 'shieldcraft', 'enqueued' ) );
	}

	public function test_a_persons_comment_is_published() {
		$comment = $this->submit( $this->human_fields( 'comment' ) );
		$this->assertInstanceOf( 'WP_Comment', $comment );
		$this->assertSame( '1', $comment->comment_approved );
		$this->assertSame( '', get_comment_meta( $comment->comment_ID, Comments::META, true ) );
	}

	public function test_a_bot_posting_directly_is_marked_spam_and_logged() {
		$comment = $this->submit( array() );
		$this->assertSame( 'spam', $comment->comment_approved );
		$meta = get_comment_meta( $comment->comment_ID, Comments::META, true );
		$this->assertSame( 'no_fields', $meta['reasons'] );
		$rows = $this->log_rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( (string) $comment->comment_ID, $rows[0]['object_id'] );
		$this->assertSame( 'spam', $rows[0]['status'] );
		$this->assertSame( (string) $this->post, $rows[0]['form'] );
	}

	public function test_logged_in_admins_are_never_marked_spam() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$comment = $this->submit( array() );
		$this->assertNotSame( 'spam', $comment->comment_approved );
		$this->assertSame( array(), $this->log_rows() );
	}

	public function test_without_javascript_the_comment_is_held() {
		$comment = $this->submit( $this->human_fields( 'comment', 20, false ) );
		$this->assertSame( '0', $comment->comment_approved );
		$this->assertCount( 1, $this->log_rows(), 'Checked and logged once, although WordPress asks twice.' );
		$this->assertSame( 'held', $this->log_rows()[0]['status'] );

		Settings::save( array( 'comments_suspicious' => 'allow' ) );
		$this->assertSame( '1', $this->submit( $this->human_fields( 'comment', 20, false ) )->comment_approved );
	}

	public function test_reject_instead_of_spam_folder() {
		Settings::save( array( 'comments_spam' => 'reject' ) );
		$result = $this->submit( array() );
		$this->assertWPError( $result );
		$this->assertSame( 'shieldcraft_spam', $result->get_error_code() );
	}

	public function test_comments_added_by_code_are_not_checked() {
		$id = wp_new_comment(
			array(
				'comment_post_ID'      => $this->post,
				'comment_author'       => 'System',
				'comment_author_email' => 'system@example.org',
				'comment_author_url'   => '',
				'comment_content'      => 'Added by another plugin.',
			)
		);
		$this->assertSame( '1', get_comment( $id )->comment_approved );
	}

	public function test_trackbacks() {
		$ping = array(
			'comment_post_ID'      => $this->post,
			'comment_author'       => 'Other blog',
			'comment_author_email' => '',
			'comment_author_url'   => 'https://other.example/post',
			'comment_content'      => 'Linking here https://a.example https://b.example https://c.example',
			'comment_type'         => 'trackback',
		);
		$this->assertSame( 'spam', get_comment( wp_new_comment( $ping ) )->comment_approved, 'Too many links.' );

		$ping['comment_content'] = 'A friendly mention of your post.';
		$this->assertSame( '1', get_comment( wp_new_comment( $ping ) )->comment_approved, 'Only content rules run; no hidden fields needed.' );

		Settings::save( array( 'trackbacks' => 'block' ) );
		$ping['comment_content'] = 'Another friendly mention.';
		$this->assertSame( 'spam', get_comment( wp_new_comment( $ping ) )->comment_approved );
		$rows = $this->log_rows();
		$this->assertSame( 'trackback', end( $rows )['reasons'] );
	}

	public function test_product_reviews_have_their_own_context() {
		if ( ! post_type_exists( 'product' ) ) {
			register_post_type(
				'product',
				array(
					'public'   => true,
					'supports' => array( 'title', 'editor', 'comments' ),
				)
			);
		}
		$product = self::factory()->post->create( array( 'post_type' => 'product' ) );
		$this->assertStringContainsString( 'data-shieldcraft="review"', $this->form( $product ) );
		$this->post = $product;
		$this->assertSame( 'spam', $this->submit( $this->human_fields( 'comment' ) )->comment_approved, 'A comment token is not valid for reviews.' );
		$this->assertSame( '1', $this->submit( $this->human_fields( 'review' ) )->comment_approved );
	}
}
