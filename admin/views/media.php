<?php
/**
 * Media view: advertisement images stored in the local uploads folder.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

$la_media  = Local_Ads_Media::all();
$la_folder = Local_Ads_Media::folder();
$la_status = Local_Ads_Ads::statuses();
?>
<h1 class="wp-heading-inline"><?php esc_html_e( 'Advertisement Media', 'local-ads' ); ?></h1>
<hr class="wp-header-end" />

<?php if ( is_wp_error( $la_folder ) ) : ?>
	<div class="notice notice-error inline"><p><?php echo esc_html( $la_folder->get_error_message() ); ?></p></div>
<?php else : ?>
	<?php /* translators: %s: folder path relative to uploads. */ ?>
	<p class="description"><?php echo esc_html( sprintf( __( 'Images are stored locally in wp-content/uploads/%s/ and are kept separate from the Media Library.', 'local-ads' ), $la_folder['relative'] ) ); ?></p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="la-panel la-upload-form">
	<input type="hidden" name="action" value="local_ads_media_upload" />
	<?php wp_nonce_field( 'local_ads_media_upload' ); ?>
	<label for="la-upload-file"><strong><?php esc_html_e( 'Upload new image', 'local-ads' ); ?></strong></label>
	<input type="file" id="la-upload-file" name="local_ads_file" accept="image/jpeg,image/png,image/webp<?php echo Local_Ads_Settings::get( 'allow_gif' ) ? ',image/gif' : ''; ?>" required />
	<?php submit_button( __( 'Upload', 'local-ads' ), 'secondary', 'submit', false ); ?>
	<?php /* translators: %s: maximum size. */ ?>
	<span class="description"><?php echo esc_html( sprintf( __( 'JPG, PNG, WEBP or GIF, up to %s.', 'local-ads' ), size_format( Local_Ads_Media::max_bytes() ) ) ); ?></span>
</form>

<table class="wp-list-table widefat fixed striped la-media-table">
	<thead>
		<tr>
			<th scope="col" class="la-col-thumb"><?php esc_html_e( 'Image', 'local-ads' ); ?></th>
			<th scope="col" class="column-primary"><?php esc_html_e( 'File', 'local-ads' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Dimensions', 'local-ads' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Size', 'local-ads' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Assigned to', 'local-ads' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Uploaded', 'local-ads' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Status', 'local-ads' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php if ( ! $la_media ) : ?>
		<tr><td colspan="7"><?php esc_html_e( 'No advertisement images yet.', 'local-ads' ); ?></td></tr>
	<?php endif; ?>
	<?php foreach ( $la_media as $m ) : ?>
		<?php
		$live = array_intersect( $m->ads, array( 'active', 'scheduled' ) );
		$file_ok = '' !== Local_Ads_Media::path( $m );
		?>
		<tr>
			<td class="la-col-thumb">
				<?php if ( $file_ok ) : ?>
					<button type="button" class="la-thumb-btn" data-la-lightbox="<?php echo esc_url( $m->url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: filename. */ __( 'Preview %s', 'local-ads' ), $m->filename ) ); ?>"><img class="la-thumb la-thumb--lg" src="<?php echo esc_url( $m->url ); ?>" alt="" loading="lazy" /></button>
				<?php else : ?>
					<span class="la-thumb la-thumb--lg la-thumb--empty" aria-hidden="true"></span>
				<?php endif; ?>
			</td>
			<td class="column-primary">
				<strong><?php echo esc_html( $m->filename ); ?></strong>
				<?php if ( ! $file_ok ) : ?><div class="la-error-text"><?php esc_html_e( 'File missing from the uploads folder.', 'local-ads' ); ?></div><?php endif; ?>
				<div class="row-actions">
					<?php if ( $file_ok ) : ?>
						<span><button type="button" class="button-link" data-la-lightbox="<?php echo esc_url( $m->url ); ?>"><?php esc_html_e( 'Preview', 'local-ads' ); ?></button> | </span>
					<?php endif; ?>
					<span><a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-edit', array( 'media_id' => $m->id ) ) ); ?>"><?php esc_html_e( 'Use for Advertisement', 'local-ads' ); ?></a> | </span>
					<span>
						<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="la-replace-form">
							<input type="hidden" name="action" value="local_ads_media_replace" />
							<input type="hidden" name="media_id" value="<?php echo esc_attr( $m->id ); ?>" />
							<?php wp_nonce_field( 'local_ads_media_replace' ); ?>
							<label class="button-link la-replace-label"><?php esc_html_e( 'Replace', 'local-ads' ); ?><input type="file" name="local_ads_file" class="screen-reader-text la-replace-input" accept="image/jpeg,image/png,image/webp,image/gif" /></label>
						</form>
					</span>
					<?php if ( current_user_can( Local_Ads_Security::CAP_DELETE ) ) : ?>
						<span class="trash"> | <a class="submitdelete la-confirm" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'local_ads_media_delete', 'id' => $m->id ), admin_url( 'admin-post.php' ) ), 'local_ads_media_delete_' . $m->id ) ); ?>" data-confirm="<?php echo esc_attr( $m->ads ? __( 'This image is assigned to advertisements. Inactive ones will be left without an image. Delete it?', 'local-ads' ) : __( 'Delete this image permanently?', 'local-ads' ) ); ?>"><?php esc_html_e( 'Delete', 'local-ads' ); ?></a></span>
					<?php endif; ?>
				</div>
			</td>
			<td><?php echo esc_html( $m->width . ' × ' . $m->height ); ?></td>
			<td><?php echo esc_html( size_format( (int) $m->filesize ) ); ?></td>
			<td>
				<?php if ( $m->ads ) : ?>
					<?php
					$links = array();
					foreach ( $m->ads as $ad_id => $st ) {
						$ad      = Local_Ads_Ads::get( $ad_id );
						$links[] = '<a href="' . esc_url( Local_Ads_Admin::url( 'local-ads-edit', array( 'id' => $ad_id ) ) ) . '">' . esc_html( $ad ? $ad->name : '#' . $ad_id ) . '</a> <span class="la-muted">(' . esc_html( isset( $la_status[ $st ] ) ? $la_status[ $st ] : $st ) . ')</span>';
					}
					echo implode( '<br />', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
					?>
				<?php else : ?>
					<span class="la-muted">&mdash;</span>
				<?php endif; ?>
			</td>
			<td><?php echo esc_html( Local_Ads_Scheduler::gmt_to_local( $m->created_at, get_option( 'date_format' ) ) ); ?></td>
			<td>
				<?php if ( $live ) : ?>
					<span class="la-badge la-badge--active"><?php esc_html_e( 'In use (live)', 'local-ads' ); ?></span>
				<?php elseif ( $m->ads ) : ?>
					<span class="la-badge la-badge--paused"><?php esc_html_e( 'Assigned', 'local-ads' ); ?></span>
				<?php else : ?>
					<span class="la-badge la-badge--draft"><?php esc_html_e( 'Unused', 'local-ads' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
