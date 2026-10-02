<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://patlew.pl
 * @since             1.0.0
 * @package           Wp_Upsite_Checking
 *
 * @wordpress-plugin
 * Plugin Name:       WP Upsite Checking
 * Plugin URI:        https://patlew.pl
 * Description:       Watches your clients' sites on a schedule and sends an e-mail notification when one goes down. Each client has its own address, recipients and interval.
 * Version:           1.0.0
 * Requires at least: 5.1
 * Requires PHP:      7.0
 * Author:            Patryk Lewandowski
 * Author URI:        https://patlew.pl/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-upsite-checking
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WP_UPSITE_CHECKING_VERSION', '1.0.0' );

/**
 * The plugin basename, used to hook the plugin's own row on the plugins list.
 */
define( 'WP_UPSITE_CHECKING_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wp-upsite-checking-activator.php
 */
function activate_wp_upsite_checking() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-upsite-checking-activator.php';
	Wp_Upsite_Checking_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wp-upsite-checking-deactivator.php
 */
function deactivate_wp_upsite_checking() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-upsite-checking-deactivator.php';
	Wp_Upsite_Checking_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_upsite_checking' );
register_deactivation_hook( __FILE__, 'deactivate_wp_upsite_checking' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wp-upsite-checking.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wp_upsite_checking() {

	$plugin = new Wp_Upsite_Checking();
	$plugin->run();

}
run_wp_upsite_checking();
