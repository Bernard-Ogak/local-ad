<?php
/**
 * Plugin Name:       Local Ads by Bernard
 * Description:       A locally hosted WordPress advertisement management, scheduling and analytics platform.
 * Version:           1.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Plugin URI:        https://github.com/Bernard-Ogak/local-ad
 * Author:            Bernard Ogak (Creative Bay)
 * Author URI:        https://www.creativebay.co.ke
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       local-ads
 * Domain Path:       /languages
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

define( 'LOCAL_ADS_VERSION', '1.0.1' );
define( 'LOCAL_ADS_DB_VERSION', '1.0.0' );
define( 'LOCAL_ADS_FILE', __FILE__ );
define( 'LOCAL_ADS_PATH', plugin_dir_path( __FILE__ ) );
define( 'LOCAL_ADS_URL', plugin_dir_url( __FILE__ ) );
define( 'LOCAL_ADS_BASENAME', plugin_basename( __FILE__ ) );

require_once LOCAL_ADS_PATH . 'includes/class-local-ads-db.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-settings.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-security.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-campaigns.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-media.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-ads.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-scheduler.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-analytics.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-display.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-cron.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads-activator.php';
require_once LOCAL_ADS_PATH . 'includes/class-local-ads.php';

if ( is_admin() ) {
	require_once LOCAL_ADS_PATH . 'includes/class-local-ads-admin.php';
}

register_activation_hook( __FILE__, array( 'Local_Ads_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Local_Ads_Activator', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return Local_Ads
 */
function local_ads() {
	return Local_Ads::instance();
}

local_ads();
