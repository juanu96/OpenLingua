<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;
use OpenLingua\Translations;

defined( 'ABSPATH' ) || exit;

final class Commerce implements Module {
	private static $syncing = false;

	public static function hooks() {
		add_filter( 'openlingua_meta_policy', array( __CLASS__, 'meta_policy' ), 10, 3 );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'sync_product_data' ), 20, 1 );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'sync_product_data' ), 20, 1 );
	}

	public static function meta_policy( $policy, $key, $post_type ) {
		if ( 'product' !== $post_type ) { return $policy; }
		if ( in_array( $key, array( '_sku', 'total_sales', '_wc_average_rating', '_wc_rating_count' ), true ) ) { return 'ignore'; }
		if ( in_array( $key, self::shared_keys(), true ) ) { return 'copy'; }
		return $policy;
	}

	public static function shared_keys() {
		return apply_filters( 'openlingua_woocommerce_shared_meta', array(
			'_regular_price', '_sale_price', '_price', '_stock', '_stock_status', '_manage_stock',
			'_backorders', '_tax_status', '_tax_class', '_virtual', '_downloadable',
		) );
	}

	public static function sync_product_data( $post_id ) {
		if ( self::$syncing || wp_is_post_revision( $post_id ) ) { return; }
		$row = Translations::row( 'post', $post_id );
		if ( ! $row || ! function_exists( 'wc_get_product' ) ) { return; }
		self::$syncing = true;
		foreach ( Translations::group( 'post', $post_id ) as $translation_id ) {
			if ( absint( $translation_id ) !== absint( $post_id ) ) { self::synchronize_product( $post_id, $translation_id ); }
		}
		self::$syncing = false;
	}

	public static function initialize_translation( $source_id, $target_id ) {
		if ( function_exists( 'wc_get_product' ) ) {
			self::$syncing = true;
			self::synchronize_product( $source_id, $target_id );
			self::$syncing = false;
		}
	}

	private static function synchronize_product( $source_id, $target_id ) {
		$source = wc_get_product( $source_id );
		$target = wc_get_product( $target_id );
		if ( ! $source || ! $target ) { return; }
		$setters = array(
			'regular_price', 'sale_price', 'manage_stock', 'stock_quantity', 'stock_status',
			'backorders', 'tax_status', 'tax_class', 'virtual', 'downloadable',
		);
		foreach ( $setters as $property ) {
			$getter = 'get_' . $property;
			$setter = 'set_' . $property;
			if ( is_callable( array( $source, $getter ) ) && is_callable( array( $target, $setter ) ) ) { $target->{$setter}( $source->{$getter}() ); }
		}
		$target->save();
	}
}
