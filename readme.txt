=== SQLite Integration ===
Contributors: kjmtsh
Plugin Name: SQLite Integration
Plugin URI: http://dogwood.skr.jp/wordpress/sqlite-integration/
Tags: database, SQLite, PDO
Author: Kojima Toshiyasu
Author URI: http://dogwood.skr.jp/
Requires at least: 3.3
Tested up to: 3.8
Stable tag: 1.5
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SQLite Integration is the plugin that enables WordPress to use SQLite. If you want to build a WordPress website with it, this plugin is for you.

== Description ==

This plugin enables WordPress to work with [SQLite](http://www.sqlite.org/). You don't have to prepare MySQL database server or its configuration. SQLite is a self-contained, serverless, transactional SQL database engine. It is not a full-featured database system like MySQL or PostgreSQL, but it best fits for low to medium traffic websites.

SQLite Integration is a kind of wrapper program, which is placed between WordPress and SQLite database and works as a mediator. It works as follows:

1. Intercepts the SQL statement for MySQL from WordPress
2. Rewrites it for SQLite to execute
3. Give it to SQLite
4. Gets the results from SQLite
5. Formats the results as WordPress wants, if necessary
6. Give the results back to WordPress

WordPress thinks she talks with MySQL and doesn't know what has happened in the background. She really talks with SQLite and will be happy with it.

SQLite Integration is a successor to [PDO for WordPress](http://wordpress.org/extend/plugins/pdo-for-wordpress) plugin, which unfortunately enough, doesn't seem to be maintained any more. SQLite Integration uses the basic idea and structures of that plugin and adds some more features or some utilities.

= Features =

SQLite Integration is not an ordinary 'plugin'. It must be be used when you install WordPress itself, which requires you to do some preparations. Please read the install section. And see more detailed instruction in the [SQLite Integration Page](http://dogwood.skr.jp/wordpress/sqlite-integration/).

Once you succeed in installing WordPress, you can use it just like the others using MySQL. Optionally, you can activate this plugin in the installed plugins panel of the adimn dashboard, and you can see the useful information and instructions. It is not required but I recommend it.

If you want to test WordPress with this plugin on the local machine but want to use MySQL on the server machine, you can control which database to use with the simple directive in the wp-config.php file. See the install instruction section.

= Backward Compatibility =

If you are using [PDO for WordPress](http://wordpress.org/extend/plugins/pdo-for-wordpress), you can migrate your database. See install instruction section.

= Support =

Please contact us with the methods below:

1. Post to [Support Forum](http://wordpress.org/support/plugin/sqlite-integration/).
2. Visti the [SQLite Integration Page](http://dogwood.skr.jp/wordpress/sqlite-integration/) or [SQLite Integration(ja) Page](http://dogwood.skr.jp/wordpress/sqlite-integration-ja/) and leave a message.

Notes: WordPress.org doesn't officially support using any other database than MySQL. So there will be no supports from WordPress.org. Even if you post to the general Forum, you have few chances to get the answer. And if you use patched plugins, you will have no support from the plugin authors, eithter.

= Translation =

Documentation is written in English. Japanese catalog file and .pot file are included in the archive. If you translate it into your language, please let me know.

== Installation ==

This plugin is *not* like the other plugins. You can't install and activate it on the plugin administration panel.

First of all, you've got to prepare WordPress installation. See also [Installing Wordpress ](http://codex.wordpress.org/Installing_WordPress) section in the Codex.

After checking the prerequisites and downloading and unzipping the WordPress archive file, you must rename wp-contig-sample.php file to wp-config.php and do some editting as the [Codex page](http://codex.wordpress.org/Editing_wp-config.php) says.

= Basic settings =

If you only use SQLite for your database, you don't have to edit the MySQL settings section. Edit the three sections below:

* Authentication Unique keys and Salts
* WordPress Database Table prefix
* WordPress Localized Language

That's all. You don't have to change any other sections.

If you want to use SQLite and MySQL interchangeably, you must edit the database server settings at the top of the file as well as the sections above mentioned. And add the line below.

`define('USE_MYSQL', false);
/* That's all, stop editing! Happy blogging. */`

This definition makes WordPress use SQLite. If you want to change the database to MySQL, change 'false' to 'true'.

= Optional settings =

When you finish basic settings, you can add optional ones. This is not required. If you don't need them, you don't have to edit wp-config.php any more.

* If you want to put the SQLite database file to the directory different from the default setting (wp-content/database), you can add the line below (don't forget to add a trailing slash):

`define('DB_DIR', '/home/youraccount/database_directory/');`

	Note: Your PHP scripts must have the permission to create that directory and files in it.

* If you want to change the database file name to another one different from the default (.ht.sqlite), you can add the line below:

`define('DB_FILE', 'database_file_name');`

	Note: If you are using 'PDO for WordPress' plugin, see also 'Migrating your database' section.

	If you don't understand well, you don't have to add any of the lines above.

= Preparing SQLite Integration =

After you finish preparing wp-config.php, follow the next steps:

1. Download SQLite Integration archive file.

2. Unzip the plugin archive file.

3. Copy db.php file contained in the archive to wp-content directory.

3. Move the sqlite-integration directory to wp-content/plugin/ directory.

  `wordpress/wp-contents/db.php`

	and
	
  `wordpress/wp-contents/sqlite-integration`

  respectively.

OK. This is all. Upload everything (keeping the directory structure) to your server and access the wp-admin/install.php with your favorite browser, and WordPress installation process will begin. Enjoy your blogging!

= Migrate your database to SQLite Integration =

If you are using PDO for WordPress now, you can migrate your database to SQLite Integration. You don't have to reinstall WordPress. Please follow the next steps:

1. Check if your MyBlog.sqlite file contains all the tables required by WordPress. You have to use a utility software like [SQLite Manager Mozilla Addon](https://addons.mozilla.org/en-US/firefox/addon/sqlite-manager/). See also [Database Description](http://codex.wordpress.org/Database_Description) in Codex.

2. Backup your MyBlog.sqlite and db.php files.

3. EITHER rename your MyBlog.sqlite to .ht.sqlite OR add the next line in wp-config.php file.
	
	`define('FQDB', 'MyBlog.sqlite');`

4. Overwrite your wp-content/db.php with the db.php file contained in SQLite Integration archive.

That's all. Don't forget to check the requirement and your WordPress version. *SQLite Integration doesn't work with WordPress version 3.2.x or lesser*.

== Frequently Asked Questions ==

= Database file is not created =

The reason of failure in creating directory or files is often that PHP is not allowed to craete them. Please check your server setting or ask the administrator.

= Such and such plugins can't be activated or doesn't seem to work properly =

Some of the plugins, especially cache plugins or database maintenace plugins, are not compatible with this plugin. Please activate SQLite Integration and see the plugin comatibility section in the documentation or visit the [SQLite Integration Page](http://dogwood.skr.jp/wordpress/sqlite-integration/).

= I don't want the admin menu and documentation =

Just deactivate the plugin, and you can remove them. Activation and deactivation affect only admin menu. If you want to remove all the plugin files, just delete it.

== Screenshots ==

1. System Information tells you your database status and installed plugins compatibility.

== Requirements ==

* PHP 5.2 or newer with PDO extension (PHP 5.3 or newer is better).
* PDO SQLite driver must be loaded.

== Known Limitations ==

Many of the other plugins will work fine with this plugin. But there are some you can't use. Generally speaking, the plugins that manipulate database not with WordPress functions but with mysql or mysqli native drivers from PHP might cause the problem.

These are other examples:

= You can't use these plugins because they create the same file that this plugin uses: =

* [W3 Total Cache](http://wordpress.org/extend/plugins/w3-total-cache/)
* [DB Cache Reloaded Fix](http://wordpress.org/extend/plugins/db-cache-reloaded-fix/)
* [HyperDB](http://wordpress.org/extend/plugins/hyperdb/)

= You can't use some of the plugins, because they are using MySQL specific features that SQLite can't emulate. For example: =

* [Yet Another Related Posts](http://wordpress.org/extend/plugins/yet-another-related-posts-plugin/)
* [Better Related Posts](http://wordpress.org/extend/plugins/better-related/)

Probably there are more, I'm afraid. If you find one, please let me know.

== Upgrade Notice ==

WordPress 3.8 compatible. Some minor bug fixes and optional features. When auto upgrading fails, please try manual upgrade via FTP.

== Changelog ==

= 1.5 (2013-12-17) =
* Tested WordPress 3.8 installation and compatibility.
* Add the optional feature to change the database from SQLite to MySQL.
* Changed the install instruction in the readme.txt.
* Add the code to check if the SQLite library was compiled with the option 'ENABLE_UPDATE_DELETE_LIMIT'.
* Changed the admin panel style to fit for WordPress 3.8.
* Restricted the direct access to the files that works in the global namespace.

= 1.4.2 (2013-11-06) =
* Fixed some minor bugs about the information in the dashboard.
* Changed the screenshot.
* Tested WordPress 3.7.1 installation.

= 1.4.1 (2013-09-27) =
* Fixed the rewriting process of BETWEEN function. This is a critical bug. When your newly created post contains 'between A and B' phrase, it is not published and disappears.
* Fixed the admin dashboard display when using MP6.
* Fixed the Japanese catalog.
* Added the procedure for returning the dummy data when using SELECT version().
* Added the procedure for displaying column informatin of WordPress tables when WP_DEBUG enabled.

= 1.4 (2013-09-12) =
* Added the database maintenance utility for fixing the database malfunction of the upgraded WordPress installation.
* Changed the manipulation of SHOW INDEX query with WHERE clause.
* Fixed the bug of the manipulation of ALTER TABLE query.

= 1.3 (2013-09-04) =
* Added the backup utility that creates the zipped archive of the current snapshot of the database file.
* Changed the dashboard style to match MP6 plugin.
* Changed the way of putting out the error messages when language catalogs are not loaded.
* Modified the _rewrite_field_types() in query_create.class.php for the dbDelta() function to work properly.
* Added the support for BETWEEN statement.
* Changed the regular expression to remove all the index hints from the query string.
* Fixed the manipulation of ALTER TABLE CHANGE COLUMN query for NewStatPress plugin to work.
* Fixed minor bugs.

= 1.2.1 (2013-08-04) =
* Removed wpdb::real_escape property following the change of the wpdb.php file which makes the plugin compatible with Wordpress 3.6.

= 1.2 (2013-08-03) =
* Fixed the date string format and its quotation for calendar widget.
* Fixed the patch utility program for using on the Windows machine.
* Fixed the textdomain error in utilities/patch.php file when uploading the patch file.
* Changed the manipulation of the query with ON DUPLICATE KEY UPDATE.
* Fixed the typos in readme.txt and readme-ja.txt.

= 1.1 (2013-07-24) =
* Fixed the manipulation of DROP INDEX query.
* Removed destruct() from shutdown_hook.
* Enabled LOCATE() function in the query string.

= 1.0 (2013-07-07) =
* First release version of the plugin.
