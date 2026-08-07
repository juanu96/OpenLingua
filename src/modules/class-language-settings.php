<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;
use OpenLingua\Languages;

defined( 'ABSPATH' ) || exit;

final class Language_Settings implements Module {
	public static function hooks() {
		add_action( 'admin_post_openlingua_save_language_settings', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'template_redirect', array( __CLASS__, 'browser_redirect' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'footer_switcher' ), 100 );
		add_filter( 'allowed_redirect_hosts', array( __CLASS__, 'allowed_redirect_hosts' ) );
	}

	public static function defaults() {
		return array(
			'url_mode' => 'directory', 'domains' => array(), 'admin_language' => 'site-default',
			'hidden_languages' => array(), 'browser_redirect' => 'off',
			'switcher' => array( 'show_flag' => true, 'show_name' => true, 'show_native_name' => false, 'show_current' => true, 'dropdown' => false, 'missing' => 'home', 'footer' => false, 'menu_locations' => array(), 'menu_position' => 'last' ),
		);
	}

	public static function get() {
		return array_replace_recursive( self::defaults(), (array) get_option( 'openlingua_language_settings', array() ) );
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_openlingua' !== $hook ) { return; }
		wp_enqueue_style( 'openlingua-language-settings', plugins_url( 'assets/admin-language-settings.css', OPENLINGUA_FILE ), array(), OPENLINGUA_VERSION );
	}

	public static function page() {
		$catalog  = Language_Catalog::merged();
		$enabled  = Languages::all();
		$settings = self::get();
		$default  = Languages::default_code();
		echo '<div class="wrap openlingua-settings"><h1>' . esc_html__( 'OpenLingua language settings', 'openlingua' ) . '</h1><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="openlingua_save_language_settings">';
		wp_nonce_field( 'openlingua_save_language_settings' );
		self::section_languages( $catalog, $enabled, $default );
		self::section_urls( $enabled, $settings );
		self::section_switcher( $settings );
		self::section_visibility( $enabled, $settings );
		self::section_advanced( $enabled, $settings );
		submit_button( __( 'Save language settings', 'openlingua' ), 'primary large' );
		echo '</form></div>';
	}

	private static function section_languages( $catalog, $enabled, $default ) {
		echo '<section class="openlingua-card"><h2>' . esc_html__( 'Site languages', 'openlingua' ) . '</h2><p>' . esc_html__( 'Enable the languages visitors can use and select the default language.', 'openlingua' ) . '</p><div class="openlingua-language-grid">';
		foreach ( $catalog as $code => $language ) {
			$active = isset( $enabled[ $code ] );
			echo '<label class="openlingua-language-option' . ( $active ? ' is-enabled' : '' ) . '"><input type="checkbox" name="enabled_languages[]" value="' . esc_attr( $code ) . '" ' . checked( $active, true, false ) . ( $code === $default ? ' data-default="1"' : '' ) . '><span class="openlingua-flag">' . esc_html( $language['flag'] ?? '🌐' ) . '</span><span><strong>' . esc_html( $language['name'] ) . '</strong><small>' . esc_html( $language['native_name'] ?? $language['name'] ) . ' · ' . esc_html( $code ) . '</small></span><input type="radio" name="default_language" value="' . esc_attr( $code ) . '" ' . checked( $default, $code, false ) . ' aria-label="' . esc_attr__( 'Default language', 'openlingua' ) . '"></label>';
		}
		echo '</div><details><summary>' . esc_html__( 'Add a custom language', 'openlingua' ) . '</summary><div class="openlingua-custom-grid"><label>' . esc_html__( 'Code', 'openlingua' ) . '<input name="custom[code]" placeholder="es-ni"></label><label>' . esc_html__( 'English name', 'openlingua' ) . '<input name="custom[name]"></label><label>' . esc_html__( 'Native name', 'openlingua' ) . '<input name="custom[native_name]"></label><label>' . esc_html__( 'WordPress locale', 'openlingua' ) . '<input name="custom[locale]" placeholder="es_NI"></label><label>' . esc_html__( 'Flag or symbol', 'openlingua' ) . '<input name="custom[flag]" placeholder="🇳🇮"></label><label>' . esc_html__( 'Text direction', 'openlingua' ) . '<select name="custom[direction]"><option value="ltr">LTR</option><option value="rtl">RTL</option></select></label></div></details></section>';
	}

