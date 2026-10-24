<?php
/**
 * Uninstall: remove ShieldCraft's options, log table, comment notes and scheduled clean-up.
 *
 * @package ShieldCraft
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove ShieldCraft's data from the current site.
 */
function shieldcraft_uninstall_site() {
	global $wpdb;
	foreach ( array( 'shieldcraft_settings', 'shieldcraft_secret', 'shieldcraft_db_version', 'shieldcraft_allowed_senders', 'shieldcraft_blocked_senders' ) as $shieldcraft_option ) {
		delete_option( $shieldcraft_option );
	}
	wp_clear_scheduled_hook( 'shieldcraft_daily' );
	delete_metadata( 'comment', 0, '_shieldcraft', '', true );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}shieldcraft_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table name.
	// Rate limit and duplicate counters expire on their own; remove the stored ones now.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_shieldcraft\_%' OR option_name LIKE '\_transient\_timeout\_shieldcraft\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $shieldcraft_site ) {
		switch_to_blog( $shieldcraft_site );
		shieldcraft_uninstall_site();
		restore_current_blog();
	}
} else {
	shieldcraft_uninstall_site();
}
