<?php

/**
 * Serves plugin updates straight from the GitHub repository.
 *
 * @link       https://patlew.pl
 * @since      1.0.0
 *
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 */

/**
 * Serves plugin updates straight from the GitHub repository.
 *
 * This plugin is not on wordpress.org, so nothing would otherwise tell WordPress
 * that a new version exists. The class watches one branch, compares the version
 * in its plugin header against the installed one, and hands WordPress a download
 * URL — which is enough for the normal "update now" button to work.
 *
 * The version in the branch's plugin header is what drives this. Merging to the
 * branch without bumping that header produces no update.
 *
 * @since      1.0.0
 * @package    Wp_Upsite_Checking
 * @subpackage Wp_Upsite_Checking/includes
 * @author     Patryk Lewandowski <kontakt@patlew.pl>
 */
class Wp_Upsite_Checking_Updater {

	/**
	 * The repository to update from, as "owner/name".
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const REPO = 'patlewpl/wp-upsite-checking';

	/**
	 * The branch that counts as released.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const BRANCH = 'main';

	/**
	 * The transient holding the version last seen on the branch.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const CACHE_KEY = 'wp_upsite_checking_remote_version';

	/**
	 * How long to trust the cached version before asking GitHub again.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * The plugin's directory name, used as its slug.
	 *
	 * @since     1.0.0
	 * @return    string    The slug.
	 */
	public static function slug() {

		return dirname( WP_UPSITE_CHECKING_BASENAME );

	}

	/**
	 * Tell WordPress whether a newer version is waiting on the branch.
	 *
	 * Hooked onto the update transient rather than the newer per-host update
	 * filter, because this runs on every supported WordPress version.
	 *
	 * @since     1.0.0
	 * @param     mixed    $transient    The plugin update transient.
	 * @return    mixed                  The transient, with this plugin's entry filled in.
	 */
	public function check_for_update( $transient ) {

		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		// "Check again" on the updates screen should skip the cache.
		$force  = ! empty( $_GET['force-check'] );
		$remote = $this->get_remote_version( $force );

		if ( ! $remote ) {
			return $transient;
		}

		$item = (object) array(
			'id'          => 'github.com/' . self::REPO,
			'slug'        => self::slug(),
			'plugin'      => WP_UPSITE_CHECKING_BASENAME,
			'new_version' => $remote,
			'url'         => 'https://github.com/' . self::REPO,
			'package'     => $this->package_url(),
			'icons'       => array(),
			'banners'     => array(),
			'tested'      => get_bloginfo( 'version' ),
		);

		if ( version_compare( WP_UPSITE_CHECKING_VERSION, $remote, '<' ) ) {
			$transient->response[ WP_UPSITE_CHECKING_BASENAME ] = $item;
		} else {
			// Listing it here as well keeps the plugin out of the "unknown origin"
			// group on the updates screen and enables auto-update toggles.
			$transient->no_update[ WP_UPSITE_CHECKING_BASENAME ] = $item;
		}

		return $transient;

	}

	/**
	 * The version in the plugin header on the watched branch.
	 *
	 * Read from the raw file rather than the API, which has no rate limit worth
	 * worrying about and needs no token for a public repository. A failed lookup
	 * is cached too, briefly, so an unreachable GitHub cannot slow every admin
	 * page down with repeated requests.
	 *
	 * @since     1.0.0
	 * @param     bool      $force    Whether to ignore the cached value.
	 * @return    string              The version, or an empty string when it could not be read.
	 */
	public function get_remote_version( $force = false ) {

		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );

			if ( false !== $cached ) {
				return (string) $cached;
			}
		}

		$response = wp_remote_get(
			sprintf(
				'https://raw.githubusercontent.com/%s/%s/%s',
				self::REPO,
				self::BRANCH,
				basename( WP_UPSITE_CHECKING_BASENAME )
			),
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'text/plain' ),
			)
		);

		$version = '';

		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			// Only the header block matters, and it is at the top of the file.
			$header = substr( wp_remote_retrieve_body( $response ), 0, 8 * KB_IN_BYTES );

			if ( preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', $header, $matches ) ) {
				$version = trim( $matches[1] );
			}
		}

		set_transient( self::CACHE_KEY, $version, $version ? self::CACHE_TTL : HOUR_IN_SECONDS );

		return $version;

	}

	/**
	 * The URL of the branch archive WordPress should install.
	 *
	 * @since     1.0.0
	 * @return    string    The zip URL.
	 */
	public function package_url() {

		return sprintf( 'https://github.com/%s/archive/refs/heads/%s.zip', self::REPO, self::BRANCH );

	}

	/**
	 * Rename the unpacked archive to the plugin's own directory name.
	 *
	 * GitHub names a branch archive after the repository and branch, so it
	 * unpacks as "wp-upsite-checking-main". Installing that as-is would leave the
	 * plugin at a different path, deactivating it and orphaning the old copy.
	 *
	 * @since     1.0.0
	 * @param     string         $source         Where the unpacked files are.
	 * @param     string         $remote_source  The directory holding $source.
	 * @param     WP_Upgrader    $upgrader       The upgrader running the install.
	 * @param     array          $extra          Arguments describing what is being updated.
	 * @return    string|WP_Error                The corrected source, or an error when it could not be renamed.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader = null, $extra = array() ) {

		global $wp_filesystem;

		if ( empty( $extra['plugin'] ) || WP_UPSITE_CHECKING_BASENAME !== $extra['plugin'] ) {
			return $source;
		}

		$corrected = trailingslashit( $remote_source ) . self::slug();

		if ( trailingslashit( $source ) === trailingslashit( $corrected ) ) {
			return $source;
		}

		if ( ! $wp_filesystem || ! $wp_filesystem->move( $source, $corrected, true ) ) {
			return new WP_Error(
				'wp_upsite_checking_rename_failed',
				__( 'The downloaded update could not be renamed to the plugin directory.', 'wp-upsite-checking' )
			);
		}

		return trailingslashit( $corrected );

	}

	/**
	 * Forget the cached version once an update has been installed.
	 *
	 * Without this the updates screen would go on offering the version that was
	 * just installed until the cache expired.
	 *
	 * @since    1.0.0
	 * @param    WP_Upgrader    $upgrader    The upgrader that ran.
	 * @param    array          $extra       What it acted on.
	 */
	public function flush_cache( $upgrader, $extra ) {

		if ( empty( $extra['type'] ) || 'plugin' !== $extra['type'] ) {
			return;
		}

		delete_transient( self::CACHE_KEY );

	}

}
