<?php
/**
 * Dashboard view.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$counts   = Local_Ads_Ads::counts();
$lifetime = Local_Ads_Analytics::lifetime();
$today    = Local_Ads_Analytics::range( 'today' );
$today_t  = Local_Ads_Analytics::totals( $today['from'], $today['to'] );
$range14  = Local_Ads_Analytics::range( 'custom', wp_date( 'Y-m-d', time() - 13 * DAY_IN_SECONDS ), wp_date( 'Y-m-d' ) );
$daily14  = Local_Ads_Analytics::daily( $range14['from'], $range14['to'] );
$running  = Local_Ads_Ads::live_ads();
$ads_tbl  = Local_Ads_DB::table( 'ads' );
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$recent  = array_map( array( 'Local_Ads_Ads', 'hydrate' ), (array) $wpdb->get_results( "SELECT * FROM {$ads_tbl} ORDER BY created_at DESC, id DESC LIMIT 5" ) );
$expired = array_map( array( 'Local_Ads_Ads', 'hydrate' ), (array) $wpdb->get_results( "SELECT * FROM {$ads_tbl} WHERE status = 'expired' ORDER BY end_gmt DESC LIMIT 5" ) );
$top     = $wpdb->get_results( "SELECT id, name, impressions, clicks FROM {$ads_tbl} WHERE impressions > 0 ORDER BY clicks DESC, impressions DESC LIMIT 5" );
// phpcs:enable
$can_edit = current_user_can( Local_Ads_Security::CAP_EDIT );
?>
<h1 class="wp-heading-inline"><?php esc_html_e( 'Local Ads Dashboard', 'local-ads' ); ?></h1>
<?php if ( $can_edit ) : ?>
	<a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-edit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Advertisement', 'local-ads' ); ?></a>
<?php endif; ?>
<hr class="wp-header-end" />

<?php if ( ! Local_Ads_Settings::get( 'enabled' ) ) : ?>
	<div class="notice notice-warning inline"><p>
		<?php esc_html_e( 'Local Ads is currently disabled, so no advertisements are shown to visitors.', 'local-ads' ); ?>
		<?php if ( current_user_can( Local_Ads_Security::CAP_MANAGE ) ) : ?>
			<a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-settings' ) ); ?>"><?php esc_html_e( 'Open settings', 'local-ads' ); ?></a>
		<?php endif; ?>
	</p></div>
<?php endif; ?>

<h2 class="la-section-title"><?php esc_html_e( 'Advertisements', 'local-ads' ); ?></h2>
<div class="la-cards">
	<?php
	$status_cards = array(
		''          => __( 'Total ads', 'local-ads' ),
		'active'    => __( 'Active', 'local-ads' ),
		'scheduled' => __( 'Scheduled', 'local-ads' ),
		'paused'    => __( 'Paused', 'local-ads' ),
		'expired'   => __( 'Expired', 'local-ads' ),
		'draft'     => __( 'Draft', 'local-ads' ),
	);
	foreach ( $status_cards as $key => $label ) :
		$n   = '' === $key ? $counts['all'] : $counts[ $key ];
		$url = Local_Ads_Admin::url( 'local-ads-ads', '' === $key ? array() : array( 'status' => $key ) );
		?>
		<a class="la-card la-card--link" href="<?php echo esc_url( $url ); ?>">
			<span class="la-card__label"><?php echo esc_html( $label ); ?></span>
			<span class="la-card__value"><?php echo esc_html( number_format_i18n( $n ) ); ?></span>
		</a>
	<?php endforeach; ?>
</div>

<div class="la-grid-2">
	<div>
		<h2 class="la-section-title"><?php esc_html_e( 'Today', 'local-ads' ); ?></h2>
		<div class="la-cards la-cards--3">
			<div class="la-card"><span class="la-card__label"><?php esc_html_e( 'Impressions', 'local-ads' ); ?></span><span class="la-card__value"><?php echo esc_html( number_format_i18n( $today_t['impressions'] ) ); ?></span></div>
			<div class="la-card"><span class="la-card__label"><?php esc_html_e( 'Clicks', 'local-ads' ); ?></span><span class="la-card__value"><?php echo esc_html( number_format_i18n( $today_t['clicks'] ) ); ?></span></div>
			<div class="la-card"><span class="la-card__label"><?php esc_html_e( 'CTR', 'local-ads' ); ?></span><span class="la-card__value"><?php echo esc_html( Local_Ads_Analytics::ctr_label( $today_t['clicks'], $today_t['impressions'] ) ); ?></span></div>
		</div>
	</div>
	<div>
		<h2 class="la-section-title"><?php esc_html_e( 'Lifetime performance', 'local-ads' ); ?></h2>
		<div class="la-cards la-cards--3">
			<div class="la-card"><span class="la-card__label"><?php esc_html_e( 'Total impressions', 'local-ads' ); ?></span><span class="la-card__value"><?php echo esc_html( number_format_i18n( $lifetime['impressions'] ) ); ?></span></div>
			<div class="la-card"><span class="la-card__label"><?php esc_html_e( 'Total clicks', 'local-ads' ); ?></span><span class="la-card__value"><?php echo esc_html( number_format_i18n( $lifetime['clicks'] ) ); ?></span></div>
			<div class="la-card"><span class="la-card__label"><?php esc_html_e( 'Overall CTR', 'local-ads' ); ?></span><span class="la-card__value"><?php echo esc_html( Local_Ads_Analytics::ctr_label( $lifetime['clicks'], $lifetime['impressions'] ) ); ?></span></div>
		</div>
	</div>
</div>

<div class="la-panel">
	<h2 class="la-panel__title"><?php esc_html_e( 'Last 14 days', 'local-ads' ); ?></h2>
	<div class="la-charts" data-la-charts="<?php echo Local_Ads_Admin::chart_attr( $daily14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in chart_attr(). ?>"></div>
</div>

<div class="la-grid-2">
	<div class="la-panel">
		<h2 class="la-panel__title"><?php esc_html_e( 'Currently running', 'local-ads' ); ?></h2>
		<?php if ( $running ) : ?>
			<table class="widefat striped la-compact">
				<thead><tr><th><?php esc_html_e( 'Advertisement', 'local-ads' ); ?></th><th><?php esc_html_e( 'Ends', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'Priority', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'Weight', 'local-ads' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $running as $ad ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-edit', array( 'id' => $ad->id ) ) ); ?>"><?php echo esc_html( $ad->name ); ?></a></td>
						<td><?php echo $ad->end_ts ? esc_html( Local_Ads_Scheduler::format_ts( $ad->end_ts ) ) : esc_html__( 'No end date', 'local-ads' ); ?></td>
						<td class="num"><?php echo esc_html( $ad->priority ); ?></td>
						<td class="num"><?php echo esc_html( $ad->weight ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( count( $running ) > 1 ) : ?>
				<p class="description"><?php esc_html_e( 'These campaigns run concurrently. Each visitor still sees only one popup at a time, chosen by the rotation method.', 'local-ads' ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<p class="la-muted"><?php esc_html_e( 'No advertisements are running right now.', 'local-ads' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="la-panel">
		<h2 class="la-panel__title"><?php esc_html_e( 'Top-performing ads', 'local-ads' ); ?></h2>
		<?php if ( $top ) : ?>
			<table class="widefat striped la-compact">
				<thead><tr><th><?php esc_html_e( 'Advertisement', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'Impressions', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'Clicks', 'local-ads' ); ?></th><th class="num"><?php esc_html_e( 'CTR', 'local-ads' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $top as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-analytics', array( 'ad_id' => $row->id ) ) ); ?>"><?php echo esc_html( $row->name ); ?></a></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $row->impressions ) ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $row->clicks ) ); ?></td>
						<td class="num"><?php echo esc_html( Local_Ads_Analytics::ctr_label( $row->clicks, $row->impressions ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="la-muted"><?php esc_html_e( 'No performance data yet.', 'local-ads' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="la-panel">
		<h2 class="la-panel__title"><?php esc_html_e( 'Recently created', 'local-ads' ); ?></h2>
		<?php if ( $recent ) : ?>
			<ul class="la-list">
				<?php foreach ( $recent as $ad ) : ?>
					<li>
						<a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-edit', array( 'id' => $ad->id ) ) ); ?>"><?php echo esc_html( $ad->name ); ?></a>
						<?php echo Local_Ads_Admin::badge( $ad->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="la-muted"><?php echo esc_html( Local_Ads_Scheduler::gmt_to_local( $ad->created_at, get_option( 'date_format' ) ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="la-muted"><?php esc_html_e( 'No advertisements yet.', 'local-ads' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="la-panel">
		<h2 class="la-panel__title"><?php esc_html_e( 'Recently expired', 'local-ads' ); ?></h2>
		<?php if ( $expired ) : ?>
			<ul class="la-list">
				<?php foreach ( $expired as $ad ) : ?>
					<li>
						<a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-edit', array( 'id' => $ad->id ) ) ); ?>"><?php echo esc_html( $ad->name ); ?></a>
						<span class="la-muted"><?php echo esc_html( Local_Ads_Scheduler::format_ts( $ad->end_ts ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="la-muted"><?php esc_html_e( 'No expired advertisements.', 'local-ads' ); ?></p>
		<?php endif; ?>
	</div>
</div>
