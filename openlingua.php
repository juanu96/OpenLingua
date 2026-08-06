<?php
/**
 * Plugin Name: OpenLingua
 * Plugin URI:  https://github.com/openlingua/openlingua
 * Description: Multilingual content, custom post types, custom fields and strings for WordPress.
 * Version:     0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author:      OpenLingua Contributors
 * License:     GPL-2.0-or-later
 * Text Domain: openlingua
 */

defined( 'ABSPATH' ) || exit;

define( 'OPENLINGUA_VERSION', '0.1.0' );
define( 'OPENLINGUA_FILE', __FILE__ );
define( 'OPENLINGUA_DIR', plugin_dir_path( __FILE__ ) );

require_once OPENLINGUA_DIR . 'src/class-database.php';
require_once OPENLINGUA_DIR . 'src/class-languages.php';
require_once OPENLINGUA_DIR . 'src/class-translations.php';
require_once OPENLINGUA_DIR . 'src/class-content.php';
require_once OPENLINGUA_DIR . 'src/class-strings.php';
require_once OPENLINGUA_DIR . 'src/class-admin.php';
require_once OPENLINGUA_DIR . 'src/class-plugin.php';

register_activation_hook( __FILE__, array( 'OpenLingua\\Database', 'activate' ) );
add_action( 'plugins_loaded', array( 'OpenLingua\\Plugin', 'boot' ) );

