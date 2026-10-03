# Changelog

All notable changes to Local Ad are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [1.0.3] - 2026-10-03

### Changed

- The plugin is now called **Local Ad** (it was "Local Ads by Bernard"). Only the name changes (Plugins screen, admin footer credit, update details and documentation): the folder `local-ads`, the text domain, the settings, the database tables and the menu stay the same, so existing sites keep all their ads and statistics.

## [1.0.2] - 2026-10-03

### Added

- Updates from GitHub Releases. The new `Update URI` header makes WordPress skip WordPress.org for this plugin, and `Local_Ads_Updater` answers the `update_plugins_github.com` filter with the latest release of `Bernard-Ogak/local-ad` that has `local-ads.zip` attached. Updates then appear on **Dashboard → Updates** and the Plugins screen, with one-click and automatic updates and release notes under **View details**. The release is cached for 12 hours (1 hour after a failed check) and **Check again** refreshes it. Filter `local_ads_github_updates` (return `false`) turns the check off. No site data is sent.

### Fixed

- `languages/local-ads.pot` header now reports the current version (it still said 1.0.0). No translatable strings were added since 1.0.0, so the template's strings are unchanged.

### Documentation

- readme.txt: new External services section describing the GitHub update check; the description no longer says nothing is sent to an outside service.
- Updated screenshot and documentation: the admin screenshots were recaptured from a running 1.0.2 install.
- Recaptured the README screenshots (dashboard, edit advertisement, analytics, campaign schedule) from a running 1.0.1 install. The popup screenshot was recaptured too and is unchanged, because 1.0.1 did not change the popup's appearance.
- Developer guide: added the checks run against 1.0.1.

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
