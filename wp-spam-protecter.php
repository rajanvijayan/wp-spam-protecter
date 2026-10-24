<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://wpsmartcare.com
 * @since             1.0.0
 * @package           Wp_Spam_Protecter
 *
 * @wordpress-plugin
 * Plugin Name:       WP Spam Protecter
 * Plugin URI:        http://protecter.wpsmartcare.com
 * Description:       Protuct your blog about spam comments and instantaneously junk messages.
 * Version:           1.0.0
 * Author:            Rajan V
 * Author URI:        http://wpsmartcare.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-spam-protecter
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WSP_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wp-spam-protecter-activator.php
 */
function activate_wp_spam_protecter() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-spam-protecter-activator.php';
	Wp_Spam_Protecter_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wp-spam-protecter-deactivator.php
 */
function deactivate_wp_spam_protecter() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-spam-protecter-deactivator.php';
	Wp_Spam_Protecter_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_spam_protecter' );
register_deactivation_hook( __FILE__, 'deactivate_wp_spam_protecter' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wp-spam-protecter.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wp_spam_protecter() {

	$plugin = new Wp_Spam_Protecter();
	$plugin->run();

}
run_wp_spam_protecter();
