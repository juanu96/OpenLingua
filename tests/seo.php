<?php
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'ARRAY_A', 'ARRAY_A' );
	$GLOBALS['seo_meta'] = array(
		10 => array( '_yoast_wpseo_title' => 'Original SEO title', '_yoast_wpseo_metadesc' => 'Original description' ),
		20 => array( '_yoast_wpseo_title' => '', '_yoast_wpseo_metadesc' => '' ),
	);
	function __( $text ) { return $text; }
	function add_action() {}
	function add_filter() {}
	function apply_filters( $hook, $value ) { return $value; }
	function get_post_meta( $post_id, $key ) { return $GLOBALS['seo_meta'][ $post_id ][ $key ] ?? ''; }
	function update_post_meta( $post_id, $key, $value ) { $GLOBALS['seo_meta'][ $post_id ][ $key ] = $value; }
	function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
	function do_action() {}
}

namespace OpenLingua {
	final class SEO_Test_DB {
		public $prefix = 'wp_';
		public function prepare( $query ) { return $query; }
		public function get_var() { return null; }
	}
	$GLOBALS['wpdb'] = new SEO_Test_DB();
	require dirname( __DIR__ ) . '/src/class-seo.php';

	$groups = SEO::translation_fields( 10, 20 );
	if ( empty( $groups['yoast']['fields'] ) || 2 !== count( $groups['yoast']['fields'] ) ) { fwrite( STDERR, "FAIL: Yoast fields were not detected.\n" ); exit( 1 ); }
	$title = $groups['yoast']['fields'][0];
	SEO::save_translation_fields( 10, 20, array( $title['id'] => 'Título SEO traducido' ) );
	if ( 'Título SEO traducido' !== $GLOBALS['seo_meta'][20]['_yoast_wpseo_title'] ) { fwrite( STDERR, "FAIL: Yoast translation was not saved.\n" ); exit( 1 ); }
	echo "SEO translation tests passed.\n";
}
