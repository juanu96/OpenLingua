<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Translations {
	private static $rows = array();
	private static $groups = array();

	public static function assign( $element_type, $element_id, $language, $group_uuid = '', $source_language = '' ) {
		global $wpdb;
		$language = sanitize_key( $language );
		if ( ! Languages::is_valid( $language ) ) {
			return new \WP_Error( 'openlingua_invalid_language', __( 'Invalid language.', 'openlingua' ) );
		}
		$group_uuid = $group_uuid ?: wp_generate_uuid4();
		$table      = Database::table( 'translations' );
		$existing   = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE element_type = %s AND element_id = %d', $table, $element_type, $element_id ) );
		$previous   = $existing ? self::row( $element_type, $element_id ) : null;
		$data       = array(
			'group_uuid'     => sanitize_text_field( $group_uuid ),
			'element_type'   => sanitize_key( $element_type ),
			'element_id'     => absint( $element_id ),
			'language'       => $language,
			'source_language'=> sanitize_key( $source_language ),
		);
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => absint( $existing ) ) );
		} else {
			$wpdb->insert( $table, $data );
		}
		self::invalidate( $element_type, $element_id, $previous ? $previous->group_uuid : '' );
		self::invalidate( $element_type, $element_id, $group_uuid );
		return $wpdb->last_error ? new \WP_Error( 'openlingua_db_error', $wpdb->last_error ) : $group_uuid;
	}

	public static function row( $element_type, $element_id ) {
		global $wpdb;
		$key = sanitize_key( $element_type ) . ':' . absint( $element_id );
		if ( array_key_exists( $key, self::$rows ) ) { return self::$rows[ $key ]; }
		$cached = wp_cache_get( $key, 'openlingua_rows', false, $found );
		if ( $found ) { self::$rows[ $key ] = $cached ?: null; return self::$rows[ $key ]; }
		$table = Database::table( 'translations' );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE element_type = %s AND element_id = %d', $table, $element_type, $element_id ) );
		self::$rows[ $key ] = $row;
		wp_cache_set( $key, $row ?: false, 'openlingua_rows' );
		return $row;
	}

	public static function group( $element_type, $element_id ) {
		global $wpdb;
		$row = self::row( $element_type, $element_id );
		if ( ! $row ) {
			return array();
		}
		$cache_key = sanitize_key( $element_type ) . ':' . $row->group_uuid;
		if ( isset( self::$groups[ $cache_key ] ) ) { return self::$groups[ $cache_key ]; }
		$table = Database::table( 'translations' );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT language, element_id FROM %i WHERE element_type = %s AND group_uuid = %s', $table, $element_type, $row->group_uuid ) );
		self::$groups[ $cache_key ] = wp_list_pluck( $rows, 'element_id', 'language' );
		return self::$groups[ $cache_key ];
	}

	public static function translated_id( $element_type, $element_id, $language ) {
		$group = self::group( $element_type, $element_id );
		return isset( $group[ $language ] ) ? absint( $group[ $language ] ) : 0;
	}

	public static function delete( $element_type, $element_id ) {
		global $wpdb;
		$row = self::row( $element_type, $element_id );
		$wpdb->delete( Database::table( 'translations' ), array( 'element_type' => $element_type, 'element_id' => absint( $element_id ) ) );
		self::invalidate( $element_type, $element_id, $row ? $row->group_uuid : '' );
	}

	private static function invalidate( $element_type, $element_id, $group_uuid = '' ) {
		$key = sanitize_key( $element_type ) . ':' . absint( $element_id );
		unset( self::$rows[ $key ] );
		wp_cache_delete( $key, 'openlingua_rows' );
		if ( $group_uuid ) { unset( self::$groups[ sanitize_key( $element_type ) . ':' . $group_uuid ] ); }
	}
}
