<?php
namespace OpenLingua;

defined( 'ABSPATH' ) || exit;

final class REST {
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route( 'openlingua/v1', '/languages', array(
			'methods' => \WP_REST_Server::READABLE,
			'callback' => array( __CLASS__, 'languages' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( 'openlingua/v1', '/translations/(?P<type>post|term)/(?P<id>\d+)', array(
			'methods' => \WP_REST_Server::READABLE,
			'callback' => array( __CLASS__, 'translations' ),
			'permission_callback' => '__return_true',
			'args' => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
		) );
	}

	public static function languages() {
		$result = array();
		foreach ( Languages::all() as $code => $language ) {
			$result[] = array( 'code' => $code, 'name' => $language['name'], 'locale' => $language['locale'], 'default' => Languages::default_code() === $code );
		}
		return rest_ensure_response( $result );
	}

	public static function translations( \WP_REST_Request $request ) {
		$type  = sanitize_key( $request['type'] );
		$id    = absint( $request['id'] );
		$items = array();
		foreach ( Translations::group( $type, $id ) as $language => $element_id ) {
			if ( 'post' === $type ) {
				if ( ! is_post_publicly_viewable( $element_id ) && ! current_user_can( 'edit_post', $element_id ) ) { continue; }
				$url = get_permalink( $element_id );
			} else {
				$term = get_term( $element_id );
				$taxonomy = $term && ! is_wp_error( $term ) ? get_taxonomy( $term->taxonomy ) : null;
				if ( ! $taxonomy || ( ! $taxonomy->public && ! current_user_can( $taxonomy->cap->manage_terms ) ) ) { continue; }
				$url = get_term_link( $term );
			}
			if ( ! is_wp_error( $url ) ) { $items[ $language ] = array( 'id' => absint( $element_id ), 'url' => $url ); }
		}
		return rest_ensure_response( array( 'type' => $type, 'source_id' => $id, 'translations' => $items ) );
	}
}
