<?php
/**
 * Analytics view: overall, per-campaign and per-advertisement reporting.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only report filters.
$la_range_key = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '30d';
$la_from      = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
$la_to        = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
$la_ad_id     = isset( $_GET['ad_id'] ) ? absint( $_GET['ad_id'] ) : 0;
$la_cid       = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
// phpcs:enable

$range    = Local_Ads_Analytics::range( $la_range_key, $la_from, $la_to );
$la_ad    = $la_ad_id ? Local_Ads_Ads::get( $la_ad_id ) : null;
$la_camp  = $la_cid ? Local_Ads_Campaigns::get( $la_cid ) : null;
$totals   = Local_Ads_Analytics::totals( $range['from'], $range['to'], $la_ad_id, $la_cid );
$daily    = Local_Ads_Analytics::daily( $range['from'], $range['to'], $la_ad_id, $la_cid );
$by_ad    = $la_ad_id ? array() : Local_Ads_Analytics::by_ad( $range['from'], $range['to'], $la_cid );
$by_camp  = ( $la_ad_id || $la_cid ) ? array() : Local_Ads_Analytics::by_campaign( $range['from'], $range['to'] );
$lifetime = Local_Ads_Analytics::lifetime( $la_ad_id, $la_cid );

$periods = array();
foreach ( array( 'today', 'yesterday', '7d', '30d', 'this_month', 'last_month' ) as $p ) {
	$r             = Local_Ads_Analytics::range( $p );
	$periods[ $p ] = array( 'label' => $r['label'] ) + Local_Ads_Analytics::totals( $r['from'], $r['to'], $la_ad_id, $la_cid );
}
$periods['lifetime'] = array( 'label' => __( 'Lifetime', 'local-ads' ) ) + $lifetime;

$range_choices = array(
	'today'      => __( 'Today', 'local-ads' ),
	'yesterday'  => __( 'Yesterday', 'local-ads' ),
	'7d'         => __( 'Last 7 days', 'local-ads' ),
	'30d'        => __( 'Last 30 days', 'local-ads' ),
	'this_month' => __( 'This month', 'local-ads' ),
	'last_month' => __( 'Last month', 'local-ads' ),
	'custom'     => __( 'Custom range', 'local-ads' ),
);
$ad_options = array( 0 => __( 'All advertisements', 'local-ads' ) );
foreach ( Local_Ads_Ads::query( array( 'per_page' => 500, 'orderby' => 'name', 'order' => 'ASC' ) )['items'] as $a ) {
	$ad_options[ $a->id ] = $a->name;
}
$export_url = wp_nonce_url(
	add_query_arg(
		array(
			'action'      => 'local_ads_export_csv',
			'range'       => 'custom',
			'from'        => $range['from'],
			'to'          => $range['to'],
			'ad_id'       => $la_ad_id,
			'campaign_id' => $la_cid,
		),
		admin_url( 'admin-post.php' )
	),
	'local_ads_export'
);

if ( $la_ad ) {
	/* translators: %s: advertisement name. */
	$title = sprintf( __( 'Analytics: %s', 'local-ads' ), $la_ad->name );
} elseif ( $la_camp ) {
	/* translators: %s: campaign name. */
	$title = sprintf( __( 'Campaign analytics: %s', 'local-ads' ), $la_camp->name );
} else {
	$title = __( 'Analytics', 'local-ads' );
}
?>
<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'local-ads' ); ?></a>
<hr class="wp-header-end" />

