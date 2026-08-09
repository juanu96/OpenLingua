<?php
namespace OpenLingua\Modules;

use OpenLingua\Admin;
use OpenLingua\Contracts\Module;
use OpenLingua\Database;
use OpenLingua\Languages;
use OpenLingua\Translations;

defined( 'ABSPATH' ) || exit;

/** Controls whether WordPress media is shared or filtered by content language. */
final class Media implements Module {
	const MODE_UNIFIED = 'unified';
	const MODE_SEPARATE = 'separate';

	public static function hooks() {
		add_action( 'add_attachment', array( __CLASS__, 'assign_uploaded_attachment' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'prepare_admin_query' ), 4 );
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'prepare_modal_query' ) );
		add_filter( 'posts_clauses', array( __CLASS__, 'filter_clauses' ), 8, 2 );
	}

	public static function mode() {
		$mode = sanitize_key( Language_Settings::get()['media_mode'] ?? self::MODE_UNIFIED );
		return self::MODE_SEPARATE === $mode ? self::MODE_SEPARATE : self::MODE_UNIFIED;
	}

	public static function current_admin_language() {
		$language = Admin::content_language();
		return 'all' === $language || Languages::is_valid( $language ) ? $language : Languages::default_code();
	}

	public static function assign_uploaded_attachment( $attachment_id ) {
		if ( self::MODE_SEPARATE !== self::mode() || ! $attachment_id ) { return; }
		$language = self::current_admin_language();
		Translations::assign( 'post', absint( $attachment_id ), 'all' === $language ? Languages::default_code() : $language );
	}

	public static function prepare_admin_query( $query ) {
		if ( ! is_admin() || ! $query || 'attachment' !== $query->get( 'post_type' ) ) { return; }
		$query->set( 'openlingua_media_library', self::mode() );
		if ( self::MODE_SEPARATE === self::mode() ) { $query->set( 'openlingua_media_language', self::current_admin_language() ); }
	}

	public static function prepare_modal_query( $query ) {
		$query['openlingua_media_library'] = self::mode();
		if ( self::MODE_SEPARATE === self::mode() ) { $query['openlingua_media_language'] = self::current_admin_language(); }
		return $query;
	}

	public static function filter_clauses( $clauses, $query ) {
		$mode = sanitize_key( $query->get( 'openlingua_media_library' ) );
		if ( ! in_array( $mode, array( self::MODE_UNIFIED, self::MODE_SEPARATE ), true ) ) { return $clauses; }
		global $wpdb;
		$table = Database::table( 'translations' );
		if ( self::MODE_UNIFIED === $mode ) {
			$clauses['where'] .= $wpdb->prepare(
				" AND (NOT EXISTS (SELECT 1 FROM %i ol_media_any WHERE ol_media_any.element_type = 'post' AND ol_media_any.element_id = %i.ID) OR EXISTS (SELECT 1 FROM %i ol_media_source WHERE ol_media_source.element_type = 'post' AND ol_media_source.element_id = %i.ID AND ol_media_source.source_language = %s))",
				$table,
				$wpdb->posts,
				$table,
				$wpdb->posts,
				''
			);
			return $clauses;
		}

		$language = sanitize_key( $query->get( 'openlingua_media_language' ) );
		if ( 'all' === $language ) { return $clauses; }
		if ( ! Languages::is_valid( $language ) ) { $language = Languages::default_code(); }
		$matching = $wpdb->prepare(
			"EXISTS (SELECT 1 FROM %i ol_media_lang WHERE ol_media_lang.element_type = 'post' AND ol_media_lang.element_id = %i.ID AND ol_media_lang.language = %s)",
			$table,
			$wpdb->posts,
			$language
		);
		if ( Languages::default_code() === $language ) {
			$unassigned = $wpdb->prepare(
				"NOT EXISTS (SELECT 1 FROM %i ol_media_unassigned WHERE ol_media_unassigned.element_type = 'post' AND ol_media_unassigned.element_id = %i.ID)",
				$table,
				$wpdb->posts
			);
			$clauses['where'] .= ' AND (' . $unassigned . ' OR ' . $matching . ')';
		} else {
			$clauses['where'] .= ' AND ' . $matching;
		}
		return $clauses;
	}
}
