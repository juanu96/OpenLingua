<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class SEO {
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
		if ( is_singular() ) {
			foreach ( Translations::group( 'post', get_queried_object_id() ) as $language => $post_id ) {
				if ( 'publish' === get_post_status( $post_id ) ) { $links[ $language ] = get_permalink( $post_id ); }
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				foreach ( Translations::group( 'term', $term->term_id ) as $language => $term_id ) {
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
