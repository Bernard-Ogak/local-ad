<?php
/**
 * Frontend: script loading, page context and the live ad feed.
 *
 * The page only carries a tiny context object. Eligible ads are fetched from an uncached
 * endpoint at runtime, so status, schedule and frequency are always verified live even when
 * the HTML page itself is served from a page cache.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend display controller.
 */
class Local_Ads_Display {

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_local_ads_get', array( __CLASS__, 'ajax_get' ) );
		add_action( 'wp_ajax_nopriv_local_ads_get', array( __CLASS__, 'ajax_get' ) );
	}

	/**
	 * Detects the type of the current page for targeting.
	 *
	 * @return array
	 */
	public static function context() {
		$type = 'other';
		if ( is_front_page() ) {
			$type = 'front';
		} elseif ( is_home() ) {
			$type = 'blog';
		} elseif ( function_exists( 'is_product' ) && is_product() ) {
			$type = 'product';
		} elseif ( is_page() ) {
			$type = 'page';
		} elseif ( is_singular( 'post' ) ) {
			$type = 'post';
		} elseif ( is_singular() ) {
			$type = 'singular';
		} elseif ( is_archive() || is_search() ) {
			$type = 'archive';
		}
		return array(
			'type'     => $type,
			'id'       => is_singular() || is_page() ? (int) get_queried_object_id() : 0,
			'checkout' => function_exists( 'is_checkout' ) && is_checkout() ? 1 : 0,
			'cart'     => function_exists( 'is_cart' ) && is_cart() ? 1 : 0,
			'account'  => function_exists( 'is_account_page' ) && is_account_page() ? 1 : 0,
		);
	}

	/**
	 * Enqueues the small deferred frontend script when ads may be running.
	 */
	public static function enqueue() {
		if ( is_admin() || ! Local_Ads_Settings::get( 'enabled' ) || is_embed() || is_feed() || is_customize_preview() ) {
			return;
		}
		if ( ! Local_Ads_Ads::maybe_live() ) {
			return;
		}
		wp_register_script( 'local-ads-public', LOCAL_ADS_URL . 'public/js/public.js', array(), LOCAL_ADS_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		$config = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'cssUrl'  => add_query_arg( 'ver', LOCAL_ADS_VERSION, LOCAL_ADS_URL . 'public/css/public.css' ),
			'ctx'     => self::context(),
			'debug'   => (int) Local_Ads_Settings::get( 'debug' ),
			'i18n'    => array(
				'close' => __( 'Close advertisement', 'local-ads' ),
				'label' => __( 'Advertisement', 'local-ads' ),
				'opens' => __( '(opens in a new tab)', 'local-ads' ),
			),
		);
		wp_add_inline_script( 'local-ads-public', 'window.LocalAdsConfig = ' . wp_json_encode( $config ) . ';', 'before' );
		wp_enqueue_script( 'local-ads-public' );
	}

	/**
	 * Global display options shared by every ad.
	 *
	 * @return array
	 */
	public static function global_options() {
		$s = Local_Ads_Settings::all();
		return array(
			'rotation'     => $s['rotation'],
			'overlay'      => (int) $s['overlay_enabled'],
			'opacity'      => (float) $s['overlay_opacity'],
			'overlayClose' => (int) $s['overlay_close'],
			'closeSize'    => $s['close_size'],
			'closePos'     => $s['close_position'],
			'closeDelay'   => 'delayed' === $s['close_visibility'] ? (int) $s['close_delay'] : 0,
			'radius'       => (int) $s['border_radius'],
			'z'            => (int) $s['z_index'],
			'speed'        => (int) $s['animation_speed'],
			'label'        => (int) $s['show_label'],
			'cap'          => $s['global_cap'],
			'capH'         => (int) $s['global_cap_hours'],
			'noScroll'     => (int) $s['noscroll_fallback'],
			'track'        => (int) $s['analytics_enabled'],
		);
	}

	/**
	 * Uncached feed of ads eligible for a page context.
	 */
	public static function ajax_get() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );

		if ( ! Local_Ads_Settings::get( 'enabled' ) ) {
			wp_send_json_success( array( 'ads' => array() ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, read-only feed.
		$ctx = array(
			'type'     => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'other',
			'id'       => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0,
			'path'     => isset( $_GET['path'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['path'] ) ), 0, 500 ) : '/',
			'checkout' => ! empty( $_GET['checkout'] ) ? 1 : 0,
			'cart'     => ! empty( $_GET['cart'] ) ? 1 : 0,
			'account'  => ! empty( $_GET['account'] ) ? 1 : 0,
		);
		// phpcs:enable

		$now  = time();
		$day  = wp_date( 'Y-m-d', $now );
		$list = array();
		foreach ( Local_Ads_Ads::live_ads( $now ) as $ad ) {
			if ( ! Local_Ads_Ads::targeting_matches( $ad->targeting, $ctx ) ) {
				continue;
			}
			$data = Local_Ads_Ads::to_frontend( $ad, $day );
			if ( $data ) {
				$list[] = $data;
			}
		}

		wp_send_json_success(
			array(
				'now'    => $now,
				'tz'     => (int) wp_timezone()->getOffset( new DateTime( 'now' ) ),
				'global' => self::global_options(),
				'ads'    => $list,
			)
		);
	}
}