<?php if ( $la_ad || $la_camp ) : ?>
	<p>
		<a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-analytics' ) ); ?>">&larr; <?php esc_html_e( 'All analytics', 'local-ads' ); ?></a>
		<?php if ( $la_ad && current_user_can( Local_Ads_Security::CAP_EDIT ) ) : ?>
			&nbsp;|&nbsp; <a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-edit', array( 'id' => $la_ad->id ) ) ); ?>"><?php esc_html_e( 'Edit advertisement', 'local-ads' ); ?></a>
			&nbsp; <?php echo Local_Ads_Admin::badge( $la_ad->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</p>
<?php elseif ( $la_ad_id ) : ?>
	<div class="notice notice-info inline"><p><?php esc_html_e( 'This advertisement has been deleted. Its historical statistics are shown below.', 'local-ads' ); ?></p></div>
<?php endif; ?>

<form method="get" class="la-filters" aria-label="<?php esc_attr_e( 'Analytics filters', 'local-ads' ); ?>">
	<input type="hidden" name="page" value="local-ads-analytics" />
	<label for="la-range"><?php esc_html_e( 'Period', 'local-ads' ); ?></label>
	<?php Local_Ads_Admin::select( 'range', $range_choices, array_key_exists( $la_range_key, $range_choices ) ? $la_range_key : '30d', array( 'id' => 'la-range' ) ); ?>
	<span data-la-when="range" data-la-is="custom" class="la-inline-fields">
		<label for="la-from"><?php esc_html_e( 'From', 'local-ads' ); ?></label> <input type="date" id="la-from" name="from" value="<?php echo esc_attr( $range['from'] ); ?>" />
		<label for="la-to"><?php esc_html_e( 'To', 'local-ads' ); ?></label> <input type="date" id="la-to" name="to" value="<?php echo esc_attr( $range['to'] ); ?>" />
	</span>
	<label for="la-f-ad"><?php esc_html_e( 'Advertisement', 'local-ads' ); ?></label>
	<?php Local_Ads_Admin::select( 'ad_id', $ad_options, $la_ad_id, array( 'id' => 'la-f-ad' ) ); ?>
	<label for="la-f-camp"><?php esc_html_e( 'Campaign', 'local-ads' ); ?></label>
	<?php Local_Ads_Admin::select( 'campaign_id', array( 0 => __( 'All campaigns', 'local-ads' ) ) + Local_Ads_Campaigns::options(), $la_cid, array( 'id' => 'la-f-camp' ) ); ?>
	<button type="submit" class="button"><?php esc_html_e( 'Apply', 'local-ads' ); ?></button>
</form>

<h2 class="la-section-title">
	<?php
	/* translators: 1: range label, 2: from date, 3: to date. */
	echo esc_html( sprintf( __( '%1$s (%2$s – %3$s)', 'local-ads' ), $range['label'], Local_Ads_Admin::date_label( $range['from'] ), Local_Ads_Admin::date_label( $range['to'] ) ) );
	?>
</h2>
<div class="la-cards la-cards--3">
	<div class="la-card"><span class="la-card__label"><?php esc_html_e( 'Impressions', 'local-ads' ); ?></span><span class="la-card__value"><?php echo esc_html( number_format_i18n( $totals['impressions'] ) ); ?></span></div>
	<div class="la-card"><span class="la-card__label"><?php esc_html_e( 'Clicks', 'local-ads' ); ?></span><span class="la-card__value"><?php echo esc_html( number_format_i18n( $totals['clicks'] ) ); ?></span></div>
	<div class="la-card"><span class="la-card__label"><?php esc_html_e( 'CTR', 'local-ads' ); ?></span><span class="la-card__value"><?php echo esc_html( Local_Ads_Analytics::ctr_label( $totals['clicks'], $totals['impressions'] ) ); ?></span></div>
</div>

<div class="la-panel">
	<h2 class="la-panel__title"><?php esc_html_e( 'Daily performance', 'local-ads' ); ?></h2>
	<div class="la-charts" data-la-charts="<?php echo Local_Ads_Admin::chart_attr( $daily ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in chart_attr(). ?>"></div>
</div>

<div class="<?php echo $la_ad_id ? 'la-grid-1' : 'la-grid-2'; ?>">
	<div class="la-panel">
		<h2 class="la-panel__title"><?php esc_html_e( 'Performance by period', 'local-ads' ); ?></h2>
		<table class="widefat striped la-compact">
			<thead><tr><th><?php esc_html_e( 'Period', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'Impressions', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'Clicks', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'CTR', 'local-ads' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $periods as $p ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $p['label'] ); ?></th>
					<td class="num"><?php echo esc_html( number_format_i18n( $p['impressions'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $p['clicks'] ) ); ?></td>
					<td class="num"><?php echo esc_html( Local_Ads_Analytics::ctr_label( $p['clicks'], $p['impressions'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Lifetime totals are kept even after older daily statistics are removed by the retention setting.', 'local-ads' ); ?></p>
	</div>

	<?php if ( ! $la_ad_id ) : ?>
	<div class="la-panel">
		<h2 class="la-panel__title"><?php esc_html_e( 'Advertisement performance', 'local-ads' ); ?></h2>
		<?php if ( $by_ad ) : ?>
			<table class="widefat striped la-compact">
				<thead><tr><th><?php esc_html_e( 'Advertisement', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'Impressions', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'Clicks', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'CTR', 'local-ads' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $by_ad as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-analytics', array( 'ad_id' => $row['ad_id'], 'range' => $la_range_key, 'from' => $range['from'], 'to' => $range['to'] ) ) ); ?>"><?php echo esc_html( $row['name'] ); ?></a>
						<?php if ( $row['campaign'] && ! $la_cid ) : ?><span class="la-muted"> · <?php echo esc_html( $row['campaign'] ); ?></span><?php endif; ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $row['impressions'] ) ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $row['clicks'] ) ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $row['ctr'], 2 ) ); ?>%</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="la-muted"><?php esc_html_e( 'No data for this period.', 'local-ads' ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( $by_camp ) : ?>
	<div class="la-panel">
		<h2 class="la-panel__title"><?php esc_html_e( 'Campaign performance', 'local-ads' ); ?></h2>
		<table class="widefat striped la-compact">
			<thead><tr><th><?php esc_html_e( 'Campaign', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'Impressions', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'Clicks', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'CTR', 'local-ads' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $by_camp as $row ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-analytics', array( 'campaign_id' => $row['campaign_id'], 'range' => $la_range_key, 'from' => $range['from'], 'to' => $range['to'] ) ) ); ?>"><?php echo esc_html( $row['name'] ); ?></a></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $row['impressions'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $row['clicks'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $row['ctr'], 2 ) ); ?>%</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>
</div>

<div class="la-panel">
	<h2 class="la-panel__title"><?php esc_html_e( 'Daily statistics', 'local-ads' ); ?></h2>
	<table class="widefat striped la-compact la-daily-table">
		<thead><tr><th><?php esc_html_e( 'Date', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'Impressions', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'Clicks', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'CTR', 'local-ads' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( array_reverse( $daily ) as $row ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( Local_Ads_Admin::date_label( $row['date'] ) ); ?></th>
				<td class="num"><?php echo esc_html( number_format_i18n( $row['impressions'] ) ); ?></td>
				<td class="num"><?php echo esc_html( number_format_i18n( $row['clicks'] ) ); ?></td>
				<td class="num"><?php echo esc_html( number_format_i18n( $row['ctr'], 2 ) ); ?>%</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
		<tfoot><tr><th scope="row"><?php esc_html_e( 'Total', 'local-ads' ); ?></th><td class="num"><?php echo esc_html( number_format_i18n( $totals['impressions'] ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $totals['clicks'] ) ); ?></td><td class="num"><?php echo esc_html( Local_Ads_Analytics::ctr_label( $totals['clicks'], $totals['impressions'] ) ); ?></td></tr></tfoot>
	</table>
</div>
