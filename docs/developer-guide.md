# Local Ads developer guide

How Local Ads by Bernard is built, for contributors and anyone extending or auditing it.

- [Repository layout](#repository-layout)
- [Architecture](#architecture)
- [Database schema](#database-schema)
- [Advertisement data](#advertisement-data)
- [Frontend request flow](#frontend-request-flow)
- [Endpoints](#endpoints)
- [Eligibility, concurrency and rotation](#eligibility-concurrency-and-rotation)
- [Analytics and atomic counters](#analytics-and-atomic-counters)
- [Security model](#security-model)
- [Time and timezones](#time-and-timezones)
- [Options and cron](#options-and-cron)
- [Internationalisation](#internationalisation)
- [Building a release](#building-a-release)
- [Testing checklist](#testing-checklist)

## Repository layout

```
local-ads.php                    Plugin header, constants, includes, activation hooks
uninstall.php                    Data removal (only when enabled in settings)
readme.txt                       WordPress.org-style readme
includes/
  class-local-ads.php            Bootstrap: loads components on plugins_loaded
  class-local-ads-activator.php  Activation / deactivation
  class-local-ads-db.php         Table names, dbDelta schema, upgrades
  class-local-ads-settings.php   Defaults, choices, sanitisation (option: local_ads_settings)
  class-local-ads-security.php   Capabilities, folder/URL validation, tracking signatures
  class-local-ads-ads.php        Ad model: CRUD, statuses, eligibility, targeting, frontend payload
  class-local-ads-campaigns.php  Campaign model
  class-local-ads-media.php      Local image storage and validation
  class-local-ads-scheduler.php  Timezone conversion, conflicts, peak overlap, calendar
  class-local-ads-analytics.php  Tracking endpoint, reports, retention, CSV
  class-local-ads-display.php    Frontend enqueue, page context, live ad feed
  class-local-ads-cron.php       15-minute maintenance event
  class-local-ads-admin.php      Menus, assets, form handlers, AJAX, notices (admin only)
  class-local-ads-list-table.php WP_List_Table for advertisements
admin/
  views/*.php                    Screen templates
  js/admin.js                    Admin UI: conditional fields, picker, preview, charts
  js/preview.js, css/preview.css Standalone preview document
  css/admin.css
public/
  js/public.js                   Frontend engine (no dependencies)
  css/public.css                 Popup styles, namespaced .local-ads-*
assets/icons/                    Plugin icons
languages/local-ads.pot          Translation template
docs/                            Documentation (not shipped in the ZIP)
bin/build-zip.ps1                Release packaging (not shipped)
```

## Architecture

All classes are static service classes prefixed `Local_Ads_`; `Local_Ads` is a small singleton that wires them together on `plugins_loaded`. The admin class is only included when `is_admin()`.

Storage uses custom tables rather than post types. That keeps the ads out of the content model, allows indexed sorting by impressions and clicks, and makes the analytics counters atomic.

The frontend is split deliberately:

- PHP renders only a tiny **page context** (page type, object ID, WooCommerce flags) into the page.
- The browser fetches eligible ads from an **uncached feed** and makes the final checks itself.

This keeps the plugin correct behind full-page caches: a cached page never contains stale ad data.

## Database schema

All tables use `$wpdb->prefix` and are created with `dbDelta()`. The schema version is stored in `local_ads_db_version`; `Local_Ads_DB::maybe_upgrade()` re-runs `install()` when it changes.

**`{prefix}local_ads_ads`**

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name, advertiser | varchar(200) | |
| description | text | admin only |
| campaign_id | bigint | 0 = none |
| status | varchar(20) | draft, scheduled, active, paused, expired |
| priority, weight | smallint | 1–100 |
| media_id | bigint | → media.id |
| alt_text | varchar(255) | |
| destination_url | text | validated http(s) |
| start_gmt, end_gmt | datetime NULL | UTC; NULL = open-ended |
| display | longtext | JSON, see below |
| targeting | longtext | JSON, see below |
| impressions, clicks | bigint | lifetime counters, never removed by retention |
| created_by, created_at, updated_at | | timestamps in UTC |

Indexes: status, campaign_id, media_id, start_gmt, end_gmt.

**`{prefix}local_ads_campaigns`**: id, name, description, status (active, paused, draft), start_gmt, end_gmt, created_at, updated_at.

**`{prefix}local_ads_media`**: id, file (path relative to the uploads base directory), filename, mime, width, height, filesize, uploaded_by, created_at, updated_at.

**`{prefix}local_ads_daily_stats`**: id, ad_id, campaign_id, stat_date (site-local date), impressions, clicks, created_at, updated_at. `UNIQUE KEY ad_date (ad_id, stat_date)` plus indexes on ad_id, campaign_id and stat_date.

**`{prefix}local_ads_events`**: id, ad_id, campaign_id, event_type (impression, click), created_at. No visitor data. Pruned after the event retention period.

## Advertisement data

`display` JSON (defaults copied from settings when an ad is created):

```json
{
  "trigger": "percent", "trigger_delay": 3, "trigger_pixels": 300, "trigger_percent": 5,
  "frequency": "session", "frequency_hours": 24,
  "auto_close": 1, "auto_close_secs": 120,
  "animation": "fade",
  "shake": 1, "shake_delay": 2, "shake_duration": 3, "shake_intensity": "medium",
  "new_tab": 1, "close_after_click": 1,
  "width": 600, "mobile_width": 92
}
```

`targeting` JSON:

```json
{
  "mode": "all",
  "home": 0, "posts": 0, "pages": 0, "products": 0,
  "page_ids": [], "post_ids": [],
  "ex_home": 0, "ex_checkout": 1, "ex_cart": 1, "ex_login": 1,
  "ex_page_ids": [], "ex_urls": ["/members/*"]
}
```

Both are sanitised by `Local_Ads_Ads::sanitize_display()` and `sanitize_targeting()`, and merged with defaults in `hydrate()` so missing keys are always filled.

## Frontend request flow

1. `wp_enqueue_scripts`: if Local Ads is enabled and `Local_Ads_Ads::maybe_live()` says an ad could be running (a cheap autoloaded option), enqueue `public.js` in the footer with `defer`, plus an inline `window.LocalAdsConfig` containing the AJAX URL, CSS URL, page context and strings. Nothing is loaded when no ad is running.
2. `public.js` requests `admin-ajax.php?action=local_ads_get` with the context and current path. The response is sent with `no-store` headers and `DONOTCACHEPAGE`.
3. The server returns the ads that are live now (status, schedule, campaign, image), after concurrency rules and page targeting, plus global display options, the server time and the site timezone offset.
4. The browser:
   1. syncs its clock to server time,
   2. drops ads outside their schedule and ads blocked by frequency state,
   3. applies any global restriction,
   4. picks one ad by rotation.
5. It arms the trigger. Scroll handling is passive and throttled to one check per 100 ms with a trailing check.
6. At trigger time it re-checks schedule and frequency, preloads the image, lazily loads `public.css`, renders the popup and records state.
7. Only once the image has loaded and the popup is in the DOM does it send the **impression** beacon.
8. A link click sends the **click** beacon and does not block navigation. It closes the popup if configured.

### Browser event for other scripts

Since 1.0.1, each time a popup is shown (after the image has loaded) or its link is clicked, `public.js` dispatches a `localads:track` event on `document`:

```js
document.addEventListener( 'localads:track', function ( e ) {
	// e.detail.id   -> advertisement ID (number)
	// e.detail.type -> 'impression' or 'click'
} );
```

The event fires whether or not Local Ads' own analytics are enabled, and never in previews. It carries only the ad ID, so listeners look up names through `Local_Ads_Ads::get()` or the reports API on the server. [Blue Lens Analytics](https://github.com/Bernard-Ogak/blue_lens_analytics) 0.5+ uses it to record `ad_impression` and `ad_click` events.

`window.LocalAds.preview(ad, global, opts)` renders without tracking or storing state. It is used by the admin preview document.

Browser storage keys (prefix `localAds:`): `s{id}` (sessionStorage, once per session), `v{id}` and `visit` (visits), `d{id}` (day), `h{id}` (hours), `seq` (sequential rotation), `gclosed` (global restriction).

## Endpoints

| Action | Access | Purpose |
|---|---|---|
| `wp_ajax(_nopriv)_local_ads_get` | public, GET | Live ad feed for a page context |
| `wp_ajax(_nopriv)_local_ads_track` | public, POST (beacon) | Record impression/click; answers 204 |
| `wp_ajax_local_ads_media_list` | `edit_local_ads` + nonce | Picker contents |
| `wp_ajax_local_ads_media_upload` | `edit_local_ads` + nonce | Picker upload |
| `wp_ajax_local_ads_check_schedule` | `edit_local_ads` + nonce | Live conflict check |
| `wp_ajax_local_ads_preview_data` | `edit_local_ads` + nonce | Unsaved form → preview payload |
| `admin_post_local_ads_*` | capability + nonce each | save_ad, ad_action, save_campaign, campaign_action, media_upload, media_replace, media_delete, save_settings, export_csv, tools, preview |

Bulk actions on the advertisements list run on the screen's `load-` hook (as core list screens do) and verify `bulk-local_ads`.

## Eligibility, concurrency and rotation

`Local_Ads_Ads::live_ads( $now )` selects ads that meet all of these:

- `status IN ('active','scheduled')`
- `media_id > 0`
- `start_gmt <= now <= end_gmt` (NULL bounds are open)
- their campaign is absent, deleted, or active and within its own dates

`apply_concurrency()` then limits the set:

- With concurrency off, the limit is 1.
- Otherwise the limit is `max_concurrent`, where 0 means unlimited.
- Ads are kept by priority (descending), then earliest start, then lowest ID.

When an ad is saved or activated, `Local_Ads_Scheduler::check_activation()` blocks it if:

- concurrency is off and any enabled ad overlaps, or
- the peak simultaneous count during the new window (computed at every start point) would exceed `max_concurrent`.

When an activation is blocked, the ad is saved as a draft with a message.

Rotation runs in the browser so that it respects each visitor's own state:

- **Weighted**: cumulative weights against `Math.random()`.
- **Priority**: the highest priority, then weighted among ties.
- **Sequential**: the next ID after the last one this visitor saw.

## Analytics and atomic counters

`Local_Ads_Analytics::record()` performs:

```sql
INSERT INTO {prefix}local_ads_daily_stats (ad_id, campaign_id, stat_date, impressions, clicks, created_at, updated_at)
VALUES (%d, %d, %s, %d, %d, %s, %s)
ON DUPLICATE KEY UPDATE {column} = {column} + 1, updated_at = VALUES(updated_at);
UPDATE {prefix}local_ads_ads SET {column} = {column} + 1 WHERE id = %d;
```

Both statements are atomic in MySQL, so concurrent requests never lose increments. `{column}` is chosen from a fixed whitelist, never from input. In testing, 50 simultaneous click processes added exactly 50.

The tracking endpoint has no nonce, because pages may be cached longer than a nonce lives. Instead:

- The ad must currently be live. Clicks get a 30-minute grace period so a popup open at the end time still counts.
- The request must carry a daily HMAC signature (`wp_salt('nonce')`, ad ID and site date) that was delivered with the live feed.
- Optionally, staff are excluded.

Previews use ID 0 and never call the endpoint.

`cleanup()` deletes daily rows older than `retention_days` (0 = forever) and event rows older than `event_retention_days`. Lifetime counters on the ads table are untouched.

## Security model

- **Capabilities**: `manage_local_ads` (settings, tools), `edit_local_ads`, `delete_local_ads`, `view_local_ads_analytics`, granted to administrators on activation and checked by every page and handler.
- **Nonces**: every state-changing request (`check_admin_referer` / `check_ajax_referer`); row actions use per-action, per-ID nonces.
- **Input**: `sanitize_text_field`, `sanitize_textarea_field`, `sanitize_key`, `absint`, whitelisted choices and clamped numbers; destination URLs limited to http(s) with a valid host; relative paths resolved with `home_url()`.
- **Output**: escaped with `esc_html`, `esc_attr`, `esc_url` and `esc_textarea`; JSON for scripts via `wp_json_encode` (with `JSON_HEX_TAG` in the preview document).
- **SQL**: `$wpdb->prepare()` for every value; identifiers come only from code.
- **Uploads**:
  - checked by `wp_check_filetype_and_ext` and `wp_getimagesize`, and the MIME type must be in the allow list;
  - double extensions such as `.php.jpg` and SVG are rejected;
  - files go through `wp_handle_upload` into the configured folder, and the result must resolve inside it;
  - the folder gets `index.php` and an `.htaccess` denying script execution.
- **Paths**: the folder setting must match `[A-Za-z0-9][A-Za-z0-9_-]*` segments (maximum 3), with no `..`, backslashes, colons or leading slash. Paths are verified with `realpath` containment before files are written or deleted.
- **CSV**: cells beginning with `= + - @` or tab/CR are prefixed with `'`.
- **Frontend payload**: image URL, size, alt text, destination, timing and display options only. No paths, descriptions or advertiser names.

## Time and timezones

- Schedules are entered in the site timezone (`wp_timezone()`), converted to UTC for storage, and compared against `time()`.
- Daily statistics use `wp_date( 'Y-m-d' )`, so a click at 23:30 Nairobi time lands on that Nairobi date, not the UTC date.
- The feed returns the server time and timezone offset. The browser applies them, so "once per day" and schedule checks don't depend on the visitor's clock.

## Options and cron

| Option | Purpose |
|---|---|
| `local_ads_settings` | All settings (array) |
| `local_ads_db_version`, `local_ads_version` | Upgrade tracking |
| `local_ads_live` | `{count, until}`: whether any ad may be live (autoloaded) |
| `local_ads_last_maintenance` | Timestamp of the last maintenance run |

Per-user transients `local_ads_notices_{user}` and `local_ads_form_{user}` carry admin notices and failed-form values across redirects.

The cron hook `local_ads_maintenance` runs every 15 minutes (custom schedule `local_ads_quarter_hour`). It syncs statuses, applies retention and refreshes `local_ads_live`. It is unscheduled on deactivation and uninstall.

## Internationalisation

Every user-facing string uses the `local-ads` text domain with literal strings, so `languages/local-ads.pot` can be regenerated with WP-CLI:

```bash
wp i18n make-pot . languages/local-ads.pot --exclude=docs,bin
```

## Building a release

```powershell
pwsh ./bin/build-zip.ps1
```

This creates `dist/local-ads.zip` containing one `local-ads/` folder with forward-slash entry names. Windows PowerShell 5.1 `Compress-Archive` writes backslashes, which some Linux hosts reject, so the script uses `System.IO.Compression` directly. Repository-only files are excluded: `docs/`, `bin/`, `dist/`, `.git*`, `README.md` and `CHANGELOG.md`.

Release steps:

1. Bump `Version` in `local-ads.php`, `LOCAL_ADS_VERSION`, `Stable tag` in `readme.txt` and the version in `public.js`.
2. Add a `CHANGELOG.md` entry.
3. Build the ZIP.
4. Tag `vX.Y.Z` and attach the ZIP to a GitHub release.

## Testing checklist

Release 1.0.0 was verified against WordPress 7.1.2 on PHP 8.2 and MariaDB 10.4, with a non-default table prefix and a non-UTC timezone:

- **Install**: upload ZIP, activate, deactivate (cron removed, data kept), reactivate, and uninstall with each data-deletion option.
- **Admin**: every screen renders with no PHP notices; create, validation errors, row and bulk actions, search, filters and sorting; settings persistence and invalid folder rejection; campaigns; CSV; tools; permission denial for editors and anonymous users; forged nonces rejected.
- **Media**: valid WEBP/PNG/JPG accepted; PHP files, disguised PHP and oversized files rejected; replace and delete rules.
- **Logic**: the A/B/C concurrency scenario on each date range; concurrency off and maximum limits; status sync; campaigns; targeting; timezone boundaries; daily click separation (10 + 25 = 35 lifetime); CTR with zero impressions; retention.
- **Concurrency**: 50 parallel processes clicking at once gave exactly +50.
- **Browser** (headless Chrome):
  - The acceptance scenario: no popup before scrolling or at 2%, a popup after 5% with fade, shake between about 2 and 5 seconds then stopping, link attributes, focus trap, manual close, no reopen on scroll or reload in the same session.
  - Clicking: closes after click and records the click, and the popup shows again in a new session.
  - Closing and motion: 10-second auto-close, Escape and overlay close, reduced motion.
  - Layout: 360–414 px phones, tablet and desktop with no overflow.
  - One popup with three live ads; rotation modes (weighted 300 loads ≈ 52/27/21 for weights 50/30/20).
  - Expiry with cron disabled; previews never tracked.
