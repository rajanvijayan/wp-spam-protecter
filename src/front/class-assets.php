<?php
/**
 * Front-end script.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Front;

/**
 * One small script, without jQuery, loaded only on pages with a protected form. It swaps the
 * printed tokens for fresh ones and adds the proof that it ran.
 */
final class Assets {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'init', array( self::class, 'register_assets' ) );
	}

	/**
	 * Data written by wp-scripts next to each build.
	 *
	 * @param string $name Entry.
	 * @return array { dependencies, version }
	 */
	public static function asset( $name ) {
		$file = SHIELDCRAFT_DIR . 'build/' . $name . '.asset.php';
		$data = file_exists( $file ) ? require $file : array();
		return array(
			'dependencies' => isset( $data['dependencies'] ) ? $data['dependencies'] : array(),
			'version'      => isset( $data['version'] ) ? $data['version'] : SHIELDCRAFT_VERSION,
		);
	}

	/**
	 * Register the script.
	 */
	public static function register_assets() {
		$asset = self::asset( 'frontend' );
		wp_register_script(
			'shieldcraft',
			SHIELDCRAFT_URL . 'build/frontend.js',
			$asset['dependencies'],
			$asset['version'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Enqueue the script (once) with its settings.
	 */
	public static function enqueue() {
		if ( ! did_action( 'init' ) ) {
			return;
		}
		if ( ! wp_script_is( 'shieldcraft', 'registered' ) ) {
			self::register_assets();
		}
		if ( wp_script_is( 'shieldcraft', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_script( 'shieldcraft' );
		wp_add_inline_script(
			'shieldcraft',
			'window.shieldcraftConfig = ' . wp_json_encode( array( 'tokenUrl' => rest_url( 'shieldcraft/v1/token' ) ) ) . ';',
			'before'
		);
	}
}
