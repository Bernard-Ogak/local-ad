<?php
/**
 * Database schema and table helpers.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin tables.
 */
class Local_Ads_DB {

	/**
	 * Returns a prefixed table name.
	 *
	 * @param string $name Short table name: ads, campaigns, media, daily_stats, events.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'local_ads_' . $name;
	}

	/**
	 * All plugin table names.
	 *
	 * @return string[]
	 */
	public static function tables() {
		return array(
			self::table( 'ads' ),
			self::table( 'campaigns' ),
			self::table( 'media' ),
			self::table( 'daily_stats' ),
			self::table( 'events' ),
		);
	}

	/**
	 * Creates or upgrades tables with dbDelta.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$ads     = self::table( 'ads' );
		$camps   = self::table( 'campaigns' );
		$media   = self::table( 'media' );
		$stats   = self::table( 'daily_stats' );
		$events  = self::table( 'events' );

		$sql = array();

		$sql[] = "CREATE TABLE {$ads} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(200) NOT NULL DEFAULT '',
  advertiser varchar(200) NOT NULL DEFAULT '',
  description text NULL,
  campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'draft',
  priority smallint(5) unsigned NOT NULL DEFAULT 10,
  weight smallint(5) unsigned NOT NULL DEFAULT 10,
  media_id bigint(20) unsigned NOT NULL DEFAULT 0,
  alt_text varchar(255) NOT NULL DEFAULT '',
  destination_url text NULL,
  start_gmt datetime NULL DEFAULT NULL,
  end_gmt datetime NULL DEFAULT NULL,
  display longtext NULL,
  targeting longtext NULL,
  impressions bigint(20) unsigned NOT NULL DEFAULT 0,
  clicks bigint(20) unsigned NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY campaign_id (campaign_id),
  KEY media_id (media_id),
  KEY start_gmt (start_gmt),
  KEY end_gmt (end_gmt)
) {$charset};";

		$sql[] = "CREATE TABLE {$camps} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(200) NOT NULL DEFAULT '',
  description text NULL,
  status varchar(20) NOT NULL DEFAULT 'active',
  start_gmt datetime NULL DEFAULT NULL,
  end_gmt datetime NULL DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY status (status)
) {$charset};";

		$sql[] = "CREATE TABLE {$media} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  file varchar(255) NOT NULL DEFAULT '',
  filename varchar(255) NOT NULL DEFAULT '',
  mime varchar(100) NOT NULL DEFAULT '',
  width int(10) unsigned NOT NULL DEFAULT 0,
  height int(10) unsigned NOT NULL DEFAULT 0,
  filesize bigint(20) unsigned NOT NULL DEFAULT 0,
  uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id)
) {$charset};";

		$sql[] = "CREATE TABLE {$stats} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ad_id bigint(20) unsigned NOT NULL DEFAULT 0,
  campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
  stat_date date NOT NULL,
  impressions bigint(20) unsigned NOT NULL DEFAULT 0,
  clicks bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY ad_date (ad_id,stat_date),
  KEY ad_id (ad_id),
  KEY campaign_id (campaign_id),
  KEY stat_date (stat_date)
) {$charset};";

		$sql[] = "CREATE TABLE {$events} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ad_id bigint(20) unsigned NOT NULL DEFAULT 0,
  campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
  event_type varchar(20) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY ad_id (ad_id),
  KEY event_type (event_type),
  KEY created_at (created_at)
) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'local_ads_db_version', LOCAL_ADS_DB_VERSION, false );
	}

	/**
	 * Runs install when the stored schema version is out of date.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'local_ads_db_version' ) !== LOCAL_ADS_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Drops every plugin table. Used only by uninstall.
	 */
	public static function drop_tables() {
		global $wpdb;
		foreach ( self::tables() as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
	}

	/**
	 * Current time in GMT as a MySQL datetime.
	 *
	 * @return string
	 */
	public static function now_gmt() {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
