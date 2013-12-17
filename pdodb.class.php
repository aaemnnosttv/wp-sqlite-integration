<?php
/**
 * @package SQLite Integration
 * @author Kojima Toshiyasu, Justin Adie
 *
 */
if (!defined('ABSPATH')) {
	echo 'Thank you, but you are not allowed to accesss this file.';
	die();
}
require_once PDODIR . 'pdoengine.class.php';
require_once PDODIR . 'install.php';

if (!defined('SAVEQUERIES')){
	define ('SAVEQUERIES', false);
}
if(!defined('PDO_DEBUG')){
  define('PDO_DEBUG', false);
}

/**
 * This class extends wpdb and replaces it.
 * It also rewrites some functions that use mysql specific functions.
 * 
 */
class PDODB extends wpdb {

  protected $dbh = null;
  
  /**
   * Constructor: emulates wpdb but this gets another parameter $db_type,
   * which is given by the constant 'DB_TYPE' defined in wp-config.php.
   * SQLite uses only $db_type and all the others are simply ignored.
   * 
   */
  function __construct() {
    register_shutdown_function(array($this, '__destruct'));

    if (WP_DEBUG)
      $this->show_errors();
    
    $this->init_charset();

    $this->db_connect();
  }
  
  function __destruct() {
    return true;
  }
  
  /**
   * dummy out the MySQL function
   * @see wpdb::set_charset()
   */
  function set_charset($dbh, $charset = null, $collate = null) {
  	if ( ! isset( $charset ) )
  		$charset = $this->charset;
  	if ( ! isset( $collate ) )
  		$collate = $this->collate;
  }
  /**
   * dummy out the MySQL function
   * @see wpdb::select()
   */
  function select($db, $dbh = null) {
    if (is_null($dbh))
      $dbh = $this->dbh;
    $this->ready = true;
    return;
  }

  /**
   * overrides wpdb::_real_escape(), which uses mysql_real_escape_string().
   * @see wpdb::_real_escape()
   */
  function _real_escape($string) {
    return addslashes($string);
  }
  
  /**
   * overrides wpdb::print_error()
   * @see wpdb::print_error()
   */
  function print_error($str = '') {
    global $EZSQL_ERROR;
    
    if (!$str) {
      $err = $this->dbh->get_error_message() ? $this->dbh->get_error_message() : '';
      if (!empty($err)) $str = $err[2]; else $str = '';
    }
    $EZSQL_ERROR[] = array('query' => $this->last_query, 'error_str' => $str);
    
    if ($this->suppress_errors)
      return false;
    
    wp_load_translations_early();
    
    if ($caller = $this->get_caller())
      $error_str = sprintf(__('WordPress database error %1$s for query %2$s made by %3$s'), $str, $this->last_query, $caller);
    else
      $error_str = sprintf(__('WordPress database error %1$s for query %2$s'), $str, $this->last_query);
    
    error_log($error_str);
    
    if (!$this->show_errors)
      return false;
    
    if (is_multisite()) {
      $msg = "WordPress database error: [$str]\n{$this->last_query}\n";
      if (defined('ERRORLOGFILE'))
        error_log($msg, 3, ERRORLOGFILE);
      if (defined('DIEONDBERROR'))
        wp_die($msg);
    } else {
      $str = htmlspecialchars($str, ENT_QUOTES);
      $query = htmlspecialchars($this->last_query, ENT_QUOTES);
      
			print "<div id='error'>
			<p class='wpdberror'><strong>WordPress database error:</strong> [$str]<br />
			<code>$query</code></p>
			</div>";
    }
  }

  /**
   * overrides wpdb::db_connect()
   * @see wpdb::db_connect()
   */
  function db_connect() {
    if (WP_DEBUG) {
      $this->dbh = new PDOEngine();
    } else {
    	// WP_DEBUG or not, we don't use @ which causes the slow execution
    	// PDOEngine class will take the Exception handling.
      $this->dbh = new PDOEngine();
    }
    if (!$this->dbh) {
      wp_load_translations_early();//probably there's no translations
      $this->bail(sprintf(__("<h1>Error establlishing a database connection</h1><p>We have been unable to connect to the specified database. <br />The error message received was %s"), $this->dbh->errorInfo()));
      return;
    }
    $this->ready = true;
  }
  
  /**
   * overrides wpdb::query()
   * @see wpdb::query()
   */
  function query($query) {
    if (!$this->ready)
      return false;
    
    $query = apply_filters('query', $query);
    
    $return_val = 0;
    $this->flush();
    
    $this->func_call = "\$db->query(\"$query\")";
    
    $this->last_query = $query;
    
    if (defined('SAVEQUERIES') && SAVEQUERIES)
      $this->timer_start();
    
    $this->result = $this->dbh->query($query);
    $this->num_queries++;
    
    if (defined('SAVEQUERIES') && SAVEQUERIES)
      $this->queries[] = array($query, $this->timer_stop(), $this->get_caller());
    
    if ($this->last_error = $this->dbh->get_error_message()) {
      if (defined('WP_INSTALLING') && WP_INSTALLING) {
        //$this->suppress_errors();
      } else {
        $this->print_error($this->last_error);
        return false;
      }
    }
    
    if (preg_match('/^\\s*(create|alter|truncate|drop|optimize)\\s*/i', $query)) {
//       $return_val = $this->result;
      $return_val = $this->dbh->get_return_value();
    } elseif (preg_match('/^\\s*(insert|delete|update|replace)\s/i', $query)) {
      $this->rows_affected = $this->dbh->get_affected_rows();
      if (preg_match('/^\s*(insert|replace)\s/i', $query)) {
        $this->insert_id = $this->dbh->get_insert_id();
      }
      $return_val = $this->rows_affected;
    } else {
      $this->last_result = $this->dbh->get_query_results();
      $this->num_rows = $this->dbh->get_num_rows();
      $return_val = $this->num_rows;
    }
    return $return_val;
  }
  
  /**
   * overrides wpdb::load_col_info(), which uses a mysql function.
   * @see wpdb::load_col_info()
   */
  function load_col_info() {
    if ($this->col_info)
      return;
    $this->col_info = $this->dbh->get_columns();
  }
  
  /**
   * overrides wpdb::has_cap()
   * We don't support collation, group_concat, set_charset
   * @see wpdb::has_cap()
   */
  function has_cap($db_cap) {
    switch(strtolower($db_cap)) {
      case 'collation':
      case 'group_concat':
      case 'set_charset':
        return false;
      case 'subqueries':
        return true;
      default:
        return false;
    }
  }
  /**
   * overrides wpdb::db_version()
   * Returns mysql version number but it means nothing for SQLite.
   * @see wpdb::db_version()
   */
  function db_version() {
    global $required_mysql_version;
    return $required_mysql_version;
  }
}

/**
 * Initialize $wpdb with PDODB class
 */
if (!isset($wpdb)) {
	global $wpdb;
	$wpdb = new PDODB();
}
?>