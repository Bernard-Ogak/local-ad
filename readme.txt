=== Local Ads by Bernard ===
Contributors: bernardogak
Tags: ads, advertising, popup, banner, analytics
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A locally hosted advertisement management, scheduling and analytics platform for WordPress. Popups, rotation, concurrent campaigns and daily click tracking, with no third-party ad network.

== Description ==

Local Ads lets you sell and run your own advertisements directly from the WordPress dashboard. Images are stored on your own server and statistics in your own database. No ad, visitor or statistics data is sent anywhere; the only outside request is the check for new versions on GitHub (see External services).

Each advertisement is an image popup with an optional destination link. You decide when it appears (on scroll, after a delay or immediately), how often a visitor sees it, which pages it runs on, and when it starts and stops. Several campaigns can run at the same time; the rotation engine chooses one popup per visitor so people are never shown a stack of popups.

= Features =

* **Advertisement management**: create, edit, duplicate, activate, pause, deactivate and delete advertisements; search, status filters, sorting and bulk actions.
* **Local media**: images live in `wp-content/uploads/local-ads/` (configurable), separate from the Media Library. Upload, replace, preview and delete with MIME, content and size validation.
* **Popup display**: responsive popup with overlay, accessible close button, Escape-to-close, focus handling and an optional "Advertisement" label.
* **Triggers**: immediately, after X seconds, when the visitor starts scrolling, after X pixels or after X% of the page.
* **Animations**: fade, slide up/down/left/right, zoom, bounce and shake entrances, plus a temporary attention shake with delay, duration and intensity. All motion is disabled for visitors who request reduced motion.
* **Visitor frequency**: once per browser session, once per visit, once per day, every X hours or always. Tracked per advertisement in the browser, with an optional global restriction.
* **Scheduling**: start and end date/time in the site timezone. Ads start and stop on time even if WP-Cron has not run.
* **Concurrent campaigns**: allow overlapping schedules (or not), cap the number running at once, see overlaps on the campaign calendar and get conflict warnings while editing.
* **Rotation**: random, sequential, priority (1–100) or weighted random.
* **Campaigns**: group ads, pause a whole campaign, bound it with dates and see aggregate analytics with drill-down.
* **Analytics**: impressions, clicks, daily clicks, daily impressions, CTR and lifetime totals; per-ad and per-campaign reports; today, yesterday, last 7/30 days, this month, last month and custom ranges; CSV export.
* **Page targeting**: whole site, homepage, blog posts, pages, WooCommerce products, selected pages and posts, with exclusions for the homepage, checkout, cart, login/account pages, selected pages and URL patterns.
* **Performance**: one small deferred script (no libraries), styles loaded only when a popup opens, nothing loaded when no ad is running, admin assets only on Local Ads screens.
* **Cache friendly**: the page carries only a tiny context object; eligible ads are fetched live, so cached pages still respect status, schedule and frequency.

== Requirements ==

* WordPress 5.8 or later
* PHP 7.4 or later
* MySQL 5.6+ or MariaDB 10.1+

== Installation ==

1. In your dashboard go to **Plugins → Add New → Upload Plugin**.
2. Choose `local-ads.zip` and click **Install Now**.
3. Click **Activate**.
4. Open **Local Ads** in the admin menu.

Activation creates the database tables, the protected upload folder and the administrator capabilities, and schedules background maintenance.

== Configuration ==

Open **Local Ads → Settings**. Settings are grouped into tabs:

* **General**: turn advertising on or off, default frequency, animation and widths, and the "Advertisement" label.
* **Popup**: default trigger and thresholds, fallback for pages that cannot scroll, default auto close, overlay, close button size/position/visibility, border radius and stacking order.
* **Animation**: default entrance animation, speed and shake.
* **Behaviour**: default link behaviour and an optional global frequency restriction.
* **Scheduling**: concurrent campaigns, maximum concurrent campaigns and the rotation method.
* **Storage**: upload subfolder, maximum image size and GIF support.
* **Analytics**: enable tracking, data retention, event log retention and excluding staff from statistics.
* **Advanced**: debug mode and uninstall behaviour.

