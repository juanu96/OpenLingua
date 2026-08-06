<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;

defined( 'ABSPATH' ) || exit;

final class CLI implements Module {
	public static function hooks() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) { \WP_CLI::add_command( 'openlingua', CLI_Command::class ); }
	}
}

class CLI_Command {
	/** Lists configured languages. */
	public function languages() {
		\WP_CLI\Utils\format_items( 'table', array_map( function ( $code, $item ) { return array( 'code' => $code, 'name' => $item['name'], 'locale' => $item['locale'] ); }, array_keys( \OpenLingua\Languages::all() ), array_values( \OpenLingua\Languages::all() ) ), array( 'code', 'name', 'locale' ) );
	}

	/** Exports a portable JSON snapshot. */
	public function export( $args, $assoc_args ) {
		$path = $assoc_args['file'] ?? 'openlingua-export.json';
		$result = file_put_contents( $path, wp_json_encode( Portability::snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $result ) { \WP_CLI::error( 'Could not write export file.' ); }
		\WP_CLI::success( 'Exported OpenLingua data to ' . $path );
	}

	/** Imports and merges a portable JSON snapshot. */
	public function import( $args, $assoc_args ) {
		$path = $assoc_args['file'] ?? '';
		if ( ! $path || ! is_readable( $path ) ) { \WP_CLI::error( 'Provide a readable --file path.' ); }
		$data = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$result = is_array( $data ) ? Portability::merge( $data ) : new \WP_Error( 'json', 'Invalid JSON.' );
		if ( is_wp_error( $result ) ) { \WP_CLI::error( $result->get_error_message() ); }
		\WP_CLI::success( 'OpenLingua data merged.' );
	}

	/** Prints diagnostic information. */
	public function diagnostics() {
		\WP_CLI::line( wp_json_encode( Diagnostics::report(), JSON_PRETTY_PRINT ) );
	}
}
