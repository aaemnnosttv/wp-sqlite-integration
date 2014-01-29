<?php
/**
 * This file defines PDOSQLiteDriver class.
 * 
 * @package SQLite Integration
 * @author  Kojima Toshiyasu
 */

/**
 * This class is for rewriting various query string except CREATE and ALTER.
 * 
 */
class PDOSQLiteDriver {

	/**
	 * Variable to indicate the query types.
	 * 
	 * @var string $query_type
	 */
	public $query_type = '';
	/**
	 * Variable to store query string.
	 * 
	 * @var string
	 */
	public $_query = '';

	/**
	 * Method to rewrite a query string for SQLite to execute.
	 * 
	 * @param strin $query
	 * @param string $query_type
	 * @return string
	 */
	public function rewrite_query($query, $query_type){
		$this->query_type = $query_type;
 		$this->_query = $query;
		switch ($this->query_type) {
		  case 'truncate':
		    $this->_handle_truncate_query();
		    break;
		  case 'alter':
		    $this->_handle_alter_query();
		    break;
		  case 'create':
		    $this->_handle_create_query();
		    break;
		  case 'describe':
		  case 'desc':
		    $this->_handle_describe_query();
		    break;
		  case 'show':
		    $this->_handle_show_query();
		    break;
		  case 'showcolumns':
		    $this->_handle_show_columns_query();
		    break;
		  case 'showindex':
		    $this->_handle_show_index();
		    break;
		  case 'select':
		    $this->_strip_backticks();
		    $this->_handle_sql_count();
		    $this->_rewrite_date_sub();
		    $this->_delete_index_hints();
		    $this->_rewrite_regexp();
		    $this->_rewrite_boolean();
		    $this->_fix_date_quoting();
		    $this->_rewrite_between();
		    break;
		  case 'insert':
		    $this->_safe_strip_backticks();
		    $this->_execute_duplicate_key_update();
		    $this->_rewrite_insert_ignore();
		    $this->_rewrite_regexp();
		    $this->_fix_date_quoting();
		    break;
		  case 'update':
		    $this->_safe_strip_backticks();
		    $this->_rewrite_update_ignore();
// 		    $this->_rewrite_date_sub();
		    $this->_rewrite_limit_usage();
		    $this->_rewrite_order_by_usage();
		    $this->_rewrite_regexp();
		    $this->_rewrite_between();
		    break;
		  case 'delete':
		    $this->_strip_backticks();
		    $this->_rewrite_limit_usage();
		    $this->_rewrite_order_by_usage();
		    $this->_rewrite_date_sub();
		    $this->_rewrite_regexp();
		    $this->_delete_workaround();
		    break;
		  case 'replace':
		    $this->_safe_strip_backticks();
		    $this->_rewrite_date_sub();
		    $this->_rewrite_regexp();
		    break;
		  case 'optimize':
		    $this->_rewrite_optimize();
		    break;
		  case 'pragma':
		  	break;
		  default:
		  	if (defined(WP_DEBUG) && WP_DEBUG) {
		  		break;
		  	} else {
			  	$this->_return_true();
			  	break;
		  	}
		}
		return $this->_query;
	}
	