Defaults for triggers, frequency, auto close, animation, shake, links and widths are copied into each new advertisement, where they can be changed individually.

== Creating advertisements ==

1. Go to **Local Ads → Add New**.
2. Enter a name and, optionally, the advertiser, an internal description and a campaign.
3. Click **Upload or select image**, then upload a new image or choose an existing one. Add alt text that describes the offer.
4. Enter the destination URL (full URL, or a path such as `/offers/` for your own site) and choose whether it opens in a new tab and whether the popup closes after a click.
5. Set the display rules, animation, schedule and page targeting.
6. Click **Preview** to see the popup on desktop and mobile. Previews are never counted.
7. Choose **Active (follows schedule)** and click **Publish advertisement**.

== Scheduling ==

Times are entered in the site timezone (Settings → General → Timezone). Leave the start empty to start immediately and the end empty to run indefinitely. An end date without a time runs until 23:59.

* Before the start time the advertisement is **Scheduled** and does not display.
* During the schedule it is **Active** and displays when otherwise eligible.
* After the end time it is **Expired** and stops displaying.

The frontend checks start and end times on every request and again in the browser at trigger time, so ads start and stop on time without relying on WP-Cron. WP-Cron runs every 15 minutes to keep the admin statuses and statistics tidy.

Manual overrides: **Activate**, **Pause**, **Deactivate** (back to draft) and, for scheduled ads, **Start now**. A paused advertisement never displays.

== Concurrent campaigns ==

With **Allow concurrent campaigns** on (default), advertisements with overlapping schedules all run during their overlap. Example: Ad A runs 1–31 October, Ad B 10–20 October and Ad C 15–25 October. On 1–9 October only A is eligible, on 10–14 A and B, on 15–20 A, B and C, on 21–25 A and C, and on 26–31 only A.

Each page view still shows at most one popup. The rotation method chooses it:

* **Random**: equal chance.
* **Sequential**: cycles through eligible ads in order for each visitor.
* **Priority**: the highest priority wins; ties are broken by weight.
* **Weighted random**: chance proportional to weight (weights 50/30/20 give roughly 50%/30%/20%).

With concurrency off, activating an ad that overlaps another enabled ad is blocked, and only one ad runs at a time. **Maximum concurrent campaigns** caps how many can run at once; activations that would exceed it are blocked.

The **Schedule** screen shows a monthly calendar with a bar per advertisement and a count of how many run on each day.

== Analytics ==

An impression is counted only when the popup has been displayed with its image loaded. Clicks are counted when the advertisement link is clicked. Each event updates:

* the daily row for that advertisement and date (site timezone),
* the advertisement's lifetime totals, and
* a short-lived event log.

Daily rows use a single atomic `INSERT ... ON DUPLICATE KEY UPDATE`, so simultaneous clicks are never lost. CTR is clicks ÷ impressions × 100 and is 0% when there are no impressions.

**Local Ads → Analytics** shows totals, a daily chart, performance by period, per-advertisement and per-campaign tables and a daily table, filtered by period, advertisement and campaign. Click an advertisement or campaign to drill down. **Export CSV** downloads `Date, Advertisement, Campaign, Impressions, Clicks, CTR` for the current filters.

== Click tracking ==

Clicks are sent with `navigator.sendBeacon()` (falling back to `fetch` with `keepalive`), so the destination opens immediately and tracking survives navigation. New-tab links use `target="_blank"` and `rel="noopener noreferrer"`.

== Media storage ==

Images are stored in `wp-content/uploads/{folder}/` where `{folder}` is set in **Settings → Storage** (default `local-ads`). The path is built from `wp_upload_dir()`, so it works with custom upload locations. Folder names are restricted to letters, numbers, dashes and underscores; `../`, absolute paths and other traversal attempts are rejected.

Uploads are checked by extension, real content type and image decoding, and size-limited. The folder receives an `index.php` and an `.htaccess` that blocks script execution. Images used by a live advertisement cannot be deleted. **Replace** swaps the file but keeps every advertisement that uses it.

== Troubleshooting ==

