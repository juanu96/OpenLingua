<?php
/**
 * Plugin Name: OpenLingua
 * Plugin URI:  https://github.com/openlingua/openlingua
 * Description: Multilingual content, custom post types, custom fields and strings for WordPress.
 * Version:     1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author:      OpenLingua Contributors
 * License:     GPL-2.0-or-later
 * Text Domain: openlingua
 */

defined( 'ABSPATH' ) || exit;

define( 'OPENLINGUA_VERSION', '1.0.0' );
define( 'OPENLINGUA_FILE', __FILE__ );
define( 'OPENLINGUA_DIR', plugin_dir_path( __FILE__ ) );

require_once OPENLINGUA_DIR . 'src/class-database.php';
require_once OPENLINGUA_DIR . 'src/contracts/interface-module.php';
require_once OPENLINGUA_DIR . 'src/contracts/interface-translation-provider.php';
require_once OPENLINGUA_DIR . 'src/class-module-registry.php';
require_once OPENLINGUA_DIR . 'src/class-languages.php';
require_once OPENLINGUA_DIR . 'src/class-translations.php';
require_once OPENLINGUA_DIR . 'src/class-content.php';
require_once OPENLINGUA_DIR . 'src/class-taxonomies.php';
require_once OPENLINGUA_DIR . 'src/class-strings.php';
require_once OPENLINGUA_DIR . 'src/class-routing.php';
require_once OPENLINGUA_DIR . 'src/class-seo.php';
require_once OPENLINGUA_DIR . 'src/class-rest.php';
require_once OPENLINGUA_DIR . 'src/class-admin.php';
require_once OPENLINGUA_DIR . 'src/class-plugin.php';
require_once OPENLINGUA_DIR . 'src/modules/class-workflow.php';
require_once OPENLINGUA_DIR . 'src/modules/class-menus.php';
require_once OPENLINGUA_DIR . 'src/modules/class-metadata.php';
require_once OPENLINGUA_DIR . 'src/modules/class-commerce.php';
require_once OPENLINGUA_DIR . 'src/modules/class-providers.php';
require_once OPENLINGUA_DIR . 'src/modules/class-jobs.php';
require_once OPENLINGUA_DIR . 'src/modules/class-string-discovery.php';
require_once OPENLINGUA_DIR . 'src/modules/class-portability.php';
require_once OPENLINGUA_DIR . 'src/modules/class-diagnostics.php';
require_once OPENLINGUA_DIR . 'src/modules/class-privacy.php';
require_once OPENLINGUA_DIR . 'src/modules/class-cli.php';

register_activation_hook( __FILE__, array( 'OpenLingua\\Database', 'activate' ) );
add_action( 'plugins_loaded', array( 'OpenLingua\\Plugin', 'boot' ) );
