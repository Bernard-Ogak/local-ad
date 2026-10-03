<?php
/**
 * Add / edit advertisement view.
 *
 * @package LocalAds
 * @var object|null $ad
 * @var array|null  $stash
 */

defined( 'ABSPATH' ) || exit;

$is_new = ! $ad;
$s      = Local_Ads_Settings::all();

if ( $stash ) {
	// Values from a submission that failed validation.
	$f = array(
		'name'            => isset( $stash['name'] ) ? (string) $stash['name'] : '',
		'advertiser'      => isset( $stash['advertiser'] ) ? (string) $stash['advertiser'] : '',
		'description'     => isset( $stash['description'] ) ? (string) $stash['description'] : '',
		'campaign_id'     => isset( $stash['campaign_id'] ) ? absint( $stash['campaign_id'] ) : 0,
		'status'          => isset( $stash['status'] ) ? sanitize_key( $stash['status'] ) : 'draft',
		'priority'        => isset( $stash['priority'] ) ? absint( $stash['priority'] ) : 10,
		'weight'          => isset( $stash['weight'] ) ? absint( $stash['weight'] ) : 10,
		'media_id'        => isset( $stash['media_id'] ) ? absint( $stash['media_id'] ) : 0,
		'alt_text'        => isset( $stash['alt_text'] ) ? (string) $stash['alt_text'] : '',
		'destination_url' => isset( $stash['destination_url'] ) ? (string) $stash['destination_url'] : '',
		'start_date'      => isset( $stash['start_date'] ) ? (string) $stash['start_date'] : '',
		'start_time'      => isset( $stash['start_time'] ) ? (string) $stash['start_time'] : '',
		'end_date'        => isset( $stash['end_date'] ) ? (string) $stash['end_date'] : '',
		'end_time'        => isset( $stash['end_time'] ) ? (string) $stash['end_time'] : '',
	);
	$display   = Local_Ads_Ads::sanitize_display( isset( $stash['display'] ) ? $stash['display'] : array() );
	$targeting = Local_Ads_Ads::sanitize_targeting( isset( $stash['targeting'] ) ? $stash['targeting'] : array() );
} elseif ( $ad ) {
	$f = array(
		'name'            => $ad->name,
		'advertiser'      => $ad->advertiser,
		'description'     => (string) $ad->description,
		'campaign_id'     => (int) $ad->campaign_id,
		'status'          => in_array( $ad->status, array( 'active', 'scheduled', 'expired' ), true ) ? 'active' : $ad->status,
		'priority'        => $ad->priority,
		'weight'          => $ad->weight,
		'media_id'        => $ad->media_id,
		'alt_text'        => $ad->alt_text,
		'destination_url' => (string) $ad->destination_url,
		'start_date'      => Local_Ads_Scheduler::gmt_to_local( $ad->start_gmt, 'Y-m-d' ),
		'start_time'      => Local_Ads_Scheduler::gmt_to_local( $ad->start_gmt, 'H:i' ),
		'end_date'        => Local_Ads_Scheduler::gmt_to_local( $ad->end_gmt, 'Y-m-d' ),
		'end_time'        => Local_Ads_Scheduler::gmt_to_local( $ad->end_gmt, 'H:i' ),
	);
	$display   = $ad->display;
	$targeting = $ad->targeting;
} else {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$f = array(
		'name'            => '',
		'advertiser'      => '',
		'description'     => '',
		'campaign_id'     => isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0,
		'status'          => 'active',
		'priority'        => 10,
		'weight'          => 10,
		'media_id'        => isset( $_GET['media_id'] ) ? absint( $_GET['media_id'] ) : 0,
		'alt_text'        => '',
		'destination_url' => '',
		'start_date'      => '',
		'start_time'      => '',
		'end_date'        => '',
		'end_time'        => '',
	);
	// phpcs:enable
	$display   = Local_Ads_Ads::default_display();
	$targeting = Local_Ads_Ads::default_targeting();
}

