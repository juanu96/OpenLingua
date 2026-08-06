<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Strings {
	public static function register( $key, $text, $domain = 'default', $source_language = '' ) {
		global $wpdb;
		$table = Database::table( 'strings' );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (domain,string_key,source_language,source_text,translations,updated_at) VALUES (%s,%s,%s,%s,%s,%s)
			ON DUPLICATE KEY UPDATE source_text = VALUES(source_text), updated_at = VALUES(updated_at)",
			sanitize_key( $domain ), sanitize_key( $key ), $source_language ?: Languages::default_code(), $text, '{}', current_time( 'mysql' )
		) );
		return $text;
	}

	public static function translate( $key, $fallback = '', $domain = 'default', $language = '' ) {
		global $wpdb;
		$table = Database::table( 'strings' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT source_text, translations FROM {$table} WHERE domain = %s AND string_key = %s", sanitize_key( $domain ), sanitize_key( $key ) ) );
		if ( ! $row ) { return self::register( $key, $fallback, $domain ); }
		$translations = json_decode( $row->translations, true );
		$language = $language ?: Languages::current();
		return ! empty( $translations[ $language ] ) ? $translations[ $language ] : $row->source_text;
	}
}

