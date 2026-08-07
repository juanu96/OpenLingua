<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Taxonomies {
	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'register_fields' ), 99 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 16 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'created_term', array( __CLASS__, 'save' ), 10, 3 );
		add_action( 'edited_term', array( __CLASS__, 'save' ), 10, 3 );
		add_action( 'delete_term', array( __CLASS__, 'delete' ), 10, 1 );
		add_action( 'admin_post_openlingua_duplicate_term', array( __CLASS__, 'duplicate' ) );
		add_action( 'admin_post_openlingua_save_term_translation', array( __CLASS__, 'save_translation' ) );
	}

	public static function admin_menu() {
		add_submenu_page( 'openlingua', __( 'Taxonomy translations', 'openlingua' ), __( 'Taxonomies', 'openlingua' ), 'manage_categories', 'openlingua-taxonomies', array( __CLASS__, 'page' ) );
	}

	public static function assets( $hook ) {
		if ( 'openlingua_page_openlingua-taxonomies' !== $hook ) { return; }
		wp_enqueue_style( 'openlingua-admin-taxonomies', plugins_url( 'assets/admin-taxonomies.css', OPENLINGUA_FILE ), array( 'dashicons' ), OPENLINGUA_VERSION );
		wp_enqueue_script( 'openlingua-admin-taxonomies', plugins_url( 'assets/admin-taxonomies.js', OPENLINGUA_FILE ), array(), OPENLINGUA_VERSION, true );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_categories' ) ) { return; }
		$taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $taxonomies[ $taxonomy ] ) ) { $taxonomy = $taxonomies ? array_key_first( $taxonomies ) : ''; }
		$query_taxonomies = $taxonomy ? array( $taxonomy ) : array();
		$terms = $query_taxonomies ? get_terms( array( 'taxonomy' => $query_taxonomies, 'hide_empty' => false, 'search' => $search, 'orderby' => 'name', 'order' => 'ASC' ) ) : array();
		if ( is_wp_error( $terms ) ) { $terms = array(); }
		$default = Languages::default_code();
		$terms = array_values( array_filter( $terms, static function ( $term ) use ( $default ) {
			$row = Translations::row( 'term', $term->term_id );
			return ! $row || $default === $row->language;
		} ) );
		$total = count( $terms );
		$per_page = 25;
		$terms = array_slice( $terms, ( $paged - 1 ) * $per_page, $per_page );
		$return_to = add_query_arg( array_filter( array( 'page' => 'openlingua-taxonomies', 'taxonomy' => $taxonomy, 's' => $search, 'paged' => $paged ) ), admin_url( 'admin.php' ) );

		echo '<div class="wrap openlingua-taxonomies"><h1>' . esc_html__( 'Taxonomy translations', 'openlingua' ) . '</h1><p class="description">' . esc_html__( 'Translate term names, URL slugs and descriptions from one compact screen.', 'openlingua' ) . '</p>';
		if ( isset( $_GET['updated'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Taxonomy translation saved.', 'openlingua' ) . '</p></div>'; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<form method="get" class="openlingua-taxonomies__filters"><input type="hidden" name="page" value="openlingua-taxonomies"><label><span class="screen-reader-text">' . esc_html__( 'Search terms', 'openlingua' ) . '</span><input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search terms', 'openlingua' ) . '"></label><label><span class="screen-reader-text">' . esc_html__( 'Filter by taxonomy', 'openlingua' ) . '</span><select name="taxonomy">';
		foreach ( $taxonomies as $name => $object ) { echo '<option value="' . esc_attr( $name ) . '" ' . selected( $taxonomy, $name, false ) . '>' . esc_html( $object->labels->singular_name . ' (' . $name . ')' ) . '</option>'; }
		echo '</select></label>'; submit_button( __( 'Filter', 'openlingua' ), 'secondary', '', false );
		if ( $search ) { echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'openlingua-taxonomies', 'taxonomy' => $taxonomy ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Clear', 'openlingua' ) . '</a>'; }
		echo '</form><table class="wp-list-table widefat fixed striped"><thead><tr><th class="column-term">' . esc_html__( 'Original term', 'openlingua' ) . '</th><th class="column-taxonomy">' . esc_html__( 'Taxonomy', 'openlingua' ) . '</th><th class="column-description">' . esc_html__( 'Description', 'openlingua' ) . '</th>';
		foreach ( Languages::all() as $code => $language ) { if ( $code !== $default ) { echo '<th class="column-language"><span title="' . esc_attr( $language['name'] ) . '">' . esc_html( $language['flag'] ?? strtoupper( $code ) ) . '</span></th>'; } }
		echo '</tr></thead><tbody>';
		if ( ! $terms ) { echo '<tr class="no-items"><td colspan="' . absint( 3 + max( 0, count( Languages::all() ) - 1 ) ) . '">' . esc_html__( 'No terms found.', 'openlingua' ) . '</td></tr>'; }
		foreach ( $terms as $term ) {
			$group = Translations::group( 'term', $term->term_id );
			echo '<tr><td class="column-term"><strong>' . esc_html( $term->name ) . '</strong><code>/' . esc_html( $term->slug ) . '/</code></td><td class="column-taxonomy">' . esc_html( $taxonomies[ $term->taxonomy ]->labels->singular_name ?? $term->taxonomy ) . '</td><td class="column-description"><span>' . esc_html( wp_trim_words( wp_strip_all_tags( $term->description ), 18, '…' ) ) . '</span></td>';
			foreach ( Languages::all() as $code => $language ) {
				if ( $code === $default ) { continue; }
				$target_id = absint( $group[ $code ] ?? 0 );
				$target = $target_id ? get_term( $target_id, $term->taxonomy ) : null;
				if ( is_wp_error( $target ) ) { $target = null; $target_id = 0; }
				$payload = array( 'sourceId' => $term->term_id, 'targetId' => $target_id, 'taxonomy' => $term->taxonomy, 'language' => $code, 'languageName' => $language['name'], 'flag' => $language['flag'] ?? '🌐', 'sourceName' => $term->name, 'name' => $target ? $target->name : $term->name, 'slug' => $target ? $target->slug : $term->slug, 'description' => $target ? $target->description : $term->description );
				/* translators: %s: language name. */
				$edit_label = sprintf( __( 'Edit %s translation', 'openlingua' ), $language['name'] );
				/* translators: %s: language name. */
				$add_label = sprintf( __( 'Add %s translation', 'openlingua' ), $language['name'] );
				$label = $target ? $edit_label : $add_label;
				echo '<td class="column-language"><button type="button" class="openlingua-taxonomy-action" data-openlingua-taxonomy-edit data-term="' . esc_attr( wp_json_encode( $payload ) ) . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><span class="dashicons ' . ( $target ? 'dashicons-edit' : 'dashicons-plus-alt2' ) . '" aria-hidden="true"></span></button></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
		$total_pages = (int) ceil( $total / $per_page );
		if ( $total_pages > 1 ) { echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( array_filter( array( 'page' => 'openlingua-taxonomies', 'taxonomy' => $taxonomy, 's' => $search, 'paged' => '%#%' ) ), admin_url( 'admin.php' ) ), 'format' => '', 'current' => $paged, 'total' => $total_pages ) ) ) . '</div></div>'; }
		self::modal( $return_to );
		echo '</div>';
	}

	private static function modal( $return_to ) {
		echo '<div class="openlingua-taxonomy-modal" data-openlingua-taxonomy-modal hidden><div class="openlingua-taxonomy-modal__backdrop" data-openlingua-taxonomy-close></div><section class="openlingua-taxonomy-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="openlingua-taxonomy-modal-title"><header><div><small>' . esc_html__( 'Taxonomy translation', 'openlingua' ) . '</small><h2 id="openlingua-taxonomy-modal-title" data-openlingua-taxonomy-title></h2></div><button type="button" class="button-link" data-openlingua-taxonomy-close aria-label="' . esc_attr__( 'Close', 'openlingua' ) . '"><span class="dashicons dashicons-no-alt"></span></button></header><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="openlingua_save_term_translation"><input type="hidden" name="source_id"><input type="hidden" name="target_id"><input type="hidden" name="taxonomy"><input type="hidden" name="language"><input type="hidden" name="return_to" value="' . esc_attr( $return_to ) . '">';
		wp_nonce_field( 'openlingua_save_term_translation', 'openlingua_taxonomy_nonce' );
		echo '<div class="openlingua-taxonomy-modal__body"><p class="openlingua-taxonomy-modal__source"><span>' . esc_html__( 'Original', 'openlingua' ) . '</span><strong data-openlingua-taxonomy-source></strong></p><label>' . esc_html__( 'Name', 'openlingua' ) . '<input type="text" name="name" required></label><label>' . esc_html__( 'URL slug', 'openlingua' ) . '<input type="text" name="slug"><small data-openlingua-taxonomy-url></small></label><label>' . esc_html__( 'Description', 'openlingua' ) . '<textarea name="description" rows="6"></textarea></label></div><footer><button type="button" class="button" data-openlingua-taxonomy-close>' . esc_html__( 'Cancel', 'openlingua' ) . '</button><button type="submit" class="button button-primary">' . esc_html__( 'Save translation', 'openlingua' ) . '</button></footer></form></section></div>';
	}

	public static function save_translation() {
		if ( ! isset( $_POST['openlingua_taxonomy_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openlingua_taxonomy_nonce'] ) ), 'openlingua_save_term_translation' ) ) { wp_die( esc_html__( 'Invalid request.', 'openlingua' ) ); }
		$source_id = absint( $_POST['source_id'] ?? 0 );
		$target_id = absint( $_POST['target_id'] ?? 0 );
		$taxonomy = sanitize_key( wp_unslash( $_POST['taxonomy'] ?? '' ) );
		$language = sanitize_key( wp_unslash( $_POST['language'] ?? '' ) );
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$slug = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$tax = get_taxonomy( $taxonomy );
		$source = get_term( $source_id, $taxonomy );
		if ( ! $tax || ! $source || is_wp_error( $source ) || ! Languages::is_valid( $language ) || ! current_user_can( $tax->cap->manage_terms ) || '' === $name ) { wp_die( esc_html__( 'You cannot save this taxonomy translation.', 'openlingua' ) ); }
		$row = Translations::row( 'term', $source_id );
		$group = $row ? $row->group_uuid : Translations::assign( 'term', $source_id, Languages::default_code() );
		$existing = Translations::translated_id( 'term', $source_id, $language );
		if ( $existing ) { $target_id = absint( $existing ); }
		$args = array( 'description' => $description );
		if ( $slug ) { $args['slug'] = $slug; }
		if ( $target_id ) {
			$result = wp_update_term( $target_id, $taxonomy, array_merge( $args, array( 'name' => $name ) ) );
		} else {
			$parent = $source->parent ? Translations::translated_id( 'term', $source->parent, $language ) : 0;
			$args['parent'] = $parent;
			$result = wp_insert_term( $name, $taxonomy, $args );
			if ( ! is_wp_error( $result ) ) {
				$target_id = absint( $result['term_id'] );
				foreach ( get_term_meta( $source_id ) as $key => $values ) { foreach ( $values as $value ) { add_term_meta( $target_id, $key, maybe_unserialize( $value ) ); } }
			}
		}
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
		Translations::assign( 'term', $target_id, $language, $group, $row ? $row->language : Languages::default_code() );
		$return_to = isset( $_POST['return_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_to'] ) ), '' ) : '';
		wp_safe_redirect( add_query_arg( 'updated', '1', $return_to ?: admin_url( 'admin.php?page=openlingua-taxonomies' ) ) ); exit;
	}

	public static function register_fields() {
		foreach ( get_taxonomies( array( 'show_ui' => true ), 'names' ) as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'add_fields' ) );
			add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'edit_fields' ), 10, 2 );
			add_filter( 'manage_edit-' . $taxonomy . '_columns', array( __CLASS__, 'translation_column' ) );
			add_filter( 'manage_' . $taxonomy . '_custom_column', array( __CLASS__, 'translation_column_value' ), 10, 3 );
		}
	}

	public static function add_fields( $taxonomy ) {
		$admin_language = Admin::content_language();
		$language = isset( $_GET['openlingua_language'] ) ? sanitize_key( wp_unslash( $_GET['openlingua_language'] ) ) : ( Languages::is_valid( $admin_language ) ? $admin_language : Languages::default_code() ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

	public static function translation_column( $columns ) {
		if ( count( Languages::all() ) < 2 ) { return $columns; }
		return $columns + Content::translation_columns();
	}

	public static function translation_column_value( $content, $column, $term_id ) {
		$code = Content::column_language( $column );
		if ( ! $code ) { return $content; }
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) { return $content; }
		$row     = Translations::row( 'term', $term_id );
		$current = $row ? $row->language : Languages::default_code();
		$group   = Translations::group( 'term', $term_id );
		if ( $code === $current ) { return $content; }
		$language = Languages::all()[ $code ];
		$name = $language['name'] ?? strtoupper( $code );
		if ( isset( $group[ $code ] ) ) {
			$translation_id = absint( $group[ $code ] );
			$url   = get_edit_term_link( $translation_id, $term->taxonomy );
			/* translators: %s: language name. */
			$label = sprintf( __( 'Edit %s translation', 'openlingua' ), $name );
			$icon  = 'dashicons-edit';
		} else {
			$url = wp_nonce_url( add_query_arg( array( 'action' => 'openlingua_duplicate_term', 'term_id' => $term_id, 'taxonomy' => $term->taxonomy, 'language' => $code ), admin_url( 'admin-post.php' ) ), 'openlingua_duplicate_term_' . $term_id );
			/* translators: %s: language name. */
			$label = sprintf( __( 'Add %s translation', 'openlingua' ), $name );
			$icon  = 'dashicons-plus-alt2';
		}
		if ( ! $url ) { return $content; }
		$output = '<span class="openlingua-translation-links"><a class="openlingua-translation-action" href="' . esc_url( $url ) . '" title="' . esc_attr( $label ) . '" aria-label="' . esc_attr( $label ) . '"><span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span></a>';
		if ( isset( $translation_id ) ) {
			$view_url = get_term_link( $translation_id, $term->taxonomy );
			/* translators: %s: language name. */
			$view_label = sprintf( __( 'View %s translation', 'openlingua' ), $name );
			if ( ! is_wp_error( $view_url ) ) { $output .= '<a class="openlingua-translation-action" href="' . esc_url( $view_url ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr( $view_label ) . '" aria-label="' . esc_attr( $view_label ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></a>'; }
		}
		return $output . '</span>';
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
