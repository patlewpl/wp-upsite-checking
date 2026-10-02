<?php

/**
 * Stores the monitored clients.
 *
 * @link       https://patlew.pl
 * @since      1.0.0
 *
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 */

/**
 * Stores the monitored clients.
 *
 * Each client is one site to watch, with its own address, notification
 * recipients and check interval. The whole list lives in a single option: an
 * agency watches tens of sites, not thousands, so one autoloaded array is
 * cheaper than a custom table and needs no schema upgrades.
 *
 * The result of the last check is kept in a separate, non-autoloaded option
 * keyed by client ID, because it changes on every check while the client
 * records themselves rarely change.
 *
 * @since      1.0.0
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 * @author     Patryk Lewandowski <kontakt@patlew.pl>
 */
class Wp_Upsite_Checking_Clients {

	/**
	 * The option name holding the client list.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const OPTION = 'wp_upsite_checking_clients';

	/**
	 * The option name holding the last check result per client.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const STATE = 'wp_upsite_checking_state';

	/**
	 * How many extra request headers a client may carry.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	const MAX_HEADERS = 10;

	/**
	 * The fields a client record consists of, with their fallback values.
	 *
	 * @since     1.0.0
	 * @return    array    The default client record.
	 */
	public static function defaults() {

		return array(
			'id'         => 0,
			'name'       => '',
			'url'        => '',
			'recipients' => '',
			'headers'    => '',
			'interval'   => Wp_Upsite_Checking_Settings::get( 'interval' ),
			'timeout'    => Wp_Upsite_Checking_Settings::get( 'timeout' ),
			'enabled'    => 1,
		);

	}

