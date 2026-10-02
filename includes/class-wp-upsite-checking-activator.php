<?php

/**
 * Fired during plugin activation
 *
 * @link       https://patlew.pl
 * @since      1.0.0
 *
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 * @author     Patryk Lewandowski <kontakt@patlew.pl>
 */
class Wp_Upsite_Checking_Activator {

	/**
	 * Store the default settings and schedule the checks.
	 *
	 * The defaults are written out on first activation only, so a plugin that is
	 * deactivated and activated again keeps the settings and clients it had.
	 * Rescheduling here restores the events that deactivation cleared.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-upsite-checking-settings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-upsite-checking-clients.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-upsite-checking-monitor.php';

		add_option( Wp_Upsite_Checking_Settings::OPTION, Wp_Upsite_Checking_Settings::defaults() );
		add_option( Wp_Upsite_Checking_Clients::OPTION, array() );

		Wp_Upsite_Checking_Monitor::reschedule_all();

	}

}
