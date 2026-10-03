<?php
/**
 * Updates from GitHub Releases.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Offers new GitHub releases through the normal WordPress update screens.
 *
 * The plugin header's "Update URI" stops WordPress from asking WordPress.org about this plugin and
 * makes core call the update_plugins_github.com filter instead. Only the release version and the
 * attached local-ads.zip are read; no site data is sent to GitHub.
 */
class Local_Ads_Updater {

	const REPO  = 'Bernard-Ogak/local-ad';
	const ASSET = 'local-ads.zip';
	const CACHE = 'local_ads_github_release';

	const CACHE_SECONDS       = 43200;
	const RETRY_AFTER_FAILURE = 3600;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 20, 3 );
	}

	/**
	 * Tells WordPress about the latest release (core compares the versions).
	 *
	 * @param array|false $update      Update data from earlier callbacks.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( LOCAL_ADS_BASENAME !== $plugin_file ) {
			return $update;
		}
		$release = self::latest();
		if ( ! $release ) {
			return $update;
		}
		return array(
			'id'      => ! empty( $plugin_data['UpdateURI'] ) ? $plugin_data['UpdateURI'] : 'https://github.com/' . self::REPO,
			'slug'    => dirname( $plugin_file ),
			'version' => $release['version'],
			'url'     => $release['url'],
			'package' => $release['package'],
		);
	}

	/**
	 * Fills the "View details" window for this plugin.
	 *
	 * @param false|object|array $result Result from earlier callbacks.
	 * @param string             $action plugins_api action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || dirname( LOCAL_ADS_BASENAME ) !== $args->slug ) {
			return $result;
		}
		$release = self::latest();
		if ( ! $release ) {
			return $result;
		}
		return (object) array(
			'name'          => 'Local Ad',
			'slug'          => $args->slug,
			'version'       => $release['version'],
			'author'        => '<a href="https://www.creativebay.co.ke">Bernard Ogak (Creative Bay)</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'download_link' => $release['package'],
			'last_updated'  => $release['published'],
			'sections'      => array(
				'changelog' => '' !== $release['notes'] ? wpautop( esc_html( $release['notes'] ) ) : '',
			),
		);
	}

	/**
	 * The latest published release with the plugin ZIP attached, cached for 12 hours.
	 *
	 * @return array|null { version, url, package, notes, published }
	 */
	public static function latest() {
		/**
		 * Whether to check GitHub for new releases.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'local_ads_github_updates', true ) ) {
			return null;
		}

		// "Check again" on Dashboard → Updates skips the cache.
		$force  = isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cached = $force ? false : get_site_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return isset( $cached['version'] ) ? $cached : null;
		}

		$release = self::fetch();
		set_site_transient( self::CACHE, $release ? $release : array( 'failed' => time() ), $release ? self::CACHE_SECONDS : self::RETRY_AFTER_FAILURE );
		return $release;
	}

	/**
	 * Reads the latest release from the GitHub API.
	 *
	 * @return array|null
	 */
	private static function fetch() {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'LocalAds/' . LOCAL_ADS_VERSION,
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		$version = ltrim( isset( $data['tag_name'] ) ? (string) $data['tag_name'] : '', 'vV' );
		if ( ! preg_match( '/^\d+(\.\d+){1,3}$/', $version ) ) {
			return null;
		}
		$assets = isset( $data['assets'] ) && is_array( $data['assets'] ) ? $data['assets'] : array();
		foreach ( $assets as $asset ) {
			if ( is_array( $asset ) && isset( $asset['name'], $asset['browser_download_url'] ) && self::ASSET === $asset['name'] && 0 === strpos( (string) $asset['browser_download_url'], 'https://github.com/' ) ) {
				return array(
					'version'   => $version,
					'url'       => esc_url_raw( isset( $data['html_url'] ) ? (string) $data['html_url'] : 'https://github.com/' . self::REPO . '/releases' ),
					'package'   => esc_url_raw( (string) $asset['browser_download_url'] ),
					'notes'     => isset( $data['body'] ) ? (string) $data['body'] : '',
					'published' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
				);
			}
		}
		return null;
	}
}
