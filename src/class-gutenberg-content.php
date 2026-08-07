<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

/** Extracts translatable Gutenberg block values without exposing block markup. */
final class Gutenberg_Content {
	private static $excluded_attributes = array(
		'id', 'ids', 'ref', 'url', 'href', 'src', 'link', 'anchor', 'class', 'classname',
		'style', 'styles', 'css', 'color', 'backgroundcolor', 'textcolor', 'gradient',
		'fontfamily', 'font-size', 'fontsize', 'align', 'layout', 'width', 'height',
		'mediaid', 'media_id', 'attachmentid', 'attachment_id', 'rel', 'target', 'type',
		'level', 'tagname', 'lock', 'metadata', 'namespace', 'query', 'termid', 'postid',
	);

	public static function hooks() {
		add_filter( 'render_block', array( __CLASS__, 'translate_dynamic_output' ), 20, 2 );
	}

	public static function translate_dynamic_output( $content, $block ) {
		$name = sanitize_key( (string) ( $block['blockName'] ?? '' ) );
		if ( '' === $name || ! class_exists( 'WP_Block_Type_Registry' ) ) { return $content; }
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( (string) $block['blockName'] );
		if ( ! $type || ! $type->is_dynamic() ) { return $content; }
		return Shortcode_Content::translate_html( $content, 'block-' . $name );
	}

	public static function is_gutenberg( $content ) {
		return function_exists( 'has_blocks' ) ? has_blocks( (string) $content ) : false !== strpos( (string) $content, '<!-- wp:' );
	}

	public static function extract( $content ) {
		if ( ! self::is_gutenberg( $content ) || ! function_exists( 'parse_blocks' ) ) { return array(); }
		$segments = array();
		self::walk_extract( parse_blocks( (string) $content ), array(), $segments );
		return $segments;
	}

	public static function values( $content ) {
		$values = array();
		foreach ( self::extract( $content ) as $segment ) { $values[ $segment['id'] ] = $segment['value']; }
		return $values;
	}

	public static function apply( $content, array $translations, $target_language = '' ) {
		if ( ! self::is_gutenberg( $content ) || ! function_exists( 'parse_blocks' ) || ! function_exists( 'serialize_blocks' ) ) { return (string) $content; }
		$blocks = parse_blocks( (string) $content );
		self::walk_apply( $blocks, array(), $translations, sanitize_key( $target_language ) );
		return serialize_blocks( $blocks );
	}

	private static function walk_extract( array $blocks, array $parent_path, array &$segments ) {
		foreach ( $blocks as $index => $block ) {
			$path = array_merge( $parent_path, array( $index ) );
			$name = (string) ( $block['blockName'] ?? '' );
			$name = $name ?: 'core/freeform';
			$label = self::block_label( $name, $path );
			self::extract_attributes( (array) ( $block['attrs'] ?? array() ), array(), $path, $label, $segments );
			foreach ( (array) ( $block['innerContent'] ?? array() ) as $fragment_index => $fragment ) {
				if ( ! is_string( $fragment ) ) { continue; }
				$segments = array_merge( $segments, self::content_segments( $fragment, $name, $path, $fragment_index, $label ) );
			}
			self::walk_extract( (array) ( $block['innerBlocks'] ?? array() ), $path, $segments );
		}
	}

	private static function extract_attributes( array $attributes, array $attribute_path, array $block_path, $label, array &$segments ) {
		foreach ( $attributes as $key => $value ) {
			$path = array_merge( $attribute_path, array( $key ) );
			if ( is_array( $value ) ) {
				self::extract_attributes( $value, $path, $block_path, $label, $segments );
				continue;
			}
			if ( ! is_string( $value ) || ! self::is_translatable_attribute( $key, $value ) ) { continue; }
			$segments[] = array(
				'id' => self::segment_id( $block_path, 'attribute', $path ),
				'label' => $label . ' — ' . self::attribute_label( $key ),
				'value' => $value,
				'format' => self::looks_like_html( $value ) ? 'html' : 'text',
				'kind' => 'attribute',
				'block_path' => $block_path,
				'value_path' => $path,
			);
		}
	}

