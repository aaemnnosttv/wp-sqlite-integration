<?php
/*
Plugin Name: SQLite Integration
Plugin URI: http://dogwood.skr.jp/wordpress/sqlite-integration/
Description: SQLite Integration is the plugin that enables WordPress to use SQLite. If you don't have MySQL and want to build a WordPress website, it's for you.
Author: Kojima Toshiyasu
Version: 1.5
Author URI: http://dogwood.skr.jp
Text Domain: sqlite-integration
Domain Path: /languages
License: GPL2 or later
*/

/* Copyright 2013 Kojima Toshiyasu (email: kjm@dogwood.skr.jp)
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Globals
 */
if (!defined('ABSPATH')) {
	echo 'Thank you, but you are not allowed to access this file.';
	die();
}
$siteurl = get_option('siteurl');
define('SQLiteDir', dirname(plugin_basename(__FILE__)));
define('SQLiteFilePath', dirname(__FILE__));
define('SQLiteDirName', basename(SQLiteFilePath));
define('SQLiteUrl', $siteurl . '/wp-content/plugins/' . SQLiteDir);

if (defined('UPLOADS')) {
  define('SQLitePatchDir', UPLOADS . '/patches');
} else {
  if (defined('WP_CONTENT_DIR')) {
    define('SQLitePatchDir', WP_CONTENT_DIR . '/uploads/patches');
  } else {
    define('SQLitePatchDir', ABSPATH . 'wp-content/uploads/patches');
  }
}

define('SQLiteListFile', SQLiteFilePath . '/utilities/plugin_lists.json');

if (!class_exists('SQLiteIntegrationUtils')) {
  require_once SQLiteFilePath . '/utilities/utility.php';
  $utils = new SQLiteIntegrationUtils();
}
if (!class_exists('SQLiteIntegrationDocument')) {
  require_once SQLiteFilePath . '/utilities/documentation.php';
  $doc = new SQLiteIntegrationDocument();
}
if (!class_exists('PatchUtils')) {
  require_once SQLiteFilePath . '/utilities/patch.php';
  $patch_utils = new PatchUtils();
}
if (!class_exists('DatabaseMaintenance')) {
	require_once SQLiteFilePath . '/utilities/database_maintenance.php';
	$maintenance = new DatabaseMaintenance();
}

/**
 * This class is for WordPress Administration Panel
 * This class and other utility classes don't affect the base functionality
 * of the plugin.
 * 
 * @package SQLite Integration
 * @author Kojima Toshiyasu
 *
 */
class SQLiteIntegration {
  /**
   * This constructor does everything needed
   */
  function __construct() {
    if (function_exists('register_activation_hook')) {
      register_activation_hook(__FILE__, array($this, 'install'));
    }
    if (function_exists('register_deactivation_hook')) {
    }
    if (function_exists('register_uninstall_hook')) {
      register_uninstall_hook(__FILE__, array('SQLiteIntegration', 'uninstall'));
    }
    if (function_exists('is_multisite') && is_multisite()) {
      add_action('network_admin_menu', array($this, 'add_network_pages'));
    } else {
      add_action('admin_menu', array($this, 'add_pages'));
    }
    add_action('plugins_loaded', array($this, 'textdomain_init'));
  }

