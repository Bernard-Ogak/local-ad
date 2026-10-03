<?php
/**
 * Campaigns view: list and add/edit form.
 *
 * @package LocalAds
 * @var array|null $stash
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$la_edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';

if ( '' !== $la_edit ) :
	$campaign = 'new' === $la_edit ? null : Local_Ads_Campaigns::get( absint( $la_edit ) );
	if ( 'new' !== $la_edit && ! $campaign ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Campaign not found.', 'local-ads' ) . '</p></div>';
		return;
	}
	if ( $stash ) {
		$c = array(
			'name'        => isset( $stash['name'] ) ? (string) $stash['name'] : '',
			'description' => isset( $stash['description'] ) ? (string) $stash['description'] : '',
			'status'      => isset( $stash['status'] ) ? sanitize_key( $stash['status'] ) : 'active',
			'start_date'  => isset( $stash['start_date'] ) ? (string) $stash['start_date'] : '',
			'start_time'  => isset( $stash['start_time'] ) ? (string) $stash['start_time'] : '',
			'end_date'    => isset( $stash['end_date'] ) ? (string) $stash['end_date'] : '',
			'end_time'    => isset( $stash['end_time'] ) ? (string) $stash['end_time'] : '',
		);
	} elseif ( $campaign ) {
		$c = array(
			'name'        => $campaign->name,
			'description' => (string) $campaign->description,
			'status'      => $campaign->status,
			'start_date'  => Local_Ads_Scheduler::gmt_to_local( $campaign->start_gmt, 'Y-m-d' ),
			'start_time'  => Local_Ads_Scheduler::gmt_to_local( $campaign->start_gmt, 'H:i' ),
			'end_date'    => Local_Ads_Scheduler::gmt_to_local( $campaign->end_gmt, 'Y-m-d' ),
			'end_time'    => Local_Ads_Scheduler::gmt_to_local( $campaign->end_gmt, 'H:i' ),
		);
	} else {
		$c = array_fill_keys( array( 'name', 'description', 'start_date', 'start_time', 'end_date', 'end_time' ), '' );
		$c['status'] = 'active';
	}
	?>
	<h1><?php echo $campaign ? esc_html__( 'Edit Campaign', 'local-ads' ) : esc_html__( 'Add New Campaign', 'local-ads' ); ?></h1>
	<p><a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-campaigns' ) ); ?>">&larr; <?php esc_html_e( 'All campaigns', 'local-ads' ); ?></a></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="la-panel la-form" novalidate>
		<input type="hidden" name="action" value="local_ads_save_campaign" />
		<input type="hidden" name="id" value="<?php echo esc_attr( $campaign ? $campaign->id : 0 ); ?>" />
		<?php wp_nonce_field( 'local_ads_save_campaign' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="la-c-name"><?php esc_html_e( 'Name', 'local-ads' ); ?> <span class="la-req" aria-hidden="true">*</span></label></th>
				<td><input type="text" id="la-c-name" name="name" class="regular-text" value="<?php echo esc_attr( $c['name'] ); ?>" required aria-required="true" maxlength="200" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="la-c-desc"><?php esc_html_e( 'Description', 'local-ads' ); ?></label></th>
				<td><textarea id="la-c-desc" name="description" rows="3" class="large-text"><?php echo esc_textarea( $c['description'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="la-c-status"><?php esc_html_e( 'Status', 'local-ads' ); ?></label></th>
				<td><?php Local_Ads_Admin::select( 'status', Local_Ads_Campaigns::statuses(), $c['status'], array( 'id' => 'la-c-status' ) ); ?>
				<p class="description"><?php esc_html_e( 'Advertisements in a paused or draft campaign do not display, whatever their own status.', 'local-ads' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="la-c-start"><?php esc_html_e( 'Start', 'local-ads' ); ?></label></th>
				<td class="la-inline-fields"><input type="date" id="la-c-start" name="start_date" value="<?php echo esc_attr( $c['start_date'] ); ?>" /> <input type="time" name="start_time" value="<?php echo esc_attr( $c['start_time'] ); ?>" aria-label="<?php esc_attr_e( 'Start time', 'local-ads' ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="la-c-end"><?php esc_html_e( 'End', 'local-ads' ); ?></label></th>
				<td class="la-inline-fields"><input type="date" id="la-c-end" name="end_date" value="<?php echo esc_attr( $c['end_date'] ); ?>" /> <input type="time" name="end_time" value="<?php echo esc_attr( $c['end_time'] ); ?>" aria-label="<?php esc_attr_e( 'End time', 'local-ads' ); ?>" />
				<p class="description"><?php esc_html_e( 'Optional. Ads in this campaign only display inside both the campaign dates and their own schedule.', 'local-ads' ); ?></p></td>
			</tr>
		</table>
		<?php submit_button( $campaign ? __( 'Update campaign', 'local-ads' ) : __( 'Create campaign', 'local-ads' ) ); ?>
	</form>
	<?php
	return;
endif;

$la_campaigns = Local_Ads_Campaigns::all();
?>
<h1 class="wp-heading-inline"><?php esc_html_e( 'Campaigns', 'local-ads' ); ?></h1>
<a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-campaigns', array( 'edit' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'local-ads' ); ?></a>
<hr class="wp-header-end" />
<p class="description"><?php esc_html_e( 'Campaigns group related advertisements, can be paused as a whole and report aggregate analytics.', 'local-ads' ); ?></p>

<table class="wp-list-table widefat fixed striped">
	<thead>
		<tr>
			<th scope="col" class="column-primary"><?php esc_html_e( 'Campaign', 'local-ads' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Status', 'local-ads' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Schedule', 'local-ads' ); ?></th>
			<th scope="col" class="num"><?php esc_html_e( 'Ads', 'local-ads' ); ?></th>
			<th scope="col" class="num"><?php esc_html_e( 'Impressions', 'local-ads' ); ?></th>
			<th scope="col" class="num"><?php esc_html_e( 'Clicks', 'local-ads' ); ?></th>
			<th scope="col" class="num"><?php esc_html_e( 'CTR', 'local-ads' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php if ( ! $la_campaigns ) : ?>
		<tr><td colspan="7"><?php esc_html_e( 'No campaigns yet.', 'local-ads' ); ?></td></tr>
	<?php endif; ?>
	<?php foreach ( $la_campaigns as $camp ) : ?>
		<?php
		$start = Local_Ads_Scheduler::gmt_to_local( $camp->start_gmt, get_option( 'date_format' ) );
		$end   = Local_Ads_Scheduler::gmt_to_local( $camp->end_gmt, get_option( 'date_format' ) );
		?>
		<tr>
			<td class="column-primary">
				<strong><a class="row-title" href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-campaigns', array( 'edit' => $camp->id ) ) ); ?>"><?php echo esc_html( $camp->name ); ?></a></strong>
				<?php if ( $camp->description ) : ?><div class="la-muted"><?php echo esc_html( wp_trim_words( $camp->description, 20 ) ); ?></div><?php endif; ?>
				<div class="row-actions">
					<span><a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-campaigns', array( 'edit' => $camp->id ) ) ); ?>"><?php esc_html_e( 'Edit', 'local-ads' ); ?></a> | </span>
					<span><a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-analytics', array( 'campaign_id' => $camp->id ) ) ); ?>"><?php esc_html_e( 'View Analytics', 'local-ads' ); ?></a> | </span>
					<span><a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-ads', array( 'campaign_id' => $camp->id ) ) ); ?>"><?php esc_html_e( 'View ads', 'local-ads' ); ?></a> | </span>
					<span><a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-edit', array( 'campaign_id' => $camp->id ) ) ); ?>"><?php esc_html_e( 'Add ad', 'local-ads' ); ?></a> | </span>
					<?php if ( 'active' === $camp->status ) : ?>
						<span><a href="<?php echo esc_url( Local_Ads_Admin::campaign_action_url( $camp->id, 'pause' ) ); ?>"><?php esc_html_e( 'Pause', 'local-ads' ); ?></a></span>
					<?php else : ?>
						<span><a href="<?php echo esc_url( Local_Ads_Admin::campaign_action_url( $camp->id, 'activate' ) ); ?>"><?php esc_html_e( 'Activate', 'local-ads' ); ?></a></span>
					<?php endif; ?>
					<?php if ( current_user_can( Local_Ads_Security::CAP_DELETE ) ) : ?>
						<span class="trash"> | <a class="submitdelete la-confirm" data-confirm="<?php esc_attr_e( 'Delete this campaign? Its advertisements are kept.', 'local-ads' ); ?>" href="<?php echo esc_url( Local_Ads_Admin::campaign_action_url( $camp->id, 'delete' ) ); ?>"><?php esc_html_e( 'Delete', 'local-ads' ); ?></a></span>
					<?php endif; ?>
				</div>
			</td>
			<td><?php echo Local_Ads_Admin::badge( $camp->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			<td><?php echo esc_html( ( $start ? $start : __( 'Any time', 'local-ads' ) ) . ' – ' . ( $end ? $end : __( 'No end', 'local-ads' ) ) ); ?></td>
			<td class="num"><?php echo esc_html( number_format_i18n( $camp->ad_count ) ); ?></td>
			<td class="num"><?php echo esc_html( number_format_i18n( $camp->impressions ) ); ?></td>
			<td class="num"><?php echo esc_html( number_format_i18n( $camp->clicks ) ); ?></td>
			<td class="num"><?php echo esc_html( Local_Ads_Analytics::ctr_label( $camp->clicks, $camp->impressions ) ); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
