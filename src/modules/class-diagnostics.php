<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;
use OpenLingua\Database;
use OpenLingua\Module_Registry;

defined( 'ABSPATH' ) || exit;

final class Diagnostics implements Module {
	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'debug_information', array( __CLASS__, 'site_health' ) );
	}

	public static function report() {
		global $wpdb;
		$tables = array();
		foreach ( array( 'translations', 'strings', 'jobs' ) as $name ) {
			$table = Database::table( $name );
			$tables[ $name ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		}
		$providers = array();
		foreach ( Providers::all() as $id => $provider ) { $providers[ $id ] = array( 'name' => $provider->label(), 'configured' => $provider->is_configured() ); }
		return array(
			'version' => OPENLINGUA_VERSION, 'database_version' => get_option( 'openlingua_db_version' ),
			'languages' => count( \OpenLingua\Languages::all() ), 'modules' => Module_Registry::all(),
			'url_mode' => \OpenLingua\Modules\Language_Settings::get()['url_mode'],
			'tables' => $tables, 'providers' => array_keys( Providers::all() ), 'provider_status' => $providers, 'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
		);
	}

	public static function admin_menu() {
		add_submenu_page( 'openlingua', __( 'Diagnostics', 'openlingua' ), __( 'Diagnostics', 'openlingua' ), 'manage_options', 'openlingua-diagnostics', array( __CLASS__, 'page' ) );
	}

	public static function assets( $hook ) {
		if ( 'openlingua_page_openlingua-diagnostics' !== $hook ) { return; }
		wp_enqueue_style( 'openlingua-diagnostics', plugins_url( 'assets/admin-diagnostics.css', OPENLINGUA_FILE ), array(), OPENLINGUA_VERSION );
	}

	public static function page() {
		$report = self::report();
		$tables_ok = ! in_array( false, $report['tables'], true );
		$configured = array_filter( $report['provider_status'], static function( $provider ) { return ! empty( $provider['configured'] ); } );
		/* translators: %d: number of enabled languages. */
		$enabled_languages_message = sprintf( _n( '%d language is enabled.', '%d languages are enabled.', $report['languages'], 'openlingua' ), $report['languages'] );
		/* translators: %d: number of configured translation services. */
		$configured_services_message = sprintf( _n( '%d translation service is configured.', '%d translation services are configured.', count( $configured ), 'openlingua' ), count( $configured ) );
		$checks = array(
			array( 'status' => $tables_ok ? 'good' : 'critical', 'title' => __( 'Translation database', 'openlingua' ), 'message' => $tables_ok ? __( 'The storage needed for translations is working correctly.', 'openlingua' ) : __( 'One or more translation tables are missing. OpenLingua cannot reliably save all translation data.', 'openlingua' ), 'action' => $tables_ok ? '' : admin_url( 'plugins.php' ), 'action_label' => __( 'Review installed plugins', 'openlingua' ) ),
			array( 'status' => $report['languages'] > 1 ? 'good' : 'warning', 'title' => __( 'Site languages', 'openlingua' ), 'message' => $report['languages'] > 1 ? $enabled_languages_message : __( 'Only one language is enabled. Add another language before creating translations.', 'openlingua' ), 'action' => admin_url( 'admin.php?page=openlingua' ), 'action_label' => __( 'Manage languages', 'openlingua' ) ),
			array( 'status' => $configured ? 'good' : 'info', 'title' => __( 'Automatic translation', 'openlingua' ), 'message' => $configured ? $configured_services_message : __( 'No automatic translation service is configured. Manual translation will continue to work normally.', 'openlingua' ), 'action' => admin_url( 'admin.php?page=openlingua-fields' ), 'action_label' => __( 'Configure services', 'openlingua' ) ),
			array( 'status' => $report['cron_disabled'] ? 'warning' : 'good', 'title' => __( 'Background translations', 'openlingua' ), 'message' => $report['cron_disabled'] ? __( 'WordPress automatic scheduling is disabled. Translation jobs require a server cron task to run on time.', 'openlingua' ) : __( 'WordPress can start queued translation jobs automatically.', 'openlingua' ), 'action' => admin_url( 'admin.php?page=openlingua-jobs' ), 'action_label' => __( 'View translation jobs', 'openlingua' ) ),
		);
		$attention = count( array_filter( $checks, static function( $check ) { return in_array( $check['status'], array( 'warning', 'critical' ), true ); } ) );
		/* translators: %d: number of diagnostic items requiring attention. */
		$attention_message = sprintf( _n( '%d item below may require action.', '%d items below may require action.', $attention, 'openlingua' ), $attention );
		echo '<div class="wrap openlingua-diagnostics"><h1>' . esc_html__( 'OpenLingua site health', 'openlingua' ) . '</h1><p class="openlingua-diagnostics__intro">' . esc_html__( 'A simple overview of the features OpenLingua needs to translate your website.', 'openlingua' ) . '</p>';
		echo '<div class="openlingua-health-summary openlingua-health-summary--' . ( $attention ? 'attention' : 'good' ) . '"><span class="dashicons ' . ( $attention ? 'dashicons-warning' : 'dashicons-yes-alt' ) . '" aria-hidden="true"></span><div><h2>' . esc_html( $attention ? __( 'Some items need your attention', 'openlingua' ) : __( 'OpenLingua is ready', 'openlingua' ) ) . '</h2><p>' . esc_html( $attention ? $attention_message : __( 'The main translation components are working correctly.', 'openlingua' ) ) . '</p></div></div>';
		echo '<div class="openlingua-health-grid">';
		foreach ( $checks as $check ) {
			$labels = array( 'good' => __( 'Working', 'openlingua' ), 'warning' => __( 'Check this', 'openlingua' ), 'critical' => __( 'Action required', 'openlingua' ), 'info' => __( 'Optional', 'openlingua' ) );
			echo '<section class="openlingua-health-card openlingua-health-card--' . esc_attr( $check['status'] ) . '"><div class="openlingua-health-card__top"><h2>' . esc_html( $check['title'] ) . '</h2><span class="openlingua-health-badge">' . esc_html( $labels[ $check['status'] ] ) . '</span></div><p>' . esc_html( $check['message'] ) . '</p>';
			if ( $check['action'] ) { echo '<a class="button" href="' . esc_url( $check['action'] ) . '">' . esc_html( $check['action_label'] ) . '</a>'; }
			echo '</section>';
		}
		echo '</div><section class="openlingua-diagnostics__details"><h2>' . esc_html__( 'Installation details', 'openlingua' ) . '</h2><dl><div><dt>' . esc_html__( 'OpenLingua version', 'openlingua' ) . '</dt><dd>' . esc_html( $report['version'] ) . '</dd></div><div><dt>' . esc_html__( 'Language URL format', 'openlingua' ) . '</dt><dd>' . esc_html( self::url_mode_label( $report['url_mode'] ) ) . '</dd></div><div><dt>' . esc_html__( 'Available translation services', 'openlingua' ) . '</dt><dd>' . esc_html( implode( ', ', array_map( static function( $provider ) { return $provider['name']; }, $report['provider_status'] ) ) ) . '</dd></div></dl>';
		echo '<details><summary>' . esc_html__( 'Technical information for support', 'openlingua' ) . '</summary><p>' . esc_html__( 'A developer or support technician may ask you to copy this information. It does not contain your API keys.', 'openlingua' ) . '</p><textarea class="large-text code" rows="16" readonly>' . esc_textarea( wp_json_encode( $report, JSON_PRETTY_PRINT ) ) . '</textarea></details></section>';
		self::builder_inspector();
		echo '</div>';
	}

	private static function builder_inspector() {
		$post_id = isset( $_GET['inspect_post'] ) ? absint( $_GET['inspect_post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only diagnostic filter.
		echo '<section class="openlingua-diagnostics__details"><h2>' . esc_html__( 'Visual builder field inspector', 'openlingua' ) . '</h2><p>' . esc_html__( 'Check which custom fields OpenLingua can safely recognize as structured visual-builder content. This tool does not change the page.', 'openlingua' ) . '</p>';
		echo '<form method="get"><input type="hidden" name="page" value="openlingua-diagnostics"><label for="openlingua-inspect-post"><strong>' . esc_html__( 'Page or post ID', 'openlingua' ) . '</strong></label> <input id="openlingua-inspect-post" name="inspect_post" type="number" min="1" value="' . esc_attr( $post_id ) . '"> <button class="button button-secondary">' . esc_html__( 'Inspect fields', 'openlingua' ) . '</button></form>';
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) { echo '<p class="notice notice-error inline"><span>' . esc_html__( 'That content item could not be found.', 'openlingua' ) . '</span></p>'; }
			else {
				$labels = array(
					'translatable-document'  => __( 'Recognized builder content', 'openlingua' ),
					'acf-field'               => __( 'Managed separately by ACF support', 'openlingua' ),
					'excluded-key'            => __( 'Technical field excluded for safety', 'openlingua' ),
					'opaque-object'           => __( 'Object data cannot be edited safely', 'openlingua' ),
					'not-a-builder-document'  => __( 'Not recognized as builder content', 'openlingua' ),
				);
				echo '<h3>' . esc_html( get_the_title( $post ) ?: sprintf( __( 'Content #%d', 'openlingua' ), $post_id ) ) . '</h3><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Custom field', 'openlingua' ) . '</th><th>' . esc_html__( 'Result', 'openlingua' ) . '</th></tr></thead><tbody>';
				foreach ( \OpenLingua\Structured_Meta_Content::inspect( $post ) as $key => $reason ) { echo '<tr><td><code>' . esc_html( $key ) . '</code></td><td>' . esc_html( $labels[ $reason ] ?? $reason ) . '</td></tr>'; }
				echo '</tbody></table>';
			}
		}
		echo '</section>';
	}

	private static function url_mode_label( $mode ) {
		$labels = array( 'directory' => __( 'Language directories, such as /es/', 'openlingua' ), 'query' => __( 'Language parameter, such as ?lang=es', 'openlingua' ), 'domain' => __( 'A separate domain for each language', 'openlingua' ) );
		return $labels[ $mode ] ?? $mode;
	}

	public static function site_health( $info ) {
		$report = self::report();
		$fields = array();
		foreach ( array( 'version', 'database_version', 'languages', 'cron_disabled' ) as $key ) { $fields[ $key ] = array( 'label' => ucwords( str_replace( '_', ' ', $key ) ), 'value' => is_bool( $report[ $key ] ) ? ( $report[ $key ] ? 'true' : 'false' ) : (string) $report[ $key ] ); }
		$info['openlingua'] = array( 'label' => 'OpenLingua', 'description' => __( 'Multilingual plugin health information.', 'openlingua' ), 'fields' => $fields );
		return $info;
	}
}
