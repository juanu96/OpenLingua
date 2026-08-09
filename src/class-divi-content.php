<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

/** Extracts translatable Divi shortcode values while preserving layout markup. */
final class Divi_Content {
	const SOURCE_SNAPSHOT_META = '_openlingua_divi_source_snapshot';
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
						'start' => $data['start'], 'length' => $data['length'], 'kind' => 'attribute', 'module' => $module, 'field' => $attribute,
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
					'value' => $value, 'start' => $opening['content_start'], 'length' => strlen( $value ), 'kind' => 'content', 'module' => $module, 'field' => 'content',
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
		if ( preg_match( '/(?:^|_)(?:url|uri|href|src|link|id|ids|class|css|style|color|colors|gradient|image|icon|font|size|width|height|align|alignment|orientation|placement|direction|layout|margin|padding|spacing|border|shadow|animation|position|transform|version|preset|order|speed|delay|duration|autoplay|loop|arrow|dots|responsive|desktop|tablet|phone|mobile|enabled|disabled|visibility|config|configuration|settings|props|metadata|global|shortcode|slider|revslider|alias)(?:$|_)/i', $attribute ) ) { return false; }
		if ( preg_match( '~^(?:on|off|yes|no|true|false|none|inherit|default|left|right|center|top|bottom|horizontal|vertical|start|end|justify|normal|reverse|\d+(?:\.\d+)?(?:px|em|rem|%|s|ms)?)$~i', $value ) ) { return false; }
		if ( preg_match( '~^(?:https?:)?//|^mailto:|^tel:|^#(?:[0-9a-f]{3,8})$~i', $value ) || self::looks_like_machine_payload( $value ) ) { return false; }
		$text = trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $text || ! preg_match( '/\p{L}/u', $text ) ) { return false; }
		if ( preg_match( '/(?:text|content|title|heading|caption|description|label|placeholder|message|button|summary|citation|author|name|alt|aria)/i', $attribute ) ) { return true; }
		return (bool) preg_match( '/\s|[.!?,;:¿¡]/u', $text );
	}

	private static function looks_like_machine_payload( $value ) {
		$value = trim( (string) $value );
		$revolution_decoded = str_ireplace( array( '%91', '%93' ), array( '[', ']' ), $value );
		$decoded = trim( rawurldecode( $revolution_decoded ) );
		foreach ( array_unique( array( $value, $decoded ) ) as $candidate ) {
			if ( preg_match( '~\[/?[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]~i', $candidate ) ) { return true; }
			if ( ! in_array( substr( $candidate, 0, 1 ), array( '{', '[' ), true ) ) { continue; }
			json_decode( $candidate, true );
			if ( JSON_ERROR_NONE === json_last_error() ) { return true; }
			if ( preg_match( '/(?:%22|["\']).+?(?:%22|["\'])\s*(?::|%3A)/i', $candidate ) ) { return true; }
			if ( preg_match( '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $candidate ) ) { return true; }
		}
		return false;
	}

	public static function values( $content ) {
		$values = array();
		foreach ( self::extract( $content ) as $segment ) { $values[ $segment['id'] ] = $segment['value']; }
		return $values;
	}

	public static function source_snapshot( $content ) {
		$segments = array();
		foreach ( self::extract( $content ) as $segment ) {
			$segments[ $segment['id'] ] = array(
				'hash'          => self::segment_hash( $segment ),
				'compatibility' => self::compatibility_key( $segment ),
				'value'         => self::normalized_value( $segment['value'], $segment['kind'] ),
			);
		}
		return array( 'version' => 2, 'segments' => $segments );
	}

	/** Aligns existing target values without assuming module occurrence numbers stayed stable. */
	public static function aligned_values( $source_content, $target_content, array $snapshot = array() ) {
		$source_segments = self::extract( $source_content );
		$target_segments = self::extract( $target_content );
		$values = array();
		$snapshot_segments = self::snapshot_segments( $snapshot );
		$legacy_snapshot = $snapshot && ! isset( $snapshot['version'] );
		$previous_pools = array();
		foreach ( $snapshot_segments as $old_id => $old_segment ) {
			if ( ! isset( $old_segment['compatibility'], $old_segment['value'] ) ) { continue; }
			$previous_pools[ $old_segment['compatibility'] . ':' . $old_segment['value'] ][] = (string) $old_id;
		}
		$pools = array();
		$source_owners = array();
		foreach ( $source_segments as $segment ) {
			$key = self::compatibility_key( $segment ) . ':' . self::normalized_value( $segment['value'], $segment['kind'] );
			$source_owners[ $key ] = $segment['id'];
		}
		foreach ( $target_segments as $segment ) {
			$values[ $segment['id'] ] = $segment['value'];
			$key = self::compatibility_key( $segment ) . ':' . self::normalized_value( $segment['value'], $segment['kind'] );
			$pools[ $key ][] = $segment;
		}
		$original_target_values = $values;
		$used = array();
		foreach ( $source_segments as $segment ) {
			$id = $segment['id'];
			$current_key = self::compatibility_key( $segment ) . ':' . self::normalized_value( $segment['value'], $segment['kind'] );
			if ( $snapshot_segments ) {
				$old_id = '';
				foreach ( $previous_pools[ $current_key ] ?? array() as $candidate_id ) {
					if ( isset( $used[ 'snapshot:' . $candidate_id ] ) ) { continue; }
					$old_id = $candidate_id;
					$used[ 'snapshot:' . $candidate_id ] = true;
					break;
				}
				if ( $old_id && array_key_exists( $old_id, $original_target_values ) ) {
					$values[ $id ] = $original_target_values[ $old_id ];
					$used[ $old_id ] = true;
					continue;
				}
				$values[ $id ] = '';
			} elseif ( $legacy_snapshot && ( ! isset( $snapshot[ $id ] ) || ! hash_equals( (string) $snapshot[ $id ], self::segment_hash( $segment ) ) ) ) {
				$values[ $id ] = '';
			}
			$key = self::compatibility_key( $segment ) . ':' . self::normalized_value( $segment['value'], $segment['kind'] );
			$matched = false;
			foreach ( $pools[ $key ] ?? array() as $candidate ) {
				if ( isset( $used[ $candidate['id'] ] ) ) { continue; }
				$values[ $id ] = $candidate['value'];
				$used[ $candidate['id'] ] = true;
				$matched = true;
				break;
			}
			if ( $matched || $snapshot || '' === trim( (string) ( $values[ $id ] ?? '' ) ) ) { continue; }
			$target_key = self::compatibility_key( $segment ) . ':' . self::normalized_value( $values[ $id ], $segment['kind'] );
			if ( ! empty( $source_owners[ $target_key ] ) && $source_owners[ $target_key ] !== $id ) { $values[ $id ] = ''; }
		}
		return $values;
	}

	private static function snapshot_segments( array $snapshot ) {
		if ( 2 !== (int) ( $snapshot['version'] ?? 0 ) || ! is_array( $snapshot['segments'] ?? null ) ) { return array(); }
		return $snapshot['segments'];
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

	/** Restores encoded embedded shortcodes that an older editor save may have altered. */
	public static function restore_embedded_shortcodes( $source_content, $target_content ) {
		$source_values = self::embedded_shortcode_attributes( (string) $source_content, true );
		$target_values = self::embedded_shortcode_attributes( (string) $target_content, false );
		$replacements = array();
		foreach ( $source_values as $key => $source ) {
			if ( ! isset( $target_values[ $key ] ) || $target_values[ $key ]['value'] === $source['value'] ) { continue; }
			$replacements[] = array( 'start' => $target_values[ $key ]['start'], 'length' => $target_values[ $key ]['length'], 'value' => $source['value'] );
		}
		usort( $replacements, function ( $left, $right ) { return $right['start'] <=> $left['start']; } );
		foreach ( $replacements as $replacement ) { $target_content = substr_replace( $target_content, $replacement['value'], $replacement['start'], $replacement['length'] ); }
		return $target_content;
	}

	private static function embedded_shortcode_attributes( $content, $source_only ) {
		preg_match_all( '~\[(?!/)([a-z][a-z0-9_-]*)\b([^\]]*)\]~i', $content, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		$counts = array();
		$result = array();
		foreach ( $tokens as $token ) {
			$module = strtolower( $token[1][0] );
			$counts[ $module ] = ( $counts[ $module ] ?? 0 ) + 1;
			foreach ( self::attributes( $token[2][0], $token[2][1] ) as $name => $data ) {
				$is_slider_attribute = (bool) preg_match( '/(?:^|_)(?:slider|revslider|shortcode)(?:$|_)/i', $name );
				if ( ! $is_slider_attribute || ( $source_only && ! self::looks_like_machine_payload( $data['value'] ) ) ) { continue; }
				$result[ $module . ':' . $counts[ $module ] . ':' . $name ] = $data;
			}
		}
		return $result;
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

	private static function segment_hash( array $segment ) {
		return hash( 'sha256', self::compatibility_key( $segment ) . "\n" . self::normalized_value( $segment['value'], $segment['kind'] ) );
	}

	private static function compatibility_key( array $segment ) {
		return ( $segment['module'] ?? '' ) . ':' . ( $segment['field'] ?? $segment['kind'] );
	}

	private static function normalized_value( $value, $kind ) {
		$value = html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = str_replace( array( "\r\n", "\r", "\xC2\xA0" ), array( "\n", "\n", ' ' ), $value );
		return 'content' === $kind ? trim( preg_replace( '/>\s+</u', '><', $value ) ) : trim( preg_replace( '/\s+/u', ' ', $value ) );
	}
}
