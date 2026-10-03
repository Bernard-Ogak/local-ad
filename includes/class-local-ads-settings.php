<?php
/**
 * Global settings: defaults, option storage and sanitization.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings store backed by a single option.
 */
class Local_Ads_Settings {

	const OPTION = 'local_ads_settings';

	/**
	 * Request-level cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values for every setting.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// General.
			'enabled'                  => 1,
			'show_label'               => 1,
			'default_width'            => 600,
			'default_mobile_width'     => 92,
			// Popup.
			'default_trigger'          => 'scroll_start',
			'default_trigger_delay'    => 3,
			'default_trigger_pixels'   => 300,
			'default_trigger_percent'  => 5,
			'noscroll_fallback'        => 5,
			'default_auto_close'       => 1,
			'default_auto_close_secs'  => 120,
			'overlay_enabled'          => 1,
			'overlay_opacity'          => 0.55,
			'overlay_close'            => 1,
			'close_size'               => 'medium',
			'close_position'           => 'top-right',
			'close_visibility'         => 'always',
			'close_delay'              => 3,
			'border_radius'            => 8,
			'z_index'                  => 999999,
			// Animation.
			'default_animation'        => 'fade',
			'animation_speed'          => 400,
			'default_shake'            => 0,
			'default_shake_delay'      => 2,
			'default_shake_duration'   => 3,
			'default_shake_intensity'  => 'medium',
			// Behaviour.
			'default_new_tab'          => 1,
			'default_close_after_click' => 1,
			'default_frequency'        => 'session',
			'default_frequency_hours'  => 24,
			'global_cap'               => 'none',
			'global_cap_hours'         => 1,
			// Scheduling.
			'allow_concurrent'         => 1,
			'max_concurrent'           => 0,
			'rotation'                 => 'random',
			// Storage.
			'upload_folder'            => 'local-ads',
			'max_upload_mb'            => 5,
			'allow_gif'                => 1,
			// Analytics.
			'analytics_enabled'        => 1,
			'retention_days'           => 0,
			'event_retention_days'     => 30,
			'exclude_managers'         => 0,
			// Advanced.
			'debug'                    => 0,
			'delete_data'              => 0,
			'delete_images'            => 0,
		);
	}

	/**
	 * Allowed values for choice settings (also used by per-ad display options).
	 *
	 * @param string $key Choice group.
	 * @return array value => label
	 */
	public static function choices( $key ) {
		switch ( $key ) {
			case 'trigger':
				return array(
					'immediate'    => __( 'Immediately', 'local-ads' ),
					'delay'        => __( 'After X seconds', 'local-ads' ),
					'scroll_start' => __( 'When visitor starts scrolling', 'local-ads' ),
					'pixels'       => __( 'After X pixels scrolled', 'local-ads' ),
					'percent'      => __( 'After X% page scroll', 'local-ads' ),
				);
			case 'frequency':
				return array(
					'session' => __( 'Once per browser session', 'local-ads' ),
					'visit'   => __( 'Once per visit (30 minutes of inactivity ends a visit)', 'local-ads' ),
					'day'     => __( 'Once per day', 'local-ads' ),
					'hours'   => __( 'Every X hours', 'local-ads' ),
					'always'  => __( 'Always eligible (every page view)', 'local-ads' ),
				);
			case 'animation':
				return array(
					'fade'        => __( 'Fade In', 'local-ads' ),
					'slide-up'    => __( 'Slide Up', 'local-ads' ),
					'slide-down'  => __( 'Slide Down', 'local-ads' ),
					'slide-left'  => __( 'Slide Left', 'local-ads' ),
					'slide-right' => __( 'Slide Right', 'local-ads' ),
					'zoom'        => __( 'Zoom', 'local-ads' ),
					'bounce'      => __( 'Bounce', 'local-ads' ),
					'shake'       => __( 'Shake', 'local-ads' ),
					'none'        => __( 'None', 'local-ads' ),
				);
			case 'intensity':
				return array(
					'low'    => __( 'Low', 'local-ads' ),
					'medium' => __( 'Medium', 'local-ads' ),
					'high'   => __( 'High', 'local-ads' ),
				);
			case 'rotation':
				return array(
					'random'   => __( 'Random', 'local-ads' ),
					'sequential' => __( 'Sequential', 'local-ads' ),
					'priority' => __( 'Priority (highest first)', 'local-ads' ),
					'weighted' => __( 'Weighted random', 'local-ads' ),
				);
			case 'close_size':
				return array(
					'small'  => __( 'Small (28px)', 'local-ads' ),
					'medium' => __( 'Medium (36px)', 'local-ads' ),
					'large'  => __( 'Large (44px)', 'local-ads' ),
				);
			case 'close_position':
				return array(
					'top-right' => __( 'Top right', 'local-ads' ),
					'top-left'  => __( 'Top left', 'local-ads' ),
				);
			case 'close_visibility':
				return array(
					'always'  => __( 'Always visible', 'local-ads' ),
					'delayed' => __( 'Visible after a short delay', 'local-ads' ),
				);
			case 'global_cap':
				return array(
					'none'    => __( 'No global restriction (frequency is tracked per advertisement)', 'local-ads' ),
					'session' => __( 'After any ad is closed, show no other ads for the rest of the browser session', 'local-ads' ),
					'hours'   => __( 'After any ad is closed, show no other ads for X hours', 'local-ads' ),
				);
			case 'retention':
				return array(
					30  => __( '30 days', 'local-ads' ),
					90  => __( '90 days', 'local-ads' ),
					180 => __( '180 days', 'local-ads' ),
					365 => __( '365 days', 'local-ads' ),
					0   => __( 'Forever', 'local-ads' ),
				);
			case 'auto_close_presets':
				return array( 10, 30, 60, 120 );
		}
		return array();
	}

