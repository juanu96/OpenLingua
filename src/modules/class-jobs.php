<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;
use OpenLingua\Database;
use OpenLingua\Divi_Content;
use OpenLingua\ACF_Content;
use OpenLingua\SEO;
use OpenLingua\Translation_Editor;

defined( 'ABSPATH' ) || exit;

final class Jobs implements Module {
	public static function hooks() {
		add_action( 'openlingua_run_translation_job', array( __CLASS__, 'run' ) );
		add_action( 'admin_post_openlingua_run_job', array( __CLASS__, 'run_from_admin' ) );
		add_action( 'admin_post_openlingua_enqueue_translation', array( __CLASS__, 'enqueue_from_admin' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'openlingua_translation_job_completed', array( __CLASS__, 'remember_completion' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'completion_notice' ) );
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
			'status' => 'pending', 'payload' => wp_json_encode( array( 'requested_by' => get_current_user_id() ) ), 'error' => '', 'created_at' => $now, 'updated_at' => $now,
		) );
		if ( ! $wpdb->insert_id ) { return new \WP_Error( 'openlingua_job_db', $wpdb->last_error ); }
		$job_id = absint( $wpdb->insert_id );
		wp_schedule_single_event( time() + 5, 'openlingua_run_translation_job', array( $job_id ) );
		return $job_id;
	}

	public static function enqueue_url( $source_id, $target_id, $provider_id, $return_to = '' ) {
		$url = add_query_arg( array( 'action' => 'openlingua_enqueue_translation', 'source_id' => absint( $source_id ), 'target_id' => absint( $target_id ), 'provider' => sanitize_key( $provider_id ), 'return_to' => $return_to ), admin_url( 'admin-post.php' ) );
		return wp_nonce_url( $url, 'openlingua_enqueue_translation_' . absint( $target_id ) );
	}

	public static function enqueue_from_admin() {
		$source_id = absint( $_GET['source_id'] ?? 0 );
		$target_id = absint( $_GET['target_id'] ?? 0 );
		$provider_id = sanitize_key( wp_unslash( $_GET['provider'] ?? '' ) );
		check_admin_referer( 'openlingua_enqueue_translation_' . $target_id );
		if ( ! current_user_can( 'edit_post', $source_id ) || ! current_user_can( 'edit_post', $target_id ) ) { wp_die( esc_html__( 'You cannot translate this content.', 'openlingua' ) ); }
		$source = \OpenLingua\Translations::row( 'post', $source_id );
		$target = \OpenLingua\Translations::row( 'post', $target_id );
		if ( ! $source || ! $target || $source->group_uuid !== $target->group_uuid ) { wp_die( esc_html__( 'These posts are not linked translations.', 'openlingua' ) ); }
		$return_to = wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['return_to'] ?? '' ) ), '' );
		$editor_url = Translation_Editor::url( $source_id, $target_id, $return_to );
		$job_id = self::enqueue( $source_id, $target_id, $target->language, $provider_id );
		$status = is_wp_error( $job_id ) ? 'error' : 'queued';
		wp_safe_redirect( add_query_arg( array( 'automatic_translation' => $status, 'provider' => $provider_id ), $editor_url ) );
		exit;
	}

	public static function run( $job_id ) {
		global $wpdb;
		$table = Database::table( 'jobs' );
		$job = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, absint( $job_id ) ) );
		if ( ! $job || ! in_array( $job->status, array( 'pending', 'failed' ), true ) ) { return false; }
		$provider = Providers::get( $job->provider );
		$source   = get_post( $job->source_id );
		$target   = get_post( $job->target_id );
		if ( ! $provider || ! $provider->is_configured() || ! $source || ! $target ) { return self::fail( $job_id, __( 'Provider or source content is unavailable.', 'openlingua' ) ); }
		$wpdb->update( $table, array( 'status' => 'processing', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $job_id ) );
		$is_divi = Divi_Content::is_divi( $source->post_content );
		$divi_segments = $is_divi ? Divi_Content::extract( $source->post_content ) : array();
		$acf_segments = ACF_Content::extract( $source->ID );
		$seo_groups = SEO::translation_fields( $source->ID, $target->ID );
		$segments = array( 'title' => $source->post_title, 'excerpt' => $source->post_excerpt );
		if ( $is_divi ) {
			foreach ( $divi_segments as $segment ) { $segments[ $segment['id'] ] = $segment['value']; }
		} else {
			$segments['content'] = $source->post_content;
		}
		foreach ( $acf_segments as $segment ) { $segments[ 'acf__' . $segment['id'] ] = $segment['value']; }
		foreach ( $seo_groups as $group ) {
			foreach ( $group['fields'] as $field ) { if ( '' !== trim( $field['source'] ) ) { $segments[ 'seo__' . $field['id'] ] = $field['source']; } }
		}
		$result = $provider->translate( $segments, self::source_language( $job->source_id ), $job->target_language, array( 'post_id' => absint( $job->source_id ) ) );
		if ( is_wp_error( $result ) ) { return self::fail( $job_id, $result->get_error_message() ); }
		if ( ! is_array( $result ) || ! isset( $result['title'] ) || ( ! $is_divi && ! isset( $result['content'] ) ) ) { return self::fail( $job_id, __( 'Provider returned an invalid response.', 'openlingua' ) ); }
		if ( $is_divi ) {
			$translated_divi = array();
			foreach ( $divi_segments as $segment ) {
				if ( ! array_key_exists( $segment['id'], $result ) ) { continue; }
				$translated_divi[ $segment['id'] ] = 'attribute' === $segment['kind'] ? sanitize_text_field( $result[ $segment['id'] ] ) : wp_kses_post( $result[ $segment['id'] ] );
			}
			$base_content = Divi_Content::is_divi( $target->post_content ) ? $target->post_content : $source->post_content;
			$content = Divi_Content::apply( $base_content, $translated_divi );
		} else {
			$content = wp_kses_post( $result['content'] );
		}
		$translated_title = sanitize_text_field( $result['title'] );
		$update = array( 'ID' => absint( $job->target_id ), 'post_title' => $translated_title, 'post_excerpt' => wp_kses_post( $result['excerpt'] ?? '' ), 'post_content' => $content );
		$desired_slug = sanitize_title( $translated_title );
		if ( $desired_slug && ( ! $target->post_name || 'draft' === $target->post_status || sanitize_title( $source->post_title ) === $target->post_name || preg_match( '/^' . preg_quote( $desired_slug, '/' ) . '-\d+$/', $target->post_name ) ) ) {
			$update['post_name'] = wp_unique_post_slug( $desired_slug, $target->ID, $target->post_status, $target->post_type, $target->post_parent );
		}
		$updated = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $updated ) ) { return self::fail( $job_id, $updated->get_error_message() ); }
		$acf_translation = array();
		foreach ( $acf_segments as $segment ) {
			$key = 'acf__' . $segment['id'];
			if ( array_key_exists( $key, $result ) ) { $acf_translation[ $segment['id'] ] = $result[ $key ]; }
		}
		ACF_Content::save( $source->ID, $target->ID, $acf_translation, true );
		$seo_translation = array();
		foreach ( $seo_groups as $group ) {
			foreach ( $group['fields'] as $field ) {
				$key = 'seo__' . $field['id'];
				if ( array_key_exists( $key, $result ) ) { $seo_translation[ $field['id'] ] = $result[ $key ]; }
			}
		}
		SEO::save_translation_fields( $source->ID, $target->ID, $seo_translation );
		update_post_meta( $job->target_id, Workflow::STATUS_META, 'in-progress' );
		$payload = json_decode( (string) $job->payload, true );
		$payload = is_array( $payload ) ? $payload : array();
		$payload['segments'] = array_keys( $segments );
		$wpdb->update( $table, array( 'status' => 'complete', 'payload' => wp_json_encode( $payload ), 'error' => '', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $job_id ) );
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

	public static function remember_completion( $job_id, $job ) {
		$payload = json_decode( (string) $job->payload, true );
		$user_id = absint( $payload['requested_by'] ?? 0 );
		if ( ! $user_id ) { return; }
		$notices = (array) get_user_meta( $user_id, '_openlingua_completed_jobs', true );
		$notices[] = array( 'job_id' => absint( $job_id ), 'source_id' => absint( $job->source_id ), 'target_id' => absint( $job->target_id ), 'time' => time() );
		update_user_meta( $user_id, '_openlingua_completed_jobs', array_slice( $notices, -10 ) );
	}

	public static function completion_notice() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) { return; }
		$notices = (array) get_user_meta( $user_id, '_openlingua_completed_jobs', true );
		if ( ! $notices ) { return; }
		delete_user_meta( $user_id, '_openlingua_completed_jobs' );
		foreach ( $notices as $notice ) {
			$source_id = absint( $notice['source_id'] ?? 0 );
			$target_id = absint( $notice['target_id'] ?? 0 );
			if ( ! $source_id || ! $target_id || ! get_post( $source_id ) || ! get_post( $target_id ) ) { continue; }
			$url = Translation_Editor::url( $source_id, $target_id );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'OpenLingua finished the automatic translation. It is ready for review.', 'openlingua' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Review translation', 'openlingua' ) . '</a></p></div>';
		}
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

	public static function assets( $hook ) {
		if ( 'openlingua_page_openlingua-jobs' !== $hook ) { return; }
		wp_enqueue_style( 'openlingua-jobs', plugins_url( 'assets/admin-jobs.css', OPENLINGUA_FILE ), array(), OPENLINGUA_VERSION );
	}

	private static function status_label( $status ) {
		$labels = array(
			'pending' => __( 'Queued', 'openlingua' ),
			'processing' => __( 'Translating', 'openlingua' ),
			'complete' => __( 'Ready for review', 'openlingua' ),
			'failed' => __( 'Failed', 'openlingua' ),
		);
		return $labels[ $status ] ?? ucfirst( $status );
	}

	public static function page() {
		global $wpdb;
		$jobs = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 100', Database::table( 'jobs' ) ) );
		echo '<div class="wrap openlingua-jobs"><h1>' . esc_html__( 'Translation jobs', 'openlingua' ) . '</h1>';
		echo '<p>' . esc_html__( 'Automatic jobs start on their own. This screen shows whether each translation is waiting, running, ready to review, or needs attention.', 'openlingua' ) . '</p>';
		echo '<div class="openlingua-jobs__legend"><span class="openlingua-job-status openlingua-job-status--pending">' . esc_html__( 'Queued', 'openlingua' ) . '</span><span class="openlingua-job-status openlingua-job-status--processing">' . esc_html__( 'Translating', 'openlingua' ) . '</span><span class="openlingua-job-status openlingua-job-status--complete">' . esc_html__( 'Ready for review', 'openlingua' ) . '</span><span class="openlingua-job-status openlingua-job-status--failed">' . esc_html__( 'Failed', 'openlingua' ) . '</span></div>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Source', 'openlingua' ) . '</th><th>' . esc_html__( 'Target', 'openlingua' ) . '</th><th>' . esc_html__( 'Provider', 'openlingua' ) . '</th><th>' . esc_html__( 'Status', 'openlingua' ) . '</th><th>' . esc_html__( 'Action', 'openlingua' ) . '</th></tr></thead><tbody>';
		foreach ( $jobs as $job ) {
			$url = wp_nonce_url( add_query_arg( array( 'action' => 'openlingua_run_job', 'job_id' => $job->id ), admin_url( 'admin-post.php' ) ), 'openlingua_run_job_' . $job->id );
			$source = get_post( $job->source_id );
			$target = get_post( $job->target_id );
			$source_label = $source ? get_the_title( $source ) . ' (#' . absint( $job->source_id ) . ')' : '#' . absint( $job->source_id );
			$target_label = $target ? get_the_title( $target ) . ' (#' . absint( $job->target_id ) . ')' : '#' . absint( $job->target_id );
			$action = '&mdash;';
			if ( 'failed' === $job->status ) { $action = '<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Retry', 'openlingua' ) . '</a>'; }
			elseif ( 'complete' === $job->status && $source && $target ) { $action = '<a class="button button-primary" href="' . esc_url( Translation_Editor::url( $source->ID, $target->ID ) ) . '">' . esc_html__( 'Review translation', 'openlingua' ) . '</a>'; }
			elseif ( 'pending' === $job->status ) { $action = '<span class="description">' . esc_html__( 'Starts automatically', 'openlingua' ) . '</span>'; }
			elseif ( 'processing' === $job->status ) { $action = '<span class="description">' . esc_html__( 'Please wait', 'openlingua' ) . '</span>'; }
			echo '<tr><td>' . absint( $job->id ) . '</td><td>' . esc_html( $source_label ) . '</td><td>' . esc_html( $target_label ) . '</td><td>' . esc_html( $job->provider ) . '</td><td><span class="openlingua-job-status openlingua-job-status--' . esc_attr( $job->status ) . '">' . esc_html( self::status_label( $job->status ) ) . '</span>' . ( $job->error ? '<div class="openlingua-job-error">' . esc_html( $job->error ) . '</div>' : '' ) . '</td><td>' . $action . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
