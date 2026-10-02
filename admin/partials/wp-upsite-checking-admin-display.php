<?php

/**
 * The list of monitored clients.
 *
 * @link       https://patlew.pl
 * @since      1.0.0
 *
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/admin/partials
 *
 * @var array $clients    The client records, keyed by ID.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$status_labels = array(
	'up'      => __( 'Up', 'wp-upsite-checking' ),
	'down'    => __( 'Down', 'wp-upsite-checking' ),
	'unknown' => __( 'Not checked yet', 'wp-upsite-checking' ),
);
?>

<div class="wrap wp-upsite-checking">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Monitored Clients', 'wp-upsite-checking' ); ?></h1>

	<a href="<?php echo esc_url( $this->page_url( array( 'action' => 'new' ) ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add Client', 'wp-upsite-checking' ); ?>
	</a>

	<?php if ( $clients ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wp-upsite-checking-check-all">
			<input type="hidden" name="action" value="wp_upsite_checking_check_all" />
			<?php wp_nonce_field( 'wp_upsite_checking_check_all' ); ?>
			<?php submit_button( __( 'Check all now', 'wp-upsite-checking' ), 'secondary', 'submit', false ); ?>
		</form>
	<?php endif; ?>

	<hr class="wp-header-end" />

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col" class="column-primary"><?php esc_html_e( 'Client', 'wp-upsite-checking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'wp-upsite-checking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Last checked', 'wp-upsite-checking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Next check', 'wp-upsite-checking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Every', 'wp-upsite-checking' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Notifies', 'wp-upsite-checking' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $clients ) : ?>
			<tr>
				<td colspan="6">
					<?php esc_html_e( 'No clients yet. Add the first site you want to watch.', 'wp-upsite-checking' ); ?>
				</td>
			</tr>
		<?php endif; ?>

		<?php foreach ( $clients as $client ) : ?>
			<?php
			$state      = Wp_Upsite_Checking_Clients::get_state( $client['id'] );
			$next_run   = Wp_Upsite_Checking_Monitor::next_run( $client['id'] );
			$recipients = Wp_Upsite_Checking_Clients::get_recipients( $client );
			$edit_url   = $this->page_url( array( 'action' => 'edit', 'client' => $client['id'] ) );
			?>
			<tr>
				<td class="column-primary">
					<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $client['name'] ); ?></a></strong>
					<?php if ( ! $client['enabled'] ) : ?>
						<span class="wp-upsite-checking-badge is-off"><?php esc_html_e( 'Paused', 'wp-upsite-checking' ); ?></span>
					<?php endif; ?>
					<div class="row-actions">
						<span class="view">
							<a href="<?php echo esc_url( $client['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $client['url'] ); ?></a>
						</span>
					</div>
					<div class="row-actions">
						<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'wp-upsite-checking' ); ?></a> | </span>
						<span class="check">
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wp_upsite_checking_check_now&client=' . $client['id'] ), 'wp_upsite_checking_check_now_' . $client['id'] ) ); ?>">
								<?php esc_html_e( 'Check now', 'wp-upsite-checking' ); ?>
							</a> |
						</span>
						<span class="delete">
							<a class="submitdelete"
								href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wp_upsite_checking_delete_client&client=' . $client['id'] ), 'wp_upsite_checking_delete_client_' . $client['id'] ) ); ?>"
								onclick="return confirm( '<?php echo esc_js( __( 'Delete this client and stop monitoring it?', 'wp-upsite-checking' ) ); ?>' );">
								<?php esc_html_e( 'Delete', 'wp-upsite-checking' ); ?>
							</a>
						</span>
					</div>
				</td>
				<td>
					<span class="wp-upsite-checking-badge is-<?php echo esc_attr( $state['status'] ); ?>">
						<?php echo esc_html( $status_labels[ $state['status'] ] ); ?>
					</span>
					<?php if ( 'down' === $state['status'] && ! empty( $state['message'] ) ) : ?>
						<div class="description"><?php echo esc_html( $state['message'] ); ?></div>
					<?php endif; ?>
				</td>
				<td>
					<?php
					echo $state['checked_at']
						? esc_html( wp_date( 'Y-m-d H:i', $state['checked_at'] ) )
						: esc_html__( 'Never', 'wp-upsite-checking' );
					?>
				</td>
				<td>
					<?php
					if ( ! $client['enabled'] ) {
						echo '&mdash;';
					} elseif ( $next_run ) {
						echo esc_html( wp_date( 'Y-m-d H:i', $next_run ) );
					} else {
						esc_html_e( 'Not scheduled', 'wp-upsite-checking' );
					}
					?>
				</td>
				<td>
					<?php
					printf(
						/* translators: %d: number of minutes between checks. */
						esc_html( _n( '%d min', '%d min', $client['interval'], 'wp-upsite-checking' ) ),
						(int) $client['interval']
					);
					?>
				</td>
				<td class="wp-upsite-checking-recipients">
					<?php
					echo $recipients
						? esc_html( implode( ', ', $recipients ) )
						: esc_html__( 'Nobody', 'wp-upsite-checking' );
					?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description wp-upsite-checking-cron-note">
		<?php esc_html_e( 'Checks run through WP-Cron on this site, which fires on incoming traffic. If this site is quiet, or itself goes down, checks are delayed or missed. A real system cron calling wp-cron.php makes the schedule reliable.', 'wp-upsite-checking' ); ?>
	</p>

</div>
