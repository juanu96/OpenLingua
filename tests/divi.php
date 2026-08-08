<?php
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) { return preg_replace( '/<[^>]*>/', '', $text ); }
}
define( 'ABSPATH', __DIR__ . '/' );

function __( $text ) { return $text; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function absint( $value ) { return abs( (int) $value ); }

require dirname( __DIR__ ) . '/src/class-divi-content.php';

function divi_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
	echo "PASS: {$message}\n";
}

$content = '[et_pb_section admin_label="Hero" background_color="#fff"]'
	. '[et_pb_row][et_pb_column]'
	. '[et_pb_text admin_label="Intro Text" _builder_version="4.24.0"]<h2>Clean energy</h2><p>For everyone.</p>[/et_pb_text]'
	. '[et_pb_button button_text="Explore &amp; Learn" button_url="https://example.test/services" _builder_version="4.24.0"][/et_pb_button]'
	. '[et_pb_blurb title="Solar Power" image="solar.jpg"]<p>Power your home.</p>[/et_pb_blurb]'
	. '[et_pb_text _dynamic_attributes="content"]@ET-DC@encoded-value@[/et_pb_text]'
	. '[dica_divi_carousel autoplay="off" item_spacing="30"]'
	. '[dica_divi_carouselitem title="Paula Barrado" image_url="https://example.test/paula.jpg"]<p>Wonderful service.</p>[/dica_divi_carouselitem]'
	. '[/dica_divi_carousel]'
	. '[vendor_card _builder_version="4.24.0" card_heading="Independent module"]<p>Detected from Divi metadata.</p>[/vendor_card]'
	. '[/et_pb_column][/et_pb_row][/et_pb_section]';

$segments = \OpenLingua\Divi_Content::extract( $content );
$values = \OpenLingua\Divi_Content::values( $content );

divi_assert( 8 === count( $segments ), 'extracts text content and translatable attributes only' );
divi_assert( '<h2>Clean energy</h2><p>For everyone.</p>' === $values['divi_et_pb_text_1_content'], 'extracts et_pb_text inner HTML' );
divi_assert( 'Explore & Learn' === $values['divi_et_pb_button_1_button_text'], 'decodes a button label for editing' );
divi_assert( 'Solar Power' === $values['divi_et_pb_blurb_1_title'], 'extracts a blurb title attribute' );
divi_assert( '<p>Power your home.</p>' === $values['divi_et_pb_blurb_1_content'], 'extracts blurb body content' );
divi_assert( false === strpos( implode( '|', array_keys( $values ) ), 'text_2_content' ), 'ignores Divi dynamic content payloads' );
divi_assert( 'Paula Barrado' === $values['divi_dica_divi_carouselitem_1_title'], 'detects a third-party Divi module title automatically' );
divi_assert( '<p>Wonderful service.</p>' === $values['divi_dica_divi_carouselitem_1_content'], 'detects third-party Divi module body content automatically' );
divi_assert( 'Independent module' === $values['divi_vendor_card_1_card_heading'], 'detects an unknown module through Divi metadata' );
divi_assert( ! isset( $values['divi_dica_divi_carousel_1_content'] ), 'does not duplicate text from a third-party container module' );

$translated = \OpenLingua\Divi_Content::apply( $content, array(
	'divi_et_pb_text_1_content' => '<h2>Energía limpia</h2><p>Para todos.</p>',
	'divi_et_pb_button_1_button_text' => 'Explorar "ahora"',
	'divi_et_pb_blurb_1_title' => 'Energía solar',
	'divi_et_pb_blurb_1_content' => '<p>Energía para tu hogar.</p>',
	'divi_dica_divi_carouselitem_1_title' => 'Paula Traducida',
	'divi_dica_divi_carouselitem_1_content' => '<p>Servicio maravilloso.</p>',
	'divi_vendor_card_1_card_heading' => 'Módulo independiente',
	'divi_vendor_card_1_content' => '<p>Detectado por metadatos Divi.</p>',
) );

divi_assert( false !== strpos( $translated, '<h2>Energía limpia</h2><p>Para todos.</p>' ), 'replaces module body text' );
divi_assert( false !== strpos( $translated, 'button_text="Explorar &quot;ahora&quot;"' ), 'escapes translated shortcode attributes' );
divi_assert( false !== strpos( $translated, 'button_url="https://example.test/services"' ), 'preserves non-translatable URLs' );
divi_assert( false !== strpos( $translated, '@ET-DC@encoded-value@' ), 'preserves dynamic Divi payloads' );
divi_assert( false !== strpos( $translated, 'title="Paula Traducida"' ) && false !== strpos( $translated, '<p>Servicio maravilloso.</p>' ), 'replaces third-party carousel text without changing its module structure' );
divi_assert( false !== strpos( $translated, 'card_heading="Módulo independiente"' ), 'replaces text from an unknown metadata-identified module' );
divi_assert( substr_count( $content, '[et_pb_' ) === substr_count( $translated, '[et_pb_' ), 'preserves the Divi module structure' );

echo "All OpenLingua Divi extractor tests passed.\n";
