<?php
/**
 * Admin controller: menus, assets, form handlers, AJAX endpoints and notices.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class Local_Ads_Admin {

	/**
	 * Page hook suffixes registered by the plugin.
	 *
	 * @var string[]
	 */
	private static $hooks = array();

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . LOCAL_ADS_BASENAME, array( __CLASS__, 'action_links' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ) );

		$posts = array( 'save_ad', 'ad_action', 'save_campaign', 'campaign_action', 'media_upload', 'media_replace', 'media_delete', 'save_settings', 'export_csv', 'tools', 'preview' );
		foreach ( $posts as $action ) {
			add_action( 'admin_post_local_ads_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}

		add_action( 'wp_ajax_local_ads_media_list', array( __CLASS__, 'ajax_media_list' ) );
		add_action( 'wp_ajax_local_ads_media_upload', array( __CLASS__, 'ajax_media_upload' ) );
		add_action( 'wp_ajax_local_ads_check_schedule', array( __CLASS__, 'ajax_check_schedule' ) );
		add_action( 'wp_ajax_local_ads_preview_data', array( __CLASS__, 'ajax_preview_data' ) );
	}

	/* =====================================================================
	 * Menu and assets
	 * ================================================================== */

	/**
	 * Admin menu.
	 */
	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( self::menu_icon_svg() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		self::$hooks[] = add_menu_page( __( 'Local Ads', 'local-ads' ), __( 'Local Ads', 'local-ads' ), Local_Ads_Security::CAP_ANALYTICS, 'local-ads', array( __CLASS__, 'page_dashboard' ), $icon, 58 );

		$pages = array(
			array( 'local-ads', __( 'Dashboard', 'local-ads' ), Local_Ads_Security::CAP_ANALYTICS, 'page_dashboard' ),
			array( 'local-ads-ads', __( 'Advertisements', 'local-ads' ), Local_Ads_Security::CAP_EDIT, 'page_ads' ),
			array( 'local-ads-edit', __( 'Add New', 'local-ads' ), Local_Ads_Security::CAP_EDIT, 'page_edit' ),
			array( 'local-ads-campaigns', __( 'Campaigns', 'local-ads' ), Local_Ads_Security::CAP_EDIT, 'page_campaigns' ),
			array( 'local-ads-media', __( 'Media', 'local-ads' ), Local_Ads_Security::CAP_EDIT, 'page_media' ),
			array( 'local-ads-schedule', __( 'Schedule', 'local-ads' ), Local_Ads_Security::CAP_EDIT, 'page_schedule' ),
			array( 'local-ads-analytics', __( 'Analytics', 'local-ads' ), Local_Ads_Security::CAP_ANALYTICS, 'page_analytics' ),
			array( 'local-ads-settings', __( 'Settings', 'local-ads' ), Local_Ads_Security::CAP_MANAGE, 'page_settings' ),
			array( 'local-ads-tools', __( 'Tools', 'local-ads' ), Local_Ads_Security::CAP_MANAGE, 'page_tools' ),
		);
		foreach ( $pages as $p ) {
			$hook          = add_submenu_page( 'local-ads', $p[1] . ' &lsaquo; ' . __( 'Local Ads', 'local-ads' ), $p[1], $p[2], $p[0], array( __CLASS__, $p[3] ) );
			self::$hooks[] = $hook;
			if ( 'local-ads-ads' === $p[0] && $hook ) {
				add_action( 'load-' . $hook, array( __CLASS__, 'process_bulk' ) );
			}
		}
	}

	/**
	 * Monochrome menu icon (WordPress recolours it to match the admin scheme).
	 *
	 * @return string
	 */
	public static function menu_icon_svg() {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M3 3h14a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1h-5.6l-3.7 3.2a.5.5 0 0 1-.8-.4V14H3a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm2.2 2.6v5.8h3.6V10H6.7V5.6H5.2zm7 0-2.3 5.8h1.6l.4-1.1h2.3l.4 1.1H16l-2.3-5.8h-1.5zm.7 1.9.7 1.8h-1.4l.7-1.8z"/></svg>';
	}

	/**
	 * Highlights "Advertisements" (not "Add New") while editing an existing ad.
	 *
	 * @param string|null $submenu_file Current submenu file.
	 * @return string|null
	 */
	public static function submenu_file( $submenu_file ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'], $_GET['id'] ) && 'local-ads-edit' === $_GET['page'] && absint( $_GET['id'] ) ) {
			return 'local-ads-ads';
		}
		return $submenu_file;
	}

	/**
	 * Author credit in the admin footer on Local Ads screens only.
	 *
	 * @param string $text Default footer text.
	 * @return string
	 */
	public static function footer_text( $text ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, self::$hooks, true ) ) {
			return $text;
		}
		return sprintf(
			/* translators: 1: plugin name and version, 2: author name, 3: link to Creative Bay. */
			esc_html__( '%1$s by %2$s · %3$s', 'local-ads' ),
			'Local Ad ' . esc_html( LOCAL_ADS_VERSION ),
			'Bernard Ogak',
			'<a href="https://www.creativebay.co.ke" target="_blank" rel="noopener noreferrer">Creative Bay</a>'
		);
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		if ( current_user_can( Local_Ads_Security::CAP_MANAGE ) ) {
			array_unshift( $links, '<a href="' . esc_url( self::url( 'local-ads-settings' ) ) . '">' . esc_html__( 'Settings', 'local-ads' ) . '</a>' );
		}
		return $links;
	}

	/**
	 * Loads admin assets on plugin screens only.
	 *
	 * @param string $hook Current hook.
	 */
	public static function assets( $hook ) {
		if ( ! in_array( $hook, self::$hooks, true ) ) {
			return;
		}
		wp_enqueue_style( 'local-ads-admin', LOCAL_ADS_URL . 'admin/css/admin.css', array(), LOCAL_ADS_VERSION );
		wp_enqueue_script( 'local-ads-admin', LOCAL_ADS_URL . 'admin/js/admin.js', array(), LOCAL_ADS_VERSION, true );
		wp_localize_script(
			'local-ads-admin',
			'LocalAdsAdmin',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'local_ads_admin' ),
				'previewUrl' => wp_nonce_url( admin_url( 'admin-post.php?action=local_ads_preview' ), 'local_ads_preview' ),
				'maxUpload'  => Local_Ads_Media::max_bytes(),
				'i18n'       => array(
					'select'      => __( 'Select advertisement image', 'local-ads' ),
					'use'         => __( 'Use this image', 'local-ads' ),
					'upload'      => __( 'Upload new image', 'local-ads' ),
					'uploading'   => __( 'Uploading…', 'local-ads' ),
					'noImages'    => __( 'No images yet. Upload one to get started.', 'local-ads' ),
					'tooLarge'    => __( 'The file is larger than the allowed size.', 'local-ads' ),
					'badType'     => __( 'Only JPG, PNG, WEBP and GIF images are allowed.', 'local-ads' ),
					'failed'      => __( 'The image could not be uploaded.', 'local-ads' ),
					'close'       => __( 'Close', 'local-ads' ),
					'preview'     => __( 'Preview advertisement', 'local-ads' ),
					'desktop'     => __( 'Desktop', 'local-ads' ),
					'mobile'      => __( 'Mobile', 'local-ads' ),
					'replay'      => __( 'Replay animation', 'local-ads' ),
					'previewNote' => __( 'Previews never count as impressions or clicks.', 'local-ads' ),
					'needImage'   => __( 'Select an image to preview the advertisement.', 'local-ads' ),
					'confirm'     => __( 'Are you sure?', 'local-ads' ),
					'impressions' => __( 'Impressions', 'local-ads' ),
					'clicks'      => __( 'Clicks', 'local-ads' ),
					'ctr'         => __( 'CTR', 'local-ads' ),
				),
			)
		);
	}

	/**
	 * Admin URL for a plugin page.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/* =====================================================================
	 * Notices and form state
	 * ================================================================== */

	/**
	 * Queues a notice for the next page load.
	 *
	 * @param string $type    success|error|warning|info.
	 * @param string $message Message.
	 */
	public static function notice( $type, $message ) {
		$key   = 'local_ads_notices_' . get_current_user_id();
		$queue = get_transient( $key );
		$queue = is_array( $queue ) ? $queue : array();
		$queue[] = array( $type, $message );
		set_transient( $key, $queue, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Prints and clears queued notices.
	 */
	public static function render_notices() {
		$key   = 'local_ads_notices_' . get_current_user_id();
		$queue = get_transient( $key );
		if ( ! is_array( $queue ) ) {
			return;
		}
		delete_transient( $key );
		foreach ( $queue as $item ) {
			list( $type, $message ) = $item;
			$type = in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info';
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
		}
	}

	/**
	 * Redirects back to a plugin page and exits.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Query args.
	 */
	private static function redirect( $page, array $args = array() ) {
		wp_safe_redirect( self::url( $page, $args ) );
		exit;
	}

	/**
	 * Keeps submitted form values so a failed save can be corrected without retyping.
	 *
	 * @param array $data Raw (unslashed) POST.
	 */
	private static function stash_form( array $data ) {
		set_transient( 'local_ads_form_' . get_current_user_id(), $data, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Returns and clears stashed form values.
	 *
	 * @return array|null
	 */
	public static function take_form() {
		$key  = 'local_ads_form_' . get_current_user_id();
		$data = get_transient( $key );
		if ( false === $data ) {
			return null;
		}
		delete_transient( $key );
		return is_array( $data ) ? $data : null;
	}

	/* =====================================================================
	 * Page renderers
	 * ================================================================== */

	/**
	 * Includes a view.
	 *
	 * @param string $view View name.
	 * @param array  $vars Variables.
	 */
	private static function view( $view, array $vars = array() ) {
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		echo '<div class="wrap local-ads-wrap">';
		self::render_notices();
		include LOCAL_ADS_PATH . 'admin/views/' . $view . '.php';
		echo '</div>';
	}

	/** Dashboard page. */
	public static function page_dashboard() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_ANALYTICS );
		Local_Ads_Ads::sync_statuses();
		self::view( 'dashboard' );
	}

	/** Advertisements list page. */
	public static function page_ads() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );
		Local_Ads_Ads::sync_statuses();
		require_once LOCAL_ADS_PATH . 'includes/class-local-ads-list-table.php';
		$table = new Local_Ads_List_Table();
		$table->prepare_items();
		self::view( 'ads', array( 'table' => $table ) );
	}

	/** Add / edit page. */
	public static function page_edit() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ad = $id ? Local_Ads_Ads::get( $id ) : null;
		if ( $id && ! $ad ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Advertisement not found.', 'local-ads' ) . '</p></div></div>';
			return;
		}
		self::view(
			'edit-ad',
			array(
				'ad'    => $ad,
				'stash' => self::take_form(),
			)
		);
	}

	/** Campaigns page. */
	public static function page_campaigns() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );
		self::view( 'campaigns', array( 'stash' => self::take_form() ) );
	}

	/** Media page. */
	public static function page_media() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );
		self::view( 'media' );
	}

	/** Schedule / calendar page. */
	public static function page_schedule() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );
		Local_Ads_Ads::sync_statuses();
		self::view( 'schedule' );
	}

	/** Analytics page. */
	public static function page_analytics() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_ANALYTICS );
		self::view( 'analytics' );
	}

	/** Settings page. */
	public static function page_settings() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_MANAGE );
		self::view( 'settings' );
	}

	/** Tools page. */
	public static function page_tools() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_MANAGE );
		self::view( 'tools' );
	}

	/* =====================================================================
	 * Advertisements
	 * ================================================================== */

	/**
	 * Validates the advertisement form.
	 *
	 * @param array $in Unslashed POST.
	 * @return array { data, errors[] }
	 */
	public static function collect_ad( array $in ) {
		$errors = array();
		$data   = array();

		$data['name'] = isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : '';
		if ( '' === $data['name'] ) {
			$errors[] = __( 'Please enter an advertisement name.', 'local-ads' );
		}
		$data['advertiser']  = isset( $in['advertiser'] ) ? sanitize_text_field( $in['advertiser'] ) : '';
		$data['description'] = isset( $in['description'] ) ? sanitize_textarea_field( $in['description'] ) : '';
		$data['alt_text']    = isset( $in['alt_text'] ) ? sanitize_text_field( $in['alt_text'] ) : '';
		$data['priority']    = isset( $in['priority'] ) ? min( 100, max( 1, (int) $in['priority'] ) ) : 10;
		$data['weight']      = isset( $in['weight'] ) ? min( 100, max( 1, (int) $in['weight'] ) ) : 10;

		$campaign_id         = isset( $in['campaign_id'] ) ? absint( $in['campaign_id'] ) : 0;
		$data['campaign_id'] = ( $campaign_id && Local_Ads_Campaigns::get( $campaign_id ) ) ? $campaign_id : 0;

		$media_id         = isset( $in['media_id'] ) ? absint( $in['media_id'] ) : 0;
		$data['media_id'] = ( $media_id && Local_Ads_Media::get( $media_id ) ) ? $media_id : 0;

		$url = Local_Ads_Security::sanitize_destination( isset( $in['destination_url'] ) ? $in['destination_url'] : '' );
		if ( false === $url ) {
			$errors[]                = __( 'The destination URL is invalid.', 'local-ads' );
			$data['destination_url'] = '';
		} else {
			$data['destination_url'] = $url;
		}

		$start = Local_Ads_Scheduler::local_to_gmt( isset( $in['start_date'] ) ? $in['start_date'] : '', isset( $in['start_time'] ) ? $in['start_time'] : '' );
		$end   = Local_Ads_Scheduler::local_to_gmt( isset( $in['end_date'] ) ? $in['end_date'] : '', isset( $in['end_time'] ) && '' !== $in['end_time'] ? $in['end_time'] : '23:59' );
		if ( false === $start || false === $end ) {
			$errors[] = __( 'Advertisement schedule is invalid.', 'local-ads' ) . ' ' . __( 'Please check the dates and times.', 'local-ads' );
			$start    = null;
			$end      = null;
		} elseif ( $start && $end && strtotime( $end . ' UTC' ) <= strtotime( $start . ' UTC' ) ) {
			$errors[] = __( 'Advertisement schedule is invalid.', 'local-ads' ) . ' ' . __( 'The end must be after the start.', 'local-ads' );
		}
		$data['start_gmt'] = $start;
		$data['end_gmt']   = $end;

		$display = isset( $in['display'] ) && is_array( $in['display'] ) ? $in['display'] : array();
		if ( isset( $display['auto_close_preset'] ) && 'custom' !== $display['auto_close_preset'] ) {
			$display['auto_close_secs'] = absint( $display['auto_close_preset'] );
		}
		$data['display']   = Local_Ads_Ads::sanitize_display( $display );
		$data['targeting'] = Local_Ads_Ads::sanitize_targeting( isset( $in['targeting'] ) ? $in['targeting'] : array() );

		$requested         = isset( $in['status'] ) ? sanitize_key( $in['status'] ) : 'draft';
		$data['requested'] = in_array( $requested, array( 'draft', 'active', 'paused' ), true ) ? $requested : 'draft';

		return array(
			'data'   => $data,
			'errors' => $errors,
		);
	}

	/**
	 * Saves an advertisement.
	 */
	public static function handle_save_ad() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );
		check_admin_referer( 'local_ads_save_ad' );

		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field in collect_ad().
		$id = isset( $in['id'] ) ? absint( $in['id'] ) : 0;
		if ( $id && ! Local_Ads_Ads::get( $id ) ) {
			self::notice( 'error', __( 'Advertisement not found.', 'local-ads' ) );
			self::redirect( 'local-ads-ads' );
		}

		$result = self::collect_ad( $in );
		$data   = $result['data'];
		if ( $result['errors'] ) {
			foreach ( $result['errors'] as $error ) {
				self::notice( 'error', $error );
			}
			self::stash_form( $in );
			self::redirect( 'local-ads-edit', $id ? array( 'id' => $id ) : array() );
		}

		$now      = time();
		$start_ts = $data['start_gmt'] ? strtotime( $data['start_gmt'] . ' UTC' ) : 0;
		$end_ts   = $data['end_gmt'] ? strtotime( $data['end_gmt'] . ' UTC' ) : 0;
		$status   = Local_Ads_Ads::resolve_status( $data['requested'], $start_ts, $end_ts, $now );
		$messages = array();

		if ( in_array( $status, array( 'active', 'scheduled' ), true ) ) {
			if ( ! $data['media_id'] ) {
				$status     = 'draft';
				$messages[] = array( 'error', __( 'Add an image before activating this advertisement. It was saved as a draft.', 'local-ads' ) );
			} else {
				$check = Local_Ads_Scheduler::check_activation( $id, $start_ts, $end_ts );
				if ( is_wp_error( $check ) ) {
					$status     = 'draft';
					$messages[] = array( 'error', $check->get_error_message() . ' ' . __( 'It was saved as a draft.', 'local-ads' ) );
				} else {
					$conflicts = Local_Ads_Scheduler::find_conflicts( $start_ts, $end_ts, $id );
					if ( $conflicts ) {
						/* translators: %s: list of advertisement names. */
						$messages[] = array( 'warning', sprintf( __( 'This advertisement overlaps with another campaign: %s. Concurrent advertisements are allowed, so they will rotate.', 'local-ads' ), implode( ', ', wp_list_pluck( array_slice( $conflicts, 0, 5 ), 'name' ) ) ) );
					}
				}
			}
		}

		$data['status'] = $status;
		$saved_id       = Local_Ads_Ads::save( $data, $id );

		self::notice( 'success', $id ? __( 'Advertisement updated successfully.', 'local-ads' ) : __( 'Advertisement created successfully.', 'local-ads' ) );
		if ( 'scheduled' === $status ) {
			/* translators: %s: start date/time. */
			$messages[] = array( 'info', sprintf( __( 'Advertisement scheduled. It will start displaying on %s.', 'local-ads' ), Local_Ads_Scheduler::format_ts( $start_ts ) ) );
		} elseif ( 'active' === $status ) {
			$messages[] = array( 'info', __( 'Advertisement activated.', 'local-ads' ) );
		} elseif ( 'expired' === $status ) {
			$messages[] = array( 'warning', __( 'Advertisement expired. Its end date is in the past, so it will not display.', 'local-ads' ) );
		} elseif ( 'paused' === $status ) {
			$messages[] = array( 'info', __( 'Advertisement paused.', 'local-ads' ) );
		}
		foreach ( $messages as $m ) {
			self::notice( $m[0], $m[1] );
		}
		self::redirect( 'local-ads-edit', array( 'id' => $saved_id ) );
	}

	/**
	 * Single-ad row actions (GET links with nonces).
	 */
	public static function handle_ad_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below.
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$action = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		// phpcs:enable
		check_admin_referer( 'local_ads_ad_' . $action . '_' . $id );

		if ( 'delete' === $action ) {
			Local_Ads_Security::require_cap( Local_Ads_Security::CAP_DELETE );
			Local_Ads_Ads::delete( $id );
			self::notice( 'success', __( 'Advertisement deleted.', 'local-ads' ) );
			self::redirect( 'local-ads-ads' );
		}

		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );

		if ( 'duplicate' === $action ) {
			$new = Local_Ads_Ads::duplicate( $id );
			if ( is_wp_error( $new ) ) {
				self::notice( 'error', $new->get_error_message() );
				self::redirect( 'local-ads-ads' );
			}
			self::notice( 'success', __( 'Advertisement duplicated as a draft.', 'local-ads' ) );
			self::redirect( 'local-ads-edit', array( 'id' => $new ) );
		}

		$result = Local_Ads_Ads::apply_action( $id, $action );
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
		} else {
			self::notice( 'success', self::status_message( $result ) );
		}
		$back = isset( $_GET['back'] ) && 'edit' === $_GET['back'] ? 'local-ads-edit' : 'local-ads-ads'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		self::redirect( $back, 'local-ads-edit' === $back ? array( 'id' => $id ) : array() );
	}

	/**
	 * Message for a resulting status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function status_message( $status ) {
		switch ( $status ) {
			case 'active':
				return __( 'Advertisement activated.', 'local-ads' );
			case 'scheduled':
				return __( 'Advertisement activated. It is scheduled and will start displaying at its start time.', 'local-ads' );
			case 'paused':
				return __( 'Advertisement paused.', 'local-ads' );
			case 'draft':
				return __( 'Advertisement deactivated. It is now a draft.', 'local-ads' );
			case 'expired':
				return __( 'Advertisement expired.', 'local-ads' );
		}
		return __( 'Advertisement updated successfully.', 'local-ads' );
	}

	/**
	 * Builds a nonce-protected row action URL.
	 *
	 * @param int    $id     Ad ID.
	 * @param string $action Action.
	 * @param array  $extra  Extra args.
	 * @return string
	 */
	public static function ad_action_url( $id, $action, array $extra = array() ) {
		$url = add_query_arg(
			array_merge(
				array(
					'action' => 'local_ads_ad_action',
					'do'     => $action,
					'id'     => (int) $id,
				),
				$extra
			),
			admin_url( 'admin-post.php' )
		);
		return wp_nonce_url( $url, 'local_ads_ad_' . $action . '_' . (int) $id );
	}

	/**
	 * Processes bulk actions and tidies search URLs before the list screen renders
	 * (runs on the screen's load- hook, like WordPress core list screens).
	 */
	public static function process_bulk() {
		if ( ! current_user_can( Local_Ads_Security::CAP_EDIT ) ) {
			return;
		}
		require_once LOCAL_ADS_PATH . 'includes/class-local-ads-list-table.php';
		$table  = new Local_Ads_List_Table();
		$action = $table->current_action();

		if ( ! $action ) {
			// Drop the nonce and referer that the GET search form adds.
			if ( ! empty( $_GET['_wp_http_referer'] ) && isset( $_SERVER['REQUEST_URI'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'action', 'action2' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				exit;
			}
			return;
		}

		check_admin_referer( 'bulk-local_ads' );
		$ids = isset( $_REQUEST['ad'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_REQUEST['ad'] ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! $ids || ! in_array( $action, array( 'activate', 'pause', 'delete' ), true ) ) {
			self::notice( 'warning', __( 'Select at least one advertisement and an action.', 'local-ads' ) );
			self::redirect( 'local-ads-ads' );
		}
		$done   = 0;
		$failed = array();
		if ( 'delete' === $action ) {
			Local_Ads_Security::require_cap( Local_Ads_Security::CAP_DELETE );
			foreach ( $ids as $id ) {
				Local_Ads_Ads::delete( $id );
				++$done;
			}
			/* translators: %d: number of advertisements. */
			self::notice( 'success', sprintf( _n( '%d advertisement deleted.', '%d advertisements deleted.', $done, 'local-ads' ), $done ) );
			self::redirect( 'local-ads-ads' );
		}
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );
		foreach ( $ids as $id ) {
			$result = Local_Ads_Ads::apply_action( $id, $action );
			if ( is_wp_error( $result ) ) {
				$ad       = Local_Ads_Ads::get( $id );
				$failed[] = ( $ad ? $ad->name : '#' . $id ) . ': ' . $result->get_error_message();
			} else {
				++$done;
			}
		}
		if ( $done ) {
			$msg = 'pause' === $action
				/* translators: %d: number of advertisements. */
				? sprintf( _n( '%d advertisement paused.', '%d advertisements paused.', $done, 'local-ads' ), $done )
				/* translators: %d: number of advertisements. */
				: sprintf( _n( '%d advertisement activated.', '%d advertisements activated.', $done, 'local-ads' ), $done );
			self::notice( 'success', $msg );
		}
		foreach ( $failed as $f ) {
			self::notice( 'error', $f );
		}
		self::redirect( 'local-ads-ads' );
	}

	/* =====================================================================
	 * Campaigns
	 * ================================================================== */

	/**
	 * Saves a campaign.
	 */
	public static function handle_save_campaign() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );
		check_admin_referer( 'local_ads_save_campaign' );
		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		$id = isset( $in['id'] ) ? absint( $in['id'] ) : 0;

		$name   = isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : '';
		$status = isset( $in['status'] ) ? sanitize_key( $in['status'] ) : 'active';
		$start  = Local_Ads_Scheduler::local_to_gmt( isset( $in['start_date'] ) ? $in['start_date'] : '', isset( $in['start_time'] ) ? $in['start_time'] : '' );
		$end    = Local_Ads_Scheduler::local_to_gmt( isset( $in['end_date'] ) ? $in['end_date'] : '', ! empty( $in['end_time'] ) ? $in['end_time'] : '23:59' );

		$errors = array();
		if ( '' === $name ) {
			$errors[] = __( 'Please enter a campaign name.', 'local-ads' );
		}
		if ( false === $start || false === $end || ( $start && $end && strtotime( $end . ' UTC' ) <= strtotime( $start . ' UTC' ) ) ) {
			$errors[] = __( 'The campaign schedule is invalid. The end must be after the start.', 'local-ads' );
		}
		if ( $errors ) {
			foreach ( $errors as $e ) {
				self::notice( 'error', $e );
			}
			self::stash_form( $in );
			self::redirect( 'local-ads-campaigns', array( 'edit' => $id ? $id : 'new' ) );
		}

		$saved = Local_Ads_Campaigns::save(
			array(
				'name'        => $name,
				'description' => isset( $in['description'] ) ? sanitize_textarea_field( $in['description'] ) : '',
				'status'      => array_key_exists( $status, Local_Ads_Campaigns::statuses() ) ? $status : 'active',
				'start_gmt'   => $start,
				'end_gmt'     => $end,
			),
			$id
		);
		self::notice( 'success', $id ? __( 'Campaign updated successfully.', 'local-ads' ) : __( 'Campaign created successfully.', 'local-ads' ) );
		self::redirect( 'local-ads-campaigns', array( 'view' => $saved ) );
	}

	/**
	 * Campaign row actions.
	 */
	public static function handle_campaign_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below.
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$action = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		// phpcs:enable
		check_admin_referer( 'local_ads_campaign_' . $action . '_' . $id );
		if ( ! Local_Ads_Campaigns::get( $id ) ) {
			self::notice( 'error', __( 'Campaign not found.', 'local-ads' ) );
			self::redirect( 'local-ads-campaigns' );
		}
		if ( 'delete' === $action ) {
			Local_Ads_Security::require_cap( Local_Ads_Security::CAP_DELETE );
			Local_Ads_Campaigns::delete( $id );
			self::notice( 'success', __( 'Campaign deleted. Its advertisements were kept and are no longer assigned to a campaign.', 'local-ads' ) );
		} else {
			Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );
			$status = 'pause' === $action ? 'paused' : 'active';
			Local_Ads_Campaigns::set_status( $id, $status );
			self::notice( 'success', 'paused' === $status ? __( 'Campaign paused. Its advertisements will not display.', 'local-ads' ) : __( 'Campaign activated.', 'local-ads' ) );
		}
		self::redirect( 'local-ads-campaigns' );
	}

	/**
	 * Builds a nonce-protected campaign action URL.
	 *
	 * @param int    $id     Campaign ID.
	 * @param string $action Action.
	 * @return string
	 */
	public static function campaign_action_url( $id, $action ) {
		$url = add_query_arg(
			array(
				'action' => 'local_ads_campaign_action',
				'do'     => $action,
				'id'     => (int) $id,
			),
			admin_url( 'admin-post.php' )
		);
		return wp_nonce_url( $url, 'local_ads_campaign_' . $action . '_' . (int) $id );
	}

	/* =====================================================================
	 * Media
	 * ================================================================== */

	/**
	 * Upload form on the Media page.
	 */
	public static function handle_media_upload() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );
		check_admin_referer( 'local_ads_media_upload' );
		$file   = isset( $_FILES['local_ads_file'] ) ? $_FILES['local_ads_file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated in handle_upload().
		$result = Local_Ads_Media::handle_upload( $file );
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
		} else {
			self::notice( 'success', __( 'Image uploaded successfully.', 'local-ads' ) );
		}
		self::redirect( 'local-ads-media' );
	}

	/**
	 * Replace an image file while keeping its ID (ads using it update automatically).
	 */
	public static function handle_media_replace() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );
		check_admin_referer( 'local_ads_media_replace' );
		$id     = isset( $_POST['media_id'] ) ? absint( $_POST['media_id'] ) : 0;
		$file   = isset( $_FILES['local_ads_file'] ) ? $_FILES['local_ads_file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated in handle_upload().
		$result = $id ? Local_Ads_Media::handle_upload( $file, $id ) : new WP_Error( 'missing', __( 'The image to replace was not found.', 'local-ads' ) );
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
		} else {
			self::notice( 'success', __( 'Image replaced. Advertisements using it now show the new image.', 'local-ads' ) );
		}
		self::redirect( 'local-ads-media' );
	}

	/**
	 * Delete an image (blocked while a live ad uses it).
	 */
	public static function handle_media_delete() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_DELETE );
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'local_ads_media_delete_' . $id );
		$result = Local_Ads_Media::delete( $id );
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
		} else {
			self::notice( 'success', __( 'Image deleted.', 'local-ads' ) );
		}
		self::redirect( 'local-ads-media' );
	}

	/**
	 * AJAX: list images for the picker.
	 */
	public static function ajax_media_list() {
		check_ajax_referer( 'local_ads_admin', 'nonce' );
		if ( ! current_user_can( Local_Ads_Security::CAP_EDIT ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'local-ads' ) ), 403 );
		}
		wp_send_json_success( array_map( array( 'Local_Ads_Media', 'to_js' ), Local_Ads_Media::all() ) );
	}

	/**
	 * AJAX: upload from the picker.
	 */
	public static function ajax_media_upload() {
		check_ajax_referer( 'local_ads_admin', 'nonce' );
		if ( ! current_user_can( Local_Ads_Security::CAP_EDIT ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'local-ads' ) ), 403 );
		}
		$file   = isset( $_FILES['file'] ) ? $_FILES['file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated in handle_upload().
		$result = Local_Ads_Media::handle_upload( $file );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( Local_Ads_Media::to_js( Local_Ads_Media::get( $result ) ) );
	}

	/* =====================================================================
	 * Schedule checks and preview
	 * ================================================================== */

	/**
	 * AJAX: live conflict detection while editing the schedule.
	 */
	public static function ajax_check_schedule() {
		check_ajax_referer( 'local_ads_admin', 'nonce' );
		if ( ! current_user_can( Local_Ads_Security::CAP_EDIT ) ) {
			wp_send_json_error( array(), 403 );
		}
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by local_to_gmt().
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$start = Local_Ads_Scheduler::local_to_gmt( isset( $_POST['start_date'] ) ? wp_unslash( $_POST['start_date'] ) : '', isset( $_POST['start_time'] ) ? wp_unslash( $_POST['start_time'] ) : '' );
		$end   = Local_Ads_Scheduler::local_to_gmt( isset( $_POST['end_date'] ) ? wp_unslash( $_POST['end_date'] ) : '', ! empty( $_POST['end_time'] ) ? wp_unslash( $_POST['end_time'] ) : '23:59' );
		// phpcs:enable
		if ( false === $start || false === $end ) {
			wp_send_json_success(
				array(
					'level'   => 'error',
					'message' => __( 'Advertisement schedule is invalid.', 'local-ads' ),
				)
			);
		}
		$s = $start ? strtotime( $start . ' UTC' ) : 0;
		$e = $end ? strtotime( $end . ' UTC' ) : 0;
		if ( $s && $e && $e <= $s ) {
			wp_send_json_success(
				array(
					'level'   => 'error',
					'message' => __( 'Advertisement schedule is invalid.', 'local-ads' ) . ' ' . __( 'The end must be after the start.', 'local-ads' ),
				)
			);
		}
		$conflicts = Local_Ads_Scheduler::find_conflicts( $s, $e, $id );
		if ( ! $conflicts ) {
			wp_send_json_success(
				array(
					'level'   => 'ok',
					'message' => __( 'No overlapping advertisements in this period.', 'local-ads' ),
				)
			);
		}
		$items = array();
		foreach ( $conflicts as $c ) {
			$range   = ( $c->start_ts ? Local_Ads_Scheduler::format_ts( $c->start_ts ) : __( 'No start', 'local-ads' ) ) . ' – ' . ( $c->end_ts ? Local_Ads_Scheduler::format_ts( $c->end_ts ) : __( 'No end', 'local-ads' ) );
			$items[] = $c->name . ' (' . $range . ')';
		}
		$check = Local_Ads_Scheduler::check_activation( $id, $s, $e );
		wp_send_json_success(
			array(
				'level'   => is_wp_error( $check ) ? 'error' : 'warning',
				'message' => is_wp_error( $check ) ? $check->get_error_message() : __( 'This advertisement overlaps with another campaign. Concurrent advertisements are allowed, so a warning is shown but activation is permitted.', 'local-ads' ),
				'items'   => $items,
			)
		);
	}

	/**
	 * AJAX: converts the unsaved edit form into the frontend ad format for preview.
	 */
	public static function ajax_preview_data() {
		check_ajax_referer( 'local_ads_admin', 'nonce' );
		if ( ! current_user_can( Local_Ads_Security::CAP_EDIT ) ) {
			wp_send_json_error( array(), 403 );
		}
		$in     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by collect_ad().
		$result = self::collect_ad( $in );
		$data   = $result['data'];
		if ( ! $data['media_id'] ) {
			wp_send_json_error( array( 'message' => __( 'Select an image to preview the advertisement.', 'local-ads' ) ) );
		}
		$ad            = (object) $data;
		$ad->id        = 0;
		$ad->start_ts  = 0;
		$ad->end_ts    = 0;
		$ad->priority  = (int) $data['priority'];
		$ad->weight    = (int) $data['weight'];
		$front         = Local_Ads_Ads::to_frontend( $ad, wp_date( 'Y-m-d' ) );
		wp_send_json_success(
			array(
				'ad'     => $front,
				'global' => Local_Ads_Display::global_options(),
			)
		);
	}

	/**
	 * Standalone preview document rendered inside an iframe. Never tracks.
	 */
	public static function handle_preview() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_EDIT );
		check_admin_referer( 'local_ads_preview' );
		$id    = isset( $_GET['ad_id'] ) ? absint( $_GET['ad_id'] ) : 0;
		$ad    = $id ? Local_Ads_Ads::get( $id ) : null;
		$front = $ad ? Local_Ads_Ads::to_frontend( $ad, wp_date( 'Y-m-d' ) ) : null;
		$data  = array(
			'ad'     => $front,
			'global' => Local_Ads_Display::global_options(),
			'origin' => admin_url(),
		);
		$config = array(
			'preview' => 1,
			'i18n'    => array(
				'close' => __( 'Close advertisement', 'local-ads' ),
				'label' => __( 'Advertisement', 'local-ads' ),
				'opens' => __( '(opens in a new tab)', 'local-ads' ),
			),
		);
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		include LOCAL_ADS_PATH . 'admin/views/preview.php';
		exit;
	}

	/* =====================================================================
	 * Settings, export and tools
	 * ================================================================== */

	/**
	 * Saves settings.
	 */
	public static function handle_save_settings() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_MANAGE );
		check_admin_referer( 'local_ads_settings' );
		$in     = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Local_Ads_Settings::sanitize().
		$tab    = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';
		$errors = array();
		if ( isset( $in['default_auto_close_preset'] ) && 'custom' !== $in['default_auto_close_preset'] ) {
			$in['default_auto_close_secs'] = absint( $in['default_auto_close_preset'] );
		}
		$clean = Local_Ads_Settings::sanitize( $in, $errors );
		Local_Ads_Settings::save( $clean );
		Local_Ads_Ads::refresh_live_flag();
		if ( in_array( 'invalid_folder', $errors, true ) ) {
			self::notice( 'error', __( 'The selected folder is invalid. Use letters, numbers, dashes and underscores only, for example "local-ads" or "ads/2026". The previous folder was kept.', 'local-ads' ) );
		} else {
			$folder = Local_Ads_Media::folder();
			if ( is_wp_error( $folder ) ) {
				self::notice( 'error', $folder->get_error_message() );
			}
		}
		self::notice( 'success', __( 'Settings saved.', 'local-ads' ) );
		self::redirect( 'local-ads-settings', array( 'tab' => $tab ) );
	}

	/**
	 * CSV export.
	 */
	public static function handle_export_csv() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_ANALYTICS );
		check_admin_referer( 'local_ads_export' );
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by range().
		$range = Local_Ads_Analytics::range(
			isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '30d',
			isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
			isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''
		);
		$ad_id = isset( $_GET['ad_id'] ) ? absint( $_GET['ad_id'] ) : 0;
		$cid   = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
		// phpcs:enable
		Local_Ads_Analytics::export_csv( $range['from'], $range['to'], $ad_id, $cid );
	}

	/**
	 * Tools actions.
	 */
	public static function handle_tools() {
		Local_Ads_Security::require_cap( Local_Ads_Security::CAP_MANAGE );
		check_admin_referer( 'local_ads_tools' );
		$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';
		switch ( $tool ) {
			case 'maintenance':
				$r = Local_Ads_Cron::run();
				/* translators: 1: statuses changed, 2: stats rows removed, 3: event rows removed. */
				self::notice( 'success', sprintf( __( 'Maintenance complete. %1$d status changes, %2$d expired daily statistics rows and %3$d old event rows removed.', 'local-ads' ), $r['statuses'], $r['stats'], $r['events'] ) );
				break;
			case 'purge_events':
				global $wpdb;
				$wpdb->query( 'TRUNCATE TABLE ' . Local_Ads_DB::table( 'events' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::notice( 'success', __( 'Event log cleared. Daily statistics and lifetime totals were kept.', 'local-ads' ) );
				break;
			case 'reset_stats':
				if ( empty( $_POST['confirm_reset'] ) ) {
					self::notice( 'error', __( 'Tick the confirmation box to reset all analytics.', 'local-ads' ) );
					break;
				}
				Local_Ads_Analytics::reset_all();
				self::notice( 'success', __( 'All analytics were reset.', 'local-ads' ) );
				break;
			case 'repair':
				Local_Ads_DB::install();
				Local_Ads_Security::add_caps();
				Local_Ads_Cron::unschedule();
				Local_Ads_Cron::schedule();
				$folder = Local_Ads_Media::folder();
				if ( is_wp_error( $folder ) ) {
					self::notice( 'error', $folder->get_error_message() );
				}
				self::notice( 'success', __( 'Database tables, capabilities, cron schedule and the upload folder were checked and repaired.', 'local-ads' ) );
				break;
			default:
				self::notice( 'error', __( 'Unknown action.', 'local-ads' ) );
		}
		self::redirect( 'local-ads-tools' );
	}

	/* =====================================================================
	 * Small view helpers
	 * ================================================================== */

	/**
	 * Status badge markup.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function badge( $status ) {
		$labels = Local_Ads_Ads::statuses() + Local_Ads_Campaigns::statuses();
		$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		return '<span class="la-badge la-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Renders a <select>.
	 *
	 * @param string $name     Field name.
	 * @param array  $options  value => label.
	 * @param mixed  $selected Selected value.
	 * @param array  $attrs    Extra attributes.
	 */
	public static function select( $name, array $options, $selected, array $attrs = array() ) {
		$extra = '';
		foreach ( $attrs as $k => $v ) {
			$extra .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		echo '<select' . ( '' !== $name ? ' name="' . esc_attr( $name ) . '"' : '' ) . $extra . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( (string) $selected, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Inline JSON data for charts.
	 *
	 * @param array $rows Daily rows.
	 * @return string Escaped attribute value.
	 */
	public static function chart_attr( array $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'd' => $row['date'],
				'l' => wp_date( 'j M', strtotime( $row['date'] . ' 12:00:00' ) ),
				'i' => $row['impressions'],
				'c' => $row['clicks'],
				'r' => $row['ctr'],
			);
		}
		return esc_attr( wp_json_encode( $out ) );
	}

	/**
	 * Formats a Y-m-d date for display.
	 *
	 * @param string $date Y-m-d.
	 * @return string
	 */
	public static function date_label( $date ) {
		$dt = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		return $dt ? wp_date( 'd M Y', $dt->getTimestamp() ) : $date;
	}
}
