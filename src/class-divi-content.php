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
		preg_match_all( '~\[(\/?)([a-z][a-z0-9_-]*)\b([^\]]*)\]~i', $content, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		$segments = array();
		$stack = array();
		$counts = array();

		foreach ( $tokens as $token ) {
			$is_closing = '/' === $token[1][0];
			$module = strtolower( $token[2][0] );
			$token_start = $token[0][1];
			$token_end = $token_start + strlen( $token[0][0] );
			if ( ! $is_closing ) {
				$attributes = self::attributes( $token[3][0], $token[3][1] );
				if ( ! self::is_divi_module( $module, $attributes ) ) { continue; }
				$counts[ $module ] = ( $counts[ $module ] ?? 0 ) + 1;
				$occurrence = $counts[ $module ];
				$label = self::module_label( $module, $occurrence, $attributes );
				foreach ( $attributes as $attribute => $data ) {
					if ( ! self::is_translatable_attribute( $attribute, $data['value'] ) ) { continue; }
					$segments[] = array(
						'id' => self::segment_id( $module, $occurrence, $attribute ),
						'label' => $label . ' — ' . self::field_label( $attribute ),
						'value' => html_entity_decode( $data['value'], ENT_QUOTES, 'UTF-8' ),
						'start' => $data['start'], 'length' => $data['length'], 'kind' => 'attribute',
					);
				}
				$dynamic = isset( $attributes['_dynamic_attributes'] ) && false !== strpos( $attributes['_dynamic_attributes']['value'], 'content' );
				$stack[] = array( 'module' => $module, 'occurrence' => $occurrence, 'content_start' => $token_end, 'label' => $label, 'extract_content' => ! $dynamic );
				continue;
			}

			for ( $index = count( $stack ) - 1; $index >= 0; $index-- ) {
				if ( $stack[ $index ]['module'] !== $module ) { continue; }
				$opening = $stack[ $index ];
				$stack = array_slice( $stack, 0, $index );
				if ( ! $opening['extract_content'] ) { break; }
				$value = substr( $content, $opening['content_start'], $token_start - $opening['content_start'] );
				if ( ! self::has_text( $value ) ) { break; }
				if ( ! in_array( $module, self::$content_modules, true ) && preg_match( '~\[\/?[a-z][a-z0-9_-]*\b~i', $value ) ) { break; }
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

	private static function is_divi_module( $module, array $attributes ) {
		if ( 0 === strpos( $module, 'et_pb_' ) || false !== strpos( $module, 'divi' ) ) { return true; }
		if ( isset( $attributes['_builder_version'] ) || isset( $attributes['_module_preset'] ) || isset( $attributes['global_colors_info'] ) ) { return true; }
		global $shortcode_tags;
		$callback = $shortcode_tags[ $module ] ?? null;
		return is_array( $callback ) && is_object( $callback[0] ?? null ) && class_exists( 'ET_Builder_Module' ) && is_a( $callback[0], 'ET_Builder_Module' );
	}

	private static function is_translatable_attribute( $attribute, $value ) {
		$attribute = strtolower( (string) $attribute );
		$value = trim( html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $value || 'admin_label' === $attribute || 0 === strpos( $attribute, '_' ) || false !== strpos( $value, '@ET-DC@' ) ) { return false; }
		if ( in_array( $attribute, self::$translatable_attributes, true ) ) { return true; }
		if ( preg_match( '/(?:^|_)(?:url|uri|href|src|link|id|ids|class|css|style|color|gradient|image|icon|font|size|width|height|align|layout|margin|padding|spacing|border|shadow|animation|position|transform|version|preset|order|speed|delay|duration|autoplay|loop|arrow|dots|responsive|desktop|tablet|phone|mobile|enabled|disabled|visibility)(?:$|_)/i', $attribute ) ) { return false; }
		if ( preg_match( '~^(?:on|off|yes|no|true|false|none|inherit|default|\d+(?:\.\d+)?(?:px|em|rem|%|s|ms)?)$~i', $value ) ) { return false; }
		if ( preg_match( '~^(?:https?:)?//|^mailto:|^tel:|^#(?:[0-9a-f]{3,8})$~i', $value ) || self::looks_like_json( $value ) ) { return false; }
		$text = trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $text || ! preg_match( '/\p{L}/u', $text ) ) { return false; }
		if ( preg_match( '/(?:text|content|title|heading|caption|description|label|placeholder|message|button|summary|citation|author|name|alt|aria)/i', $attribute ) ) { return true; }
		return (bool) preg_match( '/\s|[.!?,;:¿¡]/u', $text );
	}

	private static function looks_like_json( $value ) {
		$value = trim( (string) $value );
		if ( ! in_array( substr( $value, 0, 1 ), array( '{', '[' ), true ) ) { return false; }
		json_decode( $value, true );
		return JSON_ERROR_NONE === json_last_error();
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
