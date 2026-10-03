<?php
/**
 * WP-Cron maintenance.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Background maintenance. Frontend eligibility never depends on this running.
 */
class Local_Ads_Cron {

	const HOOK = 'local_ads_maintenance';

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	/**
	 * Adds a 15-minute interval.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function schedules( $schedules ) {
		$schedules['local_ads_quarter_hour'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes (Local Ads)', 'local-ads' ),
		);
		return $schedules;
	}

	/**
	 * Schedules the maintenance event.
	 */
	public static function schedule() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'local_ads_quarter_hour', self::HOOK );
		}
	}

	/**
	 * Removes the maintenance event.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Maintenance task: status transitions, retention cleanup and the live flag.
	 *
	 * @return array Summary.
	 */
	public static function run() {
		$changed = Local_Ads_Ads::sync_statuses();
		$cleaned = Local_Ads_Analytics::cleanup();
		Local_Ads_Ads::refresh_live_flag();
		update_option( 'local_ads_last_maintenance', time(), false );
		return array(
			'statuses' => $changed,
			'stats'    => $cleaned['stats'],
			'events'   => $cleaned['events'],
		);
	}
}
