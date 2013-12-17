<?php
/**
 * 
 * @package SQLite Integration
 * @author Kojima Toshiyasu
 *
 */
class SQLiteIntegrationUtils {
  
  public $text_domain = 'sqlite-integration';

  function __construct() {
  }

  /**
   * function to return contents of 'FQDBDIR/debug.txt'.
   * if the file is not existent, returns false.
   * @return string|boolean
   */
  private function show_error_log() {
    $file = FQDBDIR . 'debug.txt';
    if (file_exists($file)) {
      $contents = file_get_contents($file);
      return $contents;
    } else {
      return false;
    }
  }
  /**
   * function to clear the contents of 'FQDBIR/debug.txt'
   * @return boolean
   */
  private function clear_log_file() {
    $result = false;
    $file = FQDBDIR . 'debug.txt';
    $fh = fopen($file, "w+");
    if ($fh) {
      if (flock($fh, LOCK_EX)) {
        if (ftruncate($fh, 0) === false) {
          return false;
        }
        flock($fh, LOCK_UN);
      } else {
        return false;
      }
    }
    fclose($fh);
    return true;
  }
  
  /**
   * function to return associative array of system informations
   * sys_info['WordPress'] => WordPress Version
   * sys_info['PHP']       => PHP Version
   * @return array
   */
  private function get_system_info() {
    global $wp_version;
    $sys_info = array();
    $sys_info['WordPress'] = $wp_version;
    $sys_info['PHP'] = PHP_VERSION;
    return $sys_info;
  }
  /**
   * function to return various database information
   * @return assoc array
   */
  private function get_database_status() {
    global $wpdb;
    $status = array();
    $db_size = $this->get_database_size();
    $encoding = $wpdb->get_var("PRAGMA encoding");
    $integrity = $wpdb->get_var("PRAGMA integrity_check");
    $page_size = $wpdb->get_var("PRAGMA page_size");
    $page_count = $wpdb->get_var("PRAGMA page_count");
    $unused_page = $wpdb->get_var("PRAGMA freelist_count");
    $collation_list = $wpdb->get_results("PRAGMA collation_list");
    $compile_options = $wpdb->get_results("PRAGMA compile_options");
    foreach ($collation_list as $col) {
      $collations[] = $col->name;
    }
    foreach ($compile_options as $opt) {
      $options[] = $opt->compile_option;
    }
    $status['size'] = $db_size;
    $status['integrity'] = $integrity;
    $status['pagesize'] = $page_size;
    $status['page'] = $page_count;
    $status['unused'] = $unused_page;
    $status['encoding'] = $encoding;
    $status['collations'] = $collations;
    $status['options'] = $options;
    return $status;
  }
  /**
   * function to return associative array
   * array( table name => array( index name ( column name )))
   * for each table in the database
   * @return array
   */
  private function get_tables_info() {
    global $wpdb;
    $table_info = array();
    $tables = $wpdb->get_col("SHOW TABLES");
    foreach ($tables as $table) {
      $index_object = $wpdb->get_results("SHOW INDEX FROM $table");
      if (empty($index_object)) {
        $table_info[$table][] = 'no index';
      } else {
        foreach ($index_object as $index) {
          $table_info[$table][] = $index->Key_name . ' ( ' . $index->Column_name . ' )';
        }
      }
    }
    $table_info = array_reverse($table_info);
    return $table_info;
  }
  /**
   * function to return the autoincremented values of each table
   * @return assoc array name => sequence, or false
   */
  private function get_sequence() {
    global $wpdb;
    $sequence_info = array();
    $results = $wpdb->get_results("SELECT name, seq FROM sqlite_sequence");
    if (is_null($results) || empty($results)) {
      return false;
    } else {
      foreach ($results as $result) {
        $sequence_info[$result->name] = $result->seq;
      }
      return $sequence_info;
    }
  }
  /**
   * function to return contents of 'wp-content/db.php' file
   * if the file is not existent, returns false.
   * @return string|boolean
   */
  private function show_db_php() {
    if (defined('WP_CONTENT_DIR')) {
      $file = WP_CONTENT_DIR . '/db.php';
    } else {
      $file = ABSPATH . 'wp-content/db.php';
    }
    if (file_exists($file)) {
      if (is_readable($file)) {
        $contents = file_get_contents($file);
        echo $contents;
      } else {
        echo 'file is not readable';
      }
    } else {
      echo 'file doesn\'t exist';
      return false;
    }
  }
  /**
   * function to get the textarea contents and write into db.php file
   * @param string $contents
   * @return boolean
   */
  private function save_db_php($contents) {
    if (defined('WP_CONTENT_DIR')) {
      $file = WP_CONTENT_DIR . '/db.php';
    } else {
      $file = ABSPATH . 'wp-content/db.php';
    }
    $fh = fopen($file, "w+");
    if ($fh) {
      if (flock($fh, LOCK_EX)) {
        if (fwrite($fh, $contents) === false) {
          return false;
        }
        flock($fh, LOCK_UN);
      } else {
        return false;
      }
    }
    fclose($fh);
    return true;
  }
  /**
   * function to optimize database file
   * only to give vacuum command to SQLite
   * @return boolean
   */
  private function optimize_db() {
    global $wpdb;
    $result = $wpdb->query("OPTIMIZE");
    return $result;
  }
  /**
   * function to get SQLite database file size
   * @return string
   */
  private function get_database_size() {
    $db_file = FQDB;
    if (file_exists($db_file)) {
      $size = filesize($db_file);
      clearstatcache(true, $db_file);
      return $this->convert_to_formatted_number($size);
    }
  }
  /**
   * function to format file size to unit byte
   * @param integer $size
   * @return string
   */
  private function convert_to_formatted_number($size) {
    $unim = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB');
    $count = 0;
    while ($size >= 1024) {
      $count++;
      $size = $size / 1024;
    }
    return number_format($size, ($count ? 2 : 0), '.', ',') . ' ' . $unim[$count];
  }
  