$media      = $f['media_id'] ? Local_Ads_Media::get( $f['media_id'] ) : null;
$campaigns  = Local_Ads_Campaigns::options();
$presets    = Local_Ads_Settings::choices( 'auto_close_presets' );
$ac_preset  = in_array( (int) $display['auto_close_secs'], $presets, true ) ? (string) $display['auto_close_secs'] : 'custom';
$tz_label   = wp_timezone_string();
$all_pages  = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1000, 'orderby' => 'title', 'order' => 'ASC' ) );
$post_types = post_type_exists( 'product' ) ? array( 'post', 'product' ) : array( 'post' );
$all_posts  = get_posts( array( 'post_type' => $post_types, 'post_status' => 'publish', 'numberposts' => 500, 'orderby' => 'date', 'order' => 'DESC' ) );
$has_woo    = post_type_exists( 'product' );

/**
 * Prints a filterable checkbox list of posts.
 *
 * @param string    $name     Field name.
 * @param WP_Post[] $items    Posts.
 * @param int[]     $selected Selected IDs.
 * @param string    $label    Accessible label.
 */
$la_checklist = function ( $name, $items, $selected, $label ) {
	$selected = array_map( 'intval', (array) $selected );
	$list_id  = 'la-list-' . md5( $name );
	echo '<div class="la-checklist">';
	echo '<input type="search" class="la-filter regular-text" data-target="' . esc_attr( $list_id ) . '" placeholder="' . esc_attr__( 'Filter…', 'local-ads' ) . '" aria-label="' . esc_attr( $label ) . '" />';
	echo '<div class="la-checklist__items" id="' . esc_attr( $list_id ) . '">';
	if ( ! $items ) {
		echo '<p class="la-muted">' . esc_html__( 'Nothing published yet.', 'local-ads' ) . '</p>';
	}
	foreach ( $items as $item ) {
		$title = '' !== $item->post_title ? $item->post_title : __( '(no title)', 'local-ads' );
		$type  = 'product' === $item->post_type ? ' <span class="la-muted">(' . esc_html__( 'product', 'local-ads' ) . ')</span>' : '';
		echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $item->ID ) . '"' . checked( in_array( (int) $item->ID, $selected, true ), true, false ) . ' /> ' . esc_html( $title ) . $type . ' <span class="la-muted">#' . esc_html( $item->ID ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $type is escaped.
	}
	echo '</div></div>';
};
?>
<h1 class="wp-heading-inline"><?php echo $is_new ? esc_html__( 'Add New Advertisement', 'local-ads' ) : esc_html__( 'Edit Advertisement', 'local-ads' ); ?></h1>
<?php if ( ! $is_new ) : ?>
	<a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-edit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'local-ads' ); ?></a>
