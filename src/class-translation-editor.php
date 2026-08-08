<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Translation_Editor {
	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_openlingua_save_translation', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function register_page() {
		add_submenu_page( null, __( 'Translation editor', 'openlingua' ), __( 'Translation editor', 'openlingua' ), 'read', 'openlingua-translation-editor', array( __CLASS__, 'page' ) );
	}

	public static function url( $source_id, $target_id, $return_to = '' ) {
		$args = array( 'page' => 'openlingua-translation-editor', 'source_id' => absint( $source_id ), 'target_id' => absint( $target_id ) );
		if ( $return_to ) { $args['return_to'] = $return_to; }
		return add_query_arg(
			$args,
			admin_url( 'admin.php' )
		);
	}

	public static function assets( $hook ) {
		if ( 'admin_page_openlingua-translation-editor' !== $hook ) { return; }
		wp_enqueue_style( 'openlingua-translation-editor', plugins_url( 'assets/translation-editor.css', OPENLINGUA_FILE ), array( 'dashicons' ), OPENLINGUA_VERSION );
		wp_enqueue_script( 'openlingua-translation-editor', plugins_url( 'assets/translation-editor.js', OPENLINGUA_FILE ), array(), OPENLINGUA_VERSION, true );
	}

	public static function page() {
		list( $source, $target ) = self::posts_from_request();
		if ( ! $source || ! $target || ! self::is_translation_pair( $source->ID, $target->ID ) ) { wp_die( esc_html__( 'The translation could not be loaded.', 'openlingua' ) ); }
		if ( ! current_user_can( 'edit_post', $source->ID ) || ! current_user_can( 'edit_post', $target->ID ) ) { wp_die( esc_html__( 'You cannot edit this translation.', 'openlingua' ) ); }
		$source_row = Translations::row( 'post', $source->ID );
		$target_row = Translations::row( 'post', $target->ID );
		$source_code = $source_row ? $source_row->language : Languages::default_code();
		$target_code = $target_row ? $target_row->language : '';
		Translation_Memory::import_existing_pair( $source_code, $target_code );
		$source_language = Languages::all()[ $source_code ] ?? array( 'name' => strtoupper( $source_code ), 'flag' => '🌐' );
		$target_language = Languages::all()[ $target_code ] ?? array( 'name' => strtoupper( $target_code ), 'flag' => '🌐' );
		$is_divi = Divi_Content::is_divi( $source->post_content );
		$is_gutenberg = ! $is_divi && Gutenberg_Content::is_gutenberg( $source->post_content );
		$fields = array(
			'post_title' => array( 'label' => __( 'Title', 'openlingua' ), 'source' => $source->post_title, 'target' => $target->post_title, 'rows' => 2 ),
			'post_excerpt' => array( 'label' => __( 'Excerpt', 'openlingua' ), 'source' => $source->post_excerpt, 'target' => $target->post_excerpt, 'rows' => 4 ),
		);
		if ( ! $is_divi && ! $is_gutenberg ) { $fields['post_content'] = array( 'label' => __( 'Main content', 'openlingua' ), 'source' => $source->post_content, 'target' => $target->post_content, 'rows' => 18 ); }
		$divi_segments = $is_divi ? Divi_Content::extract( $source->post_content ) : array();
		$target_divi = $is_divi ? Divi_Content::values( $target->post_content ) : array();
		$gutenberg_segments = $is_gutenberg ? Gutenberg_Content::extract( $source->post_content ) : array();
		$target_gutenberg = $is_gutenberg ? Gutenberg_Content::values( $target->post_content ) : array();
		$acf_segments = ACF_Content::extract( $source->ID );
		$target_acf = ACF_Content::values( $target->ID );
		$seo_groups = SEO::translation_fields( $source->ID, $target->ID );
		$memory_fields = array();
		foreach ( $fields as $name => &$field ) {
			self::apply_memory( 'openlingua-' . $name, $field['source'], $field['target'], $source_code, $target_code, 'post_title' === $name ? 'text' : 'html', $memory_fields );
		}
		unset( $field );
		foreach ( $divi_segments as $segment ) {
			$target_divi[ $segment['id'] ] = $target_divi[ $segment['id'] ] ?? '';
			self::apply_memory( 'openlingua-' . $segment['id'], $segment['value'], $target_divi[ $segment['id'] ], $source_code, $target_code, 'content' === $segment['kind'] ? 'html' : 'text', $memory_fields );
		}
		foreach ( $gutenberg_segments as $segment ) {
			$target_gutenberg[ $segment['id'] ] = $target_gutenberg[ $segment['id'] ] ?? '';
			self::apply_memory( 'openlingua-' . $segment['id'], $segment['value'], $target_gutenberg[ $segment['id'] ], $source_code, $target_code, $segment['format'], $memory_fields );
		}
		foreach ( $acf_segments as $segment ) {
			$target_acf[ $segment['id'] ] = $target_acf[ $segment['id'] ] ?? '';
			self::apply_memory( 'openlingua-' . $segment['id'], $segment['value'], $target_acf[ $segment['id'] ], $source_code, $target_code, $segment['format'], $memory_fields );
		}
		foreach ( $seo_groups as &$group ) {
			foreach ( $group['fields'] as &$field ) { self::apply_memory( 'openlingua-seo-' . $field['id'], $field['source'], $field['target'], $source_code, $target_code, 'text', $memory_fields ); }
			unset( $field );
		}
		unset( $group );
		wp_localize_script( 'openlingua-translation-editor', 'OpenLinguaTranslationMemory', array(
			'fields' => $memory_fields,
			'label'  => __( 'Applied from translation memory', 'openlingua' ),
		) );
		$return_to = isset( $_GET['return_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['return_to'] ) ), '' ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor navigation parameter.
		$back = $return_to ?: ( get_edit_post_link( $source->ID, 'url' ) ?: admin_url( 'edit.php' ) );
		echo '<div class="wrap openlingua-editor"><header class="openlingua-editor__top"><a class="openlingua-editor__back" href="' . esc_url( $back ) . '"><span class="dashicons dashicons-arrow-left-alt"></span>' . esc_html__( 'Back', 'openlingua' ) . '</a><div><span>' . esc_html__( 'Translating', 'openlingua' ) . '</span><strong>' . esc_html( get_the_title( $source ) ) . '</strong></div><label class="openlingua-editor__search"><span class="dashicons dashicons-search"></span><input type="search" placeholder="' . esc_attr__( 'Search content', 'openlingua' ) . '"></label></header>';
		echo '<div class="openlingua-editor__languages"><div><small>' . esc_html__( 'Original', 'openlingua' ) . '</small><strong><span aria-hidden="true">' . esc_html( $source_language['flag'] ?? '🌐' ) . '</span> ' . esc_html( $source_language['name'] ) . '</strong></div><div><small>' . esc_html__( 'Translation', 'openlingua' ) . '</small><strong><span aria-hidden="true">' . esc_html( $target_language['flag'] ?? '🌐' ) . '</span> ' . esc_html( $target_language['name'] ) . '</strong></div></div>';
		$automatic_status = isset( $_GET['automatic_translation'] ) ? sanitize_key( wp_unslash( $_GET['automatic_translation'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status notice.
		if ( 'queued' === $automatic_status ) { echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Automatic translation queued. You may leave this page; OpenLingua will notify you when it is ready to review.', 'openlingua' ) . '</p></div>'; }
		if ( 'error' === $automatic_status ) { echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The automatic translation could not be queued. Check the provider settings and the Jobs screen.', 'openlingua' ) . '</p></div>'; }
		echo '<div class="openlingua-editor__automatic">';
		$provider = \OpenLingua\Modules\Providers::active();
		if ( $provider && $provider->is_configured() ) {
			$automatic_url = \OpenLingua\Modules\Jobs::enqueue_url( $source->ID, $target->ID, $provider->id(), $return_to );
			/* translators: %s: translation provider name. */
			echo '<a class="button button-primary" href="' . esc_url( $automatic_url ) . '"><span class="dashicons dashicons-translation" aria-hidden="true"></span>' . sprintf( esc_html__( 'Translate with %s', 'openlingua' ), esc_html( $provider->label() ) ) . '</a>';
		} else {
			$settings_url = $provider && method_exists( $provider, 'settings_url' ) ? $provider->settings_url() : \OpenLingua\Modules\OpenAI_Provider::settings_url();
			echo '<a class="button" href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Configure the selected translation provider', 'openlingua' ) . '</a>';
		}
		echo '<span>' . esc_html__( 'The result will be saved as in progress for your review.', 'openlingua' ) . '</span>';
		echo '</div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="openlingua_save_translation"><input type="hidden" name="source_id" value="' . absint( $source->ID ) . '"><input type="hidden" name="target_id" value="' . absint( $target->ID ) . '"><input type="hidden" name="content_mode" value="' . ( $is_divi ? 'divi' : ( $is_gutenberg ? 'gutenberg' : 'standard' ) ) . '">';
		if ( $return_to ) { echo '<input type="hidden" name="return_to" value="' . esc_attr( $return_to ) . '">'; }
		wp_nonce_field( 'openlingua_save_translation_' . $target->ID );
		echo '<main class="openlingua-editor__segments"><h2>' . esc_html__( 'Main content', 'openlingua' ) . '</h2>';
		foreach ( $fields as $name => $field ) {
			echo '<section class="openlingua-editor__segment" data-openlingua-segment><div class="openlingua-editor__source"><label>' . esc_html( $field['label'] ) . '</label><div class="openlingua-editor__original">' . nl2br( esc_html( $field['source'] ) ) . '</div></div><div class="openlingua-editor__target"><label for="openlingua-' . esc_attr( $name ) . '">' . esc_html( $field['label'] ) . '</label><textarea id="openlingua-' . esc_attr( $name ) . '" name="translation[' . esc_attr( $name ) . ']" rows="' . absint( $field['rows'] ) . '" data-openlingua-translation>' . esc_textarea( $field['target'] ) . '</textarea></div></section>';
		}
		foreach ( $divi_segments as $segment ) {
			$target_value = $target_divi[ $segment['id'] ] ?? '';
			$rows = max( 2, min( 8, substr_count( $segment['value'], "\n" ) + 2 ) );
			if ( 'content' === $segment['kind'] ) {
				echo '<section class="openlingua-editor__segment openlingua-editor__segment--divi openlingua-editor__segment--rich" data-openlingua-segment data-openlingua-rich-segment><div class="openlingua-editor__source"><label><span class="dashicons dashicons-layout"></span> ' . esc_html( $segment['label'] ) . '</label><div class="openlingua-editor__original openlingua-editor__visual" data-openlingua-source-visual>' . wp_kses_post( $segment['value'] ) . '</div><pre class="openlingua-editor__source-code" data-openlingua-source-code hidden>' . esc_html( $segment['value'] ) . '</pre></div><div class="openlingua-editor__target"><div class="openlingua-editor__field-heading"><label for="openlingua-' . esc_attr( $segment['id'] ) . '">' . esc_html( $segment['label'] ) . '</label><button type="button" class="button button-small" data-openlingua-html-toggle data-show-html="' . esc_attr__( 'Show HTML', 'openlingua' ) . '" data-hide-html="' . esc_attr__( 'Visual view', 'openlingua' ) . '" aria-pressed="false"><span class="dashicons dashicons-editor-code"></span><span data-openlingua-toggle-label>' . esc_html__( 'Show HTML', 'openlingua' ) . '</span></button></div><div class="openlingua-editor__visual openlingua-editor__visual--editable" contenteditable="true" role="textbox" aria-multiline="true" aria-label="' . esc_attr( $segment['label'] ) . '" data-openlingua-target-visual>' . wp_kses_post( $target_value ) . '</div><textarea id="openlingua-' . esc_attr( $segment['id'] ) . '" class="openlingua-editor__code" name="divi_translation[' . esc_attr( $segment['id'] ) . ']" rows="' . absint( $rows ) . '" data-openlingua-translation data-openlingua-target-code hidden>' . esc_textarea( $target_value ) . '</textarea></div></section>';
			} else {
				echo '<section class="openlingua-editor__segment openlingua-editor__segment--divi" data-openlingua-segment><div class="openlingua-editor__source"><label><span class="dashicons dashicons-layout"></span> ' . esc_html( $segment['label'] ) . '</label><div class="openlingua-editor__original">' . esc_html( $segment['value'] ) . '</div></div><div class="openlingua-editor__target"><label for="openlingua-' . esc_attr( $segment['id'] ) . '">' . esc_html( $segment['label'] ) . '</label><textarea id="openlingua-' . esc_attr( $segment['id'] ) . '" name="divi_translation[' . esc_attr( $segment['id'] ) . ']" rows="' . absint( $rows ) . '" data-openlingua-translation>' . esc_textarea( $target_value ) . '</textarea></div></section>';
			}
		}
		if ( $gutenberg_segments ) { echo '<h2>' . esc_html__( 'Block content', 'openlingua' ) . '</h2>'; }
		foreach ( $gutenberg_segments as $segment ) {
			$target_value = $target_gutenberg[ $segment['id'] ] ?? '';
			$rows = max( 2, min( 8, substr_count( $segment['value'], "\n" ) + 2 ) );
			if ( 'html' === $segment['format'] ) {
				echo '<section class="openlingua-editor__segment openlingua-editor__segment--gutenberg openlingua-editor__segment--rich" data-openlingua-segment data-openlingua-rich-segment><div class="openlingua-editor__source"><label><span class="dashicons dashicons-block-default"></span> ' . esc_html( $segment['label'] ) . '</label><div class="openlingua-editor__original openlingua-editor__visual" data-openlingua-source-visual>' . wp_kses_post( $segment['value'] ) . '</div><pre class="openlingua-editor__source-code" data-openlingua-source-code hidden>' . esc_html( $segment['value'] ) . '</pre></div><div class="openlingua-editor__target"><div class="openlingua-editor__field-heading"><label for="openlingua-' . esc_attr( $segment['id'] ) . '">' . esc_html( $segment['label'] ) . '</label><button type="button" class="button button-small" data-openlingua-html-toggle data-show-html="' . esc_attr__( 'Show HTML', 'openlingua' ) . '" data-hide-html="' . esc_attr__( 'Visual view', 'openlingua' ) . '" aria-pressed="false"><span class="dashicons dashicons-editor-code"></span><span data-openlingua-toggle-label>' . esc_html__( 'Show HTML', 'openlingua' ) . '</span></button></div><div class="openlingua-editor__visual openlingua-editor__visual--editable" contenteditable="true" role="textbox" aria-multiline="true" aria-label="' . esc_attr( $segment['label'] ) . '" data-openlingua-target-visual>' . wp_kses_post( $target_value ) . '</div><textarea id="openlingua-' . esc_attr( $segment['id'] ) . '" class="openlingua-editor__code" name="gutenberg_translation[' . esc_attr( $segment['id'] ) . ']" rows="' . absint( $rows ) . '" data-openlingua-translation data-openlingua-target-code hidden>' . esc_textarea( $target_value ) . '</textarea></div></section>';
			} else {
				echo '<section class="openlingua-editor__segment openlingua-editor__segment--gutenberg" data-openlingua-segment><div class="openlingua-editor__source"><label><span class="dashicons dashicons-block-default"></span> ' . esc_html( $segment['label'] ) . '</label><div class="openlingua-editor__original">' . nl2br( esc_html( $segment['value'] ) ) . '</div></div><div class="openlingua-editor__target"><label for="openlingua-' . esc_attr( $segment['id'] ) . '">' . esc_html( $segment['label'] ) . '</label><textarea id="openlingua-' . esc_attr( $segment['id'] ) . '" name="gutenberg_translation[' . esc_attr( $segment['id'] ) . ']" rows="' . absint( $rows ) . '" data-openlingua-translation>' . esc_textarea( $target_value ) . '</textarea></div></section>';
			}
		}
		if ( $acf_segments ) { echo '<h2>' . esc_html__( 'Custom fields', 'openlingua' ) . '</h2>'; }
		foreach ( $acf_segments as $segment ) {
			$target_value = $target_acf[ $segment['id'] ] ?? '';
			$rows = max( 2, min( 8, substr_count( $segment['value'], "\n" ) + 2 ) );
			if ( 'html' === $segment['format'] ) {
				echo '<section class="openlingua-editor__segment openlingua-editor__segment--acf openlingua-editor__segment--rich" data-openlingua-segment data-openlingua-rich-segment><div class="openlingua-editor__source"><label><span class="dashicons dashicons-forms"></span> ' . esc_html( $segment['label'] ) . '</label><div class="openlingua-editor__original openlingua-editor__visual" data-openlingua-source-visual>' . wp_kses_post( $segment['value'] ) . '</div><pre class="openlingua-editor__source-code" data-openlingua-source-code hidden>' . esc_html( $segment['value'] ) . '</pre></div><div class="openlingua-editor__target"><div class="openlingua-editor__field-heading"><label for="openlingua-' . esc_attr( $segment['id'] ) . '">' . esc_html( $segment['label'] ) . '</label><button type="button" class="button button-small" data-openlingua-html-toggle data-show-html="' . esc_attr__( 'Show HTML', 'openlingua' ) . '" data-hide-html="' . esc_attr__( 'Visual view', 'openlingua' ) . '" aria-pressed="false"><span class="dashicons dashicons-editor-code"></span><span data-openlingua-toggle-label>' . esc_html__( 'Show HTML', 'openlingua' ) . '</span></button></div><div class="openlingua-editor__visual openlingua-editor__visual--editable" contenteditable="true" role="textbox" aria-multiline="true" aria-label="' . esc_attr( $segment['label'] ) . '" data-openlingua-target-visual>' . wp_kses_post( $target_value ) . '</div><textarea id="openlingua-' . esc_attr( $segment['id'] ) . '" class="openlingua-editor__code" name="acf_translation[' . esc_attr( $segment['id'] ) . ']" rows="' . absint( $rows ) . '" data-openlingua-translation data-openlingua-target-code hidden>' . esc_textarea( $target_value ) . '</textarea></div></section>';
			} else {
				echo '<section class="openlingua-editor__segment openlingua-editor__segment--acf" data-openlingua-segment><div class="openlingua-editor__source"><label><span class="dashicons dashicons-forms"></span> ' . esc_html( $segment['label'] ) . '</label><div class="openlingua-editor__original">' . nl2br( esc_html( $segment['value'] ) ) . '</div></div><div class="openlingua-editor__target"><label for="openlingua-' . esc_attr( $segment['id'] ) . '">' . esc_html( $segment['label'] ) . '</label><textarea id="openlingua-' . esc_attr( $segment['id'] ) . '" name="acf_translation[' . esc_attr( $segment['id'] ) . ']" rows="' . absint( $rows ) . '" data-openlingua-translation>' . esc_textarea( $target_value ) . '</textarea></div></section>';
			}
		}
		foreach ( $seo_groups as $group ) {
			/* translators: %s: SEO integration name. */
			echo '<h2>' . sprintf( esc_html__( 'SEO — %s', 'openlingua' ), esc_html( $group['name'] ) ) . '</h2>';
			foreach ( $group['fields'] as $field ) {
				$rows = false !== stripos( $field['label'], 'description' ) ? 4 : 2;
				echo '<section class="openlingua-editor__segment openlingua-editor__segment--seo" data-openlingua-segment><div class="openlingua-editor__source"><label><span class="dashicons dashicons-search"></span> ' . esc_html( $field['label'] ) . '</label><div class="openlingua-editor__original">' . nl2br( esc_html( $field['source'] ) ) . '</div></div><div class="openlingua-editor__target"><label for="openlingua-seo-' . esc_attr( $field['id'] ) . '">' . esc_html( $field['label'] ) . '</label><textarea id="openlingua-seo-' . esc_attr( $field['id'] ) . '" name="seo_translation[' . esc_attr( $field['id'] ) . ']" rows="' . absint( $rows ) . '" data-openlingua-translation>' . esc_textarea( $field['target'] ) . '</textarea></div></section>';
			}
		}
		$delete_url = Content::delete_translation_url( $source->ID, $target->ID, $back );
		/* translators: %s: language name. */
		$delete_confirmation = sprintf( __( 'Move the %s translation to Trash? The original content will not be deleted.', 'openlingua' ), $target_language['name'] );
		echo '</main><footer class="openlingua-editor__footer"><div class="openlingua-editor__footer-actions"><a class="button" href="' . esc_url( get_edit_post_link( $target->ID, 'url' ) ) . '">' . esc_html__( 'Open WordPress editor', 'openlingua' ) . '</a>';
		if ( current_user_can( 'delete_post', $target->ID ) ) { echo ' <a class="button openlingua-editor__delete" href="' . esc_url( $delete_url ) . '" data-openlingua-confirm="' . esc_attr( $delete_confirmation ) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span>' . esc_html__( 'Move translation to Trash', 'openlingua' ) . '</a>'; }
		echo '</div><div class="openlingua-editor__progress"><strong data-openlingua-progress>0%</strong><span><i data-openlingua-progress-bar></i></span></div><div><button class="button" type="submit" name="translation_status" value="in-progress">' . esc_html__( 'Save draft', 'openlingua' ) . '</button> <button class="button button-primary" type="submit" name="translation_status" value="complete">' . esc_html__( 'Save and complete', 'openlingua' ) . '</button></div></footer></form></div>';
	}

	public static function save() {
		$source_id = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
		$return_to = isset( $_POST['return_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_to'] ) ), '' ) : '';
		check_admin_referer( 'openlingua_save_translation_' . $target_id );
		if ( ! $source_id || ! $target_id || ! current_user_can( 'edit_post', $source_id ) || ! current_user_can( 'edit_post', $target_id ) ) { wp_die( esc_html__( 'You cannot save this translation.', 'openlingua' ) ); }
		$translation = self::posted_array( 'translation' );
		$source = get_post( $source_id );
		$target = get_post( $target_id );
		$is_divi = $source && Divi_Content::is_divi( $source->post_content );
		$is_gutenberg = $source && ! $is_divi && Gutenberg_Content::is_gutenberg( $source->post_content );
		$content = $translation['post_content'] ?? '';
		$excerpt = $translation['post_excerpt'] ?? '';
		if ( $is_divi ) {
			$submitted = self::posted_array( 'divi_translation' );
			$allowed = array();
			foreach ( Divi_Content::extract( $source->post_content ) as $segment ) {
				if ( ! array_key_exists( $segment['id'], $submitted ) ) { continue; }
				$value = $submitted[ $segment['id'] ];
				$allowed[ $segment['id'] ] = 'attribute' === $segment['kind'] ? sanitize_text_field( $value ) : ( current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value ) );
			}
			$base_content = $target && Divi_Content::is_divi( $target->post_content ) ? $target->post_content : $source->post_content;
			$content = Divi_Content::apply( $base_content, $allowed );
			$content = Divi_Content::restore_embedded_shortcodes( $source->post_content, $content );
		} elseif ( $is_gutenberg ) {
			$submitted = self::posted_array( 'gutenberg_translation' );
			$allowed = array();
			foreach ( Gutenberg_Content::extract( $source->post_content ) as $segment ) {
				if ( ! array_key_exists( $segment['id'], $submitted ) ) { continue; }
				$value = $submitted[ $segment['id'] ];
				$allowed[ $segment['id'] ] = 'html' === $segment['format'] ? ( current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value ) ) : sanitize_textarea_field( $value );
			}
			$base_content = $target && Gutenberg_Content::is_gutenberg( $target->post_content ) ? $target->post_content : $source->post_content;
			$target_row = Translations::row( 'post', $target_id );
			$content = Gutenberg_Content::apply( $base_content, $allowed, $target_row ? $target_row->language : '' );
		}
		if ( ! current_user_can( 'unfiltered_html' ) ) { if ( ! $is_divi && ! $is_gutenberg ) { $content = wp_kses_post( $content ); } $excerpt = wp_kses_post( $excerpt ); }
		if ( ! self::is_translation_pair( $source_id, $target_id ) ) { wp_die( esc_html__( 'These posts are not linked translations.', 'openlingua' ) ); }
		$status = isset( $_POST['translation_status'] ) ? sanitize_key( wp_unslash( $_POST['translation_status'] ) ) : 'in-progress';
		if ( ! in_array( $status, array( 'in-progress', 'complete' ), true ) ) { $status = 'in-progress'; }
		$title = sanitize_text_field( $translation['post_title'] ?? '' );
		$post_status = $target->post_status;
		if ( 'complete' === $status && 'publish' === $source->post_status && self::can_publish( $target ) ) { $post_status = 'publish'; }
		$update = array( 'ID' => $target_id, 'post_title' => $title, 'post_excerpt' => $excerpt, 'post_content' => $content, 'post_status' => $post_status );
		$desired_slug = sanitize_title( $title );
		if ( $desired_slug && self::should_refresh_slug( $target, $source, $desired_slug ) ) {
			$update['post_name'] = wp_unique_post_slug( $desired_slug, $target_id, $post_status, $target->post_type, $target->post_parent );
		}
		$result = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
		$acf_translation = self::posted_array( 'acf_translation' );
		ACF_Content::save( $source_id, $target_id, $acf_translation, current_user_can( 'unfiltered_html' ) );
		$seo_translation = self::posted_array( 'seo_translation' );
		SEO::save_translation_fields( $source_id, $target_id, $seo_translation );
		Translation_Memory::learn_post( $source_id, $target_id );
		update_post_meta( $target_id, \OpenLingua\Modules\Workflow::STATUS_META, $status );
		\OpenLingua\Modules\Workflow::mark_created( $target_id, $source_id );
		update_post_meta( $target_id, \OpenLingua\Modules\Workflow::STATUS_META, $status );
		wp_safe_redirect( add_query_arg( 'updated', '1', self::url( $source_id, $target_id, $return_to ) ) ); exit;
	}

	private static function posts_from_request() {
		$source_id = isset( $_GET['source_id'] ) ? absint( $_GET['source_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$target_id = isset( $_GET['target_id'] ) ? absint( $_GET['target_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array( get_post( $source_id ), get_post( $target_id ) );
	}

	private static function posted_array( $key ) {
		if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) { return array(); } // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- The save handler verifies its nonce before calling this method; values are sanitized by segment type.
		return (array) wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- The save handler already verified its nonce; callers apply field-specific sanitization.
	}

	private static function is_translation_pair( $source_id, $target_id ) {
		$source = Translations::row( 'post', $source_id );
		$target = Translations::row( 'post', $target_id );
		return $source && $target && $source->group_uuid === $target->group_uuid && absint( $source_id ) !== absint( $target_id );
	}

	private static function can_publish( $post ) {
		$post_type = get_post_type_object( $post->post_type );
		return $post_type && current_user_can( $post_type->cap->publish_posts );
	}

	private static function should_refresh_slug( $target, $source, $desired_slug ) {
		return ! $target->post_name || 'draft' === $target->post_status || sanitize_title( $source->post_title ) === $target->post_name || preg_match( '/^' . preg_quote( $desired_slug, '/' ) . '-\d+$/', $target->post_name );
	}

	private static function apply_memory( $id, $source, &$target, $source_language, $target_language, $format, array &$fields ) {
		$format = 'html' === $format ? 'html' : 'text';
		$suggestion = Translation_Memory::find( $source, $source_language, $target_language, $format );
		$replaceable = '' === trim( (string) $target ) || Translation_Memory::normalize( $source, $format ) === Translation_Memory::normalize( $target, $format );
		$applied = $replaceable && '' !== $suggestion;
		if ( $applied ) { $target = $suggestion; }
		$fields[ $id ] = array( 'key' => $format . ':' . Translation_Memory::key( $source, $format ), 'applied' => $applied, 'replaceable' => $replaceable );
	}
}