  /**
   * function to echo plugins info table component
   */
  private function show_plugins_info() {
    $domain = $this->text_domain;
    if (file_exists(SQLiteListFile)) {
      $contents = file_get_contents(SQLiteListFile);
      $plugin_info_list = json_decode($contents);
      $plugins = get_plugins();
      foreach ($plugins as $key => $data) {
        $name = '<a href="'.$data['PluginURI'].'">'.$data['Name'].'</a>';
        foreach ($plugin_info_list as $plugin_info) {
          if ($data['Name'] == $plugin_info->name) {
            $class = 'class="'.$plugin_info->class.'"';
            // for Internationalization... it's a redundant codes, mm...
            // I might have made a mistake to store data in json format...
            switch ($plugin_info->compat) {
              case 'Needs Patch':
                if (!empty($plugin_info->patch_url)) {
                  $compat = '<a href="'.$plugin_info->patch_url.'">'.__('Needs Patch', $domain).'</a>';
                } else {
                  $compat = __('Needs Patch', $domain);
                }
                break;
              case 'Probably No':
                $compat = __('Probably No', $domain);
                break;
              case 'Probably Yes':
                $compat = __('Probably Yes', $domain);
                break;
              case 'No':
                $compat = __('No', $domain);
                break;
              case 'Checked':
                $compat = __('Checked', $domain);
                break;
              default:
                $compat = __('Not Checked', $domain);
                break;
            }
            break;
          } else {
            $class = 'class="compatible"';
            $compat = __('Not Checked', $domain);
          }
        }
        if (is_plugin_active_for_network($key)) {
          echo '<tr data-table='.'\'{"name":"'.$data['Name'].'","active":"sitewide active","comp":"'.strip_tags($compat).'"}\''." $class>";
          echo sprintf('<td>%1$s</td><td>%2$s</td><td>%3$s</td>', $name,  __('Sitewide Active', $domain), $compat);
        } elseif (is_plugin_active($key)) {
          echo '<tr data-table='.'\'{"name":"'.$data['Name'].'","active":"active","comp":"'.strip_tags($compat).'"}\''." $class>";
          echo sprintf('<td>%1$s</td><td>%2$s</td><td>%3$s</td>', $name,  __('Active', $domain), $compat);
        } else {
          echo '<tr data-table='.'\'{"name":"'.$data['Name'].'","active":"inactive","comp":"'.strip_tags($compat).'"}\''." $class>";
          echo sprintf('<td>%1$s</td><td>%2$s</td><td>%3$s</td>', $name, __('Inactive', $domain), $compat);
        }
        echo '</tr>';
      }
    }
  }

