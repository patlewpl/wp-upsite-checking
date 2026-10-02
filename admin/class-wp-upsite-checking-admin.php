<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://patlew.pl
 * @since      1.0.0
 *
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Provides the client list, the add/edit form and the plugin-wide settings page.
 *
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/admin
 * @author     Patryk Lewandowski <kontakt@patlew.pl>
 */
class Wp_Upsite_Checking_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The hook suffixes of this plugin's screens, used to scope the asset enqueues.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $page_hooks    The hook suffixes returned by the menu functions.
	 */
	private $page_hooks = array();

	/**
	 * The capability required to view and change the settings.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string    $hook_suffix    The page currently being loaded.
	 */
	public function enqueue_styles( $hook_suffix = '' ) {

		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-upsite-checking-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Add the plugin's menu and its pages.
	 *
	 * @since    1.0.0
	 */
	public function add_menu_pages() {

		$this->page_hooks[] = add_menu_page(
			__( 'Uptime Checking', 'wp-upsite-checking' ),
			__( 'Uptime', 'wp-upsite-checking' ),
			self::CAPABILITY,
			$this->plugin_name,
			array( $this, 'render_clients_page' ),
			'dashicons-visibility',
			80
		);

		$this->page_hooks[] = add_submenu_page(
			$this->plugin_name,
			__( 'Monitored Clients', 'wp-upsite-checking' ),
			__( 'Clients', 'wp-upsite-checking' ),
			self::CAPABILITY,
			$this->plugin_name,
			array( $this, 'render_clients_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			$this->plugin_name,
			__( 'Uptime Checking Settings', 'wp-upsite-checking' ),
			__( 'Settings', 'wp-upsite-checking' ),
			self::CAPABILITY,
			$this->plugin_name . '-settings',
			array( $this, 'render_settings_page' )
		);

	}

	/**
	 * Add a shortcut to the client list on the plugins list.
	 *
	 * @since     1.0.0
	 * @param     array    $links    The action links for this plugin.
	 * @return    array              The links including the shortcut.
	 */
	public function add_action_links( $links ) {

		$shortcut = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $this->page_url() ),
			esc_html__( 'Clients', 'wp-upsite-checking' )
		);

		array_unshift( $links, $shortcut );

		return $links;

	}

	/**
	 * Build a URL to one of this plugin's screens.
	 *
	 * @since     1.0.0
	 * @param     array     $args    Query arguments to add.
	 * @return    string             The admin URL.
	 */
	public function page_url( $args = array() ) {

		return add_query_arg(
			array_merge( array( 'page' => $this->plugin_name ), $args ),
			admin_url( 'admin.php' )
		);

	}

	/**
	 * Register the plugin-wide settings shown on the settings page.
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {

		register_setting(
			$this->plugin_name . '-settings',
			Wp_Upsite_Checking_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Wp_Upsite_Checking_Settings', 'sanitize' ),
				'default'           => Wp_Upsite_Checking_Settings::defaults(),
			)
		);

		add_settings_section(
			'wp_upsite_checking_main',
			__( 'Defaults', 'wp-upsite-checking' ),
			array( $this, 'render_section_intro' ),
			$this->plugin_name . '-settings'
		);

		$fields = array(
			'recipients' => __( 'Always notify', 'wp-upsite-checking' ),
			'interval'   => __( 'Default interval', 'wp-upsite-checking' ),
			'timeout'    => __( 'Default timeout', 'wp-upsite-checking' ),
		);

		foreach ( $fields as $field => $label ) {
			add_settings_field(
				$field,
				$label,
				array( $this, 'render_field_' . $field ),
				$this->plugin_name . '-settings',
				'wp_upsite_checking_main',
				array( 'label_for' => 'wp-upsite-checking-' . $field )
			);
		}

	}

	/**
	 * Render the explanation shown at the top of the settings section.
	 *
	 * @since    1.0.0
	 */
	public function render_section_intro() {

		echo '<p>' . esc_html__( 'These apply across every client. The addresses below are notified about all of them, in addition to each client\'s own recipients.', 'wp-upsite-checking' ) . '</p>';

	}

	/**
	 * Render the global recipients field.
	 *
	 * @since    1.0.0
	 */
	public function render_field_recipients() {

		printf(
			'<textarea class="large-text code" rows="3" id="wp-upsite-checking-recipients" name="%s[recipients]">%s</textarea>',
			esc_attr( Wp_Upsite_Checking_Settings::OPTION ),
			esc_textarea( Wp_Upsite_Checking_Settings::get( 'recipients' ) )
		);

		echo '<p class="description">' . esc_html__( 'Your own address, typically. One or more, separated by commas or newlines. Leave empty to notify only each client\'s own recipients.', 'wp-upsite-checking' ) . '</p>';

		$reply_to = Wp_Upsite_Checking_Settings::get_reply_to();

		if ( $reply_to ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the e-mail address replies are sent to. */
						__( 'Replies to a notification go to %s.', 'wp-upsite-checking' ),
						$reply_to
					)
				)
			);
		}

	}

	/**
	 * Render the default interval field.
	 *
	 * @since    1.0.0
	 */
	public function render_field_interval() {

		printf(
			'<input type="number" class="small-text" id="wp-upsite-checking-interval" name="%s[interval]" value="%s" min="%d" max="%d" step="1" /> %s',
			esc_attr( Wp_Upsite_Checking_Settings::OPTION ),
			esc_attr( Wp_Upsite_Checking_Settings::get( 'interval' ) ),
			esc_attr( Wp_Upsite_Checking_Settings::MIN_INTERVAL ),
			esc_attr( Wp_Upsite_Checking_Settings::MAX_INTERVAL ),
			esc_html__( 'minutes', 'wp-upsite-checking' )
		);

		echo '<p class="description">' . esc_html__( 'What a newly added client starts with. Each client can then be given its own interval.', 'wp-upsite-checking' ) . '</p>';

	}

	/**
	 * Render the default timeout field.
	 *
	 * @since    1.0.0
	 */
	public function render_field_timeout() {

		printf(
			'<input type="number" class="small-text" id="wp-upsite-checking-timeout" name="%s[timeout]" value="%s" min="1" max="60" step="1" /> %s',
			esc_attr( Wp_Upsite_Checking_Settings::OPTION ),
			esc_attr( Wp_Upsite_Checking_Settings::get( 'timeout' ) ),
			esc_html__( 'seconds', 'wp-upsite-checking' )
		);

		echo '<p class="description">' . esc_html__( 'How long to wait for a response before treating a site as down.', 'wp-upsite-checking' ) . '</p>';

	}

	/**
	 * Render the client list, or the add/edit form.
	 *
	 * @since    1.0.0
	 */
	public function render_clients_page() {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

		if ( 'new' === $action || 'edit' === $action ) {
			$client = array();

			if ( 'edit' === $action ) {
				$client = Wp_Upsite_Checking_Clients::get( isset( $_GET['client'] ) ? absint( $_GET['client'] ) : 0 );

				if ( ! $client ) {
					wp_safe_redirect( $this->page_url( array( 'message' => 'missing' ) ) );
					exit;
				}
			}

			// A rejected submission is stashed so the form can be shown again as it
			// was typed. It is only restored onto the record it was submitted for,
			// so a stale stash cannot leak into another client's form.
			$stashed = get_transient( $this->form_transient_key() );

			if ( $stashed ) {
				delete_transient( $this->form_transient_key() );

				$stashed_id = absint( isset( $stashed['data']['id'] ) ? $stashed['data']['id'] : 0 );
				$current_id = absint( isset( $client['id'] ) ? $client['id'] : 0 );

				if ( $stashed_id === $current_id ) {
					$client = wp_parse_args( $stashed['data'], Wp_Upsite_Checking_Clients::defaults() );
					$errors = $stashed['errors'];
				}
			}

			$client = wp_parse_args( (array) $client, Wp_Upsite_Checking_Clients::defaults() );
			$errors = isset( $errors ) ? $errors : array();

			require plugin_dir_path( __FILE__ ) . 'partials/wp-upsite-checking-admin-client-form.php';

			return;
		}

		$clients = Wp_Upsite_Checking_Clients::all();

		require plugin_dir_path( __FILE__ ) . 'partials/wp-upsite-checking-admin-display.php';

	}

	/**
	 * Render the plugin-wide settings page.
	 *
	 * @since    1.0.0
	 */
	public function render_settings_page() {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		require plugin_dir_path( __FILE__ ) . 'partials/wp-upsite-checking-admin-settings.php';

	}

	/**
	 * Save a client submitted from the add/edit form.
	 *
	 * @since    1.0.0
	 */
	public function handle_save_client() {

		$this->verify_request( 'wp_upsite_checking_save_client' );

		$submitted = isset( $_POST['client'] ) ? wp_unslash( (array) $_POST['client'] ) : array();
		$result    = Wp_Upsite_Checking_Clients::save( $submitted );

		if ( is_wp_error( $result ) ) {
			set_transient(
				$this->form_transient_key(),
				array(
					'data'   => $submitted,
					'errors' => $result->get_error_messages(),
				),
				5 * MINUTE_IN_SECONDS
			);

			$id = absint( isset( $submitted['id'] ) ? $submitted['id'] : 0 );

			wp_safe_redirect(
				$this->page_url(
					$id
						? array( 'action' => 'edit', 'client' => $id )
						: array( 'action' => 'new' )
				)
			);
			exit;
		}

		Wp_Upsite_Checking_Monitor::reschedule_client( $result );

		wp_safe_redirect( $this->page_url( array( 'message' => 'saved' ) ) );
		exit;

	}

	/**
	 * Delete a client.
	 *
	 * @since    1.0.0
	 */
	public function handle_delete_client() {

		$id = isset( $_REQUEST['client'] ) ? absint( $_REQUEST['client'] ) : 0;

		$this->verify_request( 'wp_upsite_checking_delete_client_' . $id );

		Wp_Upsite_Checking_Clients::delete( $id );

		wp_safe_redirect( $this->page_url( array( 'message' => 'deleted' ) ) );
		exit;

	}

	/**
	 * Run one client's check immediately.
	 *
	 * @since    1.0.0
	 */
	public function handle_check_now() {

		$id = isset( $_REQUEST['client'] ) ? absint( $_REQUEST['client'] ) : 0;

		$this->verify_request( 'wp_upsite_checking_check_now_' . $id );

		$monitor = new Wp_Upsite_Checking_Monitor();
		$monitor->run_check( $id );

		wp_safe_redirect( $this->page_url( array( 'checked' => $id ) ) );
		exit;

	}

	/**
	 * Run every enabled client's check immediately.
	 *
	 * @since    1.0.0
	 */
	public function handle_check_all() {

		$this->verify_request( 'wp_upsite_checking_check_all' );

		$monitor = new Wp_Upsite_Checking_Monitor();

		foreach ( array_keys( Wp_Upsite_Checking_Clients::enabled() ) as $id ) {
			$monitor->run_check( $id );
		}

		wp_safe_redirect( $this->page_url( array( 'message' => 'checked_all' ) ) );
		exit;

	}

	/**
	 * Repair the schedule after the global settings are saved.
	 *
	 * The default interval deliberately does not move existing clients — each
	 * client owns its own. This is here because the settings page is where
	 * monitoring is most likely to be fixed after cron has drifted, and the
	 * repair only adds missing events rather than resetting healthy ones.
	 *
	 * @since    1.0.0
	 */
	public function handle_settings_saved() {

		Wp_Upsite_Checking_Monitor::reschedule_all();

	}

	/**
	 * Show the result of an action after the redirect.
	 *
	 * @since    1.0.0
	 */
	public function show_notices() {

		if ( empty( $_GET['page'] ) || $this->plugin_name !== $_GET['page'] ) {
			return;
		}

		if ( ! empty( $_GET['checked'] ) ) {
			$client = Wp_Upsite_Checking_Clients::get( absint( $_GET['checked'] ) );

			if ( $client ) {
				$state = Wp_Upsite_Checking_Clients::get_state( $client['id'] );
				$is_up = ( 'up' === $state['status'] );

				$this->notice(
					$is_up ? 'success' : 'error',
					$is_up
						/* translators: %s: the monitored address. */
						? sprintf( __( '%s answered normally.', 'wp-upsite-checking' ), $client['url'] )
						/* translators: 1: the monitored address, 2: the reason the check failed. */
						: trim( sprintf( __( '%1$s did not answer. %2$s', 'wp-upsite-checking' ), $client['url'], $state['message'] ) )
				);
			}

			return;
		}

		$messages = array(
			'saved'       => array( 'success', __( 'Client saved.', 'wp-upsite-checking' ) ),
			'deleted'     => array( 'success', __( 'Client deleted.', 'wp-upsite-checking' ) ),
			'checked_all' => array( 'success', __( 'All enabled clients were checked.', 'wp-upsite-checking' ) ),
			'missing'     => array( 'error', __( 'That client no longer exists.', 'wp-upsite-checking' ) ),
		);

		$message = isset( $_GET['message'] ) ? sanitize_key( $_GET['message'] ) : '';

		if ( isset( $messages[ $message ] ) ) {
			$this->notice( $messages[ $message ][0], $messages[ $message ][1] );
		}

	}

	/**
	 * Print an admin notice.
	 *
	 * @since    1.0.0
	 * @param    string    $type    The notice type, 'success' or 'error'.
	 * @param    string    $text    The message to show.
	 */
	private function notice( $type, $text ) {

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $text )
		);

	}

	/**
	 * Stop a request that is not an authorized submission from this plugin's screens.
	 *
	 * @since    1.0.0
	 * @param    string    $nonce_action    The nonce action the request must carry.
	 */
	private function verify_request( $nonce_action ) {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage monitored clients.', 'wp-upsite-checking' ) );
		}

		check_admin_referer( $nonce_action );

	}

	/**
	 * The transient key holding a rejected form submission for the current user.
	 *
	 * @since     1.0.0
	 * @return    string    The transient key.
	 */
	private function form_transient_key() {

		return 'wp_upsite_checking_form_' . get_current_user_id();

	}

}
