<?php

/**
 * Stores and sanitizes the plugin-wide settings.
 *
 * @link       https://patlew.pl
 * @since      1.0.0
 *
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 */

/**
 * Stores and sanitizes the plugin-wide settings.
 *
 * These are the settings that are not tied to a single monitored client: the
 * addresses notified about every client, and the values new clients start with.
 *
 * @since      1.0.0
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 * @author     Patryk Lewandowski <kontakt@patlew.pl>
 */
class Wp_Upsite_Checking_Settings {

	/**
	 * The option name holding the plugin-wide settings.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const OPTION = 'wp_upsite_checking_options';

	/**
	 * The shortest interval that can be configured, in minutes.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	const MIN_INTERVAL = 1;

	/**
	 * The longest interval that can be configured, in minutes.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	const MAX_INTERVAL = 1440;

	/**
	 * The settings a fresh install starts with.
	 *
	 * @since     1.0.0
	 * @return    array    The default settings.
	 */
	public static function defaults() {

		return array(
			'recipients' => get_option( 'admin_email' ),
			'interval'   => 5,
			'timeout'    => 10,
		);

	}

	/**
	 * Retrieve the settings, with any missing key filled in from the defaults.
	 *
	 * @since     1.0.0
	 * @param     string    $key    Optional. A single setting to return.
	 * @return    mixed             The settings array, or one value when $key is given.
	 */
	public static function get( $key = null ) {

		$options = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );

		if ( null === $key ) {
			return $options;
		}

		return isset( $options[ $key ] ) ? $options[ $key ] : null;

	}

	/**
	 * Retrieve the addresses notified about every client.
	 *
	 * @since     1.0.0
	 * @return    array    Zero or more valid e-mail addresses.
	 */
	public static function get_recipients() {

		return self::parse_recipients( self::get( 'recipients' ) );

	}

	/**
	 * Retrieve the address replies to a notification should go to.
	 *
	 * A notification tells a client about their own site, so a reply belongs with
	 * whoever runs the monitoring rather than with the client: the first of the
	 * globally notified addresses, falling back to the site administrator.
	 *
	 * @since     1.0.0
	 * @return    string    A valid e-mail address, or an empty string when there is none.
	 */
	public static function get_reply_to() {

		$recipients = self::get_recipients();
		$address    = $recipients ? $recipients[0] : get_option( 'admin_email' );

		return is_email( $address ) ? $address : '';

	}

	/**
	 * Split a user-entered recipient list into valid e-mail addresses.
	 *
	 * Commas, semicolons and newlines are all accepted as separators so that the
	 * field is forgiving about how the list is pasted in. Invalid addresses are
	 * dropped rather than stored, which keeps wp_mail() from failing silently.
	 *
	 * @since     1.0.0
	 * @param     string    $raw    The raw value of a recipients field.
	 * @return    array             Zero or more valid, unique e-mail addresses.
	 */
	public static function parse_recipients( $raw ) {

		$parts      = preg_split( '/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
		$recipients = array();

		foreach ( (array) $parts as $part ) {
			$email = sanitize_email( $part );

			if ( is_email( $email ) ) {
				$recipients[] = $email;
			}
		}

		return array_values( array_unique( $recipients ) );

	}

	/**
	 * Sanitize the settings submitted from the settings page.
	 *
	 * @since     1.0.0
	 * @param     array    $input    The raw submitted values.
	 * @return    array              The values safe to store.
	 */
	public static function sanitize( $input ) {

		$defaults = self::defaults();
		$input    = (array) $input;

		$clean = array(
			'recipients' => implode( ', ', self::parse_recipients( isset( $input['recipients'] ) ? $input['recipients'] : '' ) ),
		);

		$interval          = isset( $input['interval'] ) ? absint( $input['interval'] ) : $defaults['interval'];
		$clean['interval'] = min( self::MAX_INTERVAL, max( self::MIN_INTERVAL, $interval ) );

		$timeout          = isset( $input['timeout'] ) ? absint( $input['timeout'] ) : $defaults['timeout'];
		$clean['timeout'] = min( 60, max( 1, $timeout ) );

		return $clean;

	}

}