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
			\OpenLingua\Modules\Language_Settings::class,
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
		add_filter( 'language_attributes', array( __CLASS__, 'language_attributes' ) );
	}

	public static function switcher() {
		$current_id = get_queried_object_id();
		$group      = $current_id ? Translations::group( 'post', $current_id ) : array();
		$settings   = \OpenLingua\Modules\Language_Settings::get()['switcher'];
		$items      = '';
		foreach ( Languages::public_all() as $code => $language ) {
			if ( 'hide' === $settings['missing'] && $current_id && ! isset( $group[ $code ] ) ) { continue; }
			$url = isset( $group[ $code ] ) ? get_permalink( $group[ $code ] ) : home_url( '/' );
			$parts = array();
			if ( ! empty( $settings['show_flag'] ) ) { $parts[] = '<span class="openlingua-switcher__flag" aria-hidden="true">' . esc_html( $language['flag'] ?? '🌐' ) . '</span>'; }
			if ( ! empty( $settings['show_name'] ) ) { $parts[] = '<span class="openlingua-switcher__name">' . esc_html( $language['name'] ) . '</span>'; }
			if ( ! empty( $settings['show_native_name'] ) && ( $language['native_name'] ?? $language['name'] ) !== $language['name'] ) { $parts[] = '<span class="openlingua-switcher__native">' . esc_html( $language['native_name'] ) . '</span>'; }
			$label = $parts ? implode( ' ', $parts ) : esc_html( strtoupper( $code ) );
			$items .= '<li><a hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $code ) . '" href="' . esc_url( Languages::url( $url, $code ) ) . '"' . ( Languages::current() === $code ? ' aria-current="page"' : '' ) . '>' . $label . '</a></li>';
		}
		if ( ! empty( $settings['dropdown'] ) ) {
			$current = Languages::all()[ Languages::current() ] ?? array( 'name' => strtoupper( Languages::current() ) );
			return '<nav class="openlingua-switcher openlingua-switcher--dropdown" aria-label="' . esc_attr__( 'Languages', 'openlingua' ) . '"><details><summary>' . esc_html( $current['name'] ) . '</summary><ul>' . $items . '</ul></details></nav>';
		}
		return '<nav class="openlingua-switcher" aria-label="' . esc_attr__( 'Languages', 'openlingua' ) . '"><ul>' . $items . '</ul></nav>';
	}

	public static function locale( $locale ) {
		$languages = Languages::all();
		$current   = Languages::current();
		if ( is_admin() ) {
			$admin = \OpenLingua\Modules\Language_Settings::get()['admin_language'];
			if ( 'user' === $admin ) { return get_user_option( 'locale' ) ?: $locale; }
			$current = 'site-default' === $admin ? Languages::default_code() : $admin;
		}
		return isset( $languages[ $current ]['locale'] ) ? $languages[ $current ]['locale'] : $locale;
	}

	public static function language_attributes( $output ) {
		$language = Languages::all()[ Languages::current() ] ?? array();
		$direction = 'rtl' === ( $language['direction'] ?? 'ltr' ) ? 'rtl' : 'ltr';
		return 'lang="' . esc_attr( str_replace( '_', '-', $language['locale'] ?? Languages::current() ) ) . '" dir="' . esc_attr( $direction ) . '"';
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
