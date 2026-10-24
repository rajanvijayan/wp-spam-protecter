<?php
/**
 * Plugin Name:       ShieldCraft – Invisible Spam Protection
 * Plugin URI:        https://wordpress.org/plugins/wp-spam-protecter/
 * Description:       Stops spam in comments, registration, WooCommerce and contact forms without CAPTCHAs or external services: invisible checks, rules and a spam log.
 * Version:           2.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Rajan V
 * Author URI:        https://profiles.wordpress.org/rajanit2000/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-spam-protecter
 *
 * @package ShieldCraft
 */

defined( 'ABSPATH' ) || exit;

define( 'SHIELDCRAFT_VERSION', '2.0.0' );
define( 'SHIELDCRAFT_FILE', __FILE__ );
define( 'SHIELDCRAFT_DIR', plugin_dir_path( __FILE__ ) );
define( 'SHIELDCRAFT_URL', plugin_dir_url( __FILE__ ) );

if ( ! file_exists( SHIELDCRAFT_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'ShieldCraft is missing its vendor folder. Run composer install, or install the plugin from WordPress.org.', 'wp-spam-protecter' ) . '</p></div>';
		}
	);
	return;
}

require SHIELDCRAFT_DIR . 'vendor/autoload.php';
require SHIELDCRAFT_DIR . 'src/api/functions.php';

register_activation_hook( __FILE__, array( 'ShieldCraft\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ShieldCraft\Installer', 'deactivate' ) );

ShieldCraft\Plugin::boot();
