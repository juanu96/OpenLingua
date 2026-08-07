<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;
use OpenLingua\Contracts\Translation_Provider;

defined( 'ABSPATH' ) || exit;

final class Google_Translate_Provider implements Module, Translation_Provider {
	use Provider_Secrets;

	const OPTION = 'openlingua_google_translate_settings';
	const ID = 'google-translate';

	public static function hooks() {
		add_filter( 'openlingua_translation_providers', array( __CLASS__, 'register' ) );
		add_action( 'openlingua_advanced_settings_sections', array( __CLASS__, 'settings_section' ) );
		add_action( 'admin_post_openlingua_save_google_translate', array( __CLASS__, 'save_settings' ) );
	}

	public static function register( $providers ) { $providers[] = new self(); return $providers; }
	public function id() { return self::ID; }
	public function label() { return 'Google Translate'; }
	public function is_configured() { return '' !== self::api_key(); }
	public static function settings_url() { return add_query_arg( array( 'page' => 'openlingua-fields', 'section' => self::ID ), admin_url( 'admin.php' ) ); }
	private static function settings() { return wp_parse_args( (array) get_option( self::OPTION, array() ), array( 'api_key' => '' ) ); }
	private static function api_key() { return self::decrypt_secret( self::settings()['api_key'] ); }

	public static function settings_section() {
		$configured = '' !== self::api_key();
		echo '<form id="openlingua-google-translate" class="openlingua-provider-panel" data-openlingua-provider-panel="google-translate" role="tabpanel" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="openlingua_save_google_translate">'; wp_nonce_field( 'openlingua_save_google_translate' );
		echo '<section class="openlingua-card"><h2>' . esc_html__( 'Automatic translation with Google Translate', 'openlingua' ) . '</h2><p>' . esc_html__( 'Connect Google Cloud Translation to translate content with its Neural Machine Translation model.', 'openlingua' ) . '</p>';
		Providers::setup_guide( array(
			array( 'text' => __( 'Create or select a Google Cloud project.', 'openlingua' ), 'url' => 'https://console.cloud.google.com/projectselector2/home/dashboard', 'label' => __( 'Select a project', 'openlingua' ) ),
			array( 'text' => __( 'Enable billing for the project.', 'openlingua' ), 'url' => 'https://console.cloud.google.com/billing', 'label' => __( 'Cloud Billing', 'openlingua' ) ),
			array( 'text' => __( 'Enable the Cloud Translation API.', 'openlingua' ), 'url' => 'https://console.cloud.google.com/apis/library/translate.googleapis.com', 'label' => __( 'Enable the API', 'openlingua' ) ),
			array( 'text' => __( 'Create an API key in Credentials, then restrict it to the Cloud Translation API and, when possible, your server IP.', 'openlingua' ), 'url' => 'https://console.cloud.google.com/apis/credentials', 'label' => __( 'Create credentials', 'openlingua' ) ),
			array( 'text' => __( 'Copy the key and paste it below.', 'openlingua' ) ),
		), __( 'Google Cloud requires billing. Its current pricing includes a monthly credit for the first 500,000 translated characters; verify the current limits before use.', 'openlingua' ) );
		echo '<table class="form-table" role="presentation"><tr><th><label for="openlingua-google-translate-key">' . esc_html__( 'Google Cloud API key', 'openlingua' ) . '</label></th><td><input id="openlingua-google-translate-key" class="regular-text" type="password" name="api_key" autocomplete="new-password">';
		if ( $configured ) { echo '<p class="description"><strong>' . esc_html__( 'A key is configured.', 'openlingua' ) . '</strong> ' . esc_html__( 'Leave empty to keep it.', 'openlingua' ) . '</p><label><input type="checkbox" name="clear_api_key" value="1"> ' . esc_html__( 'Remove the saved key', 'openlingua' ) . '</label>'; }
		echo '</td></tr><tr><th>' . esc_html__( 'Translation model', 'openlingua' ) . '</th><td><strong>' . esc_html__( 'Google Neural Machine Translation (NMT)', 'openlingua' ) . '</strong><p class="description">' . esc_html__( 'Cloud Translation Basic selects this model automatically.', 'openlingua' ) . '</p></td></tr><tr><th>' . esc_html__( 'Active provider', 'openlingua' ) . '</th><td><label><input type="radio" name="activate_provider" value="1"' . checked( Providers::active_id(), self::ID, false ) . '> ' . esc_html__( 'Use Google Translate for automatic translations', 'openlingua' ) . '</label></td></tr></table>'; submit_button( __( 'Save Google Translate settings', 'openlingua' ) ); echo '</section></form>';
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'openlingua' ) ); } check_admin_referer( 'openlingua_save_google_translate' ); $current = self::settings(); $encrypted = ! empty( $_POST['clear_api_key'] ) ? '' : $current['api_key']; $key = trim( (string) wp_unslash( $_POST['api_key'] ?? '' ) ); if ( '' !== $key ) { $encrypted = self::encrypt_secret( $key ); }
		update_option( self::OPTION, array( 'api_key' => $encrypted ), false ); if ( $encrypted && ! empty( $_POST['activate_provider'] ) ) { Providers::activate( self::ID ); } wp_safe_redirect( self::settings_url() ); exit;
	}

	public function translate( array $segments, $source_language, $target_language, array $context = array() ) {
		$key = self::api_key(); if ( ! $key ) { return new \WP_Error( 'openlingua_google_translate_key', __( 'The Google Cloud Translation API key is not configured.', 'openlingua' ) ); } $segments = array_filter( array_map( 'strval', $segments ), static function( $value ) { return '' !== trim( $value ); } ); if ( ! $segments ) { return array(); } $translated = array();
		foreach ( array_chunk( $segments, 100, true ) as $batch ) {
			$payload = array( 'q' => array_values( $batch ), 'source' => str_replace( '_', '-', sanitize_text_field( $source_language ) ), 'target' => str_replace( '_', '-', sanitize_text_field( $target_language ) ), 'format' => 'html', 'model' => 'nmt' );
			$response = wp_remote_post( 'https://translation.googleapis.com/language/translate/v2', array( 'timeout' => 120, 'headers' => array( 'X-Goog-Api-Key' => $key, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $payload ) ) ); if ( is_wp_error( $response ) ) { return $response; } $status = wp_remote_retrieve_response_code( $response ); $body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $status < 200 || $status >= 300 ) { return new \WP_Error( 'openlingua_google_translate_api', sanitize_text_field( $body['error']['message'] ?? sprintf( __( 'Google Translate returned HTTP status %d.', 'openlingua' ), $status ) ) ); }
			$items = (array) ( $body['data']['translations'] ?? array() ); if ( count( $items ) !== count( $batch ) ) { return new \WP_Error( 'openlingua_google_translate_response', __( 'Google Translate returned an incomplete response.', 'openlingua' ) ); }
			foreach ( array_combine( array_keys( $batch ), $items ) as $id => $item ) { $translated[ $id ] = html_entity_decode( (string) ( $item['translatedText'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); }
		}
		return $translated;
	}
}
