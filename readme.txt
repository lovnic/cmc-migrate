=== CMC MIGRATE ===
Contributors: lovnic
Tags: migration, multisite, singlesite, export
Requires at least: 4.6.0
Tested up to: 4.8
Stable tag: 0.0.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Migrate Wordpress site from one installation to another

== Description ==
Used to migrate wordpress installation from one site to another
The plugin installation sets up a menu under admin tools menu called cmc migrate
From cmc migrate menu, migrations can be created.
Migrations are a snapshort of your wordpress installation at a particular point in time. It includes your databases, plugins, themes, uploads and wp-content files at that time.
Migration can be imported and restored on any wordpress site.
You can remotly connect to other wordpress installation and import thier migration.
Migrations of multisite single site can be transfered to single site without multisite.
This version does not support restore of migrations to multisite single site.
make sure to set post_max_size and upload_max_filesize in php.ini to higher values in order to upload or import migrations


== Screenshots ==
1. Migrations
2. Export Site
3. Restore Site
4. Settings

== Frequently Asked Questions ==
= Minimum Requirements =
wordpress 4.6.0
php 5.5

= How to install =
Upload the plugin to the wp-content\plugins folder of your wordpress installaiton and activate it

== Changelog ==

= 0.0.1 - 2017-05-06 =
initial release

= 0.0.2 - 2017-06-11 =
fix bugs
added actions 'cmcmg_init' and 'before_cmcmg_init'
add support for multisite
Supports wordpress 4.8

= 0.0.3 - 2017-06-20 =
fix bugs

