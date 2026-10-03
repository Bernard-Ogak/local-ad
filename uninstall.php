<?php
/**
 * Uninstall Local Ads.
 *
 * Data is removed only when "Delete all Local Ads data" is enabled in Settings → Advanced.
 * Image files are removed only when "Also delete advertisement image files" is enabled too.
 *
 * @package LocalAds
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'LOCAL_ADS_PATH' ) ) {
	define( 'LOCAL_ADS_PATH', plugin_dir_path( __FILE__ ) );
}

require_once LOCAL_ADS_PATH . 'includes/class-local-ads-db.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-settings.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-security.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-media.php';

/**
 * Uninstalls for the current site.
 */
function local_ads_uninstall_site() {
	wp_clear_scheduled_hook( 'local_ads_maintenance' );

	$settings = get_option( 'local_ads_settings', array() );
	if ( empty( $settings['delete_data'] ) ) {
		return;
	}

	if ( ! empty( $settings['delete_images'] ) ) {
		Local_Ads_Media::delete_all_files();
		// Remove the protection files and the folder itself when it is empty.
		$uploads = wp_upload_dir( null, false );
		$folder  = Local_Ads_Security::sanitize_folder( isset( $settings['upload_folder'] ) ? $settings['upload_folder'] : 'local-ads' );
		if ( $folder && empty( $uploads['error'] ) ) {
			$path = trailingslashit( $uploads['basedir'] ) . $folder;
			if ( is_dir( $path ) && Local_Ads_Security::path_within( $path, $uploads['basedir'] ) ) {
				foreach ( array( 'index.php', '.htaccess' ) as $file ) {
					if ( file_exists( $path . '/' . $file ) ) {
						wp_delete_file( $path . '/' . $file );
					}
				}
				$left = scandir( $path );
				if ( is_array( $left ) && count( array_diff( $left, array( '.', '..' ) ) ) === 0 ) {
					rmdir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				}
			}
		}
	}

	Local_Ads_DB::drop_tables();
	Local_Ads_Security::remove_caps();

	foreach ( array( 'local_ads_settings', 'local_ads_db_version', 'local_ads_version', 'local_ads_live', 'local_ads_last_maintenance' ) as $option ) {
		delete_option( $option );
	}

	global $wpdb;
	// Per-user notice and form transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_local\\_ads\\_%' OR option_name LIKE '\\_transient\\_timeout\\_local\\_ads\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

if ( is_multisite() ) {
	$local_ads_sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $local_ads_sites as $local_ads_site ) {
		switch_to_blog( $local_ads_site );
		local_ads_uninstall_site();
		restore_current_blog();
	}
} else {
	local_ads_uninstall_site();
}