  /**
   * function to return output of phpinfo() as an array
   * See PHP Manual
   * @return array
   */
  private function parse_php_modules() {
    ob_start();
    phpinfo(INFO_MODULES);
    $infos = ob_get_contents();
    ob_end_clean();
    
    $infos = strip_tags($infos, '<h2><th><td>');
    $infos = preg_replace('/<th[^>]*>([^<]+)<\/th>/', "<info>\\1</info>", $infos);
    $infos = preg_replace('/<td[^>]*>([^<]+)<\/td>/', "<info>\\1</info>", $infos);
    $info_array = preg_split('/(<h2>[^<]+?<\/h2>)/', $infos, -1, PREG_SPLIT_DELIM_CAPTURE);
    $modules = array();
    for ($i = 1; $i < count($info_array); $i++) {
      if (preg_match('/<h2>([^<]+)<\/h2>/', $info_array[$i], $match)) {
        $name = trim($match[1]);
        $info_array2 = explode("\n", $info_array[$i+1]);
        foreach ($info_array2 as $info) {
          $pattern = '<info>([^<]+)<\/info>';
          $pattern3 = "/$pattern\\s*$pattern\\s*$pattern/";
          $pattern2 = "/$pattern\\s*$pattern/";
          if (preg_match($pattern3, $info, $match)) {
            $modules[$name][trim($match[1])] = array(trim($match[2]), trim($match[3]));
          } elseif (preg_match($pattern2, $info, $match)) {
            $modules[$name][trim($match[1])] = trim($match[2]);
          }
        }
      }
    }
    return $modules;
  }
  /**
   * function to echo PHP module info
   * @param string $module_name
   * @param string $setting_name
   */
  private function get_module_setting($module_name, $setting_name) {
    $module_info = $this->parse_php_modules();
    echo $module_info[$module_name][$setting_name];
  }
  function show_parent() {
    if (function_exists('is_multisite') && is_multisite()) {
      return 'settings.php';
    } else {
      return 'options-general.php';
    }
  }

  /**
   * function to parse FQDBDIR and return backup database files
   */
  private function get_backup_files() {
  	$db_name = basename(FQDB);
  	$names_to_exclude = array('.', '..', '.htaccess', 'debug.txt', '.ht.sqlite', $db_name);
  	$backup_files = array();
  	if (is_dir(FQDBDIR)) {
  		if ($dir_handle = opendir(FQDBDIR)) {
  			while (($file_name = readdir($dir_handle)) !== false) {
  				if (in_array($file_name, $names_to_exclude)) continue;
  				$backup_files[] = $file_name;
  			}
  		}
  	}
  	return $backup_files;
  }
  