	private static function section_urls( $enabled, $settings ) {
		echo '<section class="openlingua-card"><h2>' . esc_html__( 'Language URL format', 'openlingua' ) . '</h2>';
		$options = array( 'directory' => __( 'Directories: example.com/es/page/', 'openlingua' ), 'query' => __( 'Query parameter: example.com/page/?lang=es', 'openlingua' ), 'domain' => __( 'A different domain or subdomain for each language', 'openlingua' ) );
		foreach ( $options as $value => $label ) { echo '<label class="openlingua-radio"><input type="radio" name="url_mode" value="' . esc_attr( $value ) . '" ' . checked( $settings['url_mode'], $value, false ) . '> ' . esc_html( $label ) . '</label>'; }
		echo '<div class="openlingua-domains"><h3>' . esc_html__( 'Language domains', 'openlingua' ) . '</h3><p>' . esc_html__( 'Enter a complete HTTPS base URL. DNS and WordPress must already accept each domain.', 'openlingua' ) . '</p>';
		foreach ( $enabled as $code => $language ) { echo '<label><span>' . esc_html( $language['name'] ) . '</span><input type="url" name="domains[' . esc_attr( $code ) . ']" value="' . esc_attr( $settings['domains'][ $code ] ?? '' ) . '" placeholder="https://' . esc_attr( $code ) . '.example.com"></label>'; }
		echo '</div></section>';
	}

