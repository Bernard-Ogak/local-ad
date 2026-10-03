<?php
/**
 * Campaign model.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD for campaigns. A campaign groups advertisements, can be paused as a whole
 * and can bound the dates on which its advertisements run.
 */
class Local_Ads_Campaigns {

	/**
	 * Campaign statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'active' => __( 'Active', 'local-ads' ),
			'paused' => __( 'Paused', 'local-ads' ),
			'draft'  => __( 'Draft', 'local-ads' ),
		);
	}

	/**
	 * Fetches one campaign.
	 *
	 * @param int $id Campaign ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}
		$table = Local_Ads_DB::table( 'campaigns' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Lists campaigns with ad counts and lifetime totals.
	 *
	 * @return object[]
	 */
	public static function all() {
		global $wpdb;
		$camps = Local_Ads_DB::table( 'campaigns' );
		$ads   = Local_Ads_DB::table( 'ads' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT c.*, COUNT(a.id) AS ad_count, COALESCE(SUM(a.impressions),0) AS impressions, COALESCE(SUM(a.clicks),0) AS clicks FROM {$camps} c LEFT JOIN {$ads} a ON a.campaign_id = c.id GROUP BY c.id ORDER BY c.name ASC" );
	}

	/**
	 * Map of id => name for selects.
	 *
	 * @return array
	 */
	public static function options() {
		global $wpdb;
		$table = Local_Ads_DB::table( 'campaigns' );
		$rows  = $wpdb->get_results( "SELECT id, name FROM {$table} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->id ] = $row->name;
		}
		return $out;
	}

	/**
	 * Inserts or updates a campaign.
	 *
	 * @param array $data Sanitized data: name, description, status, start_gmt, end_gmt.
	 * @param int   $id   Existing ID or 0.
	 * @return int Campaign ID.
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;
		$table = Local_Ads_DB::table( 'campaigns' );
		$row   = array(
			'name'        => $data['name'],
			'description' => $data['description'],
			'status'      => $data['status'],
			'start_gmt'   => $data['start_gmt'],
			'end_gmt'     => $data['end_gmt'],
			'updated_at'  => Local_Ads_DB::now_gmt(),
		);
		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) );
		} else {
			$row['created_at'] = Local_Ads_DB::now_gmt();
			$wpdb->insert( $table, $row );
			$id = (int) $wpdb->insert_id;
		}
		Local_Ads_Ads::refresh_live_flag();
		return (int) $id;
	}

	/**
	 * Sets campaign status.
	 *
	 * @param int    $id     Campaign ID.
	 * @param string $status Status.
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;
		if ( ! array_key_exists( $status, self::statuses() ) ) {
			return;
		}
		$wpdb->update(
			Local_Ads_DB::table( 'campaigns' ),
			array(
				'status'     => $status,
				'updated_at' => Local_Ads_DB::now_gmt(),
			),
			array( 'id' => (int) $id )
		);
		Local_Ads_Ads::refresh_live_flag();
	}

	/**
	 * Deletes a campaign. Its advertisements are kept and detached; analytics history is kept.
	 *
	 * @param int $id Campaign ID.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		$wpdb->update( Local_Ads_DB::table( 'ads' ), array( 'campaign_id' => 0 ), array( 'campaign_id' => $id ) );
		$wpdb->delete( Local_Ads_DB::table( 'campaigns' ), array( 'id' => $id ) );
		Local_Ads_Ads::refresh_live_flag();
	}

	/**
	 * Whether a campaign currently allows its ads to run.
	 *
	 * @param object|null $campaign Campaign row.
	 * @param int         $now      Unix timestamp.
	 * @return bool
	 */
	public static function is_running( $campaign, $now ) {
		if ( ! $campaign ) {
			return true;
		}
		if ( 'active' !== $campaign->status ) {
			return false;
		}
		if ( $campaign->start_gmt && strtotime( $campaign->start_gmt . ' UTC' ) > $now ) {
			return false;
		}
		if ( $campaign->end_gmt && strtotime( $campaign->end_gmt . ' UTC' ) < $now ) {
			return false;
		}
		return true;
	}
}
