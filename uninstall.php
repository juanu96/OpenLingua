<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Data is intentionally preserved. Define OPENLINGUA_REMOVE_DATA before uninstalling to delete it.
if ( ! defined( 'OPENLINGUA_REMOVE_DATA' ) || ! OPENLINGUA_REMOVE_DATA ) { return; }
global $wpdb;

$openlingua_remove_site_data = static function () use ( $wpdb ) {
	foreach ( array( 'translations', 'strings', 'jobs', 'memory' ) as $openlingua_table_suffix ) {
		$openlingua_table = $wpdb->prefix . 'openlingua_' . $openlingua_table_suffix;
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $openlingua_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
	foreach ( array(
		'openlingua_languages', 'openlingua_default_language', 'openlingua_db_version',
		'openlingua_completed_migrations', 'openlingua_menu_map', 'openlingua_meta_policies',
		'openlingua_string_discovery', 'openlingua_language_settings', 'openlingua_custom_languages',
		'openlingua_flush_rewrite_rules', 'openlingua_active_translation_provider',
		'openlingua_openai_settings', 'openlingua_anthropic_settings', 'openlingua_gemini_settings',
		'openlingua_google_translate_settings',
	) as $openlingua_option ) {
		delete_option( $openlingua_option );
	}
	foreach ( array( 'openlingua_openai_models', 'openlingua_anthropic_models', 'openlingua_gemini_models' ) as $openlingua_transient ) {
		delete_transient( $openlingua_transient );
	}
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( '_openlingua_memory_imported_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	foreach ( array( '_openlingua_translation_status', '_openlingua_source_hash', '_openlingua_divi_source_snapshot' ) as $openlingua_meta_key ) {
		delete_post_meta_by_key( $openlingua_meta_key );
	}
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key IN (%s,%s,%s)', $wpdb->usermeta, '_openlingua_admin_content_language', '_openlingua_nav_menu_language', '_openlingua_completed_jobs' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	wp_clear_scheduled_hook( 'openlingua_run_translation_job' );
};

if ( is_multisite() && is_network_admin() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $openlingua_site_id ) {
		switch_to_blog( $openlingua_site_id );
		$openlingua_remove_site_data();
		restore_current_blog();
	}
} else {
	$openlingua_remove_site_data();
}

foreach ( array( 'administrator', 'editor' ) as $openlingua_role_name ) {
	$openlingua_role = get_role( $openlingua_role_name );
	if ( $openlingua_role ) { $openlingua_role->remove_cap( 'openlingua_translate' ); }
}
