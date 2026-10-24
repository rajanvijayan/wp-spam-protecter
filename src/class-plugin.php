<?php
/**
 * Plugin bootstrap.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft;

/**
 * Wires the plugin into WordPress.
 */
final class Plugin {

	/**
	 * Whether boot() ran.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Register hooks.
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		Installer::register();
		Front\Assets::register();
		Log\Privacy::register();
		Areas\Comments::register();
		Areas\Account::register();
		add_action( 'plugins_loaded', array( Integrations\WooCommerce::class, 'register' ), 20 );
		add_action( 'rest_api_init', array( Rest\Token_Controller::class, 'register_routes' ) );

		/**
		 * Fires when ShieldCraft has registered its hooks (for extensions).
		 */
		do_action( 'shieldcraft_loaded' );
	}
}
