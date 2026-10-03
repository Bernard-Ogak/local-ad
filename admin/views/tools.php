<?php
/**
 * Tools view: maintenance actions and system status.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$la_folder = Local_Ads_Media::folder();
$la_next   = wp_next_scheduled( Local_Ads_Cron::HOOK );
$la_last   = (int) get_option( 'local_ads_last_maintenance', 0 );
$la_tables = array();
foreach ( Local_Ads_DB::tables() as $t ) {
	$exists          = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t;
	$la_tables[ $t ] = $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$la_tool = function ( $tool, $label, $description, $class = 'button', $extra = '' ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="la-tool">';
	echo '<input type="hidden" name="action" value="local_ads_tools" /><input type="hidden" name="tool" value="' . esc_attr( $tool ) . '" />';
	wp_nonce_field( 'local_ads_tools' );
	echo '<h3>' . esc_html( $label ) . '</h3><p>' . esc_html( $description ) . '</p>';
	echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built below.
	echo '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
};
?>
<h1><?php esc_html_e( 'Local Ads Tools', 'local-ads' ); ?></h1>

<div class="la-grid-2">
	<div class="la-panel">
		<?php $la_tool( 'maintenance', __( 'Run maintenance now', 'local-ads' ), __( 'Updates scheduled and expired statuses and applies the data-retention settings immediately instead of waiting for WP-Cron.', 'local-ads' ) ); ?>
		<?php $la_tool( 'repair', __( 'Repair installation', 'local-ads' ), __( 'Re-creates missing database tables, administrator capabilities, the cron schedule and the protected upload folder.', 'local-ads' ) ); ?>
		<?php $la_tool( 'purge_events', __( 'Clear event log', 'local-ads' ), __( 'Removes the raw event log. Daily statistics and lifetime totals are kept.', 'local-ads' ) ); ?>
		<?php
		$la_tool(
			'reset_stats',
			__( 'Reset all analytics', 'local-ads' ),
			__( 'Permanently deletes every impression and click statistic and resets lifetime totals to zero. Advertisements are not affected.', 'local-ads' ),
			'button button-link-delete',
			'<p><label><input type="checkbox" name="confirm_reset" value="1" /> ' . esc_html__( 'I understand this cannot be undone.', 'local-ads' ) . '</label></p>'
		);
		?>
	</div>

	<div class="la-panel">
		<h2 class="la-panel__title"><?php esc_html_e( 'System status', 'local-ads' ); ?></h2>
		<table class="widefat striped la-compact">
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'Plugin version', 'local-ads' ); ?></th><td><?php echo esc_html( LOCAL_ADS_VERSION ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Database version', 'local-ads' ); ?></th><td><?php echo esc_html( get_option( 'local_ads_db_version', '—' ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Site timezone', 'local-ads' ); ?></th><td><?php echo esc_html( wp_timezone_string() . ' (' . wp_date( 'Y-m-d H:i' ) . ')' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Upload folder', 'local-ads' ); ?></th><td>
					<?php if ( is_wp_error( $la_folder ) ) : ?>
						<span class="la-error-text"><?php echo esc_html( $la_folder->get_error_message() ); ?></span>
					<?php else : ?>
						<code>uploads/<?php echo esc_html( $la_folder['relative'] ); ?>/</code>
						<?php echo wp_is_writable( $la_folder['path'] ) ? esc_html__( 'Writable', 'local-ads' ) : '<span class="la-error-text">' . esc_html__( 'Not writable', 'local-ads' ) . '</span>'; ?>
					<?php endif; ?>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Maximum upload', 'local-ads' ); ?></th><td><?php echo esc_html( size_format( Local_Ads_Media::max_bytes() ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Next maintenance', 'local-ads' ); ?></th><td><?php echo $la_next ? esc_html( Local_Ads_Scheduler::format_ts( $la_next ) ) : '<span class="la-error-text">' . esc_html__( 'Not scheduled', 'local-ads' ) . '</span>'; ?><?php echo ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? ' ' . esc_html__( '(WP-Cron is disabled; make sure a system cron calls wp-cron.php)', 'local-ads' ) : ''; ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Last maintenance', 'local-ads' ); ?></th><td><?php echo $la_last ? esc_html( Local_Ads_Scheduler::format_ts( $la_last ) ) : esc_html__( 'Never', 'local-ads' ); ?></td></tr>
				<?php foreach ( $la_tables as $t => $rows ) : ?>
					<tr><th scope="row"><code><?php echo esc_html( $t ); ?></code></th><td><?php echo null === $rows ? '<span class="la-error-text">' . esc_html__( 'Missing', 'local-ads' ) . '</span>' : esc_html( sprintf( /* translators: %s: number of rows. */ _n( '%s row', '%s rows', $rows, 'local-ads' ), number_format_i18n( $rows ) ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2 class="la-panel__title"><?php esc_html_e( 'Recent tracking events', 'local-ads' ); ?></h2>
		<?php $la_events = Local_Ads_Analytics::recent_events( 10 ); ?>
		<?php if ( $la_events ) : ?>
			<table class="widefat striped la-compact">
				<thead><tr><th><?php esc_html_e( 'Time', 'local-ads' ); ?></th><th><?php esc_html_e( 'Advertisement', 'local-ads' ); ?></th><th><?php esc_html_e( 'Event', 'local-ads' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $la_events as $e ) : ?>
					<tr>
						<td><?php echo esc_html( Local_Ads_Scheduler::gmt_to_local( $e->created_at, 'Y-m-d H:i:s' ) ); ?></td>
						<td><?php echo esc_html( $e->name ? $e->name : '#' . $e->ad_id ); ?></td>
						<td><?php echo 'click' === $e->event_type ? esc_html__( 'Click', 'local-ads' ) : esc_html__( 'Impression', 'local-ads' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="la-muted"><?php esc_html_e( 'No events recorded yet.', 'local-ads' ); ?></p>
		<?php endif; ?>
	</div>
</div>
