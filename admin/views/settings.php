<?php
/**
 * Settings view. One form; tabs only change which section is visible.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

$s    = Local_Ads_Settings::all();
$tabs = array(
	'general'    => __( 'General', 'local-ads' ),
	'popup'      => __( 'Popup', 'local-ads' ),
	'animation'  => __( 'Animation', 'local-ads' ),
	'behaviour'  => __( 'Behaviour', 'local-ads' ),
	'scheduling' => __( 'Scheduling', 'local-ads' ),
	'storage'    => __( 'Storage', 'local-ads' ),
	'analytics'  => __( 'Analytics', 'local-ads' ),
	'advanced'   => __( 'Advanced', 'local-ads' ),
);
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
$current = array_key_exists( $current, $tabs ) ? $current : 'general';
$presets = Local_Ads_Settings::choices( 'auto_close_presets' );
$ac      = in_array( (int) $s['default_auto_close_secs'], $presets, true ) ? (string) $s['default_auto_close_secs'] : 'custom';

$checkbox = function ( $key, $label ) use ( $s ) {
	echo '<input type="hidden" name="settings[' . esc_attr( $key ) . ']" value="0" />';
	echo '<label><input type="checkbox" name="settings[' . esc_attr( $key ) . ']" value="1" ' . checked( (int) $s[ $key ], 1, false ) . ' /> ' . esc_html( $label ) . '</label>';
};
$number = function ( $key, $min, $max, $step = 1, $suffix = '' ) use ( $s ) {
	echo '<input type="number" id="la-s-' . esc_attr( $key ) . '" name="settings[' . esc_attr( $key ) . ']" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '" class="small-text" value="' . esc_attr( $s[ $key ] ) . '" /> ' . esc_html( $suffix );
};
?>
<h1><?php esc_html_e( 'Local Ads Settings', 'local-ads' ); ?></h1>

<nav class="nav-tab-wrapper la-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'local-ads' ); ?>">
	<?php foreach ( $tabs as $key => $label ) : ?>
		<a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-settings', array( 'tab' => $key ) ) ); ?>" class="nav-tab<?php echo $key === $current ? ' nav-tab-active' : ''; ?>" data-la-tab="<?php echo esc_attr( $key ); ?>"<?php echo $key === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="la-settings">
	<input type="hidden" name="action" value="local_ads_save_settings" />
	<input type="hidden" name="tab" value="<?php echo esc_attr( $current ); ?>" data-la-tab-input />
	<?php wp_nonce_field( 'local_ads_settings' ); ?>

	<section class="la-tab-panel" data-la-panel="general"<?php echo 'general' === $current ? '' : ' hidden'; ?>>
		<h2><?php esc_html_e( 'General', 'local-ads' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Enable Local Ads', 'local-ads' ); ?></th><td><?php $checkbox( 'enabled', __( 'Show advertisements to visitors', 'local-ads' ) ); ?></td></tr>
			<tr><th scope="row"><label for="la-s-default_frequency"><?php esc_html_e( 'Default frequency', 'local-ads' ); ?></label></th><td><?php Local_Ads_Admin::select( 'settings[default_frequency]', Local_Ads_Settings::choices( 'frequency' ), $s['default_frequency'], array( 'id' => 'la-s-default_frequency' ) ); ?>
				<span data-la-when="settings[default_frequency]" data-la-is="hours"><?php $number( 'default_frequency_hours', 1, 8760, 1, __( 'hours', 'local-ads' ) ); ?></span>
				<p class="description"><?php esc_html_e( 'Used for new advertisements. Each advertisement can override it.', 'local-ads' ); ?></p></td></tr>
			<tr><th scope="row"><label for="la-s-default_animation"><?php esc_html_e( 'Default animation', 'local-ads' ); ?></label></th><td><?php Local_Ads_Admin::select( 'settings[default_animation]', Local_Ads_Settings::choices( 'animation' ), $s['default_animation'], array( 'id' => 'la-s-default_animation' ) ); ?></td></tr>
			<tr><th scope="row"><label for="la-s-default_width"><?php esc_html_e( 'Default popup width', 'local-ads' ); ?></label></th><td><?php $number( 'default_width', 200, 2000, 1, 'px' ); ?></td></tr>
			<tr><th scope="row"><label for="la-s-default_mobile_width"><?php esc_html_e( 'Default mobile width', 'local-ads' ); ?></label></th><td><?php $number( 'default_mobile_width', 50, 100, 1, __( '% of screen width (screens up to 600px)', 'local-ads' ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Advertisement label', 'local-ads' ); ?></th><td><?php $checkbox( 'show_label', __( 'Show a small "Advertisement" label on the popup', 'local-ads' ) ); ?></td></tr>
		</table>
	</section>

	<section class="la-tab-panel" data-la-panel="popup"<?php echo 'popup' === $current ? '' : ' hidden'; ?>>
		<h2><?php esc_html_e( 'Popup', 'local-ads' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="la-s-default_trigger"><?php esc_html_e( 'Default trigger', 'local-ads' ); ?></label></th><td><?php Local_Ads_Admin::select( 'settings[default_trigger]', Local_Ads_Settings::choices( 'trigger' ), $s['default_trigger'], array( 'id' => 'la-s-default_trigger' ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Default thresholds', 'local-ads' ); ?></th><td class="la-inline-fields">
				<label for="la-s-default_trigger_delay"><?php esc_html_e( 'Delay', 'local-ads' ); ?></label> <?php $number( 'default_trigger_delay', 0, 600, 1, __( 'seconds', 'local-ads' ) ); ?>
				<label for="la-s-default_trigger_pixels"><?php esc_html_e( 'Scroll', 'local-ads' ); ?></label> <?php $number( 'default_trigger_pixels', 1, 100000, 1, 'px' ); ?>
				<label for="la-s-default_trigger_percent"><?php esc_html_e( 'Scroll', 'local-ads' ); ?></label> <?php $number( 'default_trigger_percent', 1, 100, 1, '%' ); ?>
			</td></tr>
			<tr><th scope="row"><label for="la-s-noscroll_fallback"><?php esc_html_e( 'Pages that cannot scroll', 'local-ads' ); ?></label></th><td><?php $number( 'noscroll_fallback', 0, 600, 1, __( 'seconds', 'local-ads' ) ); ?>
				<p class="description"><?php esc_html_e( 'For scroll triggers on pages too short to scroll, show the popup after this delay. 0 = never.', 'local-ads' ); ?></p></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Default auto close', 'local-ads' ); ?></th><td>
				<?php $checkbox( 'default_auto_close', __( 'Automatically close advertisements', 'local-ads' ) ); ?>
				<div class="la-inline-fields">
					<label for="la-s-ac"><?php esc_html_e( 'After', 'local-ads' ); ?></label>
					<select name="settings[default_auto_close_preset]" id="la-s-ac">
						<?php foreach ( $presets as $p ) : ?>
							<?php /* translators: %d: seconds. */ ?>
							<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $ac, (string) $p ); ?>><?php echo esc_html( sprintf( __( '%d seconds', 'local-ads' ), $p ) ); ?></option>
						<?php endforeach; ?>
						<option value="custom" <?php selected( $ac, 'custom' ); ?>><?php esc_html_e( 'Custom', 'local-ads' ); ?></option>
					</select>
					<span data-la-when="settings[default_auto_close_preset]" data-la-is="custom"><?php $number( 'default_auto_close_secs', 3, 3600, 1, __( 'seconds', 'local-ads' ) ); ?></span>
				</div>
			</td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Overlay', 'local-ads' ); ?></th><td>
				<?php $checkbox( 'overlay_enabled', __( 'Dim the page behind the popup', 'local-ads' ) ); ?><br />
				<label for="la-s-overlay_opacity"><?php esc_html_e( 'Opacity', 'local-ads' ); ?></label> <?php $number( 'overlay_opacity', 0, 1, 0.05 ); ?><br />
				<?php $checkbox( 'overlay_close', __( 'Close the popup when the overlay is clicked', 'local-ads' ) ); ?>
			</td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Close button', 'local-ads' ); ?></th><td class="la-inline-fields">
				<label for="la-s-close_size"><?php esc_html_e( 'Size', 'local-ads' ); ?></label> <?php Local_Ads_Admin::select( 'settings[close_size]', Local_Ads_Settings::choices( 'close_size' ), $s['close_size'], array( 'id' => 'la-s-close_size' ) ); ?>
				<label for="la-s-close_position"><?php esc_html_e( 'Position', 'local-ads' ); ?></label> <?php Local_Ads_Admin::select( 'settings[close_position]', Local_Ads_Settings::choices( 'close_position' ), $s['close_position'], array( 'id' => 'la-s-close_position' ) ); ?>
				<label for="la-s-close_visibility"><?php esc_html_e( 'Visibility', 'local-ads' ); ?></label> <?php Local_Ads_Admin::select( 'settings[close_visibility]', Local_Ads_Settings::choices( 'close_visibility' ), $s['close_visibility'], array( 'id' => 'la-s-close_visibility' ) ); ?>
				<span data-la-when="settings[close_visibility]" data-la-is="delayed"><?php $number( 'close_delay', 1, 10, 1, __( 'seconds', 'local-ads' ) ); ?></span>
				<p class="description"><?php esc_html_e( 'The Escape key always closes the popup, even while the button is delayed.', 'local-ads' ); ?></p>
			</td></tr>
			<tr><th scope="row"><label for="la-s-border_radius"><?php esc_html_e( 'Border radius', 'local-ads' ); ?></label></th><td><?php $number( 'border_radius', 0, 48, 1, 'px' ); ?></td></tr>
			<tr><th scope="row"><label for="la-s-z_index"><?php esc_html_e( 'Stacking order (z-index)', 'local-ads' ); ?></label></th><td><?php $number( 'z_index', 1000, 2147483000 ); ?>
				<p class="description"><?php esc_html_e( 'Raise this if a theme header or chat widget appears above the popup.', 'local-ads' ); ?></p></td></tr>
		</table>
	</section>

	<section class="la-tab-panel" data-la-panel="animation"<?php echo 'animation' === $current ? '' : ' hidden'; ?>>
		<h2><?php esc_html_e( 'Animation', 'local-ads' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="la-s-anim"><?php esc_html_e( 'Default entrance animation', 'local-ads' ); ?></label></th><td><?php Local_Ads_Admin::select( '', Local_Ads_Settings::choices( 'animation' ), $s['default_animation'], array( 'id' => 'la-s-anim', 'data-la-mirror' => 'settings[default_animation]' ) ); ?>
				<p class="description"><?php esc_html_e( 'Used for new advertisements (same setting as on the General tab).', 'local-ads' ); ?></p></td></tr>
			<tr><th scope="row"><label for="la-s-animation_speed"><?php esc_html_e( 'Animation speed', 'local-ads' ); ?></label></th><td><?php $number( 'animation_speed', 100, 2000, 50, 'ms' ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Default shake', 'local-ads' ); ?></th><td>
				<?php $checkbox( 'default_shake', __( 'Shake new advertisements briefly after they appear', 'local-ads' ) ); ?>
				<div class="la-inline-fields">
					<label for="la-s-default_shake_delay"><?php esc_html_e( 'Delay', 'local-ads' ); ?></label> <?php $number( 'default_shake_delay', 0, 120, 1, 's' ); ?>
					<label for="la-s-default_shake_duration"><?php esc_html_e( 'Duration', 'local-ads' ); ?></label> <?php $number( 'default_shake_duration', 1, 10, 1, 's' ); ?>
					<label for="la-s-default_shake_intensity"><?php esc_html_e( 'Intensity', 'local-ads' ); ?></label> <?php Local_Ads_Admin::select( 'settings[default_shake_intensity]', Local_Ads_Settings::choices( 'intensity' ), $s['default_shake_intensity'], array( 'id' => 'la-s-default_shake_intensity' ) ); ?>
				</div>
				<p class="description"><?php esc_html_e( 'Visitors whose system requests reduced motion see no animation or shake.', 'local-ads' ); ?></p>
			</td></tr>
		</table>
	</section>

	<section class="la-tab-panel" data-la-panel="behaviour"<?php echo 'behaviour' === $current ? '' : ' hidden'; ?>>
		<h2><?php esc_html_e( 'Behaviour', 'local-ads' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Links', 'local-ads' ); ?></th><td>
				<?php $checkbox( 'default_new_tab', __( 'Open destination links in a new tab (default for new ads)', 'local-ads' ) ); ?><br />
				<?php $checkbox( 'default_close_after_click', __( 'Close the popup after a click (default for new ads)', 'local-ads' ) ); ?>
			</td></tr>
			<tr><th scope="row"><label for="la-s-freq2"><?php esc_html_e( 'Display frequency', 'local-ads' ); ?></label></th><td><?php Local_Ads_Admin::select( '', Local_Ads_Settings::choices( 'frequency' ), $s['default_frequency'], array( 'id' => 'la-s-freq2', 'data-la-mirror' => 'settings[default_frequency]' ) ); ?>
				<p class="description"><?php esc_html_e( 'Same setting as on the General tab: the default for new advertisements.', 'local-ads' ); ?></p></td></tr>
			<tr><th scope="row"><label for="la-s-global_cap"><?php esc_html_e( 'Global restriction', 'local-ads' ); ?></label></th><td>
				<?php Local_Ads_Admin::select( 'settings[global_cap]', Local_Ads_Settings::choices( 'global_cap' ), $s['global_cap'], array( 'id' => 'la-s-global_cap' ) ); ?>
				<span data-la-when="settings[global_cap]" data-la-is="hours"><?php $number( 'global_cap_hours', 1, 8760, 1, __( 'hours', 'local-ads' ) ); ?></span>
				<p class="description"><?php esc_html_e( 'A visitor never sees more than one popup per page view. By default, closing one advertisement does not block different advertisements on later pages.', 'local-ads' ); ?></p>
			</td></tr>
		</table>
	</section>

	<section class="la-tab-panel" data-la-panel="scheduling"<?php echo 'scheduling' === $current ? '' : ' hidden'; ?>>
		<h2><?php esc_html_e( 'Scheduling', 'local-ads' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Concurrent campaigns', 'local-ads' ); ?></th><td>
				<?php $checkbox( 'allow_concurrent', __( 'Allow advertisements with overlapping schedules to run at the same time', 'local-ads' ) ); ?>
				<p class="description"><?php esc_html_e( 'When disabled, activating an advertisement that overlaps another enabled one is blocked, and only one advertisement runs at a time.', 'local-ads' ); ?></p>
			</td></tr>
			<tr><th scope="row"><label for="la-s-max_concurrent"><?php esc_html_e( 'Maximum concurrent campaigns', 'local-ads' ); ?></label></th><td><?php $number( 'max_concurrent', 0, 100 ); ?>
				<p class="description"><?php esc_html_e( '0 = unlimited. Activations that would exceed this are blocked; if more are running anyway, only the highest-priority ones are shown.', 'local-ads' ); ?></p></td></tr>
			<tr><th scope="row"><label for="la-s-rotation"><?php esc_html_e( 'Rotation method', 'local-ads' ); ?></label></th><td><?php Local_Ads_Admin::select( 'settings[rotation]', Local_Ads_Settings::choices( 'rotation' ), $s['rotation'], array( 'id' => 'la-s-rotation' ) ); ?>
				<p class="description"><?php esc_html_e( 'How one advertisement is chosen when several are eligible for a visitor.', 'local-ads' ); ?></p></td></tr>
		</table>
		<?php $la_next = wp_next_scheduled( Local_Ads_Cron::HOOK ); ?>
		<?php /* translators: %s: date and time. */ ?>
		<p class="description"><?php echo esc_html( $la_next ? sprintf( __( 'Background maintenance next runs at %s. Display eligibility never waits for it: start and end times are checked on every request.', 'local-ads' ), Local_Ads_Scheduler::format_ts( $la_next ) ) : __( 'Background maintenance is not scheduled. Use Tools → Repair to reschedule it.', 'local-ads' ) ); ?></p>
	</section>

	<section class="la-tab-panel" data-la-panel="storage"<?php echo 'storage' === $current ? '' : ' hidden'; ?>>
		<h2><?php esc_html_e( 'Storage', 'local-ads' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="la-s-upload_folder"><?php esc_html_e( 'Advertisement upload folder', 'local-ads' ); ?></label></th><td>
				<code>wp-content/uploads/</code><input type="text" id="la-s-upload_folder" name="settings[upload_folder]" class="regular-text code" value="<?php echo esc_attr( $s['upload_folder'] ); ?>" pattern="[A-Za-z0-9][A-Za-z0-9_\-]*(/[A-Za-z0-9][A-Za-z0-9_\-]*){0,2}" />
				<p class="description"><?php esc_html_e( 'A subfolder of your uploads directory, e.g. "local-ads". Letters, numbers, dashes and underscores; up to 3 levels. Existing images stay where they are.', 'local-ads' ); ?></p>
			</td></tr>
			<tr><th scope="row"><label for="la-s-max_upload_mb"><?php esc_html_e( 'Maximum image size', 'local-ads' ); ?></label></th><td><?php $number( 'max_upload_mb', 1, 50, 1, 'MB' ); ?>
				<?php /* translators: %s: server limit. */ ?>
				<p class="description"><?php echo esc_html( sprintf( __( 'Your server allows uploads up to %s.', 'local-ads' ), size_format( wp_max_upload_size() ) ) ); ?></p></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'GIF images', 'local-ads' ); ?></th><td><?php $checkbox( 'allow_gif', __( 'Allow GIF uploads', 'local-ads' ) ); ?></td></tr>
		</table>
	</section>

	<section class="la-tab-panel" data-la-panel="analytics"<?php echo 'analytics' === $current ? '' : ' hidden'; ?>>
		<h2><?php esc_html_e( 'Analytics', 'local-ads' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Enable analytics', 'local-ads' ); ?></th><td><?php $checkbox( 'analytics_enabled', __( 'Record impressions and clicks', 'local-ads' ) ); ?></td></tr>
			<tr><th scope="row"><label for="la-s-retention_days"><?php esc_html_e( 'Data retention', 'local-ads' ); ?></label></th><td><?php Local_Ads_Admin::select( 'settings[retention_days]', Local_Ads_Settings::choices( 'retention' ), $s['retention_days'], array( 'id' => 'la-s-retention_days' ) ); ?>
				<p class="description"><?php esc_html_e( 'Daily statistics older than this are removed. Advertisements and their lifetime totals are always kept.', 'local-ads' ); ?></p></td></tr>
			<tr><th scope="row"><label for="la-s-event_retention_days"><?php esc_html_e( 'Event log retention', 'local-ads' ); ?></label></th><td><?php $number( 'event_retention_days', 1, 365, 1, __( 'days', 'local-ads' ) ); ?>
				<p class="description"><?php esc_html_e( 'The raw event log (ad, type, time; no visitor data) is kept briefly for auditing. Reports use daily totals.', 'local-ads' ); ?></p></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Exclude staff', 'local-ads' ); ?></th><td><?php $checkbox( 'exclude_managers', __( 'Do not count impressions or clicks from logged-in users who can edit Local Ads', 'local-ads' ) ); ?></td></tr>
		</table>
	</section>

	<section class="la-tab-panel" data-la-panel="advanced"<?php echo 'advanced' === $current ? '' : ' hidden'; ?>>
		<h2><?php esc_html_e( 'Advanced', 'local-ads' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Debug mode', 'local-ads' ); ?></th><td><?php $checkbox( 'debug', __( 'Log display decisions to the browser console', 'local-ads' ) ); ?>
				<p class="description"><?php esc_html_e( 'Shows why an advertisement was or was not displayed. Turn off on live sites.', 'local-ads' ); ?></p></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Uninstall', 'local-ads' ); ?></th><td>
				<?php $checkbox( 'delete_data', __( 'Delete all Local Ads data when the plugin is deleted', 'local-ads' ) ); ?><br />
				<?php $checkbox( 'delete_images', __( 'Also delete advertisement image files', 'local-ads' ) ); ?>
				<p class="description"><?php esc_html_e( 'Data includes settings, advertisements, campaigns and analytics. Image files are only removed when both boxes are ticked.', 'local-ads' ); ?></p>
			</td></tr>
		</table>
	</section>

	<?php submit_button( __( 'Save settings', 'local-ads' ) ); ?>
</form>