	/**
	 * method to handle SHOW TABLES query.
	 * 
	 * @access private
	 */
	private function _handle_show_query(){
	  $table_name = '';
		$pattern = '/^\\s*SHOW\\s*TABLES\\s*.*?(LIKE\\s*(.*))$/im';
		if (preg_match($pattern, $this->_query, $matches)) {
		  $table_name = str_replace(array("'", ';'), '', $matches[2]);
		}
		if (!empty($table_name)) {
			$suffix = ' AND name LIKE '. "'" . $table_name . "'";		
		} else {
			$suffix = '';
		}
		$this->_query = "SELECT name FROM sqlite_master WHERE type='table'" . $suffix . ' ORDER BY name DESC';
	}
	/**
	 * Method to strip all column qualifiers (backticks) from a query.
	 * 
	 * All the back quotes in the query string are removed automatically.
	 * So it must not be used when INSERT, UPDATE or REPLACE query is executed.
	 * 
	 * @access private
	 */
	private function _strip_backticks(){
		$this->_query = str_replace('`', '', $this->_query);
	}
	/**
	 * Method to strip all column qualifiers (backticks) from a query except post data.
	 * 
	 * All the back quotes execpt user data to be inserted are revomved automatically.
	 * This method must be used when INSERT, UPDATE or REPLACE query is executed.
	 * 
	 * @access private
	 */
	private function _safe_strip_backticks() {
		$query_string = '';
		$tokens = preg_split("/(''|')/s", $this->_query, -1, PREG_SPLIT_DELIM_CAPTURE);
		$literal = false;
		$query_string = '';
		foreach ($tokens as $token) {
			if ($token == "'") {
				if ($literal) {
					$literal = false;
				} else {
					$literal = true;
				}
			} else {
				if ($literal === false && strpos($token, '`') !== false) {
					$token = str_replace('`', '', $token);
				}
			}
			$query_string .= $token;
		}
		$this->_query = $query_string;
	}
	/**
	 * Method to emulate the SQL_CALC_FOUND_ROWS placeholder for MySQL.
	 *
	 * This is a kind of tricky play.
	 * 1. remove SQL_CALC_FOUND_ROWS option, and give it to the pdo engine
	 * 2. make another $wpdb instance, and execute SELECT COUNT(*) query
	 * 3. give the returned value to the original instance variable without LIMIT
	 * 
	 * When SQL statement contains GROUP BY option, SELECT COUNT query doesn't
	 * go well. So we remove the GROUP BY, which means the returned value may
	 * be a approximate one.
	 * 
	 * This kind of statement is required for WordPress to calculate the paging.
	 * see also WP_Query class in wp-includes/query.php
	 * 
	 * @access private
	 */
	private function _handle_sql_count(){
		if (stripos($this->_query, 'SELECT SQL_CALC_FOUND_ROWS') !== false){
			global $wpdb;
			// first strip the code. this is the end of rewriting process
			$this->_query = str_ireplace('SQL_CALC_FOUND_ROWS', '', $this->_query);
			// we make the data for next SELECE FOUND_ROWS() statement
			$unlimited_query = preg_replace('/\\bLIMIT\\s*.*/imsx', '', $this->_query);
// 			$unlimited_query = preg_replace('/\\bFALSE\\s*.*/imsx', '0', $unlimited_query);
      $unlimited_query = preg_replace('/\\bGROUP\\s*BY\\s*.*/imsx', '', $unlimited_query);
			$unlimited_query = $this->__transform_to_count($unlimited_query);
			$_wpdb = new PDODB();
			$result = $_wpdb->query($unlimited_query);
			$wpdb->dbh->found_rows_result = $_wpdb->last_result;
			$_wpdb = null;
		}
	}
	/**
	 * Call back method to change SELECT query to SELECT COUNT().
	 * 
	 * revised for version 1.5.1
	 *
	 * @param	string $query	the query to be transformed
	 * @return string the transformed query
	 * @access private
	 */
	private function __transform_to_count($query){
		$pattern = '/^\\s*SELECT\\s*(DISTINCT|)?.*?FROM\b/isx';
		$_query = preg_replace($pattern, 'SELECT \\1 COUNT(*) FROM ', $query);
		return $_query;
	}
	/**
	 * Method to rewrite INSERT IGNORE to INSERT OR IGNORE.
	 * 
	 * @access private
	 */
	private function _rewrite_insert_ignore(){
		$this->_query = str_ireplace('INSERT IGNORE', 'INSERT OR IGNORE ', $this->_query); 
	}
	/**
	 * Method to rewrite UPDATE IGNORE to UPDATE OR IGNORE.
	 * 
	 * @access private
	 */
	private function _rewrite_update_ignore(){
		$this->_query = str_ireplace('UPDATE IGNORE', 'UPDATE OR IGNORE ', $this->_query); 
	}
	/**
	 * Method to rewrite DATE_ADD() function.
	 * 
	 * DATE_ADD has a parameter PHP function can't parse, so we quote the list and
	 * pass it to the user defined function.
	 * 
	 * @access private
	 */
	private function _rewrite_date_add(){
		//(date,interval expression unit)
		$pattern = '/\\s*date_add\\s*\(([^,]*),([^\)]*)\)/imsx';
    if (preg_match($pattern, $this->_query, $matches)) {
      $expression = "'".trim($matches[2])."'";
      $this->_query = preg_replace($pattern, " date_add($matches[1], $expression) ", $this->_query);
    }
	}
	/**
	 * Method to rewrite DATE_SUB() function.
	 * 
	 * DATE_SUB has a parameter PHP function can't parse, so we quote the list and
	 * pass it to the user defined function.
	 * 
	 * @access private
	 */
	private function _rewrite_date_sub(){
		//(date,interval expression unit)
		$pattern = '/\\s*date_sub\\s*\(([^,]*),([^\)]*)\)/imsx';
    if (preg_match($pattern, $this->_query, $matches)) {
      $expression = "'".trim($matches[2])."'";
      $this->_query = preg_replace($pattern, " date_sub($matches[1], $expression) ", $this->_query);
    }
	}
	/**
	 * Method to handle CREATE query.
	 * 
	 * If the query is CREATE query, it will be passed to the query_create.class.php.
	 * So this method can't be used. It's here for safety.
	 * 
	 * @access private
	 */
	private function _handle_create_query(){
		require_once PDODIR . 'query_create.class.php';
		$engine = new CreateQuery();
		$this->_query = $engine->rewrite_query($this->_query);
		$engine = null;
	}
	
