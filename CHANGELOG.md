# Changelog

All notable changes to Local Ads by Bernard are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.1] - 2026-10-03

### Added

- `localads:track` DOM event, dispatched on `document` with `detail: { id, type }` (`type` is `impression` or `click`) whenever a popup is shown or its link is clicked. Previews never dispatch it. Blue Lens Analytics 0.5+ listens for it to link ad interactions to visits, channels, pages and conversions.

## [1.0.0] - 2026-10-03

First public release.

### Added

- Advertisement management: create, edit, duplicate (as draft), activate, pause, deactivate, "Start now", delete, search, status views, sorting and bulk activate/pause/delete.
- Local media library in `wp-content/uploads/local-ads/` with a configurable, traversal-safe subfolder; upload, replace (keeps the image ID), preview and delete; MIME, content, size and double-extension validation; `.htaccess` and `index.php` guards.
- Responsive popup with overlay, accessible close button (size, position, optional delayed visibility), Escape key, overlay click, focus trap, focus restore and an optional "Advertisement" label.
- Triggers: immediately, after X seconds, on first scroll, after X pixels and after X% of the page, with a fallback delay for pages that cannot scroll.
- Entrance animations (fade, slide up/down/left/right, zoom, bounce, shake) and a temporary attention shake with delay, duration and intensity; all motion disabled under `prefers-reduced-motion`.
- Visitor frequency per advertisement (session, visit, day, every X hours, always) and an optional global restriction after any ad is closed.
- Scheduling in the site timezone with automatic Scheduled/Active/Expired statuses; display eligibility is checked live and never depends on WP-Cron.
- Concurrent campaigns setting, maximum concurrent campaigns, live conflict detection while editing and a monthly campaign calendar with overlap counts.
- Rotation methods: random, sequential, priority and weighted random. Only one popup per page view.
- Campaigns with status, date bounds and aggregate analytics with drill-down.
- Analytics: atomic daily impression/click counters per ad, lifetime totals, CTR, periods (today, yesterday, 7 days, 30 days, this month, last month, custom), per-ad and per-campaign reports, charts and CSV export with formula-injection protection.
- Page targeting (whole site, homepage, posts, pages, WooCommerce products, selected items) and exclusions (homepage, checkout, cart, login/account, selected pages, URL patterns with wildcards).
- Desktop and mobile preview from the editor, including unsaved changes; previews are never tracked.
- Settings in eight tabs, a Tools screen (maintenance, repair, clear event log, reset analytics, system status) and data retention for daily statistics and the event log.
- Custom capabilities: `manage_local_ads`, `edit_local_ads`, `delete_local_ads`, `view_local_ads_analytics`.
- Clean uninstall that removes data only when enabled, and image files only when separately enabled.
- Translation template (`languages/local-ads.pot`) and plugin icons.

[Unreleased]: ../../compare/v1.0.1...HEAD
[1.0.1]: ../../releases/tag/v1.0.1
[1.0.0]: ../../releases/tag/v1.0.0
