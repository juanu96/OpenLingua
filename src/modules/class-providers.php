<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;
use OpenLingua\Contracts\Translation_Provider;

defined( 'ABSPATH' ) || exit;

final class Providers implements Module {
	const ACTIVE_OPTION = 'openlingua_active_translation_provider';
	private static $providers = array();

	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'discover' ), 20 );
	}

	public static function discover() {
		foreach ( (array) apply_filters( 'openlingua_translation_providers', array() ) as $provider ) {
			self::register( $provider );
		}
	}

	public static function register( $provider ) {
		if ( ! $provider instanceof Translation_Provider ) { return false; }
		self::$providers[ sanitize_key( $provider->id() ) ] = $provider;
		return true;
	}

	public static function get( $id ) {
		return self::$providers[ sanitize_key( $id ) ] ?? null;
	}

	public static function all() {
		return self::$providers;
	}

	public static function active_id() {
		return sanitize_key( get_option( self::ACTIVE_OPTION, 'openai' ) );
	}

	public static function active() {
		return self::get( self::active_id() );
	}

	public static function activate( $id ) {
		$id = sanitize_key( $id );
		if ( ! self::get( $id ) ) { return false; }
		update_option( self::ACTIVE_OPTION, $id, false );
		return true;
	}

	public static function setup_guide( array $steps, $note = '' ) {
		echo '<div class="openlingua-provider-guide"><h3>' . esc_html__( 'How to get your API key', 'openlingua' ) . '</h3><ol>';
		foreach ( $steps as $step ) {
			echo '<li>' . esc_html( $step['text'] ?? '' );
			if ( ! empty( $step['url'] ) ) {
				echo ' <a href="' . esc_url( $step['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $step['label'] ?? __( 'Open official page', 'openlingua' ) ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'openlingua' ) . '</span></a>';
			}
			echo '</li>';
		}
		echo '</ol>';
		if ( $note ) { echo '<p class="description">' . esc_html( $note ) . '</p>'; }
		echo '</div>';
	}
}