	/**
	 * Method to handle ALTER query.
	 * 
	 * If the query is ALTER query, it will be passed ot the query_alter.class.php.
	 * So this method can't be used. It is here for safety.
	 * 
	 * @access private
	 */
	private function _handle_alter_query(){
	  require_once PDODIR . 'query_alter.class.php';
	  $engine = new AlterQuery();
	  $this->_query = $engine->rewrite_query($this->_query, 'alter');
	  $engine = null;
	}
	
	/**
	 * Method to handle DESCRIBE or DESC query.
	 * 
	 * DESCRIBE is required for WordPress installation process. DESC is
	 * an alias for DESCRIBE, but it is not used in core WordPress.
	 * 
	 * @access private
	 */
	private function _handle_describe_query(){
		// $this->_query = "select 1=1";
		$pattern = '/^\\s*(DESCRIBE|DESC)\\s*(.*)/i';
		if (preg_match($pattern, $this->_query, $match)) {
  		$tablename = preg_replace('/[\';]/', '', $match[2]);
      $this->_query = "PRAGMA table_info($tablename)";
		}
	}
	/**
	 * Method to remove LIMIT clause from DELETE or UPDATE query.
	 * 
	 * The author of the original 'PDO for WordPress' says update method of wpdb
	 * insists on adding LIMIT. But the newest version of WordPress doesn't do that.
	 * Nevertheless some plugins use DELETE with LIMIT, UPDATE with LIMIT.
	 * We need to exclude sub query's LIMIT. And if SQLite is compiled with
	 * ENABLE_UPDATE_DELETE_LIMIT option, we don't remove it.
	 * 
	 * @access private
	 */
	private function _rewrite_limit_usage(){
		$_wpdb = new PDODB();
		$options = $_wpdb->get_results('PRAGMA compile_options');
		foreach ($options as $opt) {
			if (stripos($opt->compile_option, 'ENABLE_UPDATE_DELETE_LIMIT') !== false) return;
		}
	  if (stripos($this->_query, '(select') === false) {
	    $this->_query = preg_replace('/\\s*LIMIT\\s*[0-9]$/i', '', $this->_query);
	  }
	}
	/**
	 * Method to remove ORDER BY clause from DELETE or UPDATE query.
	 * 
	 * SQLite compiled without SQLITE_ENABLE_UPDATE_DELETE_LIMIT option can't
	 * execute UPDATE with ORDER BY, DELETE with GROUP BY.
	 * We need to exclude sub query's GROUP BY.
	 * 
	 * @access private
	 */
	private function _rewrite_order_by_usage() {
		$_wpdb = new PDODB();
		$options = $_wpdb->get_results('PRAGMA compile_options');
		foreach ($options as $opt) {
			if (stripos($opt->compile_option, 'ENABLE_UPDATE_DELETE_LIMIT') !== false) return;
		}
	  if (stripos($this->_query, '(select') === false) {
	    $this->_query = preg_replace('/\\s*ORDER\\s*BY\\s*.*$/i', '', $this->_query);
	  }
	}
	/**
	 * Method to handle TRUNCATE query.
	 * 
	 * @access private
	 */
	private function _handle_truncate_query(){
		$pattern = '/TRUNCATE TABLE (.*)/im';
		$this->_query = preg_replace($pattern, 'DELETE FROM $1', $this->_query);
	}
	/**
	 * Method to handle OPTIMIZE query.
	 * 
	 * Original query has the table names, but they are simply ignored.
	 * Table names are meaningless in SQLite.
	 * 
	 * @access private
	 */
	private function _rewrite_optimize(){
		$this->_query ="VACUUM";
	}
	/**
	 * Method to rewrite day.
	 * 
	 * Jusitn Adie says: some wp UI interfaces (notably the post interface)
	 * badly composes the day part of the date leading to problems in sqlite
	 * sort ordering etc.
	 * 
	 * I don't understand that...
	 * 
	 * @return void
	 * @access private
	 */
	private function _rewrite_badly_formed_dates(){
		$pattern = '/([12]\d{3,}-\d{2}-)(\d )/ims';
		$this->_query = preg_replace($pattern, '${1}0$2', $this->_query);
	}
	/**
	 * Method to remove INDEX HINT.
	 * 
	 * @return void
	 * @access private
	 */
	private function _delete_index_hints(){
		$pattern = '/\\s*(use|ignore|force)\\s+index\\s*\(.*?\)/i';
		$this->_query = preg_replace($pattern, '', $this->_query);
	}
	/**
	 * Method to fix the date string and quoting.
	 * 
	 * This is required for the calendar widget.
	 * 
	 * WHERE month(fieldname)=08 is converted to month(fieldname)='8'
	 * WHERE month(fieldname)='08' is converted to month(fieldname)='8'
	 * 
	 * I use preg_replace_callback instead of 'e' option because of security reason.
	 * cf. PHP manual (regular expression)
	 * 
	 * @return void
	 * @access private
	 */
	private function _fix_date_quoting() {
		$pattern = '/(month|year|second|day|minute|hour|dayofmonth)\\s*\((.*?)\)\\s*=\\s*["\']?(\d{1,4})[\'"]?\\s*/im';
		$this->_query = preg_replace_callback($pattern, array($this, '__fix_date_quoting'), $this->_query);
	}
	/**
	 * Call back method to rewrite date string.
	 * 
	 * @param string $match
	 * @return string
	 * @access private
	 */
	private function __fix_date_quoting($match) {
		$fixed_val = "{$match[1]}({$match[2]})='" . intval($match[3]) . "' ";
		return $fixed_val;
	}
	/**
	 * Method to rewrite REGEXP() function.
	 * 
	 * This method changes function name to regexpp() and pass it to the user defined
	 * function.
	 * 
	 * @access private
	 */
	private function _rewrite_regexp(){
		$pattern = '/\s([^\s]*)\s*regexp\s*(\'.*?\')/im';
		$this->_query = preg_replace($pattern, ' regexpp(\1, \2)', $this->_query);
	}
	/**
	 * Method to rewrite boolean to number.
	 * 
	 * SQLite doesn't support true/false type, so we need to convert them to 1/0.
	 * 
	 * @access private
	 */
	private function _rewrite_boolean() {
	  $query = str_ireplace('TRUE', "1", $this->_query);
	  $query = str_ireplace('FALSE', "0", $query);
	  $this->_query = $query;
	}
	/**
	 * Method to handl SHOW COLUMN query.
	 * 
	 * @access private
	 */
  private function _handle_show_columns_query() {
    $pattern_like = '/^\\s*SHOW\\s*(COLUMNS|FIELDS)\\s*FROM\\s*(.*)?\\s*LIKE\\s*(.*)?/i';
    $pattern = '/^\\s*SHOW\\s*(COLUMNS|FIELDS)\\s*FROM\\s*(.*)?/i';
    if (preg_match($pattern_like, $this->_query, $matches)) {
      $table_name = str_replace("'", "", trim($matches[2]));
      $column_name = str_replace("'", "", trim($matches[3]));
      $query_string = "SELECT sql FROM sqlite_master WHERE tbl_name='$table_name' AND sql LIKE '%$column_name%'";
      $this->_query = $query_string;
    } elseif (preg_match($pattern, $this->_query, $matches)) {
      $table_name = $matches[2];
      $query_string = preg_replace($pattern, "PRAGMA table_info($table_name)", $this->_query);
      $this->_query = $query_string;
    }
  }
  /**
   * Method to handle SHOW INDEX query.
   * 
   * Moved the WHERE clause manipulation to pdoengin.class.php (ver 1.3.1)
   * 
   * @access private
   */
  private function _handle_show_index() {
    $pattern = '/^\\s*SHOW\\s*(?:INDEX|INDEXES|KEYS)\\s*FROM\\s*(\\w+)?/im';
    if (preg_match($pattern, $this->_query, $match)) {
      $table_name = preg_replace("/[\';]/", '', $match[1]);
      $table_name = trim($table_name);
      $this->_query = "SELECT * FROM sqlite_master WHERE tbl_name='$table_name'";
    }
  }
  /**
   * Method to handle ON DUPLICATE KEY UPDATE statement.
   * 
   * First we use SELECT query and check if INSERT is allowed or not.
   * Rewriting procedure looks like a detour, but I've got no other way.
   * 
   * @return void
   * @access private
   */
  private function _execute_duplicate_key_update() {
    $update = false;
    $unique_keys_for_cond  = array();
    $unique_keys_for_check = array();
    $pattern =  '/^\\s*INSERT\\s*INTO\\s*(\\w+)?\\s*(.*)\\s*ON\\s*DUPLICATE\\s*KEY\\s*UPDATE\\s*(.*)$/ims';
    if (preg_match($pattern, $this->_query, $match_0)) {
      $table_name  = trim($match_0[1]);
      $insert_data = trim($match_0[2]);
      $update_data = trim($match_0[3]);
      // prepare two unique key data for the table
      // 1. array('col1', 'col2, col3', etc) 2. array('col1', 'col2', 'col3', etc)
      $_wpdb = new PDODB();
      $indexes = $_wpdb->get_results("SHOW INDEX FROM {$table_name}");
      if (!empty($indexes)) {
        foreach ($indexes as $index) {
          if ($index->Non_unique == 0) {
            $unique_keys_for_cond[] = $index->Column_name;
            if (strpos($index->Column_name, ',') !== false) {
              $unique_keys_for_check = array_merge($unique_keys_for_check, explode(',', $index->Column_name));
            } else {
              $unique_keys_for_check[] = $index->Column_name;
            }
          }
        }
        $unique_keys_for_check = array_map('trim', $unique_keys_for_check);
      } else {
        // Without unique key or primary key, UPDATE statement will affect all the rows!
        $query = 'INSERT INTO '.$table_name.' '.$insert_data;
        $this->_query = $query;
        $_wpdb = null;
        return;
      }
      // data check
      if (preg_match('/^\((.*)\)\\s*VALUES\\s*\((.*)\)$/ims', $insert_data, $match_1)) {
        $col_array = explode(',', $match_1[1]);
        $ins_data_array = explode(',', $match_1[2]);
        foreach ($col_array as $col) {
          $val = trim(array_shift($ins_data_array));
          $ins_data_assoc[trim($col)] = $val;
        }
//         $ins_data_assoc = array_combine($col_array, $ins_array);
        $condition = '';
        foreach ($unique_keys_for_cond as $unique_key) {
          if (strpos($unique_key, ',') !== false) {
            $unique_key_array = explode(',', $unique_key);
            $counter = count($unique_key_array);
            for ($i = 0; $i < $counter; ++$i) {
              $col = trim($unique_key_array[$i]);
              if (isset($ins_data_assoc[$col]) && $i == $counter - 1) {
                $condition .= $col . '=' . $ins_data_assoc[$col] . ' OR ';
              } elseif (isset($ins_data_assoc[$col])) {
                $condition .= $col . '=' . $ins_data_assoc[$col] . ' AND ';
              } else {
                continue;
              }
            }
//             $condition = rtrim($condition, ' AND ');
          } else {
            $col = trim($unique_key);
            if (isset($ins_data_assoc[$col])) {
              $condition .= $col . '=' . $ins_data_assoc[$col] . ' OR ';
            } else {
              continue;
            }
          }
        }
        $condition = rtrim($condition, ' OR ');
        $test_query = "SELECT * FROM {$table_name} WHERE {$condition}";
        $results = $_wpdb->query($test_query);
        $_wpdb = null;
        if ($results == 0) {
          $this->_query = 'INSERT INTO '.$table_name.' '.$insert_data;
          return;
        } else {
          // change (col, col...) values (data, data...) to array(col=>data, col=>data...)
          if (preg_match('/^\((.*)\)\\s*VALUES\\s*\((.*)\)$/im', $insert_data, $match_2)) {
            $col_array = explode(',', $match_2[1]);
            $ins_array = explode(',', $match_2[2]);
            $count = count($col_array);
            for ($i = 0; $i < $count; $i++) {
              $col = trim($col_array[$i]);
              $val = trim($ins_array[$i]);
              $ins_array_assoc[$col] = $val;
            }
          }
          // change col = data, col = data to array(col=>data, col=>data)
          // some plugins have semi-colon at the end of the query
          $update_data = rtrim($update_data, ';');
          $tmp_array = explode(',', $update_data);
          foreach ($tmp_array as $pair) {
            list($col, $value) = explode('=', $pair);
            $col = trim($col);
            $value = trim($value);
            $update_array_assoc[$col] = $value;
          }
          // change array(col=>values(col)) to array(col=>data)
          foreach ($update_array_assoc as $key => &$value) {
            if (preg_match('/^VALUES\\s*\((.*)\)$/im', $value, $match_3)) {
              $col = trim($match_3[1]);
              $value = $ins_array_assoc[$col];
            }
          }
          foreach ($ins_array_assoc as $key => $val) {
            if (in_array($key, $unique_keys_for_check)) {
              $where_array[] = $key . '=' . $val;
            }
          }
          $update_strings = '';
          foreach ($update_array_assoc as $key => $val) {
            if (in_array($key, $unique_keys_for_check)) {
              $where_array[] = $key . '=' . $val;
            } else {
              $update_strings .= $key . '=' . $val . ',';
            }
          }
          $update_strings = rtrim($update_strings, ',');
          $unique_where = array_unique($where_array, SORT_REGULAR);
          $where_string = ' WHERE ' . implode(' AND ', $unique_where);
          //        $where_string = ' WHERE ' . rtrim($where_string, ',');
          $update_query = 'UPDATE ' . $table_name . ' SET ' . $update_strings . $where_string;
          $this->_query = $update_query;
        }
      }
    }
//      else {
//       $pattern = '/ ON DUPLICATE KEY UPDATE.*$/im';
//       $replace_query = preg_replace($pattern, '', $this->_query);
//       $replace_query = str_ireplace('INSERT ', 'INSERT OR REPLACE ', $replace_query);
//       $this->_query = $replace_query;
//     }
  }
  /**
   * Method to rewrite BETWEEN A AND B clause.
   * 
   * This clause is the same form as natural language, so we have to check if it is
   * in the data or SQL statement.
   * 
   * @access private
   */
  private function _rewrite_between() {
  	$pattern = '/\\s*(\\w+)?\\s*BETWEEN\\s*([^\\s]*)?\\s*AND\\s*([^\\s]*)?\\s*/ims';
  	if (preg_match($pattern, $this->_query, $match)) {
  		$column_name  = trim($match[1]);
  		$min_value    = trim($match[2]);
  		$max_value    = trim($match[3]);
  		$max_value    = rtrim($max_value);
  		$tokens = preg_split("/(''|')/s", $this->_query, -1, PREG_SPLIT_DELIM_CAPTURE);
  		$literal = false;
  		$rewriting = false;
  		foreach ($tokens as $token) {
  			if ($token == "'") {
  				if ($literal) {
  					$literal = false;
  				} else {
  					$literal = true;
  				}
  			} else {
  				if ($literal === false && stripos($token, 'between') !== false) {
  					$rewriting = true;
  					break;
  				}
  			}
  		}
  		if ($rewriting) {
	  		$replacement  = " $column_name >= '$min_value' AND $column_name <= '$max_value'";
	  		$this->_query = str_ireplace($match[0], $replacement, $this->_query);
  		}
  	}
  }
  /**
   * Method to avoid DELETE with JOIN statement.
   * 
   * wp-admin/includes/upgrade.php contains 'DELETE ... JOIN' statement.
   * This query can't be replaced with regular expression or udf, so we
   * replace all the statement with another. But this query was used in
   * the very old version of WordPress when it was upgraded. So we won't
   * have no chance that this method should be used.
   * 
   * @access private
   */
  private function _delete_workaround() {
    global $wpdb;
//     $pattern = "DELETE o1 FROM $wpdb->options AS o1 JOIN $wpdb->options AS o2 USING (option_name) WHERE o2.option_id > o1.option_id";
		$pattern = "DELETE o1 FROM $wpdb->options AS o1 JOIN $wpdb->options AS o2";
    $rewritten = "DELETE FROM $wpdb->options WHERE option_id IN (SELECT MIN(option_id) FROM $wpdb->options GROUP BY option_name HAVING COUNT(*) > 1)";
    if (stripos($this->_query, $pattern) !== false) {
      $this->_query = $rewritten;
    }
  }
  /**
   * Method to suppress errors.
   * 
   * When the query string is the one that this class can't manipulate,
   * the query string is replaced with the one that always returns true
   * and does nothing.
   * 
   * @access private
   */
  private function _return_true() {
  	$this->_query = 'SELECT 1=1';
  }
}
?>