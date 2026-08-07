<?php
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$acf_test_updates = array();

	function __( $text ) { return $text; }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
	function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
	function wp_kses_post( $value ) { return strip_tags( $value, '<p><strong><em><a>' ); }
	function get_post_type() { return 'page'; }
	function update_field( $key, $value, $post_id ) { global $acf_test_updates; $acf_test_updates[ $post_id ][ $key ] = $value; }

	function get_field_objects( $post_id ) {
		$headline = 1 === $post_id ? 'Original headline' : 'Old headline';
		$body = 1 === $post_id ? '<p>Original <strong>body</strong></p>' : '<p>Old body</p>';
		$tagline = 1 === $post_id ? 'Original tagline' : 'Old tagline';
		$cards = 1 === $post_id ? array( array( 'field_card_title' => 'First card' ), array( 'field_card_title' => 'Second card' ) ) : array( array( 'field_card_title' => 'Old first' ), array( 'field_card_title' => 'Old second' ) );
		return array(
			'headline' => array( 'key' => 'field_headline', 'name' => 'headline', 'label' => 'Headline', 'type' => 'text', 'value' => $headline ),
			'body' => array( 'key' => 'field_body', 'name' => 'body', 'label' => 'Body', 'type' => 'wysiwyg', 'value' => $body ),
			'settings' => array( 'key' => 'field_settings', 'name' => 'settings', 'label' => 'Settings', 'type' => 'group', 'value' => array( 'field_tagline' => $tagline ), 'sub_fields' => array(
				array( 'key' => 'field_tagline', 'name' => 'tagline', 'label' => 'Tagline', 'type' => 'textarea' ),
			) ),
			'cards' => array( 'key' => 'field_cards', 'name' => 'cards', 'label' => 'Cards', 'type' => 'repeater', 'value' => $cards, 'sub_fields' => array(
				array( 'key' => 'field_card_title', 'name' => 'card_title', 'label' => 'Card title', 'type' => 'text' ),
			) ),
			'shared_id' => array( 'key' => 'field_shared', 'name' => 'shared_id', 'label' => 'Shared ID', 'type' => 'text', 'value' => 'do-not-translate' ),
		);
	}
}

namespace OpenLingua\Modules {
	class Metadata {
		public static function policy( $key ) { return 'shared_id' === $key ? 'copy' : 'copy-once'; }
	}
}

namespace {
	require dirname( __DIR__ ) . '/src/class-acf-content.php';

	function acf_assert( $condition, $message ) {
		if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
		echo "PASS: {$message}\n";
	}

	$segments = \OpenLingua\ACF_Content::extract( 1 );
	$values = \OpenLingua\ACF_Content::values( 1 );
	acf_assert( 5 === count( $segments ), 'extracts supported root and nested ACF fields' );
	acf_assert( 'Original headline' === $values['acf_field_headline_value'], 'extracts a text field' );
	acf_assert( '<p>Original <strong>body</strong></p>' === $values['acf_field_body_value'], 'extracts WYSIWYG HTML' );
	acf_assert( 'Original tagline' === $values['acf_field_settings_field_tagline'], 'extracts a group subfield' );
	acf_assert( 'Second card' === $values['acf_field_cards_1_field_card_title'], 'extracts repeater rows independently' );
	acf_assert( ! isset( $values['acf_field_shared_value'] ), 'does not expose fields configured as copy' );

	\OpenLingua\ACF_Content::save( 1, 2, array(
		'acf_field_headline_value' => 'Titular traducido',
		'acf_field_body_value' => '<p>Cuerpo <strong>traducido</strong><script>bad()</script></p>',
		'acf_field_settings_field_tagline' => 'Descripción traducida',
		'acf_field_cards_0_field_card_title' => 'Primera tarjeta',
	), false );

	global $acf_test_updates;
	acf_assert( 'Titular traducido' === $acf_test_updates[2]['field_headline'], 'updates a root field by field key' );
	acf_assert( '<p>Cuerpo <strong>traducido</strong>bad()</p>' === $acf_test_updates[2]['field_body'], 'sanitizes translated WYSIWYG content' );
	acf_assert( 'Descripción traducida' === $acf_test_updates[2]['field_settings']['field_tagline'], 'preserves and updates group values' );
	acf_assert( 'Primera tarjeta' === $acf_test_updates[2]['field_cards'][0]['field_card_title'], 'updates a repeater row' );
	acf_assert( 'Old second' === $acf_test_updates[2]['field_cards'][1]['field_card_title'], 'preserves untouched repeater rows' );

	echo "All OpenLingua ACF tests passed.\n";
}
