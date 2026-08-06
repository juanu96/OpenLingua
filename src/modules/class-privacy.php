<?php
namespace OpenLingua\Modules;

use OpenLingua\Contracts\Module;

defined( 'ABSPATH' ) || exit;

final class Privacy implements Module {
	public static function hooks() {
		add_action( 'admin_init', array( __CLASS__, 'policy' ) );
	}

	public static function policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) { return; }
		wp_add_privacy_policy_content( 'OpenLingua', wp_kses_post(
			'<p>' . __( 'OpenLingua stores language relationships, translated strings, workflow status, and optional translation-job metadata in this WordPress database. It does not send content to external services unless an administrator installs and configures a translation provider and explicitly creates a job.', 'openlingua' ) . '</p>'
		) );
	}
}

