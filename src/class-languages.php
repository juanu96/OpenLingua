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

	public static function set_current( $code ) {
		$code = sanitize_key( $code );
		if ( self::is_valid( $code ) ) {
			self::$current = $code;
		}
		return self::$current;
	}

	public static function is_valid( $code ) {
		return isset( self::all()[ sanitize_key( $code ) ] );
	}

	public static function url( $url, $code ) {
		$code = sanitize_key( $code );
		if ( ! self::is_valid( $code ) || ! $url ) {
			return $url;
		}

		$url        = remove_query_arg( 'lang', $url );
		$parts      = wp_parse_url( $url );
		$home_parts = wp_parse_url( home_url( '/' ) );
		if ( ! empty( $parts['host'] ) && ! empty( $home_parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( $home_parts['host'] ) ) {
			return $url;
		}

		$home_path = isset( $home_parts['path'] ) ? '/' . trim( $home_parts['path'], '/' ) : '';
		$home_path = '/' === $home_path ? '' : rtrim( $home_path, '/' );
		$path      = isset( $parts['path'] ) ? $parts['path'] : '/';
		$relative  = 0 === strpos( $path, $home_path ) ? substr( $path, strlen( $home_path ) ) : $path;
		$codes     = array_map( 'preg_quote', array_keys( self::all() ) );
		$relative  = preg_replace( '#^/(?:' . implode( '|', $codes ) . ')(?=/|$)#', '', $relative );
		$target    = home_url( '/' . $code . '/' . ltrim( $relative, '/' ) );
		if ( ! empty( $parts['query'] ) ) {
			$target .= '?' . $parts['query'];
		}
		if ( ! empty( $parts['fragment'] ) ) {
			$target .= '#' . $parts['fragment'];
		}
		return $target;
	}
}
