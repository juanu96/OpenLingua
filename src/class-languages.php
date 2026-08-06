<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Languages {
	private static $current;

	public static function all() {
		$languages = get_option( 'openlingua_languages', array() );
		return is_array( $languages ) ? $languages : array();
	}

	public static function default_code() {
		$default = sanitize_key( get_option( 'openlingua_default_language', 'en' ) );
		return isset( self::all()[ $default ] ) ? $default : (string) array_key_first( self::all() );
	}

	public static function current() {
		if ( null !== self::$current ) {
			return self::$current;
		}
		$candidate = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		self::$current = isset( self::all()[ $candidate ] ) ? $candidate : self::default_code();
		return self::$current;
	}

	public static function is_valid( $code ) {
		return isset( self::all()[ sanitize_key( $code ) ] );
	}

	public static function url( $url, $code ) {
		return self::is_valid( $code ) ? add_query_arg( 'lang', sanitize_key( $code ), $url ) : $url;
	}
}

