<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

/** Discovers and updates independently translatable ACF values. */
final class ACF_Content {
	private static $text_types = array( 'text', 'textarea', 'wysiwyg' );

	public static function available() {
		return function_exists( 'get_field_objects' ) && function_exists( 'update_field' );
	}

	public static function extract( $post_id ) {
		if ( ! self::available() ) { return array(); }
		$fields = get_field_objects( $post_id, false );
		if ( ! is_array( $fields ) ) { return array(); }
		$segments = array();
		$post_type = get_post_type( $post_id );
		foreach ( $fields as $field ) {
			if ( empty( $field['key'] ) || empty( $field['name'] ) ) { continue; }
			self::walk( $field, $field['value'] ?? null, array(), array(), $field['key'], $post_type, $segments );
		}
		return $segments;
	}

	public static function values( $post_id ) {
		$values = array();
		foreach ( self::extract( $post_id ) as $segment ) { $values[ $segment['id'] ] = $segment['value']; }
		return $values;
	}

	public static function save( $source_id, $target_id, array $submitted, $allow_html = false ) {
		if ( ! self::available() ) { return; }
		$source_segments = self::extract( $source_id );
		$target_fields = get_field_objects( $target_id, false );
		$target_by_key = array();
		foreach ( (array) $target_fields as $field ) { if ( ! empty( $field['key'] ) ) { $target_by_key[ $field['key'] ] = $field; } }
		$updates = array();

		foreach ( $source_segments as $segment ) {
			if ( ! array_key_exists( $segment['id'], $submitted ) ) { continue; }
			$root_key = $segment['root_key'];
			if ( ! array_key_exists( $root_key, $updates ) ) {
				$updates[ $root_key ] = isset( $target_by_key[ $root_key ] ) ? $target_by_key[ $root_key ]['value'] : null;
			}
			$value = (string) $submitted[ $segment['id'] ];
			$value = 'html' === $segment['format'] ? ( $allow_html ? $value : wp_kses_post( $value ) ) : sanitize_textarea_field( $value );
			$updates[ $root_key ] = self::set_path( $updates[ $root_key ], $segment['path'], $value );
		}

		foreach ( $updates as $field_key => $value ) { update_field( $field_key, $value, $target_id ); }
	}

	private static function walk( array $field, $value, array $path, array $parents, $root_key, $post_type, array &$segments ) {
		$name = $field['name'] ?? '';
		$type = $field['type'] ?? '';
		$policy = \OpenLingua\Modules\Metadata::policy( $name, $post_type );
		if ( in_array( $policy, array( 'copy', 'ignore' ), true ) ) { return; }
		$label = $field['label'] ?? $name;
		$labels = array_merge( $parents, array( $label ) );

		if ( in_array( $type, self::$text_types, true ) ) {
			if ( ! is_scalar( $value ) || '' === trim( html_entity_decode( strip_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ) ) ) { return; }
			self::add_segment( $segments, $root_key, $path, $labels, (string) $value, 'wysiwyg' === $type ? 'html' : 'plain' );
			return;
		}

		if ( 'link' === $type && is_array( $value ) && ! empty( $value['title'] ) ) {
			self::add_segment( $segments, $root_key, array_merge( $path, array( 'title' ) ), array_merge( $labels, array( __( 'Link text', 'openlingua' ) ) ), (string) $value['title'], 'plain' );
			return;
		}

		if ( in_array( $type, array( 'group', 'clone' ), true ) ) {
			foreach ( (array) ( $field['sub_fields'] ?? array() ) as $sub_field ) {
				$sub_name = $sub_field['name'] ?? '';
				$sub_key = $sub_field['key'] ?? '';
				if ( ! $sub_name || ! $sub_key ) { continue; }
				$sub_value = is_array( $value ) ? ( $value[ $sub_name ] ?? ( $value[ $sub_field['key'] ] ?? null ) ) : null;
				self::walk( $sub_field, $sub_value, array_merge( $path, array( $sub_key ) ), $labels, $root_key, $post_type, $segments );
			}
			return;
		}

		if ( 'repeater' === $type && is_array( $value ) ) {
			foreach ( $value as $row_index => $row ) {
				foreach ( (array) ( $field['sub_fields'] ?? array() ) as $sub_field ) {
					$sub_name = $sub_field['name'] ?? '';
					$sub_key = $sub_field['key'] ?? '';
					if ( ! $sub_name || ! $sub_key ) { continue; }
					$sub_value = is_array( $row ) ? ( $row[ $sub_name ] ?? ( $row[ $sub_field['key'] ] ?? null ) ) : null;
					self::walk( $sub_field, $sub_value, array_merge( $path, array( $row_index, $sub_key ) ), array_merge( $labels, array( sprintf( __( 'Row %d', 'openlingua' ), $row_index + 1 ) ) ), $root_key, $post_type, $segments );
				}
			}
			return;
		}

		if ( 'flexible_content' === $type && is_array( $value ) ) {
			foreach ( $value as $row_index => $row ) {
				$layout_name = is_array( $row ) ? ( $row['acf_fc_layout'] ?? '' ) : '';
				foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
					if ( ( $layout['name'] ?? '' ) !== $layout_name ) { continue; }
					$layout_label = $layout['label'] ?? $layout_name;
					foreach ( (array) ( $layout['sub_fields'] ?? array() ) as $sub_field ) {
						$sub_name = $sub_field['name'] ?? '';
						$sub_key = $sub_field['key'] ?? '';
						if ( ! $sub_name || ! $sub_key ) { continue; }
						$sub_value = $row[ $sub_name ] ?? ( $row[ $sub_field['key'] ] ?? null );
						self::walk( $sub_field, $sub_value, array_merge( $path, array( $row_index, $sub_key ) ), array_merge( $labels, array( $layout_label . ' ' . ( $row_index + 1 ) ) ), $root_key, $post_type, $segments );
					}
				}
			}
		}
	}

	private static function add_segment( array &$segments, $root_key, array $path, array $labels, $value, $format ) {
		$id_path = $path ? implode( '_', array_map( 'strval', $path ) ) : 'value';
		$segments[] = array( 'id' => sanitize_key( 'acf_' . $root_key . '_' . $id_path ), 'label' => implode( ' — ', $labels ), 'value' => $value, 'format' => $format, 'root_key' => $root_key, 'path' => $path );
	}

	private static function set_path( $root, array $path, $value ) {
		if ( ! $path ) { return $value; }
		if ( ! is_array( $root ) ) { $root = array(); }
		$cursor =& $root;
		foreach ( $path as $index => $part ) {
			if ( $index === count( $path ) - 1 ) { $cursor[ $part ] = $value; break; }
			if ( ! isset( $cursor[ $part ] ) || ! is_array( $cursor[ $part ] ) ) { $cursor[ $part ] = array(); }
			$cursor =& $cursor[ $part ];
		}
		return $root;
	}
}
