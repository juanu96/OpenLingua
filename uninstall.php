<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Data is intentionally preserved. Define OPENLINGUA_REMOVE_DATA before uninstalling to delete it.
if ( ! defined( 'OPENLINGUA_REMOVE_DATA' ) || ! OPENLINGUA_REMOVE_DATA ) { return; }
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openlingua_translations" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openlingua_strings" );
delete_option( 'openlingua_languages' );
delete_option( 'openlingua_default_language' );
delete_option( 'openlingua_db_version' );

