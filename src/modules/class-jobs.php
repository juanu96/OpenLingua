<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;
use OpenLingua\Database;

defined( 'ABSPATH' ) || exit;

final class Jobs implements Module {
	public static function hooks() {
		add_action( 'openlingua_run_translation_job', array( __CLASS__, 'run' ) );
		add_action( 'admin_post_openlingua_run_job', array( __CLASS__, 'run_from_admin' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}

	public static function enqueue( $source_id, $target_id, $target_language, $provider_id ) {
		global $wpdb;
		$source = get_post( $source_id );
		$target = get_post( $target_id );
		$provider = Providers::get( $provider_id );
		if ( ! $source || ! $target || ! $provider || ! $provider->is_configured() ) {
			return new \WP_Error( 'openlingua_invalid_job', __( 'The translation job is not valid or the provider is not configured.', 'openlingua' ) );
		}
		$now = current_time( 'mysql' );
		$wpdb->insert( Database::table( 'jobs' ), array(
			'source_id' => absint( $source_id ), 'target_id' => absint( $target_id ),
			'target_language' => sanitize_key( $target_language ), 'provider' => sanitize_key( $provider_id ),
			'status' => 'pending', 'payload' => '{}', 'error' => '', 'created_at' => $now, 'updated_at' => $now,
		) );
		if ( ! $wpdb->insert_id ) { return new \WP_Error( 'openlingua_job_db', $wpdb->last_error ); }
		$job_id = absint( $wpdb->insert_id );
		wp_schedule_single_event( time() + 5, 'openlingua_run_translation_job', array( $job_id ) );
		return $job_id;
	}

	public static function run( $job_id ) {
		global $wpdb;
		$table = Database::table( 'jobs' );
		$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $job_id ) ) );
		if ( ! $job || ! in_array( $job->status, array( 'pending', 'failed' ), true ) ) { return false; }
		$provider = Providers::get( $job->provider );
		$source   = get_post( $job->source_id );
		if ( ! $provider || ! $provider->is_configured() || ! $source ) { return self::fail( $job_id, __( 'Provider or source content is unavailable.', 'openlingua' ) ); }
		$wpdb->update( $table, array( 'status' => 'processing', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $job_id ) );
		$segments = array( 'title' => $source->post_title, 'excerpt' => $source->post_excerpt, 'content' => $source->post_content );
		$result = $provider->translate( $segments, self::source_language( $job->source_id ), $job->target_language, array( 'post_id' => absint( $job->source_id ) ) );
		if ( is_wp_error( $result ) ) { return self::fail( $job_id, $result->get_error_message() ); }
		if ( ! is_array( $result ) || ! isset( $result['title'], $result['content'] ) ) { return self::fail( $job_id, __( 'Provider returned an invalid response.', 'openlingua' ) ); }
		$updated = wp_update_post( array( 'ID' => absint( $job->target_id ), 'post_title' => wp_kses_post( $result['title'] ), 'post_excerpt' => wp_kses_post( $result['excerpt'] ?? '' ), 'post_content' => wp_kses_post( $result['content'] ) ), true );
		if ( is_wp_error( $updated ) ) { return self::fail( $job_id, $updated->get_error_message() ); }
		update_post_meta( $job->target_id, Workflow::STATUS_META, 'in-progress' );
		$wpdb->update( $table, array( 'status' => 'complete', 'payload' => wp_json_encode( array( 'segments' => array_keys( $segments ) ) ), 'error' => '', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $job_id ) );
		do_action( 'openlingua_translation_job_completed', $job_id, $job );
		return true;
	}

	private static function source_language( $post_id ) {
		$row = \OpenLingua\Translations::row( 'post', $post_id );
		return $row ? $row->language : \OpenLingua\Languages::default_code();
	}

	private static function fail( $job_id, $message ) {
		global $wpdb;
		$wpdb->update( Database::table( 'jobs' ), array( 'status' => 'failed', 'error' => sanitize_textarea_field( $message ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $job_id ) ) );
		return new \WP_Error( 'openlingua_job_failed', $message );
	}

	public static function run_from_admin() {
		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
		check_admin_referer( 'openlingua_run_job_' . $job_id );
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permission denied.', 'openlingua' ) ); }
		self::run( $job_id );
		wp_safe_redirect( add_query_arg( 'page', 'openlingua-jobs', admin_url( 'admin.php' ) ) ); exit;
	}

	public static function admin_menu() {
		add_submenu_page( 'openlingua', __( 'Translation jobs', 'openlingua' ), __( 'Jobs', 'openlingua' ), 'manage_options', 'openlingua-jobs', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		global $wpdb;
		$jobs = $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'jobs' ) . ' ORDER BY id DESC LIMIT 100' );
		echo '<div class="wrap"><h1>' . esc_html__( 'Translation jobs', 'openlingua' ) . '</h1><table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Source', 'openlingua' ) . '</th><th>' . esc_html__( 'Target', 'openlingua' ) . '</th><th>' . esc_html__( 'Provider', 'openlingua' ) . '</th><th>' . esc_html__( 'Status', 'openlingua' ) . '</th><th>' . esc_html__( 'Action', 'openlingua' ) . '</th></tr></thead><tbody>';
		foreach ( $jobs as $job ) {
			$url = wp_nonce_url( add_query_arg( array( 'action' => 'openlingua_run_job', 'job_id' => $job->id ), admin_url( 'admin-post.php' ) ), 'openlingua_run_job_' . $job->id );
			echo '<tr><td>' . absint( $job->id ) . '</td><td>' . absint( $job->source_id ) . '</td><td>' . absint( $job->target_id ) . '</td><td>' . esc_html( $job->provider ) . '</td><td>' . esc_html( $job->status ) . ( $job->error ? '<br><small>' . esc_html( $job->error ) . '</small>' : '' ) . '</td><td>' . ( in_array( $job->status, array( 'pending', 'failed' ), true ) ? '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Run', 'openlingua' ) . '</a>' : '&mdash;' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
