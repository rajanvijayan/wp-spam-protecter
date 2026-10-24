<?php
/**
 * Activation, upgrades and the daily clean-up.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft;

use ShieldCraft\Log\Log;

/**
 * Creates the log table and the site secret, and keeps them current on every site of a network
 * (each site is upgraded the first time ShieldCraft runs there).
 */
final class Installer {

	const DB_VERSION        = 1;
	const DB_VERSION_OPTION = 'shieldcraft_db_version';
	const SECRET_OPTION     = 'shieldcraft_secret';
	const CRON              = 'shieldcraft_daily';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'init', array( self::class, 'maybe_upgrade' ), 1 );
		add_action( self::CRON, array( Log::class, 'purge' ) );
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		self::install();
	}

	/**
	 * Deactivation: stop the daily clean-up (the data stays until uninstall).
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Install or upgrade when the stored version is older.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::DB_VERSION_OPTION ) < self::DB_VERSION || ! wp_next_scheduled( self::CRON ) ) {
			self::install();
		}
	}

	/**
	 * Table, secret, default settings and the daily clean-up.
	 */
	public static function install() {
		Log::create_table();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		self::secret();
		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Site secret used to sign tokens and to hash IP and email addresses. Not autoloaded into
	 * anything public; regenerate it by deleting the option.
	 *
	 * @return string
	 */
	public static function secret() {
		$secret = get_option( self::SECRET_OPTION );
		if ( ! is_string( $secret ) || strlen( $secret ) < 32 ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( self::SECRET_OPTION, $secret, false );
		}
		return $secret;
	}
}
