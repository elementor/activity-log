# Activity Log - Monitor & Record User Changes [![Build Status](https://travis-ci.org/pojome/wordpress-aryo-activity-log.svg?branch=master)](https://travis-ci.org/pojome/wordpress-aryo-activity-log) [![Dependency Status](https://david-dm.org/pojome/wordpress-aryo-activity-log/dev-status.svg)](https://david-dm.org/pojome/wordpress-aryo-activity-log#info=devDependencies) [![WordPress](https://img.shields.io/wordpress/v/aryo-activity-log.svg?style=flat-square)](https://wordpress.org/plugins/aryo-activity-log/) [![WordPress](https://img.shields.io/wordpress/plugin/r/aryo-activity-log.svg?style=flat-square)](https://wordpress.org/plugins/aryo-activity-log/) [![WordPress](https://img.shields.io/wordpress/plugin/v/aryo-activity-log.svg?style=flat-square)](https://wordpress.org/plugins/aryo-activity-log/) [![WordPress](https://img.shields.io/wordpress/plugin/dt/aryo-activity-log.svg?style=flat-square)](https://wordpress.org/plugins/aryo-activity-log/) [![Built with Grunt](https://cdn.gruntjs.com/builtwith.svg)](http://gruntjs.com/)


**Contributors:** [elemntor](https://profiles.wordpress.org/elemntor), [KingYes](https://profiles.wordpress.org/KingYes), [ariel.k](https://profiles.wordpress.org/ariel.k), [maor](https://profiles.wordpress.org/maor)  
**Tags:** Activity Log, User Log, Audit Log, Security, Email Log,  
**Requires at least:** 6.0  
**Requires PHP:** 7.4  
**Tested up to:** 6.8  
**Stable tag:** 2.11.2  
**License:** GPLv2 or later  

This top rated Activity Log plugin helps you monitor & log all changes and actions on your WordPress site, so you can remain secure and organized.

## Description

<strong>AN EASY TO USE & FULLY SUPPORTED WORDPRESS ACTIVITY LOG PLUGIN</strong><br />

Want to monitor and track your WordPress website activity? Find out exactly who does what on your WordPress website with this plugin. Activity Log is like an airplane’s black box that logs every action in the WordPress admin, and lets you see exactly what users are doing on your WordPress website.

* If someone is trying to hack your site
* When a post was published, and who published it
* If a plugin/theme was activated/deactivated
* Suspicious admin activity

It’s so essential; you’ll wonder how you ever managed your website without it. The plugin is also lightning fast and works behind the scenes, so it doesn\’t affect site and admin performance. For optimal performance, we built the plugin so that it runs on a separate table in the database.

If you have more than a handful of users, keeping track of who did what is virtually impossible. This plugin solves that issue by tracking what actions were initiated by which users, and displaying it in an easy-to-use and easy-to-filter view on the dashboard of your WordPress site.

<strong>New! Introducing Email Logging</strong> - Capture all emails sent from your WordPress site for streamlined debugging and compliance. Gain better visibility into email communication, aiding both troubleshooting and record-keeping. This is particularly beneficial for WooCommerce stores, allowing you to easily track sent emails alongside other critical site events.

<strong>Export to CSV</strong> - Export your Activity Log data records to CSV. Developers can easily add support for custom data formats with our new dedicated Export API.

<strong>Data Privacy and GDPR Compliance</strong> - We provide the tools to help you adhere to GDPR compliance standards, including Export/Erasure of data via the WordPress Privacy Tools.

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
* <strong>WooCommerce</strong> - Track products, orders, customers, and more
* <strong>bbPress</strong> - Forums, topics, replies, taxonomies, and other actions
* <strong>Emails sent from WordPress site</strong> - Sending successful, sending failed
* There’s more, of course, but you get the point...

For each event recorded by the activity log, the following details are also logged:

* Date and time of occurrence
* User and user role responsible for the change
* Source IP address from which the change originated
* Affected object where the change occurred

The plugin doesn\’t require any kind of setup; it works right out of the box (just another reason people love it)!

<h3>Data Storage and Performance Optimization</h3>

In order to ensure optimal performance of your website, all events and logs data are stored in a dedicated custom table within your WordPress database. This approach significantly reduces the impact on your website's performance, ensuring seamless operation even during peak traffic periods.

<h3>Uninstall Clean-up</h3>

We understand the importance of maintaining a clean and efficient database environment. That's why our plugin features an uninstall hook that seamlessly removes all traces of its presence from your website when uninstalling. This meticulous clean-up process ensures that your database remains lean and clutter-free even after our plugin has been removed.

<strong>With our optimized data storage, thorough logging, and meticulous clean-up process, you can trust that our plugin will enhance the functionality and security of your WordPress site without compromising its performance.</strong>


<h3>What users have to say</h3>

* <em>“Its tools, particularly for data privacy and GDPR compliance, make it indispensable for websites operating within European Union boundaries or dealing with EU citizens’ data”</em> - [HubSpot.com](https://blog.hubspot.com/website/8-best-plugins-tracking-user-activity-wordpress)
* <em>“If you’re after a competent WP security audit log plugin with all the basic features you need, Activity Log is it!”</em> - [WPAstra.com](https://wpastra.com/plugins/wordpress-activity-log-plugins/)
* <em>“Activity Log features a remarkably straightforward dashboard interface, providing administrators with an at-a-glance understanding of site interactions”</em> - [Malcare.com](https://www.malcare.com/blog/wordpress-activity-log/)
* <em>“Best 10 Free WordPress Plugins of the Month: Keeping tabs on what your users do with their access to the Dashboard”</em> - [ManageWP.com](https://managewp.com/best-free-wordpress-plugins-july-2014)
* <em>“Thanks to this step, we’ve discovered that our site was undergoing a brute force attack”</em> - [Artdriver.com](https://artdriver.com/blog/wordpress-site-hacked-solution-time)
* <em>“Optimized code – The plugin itself is blazing fast and leaves almost no footprint on the server”</em> - [FreshTechTips.com](https://www.freshtechtips.com/2014/01/best-audit-trail-plugins-for-wordpress.html)
* <em>“Activity Log lets you track a huge range of activities. Overall, very easy to use and setup”</em> - [ElegantThemes.com](https://www.elegantthemes.com/blog/tips-tricks/5-best-ways-to-monitor-wordpress-activity-via-the-dashboard)

<h3>Contributions:</h3>
<strong>Would you like to contribute to this plugin?</strong> You’re more than welcome to submit your pull requests on the [GitHub repo](https://github.com/pojome/activity-log). And, if you have any notes about the code, please open a ticket on the issue tracker.

## Installation

1. Upload plugin files to your plugins folder, or install using WordPress' built-in Add New Plugin installer
1. Activate the plugin
1. Go to the plugin page (under Dashboard > Activity Log)

## Screenshots

1. The log viewer page
2. The settings page
3. Screen Options

## Frequently Asked Questions

### Requirements

__Requires PHP 7.4__ for list management functionality.

### What is the plugin license?

This plugin is released under a GPL license.

### Can I export logs?

You can easily export logs with Activity Log. We also support exporting filtered results. Filter by the time the action took place, roles, users, options, action type, and more.

### How can I report security bugs? ###

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability](https://patchstack.com/database/vdp/aryo-activity-log).

## Changelog

### 2.11.2 - 2024-11-12 ###
* Security Fix: Improved code security enforcement in theme/plugin file editor

### 2.11.1 - 2024-11-05 ###
* Tweak: Added ability to search in context column

### 2.11.0 - 2024-07-29 ###
* New: Added logging for enabling and disabling automatic theme updates
* New: Added logging for enabling and disabling automatic plugin updates
* New: Added logging for enabling and disabling automatic core updates

### 2.10.1 - 2024-04-17 ###
* Tweak: Added option to not keep email logs ([Topic](https://wordpress.org/support/topic/activity-log-email-off-on-option/))

### 2.10.0 - 2024-04-08 ###
* New: Introducing Email Logging - Capture all emails sent from your WordPress site
* Tweak: Added filter to change menu page capability ([#205](https://github.com/pojome/activity-log/pull/205))
* Tweak: Set the date display on CSV export file according to WordPress settings ([#204](https://github.com/pojome/activity-log/pull/204))

### 2.9.2 - 2024-03-18 ###
* Tweak: Added an `aal_skip_insert_log` filter to skip record on demand ([Topic](https://wordpress.org/support/topic/exclude-specific-post-types-from-logging/))

### 2.9.1 - 2024-02-21 ###
* Tweak: Temporarily removed WC integration while working on updated support in the future

### 2.9.0 - 2023-11-22 ###
* New: Added log when plugin is deleted ([Topic](https://wordpress.org/support/topic/log-remove-plugin/))
* Tweak: Added an option to "Do not collect IP" in the log ([#195](https://github.com/pojome/activity-log/issues/195))

### 2.8.8 - 2023-08-20 ###
* Tweak: Added aal_export_csv_separator filter to change the separator in CSV export ([Topic](https://wordpress.org/support/topic/change-separator-in-class-aal-exporter-csv-php/))
* Tweak: Added Visitor IP Detected to prevent IP manipulations in log

### 2.8.7 - 2023-07-30 ###
* Tweak: Remove Elementor Promotion from Activity Log plugin
* Tweak: Added support for non-standard WordPress loading ([Topic](https://wordpress.org/support/topic/plugin-breaks-site-migration/))
* Fix: Logs kept for longer than settings ([Topic](https://wordpress.org/support/topic/logs-kept-for-longer-than-settings/), [#178](https://github.com/pojome/activity-log/issues/178))

### 2.8.6 - 2023-05-08 ###
* Tweak: Improved database performance for new installations by adding indexes
* Fix: Added compatibility for PHP 8.1 ([#180](https://github.com/pojome/activity-log/issues/180))

### 2.8.5 - 2022-11-21 ###
* Tweak: Now the date/time format is displayed according to the site settings ([Topic](https://wordpress.org/support/topic/date-format-question-2/))
* Fix: Added compatibility for PHP 8.1 ([Topic](https://wordpress.org/support/topic/deprecated-filter_sanitize_string-preg_match-and-strtolower-in-php-8-1/))
* Fix: Added input sanitization to avoid security issues

### 2.8.4 - 2022-09-04 ###
* Tweak: Added Activity Log setting to records log
* Tweak: Added encoded value in CSV file ([#165](https://github.com/pojome/activity-log/issues/165))

### 2.8.3 - 2022-03-09 ###
* Tweak: Run Clear old items from DB once daily to avoid unexpected errors ([#156](https://github.com/pojome/activity-log/issues/156))

### 2.8.2 - 2022-01-25 ###
* Fix: Auto-updates of core, plugins and themes are not registered to the log ([#155](https://github.com/pojome/activity-log/issues/155), props [@nicomollet](https://github.com/nicomollet))

### 2.8.1 - 2021-12-01 ###
* Fix: Activity log database table not being dropped after deleting the plugin in multisite installation

### 2.8.0 - 2021-11-17 ###
* New: Added Privacy Settings to records log
* New: Added Site Language to records log
* New: Added a filter link to Topic, IP, Date, User and Action in the log table screen
* Tweak: Aligned Topics to be in plural instead of singular
* Fix: Filter by users dropdown on activity page threw a timeout error in some cases ([#141](https://github.com/pojome/activity-log/issues/141))
* Fix: CSV Export issue with comma separated values ([Topic](https://wordpress.org/support/topic/csv-export-and-comma-seperated-values/))

### 2.7.0 - 2021-05-06 ###
* New: Added an option to skip or keep the failed login logs for better optimization ([#125](https://github.com/pojome/activity-log/issues/125))
* Tweak: Improved the activity log table with clear labels and re-order columns for better UX
* Tweak: Changed the wrong_password action to failed_login in User topic
* Tweak: Changed the added action to uploaded in Attachment topic
* Tweak: Changed the created action to registered in User topic
* Fix: Add input sanitization to avoid security issues

### 2.6.1 - 2021-02-15 ###
* Fix: Conflict with WooCommerce while you using new block editor

### 2.6.0 - 2020-10-19 ###
* Tweak: Added support for CloudFlare and CloudFlare Enterprise client IP header ([#133](https://github.com/pojome/activity-log/issues/133))
* Tweak: Added browser confirmation to Reset Database option
* Tweak: Notification tab is now deprecated for new installations
* Tweak: Added support for displaying custom role activity log ([#78](https://github.com/pojome/activity-log/issues/78), [#135](https://github.com/pojome/activity-log/issues/135), [Topic](https://wordpress.org/support/topic/only-shows-logs-for-admin/), [Topic](https://wordpress.org/support/topic/no-logs-for-most-created-roles/))
* Fix: Show user data on log-out action ([#126](https://github.com/pojome/activity-log/issues/126), [Topic](https://wordpress.org/support/topic/logout-hook-event/))
* Fix: Removed unused help context in admin to resolve deprecated WP error ([Topic](https://wordpress.org/support/topic/deprecated-function-in-debug-log/))
* Fix: PHP Notices are thrown when Debug mode is active ([Topic](https://wordpress.org/support/topic/php-errors-infomational/))
* Fix: Resolve jQuery Deprecation Notice and compatibility with WordPress 5.6+ ([Topic](https://wordpress.org/support/topic/jquery-deprecation-notice-jquery-fn-size/))

### 2.5.2 ###
* Fix: Conflict with Elementor and WordPress Widgets

### 2.5.1 ###
* Fix! - PHP < 5.4 compatibility ([Topic](https://wordpress.org/support/topic/crashed-site-now-wont-activate/))

### 2.5.0 ###
* New! Added log to Export Personal Data tool for better GDPR Compliance ([Topic](https://wordpress.org/support/topic/activity-log-gdpr-compliance/))

### 2.4.1 ###
* Fix! - Escape title before saving to database

### 2.4.0 ###
* New! Export your Activity Log data records to CSV ([#70](https://github.com/pojome/activity-log/issues/70))

### 2.3.6 ###
* Fix! - Admin table filters

### 2.3.5 ###
* Fix! - Added comparability for WordPress 4.8.2 & 4.7.6

### 2.3.4 ###
* Tweak! - Change Guest user to "N/A"

### 2.3.3 ###
* Fixed! - Minor XSS vulnerability, credit to [Han Sahin](https://sumofpwn.nl/)

### 2.3.2 ###
* Fixed! - Minor XSS vulnerability, credit to [Han Sahin](https://sumofpwn.nl/)

### 2.3.1 ###
* Tweak! - Added seconds in time column
* Tweak! - Rearrange filters in list table

### 2.3.0 ###
* Tweak! - All translates moved to [GlotPress](https://translate.wordpress.org/projects/wp-plugins/aryo-activity-log)
* Tweak! - Added restore status for Posts ([#46](https://github.com/pojome/activity-log/issues/46))
* Tweak! - A11y changes for WordPress 4.4 which requires `h1` tags ([#84](https://github.com/pojome/activity-log/issues/84))
* Tweak! - Allow some ajax requests just for admin

### 2.2.12 ###
* Tested up to WordPress v4.5

### 2.2.11 ###
* Tweak! - Temporarily remove Freemius SDK from the plugin

### 2.2.10 ###
* Tweak! Update Freemius SDK
* Tested up to WordPress v4.4.2

### 2.2.9 ###
* Tweak! Update Freemius SDK

### 2.2.8 ###
* Tweak! Update Freemius SDK

### 2.2.7 ###
* Added! - Freemius Insights platform to improve plugin UX
* Tweak! Update translate: Russian (ru_RU) - Thanks to Oleg Reznikov
* Tested up to WordPress v4.4

### 2.2.6 ###
* Tweak! - Added sort by IP address ([#77](https://github.com/pojome/activity-log/issues/77))
* Tweak! - Added more actions/types in notification

### 2.2.5 ###
* New! - Added translate: Finnish (fi) - Thanks to Nazq ([topic](https://wordpress.org/support/topic/finnish-translation-1))
* Tweak! - Better actions label in list table
* Fixed! - Notice php warring in MU delete site
* Tested up to WordPress v4.3

### 2.2.4 ###
* New! - Added translate: Czech (cs_CZ) - Thanks to Martin Kokeš ([#76](https://github.com/pojome/activity-log/pull/76))

### 2.2.3 ###
* Tweak! - Added more filters in table list columns

### 2.2.2 ###
* Fixed! some PHP strict standards (PHP v5.4+)

### 2.2.1 ###
* Fixes from prev release

### 2.2.0 ###
* New! - Adds search box, to allow users to search the description field.
* New! - Allows users to now filter by action
* New! - Added translate: Polish (pl_PL) - Thanks to Maciej Gryniuk
* Tweak! - SQL Optimizations for larger sites

### 2.1.16 ###
* New! Added translate: Russian (ru_RU) - Thanks to Oleg Reznikov
* Fixes Undefined property with some 3td party themes/plugins
* Tested up to WordPress v4.2

### 2.1.15 ###
* Tested up to WordPress v4.1
* Change plugin name to "Activity Log"

### 2.1.14 ###
* New! Added translate: Persian (fa_IR) - Thanks to [Promising](http://vwp.ir/)

### 2.1.13 ###
* New! Added filter by User Roles ([#67](https://github.com/pojome/activity-log/issues/67))

### 2.1.12 ###
* New! Added translate: Turkish (tr_TR) - Thanks to [Ahmet Kolcu](http://ahmetkolcu.org/)

### 2.1.11 ###
* Fixed! Compatible for old WP version

### 2.1.10 ###
* New! Now tracking when menus created and deleted
* New! Added translate: Portuguese (pt_BR) - Thanks to [Criação de Sites](http://www.techload.com.br/criacao-de-sites-ribeirao-preto)

### 2.1.9 ###
* New! Store all WooCommerce settings ([#62](https://github.com/pojome/activity-log/issues/62))
* Tested up to WordPress v4.0

### 2.1.8 ###
* New! Now tracking when plugins installed and updated ([#59](https://github.com/pojome/activity-log/pull/59) and [#43](https://github.com/pojome/activity-log/issues/43))

### 2.1.7 ###
* New! Now tracking when user download export file from the site ([#58](https://github.com/pojome/activity-log/issues/58) and [#63](https://github.com/pojome/activity-log/pull/63))

### 2.1.6 ###
* Tested up to WordPress v3.9.2

### 2.1.5 ###
* New! Now tracking when theme installed, updated, deleted ([#44](https://github.com/pojome/activity-log/issues/44))

### 2.1.4 ###
* Fixed! Store real IP address in Proxy too ([#53](https://github.com/pojome/activity-log/issues/53))

### 2.1.3 ###
* New! Added translate: Dutch (nl_NL) - Thanks to [Tom Aalbers](http://www.dtaalbers.com/) ([#55](https://github.com/pojome/activity-log/issues/55))

### 2.1.2 ###
* Tweak! Update translate: Hebrew (he_IL)

### 2.1.1 ###
* New! Track about WordPress core update (manual or auto-updated) ([#41](https://github.com/pojome/activity-log/issues/41))
* New! Track post comments (created, approved, unproved, trashed, untrashed, spammed, unspammed, deleted) ([#42](https://github.com/pojome/activity-log/issues/42))

### 2.1.0 ###
* New! Personally-tailored notifications that can be triggered by various types of events, users and action type (currently only email notifications are supported)
* Bug fixes, stability improvements
* Fixed an error that occurred on PHP 5.5

### 2.0.7 ###
* Tested up to WordPress v3.9.0

### 2.0.6 ###
* Fixed! Random fatal error ([topic](https://github.com/pojome/activity-log/issues/32))

### 2.0.5 ###
* New! Register `aal_init_caps` filter.
* Tweak! Change all methods to non-static.
* Tweak! Some improved coding standards and PHPDoc.
* Tweak! Split `AAL_Hooks` class to multiple classes.
* New! Added translate: Armenia (hy_AM) - Thanks to Hayk Jomardyan.

### 2.0.4 ###
* Tweak! Don't allowed to access in direct files.
* New! Added translate: Danish (da_DK) - Thanks to [Morten Dalgaard Johansen](http://www.iosoftgame.com/)

### 2.0.3 ###
* New! Record when widgets change orders.

### 2.0.2 ###
* New! Save more Options:
* General
* Writing
* Reading
* Discussion
* Media
* Permalinks

### 2.0.1 ###
* New! filter for disable erase all the log
* Bugs fixed

### 2.0.0 ###
* Added Screen Options
* New! Ability to select a number of activity items per page
* New! Columns are now sortable
* Added filter by date - All Time, Today, Yesterday, Week, Month
* Added Avatar to author
* Added role for author
* Added log for activeted theme
* Re-order Culoumns
* Compatible up to 3.8.1
* Settings page is now accessible directly from Activity Log's menu
* Keep your log for any time your wants
* Delete Log Activities from Database.
* Bugs fixed

### 1.0.8 ###
* Added translate: Serbo-Croatian (sr_RS) - Thanks to [Borisa Djuraskovic](http://www.webhostinghub.com/).

### 1.0.7 ###
* Added 'view_all_aryo_activity_log' user capability ([topic](http://wordpress.org/support/topic/capability-to-access-the-activity-log)).

### 1.0.6 ###
* Added WooCommerce integration (very basic).
* Added Settings link in plugins page.

### 1.0.5 ###
* Fix - Make sure no save double lines (menu taxonomy / post).

### 1.0.4 ###
* Added Taxonomy type (created, updated, deleted).

### 1.0.3 ###
* Added Multisite compatibility.
* Added Options hooks (limit list, you can extend by simple filter).
* Added Menu hooks.
* Tweak - Ensure no duplicate logs..

### 1.0.2 ###
* Forget remove old .pot file

### 1.0.1 ###
* Added translate: German (de_DE) - Thanks to [Robert Harm](http://www.mapsmarker.com/)
* Added translate: Hebrew (he_IL)
* Plugin name instead of file name on activation/deactivation
* <strong>New Hooks:</strong>
* A widget is being deleted from a sidebar
* A plugin is being changed
* Theme Customizer (Thanks to Ohad Raz)

### 1.0 ###
* Blastoff!
