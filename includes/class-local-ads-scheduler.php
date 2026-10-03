<?php
/**
 * Schedule helpers: timezone conversion, conflict detection and calendar data.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Scheduling logic.
 */
class Local_Ads_Scheduler {

	/**
	 * Converts a site-local date and time to a GMT MySQL datetime.
	 *
	 * @param string $date Y-m-d (site timezone).
	 * @param string $time H:i (site timezone), defaults to 00:00.
	 * @return string|null|false GMT datetime, null when date empty, false when invalid.
	 */
	public static function local_to_gmt( $date, $time = '' ) {
		$date = trim( (string) $date );
		$time = trim( (string) $time );
		if ( '' === $date ) {
			return null;
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		if ( '' === $time ) {
			$time = '00:00';
		}
		if ( ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $time ) ) {
			return false;
		}
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $date ) );
		if ( ! checkdate( $m, $d, $y ) ) {
			return false;
		}
		try {
			$dt = new DateTime( $date . ' ' . $time, wp_timezone() );
		} catch ( Exception $e ) {
			return false;
		}
		$dt->setTimezone( new DateTimeZone( 'UTC' ) );
		return $dt->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Formats a GMT datetime in the site timezone.
	 *
	 * @param string|null $gmt    GMT datetime.
	 * @param string      $format PHP date format.
	 * @return string
	 */
	public static function gmt_to_local( $gmt, $format ) {
		if ( empty( $gmt ) ) {
			return '';
		}
		return wp_date( $format, strtotime( $gmt . ' UTC' ) );
	}

	/**
	 * Human-readable date for admin tables.
	 *
	 * @param int $ts Timestamp.
	 * @return string
	 */
	public static function format_ts( $ts ) {
		if ( ! $ts ) {
			return '';
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
	}

	/**
	 * Enabled ads (active or scheduled) other than $exclude whose schedule overlaps the window.
	 *
	 * @param int $start_ts Window start (0 = open).
	 * @param int $end_ts   Window end (0 = open).
	 * @param int $exclude  Ad ID to ignore.
	 * @return object[]
	 */
	public static function find_conflicts( $start_ts, $end_ts, $exclude = 0 ) {
		global $wpdb;
		$table = Local_Ads_DB::table( 'ads' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status IN ('active','scheduled') AND id <> %d", (int) $exclude ) );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$ad = Local_Ads_Ads::hydrate( $row );
			if ( self::overlaps( $start_ts, $end_ts, $ad->start_ts, $ad->end_ts ) ) {
				$out[] = $ad;
			}
		}
		return $out;
	}

	/**
	 * Whether two windows overlap. 0 means open-ended.
	 *
	 * @param int $s1 Start 1.
	 * @param int $e1 End 1.
	 * @param int $s2 Start 2.
	 * @param int $e2 End 2.
	 * @return bool
	 */
	public static function overlaps( $s1, $e1, $s2, $e2 ) {
		$s1 = $s1 ? $s1 : PHP_INT_MIN;
		$s2 = $s2 ? $s2 : PHP_INT_MIN;
		$e1 = $e1 ? $e1 : PHP_INT_MAX;
		$e2 = $e2 ? $e2 : PHP_INT_MAX;
		return $s1 <= $e2 && $s2 <= $e1;
	}

	/**
	 * Highest number of ads running at the same moment within a window, counting a new ad
	 * that covers the whole window.
	 *
	 * @param int      $start_ts  Window start (0 = open).
	 * @param int      $end_ts    Window end (0 = open).
	 * @param object[] $conflicts Overlapping ads from find_conflicts().
	 * @return int
	 */
	public static function peak_overlap( $start_ts, $end_ts, array $conflicts ) {
		if ( ! $conflicts ) {
			return 1;
		}
		$win_start = $start_ts ? $start_ts : PHP_INT_MIN;
		$points    = array( $win_start );
		foreach ( $conflicts as $ad ) {
			if ( $ad->start_ts && $ad->start_ts > $win_start ) {
				$points[] = $ad->start_ts;
			}
		}
		$peak = 0;
		foreach ( $points as $t ) {
			$n = 0;
			foreach ( $conflicts as $ad ) {
				$s = $ad->start_ts ? $ad->start_ts : PHP_INT_MIN;
				$e = $ad->end_ts ? $ad->end_ts : PHP_INT_MAX;
				if ( $s <= $t && $t <= $e ) {
					++$n;
				}
			}
			$peak = max( $peak, $n );
		}
		return $peak + 1;
	}

	/**
	 * Checks whether enabling an ad with this schedule is allowed by the concurrency settings.
	 *
	 * @param int $id       Ad ID (0 for new).
	 * @param int $start_ts Start.
	 * @param int $end_ts   End.
	 * @return true|WP_Error
	 */
	public static function check_activation( $id, $start_ts, $end_ts ) {
		$conflicts = self::find_conflicts( $start_ts, $end_ts, $id );
		if ( ! $conflicts ) {
			return true;
		}
		$names = implode( ', ', wp_list_pluck( array_slice( $conflicts, 0, 5 ), 'name' ) );
		if ( ! Local_Ads_Settings::get( 'allow_concurrent' ) ) {
			/* translators: %s: list of advertisement names. */
			return new WP_Error( 'conflict', sprintf( __( 'This advertisement overlaps with another campaign (%s). Concurrent advertisements are disabled, so it cannot be activated for this period.', 'local-ads' ), $names ) );
		}
		$max = (int) Local_Ads_Settings::get( 'max_concurrent' );
		if ( $max > 0 ) {
			$peak = self::peak_overlap( $start_ts, $end_ts, $conflicts );
			if ( $peak > $max ) {
				/* translators: 1: maximum number, 2: peak number, 3: list of names. */
				return new WP_Error( 'limit', sprintf( __( 'Activating this advertisement would run %2$d campaigns at the same time, but the maximum is %1$d. Overlapping: %3$s.', 'local-ads' ), $max, $peak, $names ) );
			}
		}
		return true;
	}

	/**
	 * Calendar rows for a month: every ad with a schedule touching the month.
	 *
	 * @param int $year  Year.
	 * @param int $month Month 1-12.
	 * @return array { days, start_ts, end_ts, rows[], per_day[] }
	 */
	public static function month( $year, $month ) {
		global $wpdb;
		$tz     = wp_timezone();
		$first  = new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $year, $month ), $tz );
		$days   = (int) $first->format( 't' );
		$last   = $first->modify( '+' . $days . ' days' )->modify( '-1 second' );
		$m_start = $first->getTimestamp();
		$m_end   = $last->getTimestamp();

		$ads   = Local_Ads_DB::table( 'ads' );
		$camps = Local_Ads_DB::table( 'campaigns' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.*, c.name AS campaign_name FROM {$ads} a LEFT JOIN {$camps} c ON c.id = a.campaign_id WHERE (a.start_gmt IS NULL OR a.start_gmt <= %s) AND (a.end_gmt IS NULL OR a.end_gmt >= %s) ORDER BY a.start_gmt IS NULL DESC, a.start_gmt ASC, a.id ASC", gmdate( 'Y-m-d H:i:s', $m_end ), gmdate( 'Y-m-d H:i:s', $m_start ) ) );

		$out     = array();
		$per_day = array_fill( 1, $days, 0 );
		foreach ( (array) $rows as $row ) {
			$ad = Local_Ads_Ads::hydrate( $row );
			$s  = $ad->start_ts ? max( $ad->start_ts, $m_start ) : $m_start;
			$e  = $ad->end_ts ? min( $ad->end_ts, $m_end ) : $m_end;
			if ( $e < $s ) {
				continue;
			}
			$from = (int) wp_date( 'j', $s );
			$to   = (int) wp_date( 'j', $e );
			$out[] = array(
				'ad'   => $ad,
				'from' => $from,
				'to'   => $to,
			);
			if ( in_array( $ad->status, array( 'active', 'scheduled', 'expired' ), true ) ) {
				for ( $day = $from; $day <= $to; $day++ ) {
					++$per_day[ $day ];
				}
			}
		}
		return array(
			'days'    => $days,
			'first'   => $first,
			'rows'    => $out,
			'per_day' => $per_day,
		);
	}
}
