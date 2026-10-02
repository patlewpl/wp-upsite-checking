<?php

/**
 * Performs the uptime checks and sends the notifications.
 *
 * @link       https://patlew.pl
 * @since      1.0.0
 *
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 */

/**
 * Performs the uptime checks and sends the notifications.
 *
 * Owns the WP-Cron events as well. Every client gets its own recurring event,
 * identified by the client ID passed as the event argument, so each client can
 * run on its own interval.
 *
 * @since      1.0.0
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 * @author     Patryk Lewandowski <kontakt@patlew.pl>
 */
class Wp_Upsite_Checking_Monitor {

	/**
	 * The name of the WP-Cron hook that runs a client's check.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const EVENT = 'wp_upsite_checking_check';

	/**
	 * Build the name of the cron schedule for a given interval.
	 *
	 * One schedule per distinct interval, rather than one per client, keeps the
	 * schedule list short when many clients share the same interval.
	 *
	 * @since     1.0.0
	 * @param     int       $minutes    The interval in minutes.
	 * @return    string                The schedule name.
	 */
	public static function schedule_name( $minutes ) {

		return 'wp_upsite_checking_' . (int) $minutes . 'm';

	}

	/**
	 * Register a cron schedule for every interval currently in use.
	 *
	 * WordPress ships hourly, twicedaily and daily only, so each interval a
	 * client uses has to be added to the list before an event can be scheduled
	 * with it.
	 *
	 * @since     1.0.0
	 * @param     array    $schedules    The schedules registered with WordPress.
	 * @return    array                  The schedules including this plugin's own.
	 */
	public function add_cron_schedules( $schedules ) {

		$intervals = array( (int) Wp_Upsite_Checking_Settings::get( 'interval' ) );

		foreach ( Wp_Upsite_Checking_Clients::all() as $client ) {
			$intervals[] = (int) $client['interval'];
		}

		foreach ( array_unique( $intervals ) as $minutes ) {
			if ( $minutes < 1 ) {
				continue;
			}

			$schedules[ self::schedule_name( $minutes ) ] = array(
				'interval' => $minutes * MINUTE_IN_SECONDS,
				/* translators: %d: number of minutes between uptime checks. */
				'display'  => sprintf( _n( 'Every %d minute', 'Every %d minutes', $minutes, 'wp-upsite-checking' ), $minutes ),
			);
		}

		return $schedules;

	}

	/**
	 * Timestamp of a client's next scheduled check.
	 *
	 * @since     1.0.0
	 * @param     int          $id    The client ID.
	 * @return    int|false           The timestamp, or false when the client is not scheduled.
	 */
	public static function next_run( $id ) {

		return wp_next_scheduled( self::EVENT, array( (int) $id ) );

	}

	/**
	 * Schedule a client's recurring check.
	 *
	 * The first run is spread out by client ID so that adding twenty clients does
	 * not produce twenty outbound requests in the same second.
	 *
	 * @since    1.0.0
	 * @param    array    $client    The client record.
	 */
	public static function schedule_client( $client ) {

		$id = (int) $client['id'];

		if ( self::next_run( $id ) ) {
			return;
		}

		$spread = ( $id * 13 ) % max( 1, (int) $client['interval'] * MINUTE_IN_SECONDS );

		wp_schedule_event(
			time() + MINUTE_IN_SECONDS + $spread,
			self::schedule_name( $client['interval'] ),
			self::EVENT,
			array( $id )
		);

	}

	/**
	 * Remove a client's recurring check.
	 *
	 * @since    1.0.0
	 * @param    int    $id    The client ID.
	 */
	public static function unschedule_client( $id ) {

		wp_clear_scheduled_hook( self::EVENT, array( (int) $id ) );

	}

	/**
	 * Re-apply a client's schedule to match its current settings.
	 *
	 * WP-Cron stores the interval alongside the event, so an existing event keeps
	 * running at the old interval until it is cleared and scheduled again.
	 *
	 * @since    1.0.0
	 * @param    array    $client    The client record.
	 */
	public static function reschedule_client( $client ) {

		self::unschedule_client( $client['id'] );

		if ( ! empty( $client['enabled'] ) ) {
			self::schedule_client( $client );
		}

	}

	/**
	 * Bring WP-Cron back in line with the stored clients.
	 *
	 * Only the events that are actually wrong are touched: a missing one is
	 * added and a paused client's leftover one is removed. An event that is
	 * already correct is left alone, so repairing the schedule does not push
	 * back checks that were about to run.
	 *
	 * A client's own interval change is handled by reschedule_client() when the
	 * client is saved, not here.
	 *
	 * @since    1.0.0
	 */
	public static function reschedule_all() {

		foreach ( Wp_Upsite_Checking_Clients::all() as $client ) {
			$scheduled = (bool) self::next_run( $client['id'] );

			if ( ! empty( $client['enabled'] ) && ! $scheduled ) {
				self::schedule_client( $client );
			} elseif ( empty( $client['enabled'] ) && $scheduled ) {
				self::unschedule_client( $client['id'] );
			}
		}

	}

	/**
	 * Remove every client's recurring check.
	 *
	 * @since    1.0.0
	 */
	public static function unschedule_all() {

		foreach ( array_keys( Wp_Upsite_Checking_Clients::all() ) as $id ) {
			self::unschedule_client( $id );
		}

		// wp_clear_scheduled_hook() only matches events whose arguments are identical,
		// so this sweeps up anything left behind by a client record that is already gone.
		wp_unschedule_hook( self::EVENT );

	}

