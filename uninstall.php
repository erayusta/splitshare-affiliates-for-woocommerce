<?php
/**
 * Kaldırma: veriler yalnızca wp-config.php'de SSA_REMOVE_DATA true ise silinir.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'SSA_REMOVE_DATA' ) || ! SSA_REMOVE_DATA ) {
	return;
}

global $wpdb;
foreach ( array( 'ssa_partners', 'ssa_commissions', 'ssa_payouts', 'ssa_clicks' ) as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . $table . '`' ); // phpcs:ignore WordPress.DB
}
delete_option( 'ssa_settings' );
delete_option( 'ssa_db_version' );
remove_role( 'ssa_partner' );
wp_clear_scheduled_hook( 'ssa_daily' );
wp_clear_scheduled_hook( 'ssa_monthly' );
