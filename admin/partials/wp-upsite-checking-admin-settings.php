<?php

/**
 * The plugin-wide settings page.
 *
 * @link       https://patlew.pl
 * @since      1.0.0
 *
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<div class="wrap wp-upsite-checking">

	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="options.php">
		<?php
		settings_fields( $this->plugin_name . '-settings' );
		do_settings_sections( $this->plugin_name . '-settings' );
		submit_button();
		?>
	</form>

	<h2><?php esc_html_e( 'Making the schedule reliable', 'wp-upsite-checking' ); ?></h2>

	<p class="description">
		<?php esc_html_e( 'WP-Cron only fires when this site receives a request, so on a quiet site checks run later than their interval suggests. To run them on time, disable WP-Cron and call wp-cron.php from a real cron job instead:', 'wp-upsite-checking' ); ?>
	</p>

	<p><code>define( 'DISABLE_WP_CRON', true );</code> <?php esc_html_e( '— in wp-config.php', 'wp-upsite-checking' ); ?></p>
	<p><code>* * * * * curl -s <?php echo esc_html( site_url( 'wp-cron.php?doing_wp_cron' ) ); ?> &gt;/dev/null</code></p>

	<p class="description">
		<?php esc_html_e( 'Running that cron job from a different server than this one also means an outage of this site does not stop the monitoring.', 'wp-upsite-checking' ); ?>
	</p>

</div>
