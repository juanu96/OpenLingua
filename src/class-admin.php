<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Admin {
	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'post_filter' ) );
		add_action( 'admin_post_openlingua_save_strings', array( __CLASS__, 'save_strings' ) );
		add_action( 'update_option_openlingua_languages', array( __CLASS__, 'schedule_rewrite_flush' ) );
	}

	public static function menu() {
		add_menu_page( __( 'OpenLingua', 'openlingua' ), __( 'OpenLingua', 'openlingua' ), 'manage_options', 'openlingua', array( __CLASS__, 'page' ), 'dashicons-translation', 58 );
		add_submenu_page( 'openlingua', __( 'String translations', 'openlingua' ), __( 'Strings', 'openlingua' ), 'manage_options', 'openlingua-strings', array( __CLASS__, 'strings_page' ) );
	}

	public static function settings() {
		register_setting( 'openlingua', 'openlingua_languages', array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize_languages' ) ) );
		register_setting( 'openlingua', 'openlingua_default_language', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ) );
		register_setting( 'openlingua', 'openlingua_string_discovery', array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ) );
	}

	public static function sanitize_languages( $input ) {
		$output = array();
		foreach ( (array) $input as $code => $language ) {
			if ( '__new' === $code ) {
				$code = sanitize_key( $language['code'] ?? '' );
			}
			$code = sanitize_key( $code );
			if ( $code && ! empty( $language['name'] ) ) {
				$output[ $code ] = array( 'name' => sanitize_text_field( $language['name'] ), 'locale' => sanitize_text_field( $language['locale'] ?? $code ) );
			}
		}
		return $output;
	}

	public static function page() {
		if ( current_user_can( 'manage_options' ) ) { \OpenLingua\Modules\Language_Settings::page(); }
	}

	public static function post_filter() {
		echo '<select name="openlingua_language_filter"><option value="">' . esc_html__( 'All languages', 'openlingua' ) . '</option>';
		$current = isset( $_GET['openlingua_language_filter'] ) ? sanitize_key( wp_unslash( $_GET['openlingua_language_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( Languages::all() as $code => $language ) {
			echo '<option value="' . esc_attr( $code ) . '" ' . selected( $current, $code, false ) . '>' . esc_html( $language['name'] ) . '</option>';
		}
		echo '</select>';
	}

	public static function strings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'strings' ) . ' ORDER BY domain, string_key' );
		echo '<div class="wrap"><h1>' . esc_html__( 'String translations', 'openlingua' ) . '</h1>';
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No strings have been registered through the OpenLingua API yet.', 'openlingua' ) . '</p></div>'; return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="openlingua_save_strings">';
		wp_nonce_field( 'openlingua_save_strings' );
		foreach ( $rows as $row ) {
			$translations = json_decode( $row->translations, true ) ?: array();
			echo '<div class="card" style="max-width:none"><h2><code>' . esc_html( $row->domain . ':' . $row->string_key ) . '</code></h2><p><strong>' . esc_html( $row->source_text ) . '</strong></p><table class="form-table">';
			foreach ( Languages::all() as $code => $language ) {
				if ( $code === $row->source_language ) { continue; }
				echo '<tr><th><label for="string-' . absint( $row->id ) . '-' . esc_attr( $code ) . '">' . esc_html( $language['name'] ) . '</label></th><td><textarea class="large-text" id="string-' . absint( $row->id ) . '-' . esc_attr( $code ) . '" name="translations[' . absint( $row->id ) . '][' . esc_attr( $code ) . ']">' . esc_textarea( $translations[ $code ] ?? '' ) . '</textarea></td></tr>';
			}
			echo '</table></div>';
		}
		submit_button( __( 'Save translations', 'openlingua' ) ); echo '</form></div>';
	}

	public static function save_strings() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'openlingua' ) ); }
		check_admin_referer( 'openlingua_save_strings' );
		global $wpdb;
		foreach ( (array) ( $_POST['translations'] ?? array() ) as $id => $translations ) {
			$clean = array();
			foreach ( (array) $translations as $code => $text ) {
				if ( Languages::is_valid( $code ) ) { $clean[ sanitize_key( $code ) ] = sanitize_textarea_field( wp_unslash( $text ) ); }
			}
			$wpdb->update( Database::table( 'strings' ), array( 'translations' => wp_json_encode( $clean ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $id ) ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'openlingua-strings', 'updated' => '1' ), admin_url( 'admin.php' ) ) ); exit;
	}

	public static function schedule_rewrite_flush() {
		update_option( 'openlingua_flush_rewrite_rules', 1 );
	}
}
