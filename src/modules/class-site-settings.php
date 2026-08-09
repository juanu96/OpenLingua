<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/** Central user-facing behavior settings and first-run setup. */
final class Site_Settings implements Module {
	const OPTION = 'openlingua_site_settings';

	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 90 );
		add_action( 'admin_menu', array( __CLASS__, 'reorder_menu' ), 999 );
		add_action( 'admin_post_openlingua_save_site_settings', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_openlingua_complete_setup', array( __CLASS__, 'complete_setup' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function defaults() {
		return array(
			'missing_translation' => 'hide', 'new_translation_status' => 'draft', 'slug_mode' => 'translate',
			'copy_author' => true, 'copy_date' => false, 'copy_thumbnail' => true, 'copy_template' => true,
			'source_change' => 'mark', 'notify_source_changes' => true, 'automatic_on_create' => false,
			'automatic_result' => 'review', 'batch_size' => 40, 'max_attempts' => 3, 'monthly_job_limit' => 0,
			'notify_roles' => array( 'administrator' ), 'noindex_incomplete' => true, 'noindex_fallback' => false,
			'discovery_minutes' => 10, 'completed_job_retention' => 30, 'uninstall_mode' => 'preserve',
		);
	}

	public static function get() { return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() ); }

	public static function menu() {
		add_submenu_page( 'openlingua', __( 'OpenLingua setup', 'openlingua' ), __( 'Setup assistant', 'openlingua' ), 'manage_options', 'openlingua-setup', array( __CLASS__, 'setup_page' ) );
		add_submenu_page( 'openlingua', __( 'OpenLingua settings', 'openlingua' ), __( 'Settings', 'openlingua' ), 'manage_options', 'openlingua-settings', array( __CLASS__, 'page' ) );
	}

	public static function reorder_menu() {
		global $submenu;
		if ( empty( $submenu['openlingua'] ) ) { return; }
		$order = array( 'openlingua', 'openlingua-setup', 'openlingua-settings', 'openlingua-taxonomies', 'openlingua-strings', 'openlingua-shortcodes', 'openlingua-global-content', 'openlingua-divi-theme-builder', 'openlingua-menus', 'openlingua-fields', 'openlingua-jobs', 'openlingua-tools', 'openlingua-diagnostics' );
		usort( $submenu['openlingua'], static function ( $left, $right ) use ( $order ) {
			$left_order = array_search( $left[2], $order, true ); $right_order = array_search( $right[2], $order, true );
			return ( false === $left_order ? 999 : $left_order ) <=> ( false === $right_order ? 999 : $right_order );
		} );
	}

	public static function assets( $hook ) {
		if ( ! in_array( $hook, array( 'openlingua_page_openlingua-settings', 'openlingua_page_openlingua-setup' ), true ) ) { return; }
		wp_enqueue_style( 'openlingua-language-settings', plugins_url( 'assets/admin-language-settings.css', OPENLINGUA_FILE ), array(), OPENLINGUA_VERSION );
	}

	public static function setup_notice() {
		if ( get_option( 'openlingua_setup_complete' ) || ! current_user_can( 'manage_options' ) || isset( $_GET['page'] ) && 'openlingua-setup' === $_GET['page'] ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Finish setting up OpenLingua.', 'openlingua' ) . '</strong> ' . esc_html__( 'Confirm the primary language, URL format, and language switcher before translating content.', 'openlingua' ) . ' <a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=openlingua-setup' ) ) . '">' . esc_html__( 'Start setup', 'openlingua' ) . '</a></p></div>';
	}

	public static function setup_page() {
		$languages = \OpenLingua\Languages::all(); $default = \OpenLingua\Languages::default_code(); $language_settings = Language_Settings::get();
		echo '<div class="wrap openlingua-settings"><h1>' . esc_html__( 'Set up OpenLingua', 'openlingua' ) . '</h1><p>' . esc_html__( 'These essentials are enough to begin. Every option can be changed later.', 'openlingua' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="openlingua_complete_setup">';
		wp_nonce_field( 'openlingua_complete_setup' );
		echo '<section class="openlingua-card"><h2>1. ' . esc_html__( 'Primary language', 'openlingua' ) . '</h2><select name="default_language">';
		foreach ( $languages as $code => $language ) { echo '<option value="' . esc_attr( $code ) . '" ' . selected( $default, $code, false ) . '>' . esc_html( ( $language['flag'] ?? '🌐' ) . ' ' . $language['name'] ) . '</option>'; }
		echo '</select></section><section class="openlingua-card"><h2>2. ' . esc_html__( 'Language URLs', 'openlingua' ) . '</h2>';
		foreach ( array( 'directory' => __( 'Language directories', 'openlingua' ), 'query' => __( 'Query parameter', 'openlingua' ), 'domain' => __( 'Separate domains', 'openlingua' ) ) as $value => $label ) { echo '<label class="openlingua-radio"><input type="radio" name="url_mode" value="' . esc_attr( $value ) . '" ' . checked( $language_settings['url_mode'], $value, false ) . '> ' . esc_html( $label ) . '</label>'; }
		echo '</section><section class="openlingua-card"><h2>3. ' . esc_html__( 'Language selector', 'openlingua' ) . '</h2><label class="openlingua-check"><input type="checkbox" name="show_flag" value="1" checked> ' . esc_html__( 'Show flags or symbols', 'openlingua' ) . '</label><label class="openlingua-check"><input type="checkbox" name="show_name" value="1" checked> ' . esc_html__( 'Show language names', 'openlingua' ) . '</label><label class="openlingua-check"><input type="checkbox" name="dropdown" value="1" ' . checked( ! empty( $language_settings['switcher']['dropdown'] ), true, false ) . '> ' . esc_html__( 'Use a dropdown', 'openlingua' ) . '</label></section>';
		submit_button( __( 'Finish setup', 'openlingua' ), 'primary large' ); echo '</form></div>';
	}

	public static function page() {
		$s = self::get();
		echo '<div class="wrap openlingua-settings"><h1>' . esc_html__( 'OpenLingua settings', 'openlingua' ) . '</h1><p>' . esc_html__( 'Control how translations are created, reviewed, indexed, and maintained.', 'openlingua' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="openlingua_save_site_settings">'; wp_nonce_field( 'openlingua_save_site_settings' );
		self::translation_section( $s ); self::automation_section( $s ); self::seo_section( $s ); self::maintenance_section( $s );
		submit_button( __( 'Save OpenLingua settings', 'openlingua' ), 'primary large' ); echo '</form></div>';
	}

	private static function translation_section( $s ) {
		echo '<section class="openlingua-card"><h2>' . esc_html__( 'Translation behavior', 'openlingua' ) . '</h2><table class="form-table"><tr><th>' . esc_html__( 'Missing translation', 'openlingua' ) . '</th><td>' . self::select( 'missing_translation', $s['missing_translation'], array( 'hide' => __( 'Hide unavailable languages', 'openlingua' ), 'home' => __( 'Link to that language homepage', 'openlingua' ), 'fallback' => __( 'Use configured fallback language', 'openlingua' ) ) ) . '</td></tr><tr><th>' . esc_html__( 'New translation status', 'openlingua' ) . '</th><td>' . self::select( 'new_translation_status', $s['new_translation_status'], array( 'draft' => __( 'Draft', 'openlingua' ), 'pending' => __( 'Pending review', 'openlingua' ), 'publish' => __( 'Publish when permitted', 'openlingua' ) ) ) . '</td></tr><tr><th>' . esc_html__( 'Translated slug', 'openlingua' ) . '</th><td>' . self::select( 'slug_mode', $s['slug_mode'], array( 'translate' => __( 'Create from translated title', 'openlingua' ), 'source' => __( 'Keep source slug', 'openlingua' ), 'manual' => __( 'Change only when edited manually', 'openlingua' ) ) ) . '</td></tr><tr><th>' . esc_html__( 'When source changes', 'openlingua' ) . '</th><td>' . self::select( 'source_change', $s['source_change'], array( 'mark' => __( 'Mark translation outdated', 'openlingua' ), 'notify' => __( 'Mark outdated and notify', 'openlingua' ), 'automatic' => __( 'Queue automatic retranslation', 'openlingua' ) ) ) . '</td></tr></table>';
		foreach ( array( 'copy_author' => __( 'Copy author', 'openlingua' ), 'copy_date' => __( 'Copy publication date', 'openlingua' ), 'copy_thumbnail' => __( 'Copy featured image', 'openlingua' ), 'copy_template' => __( 'Copy page template', 'openlingua' ) ) as $key => $label ) { echo self::checkbox( $key, $s[ $key ], $label ); } echo '</section>';
	}

	private static function automation_section( $s ) {
		echo '<section class="openlingua-card"><h2>' . esc_html__( 'Automatic translation workflow', 'openlingua' ) . '</h2>' . self::checkbox( 'automatic_on_create', $s['automatic_on_create'], __( 'Automatically queue translations when new source content is published', 'openlingua' ) ) . '<table class="form-table"><tr><th>' . esc_html__( 'Automatic result', 'openlingua' ) . '</th><td>' . self::select( 'automatic_result', $s['automatic_result'], array( 'review' => __( 'Ready for review', 'openlingua' ), 'draft' => __( 'Keep as draft', 'openlingua' ), 'publish' => __( 'Publish when permitted', 'openlingua' ) ) ) . '</td></tr><tr><th>' . esc_html__( 'Segments per request', 'openlingua' ) . '</th><td><input type="number" min="5" max="100" name="batch_size" value="' . absint( $s['batch_size'] ) . '"></td></tr><tr><th>' . esc_html__( 'Maximum attempts', 'openlingua' ) . '</th><td><input type="number" min="1" max="10" name="max_attempts" value="' . absint( $s['max_attempts'] ) . '"></td></tr><tr><th>' . esc_html__( 'Monthly job limit', 'openlingua' ) . '</th><td><input type="number" min="0" name="monthly_job_limit" value="' . absint( $s['monthly_job_limit'] ) . '"><p class="description">' . esc_html__( 'Use 0 for no plugin-side limit.', 'openlingua' ) . '</p></td></tr></table></section>';
	}

	private static function seo_section( $s ) { echo '<section class="openlingua-card"><h2>' . esc_html__( 'SEO and notifications', 'openlingua' ) . '</h2>' . self::checkbox( 'noindex_incomplete', $s['noindex_incomplete'], __( 'Add noindex to incomplete translations', 'openlingua' ) ) . self::checkbox( 'noindex_fallback', $s['noindex_fallback'], __( 'Add noindex when fallback content is displayed', 'openlingua' ) ) . self::checkbox( 'notify_source_changes', $s['notify_source_changes'], __( 'Notify administrators when source content makes translations outdated', 'openlingua' ) ) . '</section>'; }

	private static function maintenance_section( $s ) { echo '<section class="openlingua-card"><h2>' . esc_html__( 'Maintenance and privacy', 'openlingua' ) . '</h2><table class="form-table"><tr><th>' . esc_html__( 'String discovery duration', 'openlingua' ) . '</th><td><input type="number" min="1" max="1440" name="discovery_minutes" value="' . absint( $s['discovery_minutes'] ) . '"> ' . esc_html__( 'minutes', 'openlingua' ) . '</td></tr><tr><th>' . esc_html__( 'Completed job retention', 'openlingua' ) . '</th><td><input type="number" min="1" max="365" name="completed_job_retention" value="' . absint( $s['completed_job_retention'] ) . '"> ' . esc_html__( 'days', 'openlingua' ) . '</td></tr><tr><th>' . esc_html__( 'On uninstall', 'openlingua' ) . '</th><td>' . self::select( 'uninstall_mode', $s['uninstall_mode'], array( 'preserve' => __( 'Preserve all translation data', 'openlingua' ), 'remove' => __( 'Remove OpenLingua data', 'openlingua' ) ) ) . '<p class="description">' . esc_html__( 'Removal still requires confirmation from the Tools screen before uninstalling.', 'openlingua' ) . '</p></td></tr></table></section>'; }

	private static function select( $name, $current, array $options ) { $html = '<select name="' . esc_attr( $name ) . '">'; foreach ( $options as $value => $label ) { $html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>'; } return $html . '</select>'; }
	private static function checkbox( $name, $checked, $label ) { return '<label class="openlingua-check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $checked ), true, false ) . '> ' . esc_html( $label ) . '</label>'; }

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'openlingua' ) ); } check_admin_referer( 'openlingua_save_site_settings' );
		$d = self::defaults(); $clean = array();
		$allowed = array( 'missing_translation' => array( 'hide', 'home', 'fallback' ), 'new_translation_status' => array( 'draft', 'pending', 'publish' ), 'slug_mode' => array( 'translate', 'source', 'manual' ), 'source_change' => array( 'mark', 'notify', 'automatic' ), 'automatic_result' => array( 'review', 'draft', 'publish' ), 'uninstall_mode' => array( 'preserve', 'remove' ) );
		foreach ( $allowed as $key => $values ) { $value = sanitize_key( wp_unslash( $_POST[ $key ] ?? $d[ $key ] ) ); $clean[ $key ] = in_array( $value, $values, true ) ? $value : $d[ $key ]; }
		foreach ( array( 'copy_author', 'copy_date', 'copy_thumbnail', 'copy_template', 'automatic_on_create', 'noindex_incomplete', 'noindex_fallback', 'notify_source_changes' ) as $key ) { $clean[ $key ] = ! empty( $_POST[ $key ] ); }
		$clean['batch_size'] = min( 100, max( 5, absint( $_POST['batch_size'] ?? 40 ) ) ); $clean['max_attempts'] = min( 10, max( 1, absint( $_POST['max_attempts'] ?? 3 ) ) ); $clean['monthly_job_limit'] = absint( $_POST['monthly_job_limit'] ?? 0 ); $clean['discovery_minutes'] = min( 1440, max( 1, absint( $_POST['discovery_minutes'] ?? 10 ) ) ); $clean['completed_job_retention'] = min( 365, max( 1, absint( $_POST['completed_job_retention'] ?? 30 ) ) ); $clean['notify_roles'] = (array) $d['notify_roles'];
		update_option( self::OPTION, $clean, false ); wp_safe_redirect( admin_url( 'admin.php?page=openlingua-settings&updated=1' ) ); exit;
	}

	public static function complete_setup() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'openlingua' ) ); } check_admin_referer( 'openlingua_complete_setup' );
		$default = sanitize_key( wp_unslash( $_POST['default_language'] ?? '' ) ); if ( \OpenLingua\Languages::is_valid( $default ) ) { update_option( 'openlingua_default_language', $default ); }
		$settings = Language_Settings::get(); $url_mode = sanitize_key( wp_unslash( $_POST['url_mode'] ?? 'directory' ) ); $settings['url_mode'] = in_array( $url_mode, array( 'directory', 'query', 'domain' ), true ) ? $url_mode : 'directory'; $settings['switcher']['show_flag'] = ! empty( $_POST['show_flag'] ); $settings['switcher']['show_name'] = ! empty( $_POST['show_name'] ); $settings['switcher']['dropdown'] = ! empty( $_POST['dropdown'] ); update_option( 'openlingua_language_settings', $settings, false ); update_option( 'openlingua_setup_complete', 1, false ); update_option( 'openlingua_flush_rewrite_rules', 1, false );
		wp_safe_redirect( admin_url( 'admin.php?page=openlingua-settings&setup=complete' ) ); exit;
	}
}
