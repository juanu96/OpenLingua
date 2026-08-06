<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Content {
	public static function hooks() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_language' ), 10, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_link' ) );
		add_action( 'admin_post_openlingua_duplicate', array( __CLASS__, 'duplicate' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_admin_posts' ) );
		add_filter( 'posts_clauses', array( __CLASS__, 'filter_frontend_posts' ), 10, 2 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
	}

	public static function meta_box() {
		foreach ( get_post_types( array( 'show_ui' => true ), 'names' ) as $post_type ) {
			if ( 'attachment' !== $post_type ) {
				add_meta_box( 'openlingua-language', __( 'Languages', 'openlingua' ), array( __CLASS__, 'render_meta_box' ), $post_type, 'side', 'high' );
			}
		}
	}

	public static function render_meta_box( $post ) {
		$row      = Translations::row( 'post', $post->ID );
		$current  = $row ? $row->language : Languages::default_code();
		$group    = Translations::group( 'post', $post->ID );
		wp_nonce_field( 'openlingua_save_post', 'openlingua_nonce' );
		echo '<p><label for="openlingua-language">' . esc_html__( 'Content language', 'openlingua' ) . '</label></p>';
		echo '<select id="openlingua-language" name="openlingua_language" class="widefat">';
		foreach ( Languages::all() as $code => $language ) {
			echo '<option value="' . esc_attr( $code ) . '" ' . selected( $current, $code, false ) . '>' . esc_html( $language['name'] ) . '</option>';
		}
		echo '</select><hr><strong>' . esc_html__( 'Translations', 'openlingua' ) . '</strong>';
		foreach ( Languages::all() as $code => $language ) {
			if ( $code === $current ) { continue; }
			echo '<p>' . esc_html( $language['name'] ) . ': ';
			if ( isset( $group[ $code ] ) ) {
				echo '<a href="' . esc_url( get_edit_post_link( $group[ $code ] ) ) . '">' . esc_html__( 'Edit', 'openlingua' ) . '</a>';
			} else {
				$url = wp_nonce_url( add_query_arg( array( 'action' => 'openlingua_duplicate', 'post_id' => $post->ID, 'language' => $code ), admin_url( 'admin-post.php' ) ), 'openlingua_duplicate_' . $post->ID );
				echo '<a href="' . esc_url( $url ) . '">+ ' . esc_html__( 'Create', 'openlingua' ) . '</a>';
			}
			echo '</p>';
		}
	}

	public static function save_language( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['openlingua_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openlingua_nonce'] ) ), 'openlingua_save_post' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$language = isset( $_POST['openlingua_language'] ) ? sanitize_key( wp_unslash( $_POST['openlingua_language'] ) ) : Languages::default_code();
		$row      = Translations::row( 'post', $post_id );
		Translations::assign( 'post', $post_id, $language, $row ? $row->group_uuid : '', $row ? $row->source_language : '' );
	}

	public static function duplicate() {
		$post_id  = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$language = isset( $_GET['language'] ) ? sanitize_key( wp_unslash( $_GET['language'] ) ) : '';
		check_admin_referer( 'openlingua_duplicate_' . $post_id );
		if ( ! $post_id || ! Languages::is_valid( $language ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You cannot create this translation.', 'openlingua' ) );
		}
		$source = get_post( $post_id );
		$row    = Translations::row( 'post', $post_id );
		if ( ! $row ) {
			$group = Translations::assign( 'post', $post_id, Languages::default_code() );
		} else {
			$group = $row->group_uuid;
		}
		$existing = Translations::translated_id( 'post', $post_id, $language );
		if ( $existing ) {
			wp_safe_redirect( get_edit_post_link( $existing, 'url' ) ); exit;
		}
		$new_id = wp_insert_post( array(
			'post_type' => $source->post_type, 'post_status' => 'draft', 'post_title' => $source->post_title,
			'post_content' => $source->post_content, 'post_excerpt' => $source->post_excerpt,
			'post_parent' => $source->post_parent ? ( Translations::translated_id( 'post', $source->post_parent, $language ) ?: $source->post_parent ) : 0,
			'menu_order' => $source->menu_order,
		) );
		if ( is_wp_error( $new_id ) ) { wp_die( esc_html( $new_id->get_error_message() ) ); }
		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( '_edit_lock' === $key || '_edit_last' === $key ) { continue; }
			foreach ( $values as $value ) { add_post_meta( $new_id, $key, maybe_unserialize( $value ) ); }
		}
		foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
			$term_ids = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $term_ids ) ) {
				$translated_terms = array_map(
					function ( $term_id ) use ( $language ) {
						return Translations::translated_id( 'term', $term_id, $language ) ?: $term_id;
					},
					$term_ids
				);
				wp_set_object_terms( $new_id, $translated_terms, $taxonomy );
			}
		}
		Translations::assign( 'post', $new_id, $language, $group, $row ? $row->language : Languages::default_code() );
		wp_safe_redirect( get_edit_post_link( $new_id, 'url' ) ); exit;
	}

	public static function filter_admin_posts( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || ! isset( $_GET['openlingua_language_filter'] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		global $wpdb;
		$language = sanitize_key( wp_unslash( $_GET['openlingua_language_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT element_id FROM ' . Database::table( 'translations' ) . ' WHERE element_type = %s AND language = %s', 'post', $language ) );
		$query->set( 'post__in', $ids ?: array( 0 ) );
	}

	public static function delete_link( $post_id ) { Translations::delete( 'post', $post_id ); }
	public static function query_vars( $vars ) { $vars[] = 'lang'; return $vars; }

	public static function filter_frontend_posts( $clauses, $query ) {
		if ( is_admin() || ! $query->is_main_query() || $query->is_singular() || $query->get( 'suppress_filters' ) ) { return $clauses; }
		global $wpdb;
		$table    = Database::table( 'translations' );
		$language = Languages::current();
		$default  = Languages::default_code();
		if ( $language === $default ) {
			$clauses['where'] .= $wpdb->prepare( " AND (NOT EXISTS (SELECT 1 FROM {$table} ol_any WHERE ol_any.element_type = 'post' AND ol_any.element_id = {$wpdb->posts}.ID) OR EXISTS (SELECT 1 FROM {$table} ol_lang WHERE ol_lang.element_type = 'post' AND ol_lang.element_id = {$wpdb->posts}.ID AND ol_lang.language = %s))", $language );
		} else {
			$clauses['where'] .= $wpdb->prepare( " AND EXISTS (SELECT 1 FROM {$table} ol_lang WHERE ol_lang.element_type = 'post' AND ol_lang.element_id = {$wpdb->posts}.ID AND ol_lang.language = %s)", $language );
		}
		return $clauses;
	}
}
