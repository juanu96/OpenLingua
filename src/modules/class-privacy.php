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
			'<p>' . __( 'OpenLingua stores language relationships, translated strings, settings, workflow status, and translation-job metadata in this WordPress database. This data is preserved when the plugin is removed unless the site owner explicitly enables permanent removal before uninstalling.', 'openlingua' ) . '</p>' .
			'<p>' . __( 'Manual translation does not require an external service. If an administrator configures OpenAI, Anthropic, Google Gemini, or Google Cloud Translation and explicitly starts an automatic translation job, OpenLingua sends the selected content, source and target language identifiers, translation instructions, and the site owner\'s API credential to that provider. Model-list requests send the credential but no site content. The site owner should disclose the selected provider and review its terms, privacy policy, retention settings, billing, and consent requirements.', 'openlingua' ) . '</p>' .
			'<p>' . __( 'OpenLingua does not include analytics or usage tracking.', 'openlingua' ) . '</p>'
		) );
	}
}
