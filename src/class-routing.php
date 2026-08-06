<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class Routing {
	public static function hooks() {
		add_filter( 'do_parse_request', array( __CLASS__, 'detect_prefix' ), 1, 3 );
		add_filter( 'post_link', array( __CLASS__, 'post_url' ), 10, 2 );
		add_filter( 'post_type_link', array( __CLASS__, 'post_url' ), 10, 2 );
		add_filter( 'page_link', array( __CLASS__, 'page_url' ), 10, 2 );
		add_filter( 'term_link', array( __CLASS__, 'term_url' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_to_translation' ), 2 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 100 );
	}

	public static function detect_prefix( $do_parse, $wp, $extra_query_vars ) {
		if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
			return $do_parse;
		}
		$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$home_path   = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$base        = '/' . trim( $home_path, '/' );
		$base        = '/' === $base ? '' : $base;
		$relative    = 0 === strpos( $path, $base ) ? substr( $path, strlen( $base ) ) : $path;
		$codes       = array_map( 'preg_quote', array_keys( Languages::all() ) );
		if ( ! $codes || ! preg_match( '#^/(' . implode( '|', $codes ) . ')(?=/|$)#', $relative, $matches ) ) {
			return $do_parse;
		}
		Languages::set_current( $matches[1] );
		$stripped = preg_replace( '#^/' . preg_quote( $matches[1], '#' ) . '(?=/|$)#', '', $relative );
		$query    = (string) wp_parse_url( $request_uri, PHP_URL_QUERY );
		$_SERVER['REQUEST_URI'] = $base . ( $stripped ?: '/' ) . ( $query ? '?' . $query : '' );
		return $do_parse;
	}

	public static function post_url( $url, $post ) {
		$post = get_post( $post );
		if ( ! $post ) { return $url; }
		$row = Translations::row( 'post', $post->ID );
		return $row ? Languages::url( $url, $row->language ) : $url;
	}

	public static function page_url( $url, $post_id ) {
		return self::post_url( $url, get_post( $post_id ) );
	}

	public static function term_url( $url, $term, $taxonomy ) {
		$row = Translations::row( 'term', $term->term_id );
		return $row ? Languages::url( $url, $row->language ) : $url;
	}

	public static function redirect_to_translation() {
		if ( is_preview() ) { return; }
		if ( is_singular() ) {
			$element_type = 'post';
			$element_id   = get_queried_object_id();
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$element_type = 'term';
			$element_id   = get_queried_object_id();
		} else {
			return;
		}
		$row = Translations::row( $element_type, $element_id );
		if ( ! $row || $row->language === Languages::current() ) { return; }
		$translated_id = Translations::translated_id( $element_type, $element_id, Languages::current() );
		if ( 'post' === $element_type ) {
			$target = get_permalink( $translated_id ?: $element_id );
		} else {
			$target = get_term_link( $translated_id ?: $element_id );
		}
		if ( ! is_wp_error( $target ) ) { wp_safe_redirect( $target, 302 ); exit; }
	}

	public static function maybe_flush_rewrite_rules() {
		if ( get_option( 'openlingua_flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
			delete_option( 'openlingua_flush_rewrite_rules' );
		}
	}
}