<?php endif; ?>
<hr class="wp-header-end" />

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="la-ad-form" class="la-form" novalidate>
	<input type="hidden" name="action" value="local_ads_save_ad" />
	<input type="hidden" name="id" value="<?php echo esc_attr( $is_new ? 0 : $ad->id ); ?>" />
	<?php wp_nonce_field( 'local_ads_save_ad' ); ?>

	<div class="la-edit">
		<div class="la-edit__main">

			<div class="la-panel">
				<h2 class="la-panel__title"><?php esc_html_e( 'Advertisement details', 'local-ads' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="la-name"><?php esc_html_e( 'Advertisement name', 'local-ads' ); ?> <span class="la-req" aria-hidden="true">*</span></label></th>
						<td><input type="text" id="la-name" name="name" class="regular-text" value="<?php echo esc_attr( $f['name'] ); ?>" required aria-required="true" maxlength="200" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="la-advertiser"><?php esc_html_e( 'Advertiser / company', 'local-ads' ); ?></label></th>
						<td><input type="text" id="la-advertiser" name="advertiser" class="regular-text" value="<?php echo esc_attr( $f['advertiser'] ); ?>" maxlength="200" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="la-description"><?php esc_html_e( 'Internal description', 'local-ads' ); ?></label></th>
						<td><textarea id="la-description" name="description" class="large-text" rows="3"><?php echo esc_textarea( $f['description'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Only visible to administrators.', 'local-ads' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="la-campaign"><?php esc_html_e( 'Campaign', 'local-ads' ); ?></label></th>
						<td>
							<?php Local_Ads_Admin::select( 'campaign_id', array( 0 => __( '— No campaign —', 'local-ads' ) ) + $campaigns, $f['campaign_id'], array( 'id' => 'la-campaign' ) ); ?>
							<a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-campaigns', array( 'edit' => 'new' ) ) ); ?>"><?php esc_html_e( 'Create campaign', 'local-ads' ); ?></a>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="la-priority"><?php esc_html_e( 'Priority', 'local-ads' ); ?></label></th>
						<td><input type="number" id="la-priority" name="priority" min="1" max="100" step="1" class="small-text" value="<?php echo esc_attr( $f['priority'] ); ?>" />
						<p class="description"><?php esc_html_e( '1–100. With the Priority rotation method, higher-priority ads are shown first. Priority also decides which ads keep running when a concurrency limit applies.', 'local-ads' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="la-weight"><?php esc_html_e( 'Weight', 'local-ads' ); ?></label></th>
						<td><input type="number" id="la-weight" name="weight" min="1" max="100" step="1" class="small-text" value="<?php echo esc_attr( $f['weight'] ); ?>" />
						<p class="description"><?php esc_html_e( '1–100. With Weighted random rotation, an ad with weight 50 is shown about five times as often as one with weight 10.', 'local-ads' ); ?></p></td>
					</tr>
				</table>
			</div>

			<div class="la-panel">
				<h2 class="la-panel__title"><?php esc_html_e( 'Advertisement image', 'local-ads' ); ?></h2>
				<div class="la-image-field" data-la-image>
					<input type="hidden" name="media_id" value="<?php echo esc_attr( $media ? $media->id : 0 ); ?>" data-la-image-id />
					<div class="la-image-field__preview" data-la-image-preview>
						<?php if ( $media ) : ?>
							<img src="<?php echo esc_url( Local_Ads_Media::url( $media ) ); ?>" alt="" />
						<?php else : ?>
							<span class="la-muted"><?php esc_html_e( 'No image selected.', 'local-ads' ); ?></span>
						<?php endif; ?>
					</div>
					<p class="la-image-field__meta la-muted" data-la-image-meta><?php echo $media ? esc_html( $media->filename . ' · ' . $media->width . '×' . $media->height . ' · ' . size_format( (int) $media->filesize ) ) : ''; ?></p>
					<p>
						<button type="button" class="button button-secondary" data-la-image-select><?php echo $media ? esc_html__( 'Replace image', 'local-ads' ) : esc_html__( 'Upload or select image', 'local-ads' ); ?></button>
						<button type="button" class="button-link button-link-delete" data-la-image-remove<?php echo $media ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove image', 'local-ads' ); ?></button>
					</p>
					<?php /* translators: %s: maximum size. */ ?>
					<p class="description"><?php echo esc_html( sprintf( __( 'JPG, PNG, WEBP or GIF, up to %s. Stored locally in your uploads folder.', 'local-ads' ), size_format( Local_Ads_Media::max_bytes() ) ) ); ?></p>
				</div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="la-alt"><?php esc_html_e( 'Alt text', 'local-ads' ); ?></label></th>
						<td><input type="text" id="la-alt" name="alt_text" class="large-text" value="<?php echo esc_attr( $f['alt_text'] ); ?>" maxlength="255" />
						<p class="description"><?php esc_html_e( 'Describe the advert for screen-reader users, e.g. "Kenya safari special: 20% off October departures". Leave empty only if the image is purely decorative.', 'local-ads' ); ?></p></td>
					</tr>
				</table>
			</div>

			<div class="la-panel">
				<h2 class="la-panel__title"><?php esc_html_e( 'Destination', 'local-ads' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="la-url"><?php esc_html_e( 'Destination URL', 'local-ads' ); ?></label></th>
						<td><input type="url" id="la-url" name="destination_url" class="large-text code" value="<?php echo esc_attr( $f['destination_url'] ); ?>" placeholder="https://example.com/offer/" />
						<p class="description"><?php esc_html_e( 'Optional. Full URL for external sites, or a path such as /offers/ for pages on this site. Without a URL the advert is image-only.', 'local-ads' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Link behaviour', 'local-ads' ); ?></th>
						<td>
							<input type="hidden" name="display[new_tab]" value="0" />
							<label><input type="checkbox" name="display[new_tab]" value="1" <?php checked( $display['new_tab'], 1 ); ?> /> <?php esc_html_e( 'Open destination in a new tab', 'local-ads' ); ?></label><br />
							<input type="hidden" name="display[close_after_click]" value="0" />
							<label><input type="checkbox" name="display[close_after_click]" value="1" <?php checked( $display['close_after_click'], 1 ); ?> /> <?php esc_html_e( 'Close the advertisement after it is clicked', 'local-ads' ); ?></label>
						</td>
					</tr>
				</table>
			</div>

			<div class="la-panel">
				<h2 class="la-panel__title"><?php esc_html_e( 'Display rules', 'local-ads' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="la-trigger"><?php esc_html_e( 'Show popup', 'local-ads' ); ?></label></th>
						<td>
							<?php Local_Ads_Admin::select( 'display[trigger]', Local_Ads_Settings::choices( 'trigger' ), $display['trigger'], array( 'id' => 'la-trigger' ) ); ?>
							<span data-la-when="display[trigger]" data-la-is="delay"><input type="number" name="display[trigger_delay]" min="0" max="600" class="small-text" value="<?php echo esc_attr( $display['trigger_delay'] ); ?>" aria-label="<?php esc_attr_e( 'Seconds', 'local-ads' ); ?>" /> <?php esc_html_e( 'seconds', 'local-ads' ); ?></span>
							<span data-la-when="display[trigger]" data-la-is="pixels"><input type="number" name="display[trigger_pixels]" min="1" max="100000" class="small-text" value="<?php echo esc_attr( $display['trigger_pixels'] ); ?>" aria-label="<?php esc_attr_e( 'Pixels', 'local-ads' ); ?>" /> px</span>
							<span data-la-when="display[trigger]" data-la-is="percent"><input type="number" name="display[trigger_percent]" min="1" max="100" class="small-text" value="<?php echo esc_attr( $display['trigger_percent'] ); ?>" aria-label="<?php esc_attr_e( 'Percent', 'local-ads' ); ?>" /> %</span>
							<p class="description"><?php esc_html_e( 'The popup is triggered once per page view; further scrolling never re-opens it.', 'local-ads' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="la-frequency"><?php esc_html_e( 'Visitor frequency', 'local-ads' ); ?></label></th>
						<td>
							<?php Local_Ads_Admin::select( 'display[frequency]', Local_Ads_Settings::choices( 'frequency' ), $display['frequency'], array( 'id' => 'la-frequency' ) ); ?>
							<span data-la-when="display[frequency]" data-la-is="hours"><input type="number" name="display[frequency_hours]" min="1" max="8760" class="small-text" value="<?php echo esc_attr( $display['frequency_hours'] ); ?>" aria-label="<?php esc_attr_e( 'Hours', 'local-ads' ); ?>" /> <?php esc_html_e( 'hours', 'local-ads' ); ?></span>
							<p class="description"><?php esc_html_e( 'Tracked per advertisement in the visitor\'s browser. Closing this ad never blocks other ads unless a global restriction is set in Settings.', 'local-ads' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto close', 'local-ads' ); ?></th>
						<td>
							<input type="hidden" name="display[auto_close]" value="0" />
							<label><input type="checkbox" name="display[auto_close]" value="1" <?php checked( $display['auto_close'], 1 ); ?> /> <?php esc_html_e( 'Automatically close the advertisement', 'local-ads' ); ?></label>
							<div data-la-when="display[auto_close]" data-la-is="1" class="la-inline-fields">
								<label for="la-ac-preset"><?php esc_html_e( 'After', 'local-ads' ); ?></label>
								<select name="display[auto_close_preset]" id="la-ac-preset">
									<?php foreach ( $presets as $p ) : ?>
										<?php /* translators: %d: seconds. */ ?>
										<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $ac_preset, (string) $p ); ?>><?php echo esc_html( sprintf( __( '%d seconds', 'local-ads' ), $p ) ); ?></option>
									<?php endforeach; ?>
									<option value="custom" <?php selected( $ac_preset, 'custom' ); ?>><?php esc_html_e( 'Custom', 'local-ads' ); ?></option>
								</select>
								<span data-la-when="display[auto_close_preset]" data-la-is="custom"><input type="number" name="display[auto_close_secs]" min="3" max="3600" class="small-text" value="<?php echo esc_attr( $display['auto_close_secs'] ); ?>" aria-label="<?php esc_attr_e( 'Seconds', 'local-ads' ); ?>" /> <?php esc_html_e( 'seconds', 'local-ads' ); ?></span>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="la-width"><?php esc_html_e( 'Popup width', 'local-ads' ); ?></label></th>
						<td class="la-inline-fields">
							<label for="la-width"><?php esc_html_e( 'Desktop max', 'local-ads' ); ?></label> <input type="number" id="la-width" name="display[width]" min="200" max="2000" class="small-text" value="<?php echo esc_attr( $display['width'] ); ?>" /> px
							&nbsp; <label for="la-mwidth"><?php esc_html_e( 'Mobile max', 'local-ads' ); ?></label> <input type="number" id="la-mwidth" name="display[mobile_width]" min="50" max="100" class="small-text" value="<?php echo esc_attr( $display['mobile_width'] ); ?>" /> <?php esc_html_e( '% of screen width', 'local-ads' ); ?>
						</td>
					</tr>
				</table>
			</div>

			<div class="la-panel">
				<h2 class="la-panel__title"><?php esc_html_e( 'Animation', 'local-ads' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="la-anim"><?php esc_html_e( 'Entrance animation', 'local-ads' ); ?></label></th>
						<td><?php Local_Ads_Admin::select( 'display[animation]', Local_Ads_Settings::choices( 'animation' ), $display['animation'], array( 'id' => 'la-anim' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Attention shake', 'local-ads' ); ?></th>
						<td>
							<input type="hidden" name="display[shake]" value="0" />
							<label><input type="checkbox" name="display[shake]" value="1" <?php checked( $display['shake'], 1 ); ?> /> <?php esc_html_e( 'Shake the advertisement briefly after it appears', 'local-ads' ); ?></label>
							<div data-la-when="display[shake]" data-la-is="1" class="la-inline-fields">
								<label for="la-shake-delay"><?php esc_html_e( 'Delay', 'local-ads' ); ?></label> <input type="number" id="la-shake-delay" name="display[shake_delay]" min="0" max="120" class="small-text" value="<?php echo esc_attr( $display['shake_delay'] ); ?>" /> <?php esc_html_e( 's', 'local-ads' ); ?>
								&nbsp; <label for="la-shake-dur"><?php esc_html_e( 'Duration', 'local-ads' ); ?></label> <input type="number" id="la-shake-dur" name="display[shake_duration]" min="1" max="10" class="small-text" value="<?php echo esc_attr( $display['shake_duration'] ); ?>" /> <?php esc_html_e( 's', 'local-ads' ); ?>
								&nbsp; <label for="la-shake-int"><?php esc_html_e( 'Intensity', 'local-ads' ); ?></label> <?php Local_Ads_Admin::select( 'display[shake_intensity]', Local_Ads_Settings::choices( 'intensity' ), $display['shake_intensity'], array( 'id' => 'la-shake-int' ) ); ?>
								<p class="description"><?php esc_html_e( 'The shake stops after the duration. Visitors who prefer reduced motion never see animations or shaking.', 'local-ads' ); ?></p>
							</div>
						</td>
					</tr>
				</table>
			</div>

			<div class="la-panel">
				<h2 class="la-panel__title"><?php esc_html_e( 'Schedule', 'local-ads' ); ?></h2>
				<?php /* translators: %s: timezone name. */ ?>
				<p class="description"><?php echo esc_html( sprintf( __( 'Times use the site timezone (%s). Leave the start empty to start immediately and the end empty to run indefinitely.', 'local-ads' ), $tz_label ) ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="la-start-date"><?php esc_html_e( 'Start', 'local-ads' ); ?></label></th>
						<td class="la-inline-fields"><input type="date" id="la-start-date" name="start_date" value="<?php echo esc_attr( $f['start_date'] ); ?>" data-la-schedule /> <input type="time" name="start_time" value="<?php echo esc_attr( $f['start_time'] ); ?>" aria-label="<?php esc_attr_e( 'Start time', 'local-ads' ); ?>" data-la-schedule /></td>
					</tr>
					<tr>
						<th scope="row"><label for="la-end-date"><?php esc_html_e( 'End', 'local-ads' ); ?></label></th>
						<td class="la-inline-fields"><input type="date" id="la-end-date" name="end_date" value="<?php echo esc_attr( $f['end_date'] ); ?>" data-la-schedule /> <input type="time" name="end_time" value="<?php echo esc_attr( $f['end_time'] ); ?>" aria-label="<?php esc_attr_e( 'End time', 'local-ads' ); ?>" data-la-schedule />
						<p class="description"><?php esc_html_e( 'If no end time is given, the advert runs until 23:59 on the end date.', 'local-ads' ); ?></p></td>
					</tr>
				</table>
				<div class="la-conflicts" data-la-conflicts aria-live="polite"></div>
			</div>

			<div class="la-panel">
				<h2 class="la-panel__title"><?php esc_html_e( 'Page targeting', 'local-ads' ); ?></h2>
				<fieldset class="la-fieldset">
					<legend class="screen-reader-text"><?php esc_html_e( 'Where to display', 'local-ads' ); ?></legend>
					<label><input type="radio" name="targeting[mode]" value="all" <?php checked( $targeting['mode'], 'all' ); ?> /> <?php esc_html_e( 'Entire website', 'local-ads' ); ?></label><br />
					<label><input type="radio" name="targeting[mode]" value="custom" <?php checked( $targeting['mode'], 'custom' ); ?> /> <?php esc_html_e( 'Only on selected locations', 'local-ads' ); ?></label>
				</fieldset>
				<div data-la-when="targeting[mode]" data-la-is="custom" class="la-subpanel">
					<p>
						<label><input type="checkbox" name="targeting[home]" value="1" <?php checked( $targeting['home'], 1 ); ?> /> <?php esc_html_e( 'Homepage', 'local-ads' ); ?></label><br />
						<label><input type="checkbox" name="targeting[posts]" value="1" <?php checked( $targeting['posts'], 1 ); ?> /> <?php esc_html_e( 'All blog posts', 'local-ads' ); ?></label><br />
						<label><input type="checkbox" name="targeting[pages]" value="1" <?php checked( $targeting['pages'], 1 ); ?> /> <?php esc_html_e( 'All pages', 'local-ads' ); ?></label><br />
						<label><input type="checkbox" name="targeting[products]" value="1" <?php checked( $targeting['products'], 1 ); ?> <?php disabled( ! $has_woo ); ?> /> <?php esc_html_e( 'All WooCommerce products', 'local-ads' ); ?></label>
						<?php if ( ! $has_woo ) : ?><span class="la-muted">(<?php esc_html_e( 'WooCommerce not active', 'local-ads' ); ?>)</span><?php endif; ?>
					</p>
					<div class="la-grid-2">
						<div>
							<h3 class="la-h3"><?php esc_html_e( 'Selected pages', 'local-ads' ); ?></h3>
							<?php $la_checklist( 'targeting[page_ids]', $all_pages, $targeting['page_ids'], __( 'Filter pages', 'local-ads' ) ); ?>
						</div>
						<div>
							<h3 class="la-h3"><?php esc_html_e( 'Selected posts', 'local-ads' ); ?></h3>
							<?php $la_checklist( 'targeting[post_ids]', $all_posts, $targeting['post_ids'], __( 'Filter posts', 'local-ads' ) ); ?>
						</div>
					</div>
					<p>
						<label for="la-extra-ids"><?php esc_html_e( 'Additional post, page or product IDs (comma separated)', 'local-ads' ); ?></label><br />
						<input type="text" id="la-extra-ids" name="targeting[post_ids_extra]" class="regular-text" value="" placeholder="12, 48, 103" />
					</p>
				</div>

				<h3 class="la-h3"><?php esc_html_e( 'Never display on', 'local-ads' ); ?></h3>
				<p>
					<label><input type="checkbox" name="targeting[ex_home]" value="1" <?php checked( $targeting['ex_home'], 1 ); ?> /> <?php esc_html_e( 'Homepage', 'local-ads' ); ?></label><br />
					<label><input type="checkbox" name="targeting[ex_checkout]" value="1" <?php checked( $targeting['ex_checkout'], 1 ); ?> /> <?php esc_html_e( 'Checkout', 'local-ads' ); ?></label><br />
					<label><input type="checkbox" name="targeting[ex_cart]" value="1" <?php checked( $targeting['ex_cart'], 1 ); ?> /> <?php esc_html_e( 'Cart', 'local-ads' ); ?></label><br />
					<label><input type="checkbox" name="targeting[ex_login]" value="1" <?php checked( $targeting['ex_login'], 1 ); ?> /> <?php esc_html_e( 'Login and account pages', 'local-ads' ); ?></label>
				</p>
				<div class="la-grid-2">
					<div>
						<h3 class="la-h3"><?php esc_html_e( 'Excluded pages', 'local-ads' ); ?></h3>
						<?php $la_checklist( 'targeting[ex_page_ids]', $all_pages, $targeting['ex_page_ids'], __( 'Filter excluded pages', 'local-ads' ) ); ?>
					</div>
					<div>
						<h3 class="la-h3"><label for="la-ex-urls"><?php esc_html_e( 'Excluded URLs', 'local-ads' ); ?></label></h3>
						<textarea id="la-ex-urls" name="targeting[ex_urls]" rows="6" class="large-text code" placeholder="/contact/&#10;/members/*"><?php echo esc_textarea( implode( "\n", (array) $targeting['ex_urls'] ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One per line. Use paths or full URLs; * matches anything, e.g. /shop/* .', 'local-ads' ); ?></p>
					</div>
				</div>
				<p class="description"><?php esc_html_e( 'Advertisements never display inside the WordPress admin, on the login screen, in feeds or in the Customizer.', 'local-ads' ); ?></p>
			</div>
		</div>

		<div class="la-edit__side">
			<div class="la-panel la-publish">
				<h2 class="la-panel__title"><?php esc_html_e( 'Publish', 'local-ads' ); ?></h2>
				<?php if ( ! $is_new ) : ?>
					<p><?php esc_html_e( 'Current status:', 'local-ads' ); ?> <?php echo Local_Ads_Admin::badge( $ad->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>
				<p>
					<label for="la-status"><strong><?php esc_html_e( 'Status', 'local-ads' ); ?></strong></label><br />
					<?php
					Local_Ads_Admin::select(
						'status',
						array(
							'active' => __( 'Active (follows schedule)', 'local-ads' ),
							'paused' => __( 'Paused', 'local-ads' ),
							'draft'  => __( 'Draft', 'local-ads' ),
						),
						$f['status'],
						array( 'id' => 'la-status' )
					);
					?>
				</p>
				<p class="description"><?php esc_html_e( 'Active ads become Scheduled before their start time and Expired after their end time automatically.', 'local-ads' ); ?></p>
				<div class="la-publish__actions">
					<button type="button" class="button" data-la-preview-form><?php esc_html_e( 'Preview', 'local-ads' ); ?></button>
					<button type="submit" class="button button-primary button-large"><?php echo $is_new ? esc_html__( 'Publish advertisement', 'local-ads' ) : esc_html__( 'Update advertisement', 'local-ads' ); ?></button>
				</div>
				<?php if ( ! $is_new ) : ?>
					<hr />
					<ul class="la-side-actions">
						<?php if ( in_array( $ad->status, array( 'draft', 'paused' ), true ) ) : ?>
							<li><a href="<?php echo esc_url( Local_Ads_Admin::ad_action_url( $ad->id, 'activate', array( 'back' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Activate', 'local-ads' ); ?></a></li>
						<?php endif; ?>
						<?php if ( 'scheduled' === $ad->status ) : ?>
							<li><a href="<?php echo esc_url( Local_Ads_Admin::ad_action_url( $ad->id, 'start_now', array( 'back' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Start now (override start time)', 'local-ads' ); ?></a></li>
						<?php endif; ?>
						<?php if ( in_array( $ad->status, array( 'active', 'scheduled' ), true ) ) : ?>
							<li><a href="<?php echo esc_url( Local_Ads_Admin::ad_action_url( $ad->id, 'pause', array( 'back' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Pause', 'local-ads' ); ?></a></li>
						<?php endif; ?>
						<?php if ( 'draft' !== $ad->status ) : ?>
							<li><a href="<?php echo esc_url( Local_Ads_Admin::ad_action_url( $ad->id, 'deactivate', array( 'back' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Deactivate', 'local-ads' ); ?></a></li>
						<?php endif; ?>
						<li><a href="<?php echo esc_url( Local_Ads_Admin::ad_action_url( $ad->id, 'duplicate' ) ); ?>"><?php esc_html_e( 'Duplicate', 'local-ads' ); ?></a></li>
						<?php if ( current_user_can( Local_Ads_Security::CAP_DELETE ) ) : ?>
							<li><a class="submitdelete la-confirm" data-confirm="<?php esc_attr_e( 'Delete this advertisement? Its analytics history is kept.', 'local-ads' ); ?>" href="<?php echo esc_url( Local_Ads_Admin::ad_action_url( $ad->id, 'delete' ) ); ?>"><?php esc_html_e( 'Delete', 'local-ads' ); ?></a></li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
			</div>

			<?php if ( ! $is_new ) : ?>
				<div class="la-panel">
					<h2 class="la-panel__title"><?php esc_html_e( 'Lifetime performance', 'local-ads' ); ?></h2>
					<dl class="la-dl">
						<dt><?php esc_html_e( 'Impressions', 'local-ads' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $ad->impressions ) ); ?></dd>
						<dt><?php esc_html_e( 'Clicks', 'local-ads' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $ad->clicks ) ); ?></dd>
						<dt><?php esc_html_e( 'CTR', 'local-ads' ); ?></dt><dd><?php echo esc_html( Local_Ads_Analytics::ctr_label( $ad->clicks, $ad->impressions ) ); ?></dd>
					</dl>
					<p><a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-analytics', array( 'ad_id' => $ad->id ) ) ); ?>" class="button"><?php esc_html_e( 'View Analytics', 'local-ads' ); ?></a></p>
				</div>
			<?php endif; ?>

			<div class="la-panel">
				<h2 class="la-panel__title"><?php esc_html_e( 'Rotation', 'local-ads' ); ?></h2>
				<?php
				$rot = Local_Ads_Settings::choices( 'rotation' );
				/* translators: 1: rotation method, 2: Yes/No. */
				$la_rot_text = sprintf( __( 'Rotation method: %1$s. Concurrent advertisements allowed: %2$s.', 'local-ads' ), $rot[ $s['rotation'] ], $s['allow_concurrent'] ? __( 'Yes', 'local-ads' ) : __( 'No', 'local-ads' ) );
				?>
				<p><?php echo esc_html( $la_rot_text ); ?></p>
				<?php if ( current_user_can( Local_Ads_Security::CAP_MANAGE ) ) : ?>
					<p><a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-settings', array( 'tab' => 'scheduling' ) ) ); ?>"><?php esc_html_e( 'Change in Settings', 'local-ads' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</form>
