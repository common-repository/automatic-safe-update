=== Automatic Safe Update ===
Contributors: ABCdatos
Tags: safety, version, versions, versiones, actualizar, seguridad, actualización, updating, safe, maintenance
Requires at least: 4.2
Tested up to: 6.6
Stable tag: 1.1.12
Requires PHP: 5.4
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

To update your plugins with safe and automated mode. Upgrade installed themes too.

== Description ==

To update your WordPress is a time consuming task you can't avoid. Without it, your site will be in trouble and you'll be regretful. Knowing this, here you have a powerful tool to help you to maintain your plugins updated.

Select the cases for automated updates and the manual ones based on the update level and single plugin if you want or let the plugin decide for you. Stability vs. safety is the balance. Let the plugin take itself the decision if you like it.

[Semantic version numbering](https://semver.org/) didides version numbers in three main blocks: major, minor and patch in this way: major.minor.patch. If all the plugin authors were working fine, when only patch version number changes, bug and security fixes in a compatible way were done, when the minor number increases, new functionality are added in a backwards compatible way. Major version changes are incompatible and may require additional work with your site.

You'll never know in advance when an error in a plugin can make your WordPress site fail, nor will you know in advance that you are leaving a security hole to be attacked if you do not update.

The plugin allways allow to update itself to avoid locks as happened in version 1.1.9 requiring a manual update.

== Installation ==

1. Upload files to the /wp-content/plugins/automatic-safe-update directory or install plugin through the Plugins menu of your WordPress directlly.
1. Activate the plugin through the Plugins menu of your WordPress.
1. Go to Automatic Safe Update settings from the Wordpress admin menu to configure the plugin.
1. Adjust settings as you like and press at save changes.

== Frequently Asked Questions ==

== Screenshots ==

1. Settings view.
2. Sample of pending updates message.
3. Sample of updated message.
 
== Changelog ==

= 1.1.12 =
*Jul 05 2024*
* Fixed menu notification entry.
* Fixed user capability required for configuration menu view.
* Minor code fixes.
* Renamed some files.
* Fixed the subject of the sent emails.
* Replaced semaphore functions management.
* More code styling improvements.
* WordPress 6.6 basic compatibility checked.

= 1.1.11 =
*May 12 2023*
* Solved logic bug.
* More code styling improvements.

= 1.1.10 =
*May 11 2023*
* Solved warning when running scheduled tasks from cron.
* Some code styling improvements.

= 1.1.9 =
*May 9 2023*
* Solved forced plugins check from option page issues.
* Better privative plugins compatibility.
* Supporting failure to get a plugin new version.
* Solved minor issue in desktop icon.

= 1.1.8 =
*Mar 12 2023*
* WordPress 6.2 basic compatibility checked.
* Avoid warnings on private plugins without official slug.
* Solved problem with Hello Dolly plugin as it is installed without its own directory and is a core plugin.
* Log messages corrections.

= 1.1.7 =
*Jan 04 2023*
* Removes the new WP 5.5 plugin post update mail as is considering a failure the configured rejected updates.
* Changelog URL for WP Bakery.
* Changed behaviour on semaphore adquisition to avoid failures on PHP 8.
* Solved warning when updating with bad version number obtained.
* Solved bug in update process.

= 1.1.6 =
*Sep 11 2020*
* From WP 5.5 onwards, the new e-mail update from WordPress replaces que plugin's one, it has been removed to avoid duplicated communication.
* Don't report update warnings about a-fake-plugin, the test one from Site Health.
* SVG admin menu icon.

= 1.1.5 =
*Aug 17 2020*
* Avoid critical message in Site Health.
* Fix bug duplicating update notices to different versions.
* Fix error on PHP 7.4.
* Fix syntax notice on PHP 7.4.
* WordPress 5.5 basic compatibility checked.

= 1.1.4 =
*May 16 2020*
* Fix warning on initial execution through WP-CLI.
* WordPress 5.4 compatibility checked.

= 1.1.3 =
*Jan 12 2020*
* Fix for plugin activation and configuration in PHP 5.
* Confirmed PHP 5.4 minimum version required.
* Corrected some mail strings.
* Corrections to avoid empty update jobs processing.

= 1.1.2 =
*Nov 30 2019*
* Link to plugin page for manual updates included in mail.
* Bug avoiding PHP 5.3 compatibility solved.

= 1.1.1 =
*Nov 20 2019*
* If defined, displays WP_AUTO_UPDATE_CORE value in the settings page.
* Removed wrong trunk copy included wich disables common activation procedure from Add Plugin page.

= 1.1.0 =
*Oct 26 2019*
* Corrected debug mode warnings.
* Experimental (alpha) themes and translations update capability.
* Ensure availability of semaphores functions for mail processes.
* WordPress 5.3 compatibility verified.
* PHP 7.4 compatibility verified.

= 1.0.2 =
*May 28 2019*
* Small translation bug in e-mail.
* Bug when individual plugin setting is hold.

= 1.0.1 =
*May 20 2019*
* Translation related bugs.

= 1.0 =
*May 19 2019*
* WordPress.org initial version.

== Upgrade Notice ==
