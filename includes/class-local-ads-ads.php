<?php
/**
 * Advertisement model: storage, status lifecycle, eligibility and targeting.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Advertisement CRUD and eligibility rules.
 */
class Local_Ads_Ads {

	const LIVE_OPTION = 'local_ads_live';

	/**
	 * Status labels.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'draft'     => __( 'Draft', 'local-ads' ),
			'scheduled' => __( 'Scheduled', 'local-ads' ),
			'active'    => __( 'Active', 'local-ads' ),
			'paused'    => __( 'Paused', 'local-ads' ),
			'expired'   => __( 'Expired', 'local-ads' ),
		);
	}

	/**
	 * Statuses that may display (subject to the schedule).
	 *
	 * @return string[]
	 */
	public static function enabled_statuses() {
		return array( 'active', 'scheduled' );
	}

	/**
	 * Per-ad display options, defaulted from global settings.
	 *
	 * @return array
	 */
	public static function default_display() {
		$s = Local_Ads_Settings::all();
		return array(
			'trigger'           => $s['default_trigger'],
			'trigger_delay'     => (int) $s['default_trigger_delay'],
			'trigger_pixels'    => (int) $s['default_trigger_pixels'],
			'trigger_percent'   => (int) $s['default_trigger_percent'],
			'frequency'         => $s['default_frequency'],
			'frequency_hours'   => (int) $s['default_frequency_hours'],
			'auto_close'        => (int) $s['default_auto_close'],
			'auto_close_secs'   => (int) $s['default_auto_close_secs'],
			'animation'         => $s['default_animation'],
			'shake'             => (int) $s['default_shake'],
			'shake_delay'       => (int) $s['default_shake_delay'],
			'shake_duration'    => (int) $s['default_shake_duration'],
			'shake_intensity'   => $s['default_shake_intensity'],
			'new_tab'           => (int) $s['default_new_tab'],
			'close_after_click' => (int) $s['default_close_after_click'],
			'width'             => (int) $s['default_width'],
			'mobile_width'      => (int) $s['default_mobile_width'],
		);
	}

	/**
	 * Default targeting: whole site, never on checkout, cart or login/account pages.
	 *
	 * @return array
	 */
	public static function default_targeting() {
		return array(
			'mode'        => 'all',
			'home'        => 0,
			'posts'       => 0,
			'pages'       => 0,
			'products'    => 0,
			'page_ids'    => array(),
			'post_ids'    => array(),
			'ex_home'     => 0,
			'ex_checkout' => 1,
			'ex_cart'     => 1,
			'ex_login'    => 1,
			'ex_page_ids' => array(),
			'ex_urls'     => array(),
		);
	}

	/**
	 * Sanitizes per-ad display options.
	 *
	 * @param array $in Raw input.
	 * @return array
	 */
	public static function sanitize_display( $in ) {
		$d   = self::default_display();
		$in  = is_array( $in ) ? $in : array();
		$out = array();

		$choice = function ( $key, $group ) use ( $in, $d ) {
			$value = isset( $in[ $key ] ) ? sanitize_key( $in[ $key ] ) : $d[ $key ];
			return array_key_exists( $value, Local_Ads_Settings::choices( $group ) ) ? $value : $d[ $key ];
		};
		$int = function ( $key, $min, $max ) use ( $in, $d ) {
			$value = isset( $in[ $key ] ) && '' !== $in[ $key ] ? (int) $in[ $key ] : (int) $d[ $key ];
			return min( $max, max( $min, $value ) );
		};

		$out['trigger']           = $choice( 'trigger', 'trigger' );
		$out['trigger_delay']     = $int( 'trigger_delay', 0, 600 );
		$out['trigger_pixels']    = $int( 'trigger_pixels', 1, 100000 );
		$out['trigger_percent']   = $int( 'trigger_percent', 1, 100 );
		$out['frequency']         = $choice( 'frequency', 'frequency' );
		$out['frequency_hours']   = $int( 'frequency_hours', 1, 8760 );
		$out['auto_close']        = empty( $in['auto_close'] ) ? 0 : 1;
		$out['auto_close_secs']   = $int( 'auto_close_secs', 3, 3600 );
		$out['animation']         = $choice( 'animation', 'animation' );
		$out['shake']             = empty( $in['shake'] ) ? 0 : 1;
		$out['shake_delay']       = $int( 'shake_delay', 0, 120 );
		$out['shake_duration']    = $int( 'shake_duration', 1, 10 );
		$out['shake_intensity']   = $choice( 'shake_intensity', 'intensity' );
		$out['new_tab']           = empty( $in['new_tab'] ) ? 0 : 1;
		$out['close_after_click'] = empty( $in['close_after_click'] ) ? 0 : 1;
		$out['width']             = $int( 'width', 200, 2000 );
		$out['mobile_width']      = $int( 'mobile_width', 50, 100 );
		return $out;
	}

