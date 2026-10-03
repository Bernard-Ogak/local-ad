<?php
/**
 * Main plugin class.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the components together.
 */
final class Local_Ads {

	/**
	 * Singleton.
	 *
	 * @var Local_Ads|null
	 */
	private static $instance = null;

	/**
	 * Returns the instance.
	 *
	 * @return Local_Ads
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load' ) );
	}

	/**
	 * Boots the components once WordPress has loaded plugins.
	 */
	public function load() {
		load_plugin_textdomain( 'local-ads', false, dirname( LOCAL_ADS_BASENAME ) . '/languages' );

		Local_Ads_DB::maybe_upgrade();
		if ( get_option( 'local_ads_version' ) !== LOCAL_ADS_VERSION ) {
			Local_Ads_Security::add_caps();
			update_option( 'local_ads_version', LOCAL_ADS_VERSION, false );
		}

		Local_Ads_Cron::init();
		Local_Ads_Analytics::init();
		Local_Ads_Display::init();
		Local_Ads_Updater::init();

		if ( is_admin() && class_exists( 'Local_Ads_Admin' ) ) {
			Local_Ads_Admin::init();
		}
	}
}
