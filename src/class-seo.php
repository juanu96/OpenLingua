<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class SEO {
	private static function meta_adapters() {
		return array(
			'yoast' => array(
				'name' => 'Yoast SEO',
				'fields' => array(
					'_yoast_wpseo_title' => __( 'SEO title', 'openlingua' ), '_yoast_wpseo_metadesc' => __( 'Meta description', 'openlingua' ),
					'_yoast_wpseo_focuskw' => __( 'Focus keyphrase', 'openlingua' ), '_yoast_wpseo_opengraph-title' => __( 'Facebook title', 'openlingua' ),
					'_yoast_wpseo_opengraph-description' => __( 'Facebook description', 'openlingua' ), '_yoast_wpseo_twitter-title' => __( 'X title', 'openlingua' ),
					'_yoast_wpseo_twitter-description' => __( 'X description', 'openlingua' ),
				),
			),
			'rank-math' => array(
				'name' => 'Rank Math',
				'fields' => array(
					'rank_math_title' => __( 'SEO title', 'openlingua' ), 'rank_math_description' => __( 'Meta description', 'openlingua' ),
					'rank_math_focus_keyword' => __( 'Focus keyword', 'openlingua' ), 'rank_math_facebook_title' => __( 'Facebook title', 'openlingua' ),
					'rank_math_facebook_description' => __( 'Facebook description', 'openlingua' ), 'rank_math_twitter_title' => __( 'X title', 'openlingua' ),
					'rank_math_twitter_description' => __( 'X description', 'openlingua' ),
				),
			),
			'seopress' => array(
				'name' => 'SEOPress',
				'fields' => array(
					'_seopress_titles_title' => __( 'SEO title', 'openlingua' ), '_seopress_titles_desc' => __( 'Meta description', 'openlingua' ),
					'_seopress_analysis_target_kw' => __( 'Target keywords', 'openlingua' ), '_seopress_social_fb_title' => __( 'Facebook title', 'openlingua' ),
					'_seopress_social_fb_desc' => __( 'Facebook description', 'openlingua' ), '_seopress_social_twitter_title' => __( 'X title', 'openlingua' ),
					'_seopress_social_twitter_desc' => __( 'X description', 'openlingua' ),
				),
			),
		);
	}

	public static function translation_fields( $source_id, $target_id ) {
		$groups = array();
		foreach ( self::meta_adapters() as $provider => $adapter ) {
			foreach ( $adapter['fields'] as $key => $label ) {
				$source = (string) get_post_meta( $source_id, $key, true );
				$target = (string) get_post_meta( $target_id, $key, true );
				if ( '' === $source && '' === $target ) { continue; }
				$groups[ $provider ]['name'] = $adapter['name'];
				$groups[ $provider ]['fields'][] = self::field( $provider, $key, $label, $source, $target );
			}
		}
		$aioseo = self::aioseo_rows( $source_id, $target_id );
		if ( $aioseo ) { $groups['aioseo'] = array( 'name' => 'All in One SEO', 'fields' => $aioseo ); }
		return (array) apply_filters( 'openlingua_seo_translation_fields', $groups, $source_id, $target_id );
	}

	private static function field( $provider, $key, $label, $source, $target ) {
		return array( 'id' => substr( hash( 'sha256', $provider . '|' . $key ), 0, 24 ), 'key' => $key, 'label' => $label, 'source' => $source, 'target' => $target );
	}

	private static function aioseo_rows( $source_id, $target_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_posts';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return array(); }
		$source = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE post_id = %d', $table, $source_id ), ARRAY_A );
		$target = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE post_id = %d', $table, $target_id ), ARRAY_A );
		$labels = array( 'title' => __( 'SEO title', 'openlingua' ), 'description' => __( 'Meta description', 'openlingua' ), 'og_title' => __( 'Facebook title', 'openlingua' ), 'og_description' => __( 'Facebook description', 'openlingua' ), 'twitter_title' => __( 'X title', 'openlingua' ), 'twitter_description' => __( 'X description', 'openlingua' ) );
		$fields = array();
		foreach ( $labels as $key => $label ) {
			$source_value = (string) ( $source[ $key ] ?? '' );
			$target_value = (string) ( $target[ $key ] ?? '' );
			if ( '' !== $source_value || '' !== $target_value ) { $fields[] = self::field( 'aioseo', $key, $label, $source_value, $target_value ); }
		}
		return $fields;
	}

	public static function save_translation_fields( $source_id, $target_id, array $submitted ) {
		$groups = self::translation_fields( $source_id, $target_id );
		$touched = array();
		foreach ( $groups as $provider => $group ) {
			foreach ( $group['fields'] as $field ) {
				if ( ! array_key_exists( $field['id'], $submitted ) ) { continue; }
				$value = sanitize_textarea_field( $submitted[ $field['id'] ] );
				if ( 'aioseo' === $provider ) { self::save_aioseo_field( $target_id, $field['key'], $value ); }
				else { update_post_meta( $target_id, $field['key'], $value ); }
				$touched[ $provider ] = true;
			}
		}
		if ( isset( $touched['yoast'] ) && function_exists( 'YoastSEO' ) ) {
			$yoast = YoastSEO();
			if ( isset( $yoast->helpers->indexable ) && method_exists( $yoast->helpers->indexable, 'delete_for_post' ) ) { $yoast->helpers->indexable->delete_for_post( $target_id ); }
		}
		do_action( 'openlingua_saved_seo_translation_fields', $source_id, $target_id, $submitted );
	}

	private static function save_aioseo_field( $post_id, $key, $value ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_posts';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM %i WHERE post_id = %d', $table, $post_id ) );
		if ( $exists ) { $wpdb->update( $table, array( $key => $value ), array( 'post_id' => $post_id ) ); }
		else { $wpdb->insert( $table, array( 'post_id' => $post_id, $key => $value ) ); }
	}

	public static function hooks() {
		add_action( 'wp_head', array( __CLASS__, 'hreflang' ), 2 );
		add_filter( 'get_canonical_url', array( __CLASS__, 'post_canonical' ), 10, 2 );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'canonical' ) );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'canonical' ) );
		add_filter( 'seopress_titles_canonical', array( __CLASS__, 'canonical' ) );
	}

	public static function hreflang() {
		$links = self::current_links();
		if ( count( $links ) < 2 ) { return; }
		foreach ( $links as $language => $url ) {
			echo '<link rel="alternate" hreflang="' . esc_attr( $language ) . '" href="' . esc_url( $url ) . '" />' . "\n";
		}
		$default = Languages::default_code();
		if ( isset( $links[ $default ] ) ) {
			echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $links[ $default ] ) . '" />' . "\n";
		}
	}

	public static function post_canonical( $url, $post ) {
		$row = Translations::row( 'post', $post->ID );
		return $row ? Languages::url( $url, $row->language ) : $url;
	}

	public static function canonical( $url ) {
		$links = self::current_links();
		return isset( $links[ Languages::current() ] ) ? $links[ Languages::current() ] : $url;
	}

	public static function current_links() {
		$links = array();
		$public_languages = \OpenLingua\Languages::public_all();
		if ( is_singular() ) {
			foreach ( Translations::group( 'post', get_queried_object_id() ) as $language => $post_id ) {
				if ( isset( $public_languages[ $language ] ) && 'publish' === get_post_status( $post_id ) ) { $links[ $language ] = get_permalink( $post_id ); }
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				foreach ( Translations::group( 'term', $term->term_id ) as $language => $term_id ) {
					if ( ! isset( $public_languages[ $language ] ) ) { continue; }
					$translated_term = get_term( absint( $term_id ) );
					if ( ! $translated_term || is_wp_error( $translated_term ) ) { continue; }
					$link = get_term_link( $translated_term, $translated_term->taxonomy );
					if ( ! is_wp_error( $link ) ) { $links[ $language ] = $link; }
				}
			}
		}
		return $links;
	}
}