	/**
	 * Retrieve every client, ordered by name.
	 *
	 * @since     1.0.0
	 * @return    array    The client records, keyed by ID.
	 */
	public static function all() {

		$clients = (array) get_option( self::OPTION, array() );

		foreach ( $clients as $id => $client ) {
			$clients[ $id ] = wp_parse_args( (array) $client, self::defaults() );
		}

		uasort(
			$clients,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $clients;

	}

	/**
	 * Retrieve the clients whose checks should be running.
	 *
	 * @since     1.0.0
	 * @return    array    The enabled client records, keyed by ID.
	 */
	public static function enabled() {

		return array_filter(
			self::all(),
			function ( $client ) {
				return ! empty( $client['enabled'] );
			}
		);

	}

	/**
	 * Retrieve a single client.
	 *
	 * @since     1.0.0
	 * @param     int            $id    The client ID.
	 * @return    array|null            The client record, or null when there is no such client.
	 */
	public static function get( $id ) {

		$clients = (array) get_option( self::OPTION, array() );
		$id      = (int) $id;

		if ( ! isset( $clients[ $id ] ) ) {
			return null;
		}

		return wp_parse_args( (array) $clients[ $id ], self::defaults() );

	}

	/**
	 * Create or update a client.
	 *
	 * @since     1.0.0
	 * @param     array             $data    The submitted client data.
	 * @return    array|WP_Error             The stored record, or an error describing what was rejected.
	 */
	public static function save( $data ) {

		$client = self::sanitize( $data );

		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$clients = (array) get_option( self::OPTION, array() );

		if ( ! $client['id'] ) {
			$ids           = array_map( 'intval', array_keys( $clients ) );
			$client['id']  = $ids ? max( $ids ) + 1 : 1;
		}

		$clients[ $client['id'] ] = $client;

		update_option( self::OPTION, $clients );

		return $client;

	}

	/**
	 * Remove a client, its stored state and its scheduled check.
	 *
	 * @since     1.0.0
	 * @param     int     $id    The client ID.
	 * @return    bool           Whether a client was removed.
	 */
	public static function delete( $id ) {

		$clients = (array) get_option( self::OPTION, array() );
		$id      = (int) $id;

		if ( ! isset( $clients[ $id ] ) ) {
			return false;
		}

		unset( $clients[ $id ] );
		update_option( self::OPTION, $clients );

		Wp_Upsite_Checking_Monitor::unschedule_client( $id );
		self::delete_state( $id );

		return true;

	}

	/**
	 * Validate and clean a submitted client record.
	 *
	 * @since     1.0.0
	 * @param     array             $data    The submitted client data.
	 * @return    array|WP_Error             The record safe to store, or an error listing every rejected field.
	 */
	public static function sanitize( $data ) {

		$data     = wp_parse_args( (array) $data, self::defaults() );
		$defaults = self::defaults();
		$errors   = new WP_Error();

		$client = array(
			'id'      => absint( $data['id'] ),
			'name'    => sanitize_text_field( $data['name'] ),
			'enabled' => empty( $data['enabled'] ) ? 0 : 1,
		);

		$client['url'] = esc_url_raw( trim( (string) $data['url'] ), array( 'http', 'https' ) );

		if ( '' === $client['url'] ) {
			$errors->add( 'url', __( 'Enter the address to monitor as a full http:// or https:// URL.', 'wp-upsite-checking' ) );
		}

		if ( '' === $client['name'] ) {
			// The name is only a label, so fall back to the host rather than refusing the record.
			$host           = wp_parse_url( $client['url'], PHP_URL_HOST );
			$client['name'] = $host ? $host : __( 'Unnamed client', 'wp-upsite-checking' );
		}

		$recipients           = Wp_Upsite_Checking_Settings::parse_recipients( $data['recipients'] );
		$client['recipients'] = implode( ', ', $recipients );

		// A client without its own recipients is fine as long as the global
		// addresses will catch the notification; otherwise nobody would hear about it.
		if ( empty( $recipients ) && ! Wp_Upsite_Checking_Settings::get_recipients() ) {
			$errors->add(
				'recipients',
				__( 'Enter at least one notification e-mail for this client, or set a global address under Settings.', 'wp-upsite-checking' )
			);
		}

		$rejected          = array();
		$headers           = self::parse_headers( $data['headers'], $rejected );
		$client['headers'] = self::format_headers( $headers );

		if ( $rejected ) {
			$errors->add(
				'headers',
				sprintf(
					/* translators: 1: the header lines that were rejected, 2: how many headers are allowed. */
					__( 'Each extra header must be one "Name: value" line, and there is a limit of %2$d. These were not used: %1$s', 'wp-upsite-checking' ),
					implode( ' / ', $rejected ),
					self::MAX_HEADERS
				)
			);
		}

		$interval            = absint( $data['interval'] );
		$client['interval']  = $interval
			? min( Wp_Upsite_Checking_Settings::MAX_INTERVAL, max( Wp_Upsite_Checking_Settings::MIN_INTERVAL, $interval ) )
			: $defaults['interval'];

		$timeout           = absint( $data['timeout'] );
		$client['timeout'] = $timeout ? min( 60, max( 1, $timeout ) ) : $defaults['timeout'];

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return $client;

	}

	/**
	 * Turn a client's header field into headers for the request.
	 *
	 * Written as one "Name: value" per line. A name has to be a valid HTTP token
	 * and a value cannot carry a line break, otherwise a stray newline in the
	 * field would let further headers be injected into the request.
	 *
	 * @since     1.0.0
	 * @param     string    $raw         The raw value of the headers field.
	 * @param     array     $rejected    Filled with the lines that could not be used.
	 * @return    array                  The headers, keyed by name.
	 */
	public static function parse_headers( $raw, &$rejected = null ) {

		$headers  = array();
		$rejected = array();

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			if ( count( $headers ) >= self::MAX_HEADERS ) {
				$rejected[] = $line;
				continue;
			}

			$parts = explode( ':', $line, 2 );
			$name  = isset( $parts[1] ) ? trim( $parts[0] ) : '';
			$value = isset( $parts[1] ) ? trim( $parts[1] ) : '';

			// Strip anything that could break out of the header value.
			$value = preg_replace( '/[\x00-\x1F\x7F]/', '', $value );

			if ( '' === $value || ! preg_match( '/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $name ) ) {
				$rejected[] = $line;
				continue;
			}

			$headers[ $name ] = $value;
		}

		return $headers;

	}

	/**
	 * Render headers back into the one-per-line form shown in the form.
	 *
	 * @since     1.0.0
	 * @param     array     $headers    The headers, keyed by name.
	 * @return    string                One "Name: value" per line.
	 */
	public static function format_headers( $headers ) {

		$lines = array();

		foreach ( (array) $headers as $name => $value ) {
			$lines[] = $name . ': ' . $value;
		}

		return implode( "\n", $lines );

	}

	/**
	 * Retrieve the addresses notified about a client.
	 *
	 * The client's own recipients and the global addresses are merged, so the
	 * agency can be told about every client while each client hears only about
	 * their own site.
	 *
	 * @since     1.0.0
	 * @param     array    $client    The client record.
	 * @return    array               Zero or more unique e-mail addresses.
	 */
	public static function get_recipients( $client ) {

		$recipients = array_merge(
			Wp_Upsite_Checking_Settings::parse_recipients( $client['recipients'] ),
			Wp_Upsite_Checking_Settings::get_recipients()
		);

		return array_values( array_unique( $recipients ) );

	}

	/**
	 * Retrieve the stored result of the last check for a client.
	 *
	 * @since     1.0.0
	 * @param     int      $id    The client ID.
	 * @return    array           The last result, with 'status' set to 'unknown' before the first check.
	 */
	public static function get_state( $id ) {

		$states = (array) get_option( self::STATE, array() );
		$id     = (int) $id;

		return wp_parse_args(
			isset( $states[ $id ] ) ? (array) $states[ $id ] : array(),
			array(
				'status'      => 'unknown',
				'code'        => 0,
				'message'     => '',
				'checked_at' => 0,
				'changed_at' => 0,
			)
		);

	}

	/**
	 * Store the result of a check for a client.
	 *
	 * @since    1.0.0
	 * @param    int      $id       The client ID.
	 * @param    array    $state    The state to store.
	 */
	public static function set_state( $id, $state ) {

		$states              = (array) get_option( self::STATE, array() );
		$states[ (int) $id ] = $state;

		update_option( self::STATE, $states, false );

	}

	/**
	 * Forget the stored state of a client.
	 *
	 * @since    1.0.0
	 * @param    int    $id    The client ID.
	 */
	public static function delete_state( $id ) {

		$states = (array) get_option( self::STATE, array() );

		unset( $states[ (int) $id ] );

		update_option( self::STATE, $states, false );

	}

}