<?php
define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );

function __( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_kses_post( $value ) { return preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', (string) $value ); }
function has_blocks( $content ) { return false !== strpos( (string) $content, '<!-- wp:' ); }

require ABSPATH . 'wp-includes/class-wp-block-parser.php';

function parse_blocks( $content ) {
	return ( new WP_Block_Parser() )->parse( $content );
}

function serialize_block_attributes( $attributes ) {
	$encoded = json_encode( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return str_replace( array( '--', '<', '>', '&', '\\"' ), array( '\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026', '\\u0022' ), $encoded );
}

function serialize_block( $block ) {
	$content = '';
	$inner_index = 0;
	foreach ( $block['innerContent'] as $chunk ) {
		$content .= is_string( $chunk ) ? $chunk : serialize_block( $block['innerBlocks'][ $inner_index++ ] );
	}
	if ( empty( $block['blockName'] ) ) { return $content; }
	$name = 0 === strpos( $block['blockName'], 'core/' ) ? substr( $block['blockName'], 5 ) : $block['blockName'];
	$attrs = empty( $block['attrs'] ) ? '' : ' ' . serialize_block_attributes( $block['attrs'] );
	return '' === $content ? '<!-- wp:' . $name . $attrs . ' /-->' : '<!-- wp:' . $name . $attrs . ' -->' . $content . '<!-- /wp:' . $name . ' -->';
}

function serialize_blocks( $blocks ) {
	return implode( '', array_map( 'serialize_block', $blocks ) );
}

require dirname( __DIR__ ) . '/src/class-gutenberg-content.php';

function gutenberg_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
	echo "PASS: {$message}\n";
}

$content = '<!-- wp:heading --><h2 class="wp-block-heading">Clean energy</h2><!-- /wp:heading -->'
	. '<!-- wp:paragraph --><p>Power for everyone.</p><!-- /wp:paragraph -->'
	. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"text":"Explore services","url":"https://example.test/services"} --><div class="wp-block-button"><a class="wp-block-button__link">Explore services</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
	. '<!-- wp:acme/card {"title":"A better future","settings":{"description":"Made for every family.","color":"blue"},"className":"hero-card"} /-->';

$segments = \OpenLingua\Gutenberg_Content::extract( $content );
$values = \OpenLingua\Gutenberg_Content::values( $content );

gutenberg_assert( \OpenLingua\Gutenberg_Content::is_gutenberg( $content ), 'detects Gutenberg block content' );
gutenberg_assert( in_array( '<h2 class="wp-block-heading">Clean energy</h2>', $values, true ), 'extracts heading HTML without block comments' );
gutenberg_assert( in_array( 'Explore services', $values, true ), 'extracts a custom text attribute' );
gutenberg_assert( in_array( 'Made for every family.', $values, true ), 'extracts nested third-party block attributes' );
gutenberg_assert( ! in_array( 'https://example.test/services', $values, true ), 'does not expose URLs for translation' );
gutenberg_assert( ! in_array( 'hero-card', $values, true ) && ! in_array( 'blue', $values, true ), 'ignores technical styling attributes' );

$translations = array();
foreach ( $segments as $segment ) {
	if ( false !== strpos( $segment['value'], 'Clean energy' ) ) { $translations[ $segment['id'] ] = '<h2 class="wp-block-heading">Energía limpia</h2>'; }
	if ( 'Explore services' === $segment['value'] ) { $translations[ $segment['id'] ] = 'Explorar servicios'; }
	if ( 'Made for every family.' === $segment['value'] ) { $translations[ $segment['id'] ] = 'Creado para cada familia.'; }
}
$translated = \OpenLingua\Gutenberg_Content::apply( $content, $translations );

gutenberg_assert( false !== strpos( $translated, 'Energía limpia' ), 'replaces native block HTML' );
gutenberg_assert( false !== strpos( $translated, 'Explorar servicios' ), 'replaces block attributes' );
gutenberg_assert( false !== strpos( $translated, 'Creado para cada familia.' ), 'replaces nested attributes' );
gutenberg_assert( false !== strpos( $translated, 'https://example.test/services' ), 'preserves URLs' );
gutenberg_assert( count( parse_blocks( $translated ) ) === count( parse_blocks( $content ) ), 'preserves the top-level block structure' );

echo "All OpenLingua Gutenberg extractor tests passed.\n";
