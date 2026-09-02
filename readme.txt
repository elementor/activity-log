=== Activity Log - Monitor & Record User Changes ===
Contributors: elemntor, KingYes, ariel.k
Tags: Activity Log, User Log, Audit Log, Security, Email Log,
Requires at least: 6.2
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 2.14.1
License: GPLv2 or later

Monitor every change on your WordPress site — who did what, when, and where it came from — for a complete audit trail and stronger security.

== Description ==

<strong>An easy to use, fully supported WordPress activity log plugin.</strong><br />

Want to know exactly who does what on your WordPress site? Activity Log works like an airplane’s black box: it quietly records every action in the WordPress admin — and now every request made through the REST API, WP-CLI, WP-Cron, and more — so you always know:

* If someone is trying to hack your site
* When a post was published, and who published it
* If a plugin/theme was activated/deactivated
* Suspicious admin activity

The plugin doesn’t require any setup; it works right out of the box, runs on its own database table so it doesn’t slow down your site, and stays out of your way until you need it.

<h3>What's New</h3>

* <strong>Request Source Tracking</strong> - See exactly where each change came from: the WP Admin, the REST API, WP-CLI, WP-Cron, XML-RPC, or the WP Abilities API, including which Application Password was used. Filter the log by source to quickly spot automated or API-driven changes alongside manual admin activity.
* <strong>Email Logging</strong> - Capture all emails sent from your WordPress site for streamlined debugging and compliance. Especially useful for WooCommerce stores tracking order emails alongside other site events.
* <strong>Export to CSV</strong> - Export your Activity Log data to CSV, or build support for your own format with our dedicated Export API.
* <strong>Data Privacy and GDPR Compliance</strong> - Export or erase log data directly through the WordPress Privacy Tools.

If you have more than a handful of users, keeping track of who did what by hand is virtually impossible. Activity Log solves that by tying every action back to the user who triggered it, in an easy-to-filter view right on your WordPress dashboard.

<h3>With the Activity Log you can record:</h3>

* <strong>WordPress</strong> - Core updates
* <strong>Posts</strong> - Created, updated, deleted
* <strong>Pages</strong> - Created, updated, deleted
* <strong>Custom Post Type</strong> - Created, updated, deleted
* <strong>Tags</strong> - Created, updated, deleted
* <strong>Categories</strong> - Created, updated, deleted
* <strong>Taxonomies</strong> - Created, updated, deleted
* <strong>Menus</strong> - Created, updated, deleted
* <strong>Media</strong> - Created, updated, deleted
* <strong>Comments</strong> - Created, approved, unapproved, trashed, untrashed, spammed, unspammed, deleted
* <strong>Users</strong> - Login, logout, login failed, update profile, registered, deleted
* <strong>Plugins</strong> - Installed, updated, activated, deactivated, changed
* <strong>Themes</strong> - Installed, updated, deleted, activated, changed (Editor and Customizer)
* <strong>Widgets</strong> - Added to sidebar, deleted from sidebar, order widgets
* <strong>Setting</strong> - General, writing, reading, discussion, media, permalinks
* <strong>Options</strong> - Extended custom settings for 3rd party plugins
* <strong>Export</strong> - Exported activity log file
* <strong>Request Source</strong> - WP Admin, REST API, WP-CLI, WP-Cron, XML-RPC, WP Abilities, and Application Password name when used
* <strong>WooCommerce</strong> - Track products, orders, customers, and more
* <strong>bbPress</strong> - Forums, topics, replies, taxonomies, and other actions
* <strong>Emails sent from WordPress site</strong> - Sending successful, sending failed
* There’s more, of course, but you get the point...

For each event recorded by the activity log, the following details are also logged:

* Date and time of occurrence
* User and user role responsible for the change
* Source IP address from which the change originated
* Request source — WP Admin, REST API, WP-CLI, WP-Cron, XML-RPC, or WP Abilities
* Affected object where the change occurred

<h3>Data Storage and Performance</h3>

All events are stored in a dedicated custom database table, keeping the impact on your site's performance to a minimum — even under heavy traffic.

<h3>Uninstall Clean-up</h3>

Uninstalling the plugin removes all of its data from your database automatically, leaving nothing behind.


<h3>What users have to say</h3>

