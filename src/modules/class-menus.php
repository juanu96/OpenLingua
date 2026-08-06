<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;
use OpenLingua\Languages;

defined( 'ABSPATH' ) || exit;

final class Menus implements Module {
	public static function hooks() {
		add_filter( 'wp_nav_menu_args', array( __CLASS__, 'translate_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_openlingua_save_menus', array( __CLASS__, 'save' ) );
	}

	public static function set( $location, $language, $menu_id ) {
		if ( ! Languages::is_valid( $language ) ) { return false; }
		$map = get_option( 'openlingua_menu_map', array() );
		$map[ sanitize_key( $location ) ][ sanitize_key( $language ) ] = absint( $menu_id );
		return update_option( 'openlingua_menu_map', $map );
	}

	public static function get( $location, $language = '' ) {
		$map = get_option( 'openlingua_menu_map', array() );
		$language = $language ?: Languages::current();
		return absint( $map[ sanitize_key( $location ) ][ sanitize_key( $language ) ] ?? 0 );
	}

	public static function translate_menu( $args ) {
		if ( empty( $args['theme_location'] ) ) { return $args; }
		$menu_id = self::get( $args['theme_location'] );
		if ( $menu_id ) { $args['menu'] = $menu_id; }
		return $args;
	}

	public static function admin_menu() {
		add_submenu_page( 'openlingua', __( 'Menu translations', 'openlingua' ), __( 'Menus', 'openlingua' ), 'manage_options', 'openlingua-menus', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		$locations = get_registered_nav_menus();
		$menus = wp_get_nav_menus();
		echo '<div class="wrap"><h1>' . esc_html__( 'Menu translations', 'openlingua' ) . '</h1><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="openlingua_save_menus">';
		wp_nonce_field( 'openlingua_save_menus' );
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Theme location', 'openlingua' ) . '</th>';
		foreach ( Languages::all() as $language ) { echo '<th>' . esc_html( $language['name'] ) . '</th>'; }
		echo '</tr></thead><tbody>';
		foreach ( $locations as $location => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th>';
			foreach ( Languages::all() as $code => $language ) {
				echo '<td><select name="menus[' . esc_attr( $location ) . '][' . esc_attr( $code ) . ']"><option value="0">' . esc_html__( 'Theme default', 'openlingua' ) . '</option>';
				foreach ( $menus as $menu ) { echo '<option value="' . absint( $menu->term_id ) . '" ' . selected( self::get( $location, $code ), $menu->term_id, false ) . '>' . esc_html( $menu->name ) . '</option>'; }
				echo '</select></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>'; submit_button(); echo '</form></div>';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'openlingua' ) ); }
		check_admin_referer( 'openlingua_save_menus' );
		$clean = array();
		foreach ( (array) ( $_POST['menus'] ?? array() ) as $location => $languages ) {
			foreach ( (array) $languages as $code => $menu_id ) {
				if ( Languages::is_valid( $code ) ) { $clean[ sanitize_key( $location ) ][ sanitize_key( $code ) ] = absint( $menu_id ); }
			}
		}
		update_option( 'openlingua_menu_map', $clean );
		wp_safe_redirect( add_query_arg( array( 'page' => 'openlingua-menus', 'updated' => 1 ), admin_url( 'admin.php' ) ) ); exit;
	}
}
