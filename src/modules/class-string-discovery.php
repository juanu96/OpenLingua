<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;
use OpenLingua\Languages;
use OpenLingua\Strings;

defined( 'ABSPATH' ) || exit;

final class String_Discovery implements Module {
	private static $seen = array();

	public static function hooks() {
		if ( get_option( 'openlingua_string_discovery', false ) ) {
			add_filter( 'gettext', array( __CLASS__, 'capture' ), 999, 3 );
		}
	}

	public static function capture( $translated, $original, $domain ) {
		if ( '' === trim( $original ) || isset( self::$seen[ $domain ][ $original ] ) ) { return $translated; }
		self::$seen[ $domain ][ $original ] = true;
		$key = substr( hash( 'sha256', $domain . "\0" . $original ), 0, 32 );
		Strings::register( $key, $original, $domain, Languages::default_code() );
		return $translated;
	}
}