	private static function section_switcher( $settings ) {
		$s = $settings['switcher'];
		echo '<section class="openlingua-card"><h2>' . esc_html__( 'Language switcher', 'openlingua' ) . '</h2>';
		foreach ( array( 'show_flag' => __( 'Show flag or symbol', 'openlingua' ), 'show_name' => __( 'Show translated language name', 'openlingua' ), 'show_native_name' => __( 'Show native language name', 'openlingua' ), 'show_current' => __( 'Include the current language', 'openlingua' ), 'dropdown' => __( 'Render as a dropdown', 'openlingua' ), 'footer' => __( 'Automatically show a switcher in the footer', 'openlingua' ) ) as $key => $label ) { echo '<label class="openlingua-check"><input type="checkbox" name="switcher[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $s[ $key ] ), true, false ) . '> ' . esc_html( $label ) . '</label>'; }
		echo '<label class="openlingua-select">' . esc_html__( 'When a translation is missing', 'openlingua' ) . '<select name="switcher[missing]"><option value="home" ' . selected( $s['missing'], 'home', false ) . '>' . esc_html__( 'Link to that language homepage', 'openlingua' ) . '</option><option value="hide" ' . selected( $s['missing'], 'hide', false ) . '>' . esc_html__( 'Hide the language', 'openlingua' ) . '</option></select></label></section>';
	}

	private static function section_visibility( $enabled, $settings ) {
		echo '<section class="openlingua-card"><h2>' . esc_html__( 'Language visibility', 'openlingua' ) . '</h2><p>' . esc_html__( 'Hidden languages remain editable by administrators but are removed from public switchers and discovery endpoints.', 'openlingua' ) . '</p>';
		foreach ( $enabled as $code => $language ) { echo '<label class="openlingua-check"><input type="checkbox" name="hidden_languages[]" value="' . esc_attr( $code ) . '" ' . checked( in_array( $code, $settings['hidden_languages'], true ), true, false ) . '> ' . esc_html( $language['name'] ) . '</label>'; }
		echo '</section>';
	}

	private static function section_advanced( $enabled, $settings ) {
		echo '<section class="openlingua-card"><h2>' . esc_html__( 'Administration and browser behavior', 'openlingua' ) . '</h2><label class="openlingua-select">' . esc_html__( 'WordPress administration language', 'openlingua' ) . '<select name="admin_language"><option value="site-default">' . esc_html__( 'Use the site default language', 'openlingua' ) . '</option><option value="user">' . esc_html__( 'Use each user profile language', 'openlingua' ) . '</option>';
		foreach ( $enabled as $code => $language ) { echo '<option value="' . esc_attr( $code ) . '" ' . selected( $settings['admin_language'], $code, false ) . '>' . esc_html( $language['name'] ) . '</option>'; }
		echo '</select></label><label class="openlingua-select">' . esc_html__( 'Browser language redirect', 'openlingua' ) . '<select name="browser_redirect"><option value="off" ' . selected( $settings['browser_redirect'], 'off', false ) . '>' . esc_html__( 'Disabled (recommended for SEO)', 'openlingua' ) . '</option><option value="once" ' . selected( $settings['browser_redirect'], 'once', false ) . '>' . esc_html__( 'Redirect only on the first visit', 'openlingua' ) . '</option><option value="always" ' . selected( $settings['browser_redirect'], 'always', false ) . '>' . esc_html__( 'Redirect whenever the requested language differs', 'openlingua' ) . '</option></select></label><label class="openlingua-check"><input type="checkbox" name="string_discovery" value="1" ' . checked( get_option( 'openlingua_string_discovery', false ), true, false ) . '> ' . esc_html__( 'Temporarily discover gettext strings from themes and plugins', 'openlingua' ) . '</label></section>';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'openlingua' ) ); }
		check_admin_referer( 'openlingua_save_language_settings' );
		$custom = isset( $_POST['custom'] ) ? (array) wp_unslash( $_POST['custom'] ) : array();
		$custom_code = sanitize_key( wp_unslash( $custom['code'] ?? '' ) );
		$custom_languages = (array) get_option( 'openlingua_custom_languages', array() );
		if ( $custom_code && ! empty( $custom['name'] ) ) {
			$custom_languages[ $custom_code ] = array( 'name' => sanitize_text_field( wp_unslash( $custom['name'] ) ), 'native_name' => sanitize_text_field( wp_unslash( $custom['native_name'] ?? $custom['name'] ) ), 'locale' => sanitize_text_field( wp_unslash( $custom['locale'] ?? $custom_code ) ), 'flag' => sanitize_text_field( wp_unslash( $custom['flag'] ?? '🌐' ) ), 'direction' => 'rtl' === ( $custom['direction'] ?? '' ) ? 'rtl' : 'ltr' );
			update_option( 'openlingua_custom_languages', $custom_languages );
		}
		$catalog = array_replace( Language_Catalog::all(), $custom_languages );
		$enabled_codes = array_map( 'sanitize_key', isset( $_POST['enabled_languages'] ) ? (array) wp_unslash( $_POST['enabled_languages'] ) : array() );
		if ( $custom_code ) { $enabled_codes[] = $custom_code; }
		$default = sanitize_key( wp_unslash( $_POST['default_language'] ?? '' ) );
		if ( ! isset( $catalog[ $default ] ) ) { $default = Languages::default_code(); }
		if ( ! in_array( $default, $enabled_codes, true ) ) { $enabled_codes[] = $default; }
		$enabled = array_intersect_key( $catalog, array_flip( array_unique( $enabled_codes ) ) );
		if ( ! $enabled ) { wp_die( esc_html__( 'At least one language must remain enabled.', 'openlingua' ) ); }
		update_option( 'openlingua_languages', $enabled ); update_option( 'openlingua_default_language', $default );
		$url_mode = sanitize_key( wp_unslash( $_POST['url_mode'] ?? 'directory' ) );
		if ( ! in_array( $url_mode, array( 'directory', 'query', 'domain' ), true ) ) { $url_mode = 'directory'; }
		$submitted_domains = isset( $_POST['domains'] ) ? (array) wp_unslash( $_POST['domains'] ) : array();
		$domains = array(); foreach ( $submitted_domains as $code => $url ) { if ( isset( $enabled[ $code ] ) && $url ) { $domains[ sanitize_key( $code ) ] = untrailingslashit( esc_url_raw( $url ) ); } }
		$admin_language = sanitize_key( wp_unslash( $_POST['admin_language'] ?? 'site-default' ) ); if ( ! in_array( $admin_language, array_merge( array( 'site-default', 'user' ), array_keys( $enabled ) ), true ) ) { $admin_language = 'site-default'; }
		$browser = sanitize_key( wp_unslash( $_POST['browser_redirect'] ?? 'off' ) ); if ( ! in_array( $browser, array( 'off', 'once', 'always' ), true ) ) { $browser = 'off'; }
		$switcher = isset( $_POST['switcher'] ) ? (array) wp_unslash( $_POST['switcher'] ) : array();
		$registered_locations = array_keys( get_registered_nav_menus() );
		$menu_locations = array_values( array_intersect( $registered_locations, array_map( 'sanitize_key', (array) ( $switcher['menu_locations'] ?? array() ) ) ) );
		$clean_switcher = array( 'show_flag' => ! empty( $switcher['show_flag'] ), 'show_name' => ! empty( $switcher['show_name'] ), 'show_native_name' => ! empty( $switcher['show_native_name'] ), 'show_current' => ! empty( $switcher['show_current'] ), 'dropdown' => ! empty( $switcher['dropdown'] ), 'footer' => ! empty( $switcher['footer'] ), 'missing' => 'hide' === ( $switcher['missing'] ?? '' ) ? 'hide' : 'home', 'menu_locations' => $menu_locations, 'menu_position' => 'first' === ( $switcher['menu_position'] ?? '' ) ? 'first' : 'last' );
		$hidden = array_values( array_intersect( array_keys( $enabled ), array_map( 'sanitize_key', isset( $_POST['hidden_languages'] ) ? (array) wp_unslash( $_POST['hidden_languages'] ) : array() ) ) );
		update_option( 'openlingua_language_settings', array( 'url_mode' => $url_mode, 'domains' => $domains, 'admin_language' => $admin_language, 'hidden_languages' => $hidden, 'browser_redirect' => $browser, 'switcher' => $clean_switcher ) );
		update_option( 'openlingua_string_discovery', ! empty( $_POST['string_discovery'] ) ); update_option( 'openlingua_flush_rewrite_rules', 1 );
		wp_safe_redirect( add_query_arg( array( 'page' => 'openlingua', 'updated' => 1 ), admin_url( 'admin.php' ) ) ); exit;
	}

	public static function browser_redirect() {
		$mode = self::get()['browser_redirect'];
		if ( 'off' === $mode || is_admin() || wp_doing_ajax() || wp_is_json_request() || is_preview() || headers_sent() ) { return; }
		if ( 'once' === $mode && ! empty( $_COOKIE['openlingua_browser_redirect'] ) ) { return; }
		$accepted = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '' ) ) );
		foreach ( preg_split( '/\s*,\s*/', $accepted ) as $candidate ) {
			$code = sanitize_key( strtok( $candidate, ';' ) );
			$base = substr( $code, 0, 2 );
			$target = Languages::is_valid( $code ) ? $code : ( Languages::is_valid( $base ) ? $base : '' );
			if ( $target ) {
				setcookie( 'openlingua_browser_redirect', '1', array( 'expires' => time() + MONTH_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
				if ( $target !== Languages::current() ) {
					$request_path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ), PHP_URL_PATH );
					wp_safe_redirect( Languages::url( home_url( $request_path ?: '/' ), $target ), 302 ); exit;
				}
				break;
			}
		}
	}

	public static function footer_switcher() {
		if ( self::get()['switcher']['footer'] ) { echo do_shortcode( '[openlingua_switcher]' ); }
	}

	public static function allowed_redirect_hosts( $hosts ) {
		foreach ( (array) self::get()['domains'] as $domain ) {
			$host = wp_parse_url( $domain, PHP_URL_HOST );
			if ( $host ) { $hosts[] = $host; }
		}
		return array_values( array_unique( $hosts ) );
	}
}