  /**
   * Nothing to install
   * for future use...
   */
  function install() {
    global $wpdb;
    if (function_exists('is_multisite') && is_multisite()) {
      $old_blog = $wpdb->blogid;
      $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
      foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        $this->_install();
      }
      switch_to_blog($old_blog);
      return;
    } else {
      $this->_install();
      return;
    }
  }
  
  /**
   * Nothing to do...
   * We show menu and documents only to the network administrator
   */
  function _install() {
  }
  
  /**
   * Is it better that we remove wp-content/db.php and wp-content/patches
   * directory?...
   * If you migrate the site to the sever with MySQL, you have only to
   * migrate the data in the database.
   */
  function uninstall() {
    // remove patch files and patch directory
    if (file_exists(SQLitePatchDir) && is_dir(SQLitePatchDir)) {
      $dir_handle = opendir(SQLitePatchDir);
      while (($file_name = readdir($dir_handle)) !== false) {
        if ($file_name != '.' && $file_name != '..') {
          unlink(SQLitePatchDir.'/'.$file_name);
        }
      }
      rmdir(SQLitePatchDir);
    }
    // remove wp-content/db.php
    if (defined('WP_CONTENT_DIR')) {
      $target = WP_CONTENT_DIR . 'db.php';
    } else {
      $target = ABSPATH . 'wp-content/db.php';
    }
    if (file_exists($target)) {
      unlink($target);
    }
  }
  
  /**
   * We use class method to show pages and want to load style files and script
   * files only in our plugin documents, so we need add_submenu_page with parent
   * slug set to null. This means that menu items are added but hidden from the
   * users.
   */
  function add_pages() {
    global $utils, $doc, $patch_utils, $maintenance;
    if (function_exists('add_options_page')) {
      $welcome_page = add_options_page(__('SQLite Integration'), __('SQLite Integration'), 'manage_options', 'sqlite-integration', array($utils, 'welcome'));
      $util_page = add_submenu_page(null, 'System Info', 'System Info', 'manage_options', 'sys-info', array($utils, 'show_utils'));
      $edit_db   = add_submenu_page(null, 'Setting File', 'Setting File', 'manage_options', 'setting-file', array($utils, 'edit_db_file'));
      $doc_page  = add_submenu_page(null, 'Documentation', 'Documentation', 'manage_options', 'doc', array($doc, 'show_doc'));
      $patch_page = add_submenu_page(null, 'Patch Utility', 'Patch Utility', 'manage_options', 'patch', array($patch_utils, 'show_patch_page'));
      $maintenance_page = add_submenu_page(null, 'DB Maintenance', 'DB Maintenance', 'manage_options', 'maintenance', array($maintenance, 'show_maintenance_page'));
      add_action('admin_print_styles-'.$welcome_page, array($this, 'add_style_sheet'));
      add_action('admin_print_styles-'.$util_page, array($this, 'add_style_sheet'));
      add_action('admin_print_styles-'.$edit_db, array($this, 'add_style_sheet'));
      add_action('admin_print_styles-'.$doc_page, array($this, 'add_style_sheet'));
      add_action('admin_print_styles-'.$patch_page, array($this, 'add_style_sheet'));
      add_action('admin_print_scripts-'.$util_page, array($this, 'add_sqlite_script'));
      add_action('admin_print_scripts-'.$doc_page, array($this, 'add_sqlite_script'));
      add_action('admin_print_scripts-'.$patch_page, array($this, 'add_sqlite_script'));
      add_action('admin_print_scripts-'.$edit_db, array($this, 'add_sqlite_script'));
      add_action('admin_print_styles-'.$maintenance_page, array($this, 'add_style_sheet'));
    }
  }
  
  /**
   * Network admin can only see documents and manipulate patch files.
   * So, capability is set to manage_network_options.
   */
  function add_network_pages() {
    global $utils, $doc, $patch_utils, $maintenance;
    if (function_exists('add_options_page')) {
      $welcome_page = add_submenu_page('settings.php', __('SQLite Integration'), __('SQLite Integration'), 'manage_network_options', 'sqlite-integration', array($utils, 'welcome'));
      $util_page = add_submenu_page(null, 'System Info', 'System Info', 'manage_network_options', 'sys-info', array($utils, 'show_utils'));
      $edit_db   = add_submenu_page(null, 'Setting File', 'Setting File', 'manage_network_options', 'setting-file', array($utils, 'edit_db_file'));
      $doc_page  = add_submenu_page(null, 'Documentation', 'Documentation', 'manage_network_options', 'doc', array($doc, 'show_doc'));
      $patch_page = add_submenu_page(null, 'Patch Utility', 'Patch Utility', 'manage_network_options', 'patch', array($patch_utils, 'show_patch_page'));
      $maintenance_page = add_submenu_page(null, 'DB Maintenance', 'DB Maintenance', 'manage_network_options', 'maintenance', array($maintenance, 'show_maintenance_page'));
      add_action('admin_print_styles-'.$welcome_page, array($this, 'add_style_sheet'));
      add_action('admin_print_styles-'.$util_page, array($this, 'add_style_sheet'));
      add_action('admin_print_styles-'.$edit_db, array($this, 'add_style_sheet'));
      add_action('admin_print_styles-'.$doc_page, array($this, 'add_style_sheet'));
      add_action('admin_print_styles-'.$patch_page, array($this, 'add_style_sheet'));
      add_action('admin_print_scripts-'.$util_page, array($this, 'add_sqlite_script'));
      add_action('admin_print_scripts-'.$doc_page, array($this, 'add_sqlite_script'));
      add_action('admin_print_scripts-'.$patch_page, array($this, 'add_sqlite_script'));
      add_action('admin_print_scripts-'.$edit_db, array($this, 'add_sqlite_script'));
      add_action('admin_print_styles-'.$maintenance_page, array($this, 'add_style_sheet'));
    }
  }
  
  /**
   * Japanese catalog is only available
   */
  function textdomain_init() {
   global $utils;
//     $current_locale = get_locale();
//     if (!empty($current_locale)) {
//       $moFile = dirname(__FILE__) . "/languages/sqlite-wordpress-" . $current_locale . ".mo";
//       if(@file_exists($moFile) && is_readable($moFile)) load_textdomain('sqlite-wordpress', $moFile);
//     }
   load_plugin_textdomain($utils->text_domain, false, SQLiteDir.'/languages/');
  }

  /**
   * Styles and JavaScripts
   */
  function add_style_sheet() {
  	global $current_user;
  	get_currentuserinfo();
  	$admin_color = get_user_meta($current_user->ID, 'admin_color', true);
  	if ($admin_color == 'fresh') {
  		$stylesheet_file = 'style.min.css';
  	} else {
	  	$stylesheet_file = $admin_color . '.min.css';
  	}
    $style_url = SQLiteUrl . '/styles/' . $stylesheet_file;
    $style_file = SQLiteFilePath . '/styles/' . $stylesheet_file;
    if (file_exists($style_file)) {
      wp_enqueue_style('sqlite_integration_stylesheet', $style_url);
    }
  }
  function add_sqlite_script() {
    $script_url = SQLiteUrl . '/js/sqlite.min.js';
    $script_file = SQLiteFilePath . '/js/sqlite.min.js';
    if (file_exists($script_file)) {
      wp_enqueue_script('sqlite-integration', $script_url, 'jquery');
    }
  }
}

/* this is enough for initialization */
new SQLiteIntegration;
?>