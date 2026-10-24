<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       http://wpsmartcare.com
 * @since      1.0.0
 *
 * @package    Wp_Spam_Protecter
 * @subpackage Wp_Spam_Protecter/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Wp_Spam_Protecter
 * @subpackage Wp_Spam_Protecter/includes
 * @author     Rajan V <help@wpsmartcare.com>
 */
class Wp_Spam_Protecter_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'wp-spam-protecter',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
