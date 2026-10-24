<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://wpsmartcare.com
 * @since      1.0.0
 *
 * @package    Wp_Spam_Protecter
 * @subpackage Wp_Spam_Protecter/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wp_Spam_Protecter
 * @subpackage Wp_Spam_Protecter/admin
 * @author     Rajan V <help@wpsmartcare.com>
 */
class Wp_Spam_Protecter_Admin {

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
	 * @param      string    $wsp       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $wsp, $version ) {

		$this->wsp = $wsp;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wp_Spam_Protecter_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wp_Spam_Protecter_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->wsp, plugin_dir_url( __FILE__ ) . 'css/wp-spam-protecter-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wp_Spam_Protecter_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wp_Spam_Protecter_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->wsp, plugin_dir_url( __FILE__ ) . 'js/wp-spam-protecter-admin.js', array( 'jquery' ), $this->version, false );

	}

}
