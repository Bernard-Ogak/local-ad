<?php
/**
 * Impression and click tracking, reporting queries and CSV export.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Analytics.
 */
class Local_Ads_Analytics {

	/**
	 * Registers tracking endpoints.
	 */
	public static function init() {
		add_action( 'wp_ajax_local_ads_track', array( __CLASS__, 'ajax_track' ) );
		add_action( 'wp_ajax_nopriv_local_ads_track', array( __CLASS__, 'ajax_track' ) );
	}

	/**
	 * CTR as a percentage rounded to two decimals; 0 when there are no impressions.
	 *
	 * @param int $clicks      Clicks.
	 * @param int $impressions Impressions.
	 * @return float
	 */
	public static function ctr( $clicks, $impressions ) {
		$impressions = (int) $impressions;
		if ( $impressions <= 0 ) {
			return 0.0;
		}
		return round( ( (int) $clicks / $impressions ) * 100, 2 );
	}

	/**
	 * Formats a CTR for display.
	 *
	 * @param int $clicks      Clicks.
	 * @param int $impressions Impressions.
	 * @return string
	 */
	public static function ctr_label( $clicks, $impressions ) {
		return number_format_i18n( self::ctr( $clicks, $impressions ), 2 ) . '%';
	}

	/**
	 * Public tracking endpoint. Sent with navigator.sendBeacon(), so it answers with 204.
	 *
	 * No nonce is used on purpose: pages may be served from a page cache where nonces
	 * expire. Requests are instead validated against the ad's live state and a daily
	 * signature delivered with the ad data.
	 */
	public static function ajax_track() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$ad_id = isset( $_POST['ad'] ) ? absint( $_POST['ad'] ) : 0;
		$type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$sig   = isset( $_POST['sig'] ) ? sanitize_text_field( wp_unslash( $_POST['sig'] ) ) : '';
		// phpcs:enable
		nocache_headers();

