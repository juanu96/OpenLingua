<?php
// Lightweight tests for translation-memory normalization and exact keys.
define( 'ABSPATH', __DIR__ . '/' );

function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }

require dirname( __DIR__ ) . '/src/class-translation-memory.php';

function openlingua_memory_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: {$expected}\nActual: {$actual}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

openlingua_memory_assert_same(
	'Hello world',
	\OpenLingua\Translation_Memory::normalize( "  Hello\r\n world\xC2\xA0" ),
	'normalizes whitespace for exact plain-text matching'
);
openlingua_memory_assert_same(
	'<h2>Hello</h2><p>World</p>',
	\OpenLingua\Translation_Memory::normalize( " <h2>Hello</h2>\n <p>World</p> ", 'html' ),
	'normalizes spacing between HTML elements without removing markup'
);
openlingua_memory_assert_same(
	\OpenLingua\Translation_Memory::key( 'Repeated text' ),
	\OpenLingua\Translation_Memory::key( " Repeated\n text " ),
	'creates the same key for equivalent plain text'
);
if ( \OpenLingua\Translation_Memory::key( '<strong>Text</strong>', 'html' ) === \OpenLingua\Translation_Memory::key( 'Text', 'text' ) ) {
	fwrite( STDERR, "FAIL: keeps HTML and plain-text memory keys separate\n" );
	exit( 1 );
}
echo "PASS: keeps HTML and plain-text memory keys separate\n";