* **The popup does not appear**: check that Local Ads is enabled, the ad is Active (not Scheduled, Paused or Expired), it has an image, and the page matches its targeting. You may already have seen it this session: open a private window or clear site data. Turn on **Settings → Advanced → Debug mode** to see the reason in the browser console.
* **It appears on a page that is cached**: this is expected; ads are fetched live. If a popup never appears on cached pages after enabling the first ad, purge the page cache once so pages include the Local Ads script.
* **Statistics are not increasing**: check **Settings → Analytics → Enable analytics** and **Exclude staff** (staff clicks are not counted when that is on).
* **Ads did not change status**: statuses in the admin are refreshed whenever you open Local Ads screens and by WP-Cron. Use **Tools → Run maintenance now** if WP-Cron is disabled.
* **Upload fails**: check the maximum size in **Settings → Storage** and your server upload limit shown there.

== Security ==

* Every admin action checks capabilities (`manage_local_ads`, `edit_local_ads`, `delete_local_ads`, `view_local_ads_analytics`) and a nonce.
* All input is sanitized and all output escaped; all SQL uses `$wpdb->prepare()` and `$wpdb->prefix`.
* Uploads are validated by content, not just filename, and stored in a folder that blocks script execution.
* The frontend receives only what it needs to show an ad: image URL, size, alt text, destination, timing and display options.
* The public tracking endpoint accepts only valid ad IDs with a daily signature and only counts ads that are actually live.

== Privacy ==

Local Ads does not store IP addresses, user agents, names, email addresses or browsing histories. Statistics are aggregate counts per advertisement per day plus a short event log containing only the ad, event type and time.

Frequency control uses the visitor's browser storage (`sessionStorage` for once-per-session, `localStorage` for longer intervals) with keys prefixed `localAds:`. This data never leaves the browser. Depending on your jurisdiction you may wish to mention it in your privacy policy.

== Uninstallation ==

Deactivating the plugin stops background maintenance but keeps all data.

Deleting the plugin removes data only if **Settings → Advanced → Delete all Local Ads data** is ticked. It then removes the settings, advertisements, campaigns, analytics tables, capabilities and transients. Image files are deleted only if **Also delete advertisement image files** is ticked as well.

== Frequently Asked Questions ==

= Can more than one popup show at once? =

No. Several campaigns may be active, but each page view shows at most one popup.

= Does closing one ad hide all ads? =

No. Frequency is tracked per advertisement. Use **Settings → Behaviour → Global restriction** if you want closing any ad to suppress the others.

= Do previews count as impressions? =

No. Previews, drafts, paused, scheduled and expired ads are never counted.

== Changelog ==

= 1.0.2 =
* Added: updates from GitHub. New releases of Local Ads appear on Dashboard → Updates and the Plugins screen like any other plugin update, with one-click and automatic updates.
* Fixed: the translation template header reported version 1.0.0.

= 1.0.1 =
* Added: a `localads:track` browser event (detail: `id`, `type`) each time an ad is shown or clicked, so analytics tools such as Blue Lens Analytics can relate ad clicks to visits and conversions.

= 1.0.0 =
* Initial release.

== External services ==

Local Ads checks GitHub for new versions of itself, so that updates appear on the WordPress update screens.

* What it is for: finding the latest release of Local Ads and downloading its ZIP when you update.
* What is sent and when: about twice a day, and when you click "Check again" on Dashboard → Updates, your server requests `https://api.github.com/repos/Bernard-Ogak/local-ad/releases/latest`. The request carries only the plugin version in its user agent; no site address, ad, visitor or statistics data is sent. GitHub receives your server's IP address, as with any web request. The release ZIP is downloaded from github.com only when an update is installed.
* To turn it off: `add_filter( 'local_ads_github_updates', '__return_false' );`
* Terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
* Privacy policy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

== License ==

Local Ads by Bernard is free software released under the GNU General Public License version 2 or later.

== Author ==

Bernard Ogak, Creative Bay: https://www.creativebay.co.ke

Source code and issues: https://github.com/Bernard-Ogak/local-ad
