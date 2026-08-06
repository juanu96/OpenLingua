<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;
use OpenLingua\Database;
use OpenLingua\Module_Registry;

defined( 'ABSPATH' ) || exit;

final class Diagnostics implements Module {
	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_filter( 'debug_information', array( __CLASS__, 'site_health' ) );
	}

	public static function report() {
		global $wpdb;
		$tables = array();
		foreach ( array( 'translations', 'strings', 'jobs' ) as $name ) {
			$table = Database::table( $name );
			$tables[ $name ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		}
		return array(
			'version' => OPENLINGUA_VERSION, 'database_version' => get_option( 'openlingua_db_version' ),
			'languages' => count( \OpenLingua\Languages::all() ), 'modules' => Module_Registry::all(),
			'tables' => $tables, 'providers' => array_keys( Providers::all() ), 'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
		);
	}

	public static function admin_menu() {
		add_submenu_page( 'openlingua', __( 'Diagnostics', 'openlingua' ), __( 'Diagnostics', 'openlingua' ), 'manage_options', 'openlingua-diagnostics', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		echo '<div class="wrap"><h1>' . esc_html__( 'OpenLingua diagnostics', 'openlingua' ) . '</h1><textarea class="large-text code" rows="24" readonly>' . esc_textarea( wp_json_encode( self::report(), JSON_PRETTY_PRINT ) ) . '</textarea></div>';
	}

	public static function site_health( $info ) {
		$report = self::report();
		$fields = array();
		foreach ( array( 'version', 'database_version', 'languages', 'cron_disabled' ) as $key ) { $fields[ $key ] = array( 'label' => ucwords( str_replace( '_', ' ', $key ) ), 'value' => is_bool( $report[ $key ] ) ? ( $report[ $key ] ? 'true' : 'false' ) : (string) $report[ $key ] ); }
		$info['openlingua'] = array( 'label' => 'OpenLingua', 'description' => __( 'Multilingual plugin health information.', 'openlingua' ), 'fields' => $fields );
		return $info;
	}
}