  /**
   * function to create backup file
   */
  private function backup_db() {
  	$result = array();
  	$database_file = FQDB;
  	$db_name = basename(FQDB);
  	if (!file_exists($database_file)) {
  		return false;
  	}
  	$today = date("Ymd");
  	if (!extension_loaded('zip')) {
  		$backup_file = $database_file . '.' . $today . '.back';
  		if (copy($database_file, $backup_file)) {
  			$result['success'] = basename($backup_file) . __(' was created.', $domain);
  		} else {
  			$result['error'] = basename($backup_file) . __(' was not created.', $domain);
  		}
  	} else {
  		$backup_file = $database_file . '.' . $today . '.zip';
  		$zip = new ZipArchive();
  		$res = $zip->open($backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
  		if ($res === true) {
  			$zip->addFile($database_file, $db_name);
  			$result['success'] = basename($backup_file) . __(' was created.', $domain);
  		} else {
  			$result['error'] = basename($backup_file) . __(' was not created.', $domain);
  		}
  		$zip->close();
  	}
  	return $result;
  }
  
  private function delete_backup_db() {
  	global $utils;
  	$domain = $utils->text_domain;
  	$file_names = array();
  	$results = array();
  	if (isset($_POST['backup_checked'])) {
  		$file_names = $_POST['backup_checked'];
  	} else {
  		return false;
  	}
  	if (chdir(FQDBDIR)) {
  		foreach ($file_names as $file) {
  			if (unlink($file)) {
  				$results[$file] = sprintf(__('File %s was deleted.', $domain), $file);
  			} else {
  				$results[$file] = sprintf(__('Error! File was not deleted.', $domain), $file);
  			}
  		}
  	}
  	return $results;
  }
  
  function welcome() {
    $domain = $this->text_domain;
    if (isset($_GET['page']) && $_GET['page'] == 'sqlite-integration') :?>
    <div class="wrap single" id="sqlite-admin-wrap">
    <h2><?php _e('Welcome to SQLite Integration', $domain) ?></h2>
    <p>
      <?php _e('Thank you for using SQLite Integration plugin!', $domain) ?>
    </p>
    <p>
      <?php _e('You read this message, which means you have succeeded in installing WordPress with this plugin SQLite Integration. Congratulations and enjoy your Blogging!', $domain) ?>
    </p>
    <p>
      <?php _e('You don\'t have to set any special settings. In fact there are no other settings. You can write articles or pages and customize you WordPress in an ordinary way. You want to install your plugins? All right, go ahead. But some of them may be incompatible with this. Please read more information about this plugin and your SQLite database below.', $domain)?>
    </p>
    <p><?php _e('Deactivation makes this documents and utilities disappear from the dashboard, but it doesn\'t affect the functionality of the SQLite Integration. when uninstalled, it will remove wp-content/uploads/patches directory (if exists) and wp-content/db.php file altogether.', $domain);?></p>
    <table class="widefat" cellspacing="0" id="menu">
      <thead>
        <th><?php _e('Title', $domain);?></th>
        <th><?php _e('Contents', $domain);?></th>
      </thead>
      <tbody>
        <tr>
          <td class="menu-title"><a href="<?php echo $this->show_parent(); ?>?page=doc"><?php _e('Documentation', $domain) ?></a></td>
          <td><?php _e('You can read documentation about this plugin and plugin compatibility.', $domain)?></td>
        </tr>
        <tr>
        	<td class="menu-title"><a href="<?php echo $this->show_parent();?>?page=sys-info"><?php _e('System Info', $domain) ?></a></td>
        	<td><?php _e('You can see database and system information.', $domain)?></td>
        </tr>
        <tr>
        	<td class="menu-title"><a href="<?php echo $this->show_parent(); ?>?page=setting-file"><?php _e('Miscellaneous', $domain) ?></a></td>
        	<td><?php _e('You can see the error log and edit db.php file (if necessary) and optimize your database.', $domain)?></td>
        </tr>
        <tr>
        	<td><a href="<?php echo $this->show_parent();?>?page=patch"><?php _e('Patch Utility', $domain)?></a></td>
        	<td><?php _e('You can upload patch files and apply them to the incompatible plugins.', $domain)?></td>
        </tr>
        <tr>
        	<td><a href="<?php echo $this->show_parent();?>?page=maintenance"><?php _e('Maintenance', $domain);?></a></td>
        	<td><?php _e('You can check your database and fix it if needed.', $domain);?></td>
        </tr>
      </tbody>
    </table>
    </div>

    <?php endif;
  }

  /**
   * function to show Utiliy page contents
   */
  function show_utils() {
    $domain = $this->text_domain;
    if (is_multisite() && !current_user_can('manage_network_options')) {
      die(__('You are not allowed to access this page!', $domain));
    } elseif (!current_user_can('manage_options')) {
      die(__('You are not allowed to access this page!', $domain));
    }
    if (isset($_GET['page']) && $_GET['page'] == 'sys-info') :?>
    <div class="navigation">
      <ul class="navi-menu">
        <li class="menu-item"><a href="<?php echo $this->show_parent();?>?page=doc"><?php _e('Documentation', $domain)?></a></li>
        <li class="menu-selected"><?php _e('System Info', $domain);?></li>
        <li class="menu-item"><a href="<?php echo $this->show_parent();?>?page=setting-file"><?php _e('Miscellaneous', $domain);?></a></li>
        <li class="menu-item"><a href="<?php echo $this->show_parent();?>?page=patch"><?php _e('Patch Utility', $domain);?></a></li>
        <li class="menu-item"><a href="<?php echo $this->show_parent();?>?page=maintenance"><?php _e('Maintenance', $domain);?></a></li>
      </ul>
    </div>
    <div class="wrap" id="sqlite-admin-wrap">
    <h2><?php _e('System Information', $domain) ?></h2>
    <h3><?php _e('PHP Informations', $domain) ?></h3>
    <?php $info = $this->get_system_info(); ?>
    <table class="widefat page fixed" cellspacing="0" id="sys-info">
      <thead>
      <tr>
        <th class="item"><?php _e('Items', $domain);?></th>
        <th><?php _e('Description', $domain);?></th>
      </tr>
      </thead>
      <tbody>
      <tr>
        <td class="item"><?php _e('WordPress Version', $domain);?></td>
        <td><?php echo $info['WordPress']; ?></td>
      </tr>
      <tr>
        <td class="item"><?php _e('PHP Version', $domain);?></td>
        <td><?php echo $info['PHP']; ?></td>
      </tr>
      <tr>
        <td class="item"><?php _e('PDO Support', $domain);?></td>
        <td><?php $this->get_module_setting('PDO', 'PDO support');?></td>
      </tr>
      <tr>
        <td class="item"><?php _e('PDO Drivers', $domain);?></td>
        <td><?php $this->get_module_setting('PDO', 'PDO drivers');?></td>
      </tr>
      <tr>
        <td class="item"><?php _e('PDO Driver for SQLite 3.x', $domain);?></td>
        <td><?php $this->get_module_setting('pdo_sqlite', 'PDO Driver for SQLite 3.x');?></td>
      </tr>
      <tr>
        <td class="item"><?php _e('SQLite Library Version', $domain);?></td>
        <td><?php $this->get_module_setting('pdo_sqlite', 'SQLite Library');?>
      </tr>
      </tbody>
    </table>
    
    <h3><?php _e('Your Database Status', $domain)?></h3>
    <table class="widefat page fixed" cellspacing="0" id="status">
      <thead>
        <tr>
        	<th><?php _e('Items', $domain)?></th>
        	<th><?php _e('Status', $domain)?></th>
        </tr>
      </thead>
      <tbody>
      <?php $status = $this->get_database_status();?>
        <tr>
          <td><?php _e('Database Size', $domain);?></th>
          <td><?php echo $status['size'];?></td>
        </tr>
        <tr>
        	<td><?php _e('Page Size', $domain);?></td>
        	<td><?php echo $status['pagesize'];?></td>
        </tr>
        <tr>
        	<td><?php _e('Total Number of Pages', $domain);?></td>
        	<td><?php echo $status['page'];?></td>
        </tr>
        <tr>
        	<td><?php _e('Unused Page', $domain)?></td>
        	<td><?php echo $status['unused'];?></td>
        </tr>
        <tr>
        	<td><?php _e('Integrity Check', $domain);?></td>
        	<td><?php echo strtoupper($status['integrity']);?></td>
        </tr>
        <tr>
          <td><?php _e('Encoding', $domain);?></th>
          <td><?php echo $status['encoding'];?></td>
        </tr>
        <tr>
          <td><?php _e('Collations', $domain);?></th>
          <td>
          <?php $i = 0;
            foreach($status['collations'] as $col) {
            if ($i != 0) echo '<br />';
            echo ($i+1).'. '.$col;
            $i++;
          }
          ?>
          </td>
        </tr>
        <tr>
        	<td><?php _e('Compile Options', $domain);?></td>
        	<td>
        	<?php $i = 0;
        	  foreach ($status['options'] as $op) {
        	    if ($i != 0) echo '<br />';
        	    echo ($i+1).'. '.$op;
        	    $i++;
        	  }
        	?>
        	</td>
        </tr>
      </tbody>
    </table>
    
    <h3><?php _e('Database Tables and Indexes', $domain) ?></h3>
    <p>
    <?php _e('Table names in brown are required by WordPress, and those in blue are created by some plugins. The table sqlite_sequence is not a WordPress table but a table required by SQLite to store the current autoincremented value of each table, which is displayed in the parenthesis after the table names. You can\'t manipulate the tables or indexes here. Please use SQLite utilities (e.g. SQLiteManager).', $domain) ?>
    </p>
    <table class="widefat page fixed" cellspacing="0" id="sqlite-table">
      <thead>
      <tr>
        <th data-sort='{"key":"tblName"}' class="tbl-name"><?php _e('Table Name', $domain) ?></th>
        <th data-sort='{"key":"which"}' class="tbl_owner"><?php _e('System/User', $domain) ?>
        <th class="tbl_index"><?php _e('Index ( Column )', $domain) ?></th>
      </tr>
      </thead>
      <tbody>
      <?php global $wpdb;
      $table_info = $this->get_tables_info();
      $table_seq  = $this->get_sequence();
			$network_tables = array();
      if (is_multisite()) {
        $tmp_tables = $wpdb->tables('blog', false);
        $blogs = $wpdb->get_col("SELECT * FROM {$wpdb->prefix}blogs");
        if (count($blogs) > 1) {
          foreach ($blogs as $id) {
            if ($id == 1) continue;
            foreach ($tmp_tables as $tmp_tbl) {
              $network_tables[] = $wpdb->prefix.$id.'_'.$tmp_tbl;
            }
          }
        }
      }
      foreach ($table_info as $tbl_name => $index) : ?>
        <?php if (in_array($tbl_name, $wpdb->tables('all', true)) || in_array($tbl_name, $network_tables) || $tbl_name == 'sqlite_sequence') {
          $which_table = 'system';
        } else {
          $which_table = 'user';
        }
        echo '<tr data-table=\'{"tblName":"' . $tbl_name . '","which":"' . $which_table . '"}\'>';
        if (array_key_exists($tbl_name, $table_seq)) $tbl_name .= " ($table_seq[$tbl_name])";
        echo '<td class="'. $which_table . '">' . $tbl_name . '</td>';
        echo '<td class="'.$which_table.'">' . $which_table . ' table</td>';?>
        <td class="<?php echo $which_table?>"><?php foreach ($index as $idx) { echo $idx . '<br />';} ?></td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
    </div>
    
    <div class="wrap" id="sqlite-admin-side-wrap">
    <h2><?php _e('Plugin Info', $domain)?></h2>
    <p>
    <?php _e('This table shows plugins you have installed and their compatibility.', $domain)?>
    </p>
    <table class="widefat page fixed" cellspacing="0" id="plugins-info">
    <thead>
      <tr>
        <th data-sort='{"key":"name"}' class="installed-plugins"><?php _e('Installed Plugins', $domain)?></th>
        <th data-sort='{"key":"active"}' class="active-plugins"><?php _e('Active/Inactive', $domain)?></th>
        <th data-sort='{"key":"comp"}' class="compatible"><?php _e('Compatible', $domain)?></th>
      </tr>
    </thead>
    <tbody>
    <?php $this->show_plugins_info();?>
    </tbody>
    </table>
    </div>
    <?php endif;
  }

  /**
   * function to show Setting File page
   */
  function edit_db_file() {
    $domain = $this->text_domain;
    if (is_multisite() && !current_user_can('manage_network_options')) {
      die(__('You are not allowed to access this page!', $domain));
    } elseif (!current_user_can('manage_options')) {
      die(__('You are not allowed to access this page!', $domain));
    }
    if (isset($_POST['sqlitewordpress_log_reset'])) {
      check_admin_referer('sqlitewordpress-log-reset-stats');
      if ($this->clear_log_file()) {
        $messages = __('Log cleared', $domain);
        echo '<div id="message" class="updated fade">'.$messages.'</div>';
      } else {
        $messages = __('Log not cleared', $domain);
        echo '<div id="message" class="updated fade">'.$messages.'</div>';
      }
    }
    if (isset($_POST['sqlitewordpress_db_save'])) {
      check_admin_referer('sqlitewordpress-db-save-stats');
      if (isset($_POST['dbfile'])) {
        $contents = $_POST['dbfile'];
        if (get_magic_quotes_gpc() || version_compare(PHP_VERSION, '5.4', '>=')) {
          $contents = stripslashes($contents);
        }
        if ($this->save_db_php($contents)) {
          $messages = __('db.php was saved', $domain);
          echo '<div id="message" class="updated fade">'.$messages.'</div>';
        } else {
          $messages = __('Error! db.php couldn\'t be saved', $domain);
          echo '<div id="message" class="updated fade">'.$messages.'</div>';
        }
      }
    }
    if (isset($_POST['sqlitewordpress_db_optimize'])) {
      check_admin_referer('sqlitewordpress-db-optimize-stats');
      $size_before = $this->get_database_size();
      $result = $this->optimize_db();
      if ($result) {
        $size_after = $this->get_database_size();
        $messages = sprintf(__('Optimization finished. Before optimization: %1$s, After optimization: %2$s.', $domain), $size_before, $size_after);
        echo '<div id="message" class="updated fade">'.$messages.'</div>';
      } else {
        $messages = __('Optimization failed', $domain);
        echo '<div id="message" class="updated fade">'.$messages.'</div>';
      }
    }
    if (isset($_POST['backup_db'])) {
			check_admin_referer('sqliteintegration-backup-manip-stats');
			$results = $this->backup_db();
			if ($results === false) {
				$message = __('Couldn\'t find your database file.');
				echo '<div id="message" class="updated fade">'.$message.'</div>';
			} elseif (is_array($results) && array_key_exists('success', $results)) {
				echo '<div id="message" class="updated fade">'.$results['success'].'</div>';
			} else {
				echo '<div id="message" class="update fade">'.$results['error'].'</div>';
			}
		}
		if (isset($_POST['delete_backup_files'])) {
			check_admin_referer('sqliteintegration-backup-manip-stats');
			$results = $this->delete_backup_db();
			if ($results === false) {
				$message = __('Please select backup file(s).', $domain);
				echo '<div id="message" class="updated fade">'.$message.'</div>';
			} elseif (is_array($results) && count($results) > 0) {
				echo '<div id="message" class="updated fade">';
				foreach ($results as $key => $val) {
					echo $val.'<br />';
				}
				echo '</div>';
			} else {
				$message = __('Error! Please remove file(s) manyally.', $domain);
				echo '<div id="message" class="updated fade">'.$message.'</div>';
			}
		}
    if (isset($_GET['page']) && $_GET['page'] == 'setting-file') :?>
      <div class="navigation">
        <ul class="navi-menu">
          <li class="menu-item"><a href="<?php echo $this->show_parent();?>?page=doc"><?php _e('Documentation', $domain)?></a></li>
          <li class="menu-item"><a href="<?php echo $this->show_parent();?>?page=sys-info"><?php _e('System Info', $domain) ?></a></li>
          <li class="menu-selected"><?php _e('Miscellaneous', $domain);?></li>
          <li class="menu-item"><a href="<?php echo $this->show_parent();?>?page=patch"><?php _e('Patch Utility', $domain)?></a></li>
        	<li class="menu-item"><a href="<?php echo $this->show_parent();?>?page=maintenance"><?php _e('Maintenance', $domain);?></a></li>
        </ul>
      </div>
      <div class="wrap single" id="sqlite-admin-wrap">
      <h2><?php _e('Database Optimization, Error Log, Init File', $domain)?></h2>
      <h3><?php _e('Optimize You Database', $domain)?></h3>
      <p>
      <?php _e('This button sends &quot;vacuum&quot; command to your SQLite database. That command reclaims space after data has been deleted.', $domain)?>
      </p>
      <form action="" method="post">
      <?php if (function_exists('wp_nonce_field')) {
        wp_nonce_field('sqlitewordpress-db-optimize-stats');
      }
      ?>
      <p>
      <input type="submit" name="sqlitewordpress_db_optimize" value="<?php _e('Optimize', $domain)?>" onclick="return confirm('<?php _e('Are you sure to optimize your database?\n\nClick [Cancel] to stop, [OK] to continue.', $domain);?>')" class="button-primary">
      </p>
      </form>
      <h3><?php _e('Create or Delete backup file(s)', $domain);?></h3>
      <p>
      	<?php _e('Click the backup button below if you want to create a current snapshot of your database file. The backup file is named &lsquo;DB_FILE_NAME.yyyymmdd.zip&rsquo; if PHP zip extension is loaded or &lsquo;DB_FILE_NAME.yyyymmdd.back&rsquo; if not loaded, and is put in the same directory that the database is in.', $domain);?>
      </p>
      <p>
      	<?php _e('If you want to delete the file(s), check the file name and click the Delete button. You can check multiple files.', $domain);?>
      </p>
      <?php $backup_files = $this->get_backup_files();?>
      <form action="" method="post" id="delete-backup-form">
      	<?php if (function_exists('wp_nonce_field')) {
      		wp_nonce_field('sqliteintegration-backup-manip-stats');
      	}
      	?>
      	<table class="widefat page fixed" id="backup-files">
      		<thead>
      			<tr>
      				<th class="item"><?php _e('Delete', $domain);?></th>
      				<th data-sort='{"key":"name"}'><?php _e('Backup Files', $domain);?></th>
      			</tr>
      		</thead>
      		<tbody>
      			<?php if (!empty($backup_files)) : ?>
      			<?php foreach ($backup_files as $file) : ?>
      			<tr data-table='{"name":"<?php echo $file;?>"}'>
      				<td><input type="checkbox" id="backup_check" name="backup_checked[]" value="<?php echo $file;?>"/></td>
      				<td><?php echo $file;?></td>
      			</tr>
      			<?php endforeach;?>
      			<?php endif;?>
      		</tbody>
      	</table>
      	<p>
	      <input type="submit" name="backup_db" class="button-primary" value="<?php _e('Backup', $domain);?>" onclick="return confirm('<?php _e('Are you sure to make a backup file?\n\nClick [Cancel] to stop, [OK] to continue.', $domain);?>')" />
	      <input type="submit" name="delete_backup_files" class="button-primary" value="<?php _e('Delete file', $domain);?>" onclick="return confirm('<?php _e('Are you sure to delete backup file(s)?\n\nClick [Cancel] to stop, [OK] to continue.', $domain);?>')" />
      	</p>
      </form>
      <h3><?php _e('SQLite Integration Error Log', $domain);?></h3>
      <p>
      <?php _e('This is the contents of SQLite Integration error log file(default: wp-content/database/debug.txt). If you want to clear this file, click the Clear Log button.', $domain)?>
      </p>
      <form action="" method="post">
      <?php if (function_exists('wp_nonce_field')) {
        wp_nonce_field('sqlitewordpress-log-reset-stats');
      }
      ?>
      <textarea name="errorlog" id="errorlog" cols="70" rows="10">
<?php $ret_val = $this->show_error_log();
      if ($ret_val === false || empty($ret_val)) {
        $message = __('No error messages are found', $domain);
        echo $message;
      } else {
        echo $ret_val;
      }
      ?>
      </textarea>
      <p>
      <input type="submit" name="sqlitewordpress_log_reset" value="<?php _e('Clear Log', $domain)?>" onclick="return confirm('<?php _e('Are you sure to clear Log?\n\nClick [Cancel] to stop, [OK] to continue.', $domain);?>')" class="button-primary">
      </p>
      </form>

      <h3><?php _e('Edit Initial File (wp-content/db.php)', $domain)?></h3>
      <p>
        <?php _e('When you go &quot;Plugins &raquo; Edit Plugin&quot; page, you can edit plugin source file. But you can\'t see this file there because it is not in the plugin directory. If you need to edit this file, you can edit here. This settings may cause problems. <span class="alert">If you don\'t understand well, please don\'t edit this file</span>.', $domain)?>
      </p>
      <form action="" method="post">
      <?php if (function_exists('wp_nonce_field')) {
        wp_nonce_field('sqlitewordpress-db-save-stats');
      }
      ?>
      <textarea name="dbfile" id="dbfile" cols="70" rows="10">
<?php $this->show_db_php();?></textarea>
      <p>
      <input type="submit" name="sqlitewordpress_db_save" value="<?php _e('Save')?>" onclick="return confirm('<?php _e('Are you sure to save this file?\n\nClick [Cancel] to stop, [OK] to continue.', $domain);?>')" class="button-primary">
      </p>
      </form>
      
      </div>
    <?php endif;
  }
}
?>
