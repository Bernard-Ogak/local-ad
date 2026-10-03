<?php
/**
 * Advertisements list view.
 *
 * @package LocalAds
 * @var Local_Ads_List_Table $table
 */

defined( 'ABSPATH' ) || exit;
?>
<h1 class="wp-heading-inline"><?php esc_html_e( 'Advertisements', 'local-ads' ); ?></h1>
<a href="<?php echo esc_url( Local_Ads_Admin::url( 'local-ads-edit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'local-ads' ); ?></a>
<?php
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$la_search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
if ( '' !== $la_search ) {
	/* translators: %s: search query. */
	echo '<span class="subtitle">' . esc_html( sprintf( __( 'Search results for: %s', 'local-ads' ), $la_search ) ) . '</span>';
}
?>
<hr class="wp-header-end" />

<?php $table->views(); ?>

<form method="get">
	<input type="hidden" name="page" value="local-ads-ads" />
	<?php
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_REQUEST['status'] ) ) :
		?>
		<input type="hidden" name="status" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_REQUEST['status'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />
	<?php endif; ?>
	<?php $table->search_box( __( 'Search advertisements', 'local-ads' ), 'local-ads-search' ); ?>
	<?php $table->display(); ?>
</form>
