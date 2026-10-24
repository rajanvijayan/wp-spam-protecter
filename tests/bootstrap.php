<?php
/**
 * PHPUnit bootstrap. Set WP_TESTS_DIR to the WordPress test library (bin/install-wp-tests.sh).
 * Optional plugins are loaded when their folder is given: WC_DIR (WooCommerce).
 *
 * @package ShieldCraft
 */

$shieldcraft_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $shieldcraft_tests_dir ) {
	$shieldcraft_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}
if ( ! file_exists( $shieldcraft_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $shieldcraft_tests_dir/includes/functions.php\n"; // phpcs:ignore
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $shieldcraft_tests_dir . '/includes/functions.php';

$shieldcraft_plugins = array();
$shieldcraft_wc_dir  = getenv( 'WC_DIR' );
if ( $shieldcraft_wc_dir && file_exists( $shieldcraft_wc_dir . '/woocommerce.php' ) ) {
	$shieldcraft_plugins[] = $shieldcraft_wc_dir . '/woocommerce.php';
}

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $shieldcraft_plugins ) {
		foreach ( $shieldcraft_plugins as $shieldcraft_plugin ) {
			require $shieldcraft_plugin;
		}
		require dirname( __DIR__ ) . '/wp-spam-protecter.php';
	}
);

tests_add_filter(
	'setup_theme',
	static function () {
		if ( class_exists( 'WC_Install' ) ) {
			WC_Install::install();
			$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wp_roles();
		}
		ShieldCraft\Installer::install();
	}
);

require $shieldcraft_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/class-testcase.php';
