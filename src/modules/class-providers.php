<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;
use OpenLingua\Contracts\Translation_Provider;

defined( 'ABSPATH' ) || exit;

final class Providers implements Module {
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
}

