<?php
/**
 * Plugin Name: MarketingSoHigh SEO
 * Plugin URI: https://marketingsohigh.com/seo-plugin
 * Description: The ultimate WordPress SEO plugin. Full RankMath/Yoast replacement with AI-powered marketing when connected to your MarketingSoHigh dashboard.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Techno Believe Solutions Ltd
 * Author URI: https://technobelieve.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: marketingsohigh-seo
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MSH_VERSION', '1.0.0' );
define( 'MSH_PLUGIN_FILE', __FILE__ );
define( 'MSH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MSH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MSH_DB_VERSION', '1.0.0' );
define( 'MSH_MINIMUM_WP', '6.0' );
define( 'MSH_MINIMUM_PHP', '8.0' );

require_once MSH_PLUGIN_DIR . 'includes/class-msh-autoloader.php';
new MSH_Autoloader();

require_once MSH_PLUGIN_DIR . 'includes/class-msh-loader.php';

function msh_init() {
    return MSH_Loader::get_instance();
}
add_action( 'plugins_loaded', 'msh_init' );

register_activation_hook( __FILE__, [ 'MSH_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'MSH_Deactivator', 'deactivate' ] );