	/**
	 * Run a client's check and notify when the status has changed.
	 *
	 * The e-mail is sent on the transition only — going down, and coming back up
	 * again — so an outage produces one message rather than one every interval.
	 *
	 * @since     1.0.0
	 * @param     int             $client_id    The client to check.
	 * @return    array|WP_Error                The new state, or an error when there is no such client.
	 */
	public function run_check( $client_id ) {

		$client = Wp_Upsite_Checking_Clients::get( $client_id );

		if ( ! $client ) {
			// The client was deleted but an event survived; clean it up.
			self::unschedule_client( $client_id );

			return new WP_Error( 'no_client', __( 'That client no longer exists.', 'wp-upsite-checking' ) );
		}

		$previous = Wp_Upsite_Checking_Clients::get_state( $client['id'] );
		$result   = $this->check_url(
			$client['url'],
			$client['timeout'],
			Wp_Upsite_Checking_Clients::parse_headers( $client['headers'] )
		);

		$now   = time();
		$state = array(
			'status'     => $result['status'],
			'code'       => $result['code'],
			'message'    => $result['message'],
			'checked_at' => $now,
			'changed_at' => $result['status'] === $previous['status'] && $previous['changed_at'] ? (int) $previous['changed_at'] : $now,
		);

		$changed = ( $result['status'] !== $previous['status'] );

		// A first check that finds the site up is the expected case and is not worth an e-mail.
		$worth_notifying = $changed && ! ( 'unknown' === $previous['status'] && 'up' === $result['status'] );

		if ( $worth_notifying ) {
			$this->notify( $result['status'], $client, $state );
		}

		Wp_Upsite_Checking_Clients::set_state( $client['id'], $state );

		return $state;

	}

	/**
	 * Request a URL and decide whether it counts as up.
	 *
	 * A transport-level failure (DNS, refused connection, timeout) and any status
	 * outside the 2xx/3xx range both count as down, so a PHP fatal error served as
	 * a 500 is caught as well as an unreachable host.
	 *
	 * @since     1.0.0
	 * @param     string    $url        The address to request.
	 * @param     int       $timeout    How long to wait for a response, in seconds.
	 * @param     array     $headers    Extra request headers, keyed by name.
	 * @return    array                 The status ('up' or 'down'), HTTP code and a human-readable message.
	 */
	public function check_url( $url, $timeout = 10, $headers = array() ) {

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => (int) $timeout,
				'redirection' => 5,
				'sslverify'   => true,
				'user-agent'  => 'WP Upsite Checking/' . WP_UPSITE_CHECKING_VERSION . '; ' . home_url( '/' ),
				// The client's own headers come last, so they can override these.
				'headers'     => array_merge( array( 'Cache-Control' => 'no-cache' ), (array) $headers ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 'down',
				'code'    => 0,
				'message' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 400 ) {
			return array(
				'status'  => 'up',
				'code'    => $code,
				'message' => '',
			);
		}

		return array(
			'status'  => 'down',
			'code'    => $code,
			/* translators: 1: HTTP status code, 2: HTTP status message. */
			'message' => trim( sprintf( __( 'HTTP %1$d %2$s', 'wp-upsite-checking' ), $code, wp_remote_retrieve_response_message( $response ) ) ),
		);

	}

	/**
	 * Send the notification for a client's status change.
	 *
	 * @since     1.0.0
	 * @param     string    $status    The new status, 'up' or 'down'.
	 * @param     array     $client    The client record.
	 * @param     array     $state     The state the check produced.
	 * @return    bool                 Whether the message was handed to wp_mail() successfully.
	 */
	protected function notify( $status, $client, $state ) {

		$recipients = Wp_Upsite_Checking_Clients::get_recipients( $client );

		if ( empty( $recipients ) ) {
			return false;
		}

		$when = wp_date( 'Y-m-d H:i:s', $state['checked_at'] );

		if ( 'down' === $status ) {
			/* translators: %s: the client name. */
			$subject = sprintf( __( '[%s] Your site is down', 'wp-upsite-checking' ), $client['name'] );
			/* translators: %s: the monitored address. */
			$body = sprintf( __( 'Your site %s is down.', 'wp-upsite-checking' ), $client['url'] ) . "\n\n";

			if ( ! empty( $state['message'] ) ) {
				/* translators: %s: the reason the check failed, e.g. an HTTP status or a connection error. */
				$body .= sprintf( __( 'Reason: %s', 'wp-upsite-checking' ), $state['message'] ) . "\n";
			}
		} else {
			/* translators: %s: the client name. */
			$subject = sprintf( __( '[%s] Your site is back up', 'wp-upsite-checking' ), $client['name'] );
			/* translators: %s: the monitored address. */
			$body = sprintf( __( 'Your site %s is reachable again.', 'wp-upsite-checking' ), $client['url'] ) . "\n\n";
		}

		/* translators: %s: date and time of the check, in the site's timezone. */
		$body .= sprintf( __( 'Checked at: %s', 'wp-upsite-checking' ), $when ) . "\n";
		$body .= __( 'Sent by WP Upsite Checking.', 'wp-upsite-checking' ) . "\n";

		// The From address is whatever the site sends mail as, which a client
		// cannot usefully reply to. Point replies at whoever runs the monitoring.
		$headers  = array();
		$reply_to = Wp_Upsite_Checking_Settings::get_reply_to();

		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		/**
		 * Filter the notification before it is sent.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $message    The recipients, subject, body and headers of the message.
		 * @param string $status     The new status, 'up' or 'down'.
		 * @param array  $client     The client record.
		 * @param array  $state      The state the check produced.
		 */
		$message = apply_filters(
			'wp_upsite_checking_notification',
			array(
				'recipients' => $recipients,
				'subject'    => $subject,
				'body'       => $body,
				'headers'    => $headers,
			),
			$status,
			$client,
			$state
		);

		return (bool) wp_mail( $message['recipients'], $message['subject'], $message['body'], $message['headers'] );

	}

}