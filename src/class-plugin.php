<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	public static function boot() {
		Database::maybe_upgrade();
		if ( is_multisite() ) { add_action( 'wp_initialize_site', array( 'OpenLingua\\Database', 'install_new_site' ), 20, 1 ); }
		load_plugin_textdomain( 'openlingua', false, dirname( plugin_basename( OPENLINGUA_FILE ) ) . '/languages' );
		Content::hooks();
		Taxonomies::hooks();
		Routing::hooks();
		SEO::hooks();
		REST::hooks();
		Admin::hooks();
		Module_Registry::boot( apply_filters( 'openlingua_modules', array(
			\OpenLingua\Modules\Workflow::class,
			\OpenLingua\Modules\Menus::class,
			\OpenLingua\Modules\Metadata::class,
			\OpenLingua\Modules\Commerce::class,
			\OpenLingua\Modules\Providers::class,
			\OpenLingua\Modules\Jobs::class,
			\OpenLingua\Modules\String_Discovery::class,
			\OpenLingua\Modules\Portability::class,
			\OpenLingua\Modules\Diagnostics::class,
			\OpenLingua\Modules\Privacy::class,
			\OpenLingua\Modules\CLI::class,
		) ) );
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

function translate_plural( $singular_key, $plural_key, $number, $singular, $plural, $domain = 'default', $language = '' ) {
	return Strings::translate_plural( $singular_key, $plural_key, $number, $singular, $plural, $domain, $language );
}

function translated_post_id( $post_id, $language ) {
	return Translations::translated_id( 'post', $post_id, $language );
}

function translated_term_id( $term_id, $language ) {
	return Translations::translated_id( 'term', $term_id, $language );
}

function register_provider( $provider ) {
	return \OpenLingua\Modules\Providers::register( $provider );
}

function enqueue_translation_job( $source_id, $target_id, $target_language, $provider_id ) {
	return \OpenLingua\Modules\Jobs::enqueue( $source_id, $target_id, $target_language, $provider_id );
}

function set_menu_translation( $location, $language, $menu_id ) {
	return \OpenLingua\Modules\Menus::set( $location, $language, $menu_id );
}
