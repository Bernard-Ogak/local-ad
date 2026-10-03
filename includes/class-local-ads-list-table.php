<?php
/**
 * Advertisements list table.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WordPress-native list of advertisements with search, status views, sorting and bulk actions.
 */
class Local_Ads_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'local_ad',
				'plural'   => 'local_ads',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'thumb'       => '<span class="screen-reader-text">' . esc_html__( 'Image', 'local-ads' ) . '</span>',
			'name'        => __( 'Advertisement', 'local-ads' ),
			'status'      => __( 'Status', 'local-ads' ),
			'campaign'    => __( 'Campaign', 'local-ads' ),
			'start'       => __( 'Start', 'local-ads' ),
			'end'         => __( 'End', 'local-ads' ),
			'priority'    => __( 'Priority / Weight', 'local-ads' ),
			'impressions' => __( 'Impressions', 'local-ads' ),
			'clicks'      => __( 'Clicks', 'local-ads' ),
			'ctr'         => __( 'CTR', 'local-ads' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'name'        => array( 'name', false ),
			'status'      => array( 'status', false ),
			'start'       => array( 'start', false ),
			'end'         => array( 'end', false ),
			'priority'    => array( 'priority', true ),
			'impressions' => array( 'impressions', true ),
			'clicks'      => array( 'clicks', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = array(
			'activate' => __( 'Activate', 'local-ads' ),
			'pause'    => __( 'Pause', 'local-ads' ),
		);
		if ( current_user_can( Local_Ads_Security::CAP_DELETE ) ) {
			$actions['delete'] = __( 'Delete', 'local-ads' );
		}
		return $actions;
	}

	/**
	 * Status filter links.
	 *
	 * @return array
	 */
	protected function get_views() {
		$counts  = Local_Ads_Ads::counts();
		$current = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base    = Local_Ads_Admin::url( 'local-ads-ads' );
		$views   = array();
		$labels  = array( '' => __( 'All', 'local-ads' ) ) + array(
			'active'    => __( 'Active', 'local-ads' ),
			'scheduled' => __( 'Scheduled', 'local-ads' ),
			'paused'    => __( 'Paused', 'local-ads' ),
			'expired'   => __( 'Expired', 'local-ads' ),
			'draft'     => __( 'Draft', 'local-ads' ),
		);
		foreach ( $labels as $key => $label ) {
			$count         = '' === $key ? $counts['all'] : $counts[ $key ];
			$url           = '' === $key ? $base : add_query_arg( 'status', $key, $base );
			$views[ '' === $key ? 'all' : $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( $url ),
				$current === $key ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}
		return $views;
	}

	/**
	 * Loads items.
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$per_page = $this->get_items_per_page( 'local_ads_per_page', 20 );
		$result   = Local_Ads_Ads::query(
			array(
				'search'      => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
				'status'      => isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '',
				'campaign_id' => isset( $_REQUEST['campaign_id'] ) ? absint( $_REQUEST['campaign_id'] ) : 0,
				'orderby'     => isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at',
				'order'       => isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc',
				'per_page'    => $per_page,
				'page'        => $this->get_pagenum(),
			)
		);
		// phpcs:enable
		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Message when empty.
	 */
	public function no_items() {
		esc_html_e( 'No advertisements found.', 'local-ads' );
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Ad.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<label class="screen-reader-text" for="la-ad-%1$d">%2$s</label><input type="checkbox" id="la-ad-%1$d" name="ad[]" value="%1$d" />',
			(int) $item->id,
			/* translators: %s: advertisement name. */
			esc_html( sprintf( __( 'Select %s', 'local-ads' ), $item->name ) )
		);
	}

	/**
	 * Thumbnail column.
	 *
	 * @param object $item Ad.
	 * @return string
	 */
	protected function column_thumb( $item ) {
		$media = Local_Ads_Media::get( $item->media_id );
		if ( ! $media ) {
			return '<span class="la-thumb la-thumb--empty" aria-hidden="true"></span>';
		}
		return '<img class="la-thumb" src="' . esc_url( Local_Ads_Media::url( $media ) ) . '" alt="" loading="lazy" />';
	}

	/**
	 * Name column with row actions.
	 *
	 * @param object $item Ad.
	 * @return string
	 */
	protected function column_name( $item ) {
		$edit    = Local_Ads_Admin::url( 'local-ads-edit', array( 'id' => $item->id ) );
		$actions = array(
			'edit'      => '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'local-ads' ) . '</a>',
			'analytics' => '<a href="' . esc_url( Local_Ads_Admin::url( 'local-ads-analytics', array( 'ad_id' => $item->id ) ) ) . '">' . esc_html__( 'View Analytics', 'local-ads' ) . '</a>',
		);
		if ( $item->media_id ) {
			$actions['preview'] = '<a href="#" class="la-preview-link" data-ad-id="' . esc_attr( $item->id ) . '">' . esc_html__( 'Preview', 'local-ads' ) . '</a>';
		}
		$actions['duplicate'] = '<a href="' . esc_url( Local_Ads_Admin::ad_action_url( $item->id, 'duplicate' ) ) . '">' . esc_html__( 'Duplicate', 'local-ads' ) . '</a>';

		if ( in_array( $item->status, array( 'draft', 'paused' ), true ) ) {
			$actions['activate'] = '<a href="' . esc_url( Local_Ads_Admin::ad_action_url( $item->id, 'activate' ) ) . '">' . esc_html__( 'Activate', 'local-ads' ) . '</a>';
		}
		if ( 'scheduled' === $item->status ) {
			$actions['start_now'] = '<a href="' . esc_url( Local_Ads_Admin::ad_action_url( $item->id, 'start_now' ) ) . '">' . esc_html__( 'Start now', 'local-ads' ) . '</a>';
		}
		if ( in_array( $item->status, array( 'active', 'scheduled' ), true ) ) {
			$actions['pause'] = '<a href="' . esc_url( Local_Ads_Admin::ad_action_url( $item->id, 'pause' ) ) . '">' . esc_html__( 'Pause', 'local-ads' ) . '</a>';
		}
		if ( 'draft' !== $item->status ) {
			$actions['deactivate'] = '<a href="' . esc_url( Local_Ads_Admin::ad_action_url( $item->id, 'deactivate' ) ) . '">' . esc_html__( 'Deactivate', 'local-ads' ) . '</a>';
		}
		if ( current_user_can( Local_Ads_Security::CAP_DELETE ) ) {
			$actions['delete'] = '<a href="' . esc_url( Local_Ads_Admin::ad_action_url( $item->id, 'delete' ) ) . '" class="submitdelete la-confirm" data-confirm="' . esc_attr__( 'Delete this advertisement? Its analytics history is kept.', 'local-ads' ) . '">' . esc_html__( 'Delete', 'local-ads' ) . '</a>';
		}

		$out = '<strong><a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $item->name ) . '</a></strong>';
		if ( $item->advertiser ) {
			$out .= '<div class="la-muted">' . esc_html( $item->advertiser ) . '</div>';
		}
		return $out . $this->row_actions( $actions );
	}

	/**
	 * Default column output.
	 *
	 * @param object $item        Ad.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'status':
				return Local_Ads_Admin::badge( $item->status );
			case 'campaign':
				return $item->campaign_name ? esc_html( $item->campaign_name ) : '<span class="la-muted">&mdash;</span>';
			case 'start':
				return $item->start_ts ? esc_html( Local_Ads_Scheduler::format_ts( $item->start_ts ) ) : '<span class="la-muted">' . esc_html__( 'Immediately', 'local-ads' ) . '</span>';
			case 'end':
				return $item->end_ts ? esc_html( Local_Ads_Scheduler::format_ts( $item->end_ts ) ) : '<span class="la-muted">' . esc_html__( 'No end date', 'local-ads' ) . '</span>';
			case 'priority':
				return esc_html( $item->priority . ' / ' . $item->weight );
			case 'impressions':
				return esc_html( number_format_i18n( (int) $item->impressions ) );
			case 'clicks':
				return esc_html( number_format_i18n( (int) $item->clicks ) );
			case 'ctr':
				return esc_html( Local_Ads_Analytics::ctr_label( $item->clicks, $item->impressions ) );
		}
		return '';
	}
}
