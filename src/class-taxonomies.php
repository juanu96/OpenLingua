<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Taxonomies {
	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'register_fields' ), 99 );
		add_action( 'created_term', array( __CLASS__, 'save' ), 10, 3 );
		add_action( 'edited_term', array( __CLASS__, 'save' ), 10, 3 );
		add_action( 'delete_term', array( __CLASS__, 'delete' ), 10, 1 );
		add_action( 'admin_post_openlingua_duplicate_term', array( __CLASS__, 'duplicate' ) );
	}

	public static function register_fields() {
		foreach ( get_taxonomies( array( 'show_ui' => true ), 'names' ) as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'add_fields' ) );
			add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'edit_fields' ), 10, 2 );
		}
	}

	public static function add_fields( $taxonomy ) {
		$language = isset( $_GET['openlingua_language'] ) ? sanitize_key( wp_unslash( $_GET['openlingua_language'] ) ) : Languages::default_code(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source   = isset( $_GET['openlingua_source_term'] ) ? absint( $_GET['openlingua_source_term'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_nonce_field( 'openlingua_save_term', 'openlingua_term_nonce' );
		echo '<div class="form-field"><label for="openlingua-term-language">' . esc_html__( 'Language', 'openlingua' ) . '</label>';
		self::select( $language );
		if ( $source ) { echo '<input type="hidden" name="openlingua_source_term" value="' . absint( $source ) . '">'; }
		echo '</div>';
	}

	public static function edit_fields( $term, $taxonomy ) {
		$row     = Translations::row( 'term', $term->term_id );
		$current = $row ? $row->language : Languages::default_code();
		$group   = Translations::group( 'term', $term->term_id );
		wp_nonce_field( 'openlingua_save_term', 'openlingua_term_nonce' );
		echo '<tr class="form-field"><th><label for="openlingua-term-language">' . esc_html__( 'Language', 'openlingua' ) . '</label></th><td>';
		self::select( $current );
		echo '<p class="description">' . esc_html__( 'Translations of this term:', 'openlingua' ) . '</p><ul>';
		foreach ( Languages::all() as $code => $language ) {
			if ( $code === $current ) { continue; }
			if ( isset( $group[ $code ] ) ) {
				$url = get_edit_term_link( $group[ $code ], $taxonomy );
				echo '<li>' . esc_html( $language['name'] ) . ': <a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit', 'openlingua' ) . '</a></li>';
			} else {
				$url = wp_nonce_url( add_query_arg( array( 'action' => 'openlingua_duplicate_term', 'term_id' => $term->term_id, 'taxonomy' => $taxonomy, 'language' => $code ), admin_url( 'admin-post.php' ) ), 'openlingua_duplicate_term_' . $term->term_id );
				echo '<li>' . esc_html( $language['name'] ) . ': <a href="' . esc_url( $url ) . '">+ ' . esc_html__( 'Create', 'openlingua' ) . '</a></li>';
			}
		}
		echo '</ul></td></tr>';
	}

	private static function select( $current ) {
		echo '<select id="openlingua-term-language" name="openlingua_term_language">';
		foreach ( Languages::all() as $code => $language ) {
			echo '<option value="' . esc_attr( $code ) . '" ' . selected( $current, $code, false ) . '>' . esc_html( $language['name'] ) . '</option>';
		}
		echo '</select>';
	}

	public static function save( $term_id, $tt_id, $taxonomy ) {
		if ( ! isset( $_POST['openlingua_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openlingua_term_nonce'] ) ), 'openlingua_save_term' ) ) { return; }
		$language = isset( $_POST['openlingua_term_language'] ) ? sanitize_key( wp_unslash( $_POST['openlingua_term_language'] ) ) : Languages::default_code();
		$source   = isset( $_POST['openlingua_source_term'] ) ? absint( $_POST['openlingua_source_term'] ) : 0;
		$row      = Translations::row( 'term', $term_id );
		$source_row = $source ? Translations::row( 'term', $source ) : null;
		Translations::assign( 'term', $term_id, $language, $source_row ? $source_row->group_uuid : ( $row ? $row->group_uuid : '' ), $source_row ? $source_row->language : ( $row ? $row->source_language : '' ) );
	}

	public static function duplicate() {
		$term_id  = isset( $_GET['term_id'] ) ? absint( $_GET['term_id'] ) : 0;
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
		$language = isset( $_GET['language'] ) ? sanitize_key( wp_unslash( $_GET['language'] ) ) : '';
		check_admin_referer( 'openlingua_duplicate_term_' . $term_id );
		$tax = get_taxonomy( $taxonomy );
		if ( ! $term_id || ! $tax || ! Languages::is_valid( $language ) || ! current_user_can( $tax->cap->manage_terms ) ) {
			wp_die( esc_html__( 'You cannot create this term translation.', 'openlingua' ) );
		}
		$source = get_term( $term_id, $taxonomy );
		if ( ! $source || is_wp_error( $source ) ) { wp_die( esc_html__( 'Source term not found.', 'openlingua' ) ); }
		$row = Translations::row( 'term', $term_id );
		if ( ! $row ) { $group = Translations::assign( 'term', $term_id, Languages::default_code() ); } else { $group = $row->group_uuid; }
		$existing = Translations::translated_id( 'term', $term_id, $language );
		if ( $existing ) { wp_safe_redirect( get_edit_term_link( $existing, $taxonomy ) ); exit; }
		$parent = $source->parent ? Translations::translated_id( 'term', $source->parent, $language ) : 0;
		$result = wp_insert_term( $source->name . ' (' . strtoupper( $language ) . ')', $taxonomy, array( 'description' => $source->description, 'parent' => $parent ) );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
		$new_id = absint( $result['term_id'] );
		foreach ( get_term_meta( $term_id ) as $key => $values ) {
			foreach ( $values as $value ) { add_term_meta( $new_id, $key, maybe_unserialize( $value ) ); }
		}
		Translations::assign( 'term', $new_id, $language, $group, $row ? $row->language : Languages::default_code() );
		wp_safe_redirect( get_edit_term_link( $new_id, $taxonomy ) ); exit;
	}

	public static function delete( $term_id ) {
		Translations::delete( 'term', $term_id );
	}
}

