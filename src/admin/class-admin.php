<?php
/**
 * Settings page, dashboard widget, comment notes and the plugins screen link.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Admin;

use ShieldCraft\Areas\Comments;
use ShieldCraft\Engine\Engine;
use ShieldCraft\Front\Assets;
use ShieldCraft\Integrations\Form_Plugins;
use ShieldCraft\Log\Log;
use ShieldCraft\Settings;
use WP_Comment;

/**
 * Settings → ShieldCraft loads the admin app (assets/src/settings), which talks to the routes in
 * Rest\Admin_Controller.
 */
final class Admin {

	const SLUG = 'shieldcraft';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_dashboard_setup', array( self::class, 'dashboard_widget' ) );
		add_filter( 'comment_row_actions', array( self::class, 'comment_note' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( SHIELDCRAFT_FILE ), array( self::class, 'action_links' ) );
	}

	/**
	 * Page URL.
	 *
	 * @param string $tab Tab.
	 * @return string
	 */
	public static function url( $tab = '' ) {
		return admin_url( 'options-general.php?page=' . self::SLUG ) . ( $tab ? '#' . $tab : '' );
	}

	/**
	 * Settings → ShieldCraft.
	 */
	public static function menu() {
		add_options_page(
			__( 'ShieldCraft', 'wp-spam-protecter' ),
			__( 'ShieldCraft', 'wp-spam-protecter' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Page markup: the app mounts here.
	 */
	public static function render() {
		echo '<div class="wrap"><div id="shieldcraft-admin"><p>' . esc_html__( 'Loading…', 'wp-spam-protecter' ) . '</p></div></div>';
	}

	/**
	 * App scripts, on the page only.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function enqueue( $hook ) {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		$asset = Assets::asset( 'admin' );
		wp_enqueue_script( 'shieldcraft-admin', SHIELDCRAFT_URL . 'build/admin.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'shieldcraft-admin', SHIELDCRAFT_URL . 'build/admin.css', array( 'wp-components' ), $asset['version'] );
		wp_set_script_translations( 'shieldcraft-admin', 'wp-spam-protecter' );
		wp_add_inline_script( 'shieldcraft-admin', 'window.shieldcraftAdmin = ' . wp_json_encode( self::config() ) . ';', 'before' );
	}

	/**
	 * Data for the app.
	 *
	 * @return array
	 */
	public static function config() {
		$roles = array();
		foreach ( wp_roles()->get_names() as $role => $name ) {
			$roles[] = array(
				'value' => $role,
				'label' => translate_user_role( $name ),
			);
		}
		$headers = array();
		// What each address source holds for the current request, to help choose the right one.
		foreach ( Settings::CHOICES['ip_header'] as $header ) {
			$key             = '' === $header ? 'REMOTE_ADDR' : $header;
			$headers[ $key ] = isset( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) : '';
		}
		$integrations = Form_Plugins::supported();
		return array(
			'version'      => SHIELDCRAFT_VERSION,
			'contexts'     => Engine::contexts(),
			'reasons'      => Reasons::labels(),
			'roles'        => $roles,
			'scripts'      => Settings::SCRIPTS,
			'headers'      => $headers,
			'woocommerce'  => class_exists( 'WooCommerce' ),
			'multisite'    => is_multisite(),
			'integrations' => $integrations,
			'disabled'     => defined( 'SHIELDCRAFT_DISABLE' ) && SHIELDCRAFT_DISABLE,
			'discussion'   => admin_url( 'options-discussion.php' ),
		);
	}

	/**
	 * Dashboard widget with the week's numbers.
	 */
	public static function dashboard_widget() {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'shieldcraft', __( 'ShieldCraft', 'wp-spam-protecter' ), array( self::class, 'render_widget' ) );
		}
	}

	/**
	 * Widget markup.
	 */
	public static function render_widget() {
		$stats    = Log::stats( 7 );
		$contexts = Engine::contexts();
		echo '<p>' . esc_html(
			sprintf(
				/* translators: %d: number of submissions. */
				_n( '%d submission stopped or held in the last 7 days.', '%d submissions stopped or held in the last 7 days.', $stats['total'], 'wp-spam-protecter' ),
				$stats['total']
			)
		) . '</p>';
		if ( $stats['total'] ) {
			echo '<ul>';
			foreach ( array_slice( (array) $stats['contexts'], 0, 5, true ) as $context => $count ) {
				printf( '<li>%s: <strong>%d</strong></li>', esc_html( isset( $contexts[ $context ] ) ? $contexts[ $context ] : $context ), (int) $count );
			}
			echo '</ul>';
		}
		printf( '<p><a href="%s">%s</a></p>', esc_url( self::url( 'log' ) ), esc_html__( 'Open the spam log', 'wp-spam-protecter' ) );
	}

	/**
	 * Why ShieldCraft held or marked a comment, in its row on the comments screen.
	 *
	 * @param string[]   $actions Row actions.
	 * @param WP_Comment $comment Comment.
	 * @return string[]
	 */
	public static function comment_note( $actions, $comment ) {
		$meta = $comment instanceof WP_Comment ? get_comment_meta( $comment->comment_ID, Comments::META, true ) : '';
		if ( is_array( $meta ) && ! empty( $meta['reasons'] ) && '1' !== $comment->comment_approved ) {
			$actions['shieldcraft'] = '<span class="shieldcraft-note">' . esc_html(
				sprintf(
					/* translators: %s: reasons. */
					__( 'ShieldCraft: %s', 'wp-spam-protecter' ),
					Reasons::describe( $meta['reasons'] )
				)
			) . '</span>';
		}
		return $actions;
	}

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'wp-spam-protecter' ) . '</a>' );
		return $links;
	}
}