	private static function walk_apply( array &$blocks, array $parent_path, array $translations, $target_language ) {
		foreach ( $blocks as $index => &$block ) {
			$path = array_merge( $parent_path, array( $index ) );
			$name = (string) ( $block['blockName'] ?? '' );
			$name = $name ?: 'core/freeform';
			if ( ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) { $block['attrs'] = array(); }
			if ( ! isset( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) { $block['innerBlocks'] = array(); }
			if ( ! isset( $block['innerContent'] ) || ! is_array( $block['innerContent'] ) ) { $block['innerContent'] = array(); }
			self::apply_attributes( $block['attrs'], array(), $path, $translations );
			foreach ( (array) ( $block['innerContent'] ?? array() ) as $fragment_index => $fragment ) {
				if ( ! is_string( $fragment ) ) { continue; }
				$label = self::block_label( $name, $path );
				$replacements = array();
				foreach ( self::content_segments( $fragment, $name, $path, $fragment_index, $label ) as $segment ) {
					if ( ! array_key_exists( $segment['id'], $translations ) ) { continue; }
					$value = (string) $translations[ $segment['id'] ];
					$replacements[] = array( 'offset' => $segment['offset'], 'length' => $segment['length'], 'value' => 'html' === $segment['format'] ? wp_kses_post( $value ) : esc_html( sanitize_textarea_field( $value ) ) );
				}
				usort( $replacements, function ( $left, $right ) { return $right['offset'] <=> $left['offset']; } );
				foreach ( $replacements as $replacement ) { $fragment = substr_replace( $fragment, $replacement['value'], $replacement['offset'], $replacement['length'] ); }
				$block['innerContent'][ $fragment_index ] = $fragment;
			}
			if ( 'core/block' === $name && $target_language && ! empty( $block['attrs']['ref'] ) ) {
				$translated_ref = Translations::translated_id( 'post', absint( $block['attrs']['ref'] ), $target_language );
				if ( $translated_ref ) { $block['attrs']['ref'] = absint( $translated_ref ); }
			}
			self::walk_apply( $block['innerBlocks'], $path, $translations, $target_language );
		}
		unset( $block );
	}

	private static function apply_attributes( array &$attributes, array $attribute_path, array $block_path, array $translations ) {
		foreach ( $attributes as $key => &$value ) {
			$path = array_merge( $attribute_path, array( $key ) );
			if ( is_array( $value ) ) { self::apply_attributes( $value, $path, $block_path, $translations ); continue; }
			$id = self::segment_id( $block_path, 'attribute', $path );
			if ( ! is_string( $value ) || ! array_key_exists( $id, $translations ) ) { continue; }
			$translated = (string) $translations[ $id ];
			$value = self::looks_like_html( $value ) ? wp_kses_post( $translated ) : sanitize_textarea_field( $translated );
		}
		unset( $value );
	}

	private static function is_translatable_attribute( $key, $value ) {
		$key = strtolower( (string) $key );
		$value = trim( (string) $value );
		if ( '' === $value || ! preg_match( '/\p{L}/u', html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' ) ) ) { return false; }
		if ( in_array( $key, self::$excluded_attributes, true ) || preg_match( '/(?:^|_)(?:url|uri|href|src|id|ids|class|style|color|size|width|height|align|layout|slug|key|token|icon)(?:$|_)/i', $key ) ) { return false; }
		if ( preg_match( '~^(?:https?:)?//|^mailto:|^tel:|^#?[0-9a-f]{3,8}$~i', $value ) ) { return false; }
		if ( preg_match( '/(?:text|content|title|heading|caption|description|label|placeholder|message|button|summary|citation|author|name|alt|aria)/i', $key ) ) { return true; }
		return self::looks_like_html( $value ) || preg_match( '/\s|[.!?,;:¿¡]/u', $value );
	}

	private static function has_visible_text( $html ) {
		return '' !== trim( html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	private static function content_segments( $fragment, $block_name, array $block_path, $fragment_index, $label ) {
		if ( ! self::has_visible_text( $fragment ) ) { return array(); }
		if ( 'core/table' !== $block_name ) {
			return array( array(
				'id' => self::segment_id( $block_path, 'content', array( $fragment_index ) ),
				'label' => $label . ' — ' . __( 'Content', 'openlingua' ),
				'value' => $fragment, 'format' => 'html', 'kind' => 'content',
				'block_path' => $block_path, 'value_path' => array( $fragment_index ),
				'offset' => 0, 'length' => strlen( $fragment ),
			) );
		}

		preg_match_all( '~<(th|td|caption)\b[^>]*>(.*?)</\1\s*>~is', $fragment, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		$segments = array();
		$counts = array( 'th' => 0, 'td' => 0, 'caption' => 0 );
		foreach ( $matches as $match ) {
			$tag = strtolower( $match[1][0] );
			$value = $match[2][0];
			if ( ! self::has_visible_text( $value ) ) { continue; }
			$counts[ $tag ]++;
			$field = 'th' === $tag ? __( 'Header cell', 'openlingua' ) : ( 'caption' === $tag ? __( 'Caption', 'openlingua' ) : __( 'Cell', 'openlingua' ) );
			$segments[] = array(
				'id' => self::segment_id( $block_path, 'content', array( $fragment_index, $tag, $counts[ $tag ] ) ),
				'label' => $label . ' — ' . $field . ' ' . $counts[ $tag ],
				'value' => $value,
				'format' => self::looks_like_html( $value ) ? 'html' : 'text',
				'kind' => 'content', 'block_path' => $block_path,
				'value_path' => array( $fragment_index, $tag, $counts[ $tag ] ),
				'offset' => $match[2][1], 'length' => strlen( $value ),
			);
		}
		return $segments;
	}

	private static function looks_like_html( $value ) {
		return (bool) preg_match( '/<\/?[a-z][^>]*>/i', (string) $value );
	}

	private static function segment_id( array $block_path, $kind, array $value_path ) {
		return sanitize_key( 'gutenberg_' . implode( '_', array_map( 'absint', $block_path ) ) . '_' . $kind . '_' . implode( '_', array_map( 'sanitize_key', $value_path ) ) );
	}

	private static function block_label( $name, array $path ) {
		$title = ucwords( str_replace( array( '/', '-', '_' ), ' ', preg_replace( '/^core\//', '', $name ) ) );
		return sprintf( '%s %s', $title, implode( '.', array_map( function ( $part ) { return absint( $part ) + 1; }, $path ) ) );
	}

	private static function attribute_label( $key ) {
		return ucwords( trim( preg_replace( '/([a-z])([A-Z])/', '$1 $2', str_replace( array( '_', '-' ), ' ', (string) $key ) ) ) );
	}
}
