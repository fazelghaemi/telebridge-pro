<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * administrative area. This file also includes all of the dependencies used by
 * the plugin, registers the activation and deactivation functions, and defines
 * a function that starts the plugin.
 *
 * @link              https://telebridge.ai
 * @since             1.0.0
 * @package           TeleBridge_Pro
 *
 * @wordpress-plugin
 * Plugin Name:       TeleBridge Pro - AI Telegram to WordPress
 * Plugin URI:        https://telebridge.ai
 * Description:       The ultimate bridge to sync Telegram posts to WordPress, WooCommerce, and JetEngine using AI (Google Gemini, OpenAI, GapGPT).
 * Version:           1.0.0
 * Author:            TeleBridge Team
 * Author URI:        https://telebridge.ai
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       telebridge-pro
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'TELEBRIDGE_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This can be used to set default options or create database tables.
 */
function activate_telebridge_pro() {
	// Set default options upon activation
	if ( false === get_option( 'telebridge_active_ai_provider' ) ) {
		add_option( 'telebridge_active_ai_provider', 'google' );
	}
	if ( false === get_option( 'telebridge_webhook_token' ) ) {
		add_option( 'telebridge_webhook_token', wp_generate_password( 20, false ) );
	}
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_telebridge_pro() {
	// Cleanup tasks can go here (optional)
}

register_activation_hook( __FILE__, 'activate_telebridge_pro' );
register_deactivation_hook( __FILE__, 'deactivate_telebridge_pro' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-telebridge-core.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_telebridge_pro() {

	$plugin = new TeleBridge_Core();
	$plugin->run();

}
run_telebridge_pro();