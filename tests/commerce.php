<?php
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	function apply_filters( $hook, $value ) { return $value; }
	function absint( $value ) { return abs( (int) $value ); }
	function get_post_type( $post_id ) { return in_array( $post_id, array( 20, 40 ), true ) ? 'product' : 'post'; }
}

namespace OpenLingua {
	final class Translations {
		public static function translated_id( $type, $id, $language ) {
			$map = array( 10 => 20, 30 => 40, 50 => 60 );
			return 'post' === $type && 'es' === $language ? ( $map[ $id ] ?? 0 ) : 0;
		}
	}
}

namespace OpenLingua\Modules {
	require dirname( __DIR__ ) . '/src/contracts/interface-module.php';
	require dirname( __DIR__ ) . '/src/modules/class-commerce.php';
	function commerce_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}.\n" ); exit( 1 ); } }
	$keys = Commerce::shared_keys();
	commerce_assert( in_array( '_downloadable_files', $keys, true ) && in_array( '_weight', $keys, true ), 'shares operational download and dimension data' );
	commerce_assert( array( 20, 40 ) === Commerce::translated_product_ids( array( 10, 30, 50, 10 ), 'es' ), 'maps related products to available target-language products only' );
	echo "WooCommerce integration tests passed.\n";
}
