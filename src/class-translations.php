<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Translations {
	public static function assign( $element_type, $element_id, $language, $group_uuid = '', $source_language = '' ) {
		global $wpdb;
		$language = sanitize_key( $language );
		if ( ! Languages::is_valid( $language ) ) {
			return new \WP_Error( 'openlingua_invalid_language', __( 'Invalid language.', 'openlingua' ) );
		}
		$group_uuid = $group_uuid ?: wp_generate_uuid4();
		$table      = Database::table( 'translations' );
		$existing   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE element_type = %s AND element_id = %d", $element_type, $element_id ) );
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
		return $wpdb->last_error ? new \WP_Error( 'openlingua_db_error', $wpdb->last_error ) : $group_uuid;
	}

	public static function row( $element_type, $element_id ) {
		global $wpdb;
		$table = Database::table( 'translations' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE element_type = %s AND element_id = %d", $element_type, $element_id ) );
	}

	public static function group( $element_type, $element_id ) {
		global $wpdb;
		$row = self::row( $element_type, $element_id );
		if ( ! $row ) {
			return array();
		}
		$table = Database::table( 'translations' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT language, element_id FROM {$table} WHERE element_type = %s AND group_uuid = %s", $element_type, $row->group_uuid ) );
		return wp_list_pluck( $rows, 'element_id', 'language' );
	}

	public static function translated_id( $element_type, $element_id, $language ) {
		$group = self::group( $element_type, $element_id );
		return isset( $group[ $language ] ) ? absint( $group[ $language ] ) : 0;
	}

	public static function delete( $element_type, $element_id ) {
		global $wpdb;
		$wpdb->delete( Database::table( 'translations' ), array( 'element_type' => $element_type, 'element_id' => absint( $element_id ) ) );
	}
}