	/**
	 * Sanitizes targeting rules.
	 *
	 * @param array $in Raw input.
	 * @return array
	 */
	public static function sanitize_targeting( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$out = array(
			'mode' => ( isset( $in['mode'] ) && 'custom' === $in['mode'] ) ? 'custom' : 'all',
		);
		foreach ( array( 'home', 'posts', 'pages', 'products', 'ex_home', 'ex_checkout', 'ex_cart', 'ex_login' ) as $key ) {
			$out[ $key ] = empty( $in[ $key ] ) ? 0 : 1;
		}
		foreach ( array( 'page_ids', 'post_ids', 'ex_page_ids' ) as $key ) {
			$ids = array();
			if ( isset( $in[ $key ] ) ) {
				$raw = is_array( $in[ $key ] ) ? $in[ $key ] : preg_split( '/[\s,]+/', (string) $in[ $key ] );
				$ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
			}
			$out[ $key ] = $ids;
		}
		// Extra IDs typed by hand.
		foreach ( array( 'page_ids_extra' => 'page_ids', 'post_ids_extra' => 'post_ids' ) as $extra => $key ) {
			if ( ! empty( $in[ $extra ] ) ) {
				$more        = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $in[ $extra ] ) ) );
				$out[ $key ] = array_values( array_unique( array_merge( $out[ $key ], $more ) ) );
			}
		}
		$urls = array();
		if ( ! empty( $in['ex_urls'] ) ) {
			$lines = is_array( $in['ex_urls'] ) ? $in['ex_urls'] : preg_split( '/\r\n|\r|\n/', (string) $in['ex_urls'] );
			foreach ( $lines as $line ) {
				$line = self::normalize_url_rule( $line );
				if ( '' !== $line ) {
					$urls[] = $line;
				}
			}
		}
		$out['ex_urls'] = array_slice( array_values( array_unique( $urls ) ), 0, 100 );
		return $out;
	}

	/**
	 * Normalises a URL exclusion rule to a path (with optional * wildcards).
	 *
	 * @param string $line Rule.
	 * @return string
	 */
	public static function normalize_url_rule( $line ) {
		$line = trim( sanitize_text_field( (string) $line ) );
		if ( '' === $line ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $line ) ) {
			$path  = (string) wp_parse_url( $line, PHP_URL_PATH );
			$query = (string) wp_parse_url( $line, PHP_URL_QUERY );
			$line  = ( '' === $path ? '/' : $path ) . ( '' !== $query ? '?' . $query : '' );
		}
		if ( '/' !== $line[0] && '*' !== $line[0] ) {
			$line = '/' . $line;
		}
		return substr( $line, 0, 255 );
	}

	/**
	 * Decodes JSON columns and adds computed properties.
	 *
	 * @param object|null $row DB row.
	 * @return object|null
	 */
	public static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}
		$display        = json_decode( (string) $row->display, true );
		$targeting      = json_decode( (string) $row->targeting, true );
		$row->display   = wp_parse_args( is_array( $display ) ? $display : array(), self::default_display() );
		$row->targeting = wp_parse_args( is_array( $targeting ) ? $targeting : array(), self::default_targeting() );
		$row->start_ts  = $row->start_gmt ? strtotime( $row->start_gmt . ' UTC' ) : 0;
		$row->end_ts    = $row->end_gmt ? strtotime( $row->end_gmt . ' UTC' ) : 0;
		$row->id        = (int) $row->id;
		$row->priority  = (int) $row->priority;
		$row->weight    = (int) $row->weight;
		$row->media_id  = (int) $row->media_id;
		return $row;
	}

	/**
	 * Fetches one ad.
	 *
	 * @param int $id Ad ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}
		$table = Local_Ads_DB::table( 'ads' );
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Paged, filtered list for the admin table.
	 *
	 * @param array $args search, status, campaign_id, orderby, order, per_page, page.
	 * @return array { items, total }
	 */
	public static function query( array $args ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'search'      => '',
				'status'      => '',
				'campaign_id' => 0,
				'orderby'     => 'created_at',
				'order'       => 'DESC',
				'per_page'    => 20,
				'page'        => 1,
			)
		);
		$ads   = Local_Ads_DB::table( 'ads' );
		$camps = Local_Ads_DB::table( 'campaigns' );
		$where = array( '1=1' );
		$vals  = array();

		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(a.name LIKE %s OR a.advertiser LIKE %s OR a.description LIKE %s)';
			array_push( $vals, $like, $like, $like );
		}
		if ( $args['status'] && array_key_exists( $args['status'], self::statuses() ) ) {
			$where[] = 'a.status = %s';
			$vals[]  = $args['status'];
		}
		if ( $args['campaign_id'] ) {
			$where[] = 'a.campaign_id = %d';
			$vals[]  = (int) $args['campaign_id'];
		}

		$sortable = array(
			'name'        => 'a.name',
			'status'      => 'a.status',
			'start'       => 'a.start_gmt',
			'end'         => 'a.end_gmt',
			'impressions' => 'a.impressions',
			'clicks'      => 'a.clicks',
			'priority'    => 'a.priority',
			'created_at'  => 'a.created_at',
		);
		$orderby  = isset( $sortable[ $args['orderby'] ] ) ? $sortable[ $args['orderby'] ] : 'a.created_at';
		$order    = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$ads} a WHERE {$where_sql}";
		$list_sql  = "SELECT a.*, c.name AS campaign_name FROM {$ads} a LEFT JOIN {$camps} c ON c.id = a.campaign_id WHERE {$where_sql} ORDER BY {$orderby} {$order}, a.id DESC LIMIT %d OFFSET %d";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) ( $vals ? $wpdb->get_var( $wpdb->prepare( $count_sql, $vals ) ) : $wpdb->get_var( $count_sql ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $vals, array( $per_page, $offset ) ) ) );
		// phpcs:enable

		return array(
			'items' => array_map( array( __CLASS__, 'hydrate' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Count of ads per status.
	 *
	 * @return array status => count, plus 'all'.
	 */
	public static function counts() {
		global $wpdb;
		$table  = Local_Ads_DB::table( 'ads' );
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counts = array_fill_keys( array_keys( self::statuses() ), 0 );
		$all    = 0;
		foreach ( $rows as $row ) {
			if ( isset( $counts[ $row->status ] ) ) {
				$counts[ $row->status ] = (int) $row->n;
			}
			$all += (int) $row->n;
		}
		$counts['all'] = $all;
		return $counts;
	}

	/**
	 * Resolves the stored status for an ad that the admin wants enabled/disabled.
	 *
	 * @param string $requested draft|active|paused (anything else treated as draft).
	 * @param int    $start_ts  Start timestamp or 0.
	 * @param int    $end_ts    End timestamp or 0.
	 * @param int    $now       Current timestamp.
	 * @return string
	 */
	public static function resolve_status( $requested, $start_ts, $end_ts, $now ) {
		if ( 'paused' === $requested ) {
			return 'paused';
		}
		if ( 'active' !== $requested && 'scheduled' !== $requested ) {
			return 'draft';
		}
		if ( $end_ts && $end_ts < $now ) {
			return 'expired';
		}
		if ( $start_ts && $start_ts > $now ) {
			return 'scheduled';
		}
		return 'active';
	}

	/**
	 * Inserts or updates an ad.
	 *
	 * @param array $data Sanitized fields (see Local_Ads_Admin::collect_ad()).
	 * @param int   $id   Existing ID or 0.
	 * @return int
	 */
	public static function save( array $data, $id = 0 ) {
		global $wpdb;
		$table = Local_Ads_DB::table( 'ads' );
		$row   = array(
			'name'            => $data['name'],
			'advertiser'      => $data['advertiser'],
			'description'     => $data['description'],
			'campaign_id'     => (int) $data['campaign_id'],
			'status'          => $data['status'],
			'priority'        => (int) $data['priority'],
			'weight'          => (int) $data['weight'],
			'media_id'        => (int) $data['media_id'],
			'alt_text'        => $data['alt_text'],
			'destination_url' => $data['destination_url'],
			'start_gmt'       => $data['start_gmt'],
			'end_gmt'         => $data['end_gmt'],
			'display'         => wp_json_encode( $data['display'] ),
			'targeting'       => wp_json_encode( $data['targeting'] ),
			'updated_at'      => Local_Ads_DB::now_gmt(),
		);
		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) );
		} else {
			$row['created_by']  = get_current_user_id();
			$row['created_at']  = Local_Ads_DB::now_gmt();
			$row['impressions'] = 0;
			$row['clicks']      = 0;
			$wpdb->insert( $table, $row );
			$id = (int) $wpdb->insert_id;
		}
		self::refresh_live_flag();
		return (int) $id;
	}

	/**
	 * Writes a status directly.
	 *
	 * @param int    $id     Ad ID.
	 * @param string $status Status.
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;
		$wpdb->update(
			Local_Ads_DB::table( 'ads' ),
			array(
				'status'     => $status,
				'updated_at' => Local_Ads_DB::now_gmt(),
			),
			array( 'id' => (int) $id )
		);
		self::refresh_live_flag();
	}

	/**
	 * Applies a manual action.
	 *
	 * @param int    $id     Ad ID.
	 * @param string $action activate|pause|deactivate|start_now.
	 * @return string|WP_Error Resulting status.
	 */
	public static function apply_action( $id, $action ) {
		global $wpdb;
		$ad = self::get( $id );
		if ( ! $ad ) {
			return new WP_Error( 'missing', __( 'Advertisement not found.', 'local-ads' ) );
		}
		$now = time();
		switch ( $action ) {
			case 'pause':
				self::update_status( $ad->id, 'paused' );
				return 'paused';
			case 'deactivate':
				self::update_status( $ad->id, 'draft' );
				return 'draft';
			case 'start_now':
				if ( $ad->end_ts && $ad->end_ts < $now ) {
					return new WP_Error( 'ended', __( 'Advertisement schedule is invalid: the end date has already passed. Edit the schedule first.', 'local-ads' ) );
				}
				$check = Local_Ads_Scheduler::check_activation( $ad->id, $now, $ad->end_ts );
				if ( is_wp_error( $check ) ) {
					return $check;
				}
				$wpdb->update(
					Local_Ads_DB::table( 'ads' ),
					array(
						'start_gmt'  => gmdate( 'Y-m-d H:i:s', $now ),
						'status'     => 'active',
						'updated_at' => Local_Ads_DB::now_gmt(),
					),
					array( 'id' => $ad->id )
				);
				self::refresh_live_flag();
				return 'active';
			case 'activate':
				if ( $ad->end_ts && $ad->end_ts < $now ) {
					return new WP_Error( 'ended', __( 'Advertisement schedule is invalid: the end date has already passed. Edit the schedule first.', 'local-ads' ) );
				}
				if ( ! $ad->media_id || ! Local_Ads_Media::get( $ad->media_id ) ) {
					return new WP_Error( 'no_image', __( 'Add an image before activating this advertisement.', 'local-ads' ) );
				}
				$check = Local_Ads_Scheduler::check_activation( $ad->id, $ad->start_ts, $ad->end_ts );
				if ( is_wp_error( $check ) ) {
					return $check;
				}
				$status = self::resolve_status( 'active', $ad->start_ts, $ad->end_ts, $now );
				self::update_status( $ad->id, $status );
				return $status;
		}
		return new WP_Error( 'action', __( 'Unknown action.', 'local-ads' ) );
	}

	/**
	 * Duplicates an ad as a draft with zeroed statistics.
	 *
	 * @param int $id Ad ID.
	 * @return int|WP_Error New ID.
	 */
	public static function duplicate( $id ) {
		$ad = self::get( $id );
		if ( ! $ad ) {
			return new WP_Error( 'missing', __( 'Advertisement not found.', 'local-ads' ) );
		}
		return self::save(
			array(
				/* translators: %s: original advertisement name. */
				'name'            => sprintf( __( '%s (Copy)', 'local-ads' ), $ad->name ),
				'advertiser'      => $ad->advertiser,
				'description'     => (string) $ad->description,
				'campaign_id'     => (int) $ad->campaign_id,
				'status'          => 'draft',
				'priority'        => $ad->priority,
				'weight'          => $ad->weight,
				'media_id'        => $ad->media_id,
				'alt_text'        => $ad->alt_text,
				'destination_url' => (string) $ad->destination_url,
				'start_gmt'       => $ad->start_gmt,
				'end_gmt'         => $ad->end_gmt,
				'display'         => $ad->display,
				'targeting'       => $ad->targeting,
			)
		);
	}

	/**
	 * Deletes an ad. Its daily statistics are kept so campaign history stays accurate.
	 *
	 * @param int $id Ad ID.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( Local_Ads_DB::table( 'ads' ), array( 'id' => (int) $id ) );
		self::refresh_live_flag();
	}

	/**
	 * Moves scheduled ads to active and ended ads to expired.
	 *
	 * Display eligibility never depends on this having run; it only keeps admin statuses tidy.
	 *
	 * @param int|null $now Timestamp.
	 * @return int Rows changed.
	 */
	public static function sync_statuses( $now = null ) {
		global $wpdb;
		$table   = Local_Ads_DB::table( 'ads' );
		$now_sql = gmdate( 'Y-m-d H:i:s', null === $now ? time() : (int) $now );
		$changed = 0;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$changed += (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'expired', updated_at = %s WHERE status IN ('active','scheduled') AND end_gmt IS NOT NULL AND end_gmt < %s", $now_sql, $now_sql ) );
		$changed += (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'active', updated_at = %s WHERE status = 'scheduled' AND (start_gmt IS NULL OR start_gmt <= %s)", $now_sql, $now_sql ) );
		$changed += (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'scheduled', updated_at = %s WHERE status = 'active' AND start_gmt IS NOT NULL AND start_gmt > %s", $now_sql, $now_sql ) );
		// phpcs:enable
		if ( $changed ) {
			self::refresh_live_flag();
		}
		return $changed;
	}

	/**
	 * Stores a cheap flag telling the frontend whether any ad could display, so pages
	 * do not load the script when nothing is running.
	 */
	public static function refresh_live_flag() {
		global $wpdb;
		$table = Local_Ads_DB::table( 'ads' );
		$now   = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, SUM(end_gmt IS NULL) AS open_ended, MAX(end_gmt) AS max_end FROM {$table} WHERE status IN ('active','scheduled') AND (end_gmt IS NULL OR end_gmt >= %s)", $now ) );
		$until = 0;
		if ( $row && (int) $row->n > 0 && ! (int) $row->open_ended && $row->max_end ) {
			$until = strtotime( $row->max_end . ' UTC' );
		}
		update_option(
			self::LIVE_OPTION,
			array(
				'count' => $row ? (int) $row->n : 0,
				'until' => $until,
			),
			true
		);
	}

	/**
	 * Whether any ad may currently be live (cheap check used before enqueueing scripts).
	 *
	 * @return bool
	 */
	public static function maybe_live() {
		$flag = get_option( self::LIVE_OPTION, null );
		if ( ! is_array( $flag ) ) {
			self::refresh_live_flag();
			$flag = get_option( self::LIVE_OPTION, array() );
		}
		if ( empty( $flag['count'] ) ) {
			return false;
		}
		return empty( $flag['until'] ) || (int) $flag['until'] >= time();
	}

	/**
	 * Ads whose status, schedule and campaign allow them to run at $now, after the
	 * concurrency rules are applied. Page targeting and visitor frequency are applied later.
	 *
	 * @param int|null $now Timestamp.
	 * @return object[]
	 */
	public static function live_ads( $now = null ) {
		global $wpdb;
		$now     = null === $now ? time() : (int) $now;
		$now_sql = gmdate( 'Y-m-d H:i:s', $now );
		$ads     = Local_Ads_DB::table( 'ads' );
		$camps   = Local_Ads_DB::table( 'campaigns' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql  = $wpdb->prepare( "SELECT a.* FROM {$ads} a LEFT JOIN {$camps} c ON c.id = a.campaign_id WHERE a.status IN ('active','scheduled') AND a.media_id > 0 AND (a.start_gmt IS NULL OR a.start_gmt <= %s) AND (a.end_gmt IS NULL OR a.end_gmt >= %s) AND (a.campaign_id = 0 OR c.id IS NULL OR (c.status = 'active' AND (c.start_gmt IS NULL OR c.start_gmt <= %s) AND (c.end_gmt IS NULL OR c.end_gmt >= %s)))", $now_sql, $now_sql, $now_sql, $now_sql );
		$rows = array_map( array( __CLASS__, 'hydrate' ), (array) $wpdb->get_results( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return self::apply_concurrency( $rows );
	}

	/**
	 * Applies the concurrency settings to a set of live ads.
	 *
	 * With concurrency disabled only one ad runs; with a maximum, only that many run.
	 * Running ads are chosen by priority, then earliest start, then lowest ID.
	 *
	 * @param object[] $rows Live ads.
	 * @return object[]
	 */
	public static function apply_concurrency( array $rows ) {
		$limit = Local_Ads_Settings::get( 'allow_concurrent' ) ? (int) Local_Ads_Settings::get( 'max_concurrent' ) : 1;
		if ( $limit < 1 || count( $rows ) <= $limit ) {
			return $rows;
		}
		usort(
			$rows,
			function ( $a, $b ) {
				if ( $a->priority !== $b->priority ) {
					return $b->priority - $a->priority;
				}
				if ( $a->start_ts !== $b->start_ts ) {
					return $a->start_ts - $b->start_ts;
				}
				return $a->id - $b->id;
			}
		);
		return array_slice( $rows, 0, $limit );
	}

	/**
	 * Whether a single ad is live at $now (status, schedule, campaign, image).
	 *
	 * @param object   $ad  Hydrated ad.
	 * @param int|null $now Timestamp.
	 * @return bool
	 */
	public static function is_live( $ad, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		if ( ! $ad || ! in_array( $ad->status, self::enabled_statuses(), true ) || ! $ad->media_id ) {
			return false;
		}
		if ( $ad->start_ts && $ad->start_ts > $now ) {
			return false;
		}
		if ( $ad->end_ts && $ad->end_ts < $now ) {
			return false;
		}
		if ( $ad->campaign_id ) {
			$campaign = Local_Ads_Campaigns::get( $ad->campaign_id );
			if ( $campaign && ! Local_Ads_Campaigns::is_running( $campaign, $now ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Matches targeting rules against a page context.
	 *
	 * @param array $t   Targeting rules.
	 * @param array $ctx { type, id, path, checkout, cart, account }.
	 * @return bool
	 */
	public static function targeting_matches( array $t, array $ctx ) {
		$type = isset( $ctx['type'] ) ? $ctx['type'] : '';
		$id   = isset( $ctx['id'] ) ? (int) $ctx['id'] : 0;
		$path = isset( $ctx['path'] ) ? (string) $ctx['path'] : '/';

		// Exclusions win.
		if ( ! empty( $t['ex_home'] ) && 'front' === $type ) {
			return false;
		}
		if ( ! empty( $t['ex_checkout'] ) && ! empty( $ctx['checkout'] ) ) {
			return false;
		}
		if ( ! empty( $t['ex_cart'] ) && ! empty( $ctx['cart'] ) ) {
			return false;
		}
		if ( ! empty( $t['ex_login'] ) && ( ! empty( $ctx['account'] ) || 'login' === $type ) ) {
			return false;
		}
		if ( $id && ! empty( $t['ex_page_ids'] ) && in_array( $id, array_map( 'intval', $t['ex_page_ids'] ), true ) ) {
			return false;
		}
		if ( ! empty( $t['ex_urls'] ) ) {
			foreach ( $t['ex_urls'] as $rule ) {
				if ( self::path_matches( $rule, $path ) ) {
					return false;
				}
			}
		}

		if ( 'custom' !== $t['mode'] ) {
			return true;
		}
		if ( ! empty( $t['home'] ) && 'front' === $type ) {
			return true;
		}
		if ( ! empty( $t['posts'] ) && 'post' === $type ) {
			return true;
		}
		if ( ! empty( $t['pages'] ) && 'page' === $type ) {
			return true;
		}
		if ( ! empty( $t['products'] ) && 'product' === $type ) {
			return true;
		}
		if ( $id && 'page' === $type && in_array( $id, array_map( 'intval', (array) $t['page_ids'] ), true ) ) {
			return true;
		}
		if ( $id && in_array( $type, array( 'post', 'product', 'singular' ), true ) && in_array( $id, array_map( 'intval', (array) $t['post_ids'] ), true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Matches an exclusion rule (path with optional * wildcards) against a request path.
	 *
	 * @param string $rule Rule, e.g. "/checkout/*".
	 * @param string $path Request path with optional query.
	 * @return bool
	 */
	public static function path_matches( $rule, $path ) {
		$rule = (string) $rule;
		if ( '' === $rule ) {
			return false;
		}
		$path    = '/' . ltrim( (string) $path, '/' );
		$pattern = '#^' . str_replace( '\*', '.*', preg_quote( untrailingslashit( $rule ), '#' ) ) . '/?(\?.*)?$#i';
		if ( false !== strpos( $rule, '?' ) ) {
			$pattern = '#^' . str_replace( '\*', '.*', preg_quote( $rule, '#' ) ) . '$#i';
		}
		return (bool) preg_match( $pattern, $path );
	}

	/**
	 * Data the frontend needs for one ad (no internal information).
	 *
	 * @param object $ad    Hydrated ad.
	 * @param string $day   Site date for the tracking signature.
	 * @return array|null
	 */
	public static function to_frontend( $ad, $day ) {
		$media = Local_Ads_Media::get( $ad->media_id );
		if ( ! $media ) {
			return null;
		}
		$d = $ad->display;
		return array(
			'id'       => $ad->id,
			'sig'      => Local_Ads_Security::track_signature( $ad->id, $day ),
			'img'      => Local_Ads_Media::url( $media ),
			'w'        => (int) $media->width,
			'h'        => (int) $media->height,
			'alt'      => (string) $ad->alt_text,
			'url'      => (string) $ad->destination_url,
			'start'    => (int) $ad->start_ts,
			'end'      => (int) $ad->end_ts,
			'priority' => $ad->priority,
			'weight'   => max( 1, $ad->weight ),
			'trigger'  => $d['trigger'],
			'tDelay'   => (int) $d['trigger_delay'],
			'tPx'      => (int) $d['trigger_pixels'],
			'tPct'     => (int) $d['trigger_percent'],
			'freq'     => $d['frequency'],
			'freqH'    => (int) $d['frequency_hours'],
			'autoClose' => (int) $d['auto_close'] ? (int) $d['auto_close_secs'] : 0,
			'anim'     => $d['animation'],
			'shake'    => (int) $d['shake'],
			'shakeDelay' => (int) $d['shake_delay'],
			'shakeDur' => (int) $d['shake_duration'],
			'shakeInt' => $d['shake_intensity'],
			'newTab'   => (int) $d['new_tab'],
			'closeOnClick' => (int) $d['close_after_click'],
			'width'    => (int) $d['width'],
			'mWidth'   => (int) $d['mobile_width'],
		);
	}
}
