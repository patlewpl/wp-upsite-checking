<?php

/**
 * The add/edit form for one monitored client.
 *
 * @link       https://patlew.pl
 * @since      1.0.0
 *
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/admin/partials
 *
 * @var array $client    The client being edited, or the defaults for a new one.
 * @var array $errors    Messages from a submission that was rejected.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$is_new = empty( $client['id'] );
?>

<div class="wrap wp-upsite-checking">

	<h1>
		<?php
		echo $is_new
			? esc_html__( 'Add Client', 'wp-upsite-checking' )
			: esc_html__( 'Edit Client', 'wp-upsite-checking' );
		?>
	</h1>

	<?php if ( $errors ) : ?>
		<div class="notice notice-error">
			<?php foreach ( $errors as $error ) : ?>
				<p><?php echo esc_html( $error ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

		<input type="hidden" name="action" value="wp_upsite_checking_save_client" />
		<input type="hidden" name="client[id]" value="<?php echo esc_attr( $client['id'] ); ?>" />
		<?php wp_nonce_field( 'wp_upsite_checking_save_client' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="wp-upsite-checking-name"><?php esc_html_e( 'Client name', 'wp-upsite-checking' ); ?></label>
					</th>
					<td>
						<input type="text" class="regular-text" id="wp-upsite-checking-name" name="client[name]"
							value="<?php echo esc_attr( $client['name'] ); ?>" />
						<p class="description"><?php esc_html_e( 'A label for your own use. Left empty, the domain is used.', 'wp-upsite-checking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp-upsite-checking-url"><?php esc_html_e( 'Address to check', 'wp-upsite-checking' ); ?></label>
					</th>
					<td>
						<input type="url" class="regular-text code" id="wp-upsite-checking-url" name="client[url]"
							value="<?php echo esc_attr( $client['url'] ); ?>" placeholder="https://example.com/" required />
						<p class="description"><?php esc_html_e( 'The full URL to request. Anything answering 200-399 counts as up; a connection failure or any other status counts as down.', 'wp-upsite-checking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp-upsite-checking-recipients"><?php esc_html_e( 'Notify', 'wp-upsite-checking' ); ?></label>
					</th>
					<td>
						<textarea class="large-text code" rows="3" id="wp-upsite-checking-recipients" name="client[recipients]"><?php echo esc_textarea( $client['recipients'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'This client\'s own addresses, separated by commas or newlines.', 'wp-upsite-checking' ); ?>
							<?php
							$global = Wp_Upsite_Checking_Settings::get_recipients();

							if ( $global ) {
								printf(
									/* translators: %s: the globally notified e-mail addresses. */
									esc_html__( 'Notifications also go to %s.', 'wp-upsite-checking' ),
									esc_html( implode( ', ', $global ) )
								);
							}
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp-upsite-checking-interval"><?php esc_html_e( 'Check every', 'wp-upsite-checking' ); ?></label>
					</th>
					<td>
						<input type="number" class="small-text" id="wp-upsite-checking-interval" name="client[interval]"
							value="<?php echo esc_attr( $client['interval'] ); ?>"
							min="<?php echo esc_attr( Wp_Upsite_Checking_Settings::MIN_INTERVAL ); ?>"
							max="<?php echo esc_attr( Wp_Upsite_Checking_Settings::MAX_INTERVAL ); ?>" step="1" />
						<?php esc_html_e( 'minutes', 'wp-upsite-checking' ); ?>
						<p class="description"><?php esc_html_e( 'This client\'s own schedule, independent of the others.', 'wp-upsite-checking' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp-upsite-checking-timeout"><?php esc_html_e( 'Request timeout', 'wp-upsite-checking' ); ?></label>
					</th>
					<td>
						<input type="number" class="small-text" id="wp-upsite-checking-timeout" name="client[timeout]"
							value="<?php echo esc_attr( $client['timeout'] ); ?>" min="1" max="60" step="1" />
						<?php esc_html_e( 'seconds', 'wp-upsite-checking' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wp-upsite-checking-headers"><?php esc_html_e( 'Extra request headers', 'wp-upsite-checking' ); ?></label>
					</th>
					<td>
						<textarea class="large-text code" rows="3" id="wp-upsite-checking-headers" name="client[headers]"
							placeholder="X-Monitor-Token: ..."><?php echo esc_textarea( $client['headers'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Optional, one "Name: value" per line. Use this when a firewall or CDN in front of the site blocks this server: add a rule there that lets requests carrying your header through, and send that header from here.', 'wp-upsite-checking' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Monitoring', 'wp-upsite-checking' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="client[enabled]" value="1" <?php checked( 1, $client['enabled'] ); ?> />
							<?php esc_html_e( 'Run the scheduled check for this client', 'wp-upsite-checking' ); ?>
						</label>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( $is_new ? __( 'Add Client', 'wp-upsite-checking' ) : __( 'Save Client', 'wp-upsite-checking' ) ); ?>

		<a href="<?php echo esc_url( $this->page_url() ); ?>" class="button-link">
			<?php esc_html_e( 'Back to the list', 'wp-upsite-checking' ); ?>
		</a>

	</form>

</div>
