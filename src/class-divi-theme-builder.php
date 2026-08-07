<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

/** Integrates OpenLingua translations with Divi Theme Builder layouts. */
final class Divi_Theme_Builder {
	private static $layout_types = array(
		'header' => 'et_header_layout',
		'body'   => 'et_body_layout',
		'footer' => 'et_footer_layout',
	);

	public static function hooks() {
		// Register after OpenLingua's top-level page so WordPress keeps the main settings submenu intact.
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'et_theme_builder_template_layouts', array( __CLASS__, 'translate_template_layouts' ), 20 );
		add_filter( 'et_theme_builder_template_setting_filter_validation_id', array( __CLASS__, 'translate_condition_object_id' ), 20, 3 );
		add_filter( 'pre_trash_post', array( __CLASS__, 'protect_translated_layout' ), 10, 3 );
		add_filter( 'openlingua_meta_policy', array( __CLASS__, 'metadata_policy' ), 10, 3 );
	}

	public static function available() {
		return function_exists( 'et_theme_builder_get_theme_builder_templates' )
			&& defined( 'ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE' )
			&& defined( 'ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE' )
			&& defined( 'ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE' );
	}

	public static function admin_menu() {
		if ( ! self::available() ) { return; }
		add_submenu_page( 'openlingua', __( 'Divi Theme Builder translations', 'openlingua' ), __( 'Divi Theme Builder', 'openlingua' ), 'manage_options', 'openlingua-divi-theme-builder', array( __CLASS__, 'page' ) );
	}

	public static function assets( $hook ) {
		if ( 'openlingua_page_openlingua-divi-theme-builder' !== $hook ) { return; }
		wp_enqueue_style( 'openlingua-admin-translations', plugins_url( 'assets/admin-translations.css', OPENLINGUA_FILE ), array( 'dashicons' ), OPENLINGUA_VERSION );
		wp_enqueue_style( 'openlingua-divi-theme-builder', plugins_url( 'assets/admin-divi-theme-builder.css', OPENLINGUA_FILE ), array( 'openlingua-admin-translations' ), OPENLINGUA_VERSION );
		wp_enqueue_script( 'openlingua-admin-translations', plugins_url( 'assets/admin-translations.js', OPENLINGUA_FILE ), array(), OPENLINGUA_VERSION, true );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		echo '<div class="wrap openlingua-divi-tb"><h1>' . esc_html__( 'Divi Theme Builder translations', 'openlingua' ) . '</h1>';
		if ( ! self::available() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Divi Theme Builder is not available.', 'openlingua' ) . '</p></div></div>';
			return;
		}
		$rows = self::rows_from_templates( et_theme_builder_get_theme_builder_templates( true ) );
		echo '<p>' . esc_html__( 'Keep layout design and display conditions in Divi. Translate the textual content of each header, body and footer here.', 'openlingua' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=et_theme_builder' ) ) . '"><span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>' . esc_html__( 'Open Divi Theme Builder', 'openlingua' ) . '</a></p>';
		if ( ! $rows ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'No custom Divi Theme Builder layouts were found.', 'openlingua' ) . '</p></div></div>';
			return;
		}
		$languages = Languages::all();
		echo '<table class="widefat striped openlingua-divi-tb__table"><thead><tr><th>' . esc_html__( 'Divi template', 'openlingua' ) . '</th><th>' . esc_html__( 'Section', 'openlingua' ) . '</th>';
		foreach ( $languages as $language ) { echo '<th><span class="openlingua-column-language"><span aria-hidden="true">' . esc_html( $language['flag'] ?? '🌐' ) . '</span> ' . esc_html( $language['name'] ) . '</span></th>'; }
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) { self::render_row( $row, $languages ); }
		echo '</tbody></table><p class="description">' . esc_html__( 'Dynamic Post Content modules remain connected to the translated page or post and are not duplicated as static text.', 'openlingua' ) . '</p></div>';
	}

	public static function rows_from_templates( array $templates ) {
		$rows = array();
		foreach ( $templates as $template ) {
			$template_id = absint( $template['id'] ?? 0 );
			$template_name = ! empty( $template['default'] ) ? __( 'Default Website Template', 'openlingua' ) : trim( (string) ( $template['title'] ?? '' ) );
			/* translators: %d: Divi template ID. */
			if ( '' === $template_name ) { $template_name = sprintf( __( 'Template #%d', 'openlingua' ), $template_id ); }
			foreach ( self::$layout_types as $kind => $post_type ) {
				$layout = $template['layouts'][ $kind ] ?? array();
				$layout_id = absint( $layout['id'] ?? 0 );
				if ( ! $layout_id ) { continue; }
				if ( ! isset( $rows[ $layout_id ] ) ) {
					$rows[ $layout_id ] = array( 'id' => $layout_id, 'kind' => $kind, 'post_type' => $post_type, 'contexts' => array(), 'global' => ! empty( $layout['global'] ), 'enabled' => ! empty( $layout['enabled'] ) );
				}
				$rows[ $layout_id ]['contexts'][] = $template_name;
			}
		}
		return array_values( $rows );
	}

	private static function render_row( array $layout, array $languages ) {
		$post = get_post( $layout['id'] );
		if ( ! $post ) { return; }
		$row = Translations::row( 'post', $post->ID );
		$source_language = $row ? $row->language : Languages::default_code();
		$group = $row ? Translations::group( 'post', $post->ID ) : array( $source_language => $post->ID );
		$section_names = array( 'header' => __( 'Header', 'openlingua' ), 'body' => __( 'Body', 'openlingua' ), 'footer' => __( 'Footer', 'openlingua' ) );
		$return_url = admin_url( 'admin.php?page=openlingua-divi-theme-builder' );
		echo '<tr><td><strong>' . esc_html( implode( ', ', array_unique( $layout['contexts'] ) ) ) . '</strong><br><small>' . esc_html( get_the_title( $post ) ?: sprintf( '#%d', $post->ID ) ) . '</small></td>';
		echo '<td><span class="dashicons dashicons-' . ( 'header' === $layout['kind'] ? 'align-wide' : ( 'footer' === $layout['kind'] ? 'align-center' : 'layout' ) ) . '" aria-hidden="true"></span> ' . esc_html( $section_names[ $layout['kind'] ] ) . ( $layout['global'] ? ' <span class="openlingua-divi-tb__global">' . esc_html__( 'Global', 'openlingua' ) . '</span>' : '' ) . ( ! $layout['enabled'] ? ' <span class="openlingua-divi-tb__disabled">' . esc_html__( 'Disabled', 'openlingua' ) . '</span>' : '' ) . '</td>';
		foreach ( $languages as $code => $language ) {
			echo '<td>';
			if ( $code === $source_language ) {
				$edit_url = self::layout_edit_url( $post->ID );
				echo '<span class="openlingua-divi-tb__original">' . esc_html__( 'Original', 'openlingua' ) . '</span>';
				if ( $edit_url ) { echo ' <a class="openlingua-translation-action" href="' . esc_url( $edit_url ) . '" title="' . esc_attr__( 'Edit original layout in Divi', 'openlingua' ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span></a>'; }
			} elseif ( isset( $group[ $code ] ) ) {
				$translation_id = absint( $group[ $code ] );
				if ( 'trash' === get_post_status( $translation_id ) ) {
					/* translators: %s: language name. */
					$restore_label = sprintf( __( 'Restore %s translation', 'openlingua' ), $language['name'] );
					echo '<a class="openlingua-translation-action" href="' . esc_url( Content::restore_translation_url( $post->ID, $translation_id ) ) . '" title="' . esc_attr( $restore_label ) . '"><span class="dashicons dashicons-undo" aria-hidden="true"></span></a>';
				} else {
					$editor_url = Translation_Editor::url( $post->ID, $translation_id, $return_url );
					/* translators: %s: language name. */
					$confirmation = sprintf( __( 'Move the %s translation to Trash? The original layout will not be deleted.', 'openlingua' ), $language['name'] );
					/* translators: %s: language name. */
					$edit_label = sprintf( __( 'Edit %s translation', 'openlingua' ), $language['name'] );
					/* translators: %s: language name. */
					$trash_label = sprintf( __( 'Move %s translation to Trash', 'openlingua' ), $language['name'] );
					echo '<span class="openlingua-translation-links"><a class="openlingua-translation-action" href="' . esc_url( $editor_url ) . '" title="' . esc_attr( $edit_label ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span></a><a class="openlingua-translation-action openlingua-translation-action--delete" href="' . esc_url( Content::delete_translation_url( $post->ID, $translation_id, $return_url ) ) . '" data-openlingua-confirm="' . esc_attr( $confirmation ) . '" title="' . esc_attr( $trash_label ) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></a></span>';
				}
			} else {
				$url = wp_nonce_url( add_query_arg( array( 'action' => 'openlingua_duplicate', 'post_id' => $post->ID, 'language' => $code, 'redirect_to' => $return_url ), admin_url( 'admin-post.php' ) ), 'openlingua_duplicate_' . $post->ID );
				/* translators: %s: language name. */
				$add_label = sprintf( __( 'Add %s translation', 'openlingua' ), $language['name'] );
				echo '<a class="openlingua-translation-action" href="' . esc_url( $url ) . '" title="' . esc_attr( $add_label ) . '"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span></a>';
			}
			echo '</td>';
		}
		echo '</tr>';
	}

	public static function translate_template_layouts( $layouts ) {
		if ( ! is_array( $layouts ) || is_admin() || Languages::current() === Languages::default_code() ) { return $layouts; }
		foreach ( self::$layout_types as $fallback_type ) {
			$post_type = self::constant_post_type( $fallback_type );
			if ( ! isset( $layouts[ $post_type ]['id'] ) ) { continue; }
			$source_id = absint( $layouts[ $post_type ]['id'] );
			if ( ! $source_id ) { continue; }
			$translation_id = Translations::translated_id( 'post', $source_id, Languages::current() );
			if ( $translation_id && 'publish' === get_post_status( $translation_id ) && $post_type === get_post_type( $translation_id ) ) {
				$layouts[ $post_type ]['id'] = $translation_id;
			}
		}
		return $layouts;
	}

	public static function translate_condition_object_id( $id, $type, $subtype ) {
		$element_type = 'taxonomy' === $type ? 'term' : 'post';
		$translated_id = Translations::translated_id( $element_type, absint( $id ), Languages::current() );
		return $translated_id ?: $id;
	}

	public static function protect_translated_layout( $trash, $post, $previous_status ) {
		if ( null !== $trash || ! $post || ! self::is_layout_post_type( $post->post_type ) ) { return $trash; }
		$row = Translations::row( 'post', $post->ID );
		if ( $row && $row->source_language && get_post_meta( $post->ID, '_et_theme_builder_marked_as_unused', true ) ) {
			delete_post_meta( $post->ID, '_et_theme_builder_marked_as_unused' );
			return false;
		}
		return $trash;
	}

	public static function metadata_policy( $policy, $key, $post_type ) {
		if ( '_et_theme_builder_marked_as_unused' === $key && self::is_layout_post_type( $post_type ) ) { return 'ignore'; }
		return $policy;
	}

	private static function constant_post_type( $fallback ) {
		$constants = array(
			'et_header_layout' => 'ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE',
			'et_body_layout'   => 'ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE',
			'et_footer_layout' => 'ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE',
		);
		return isset( $constants[ $fallback ] ) && defined( $constants[ $fallback ] ) ? constant( $constants[ $fallback ] ) : $fallback;
	}

	private static function layout_edit_url( $layout_id ) {
		if ( function_exists( 'et_fb_get_builder_url' ) ) {
			$permalink = get_permalink( $layout_id );
			if ( $permalink ) { return add_query_arg( 'et_tb', '1', et_fb_get_builder_url( $permalink ) ); }
		}
		return get_edit_post_link( $layout_id, 'url' );
	}

	private static function is_layout_post_type( $post_type ) {
		return in_array( $post_type, array_map( array( __CLASS__, 'constant_post_type' ), self::$layout_types ), true );
	}
}