	/**
	 * Returns all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Returns one setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Saves a full settings array (already sanitized).
	 *
	 * @param array $settings Settings.
	 */
	public static function save( array $settings ) {
		update_option( self::OPTION, $settings );
		self::$cache = null;
	}

	/**
	 * Clears the request cache.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Sanitizes raw settings input. Unknown keys are discarded; missing checkbox keys become 0.
	 *
	 * @param array $input  Raw input (already unslashed).
	 * @param array $errors Collected error codes (by reference).
	 * @return array
	 */
	public static function sanitize( array $input, array &$errors = array() ) {
		$d   = self::defaults();
		$out = self::all();

		$bools = array( 'enabled', 'show_label', 'default_auto_close', 'overlay_enabled', 'overlay_close', 'default_shake', 'default_new_tab', 'default_close_after_click', 'allow_concurrent', 'allow_gif', 'analytics_enabled', 'exclude_managers', 'debug', 'delete_data', 'delete_images' );
		foreach ( $bools as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$ints = array(
			'default_width'           => array( 200, 2000 ),
			'default_mobile_width'    => array( 50, 100 ),
			'default_trigger_delay'   => array( 0, 600 ),
			'default_trigger_pixels'  => array( 1, 100000 ),
			'default_trigger_percent' => array( 1, 100 ),
			'noscroll_fallback'       => array( 0, 600 ),
			'default_auto_close_secs' => array( 3, 3600 ),
			'close_delay'             => array( 1, 10 ),
			'border_radius'           => array( 0, 48 ),
			'z_index'                 => array( 1000, 2147483000 ),
			'animation_speed'         => array( 100, 2000 ),
			'default_shake_delay'     => array( 0, 120 ),
			'default_shake_duration'  => array( 1, 10 ),
			'default_frequency_hours' => array( 1, 8760 ),
			'global_cap_hours'        => array( 1, 8760 ),
			'max_concurrent'          => array( 0, 100 ),
			'max_upload_mb'           => array( 1, 50 ),
			'event_retention_days'    => array( 1, 365 ),
		);
		foreach ( $ints as $key => $range ) {
			if ( isset( $input[ $key ] ) && '' !== $input[ $key ] ) {
				$out[ $key ] = min( $range[1], max( $range[0], (int) $input[ $key ] ) );
			}
		}

		if ( isset( $input['overlay_opacity'] ) ) {
			$out['overlay_opacity'] = round( min( 1, max( 0, (float) $input['overlay_opacity'] ) ), 2 );
		}

		$choice_map = array(
			'default_trigger'         => 'trigger',
			'default_frequency'       => 'frequency',
			'default_animation'       => 'animation',
			'default_shake_intensity' => 'intensity',
			'rotation'                => 'rotation',
			'close_size'              => 'close_size',
			'close_position'          => 'close_position',
			'close_visibility'        => 'close_visibility',
			'global_cap'              => 'global_cap',
		);
		foreach ( $choice_map as $key => $group ) {
			if ( isset( $input[ $key ] ) ) {
				$value       = sanitize_key( $input[ $key ] );
				$out[ $key ] = array_key_exists( $value, self::choices( $group ) ) ? $value : $d[ $key ];
			}
		}

		if ( isset( $input['retention_days'] ) ) {
			$value                 = absint( $input['retention_days'] );
			$out['retention_days'] = array_key_exists( $value, self::choices( 'retention' ) ) ? $value : 0;
		}

		if ( isset( $input['upload_folder'] ) ) {
			$folder = Local_Ads_Security::sanitize_folder( $input['upload_folder'] );
			if ( false === $folder ) {
				$errors[] = 'invalid_folder';
			} else {
				$out['upload_folder'] = $folder;
			}
		}

		return $out;
	}
}
