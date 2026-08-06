<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;
use OpenLingua\Database;

defined( 'ABSPATH' ) || exit;

final class Portability implements Module {
	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_openlingua_export', array( __CLASS__, 'export_download' ) );
		add_action( 'admin_post_openlingua_import', array( __CLASS__, 'import_upload' ) );
	}

	public static function snapshot() {
		global $wpdb;
		return array(
			'format' => 'openlingua-portable', 'format_version' => 1, 'plugin_version' => OPENLINGUA_VERSION,
			'generated_at' => gmdate( 'c' ), 'site_url' => home_url( '/' ),
			'settings' => array(
				'languages' => get_option( 'openlingua_languages', array() ),
				'default_language' => get_option( 'openlingua_default_language', '' ),
				'menu_map' => get_option( 'openlingua_menu_map', array() ),
				'meta_policies' => get_option( 'openlingua_meta_policies', array() ),
			),
			'translations' => $wpdb->get_results( 'SELECT group_uuid,element_type,element_id,language,source_language FROM ' . Database::table( 'translations' ), ARRAY_A ),
			'strings' => $wpdb->get_results( 'SELECT domain,string_key,source_language,source_text,translations,updated_at FROM ' . Database::table( 'strings' ), ARRAY_A ),
		);
	}

	public static function merge( array $data ) {
		if ( 'openlingua-portable' !== ( $data['format'] ?? '' ) || 1 !== absint( $data['format_version'] ?? 0 ) ) {
			return new \WP_Error( 'openlingua_import_format', __( 'Unsupported OpenLingua import format.', 'openlingua' ) );
		}
		$settings = (array) ( $data['settings'] ?? array() );
		if ( ! empty( $settings['languages'] ) ) { update_option( 'openlingua_languages', \OpenLingua\Admin::sanitize_languages( $settings['languages'] ) ); }
		if ( ! empty( $settings['default_language'] ) ) { update_option( 'openlingua_default_language', sanitize_key( $settings['default_language'] ) ); }
		update_option( 'openlingua_menu_map', (array) ( $settings['menu_map'] ?? array() ) );
		update_option( 'openlingua_meta_policies', (array) ( $settings['meta_policies'] ?? array() ) );
		foreach ( (array) ( $data['translations'] ?? array() ) as $row ) {
			$type = sanitize_key( $row['element_type'] ?? '' );
			$id   = absint( $row['element_id'] ?? 0 );
			$exists = 'post' === $type ? get_post( $id ) : ( 'term' === $type ? get_term( $id ) : false );
			if ( ! $id || ! $exists || is_wp_error( $exists ) ) { continue; }
			\OpenLingua\Translations::assign( $type, $id, sanitize_key( $row['language'] ?? '' ), sanitize_text_field( $row['group_uuid'] ?? '' ), sanitize_key( $row['source_language'] ?? '' ) );
		}
		global $wpdb;
		$table = Database::table( 'strings' );
		foreach ( (array) ( $data['strings'] ?? array() ) as $row ) {
			if ( empty( $row['string_key'] ) ) { continue; }
			$wpdb->replace( $table, array(
				'domain' => sanitize_key( $row['domain'] ?? 'default' ), 'string_key' => sanitize_key( $row['string_key'] ?? '' ),
				'source_language' => sanitize_key( $row['source_language'] ?? '' ), 'source_text' => sanitize_textarea_field( $row['source_text'] ?? '' ),
				'translations' => wp_json_encode( json_decode( $row['translations'] ?? '{}', true ) ?: array() ), 'updated_at' => current_time( 'mysql' ),
			) );
		}
		return true;
	}

	public static function admin_menu() {
		add_submenu_page( 'openlingua', __( 'Import and export', 'openlingua' ), __( 'Tools', 'openlingua' ), 'manage_options', 'openlingua-tools', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Import and export', 'openlingua' ) . '</h1><h2>' . esc_html__( 'Export', 'openlingua' ) . '</h2><p>' . esc_html__( 'Download languages, relationships, strings, menus, and field policies as JSON.', 'openlingua' ) . '</p>';
		$url = wp_nonce_url( add_query_arg( 'action', 'openlingua_export', admin_url( 'admin-post.php' ) ), 'openlingua_export' );
		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Download export', 'openlingua' ) . '</a></p><hr><h2>' . esc_html__( 'Merge import', 'openlingua' ) . '</h2><p>' . esc_html__( 'Existing content is not deleted. Matching relationships and strings are updated.', 'openlingua' ) . '</p><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="openlingua_import">';
		wp_nonce_field( 'openlingua_import' );
		echo '<input type="file" name="openlingua_file" accept="application/json,.json" required>'; submit_button( __( 'Merge import', 'openlingua' ) ); echo '</form></div>';
	}

	public static function export_download() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'openlingua' ) ); }
		check_admin_referer( 'openlingua_export' );
		nocache_headers(); header( 'Content-Type: application/json; charset=utf-8' ); header( 'Content-Disposition: attachment; filename=openlingua-export-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( self::snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); exit;
	}

	public static function import_upload() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'openlingua' ) ); }
		check_admin_referer( 'openlingua_import' );
		$file = $_FILES['openlingua_file'] ?? null;
		if ( ! $file || UPLOAD_ERR_OK !== $file['error'] || $file['size'] > 10 * MB_IN_BYTES ) { wp_die( esc_html__( 'Invalid or oversized import file.', 'openlingua' ) ); }
		$data = json_decode( file_get_contents( $file['tmp_name'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$result = is_array( $data ) ? self::merge( $data ) : new \WP_Error( 'openlingua_json', __( 'Invalid JSON document.', 'openlingua' ) );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
		wp_safe_redirect( add_query_arg( array( 'page' => 'openlingua-tools', 'imported' => 1 ), admin_url( 'admin.php' ) ) ); exit;
	}
}
