<?php
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['media_options'] = array(
		'openlingua_default_language' => 'en',
		'openlingua_languages' => array( 'en' => array( 'name' => 'English' ), 'es' => array( 'name' => 'Spanish' ) ),
		'openlingua_language_settings' => array( 'media_mode' => 'unified' ),
	);
	$GLOBALS['media_assignments'] = array();
	function add_action() {}
	function add_filter() {}
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
	function absint( $value ) { return abs( (int) $value ); }
	function get_option( $key, $default = false ) { return $GLOBALS['media_options'][ $key ] ?? $default; }
	function is_admin() { return true; }
	class Media_WPDB {
		public $posts = 'wp_posts';
		public function prepare( $query, ...$args ) {
			foreach ( $args as $arg ) {
				$replacement = is_string( $arg ) ? "'" . $arg . "'" : (string) $arg;
				$query = preg_replace( '/%[isd]/', $replacement, $query, 1 );
			}
			return $query;
		}
	}
	$GLOBALS['wpdb'] = new Media_WPDB();
}

namespace OpenLingua {
	class Database { public static function table( $name ) { return 'wp_openlingua_' . $name; } }
	class Admin { public static $language = 'es'; public static function content_language() { return self::$language; } }
	class Languages {
		public static function all() { return $GLOBALS['media_options']['openlingua_languages']; }
		public static function is_valid( $code ) { return isset( self::all()[ $code ] ); }
		public static function default_code() { return $GLOBALS['media_options']['openlingua_default_language']; }
	}
	class Translations { public static function assign( $type, $id, $language ) { $GLOBALS['media_assignments'][] = compact( 'type', 'id', 'language' ); } }
}

namespace OpenLingua\Modules {
	class Language_Settings { public static function get() { return array_replace( array( 'media_mode' => 'unified' ), (array) get_option( 'openlingua_language_settings', array() ) ); } }
}

namespace {
	require dirname( __DIR__ ) . '/src/contracts/interface-module.php';
	require dirname( __DIR__ ) . '/src/modules/class-media.php';

	function media_assert( $condition, $message ) {
		if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
		echo "PASS: {$message}\n";
	}

	class Media_Query {
		private $vars = array();
		public function __construct( array $vars = array() ) { $this->vars = $vars; }
		public function get( $key ) { return $this->vars[ $key ] ?? ''; }
		public function set( $key, $value ) { $this->vars[ $key ] = $value; }
	}

	media_assert( 'unified' === \OpenLingua\Modules\Media::mode(), 'uses a unified media library by default' );
	\OpenLingua\Modules\Media::assign_uploaded_attachment( 42 );
	media_assert( array() === $GLOBALS['media_assignments'], 'does not create language relationships for unified uploads' );

	$GLOBALS['media_options']['openlingua_language_settings']['media_mode'] = 'separate';
	\OpenLingua\Modules\Media::assign_uploaded_attachment( 42 );
	media_assert( 'es' === $GLOBALS['media_assignments'][0]['language'], 'assigns new uploads to the current content language in separate mode' );
	$modal = \OpenLingua\Modules\Media::prepare_modal_query( array( 'post_mime_type' => 'image' ) );
	media_assert( 'separate' === $modal['openlingua_media_library'] && 'es' === $modal['openlingua_media_language'], 'filters visual-builder and editor media selectors by current language' );

	$query = new Media_Query( array( 'openlingua_media_library' => 'separate', 'openlingua_media_language' => 'es' ) );
	$clauses = \OpenLingua\Modules\Media::filter_clauses( array( 'where' => '' ), $query );
	media_assert( false !== strpos( $clauses['where'], "language = 'es'" ), 'limits separated media queries to the selected language' );
	$query = new Media_Query( array( 'openlingua_media_library' => 'separate', 'openlingua_media_language' => 'all' ) );
	$clauses = \OpenLingua\Modules\Media::filter_clauses( array( 'where' => 'original' ), $query );
	media_assert( 'original' === $clauses['where'], 'keeps every attachment visible when All languages is selected' );

	$query = new Media_Query( array( 'openlingua_media_library' => 'unified' ) );
	$clauses = \OpenLingua\Modules\Media::filter_clauses( array( 'where' => '' ), $query );
	media_assert( false !== strpos( $clauses['where'], 'source_language' ), 'collapses legacy translated attachment records in unified mode' );
	echo "All OpenLingua media tests passed.\n";
}
