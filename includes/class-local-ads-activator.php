<?php
/**
 * Activation and deactivation.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle hooks.
 */
class Local_Ads_Activator {

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		Local_Ads_DB::install();
		Local_Ads_Security::add_caps();
		if ( false === get_option( Local_Ads_Settings::OPTION, false ) ) {
			add_option( Local_Ads_Settings::OPTION, Local_Ads_Settings::defaults() );
		}
		Local_Ads_Settings::flush();
		Local_Ads_Media::folder(); // Creates and protects the upload folder.
		Local_Ads_Ads::sync_statuses();
		Local_Ads_Ads::refresh_live_flag();
		Local_Ads_Cron::schedule();
		update_option( 'local_ads_version', LOCAL_ADS_VERSION, false );
	}

	/**
	 * Plugin deactivation: stop background work. Data is kept.
	 */
	public static function deactivate() {
		Local_Ads_Cron::unschedule();
	}
}
