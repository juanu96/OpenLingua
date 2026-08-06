<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Data is intentionally preserved. Define OPENLINGUA_REMOVE_DATA before uninstalling to delete it.
if ( ! defined( 'OPENLINGUA_REMOVE_DATA' ) || ! OPENLINGUA_REMOVE_DATA ) { return; }
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openlingua_translations" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openlingua_strings" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openlingua_jobs" );
delete_option( 'openlingua_languages' );
delete_option( 'openlingua_default_language' );
delete_option( 'openlingua_db_version' );
delete_option( 'openlingua_menu_map' );
delete_option( 'openlingua_meta_policies' );
delete_option( 'openlingua_string_discovery' );
delete_option( 'openlingua_flush_rewrite_rules' );
wp_clear_scheduled_hook( 'openlingua_run_translation_job' );
foreach ( array( 'administrator', 'editor' ) as $role_name ) {
	$role = get_role( $role_name );
	if ( $role ) { $role->remove_cap( 'openlingua_translate' ); }
}