		$ok = false;
		if ( $ad_id && in_array( $type, array( 'impression', 'click' ), true ) && Local_Ads_Security::verify_track_signature( $ad_id, $sig ) ) {
			$ok = self::record_if_allowed( $ad_id, $type );
		}
		status_header( $ok ? 204 : 202 );
		exit;
	}

	/**
	 * Applies tracking rules then records the event.
	 *
	 * @param int    $ad_id Ad ID.
	 * @param string $type  impression|click.
	 * @return bool
	 */
	public static function record_if_allowed( $ad_id, $type ) {
		if ( ! Local_Ads_Settings::get( 'analytics_enabled' ) || ! Local_Ads_Settings::get( 'enabled' ) ) {
			return false;
		}
		if ( Local_Ads_Settings::get( 'exclude_managers' ) && is_user_logged_in() && current_user_can( Local_Ads_Security::CAP_EDIT ) ) {
			return false;
		}
		$ad = Local_Ads_Ads::get( $ad_id );
		if ( ! $ad ) {
			return false;
		}
		$now = time();
		// Impressions only for ads that are live right now. Clicks get a grace period so a
		// visitor who clicks a popup that was opened just before the end time is still counted.
		$live = Local_Ads_Ads::is_live( $ad, $now ) || ( 'click' === $type && Local_Ads_Ads::is_live( $ad, $now - 30 * MINUTE_IN_SECONDS ) );
		if ( ! $live ) {
			return false;
		}
		return self::record( $ad, $type, $now );
	}

	/**
	 * Records an event atomically: daily aggregate upsert, lifetime counter and event row.
	 *
	 * @param object $ad   Hydrated ad.
	 * @param string $type impression|click.
	 * @param int    $ts   Event timestamp.
	 * @return bool
	 */
	public static function record( $ad, $type, $ts ) {
		global $wpdb;
		$column = 'click' === $type ? 'clicks' : 'impressions';
		$date   = wp_date( 'Y-m-d', $ts );
		$now    = gmdate( 'Y-m-d H:i:s', $ts );
		$stats  = Local_Ads_DB::table( 'daily_stats' );
		$ads    = Local_Ads_DB::table( 'ads' );
		$imp    = 'impressions' === $column ? 1 : 0;
		$clk    = 'clicks' === $column ? 1 : 0;

		// A single INSERT ... ON DUPLICATE KEY UPDATE is atomic in MySQL, so concurrent
		// requests can never lose increments.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$done = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$stats} (ad_id, campaign_id, stat_date, impressions, clicks, created_at, updated_at) VALUES (%d, %d, %s, %d, %d, %s, %s) ON DUPLICATE KEY UPDATE {$column} = {$column} + 1, updated_at = VALUES(updated_at)",
				$ad->id,
				(int) $ad->campaign_id,
				$date,
				$imp,
				$clk,
				$now,
				$now
			)
		);
		if ( false === $done ) {
			return false;
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$ads} SET {$column} = {$column} + 1 WHERE id = %d", $ad->id ) );
		// phpcs:enable
		$wpdb->insert(
			Local_Ads_DB::table( 'events' ),
			array(
				'ad_id'       => $ad->id,
				'campaign_id' => (int) $ad->campaign_id,
				'event_type'  => $type,
				'created_at'  => $now,
			),
			array( '%d', '%d', '%s', '%s' )
		);
		return true;
	}

	/**
	 * Resolves a preset or custom range to Y-m-d bounds in site time.
	 *
	 * @param string $range today|yesterday|7d|30d|this_month|last_month|custom|all.
	 * @param string $from  Custom start Y-m-d.
	 * @param string $to    Custom end Y-m-d.
	 * @return array { from, to, label }
	 */
	public static function range( $range, $from = '', $to = '' ) {
		$tz    = wp_timezone();
		$today = new DateTimeImmutable( 'now', $tz );
		$fmt   = 'Y-m-d';
		switch ( $range ) {
			case 'today':
				return array( 'from' => $today->format( $fmt ), 'to' => $today->format( $fmt ), 'label' => __( 'Today', 'local-ads' ) );
			case 'yesterday':
				$y = $today->modify( '-1 day' );
				return array( 'from' => $y->format( $fmt ), 'to' => $y->format( $fmt ), 'label' => __( 'Yesterday', 'local-ads' ) );
			case '7d':
				return array( 'from' => $today->modify( '-6 days' )->format( $fmt ), 'to' => $today->format( $fmt ), 'label' => __( 'Last 7 days', 'local-ads' ) );
			case 'this_month':
				return array( 'from' => $today->format( 'Y-m-01' ), 'to' => $today->format( $fmt ), 'label' => __( 'This month', 'local-ads' ) );
			case 'last_month':
				$lm = $today->modify( 'first day of last month' );
				return array( 'from' => $lm->format( 'Y-m-01' ), 'to' => $lm->format( 'Y-m-t' ), 'label' => __( 'Last month', 'local-ads' ) );
			case 'custom':
				$valid = function ( $d ) {
					return is_string( $d ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && checkdate( (int) substr( $d, 5, 2 ), (int) substr( $d, 8, 2 ), (int) substr( $d, 0, 4 ) );
				};
				if ( $valid( $from ) && $valid( $to ) ) {
					if ( $from > $to ) {
						list( $from, $to ) = array( $to, $from );
					}
					return array( 'from' => $from, 'to' => $to, 'label' => __( 'Custom range', 'local-ads' ) );
				}
				// Fall through to 30 days when the custom range is invalid.
			case '30d':
			default:
				return array( 'from' => $today->modify( '-29 days' )->format( $fmt ), 'to' => $today->format( $fmt ), 'label' => __( 'Last 30 days', 'local-ads' ) );
		}
	}

	/**
	 * Builds a WHERE clause for stats queries.
	 *
	 * @param string $from        Y-m-d or ''.
	 * @param string $to          Y-m-d or ''.
	 * @param int    $ad_id       Ad filter.
	 * @param int    $campaign_id Campaign filter.
	 * @return string Prepared SQL fragment.
	 */
	private static function where( $from, $to, $ad_id, $campaign_id ) {
		global $wpdb;
		$parts = array( '1=1' );
		if ( $from ) {
			$parts[] = $wpdb->prepare( 's.stat_date >= %s', $from );
		}
		if ( $to ) {
			$parts[] = $wpdb->prepare( 's.stat_date <= %s', $to );
		}
		if ( $ad_id ) {
			$parts[] = $wpdb->prepare( 's.ad_id = %d', $ad_id );
		}
		if ( $campaign_id ) {
			$parts[] = $wpdb->prepare( 's.campaign_id = %d', $campaign_id );
		}
		return implode( ' AND ', $parts );
	}

	/**
	 * Totals over a range.
	 *
	 * @param string $from        Y-m-d.
	 * @param string $to          Y-m-d.
	 * @param int    $ad_id       Ad filter.
	 * @param int    $campaign_id Campaign filter.
	 * @return array { impressions, clicks, ctr }
	 */
	public static function totals( $from, $to, $ad_id = 0, $campaign_id = 0 ) {
		global $wpdb;
		$stats = Local_Ads_DB::table( 'daily_stats' );
		$where = self::where( $from, $to, $ad_id, $campaign_id );
		$row   = $wpdb->get_row( "SELECT COALESCE(SUM(s.impressions),0) AS impressions, COALESCE(SUM(s.clicks),0) AS clicks FROM {$stats} s WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$imp   = $row ? (int) $row->impressions : 0;
		$clk   = $row ? (int) $row->clicks : 0;
		return array(
			'impressions' => $imp,
			'clicks'      => $clk,
			'ctr'         => self::ctr( $clk, $imp ),
		);
	}

	/**
	 * Lifetime totals from the ad counters (these survive analytics retention cleanup).
	 *
	 * @param int $ad_id       Ad filter.
	 * @param int $campaign_id Campaign filter.
	 * @return array
	 */
	public static function lifetime( $ad_id = 0, $campaign_id = 0 ) {
		global $wpdb;
		$ads   = Local_Ads_DB::table( 'ads' );
		$where = '1=1';
		if ( $ad_id ) {
			$where = $wpdb->prepare( 'id = %d', $ad_id );
		} elseif ( $campaign_id ) {
			$where = $wpdb->prepare( 'campaign_id = %d', $campaign_id );
		}
		$row = $wpdb->get_row( "SELECT COALESCE(SUM(impressions),0) AS impressions, COALESCE(SUM(clicks),0) AS clicks FROM {$ads} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$imp = $row ? (int) $row->impressions : 0;
		$clk = $row ? (int) $row->clicks : 0;
		return array(
			'impressions' => $imp,
			'clicks'      => $clk,
			'ctr'         => self::ctr( $clk, $imp ),
		);
	}

	/**
	 * Daily rows over a range, with zero rows filled in for days without data.
	 *
	 * @param string $from        Y-m-d.
	 * @param string $to          Y-m-d.
	 * @param int    $ad_id       Ad filter.
	 * @param int    $campaign_id Campaign filter.
	 * @return array[] Ordered ascending: date, impressions, clicks, ctr.
	 */
	public static function daily( $from, $to, $ad_id = 0, $campaign_id = 0 ) {
		global $wpdb;
		$stats = Local_Ads_DB::table( 'daily_stats' );
		$where = self::where( $from, $to, $ad_id, $campaign_id );
		$rows  = $wpdb->get_results( "SELECT s.stat_date, SUM(s.impressions) AS impressions, SUM(s.clicks) AS clicks FROM {$stats} s WHERE {$where} GROUP BY s.stat_date ORDER BY s.stat_date ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$map   = array();
		foreach ( (array) $rows as $row ) {
			$map[ $row->stat_date ] = $row;
		}
		$out    = array();
		$cursor = new DateTimeImmutable( $from, wp_timezone() );
		$end    = new DateTimeImmutable( $to, wp_timezone() );
		$guard  = 0;
		while ( $cursor <= $end && $guard < 3700 ) {
			$key   = $cursor->format( 'Y-m-d' );
			$imp   = isset( $map[ $key ] ) ? (int) $map[ $key ]->impressions : 0;
			$clk   = isset( $map[ $key ] ) ? (int) $map[ $key ]->clicks : 0;
			$out[] = array(
				'date'        => $key,
				'impressions' => $imp,
				'clicks'      => $clk,
				'ctr'         => self::ctr( $clk, $imp ),
			);
			$cursor = $cursor->modify( '+1 day' );
			++$guard;
		}
		return $out;
	}

	/**
	 * Per-ad performance over a range.
	 *
	 * @param string $from        Y-m-d.
	 * @param string $to          Y-m-d.
	 * @param int    $campaign_id Campaign filter.
	 * @param int    $ad_id       Ad filter.
	 * @return array[]
	 */
	public static function by_ad( $from, $to, $campaign_id = 0, $ad_id = 0 ) {
		global $wpdb;
		$stats = Local_Ads_DB::table( 'daily_stats' );
		$ads   = Local_Ads_DB::table( 'ads' );
		$camps = Local_Ads_DB::table( 'campaigns' );
		$where = self::where( $from, $to, $ad_id, $campaign_id );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT s.ad_id, a.name, a.status, c.name AS campaign_name, SUM(s.impressions) AS impressions, SUM(s.clicks) AS clicks FROM {$stats} s LEFT JOIN {$ads} a ON a.id = s.ad_id LEFT JOIN {$camps} c ON c.id = s.campaign_id WHERE {$where} GROUP BY s.ad_id, a.name, a.status, c.name ORDER BY impressions DESC" );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'ad_id'       => (int) $row->ad_id,
				/* translators: %d: advertisement ID. */
				'name'        => null === $row->name ? sprintf( __( 'Deleted advertisement #%d', 'local-ads' ), $row->ad_id ) : $row->name,
				'status'      => (string) $row->status,
				'campaign'    => (string) $row->campaign_name,
				'impressions' => (int) $row->impressions,
				'clicks'      => (int) $row->clicks,
				'ctr'         => self::ctr( $row->clicks, $row->impressions ),
			);
		}
		return $out;
	}

	/**
	 * Per-campaign performance over a range.
	 *
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return array[]
	 */
	public static function by_campaign( $from, $to ) {
		global $wpdb;
		$stats = Local_Ads_DB::table( 'daily_stats' );
		$camps = Local_Ads_DB::table( 'campaigns' );
		$where = self::where( $from, $to, 0, 0 );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT s.campaign_id, c.name, SUM(s.impressions) AS impressions, SUM(s.clicks) AS clicks FROM {$stats} s LEFT JOIN {$camps} c ON c.id = s.campaign_id WHERE {$where} AND s.campaign_id > 0 GROUP BY s.campaign_id, c.name ORDER BY impressions DESC" );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'campaign_id' => (int) $row->campaign_id,
				/* translators: %d: campaign ID. */
				'name'        => null === $row->name ? sprintf( __( 'Deleted campaign #%d', 'local-ads' ), $row->campaign_id ) : $row->name,
				'impressions' => (int) $row->impressions,
				'clicks'      => (int) $row->clicks,
				'ctr'         => self::ctr( $row->clicks, $row->impressions ),
			);
		}
		return $out;
	}

	/**
	 * Most recent tracked events.
	 *
	 * @param int $limit Limit.
	 * @return object[]
	 */
	public static function recent_events( $limit = 10 ) {
		global $wpdb;
		$events = Local_Ads_DB::table( 'events' );
		$ads    = Local_Ads_DB::table( 'ads' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT e.*, a.name FROM {$events} e LEFT JOIN {$ads} a ON a.id = e.ad_id ORDER BY e.id DESC LIMIT %d", $limit ) );
	}

	/**
	 * Deletes daily statistics older than the retention setting and old event rows.
	 *
	 * Advertisement records and lifetime counters are never touched.
	 *
	 * @return array { stats, events } rows deleted.
	 */
	public static function cleanup() {
		global $wpdb;
		$deleted   = array(
			'stats'  => 0,
			'events' => 0,
		);
		$retention = (int) Local_Ads_Settings::get( 'retention_days' );
		if ( $retention > 0 ) {
			$cutoff           = wp_date( 'Y-m-d', time() - $retention * DAY_IN_SECONDS );
			$stats            = Local_Ads_DB::table( 'daily_stats' );
			$deleted['stats'] = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$stats} WHERE stat_date < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$event_days        = max( 1, (int) Local_Ads_Settings::get( 'event_retention_days' ) );
		$events            = Local_Ads_DB::table( 'events' );
		$deleted['events'] = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$events} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - $event_days * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $deleted;
	}

	/**
	 * Clears every statistic and resets lifetime counters.
	 */
	public static function reset_all() {
		global $wpdb;
		$stats  = Local_Ads_DB::table( 'daily_stats' );
		$events = Local_Ads_DB::table( 'events' );
		$ads    = Local_Ads_DB::table( 'ads' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$stats}" );
		$wpdb->query( "TRUNCATE TABLE {$events}" );
		$wpdb->query( "UPDATE {$ads} SET impressions = 0, clicks = 0" );
		// phpcs:enable
	}

	/**
	 * Streams a CSV export: one row per advertisement per day.
	 *
	 * @param string $from        Y-m-d.
	 * @param string $to          Y-m-d.
	 * @param int    $ad_id       Ad filter.
	 * @param int    $campaign_id Campaign filter.
	 */
	public static function export_csv( $from, $to, $ad_id = 0, $campaign_id = 0 ) {
		global $wpdb;
		$stats = Local_Ads_DB::table( 'daily_stats' );
		$ads   = Local_Ads_DB::table( 'ads' );
		$camps = Local_Ads_DB::table( 'campaigns' );
		$where = self::where( $from, $to, $ad_id, $campaign_id );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT s.stat_date, s.ad_id, a.name, c.name AS campaign_name, s.impressions, s.clicks FROM {$stats} s LEFT JOIN {$ads} a ON a.id = s.ad_id LEFT JOIN {$camps} c ON c.id = s.campaign_id WHERE {$where} ORDER BY s.stat_date DESC, a.name ASC" );

		$filename = sanitize_file_name( 'local-ads-analytics-' . $from . '-to-' . $to . '.csv' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'Date', 'Advertisement', 'Campaign', 'Impressions', 'Clicks', 'CTR' ) );
		foreach ( (array) $rows as $row ) {
			/* translators: %d: advertisement ID. */
			$name = null === $row->name ? sprintf( __( 'Deleted advertisement #%d', 'local-ads' ), $row->ad_id ) : $row->name;
			fputcsv(
				$out,
				array(
					$row->stat_date,
					self::csv_safe( $name ),
					self::csv_safe( (string) $row->campaign_name ),
					(int) $row->impressions,
					(int) $row->clicks,
					number_format( self::ctr( $row->clicks, $row->impressions ), 2, '.', '' ),
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Neutralises spreadsheet formula injection.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	public static function csv_safe( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}
}
