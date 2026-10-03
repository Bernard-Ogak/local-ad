<?php
/**
 * Capabilities, request verification and path safety helpers.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Security helpers.
 */
class Local_Ads_Security {

	const CAP_MANAGE    = 'manage_local_ads';
	const CAP_EDIT      = 'edit_local_ads';
	const CAP_DELETE    = 'delete_local_ads';
	const CAP_ANALYTICS = 'view_local_ads_analytics';

	/**
	 * Every custom capability.
	 *
	 * @return string[]
	 */
	public static function caps() {
		return array( self::CAP_MANAGE, self::CAP_EDIT, self::CAP_DELETE, self::CAP_ANALYTICS );
	}

	/**
	 * Grants all capabilities to administrators.
	 */
	public static function add_caps() {
		$role = get_role( 'administrator' );
		if ( $role ) {
			foreach ( self::caps() as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Removes the capabilities from every role.
	 */
	public static function remove_caps() {
		$roles = wp_roles();
		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::caps() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Dies unless the current user has a capability.
	 *
	 * @param string $cap Capability.
	 */
	public static function require_cap( $cap ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'local-ads' ), 403 );
		}
	}

	/**
	 * Validates and normalises an uploads subfolder.
	 *
	 * Accepts letters, numbers, dashes and underscores, separated by forward slashes, up to 3 levels.
	 * Rejects traversal, absolute paths, drive letters and empty values.
	 *
	 * @param string $raw Raw folder value.
	 * @return string|false
	 */
	public static function sanitize_folder( $raw ) {
		if ( ! is_string( $raw ) ) {
			return false;
		}
		$raw = trim( $raw );
		if ( '' === $raw || false !== strpos( $raw, '..' ) || false !== strpos( $raw, '\\' ) || false !== strpos( $raw, ':' ) || false !== strpos( $raw, "\0" ) ) {
			return false;
		}
		if ( '/' === $raw[0] || '~' === $raw[0] ) {
			return false;
		}
		$raw   = trim( $raw, '/' );
		$parts = explode( '/', $raw );
		if ( count( $parts ) > 3 ) {
			return false;
		}
		foreach ( $parts as $part ) {
			if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $part ) ) {
				return false;
			}
		}
		return strtolower( implode( '/', $parts ) );
	}

	/**
	 * Checks that a path lies inside a base directory (after resolving symlinks and dots).
	 *
	 * @param string $path Path to check (must exist).
	 * @param string $base Base directory (must exist).
	 * @return bool
	 */
	public static function path_within( $path, $base ) {
		$real_path = realpath( $path );
		$real_base = realpath( $base );
		if ( false === $real_path || false === $real_base ) {
			return false;
		}
		$real_path = wp_normalize_path( $real_path );
		$real_base = trailingslashit( wp_normalize_path( $real_base ) );
		return 0 === strpos( trailingslashit( $real_path ), $real_base ) || trailingslashit( $real_path ) === $real_base;
	}

	/**
	 * Validates a destination URL. Returns the cleaned URL, '' when empty, or false when invalid.
	 *
	 * Relative paths ("/offers/") are allowed and resolved against the site home.
	 *
	 * @param string $raw Raw URL.
	 * @return string|false
	 */
	public static function sanitize_destination( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( '/' === $raw[0] && ( ! isset( $raw[1] ) || '/' !== $raw[1] ) ) {
			$raw = home_url( $raw );
		}
		$url = esc_url_raw( $raw, array( 'http', 'https' ) );
		if ( '' === $url || preg_match( '/\s/', $raw ) ) {
			return false;
		}
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true ) || empty( $host ) || ! preg_match( '/^[\p{L}\p{N}.-]+$/u', $host ) ) {
			return false;
		}
		return $url;
	}

	/**
	 * Short signature that ties tracking requests to ad data served by this site today.
	 *
	 * Not a secret per visitor (pages may be cached); it only stops blind requests for arbitrary IDs.
	 *
	 * @param int    $ad_id Ad ID.
	 * @param string $day   Y-m-d day in site time.
	 * @return string
	 */
	public static function track_signature( $ad_id, $day ) {
		return substr( hash_hmac( 'sha256', 'local-ads|' . (int) $ad_id . '|' . $day, wp_salt( 'nonce' ) ), 0, 16 );
	}

	/**
	 * Verifies a tracking signature for today or yesterday (site time).
	 *
	 * @param int    $ad_id Ad ID.
	 * @param string $sig   Signature.
	 * @return bool
	 */
	public static function verify_track_signature( $ad_id, $sig ) {
		$sig = (string) $sig;
		if ( '' === $sig ) {
			return false;
		}
		$now = time();
		foreach ( array( $now, $now - DAY_IN_SECONDS ) as $ts ) {
			if ( hash_equals( self::track_signature( $ad_id, wp_date( 'Y-m-d', $ts ) ), $sig ) ) {
				return true;
			}
		}
		return false;
	}
}
