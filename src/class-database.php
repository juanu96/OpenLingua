<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Database {
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'openlingua_' . $name;
	}

	public static function activate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$links   = self::table( 'translations' );
		$strings = self::table( 'strings' );

		dbDelta( "CREATE TABLE {$links} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_uuid varchar(36) NOT NULL,
			element_type varchar(32) NOT NULL,
			element_id bigint(20) unsigned NOT NULL,
			language varchar(20) NOT NULL,
			source_language varchar(20) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY element (element_type,element_id),
			UNIQUE KEY translation (group_uuid,element_type,language),
			KEY language (language)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$strings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			domain varchar(100) NOT NULL,
			string_key varchar(191) NOT NULL,
			source_language varchar(20) NOT NULL,
			source_text longtext NOT NULL,
			translations longtext NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY string_identity (domain,string_key)
		) {$charset};" );

		if ( ! get_option( 'openlingua_languages' ) ) {
			add_option( 'openlingua_languages', array(
				'en' => array( 'name' => 'English', 'locale' => 'en_US' ),
				'es' => array( 'name' => 'Español', 'locale' => 'es_ES' ),
			) );
			add_option( 'openlingua_default_language', 'en' );
		}
		update_option( 'openlingua_db_version', OPENLINGUA_VERSION );
	}
}

