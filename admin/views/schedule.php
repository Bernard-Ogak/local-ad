<?php
/**
 * Schedule view: monthly campaign calendar with overlap counts.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$la_now   = new DateTimeImmutable( 'now', wp_timezone() );
$la_month = isset( $_GET['month'] ) && preg_match( '/^\d{4}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['month'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : $la_now->format( 'Y-m' );
// phpcs:enable
list( $la_y, $la_m ) = array_map( 'intval', explode( '-', $la_month ) );
if ( $la_m < 1 || $la_m > 12 || $la_y < 2000 || $la_y > 2100 ) {
	$la_y = (int) $la_now->format( 'Y' );
	$la_m = (int) $la_now->format( 'n' );
}
$cal    = Local_Ads_Scheduler::month( $la_y, $la_m );
$first  = $cal['first'];
$prev   = $first->modify( '-1 month' )->format( 'Y-m' );
$next   = $first->modify( '+1 month' )->format( 'Y-m' );
$days   = $cal['days'];
$today  = ( $la_now->format( 'Y-m' ) === $first->format( 'Y-m' ) ) ? (int) $la_now->format( 'j' ) : 0;
$max    = $cal['per_day'] ? max( $cal['per_day'] ) : 0;
$limit  = Local_Ads_Settings::get( 'allow_concurrent' ) ? (int) Local_Ads_Settings::get( 'max_concurrent' ) : 1;
$colors = array( '#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948' );
?>
<h1 class="wp-heading-inline"><?php esc_html_e( 'Campaign Schedule', 'local-ads' ); ?></h1>
<a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-edit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Advertisement', 'local-ads' ); ?></a>
<hr class="wp-header-end" />

<div class="la-cal-nav">
	<a class="button" href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-schedule', array( 'month' => $prev ) ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'local-ads' ); ?></a>
	<h2 class="la-cal-nav__title"><?php echo esc_html( wp_date( 'F Y', $first->getTimestamp() ) ); ?></h2>
	<a class="button" href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-schedule', array( 'month' => $next ) ) ); ?>"><?php esc_html_e( 'Next', 'local-ads' ); ?> &rarr;</a>
	<a class="button-link" href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-schedule' ) ); ?>"><?php esc_html_e( 'Today', 'local-ads' ); ?></a>
</div>

<?php if ( $limit > 0 && $max > $limit ) : ?>
	<?php /* translators: 1: peak count, 2: limit. */ ?>
	<div class="notice notice-warning inline"><p><?php echo esc_html( sprintf( __( 'Up to %1$d advertisements overlap this month, above the concurrency limit of %2$d. Only the highest-priority ones will run on those days.', 'local-ads' ), $max, $limit ) ); ?></p></div>
<?php endif; ?>

<div class="la-panel la-cal-wrap">
	<?php if ( ! $cal['rows'] ) : ?>
		<p class="la-muted"><?php esc_html_e( 'No advertisements are scheduled in this month.', 'local-ads' ); ?></p>
	<?php else : ?>
	<div class="la-cal" style="--la-days: <?php echo esc_attr( $days ); ?>;" role="table" aria-label="<?php esc_attr_e( 'Campaign schedule', 'local-ads' ); ?>">
		<div class="la-cal__row la-cal__head" role="row">
			<div class="la-cal__name" role="columnheader"><?php esc_html_e( 'Advertisement', 'local-ads' ); ?></div>
			<div class="la-cal__track" role="presentation">
				<?php for ( $d = 1; $d <= $days; $d++ ) : ?>
					<span class="la-cal__day<?php echo $d === $today ? ' is-today' : ''; ?>" role="columnheader"><?php echo esc_html( $d ); ?></span>
				<?php endfor; ?>
			</div>
		</div>
		<?php foreach ( $cal['rows'] as $row ) : ?>
			<?php
			$ad    = $row['ad'];
			$color = $colors[ $ad->id % count( $colors ) ];
			$range = ( $ad->start_ts ? Local_Ads_Scheduler::format_ts( $ad->start_ts ) : __( 'Immediately', 'local-ads' ) ) . ' – ' . ( $ad->end_ts ? Local_Ads_Scheduler::format_ts( $ad->end_ts ) : __( 'No end date', 'local-ads' ) );
			?>
			<div class="la-cal__row" role="row">
				<div class="la-cal__name" role="rowheader">
					<a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-edit', array( 'id' => $ad->id ) ) ); ?>"><?php echo esc_html( $ad->name ); ?></a>
					<?php echo Local_Ads_Admin::badge( $ad->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( $ad->campaign_name ) : ?><span class="la-muted"><?php echo esc_html( $ad->campaign_name ); ?></span><?php endif; ?>
				</div>
				<div class="la-cal__track" role="cell">
					<span class="la-cal__bar la-cal__bar--<?php echo esc_attr( $ad->status ); ?>" style="grid-column: <?php echo esc_attr( $row['from'] ); ?> / <?php echo esc_attr( $row['to'] + 1 ); ?>; --la-bar: <?php echo esc_attr( $color ); ?>;" title="<?php echo esc_attr( $ad->name . ': ' . $range ); ?>">
						<span class="screen-reader-text"><?php echo esc_html( $range ); ?></span>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
		<div class="la-cal__row la-cal__totals" role="row">
			<div class="la-cal__name" role="rowheader"><?php esc_html_e( 'Running at once', 'local-ads' ); ?></div>
			<div class="la-cal__track" role="presentation">
				<?php for ( $d = 1; $d <= $days; $d++ ) : ?>
					<?php $n = $cal['per_day'][ $d ]; ?>
					<span class="la-cal__count<?php echo $n > 1 ? ' is-overlap' : ''; ?><?php echo ( $limit > 0 && $n > $limit ) ? ' is-over' : ''; ?>" role="cell"><?php echo $n ? esc_html( $n ) : ''; ?></span>
				<?php endfor; ?>
			</div>
		</div>
	</div>
	<p class="description la-cal-legend">
		<span class="la-cal__swatch la-cal__swatch--paused"></span> <?php esc_html_e( 'Hatched bars are paused or draft and will not display.', 'local-ads' ); ?>
		<?php esc_html_e( 'The bottom row counts enabled advertisements per day; highlighted days have overlapping campaigns. Visitors still see only one popup at a time.', 'local-ads' ); ?>
	</p>
	<?php endif; ?>
</div>
