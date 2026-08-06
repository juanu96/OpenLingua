<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	public static function boot() {
		Database::maybe_upgrade();
		load_plugin_textdomain( 'openlingua', false, dirname( plugin_basename( OPENLINGUA_FILE ) ) . '/languages' );
		Content::hooks();
		Taxonomies::hooks();
		Routing::hooks();
		SEO::hooks();
		REST::hooks();
		Admin::hooks();
		add_shortcode( 'openlingua_switcher', array( __CLASS__, 'switcher' ) );
		add_filter( 'locale', array( __CLASS__, 'locale' ) );
	}

	public static function switcher() {
		$current_id = get_queried_object_id();
		$group      = $current_id ? Translations::group( 'post', $current_id ) : array();
		$html       = '<nav class="openlingua-switcher" aria-label="' . esc_attr__( 'Languages', 'openlingua' ) . '"><ul>';
		foreach ( Languages::all() as $code => $language ) {
			$url = isset( $group[ $code ] ) ? get_permalink( $group[ $code ] ) : home_url( '/' );
			$html .= '<li><a hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $code ) . '" href="' . esc_url( Languages::url( $url, $code ) ) . '"' . ( Languages::current() === $code ? ' aria-current="page"' : '' ) . '>' . esc_html( $language['name'] ) . '</a></li>';
		}
		return $html . '</ul></nav>';
	}

	public static function locale( $locale ) {
		$languages = Languages::all();
		$current   = Languages::current();
		return isset( $languages[ $current ]['locale'] ) ? $languages[ $current ]['locale'] : $locale;
	}
}

function register_string( $key, $text, $domain = 'default', $source_language = '' ) {
	return Strings::register( $key, $text, $domain, $source_language );
}

function translate_string( $key, $fallback = '', $domain = 'default', $language = '' ) {
	return Strings::translate( $key, $fallback, $domain, $language );
}

function translated_post_id( $post_id, $language ) {
	return Translations::translated_id( 'post', $post_id, $language );
}

function translated_term_id( $term_id, $language ) {
	return Translations::translated_id( 'term', $term_id, $language );
}
