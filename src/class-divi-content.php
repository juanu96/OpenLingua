<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

/** Extracts translatable Divi shortcode values while preserving layout markup. */
final class Divi_Content {
	private static $content_modules = array(
		'et_pb_text', 'et_pb_blurb', 'et_pb_cta', 'et_pb_toggle', 'et_pb_accordion_item',
		'et_pb_slide', 'et_pb_testimonial', 'et_pb_pricing_table',
	);

	private static $translatable_attributes = array(
		'button_text', 'title', 'heading', 'subhead', 'field_title', 'placeholder',
		'success_message', 'submit_button_text', 'email_name', 'name_text', 'email_text',
		'message_text', 'captcha_text', 'custom_message', 'author', 'job_title', 'company_name',
		'currency', 'sum', 'frequency',
	);

	public static function is_divi( $content ) {
		return false !== strpos( (string) $content, '[et_pb_' );
	}

	public static function extract( $content ) {
		$content = (string) $content;
		if ( ! self::is_divi( $content ) ) { return array(); }
		preg_match_all( '~\[(\/?)(et_pb_[a-z0-9_]+)\b([^\]]*)\]~i', $content, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		$segments = array();
		$stack = array();
		$counts = array();

		foreach ( $tokens as $token ) {
			$is_closing = '/' === $token[1][0];
			$module = strtolower( $token[2][0] );
			$token_start = $token[0][1];
			$token_end = $token_start + strlen( $token[0][0] );
			if ( ! $is_closing ) {
				$counts[ $module ] = ( $counts[ $module ] ?? 0 ) + 1;
				$occurrence = $counts[ $module ];
				$attributes = self::attributes( $token[3][0], $token[3][1] );
				$label = self::module_label( $module, $occurrence, $attributes );
				foreach ( $attributes as $attribute => $data ) {
					if ( ! in_array( $attribute, self::$translatable_attributes, true ) || '' === trim( $data['value'] ) ) { continue; }
					$segments[] = array(
						'id' => self::segment_id( $module, $occurrence, $attribute ),
						'label' => $label . ' — ' . self::field_label( $attribute ),
						'value' => html_entity_decode( $data['value'], ENT_QUOTES, 'UTF-8' ),
						'start' => $data['start'], 'length' => $data['length'], 'kind' => 'attribute',
					);
				}
				$dynamic = isset( $attributes['_dynamic_attributes'] ) && false !== strpos( $attributes['_dynamic_attributes']['value'], 'content' );
				$stack[] = array( 'module' => $module, 'occurrence' => $occurrence, 'content_start' => $token_end, 'label' => $label, 'extract_content' => in_array( $module, self::$content_modules, true ) && ! $dynamic );
				continue;
			}

			for ( $index = count( $stack ) - 1; $index >= 0; $index-- ) {
				if ( $stack[ $index ]['module'] !== $module ) { continue; }
				$opening = $stack[ $index ];
				$stack = array_slice( $stack, 0, $index );
				if ( ! $opening['extract_content'] ) { break; }
				$value = substr( $content, $opening['content_start'], $token_start - $opening['content_start'] );
				if ( ! self::has_text( $value ) ) { break; }
				$segments[] = array(
					'id' => self::segment_id( $module, $opening['occurrence'], 'content' ),
					'label' => $opening['label'] . ' — ' . __( 'Content', 'openlingua' ),
					'value' => $value, 'start' => $opening['content_start'], 'length' => strlen( $value ), 'kind' => 'content',
				);
				break;
			}
		}

		usort( $segments, function ( $left, $right ) { return $left['start'] <=> $right['start']; } );
		return $segments;
	}

	public static function values( $content ) {
		$values = array();
		foreach ( self::extract( $content ) as $segment ) { $values[ $segment['id'] ] = $segment['value']; }
		return $values;
	}

	public static function apply( $content, array $translations ) {
		$replacements = array();
		foreach ( self::extract( $content ) as $segment ) {
			if ( ! array_key_exists( $segment['id'], $translations ) ) { continue; }
			$value = (string) $translations[ $segment['id'] ];
			if ( 'attribute' === $segment['kind'] ) { $value = htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
			$replacements[] = array( 'start' => $segment['start'], 'length' => $segment['length'], 'value' => $value );
		}
		usort( $replacements, function ( $left, $right ) { return $right['start'] <=> $left['start']; } );
		foreach ( $replacements as $replacement ) {
			$content = substr_replace( $content, $replacement['value'], $replacement['start'], $replacement['length'] );
		}
		return $content;
	}

	private static function attributes( $text, $absolute_start ) {
		preg_match_all( '~\s([a-zA-Z0-9_:-]+)=("|\')(.*?)\2~s', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		$attributes = array();
		foreach ( $matches as $match ) {
			$name = strtolower( $match[1][0] );
			$attributes[ $name ] = array( 'value' => $match[3][0], 'start' => $absolute_start + $match[3][1], 'length' => strlen( $match[3][0] ) );
		}
		return $attributes;
	}

	private static function module_label( $module, $occurrence, array $attributes ) {
		if ( ! empty( $attributes['admin_label']['value'] ) ) { return html_entity_decode( $attributes['admin_label']['value'], ENT_QUOTES, 'UTF-8' ); }
		$name = ucwords( str_replace( '_', ' ', preg_replace( '/^et_pb_/', '', $module ) ) );
		return sprintf( '%s %d', $name, $occurrence );
	}

	private static function field_label( $field ) {
		return ucwords( str_replace( '_', ' ', $field ) );
	}

	private static function segment_id( $module, $occurrence, $field ) {
		return sanitize_key( 'divi_' . $module . '_' . absint( $occurrence ) . '_' . $field );
	}

	private static function has_text( $value ) {
		$value = preg_replace( '/@ET-DC@.*?@/s', '', $value );
		return '' !== trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' ) );
	}
}
