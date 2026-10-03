# Local Ads user guide

This guide covers every screen and setting in Local Ads by Bernard 1.0. It is written for site administrators; no coding is needed.

- [Installation](#installation)
- [How Local Ads decides what to show](#how-local-ads-decides-what-to-show)
- [Creating an advertisement](#creating-an-advertisement)
- [Statuses and manual controls](#statuses-and-manual-controls)
- [Scheduling](#scheduling)
- [Concurrent campaigns and rotation](#concurrent-campaigns-and-rotation)
- [Campaigns](#campaigns)
- [Media](#media)
- [Analytics](#analytics)
- [Settings reference](#settings-reference)
- [Tools](#tools)
- [Permissions](#permissions)
- [Privacy](#privacy)
- [Uninstalling](#uninstalling)
- [Troubleshooting](#troubleshooting)

## Installation

1. Download `local-ads.zip` from the latest release.
2. Go to **Plugins → Add New → Upload Plugin**, choose the file and click **Install Now**.
3. Click **Activate**.

Activation creates five database tables, the image folder `wp-content/uploads/local-ads/`, the administrator permissions and a background maintenance task that runs every 15 minutes. The **Local Ads** menu is available straight away.

## How Local Ads decides what to show

On every page view:

1. Local Ads must be enabled (**Settings → General**).
2. The page must match the ad's targeting and not be excluded.
3. The ad must be Active or Scheduled, inside its start and end time, have an image, and its campaign (if any) must be active and within its dates.
4. Concurrency rules are applied (see [Concurrent campaigns](#concurrent-campaigns-and-rotation)).
5. The visitor's frequency state is checked in their browser.
6. One ad is chosen by the rotation method.
7. The popup waits for its trigger, then opens.

An impression is counted only when the popup has actually been displayed with its image loaded. A click is counted when the visitor clicks the advertisement.

A visitor never sees more than one popup per page view, however many campaigns are running.

## Creating an advertisement

Go to **Local Ads → Add New**. The screen has these sections.

### Advertisement details

| Field | Notes |
|---|---|
| Advertisement name | Required. Shown in the admin only. |
| Advertiser / company | Optional, admin only. |
| Internal description | Optional notes, admin only. |
| Campaign | Optional. Assign the ad to a campaign. |
| Priority | 1–100. Used by the Priority rotation method and to decide which ads keep running when a concurrency limit applies. |
| Weight | 1–100. Used by Weighted random rotation. Weight 50 is shown about five times as often as weight 10. |

### Advertisement image

Click **Upload or select image**. In the picker you can upload a new image or choose one you uploaded before; double-click selects and closes. **Remove image** clears it.

Accepted formats: JPG, PNG, WEBP and GIF (GIF can be disabled), up to the size in **Settings → Storage** (default 5 MB).

**Alt text** describes the advert for screen-reader users, for example "Kenya safari special: 20% off October departures". Leave it empty only if the image is purely decorative.

An advertisement cannot be activated without an image. If you try, it is saved as a draft and you are told why.

### Destination

- **Destination URL**: optional. Use a full URL for other sites or a path such as `/offers/` for your own site. Without a URL the ad is image-only.
- **Open destination in a new tab**: uses `target="_blank"` with `rel="noopener noreferrer"`. Default: on.
- **Close the advertisement after it is clicked**. Default: on.

### Display rules

- **Show popup**: Immediately · After X seconds · When visitor starts scrolling · After X pixels scrolled · After X% page scroll. The trigger fires once per page view; scrolling again never reopens it.
- **Visitor frequency**:
  - *Once per browser session*: until the browser or tab is closed (default).
  - *Once per visit*: a visit ends after 30 minutes without activity.
  - *Once per day*: per calendar day in the site timezone.
  - *Every X hours*.
  - *Always eligible*: may show on every page view.
- **Auto close**: closes the popup after 10, 30, 60, 120 seconds or a custom time. Default: 120 seconds.
- **Popup width**: desktop maximum in pixels (default 600) and mobile maximum as a percentage of screen width (default 92%) for screens up to 600 px wide.

### Animation

- **Entrance animation**: Fade In, Slide Up, Slide Down, Slide Left, Slide Right, Zoom, Bounce, Shake or None.
- **Attention shake**: shakes the popup briefly after it appears. Set the delay (default 2 s), duration (default 3 s) and intensity (Low, Medium, High). The shake always stops after the duration.

Visitors whose device requests reduced motion see no animation or shake.

### Schedule

Enter start and end dates and times in the site timezone (shown on screen; change it in **Settings → General → Timezone** in WordPress). Leave the start empty to start immediately and the end empty to run indefinitely. An end date without a time runs until 23:59.

While you edit the dates, Local Ads checks for overlapping advertisements and shows them under the schedule.

### Page targeting

- **Entire website** (default), or **Only on selected locations**: Homepage, All blog posts, All pages, All WooCommerce products, selected pages, selected posts and extra IDs.
- **Never display on**: Homepage, Checkout, Cart, Login and account pages (checkout, cart and login are excluded by default), selected pages and excluded URLs.

Excluded URLs take one rule per line. Use a path (`/contact/`) or full URL; `*` matches anything (`/members/*`). Trailing slashes do not matter.

Ads never display in the WordPress admin, on the login screen, in feeds or in the Customizer.

### Publishing and preview

In the **Publish** box choose **Active (follows schedule)**, **Paused** or **Draft**, then click **Publish advertisement** or **Update advertisement**.

**Preview** opens the popup on a sample page, using your unsaved changes. Switch between **Desktop** and **Mobile** and use **Replay animation**. Previews never count as impressions or clicks, and links in a preview do not navigate.

If something is invalid (for example an end before the start, or a bad URL), nothing is saved, a message explains the problem, and the form keeps what you typed.

## Statuses and manual controls

| Status | Meaning | Displays? |
|---|---|---|
| Draft | Not published. Duplicates start as drafts. | No |
| Scheduled | Active, but the start time is in the future. | From the start time |
| Active | Within its schedule. | Yes, if otherwise eligible |
| Paused | Stopped by an administrator. | No |
| Expired | The end time has passed. | No |

Scheduled and Expired are set automatically. Row actions on **Local Ads → Advertisements** and links in the Publish box:

- **Activate**: enables the ad (it becomes Active or Scheduled depending on its dates). Not possible once the end date has passed; edit the schedule first.
- **Start now**: for a scheduled ad, moves the start time to now.
- **Pause**: stops display immediately, whatever the schedule.
- **Deactivate**: returns the ad to Draft.
- **Duplicate**: creates a draft copy with zero statistics.
- **Delete**: removes the ad. Its past statistics are kept so campaign totals stay correct.

The list supports search, status views (All, Active, Scheduled, Paused, Expired, Draft), sorting by name, status, start, end, priority, impressions and clicks, and bulk **Activate**, **Pause** and **Delete**.

## Scheduling

Start and end times are checked on every request, and again in the visitor's browser just before the popup opens. An ad therefore starts and stops on time even on sites where WP-Cron is disabled or rarely triggered. If an ad reaches its end time while its popup is open, the popup closes.

The background task (every 15 minutes) only keeps the admin statuses tidy and applies data retention.

## Concurrent campaigns and rotation

**Settings → Scheduling** controls overlapping schedules.

- **Allow concurrent campaigns** (default on): ads with overlapping schedules all run during the overlap. When you save an overlapping ad you get a warning, but it is activated.
- With it **off**, activating an ad that overlaps another enabled ad is blocked (the ad is saved as a draft with an explanation), and only one ad runs at a time.
- **Maximum concurrent campaigns** (0 = unlimited): activations that would make more ads run at once are blocked. If more are running anyway, only the highest-priority ones are shown.

Example with concurrency on: Ad A runs 1–31 October, Ad B 10–20 October and Ad C 15–25 October.

| Dates | Eligible |
|---|---|
| 1–9 Oct | A |
| 10–14 Oct | A, B |
| 15–20 Oct | A, B, C |
| 21–25 Oct | A, C |
| 26–31 Oct | A |

The **rotation method** chooses which eligible ad a visitor sees:

- **Random** (default): equal chance.
- **Sequential**: each visitor cycles through the eligible ads in order.
- **Priority**: highest priority wins; ties are decided by weight.
- **Weighted random**: chance in proportion to weight.

**Local Ads → Schedule** shows a monthly calendar with one bar per ad (hatched bars are paused or draft) and a bottom row counting how many enabled ads run each day. Days with overlaps are highlighted, and days above your limit are marked red.

## Campaigns

**Local Ads → Campaigns** groups related ads, for example "October Safari Promotion" with Kenya, Tanzania, Uganda and Rwanda ads.

A campaign has a name, description, status (Active, Paused, Draft) and optional start and end dates. Ads in a paused or draft campaign never display, and ads only display inside both the campaign dates and their own schedule.

Row actions: Edit, View Analytics, View ads, Add ad, Pause/Activate and Delete. Deleting a campaign keeps its ads (they become unassigned) and their history.

## Media

**Local Ads → Media** lists every advertisement image with thumbnail, file name, dimensions, size, the ads using it, upload date and status (*In use (live)*, *Assigned* or *Unused*).

- **Preview** opens the image full size.
- **Use for Advertisement** starts a new ad with that image.
- **Replace** uploads a new file but keeps the same image record, so every ad using it switches to the new picture.
- **Delete** is blocked while an active or scheduled ad uses the image. Inactive ads using it are left without an image.

Files are checked by their real content, not only the file name. PHP and other scripts are rejected, including disguised files such as `photo.php.jpg`, and the folder blocks script execution.

## Analytics

**Local Ads → Analytics** shows:

- Totals for the selected period: impressions, clicks and CTR.
- Charts of daily impressions, clicks and CTR. Hover a day for its figures.
- **Performance by period**: today, yesterday, last 7 days, last 30 days, this month, last month and lifetime.
- **Advertisement performance** and **Campaign performance** tables. Click a name to drill down.
- **Daily statistics**: one row per day.

Filter by period (including a custom range), advertisement and campaign. **View Analytics** on an ad or campaign opens the same screen filtered to it.

CTR = clicks ÷ impressions × 100, and 0% when there are no impressions. Days are calendar days in the site timezone.

**Export CSV** downloads the current filter as `Date, Advertisement, Campaign, Impressions, Clicks, CTR`, one row per ad per day. It contains no visitor information.

The **Dashboard** summarises ad counts by status, today's and lifetime performance, a 14-day chart, the ads running now, top performers, and recently created and expired ads.

## Settings reference

| Tab | Setting | Default |
|---|---|---|
| General | Enable Local Ads | On |
| | Default frequency (for new ads) | Once per browser session |
| | Default animation | Fade In |
| | Default popup width / mobile width | 600 px / 92% |
| | Show "Advertisement" label | On |
| Popup | Default trigger and thresholds | When visitor starts scrolling; 3 s, 300 px, 5% |
| | Pages that cannot scroll: show after | 5 s (0 = never) |
| | Default auto close | On, 120 s |
| | Overlay, opacity, close on overlay click | On, 0.55, on |
| | Close button size, position, visibility | Medium, top right, always visible |
| | Border radius, stacking order (z-index) | 8 px, 999999 |
| Animation | Default entrance animation, speed | Fade In, 400 ms |
| | Default shake, delay, duration, intensity | Off, 2 s, 3 s, Medium |
| Behaviour | Open links in new tab / close after click (defaults) | On / On |
| | Global restriction after any ad is closed | None |
| Scheduling | Allow concurrent campaigns | On |
| | Maximum concurrent campaigns | 0 (unlimited) |
| | Rotation method | Random |
| Storage | Upload folder (under `wp-content/uploads/`) | `local-ads` |
| | Maximum image size | 5 MB |
| | Allow GIF | On |
| Analytics | Enable analytics | On |
| | Data retention (daily statistics) | Forever |
| | Event log retention | 30 days |
| | Exclude staff from statistics | Off |
| Advanced | Debug mode (browser console) | Off |
| | Delete data on uninstall / also delete images | Off / Off |

Defaults marked "for new ads" are copied into each new advertisement, where they can be changed individually. Popup appearance settings (overlay, close button, radius) apply to all ads.

The upload folder accepts letters, numbers, dashes and underscores, up to three levels (`ads/2026`). Anything else, such as `../`, absolute paths or drive letters, is rejected and the previous folder kept. Existing images stay where they were uploaded.

Data retention removes old **daily statistics** only. Advertisements and their lifetime totals are never removed by retention.

## Tools

- **Run maintenance now**: updates statuses and applies retention immediately.
- **Repair installation**: re-creates missing tables, permissions, the cron schedule and the protected folder.
- **Clear event log**: removes the raw event log; reports are unaffected.
- **Reset all analytics**: deletes all statistics and resets lifetime totals (requires a confirmation tick).
- **System status**: versions, timezone, folder and permissions, upload limit, cron timing, table row counts and recent tracking events.

## Permissions

| Capability | Allows |
|---|---|
| `view_local_ads_analytics` | Dashboard and Analytics, CSV export |
| `edit_local_ads` | Advertisements, campaigns, media, schedule |
| `delete_local_ads` | Deleting ads, campaigns and images |
| `manage_local_ads` | Settings and Tools |

Administrators receive all four on activation. Grant them to other roles with any role-editor plugin.

## Privacy

Local Ads stores no IP addresses, user agents, names, email addresses or browsing histories. Statistics are counts per ad per day, plus a short event log with only the ad, the event type and the time.

Frequency control uses the visitor's own browser storage (`sessionStorage` and `localStorage`, keys starting `localAds:`). This data never leaves the browser. You may wish to mention it in your privacy policy.

The only outside request Local Ads makes is the update check described under [Updates](#updates).

## Updates

New versions are published on GitHub. Local Ads checks for them about twice a day and shows them on **Dashboard → Updates** and the **Plugins** screen like any other plugin, so you can update with one click or turn on automatic updates. **View details** shows the release notes. Click **Check again** on **Dashboard → Updates** to look straight away.

The check sends no site, ad or visitor data. To turn it off, add `add_filter( 'local_ads_github_updates', '__return_false' );` to a small plugin or your theme's `functions.php`.

## Uninstalling

- **Deactivate** stops background maintenance and keeps everything.
- **Delete** removes data only if **Settings → Advanced → Delete all Local Ads data** is ticked: settings, ads, campaigns, analytics tables, permissions and temporary data.
- Image files are deleted only if **Also delete advertisement image files** is ticked as well.

## Troubleshooting

**The popup does not appear.** Check that Local Ads is enabled; the ad is Active (not Scheduled, Paused, Expired or Draft) and has an image; its campaign is active; and the page matches its targeting. You may already have seen it this session, so try a private window. Turn on **Settings → Advanced → Debug mode** and open the browser console to see the reason.

**It doesn't appear on cached pages.** Ads are fetched live, so cached pages normally work. If pages were cached while no ad was running, they will not include the Local Ads script; purge the page cache once.

**Statistics are not increasing.** Check **Enable analytics**, and whether **Exclude staff** is on while you test logged in.

**Statuses look out of date.** They refresh when you open Local Ads screens and every 15 minutes. If WP-Cron is disabled, use **Tools → Run maintenance now**. Display is never affected.

**An upload fails.** Check the maximum size in **Settings → Storage** and the server limit shown there, and that the file is a real JPG, PNG, WEBP or GIF.

**Another element covers the popup.** Raise **Settings → Popup → Stacking order**.

---

Local Ads by Bernard · © 2026 Bernard Ogak, [Creative Bay](https://www.creativebay.co.ke) · GPL-2.0-or-later
