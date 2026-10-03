<?php
/**
 * Standalone preview document (loaded in an iframe from the admin). Never records analytics.
 *
 * @package LocalAds
 * @var array $data
 * @var array $config
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<title><?php esc_html_e( 'Advertisement preview', 'local-ads' ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( add_query_arg( 'ver', LOCAL_ADS_VERSION, LOCAL_ADS_URL . 'public/css/public.css' ) ); ?>" /><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone document. ?>
<link rel="stylesheet" href="<?php echo esc_url( add_query_arg( 'ver', LOCAL_ADS_VERSION, LOCAL_ADS_URL . 'admin/css/preview.css' ) ); ?>" /><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
</head>
<body class="la-preview-body">
	<div class="la-mock" aria-hidden="true">
		<div class="la-mock__header"><span class="la-mock__logo"></span><span class="la-mock__nav"></span></div>
		<div class="la-mock__hero"></div>
		<div class="la-mock__lines"><span></span><span></span><span></span><span></span><span></span><span></span></div>
		<div class="la-mock__grid"><span></span><span></span><span></span></div>
		<div class="la-mock__lines"><span></span><span></span><span></span><span></span></div>
	</div>
	<p class="la-preview-empty" data-la-preview-empty hidden><?php esc_html_e( 'Select an image to preview the advertisement.', 'local-ads' ); ?></p>
	<script id="la-preview-data" type="application/json"><?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
	<script id="la-preview-config" type="application/json"><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
	<script src="<?php echo esc_url( add_query_arg( 'ver', LOCAL_ADS_VERSION, LOCAL_ADS_URL . 'admin/js/preview.js' ) ); ?>"></script><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone document. ?>
	<script src="<?php echo esc_url( add_query_arg( 'ver', LOCAL_ADS_VERSION, LOCAL_ADS_URL . 'public/js/public.js' ) ); ?>"></script><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
</body>
</html>
