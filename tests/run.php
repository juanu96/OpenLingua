<?php
// Lightweight tests for URL generation and language-prefix detection without booting WordPress.
define( 'ABSPATH', __DIR__ . '/' );

$openlingua_test_options = array(
	'openlingua_languages' => array(
		'en' => array( 'name' => 'English', 'locale' => 'en_US' ),
		'es' => array( 'name' => 'Español', 'locale' => 'es_ES' ),
	),
	'openlingua_default_language' => 'en',
);

function get_option( $key, $default = false ) { global $openlingua_test_options; return $openlingua_test_options[ $key ] ?? $default; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return stripslashes( $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '' ) { return 'https://example.test/site/' . ltrim( $path, '/' ); }
function is_admin() { return false; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
function add_query_arg( $key, $value, $url ) {
	$parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query ); $query[ $key ] = $value;
	$base = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? 'example.test' ) . ( $parts['path'] ?? '/' );
	return $base . '?' . http_build_query( $query );
}
function remove_query_arg( $key, $url ) {
	$parts = parse_url( $url );
	parse_str( $parts['query'] ?? '', $query );
	unset( $query[ $key ] );
	$base = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? 'example.test' ) . ( $parts['path'] ?? '/' );
	return $base . ( $query ? '?' . http_build_query( $query ) : '' );
}

require dirname( __DIR__ ) . '/src/class-languages.php';
require dirname( __DIR__ ) . '/src/class-routing.php';

function openlingua_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: {$expected}\nActual: {$actual}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

openlingua_assert_same(
	'https://example.test/site/es/about/?preview=1',
	\OpenLingua\Languages::url( 'https://example.test/site/about/?preview=1', 'es' ),
	'adds a language prefix and preserves query arguments'
);
openlingua_assert_same(
	'https://example.test/site/es/about/',
	\OpenLingua\Languages::url( 'https://example.test/site/en/about/', 'es' ),
	'replaces an existing language prefix'
);
openlingua_assert_same(
	'https://external.test/about/',
	\OpenLingua\Languages::url( 'https://external.test/about/', 'es' ),
	'does not rewrite external URLs'
);
openlingua_assert_same(
	'https://example.test/site/about/',
	\OpenLingua\Languages::url( 'https://example.test/site/about/', 'invalid' ),
	'does not generate URLs for unconfigured languages'
);

$openlingua_test_options['openlingua_language_settings'] = array( 'url_mode' => 'query', 'domains' => array() );
openlingua_assert_same(
	'https://example.test/site/about/?preview=1&lang=es',
	\OpenLingua\Languages::url( 'https://example.test/site/about/?preview=1', 'es' ),
	'generates query-parameter language URLs'
);

$openlingua_test_options['openlingua_language_settings'] = array( 'url_mode' => 'domain', 'domains' => array( 'en' => 'https://en.example.test', 'es' => 'https://es.example.test' ) );
openlingua_assert_same(
	'https://es.example.test/about/?preview=1',
	\OpenLingua\Languages::url( 'https://example.test/site/about/?preview=1', 'es' ),
	'generates language-domain URLs and preserves the content path'
);

$openlingua_test_options['openlingua_language_settings'] = array( 'url_mode' => 'directory', 'domains' => array() );
$_SERVER['REQUEST_URI'] = '/site/es/news/?page=2';
\OpenLingua\Routing::detect_prefix( true, null, array() );
openlingua_assert_same( '/site/news/?page=2', $_SERVER['REQUEST_URI'], 'strips the language prefix before WordPress routing' );
openlingua_assert_same( 'es', \OpenLingua\Languages::current(), 'sets the current language from the URL prefix' );

$openlingua_test_options['openlingua_language_settings'] = array( 'url_mode' => 'domain', 'domains' => array( 'en' => 'https://en.example.test', 'es' => 'https://es.example.test' ) );
$_SERVER['HTTP_HOST'] = 'en.example.test';
$_SERVER['REQUEST_URI'] = '/news/';
\OpenLingua\Routing::detect_prefix( true, null, array() );
openlingua_assert_same( 'en', \OpenLingua\Languages::current(), 'detects the language from a configured domain' );

echo "All OpenLingua routing tests passed.\n";