* <em>“Its tools, particularly for data privacy and GDPR compliance, make it indispensable for websites operating within European Union boundaries or dealing with EU citizens’ data”</em> - [HubSpot.com](https://blog.hubspot.com/website/8-best-plugins-tracking-user-activity-wordpress)
* <em>“If you’re after a competent WP security audit log plugin with all the basic features you need, Activity Log is it!”</em> - [WPAstra.com](https://wpastra.com/plugins/wordpress-activity-log-plugins/)
* <em>“Activity Log features a remarkably straightforward dashboard interface, providing administrators with an at-a-glance understanding of site interactions”</em> - [Malcare.com](https://www.malcare.com/blog/wordpress-activity-log/)
* <em>“Thanks to this step, we’ve discovered that our site was undergoing a brute force attack”</em> - [Artdriver.com](https://artdriver.com/blog/wordpress-site-hacked-solution-time)
* <em>“Activity Log lets you track a huge range of activities. Overall, very easy to use and setup”</em> - [ElegantThemes.com](https://www.elegantthemes.com/blog/tips-tricks/5-best-ways-to-monitor-wordpress-activity-via-the-dashboard)

<h3>Contributions:</h3>
<strong>Would you like to contribute to this plugin?</strong> You’re more than welcome to submit your pull requests on the [GitHub repo](https://github.com/pojome/activity-log). And, if you have any notes about the code, please open a ticket on the issue tracker.

== Installation ==

1. Upload plugin files to your plugins folder, or install using WordPress' built-in Add New Plugin installer
1. Activate the plugin
1. Go to the plugin page (under Dashboard > Activity Log)

== Screenshots ==

1. The log viewer page
2. The settings page
3. Screen Options

== Frequently Asked Questions ==

= Requirements =
__Requires PHP 7.4__ for list management functionality.

= What is the plugin license? =

This plugin is released under a GPL license.

= Will this slow down my site? =

No. Activity Log stores every event in its own dedicated database table instead of mixing into WordPress' core tables, so logging has minimal impact on your site's performance, even under heavy traffic.

= Can I export logs? =

You can easily export logs with Activity Log. We also support exporting filtered results. Filter by the time the action took place, roles, users, options, action type, and more.

= Can I see where a change came from? =

Yes. Each log entry records its Request Source, so you can tell whether a change was made through the WP Admin, the REST API, WP-CLI, WP-Cron, XML-RPC, or the WP Abilities API. If the request was authenticated with an Application Password, its name is shown as well. Use the Source filter on the log screen to narrow results down to a specific channel.

= How long are logs kept? =

By default, logs are kept for 30 days, but you can set your own retention period from the settings page — from a few days to forever. Failed login attempts and email logs can also be kept or discarded independently.

= Do I have to collect visitor IP addresses? =

No. If you'd rather not store IP addresses for privacy reasons, set the "Visitor IP Detected" option to "Do not collect IP" and the IP column will be hidden going forward.

= Does this work on WordPress Multisite? =

Yes. Activity Log fully supports Multisite, logging activity per site and cleaning up its data correctly when a site is removed from the network.

= Does this work with WooCommerce? =

Yes. Products, orders, and customers are tracked right out of the box, since Activity Log records changes across any post type and taxonomy — no extra setup needed.

= Can I exclude specific events from being logged? =

Yes. Beyond the built-in toggles for failed logins and email logs, developers can hook into the `aal_skip_insert_log` filter to skip logging for any event on demand.

= How can I report security bugs? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability](https://patchstack.com/database/vdp/aryo-activity-log).

== Changelog ==

= 2.14.1 - 2026-09-02 =
* Tweak: Moved Application Password badge from Source column to User column

= 2.14.0 - 2026-09-01 =
* New: Redesigned activity log admin UI built with React
* Tweak: Renamed date filter options from "Week" / "Month" to "Last 7 days" / "Last 30 days" for clarity

= 2.13.1 - 2026-08-26 =
* Fix: CSV export file missing IP column ([Topic](https://wordpress.org/support/topic/missing-source-ip-in-csv-export/))

= 2.13.0 - 2026-08-24 =
* Removed: Email notifications feature (hidden since 2.5 for sites that never enabled it) has been fully removed

= 2.12.1 - 2026-08-20 =
* Tweak: Removed text domain loading method in favor of WordPress standard loading
* Fix: Re-release for WordPress.org after a failed deploy

= 2.12.0 - 2026-08-19 =
* New: Request Source Tracking - See where each change came from (REST API, WP-CLI, WP-Cron, XML-RPC, WP Abilities, Application Passwords)
* New: Added metadata storage for extensible log context
* Tweak: Large activity log tables no longer auto-run database migrations — an admin notice with a manual upgrade button is shown instead

= 2.11.2 - 2024-11-12 =
* Security Fix: Improved code security enforcement in theme/plugin file editor

= 2.11.1 - 2024-11-05 =
* Tweak: Added ability to search in context column

= 2.11.0 - 2024-07-29 =
* New: Added logging for enabling and disabling automatic theme updates
* New: Added logging for enabling and disabling automatic plugin updates
* New: Added logging for enabling and disabling automatic core updates

= 2.10.1 - 2024-04-17 =
* Tweak: Added option to not keep email logs ([Topic](https://wordpress.org/support/topic/activity-log-email-off-on-option/))

= 2.10.0 - 2024-04-08 =
* New: Introducing Email Logging - Capture all emails sent from your WordPress site
* Tweak: Added filter to change menu page capability ([#205](https://github.com/pojome/activity-log/pull/205))
* Tweak: Set the date display on CSV export file according to WordPress settings ([#204](https://github.com/pojome/activity-log/pull/204))

[See changelog for all versions.](https://github.com/elementor/activity-log/blob/master/changelog.txt)
