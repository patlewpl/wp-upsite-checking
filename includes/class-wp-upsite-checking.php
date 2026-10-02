<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across the
 * scheduled checks and the admin area.
 *
 * @link       https://patlew.pl
 * @since      1.0.0
 *
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, the scheduled uptime checks and
 * the admin-specific hooks. The plugin has no front-end behaviour.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 * @author     Patryk Lewandowski <kontakt@patlew.pl>
 */
class Wp_Upsite_Checking {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Wp_Upsite_Checking_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the scheduled
	 * checks and the admin area.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'WP_UPSITE_CHECKING_VERSION' ) ) {
			$this->version = WP_UPSITE_CHECKING_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'wp-upsite-checking';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_monitor_hooks();
		$this->define_updater_hooks();
		$this->define_admin_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wp_Upsite_Checking_Loader. Orchestrates the hooks of the plugin.
	 * - Wp_Upsite_Checking_i18n. Defines internationalization functionality.
	 * - Wp_Upsite_Checking_Settings. Stores the plugin-wide settings.
	 * - Wp_Upsite_Checking_Clients. Stores the monitored clients.
	 * - Wp_Upsite_Checking_Monitor. Runs the checks and sends the notifications.
	 * - Wp_Upsite_Checking_Updater. Offers updates from the GitHub repository.
	 * - Wp_Upsite_Checking_Admin. Defines all hooks for the admin area.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-upsite-checking-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-upsite-checking-i18n.php';

		/**
		 * The class responsible for storing and sanitizing the plugin settings.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-upsite-checking-settings.php';

		/**
		 * The class responsible for storing the monitored clients.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-upsite-checking-clients.php';

		/**
		 * The class responsible for checking the clients and sending notifications.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-upsite-checking-monitor.php';

		/**
		 * The class responsible for offering updates from the GitHub repository.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-upsite-checking-updater.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wp-upsite-checking-admin.php';

		$this->loader = new Wp_Upsite_Checking_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wp_Upsite_Checking_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Wp_Upsite_Checking_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register the hooks that run the uptime check.
	 *
	 * These are registered on every request, not just in the admin area: WP-Cron
	 * runs the event on a front-end request, where the admin hooks never load.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_monitor_hooks() {

		$monitor = new Wp_Upsite_Checking_Monitor();

		$this->loader->add_filter( 'cron_schedules', $monitor, 'add_cron_schedules' );

		// The event carries the ID of the client to check, so the callback has to
		// be given that argument.
		$this->loader->add_action( Wp_Upsite_Checking_Monitor::EVENT, $monitor, 'run_check', 10, 1 );

	}

	/**
	 * Register the hooks that offer updates from the repository.
	 *
	 * Registered outside the admin hooks because WP-Cron refreshes the update
	 * transient on its own, with no admin screen loaded.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_updater_hooks() {

		$updater = new Wp_Upsite_Checking_Updater();

		$this->loader->add_filter( 'site_transient_update_plugins', $updater, 'check_for_update' );
		$this->loader->add_filter( 'upgrader_source_selection', $updater, 'fix_source_dir', 10, 4 );
		$this->loader->add_action( 'upgrader_process_complete', $updater, 'flush_cache', 10, 2 );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Wp_Upsite_Checking_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_menu_pages' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'show_notices' );
		$this->loader->add_action( 'admin_post_wp_upsite_checking_save_client', $plugin_admin, 'handle_save_client' );
		$this->loader->add_action( 'admin_post_wp_upsite_checking_delete_client', $plugin_admin, 'handle_delete_client' );
		$this->loader->add_action( 'admin_post_wp_upsite_checking_check_now', $plugin_admin, 'handle_check_now' );
		$this->loader->add_action( 'admin_post_wp_upsite_checking_check_all', $plugin_admin, 'handle_check_all' );
		$this->loader->add_action( 'update_option_' . Wp_Upsite_Checking_Settings::OPTION, $plugin_admin, 'handle_settings_saved' );
		$this->loader->add_filter( 'plugin_action_links_' . WP_UPSITE_CHECKING_BASENAME, $plugin_admin, 'add_action_links' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Wp_Upsite_Checking_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
