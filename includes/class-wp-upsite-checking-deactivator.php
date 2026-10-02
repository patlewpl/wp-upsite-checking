<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://patlew.pl
 * @since      1.0.0
 *
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 * @author     Patryk Lewandowski <kontakt@patlew.pl>
 */
class Wp_Upsite_Checking_Deactivator {

	/**
	 * Remove every client's recurring check from WP-Cron.
	 *
	 * The clients and settings are deliberately left in place; they are removed
	 * on uninstall.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-upsite-checking-settings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-upsite-checking-clients.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-upsite-checking-monitor.php';

		Wp_Upsite_Checking_Monitor::unschedule_all();

	}

}
