<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://wpsmartcare.com
 * @since      1.0.0
 *
 * @package    Wp_Spam_Protecter
 * @subpackage Wp_Spam_Protecter/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Wp_Spam_Protecter
 * @subpackage Wp_Spam_Protecter/public
 * @author     Rajan V <help@wpsmartcare.com>
 */
class Wp_Spam_Protecter_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $wsp    The ID of this plugin.
	 */
	private $wsp;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $wsp       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $wsp, $version ) {

		$this->wsp = $wsp;
		$this->version = $version;

	}

	public function comment_form_after_fields() {

		echo '<input name="'.$this->wsp.'-year" type="hidden" value="'. date('Y') .'" autocomplete="off" />';

		echo '<input name="'.$this->wsp.'-email-url-website" type="hidden" />';

	}

	public function comment_post( $comment_id ){

		if( $this->is_spam() ){
			wp_set_comment_status( $comment_id, 'spam' );
		}

	}

	public function is_spam() {

		if ( ( isset( $_POST[$this->wsp.'-year'] ) ) && ( $_POST[$this->wsp.'-email-url-website'] == '' ) ){
			
			$year = trim($_POST[$this->wsp.'-year']);
			$euw = trim($_POST[$this->wsp.'-email-url-website']);

			if( $year != date('Y') ){
				return true;
			}

			return false;

		}

		return true;
	}

}
