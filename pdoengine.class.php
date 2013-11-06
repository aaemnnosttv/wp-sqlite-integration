<?php
/**
 * @package SQLite Integration
 * @author Kojima Toshiyasu, Justin Adie
 *
 */

/**
 * This class does the real work
 * accepts a request from wpdb class, initialize PDO instance,
 * execute SQL statement, and returns the results to wpdb class.
 */
class PDOEngine extends PDO {
  public  $is_error = false;
  public  $found_rows_result;
  private $rewritten_query;
  private $query_type;
  private $results = null;
  private $_results = null;
  private $pdo;
  private $prepared_query;
  private $extracted_variables = array();
  private $error_messages = array();
  private $errors;
  public  $queries = array();
  private $last_insert_id;
  private $affected_rows;
  private $column_names;
  private $num_rows;
  private $return_value;
  private $can_insert_multiple_rows = false;
  private $param_num;
  protected $has_active_transaction = false;

  /**
   * Constructor
   * @param
   */
  function __construct() {
    $this->init();
  }
  function __destruct() {
    $this->pdo = null;
    return true;
  }
  
  /**
   * Function to initialize database
   * checks if there's a database directory and database file, creates the tables,
   * and binds the user defined function to the pdo object
   * @return boolean
   */
  private function init() {
    $dsn = 'sqlite:' . FQDB;
    $result = $this->prepare_directory();
    if (!$result) return false;
    if (is_file(FQDB)) {
      $locked = false;
    	do {
      	try {
      		if ($locked) $locked = false;
          $this->pdo = new PDO(
              $dsn,  // data source name
              null,  // user name
              null,  // user password
              array( // PDO options
                  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                  ));
          $statement = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'");
          $number_of_tables = $statement->fetchColumn(0);
          $statement = null;
          if ($number_of_tables == 0) {
            $this->make_sqlite_tables();
          }
        } catch (PDOException $err) {
          $status = $err->getCode();
          // code 5 => The database file is locked
          // code 6 => A table in the database is locked
          if ($status == 5 || $status == 6) {
            $locked = true;
          } else {
            $message = 'Database connection error!<br />';
            $message .= sprintf("Error message is: %s", $err->getMessage());
            $this->set_error(__LINE__, __FUNCTION__, $message);
            return false;
          }
        }
      } while ($locked);
      require_once UDF_FILE;
      new PDOSQLiteUDFS($this->pdo);
      if (version_compare($this->get_sqlite_version(), '3.7.11', '>=')) {
        $this->can_insert_multiple_rows = true;
      }
    } else { // database file is not found, so we make it and create tables...
      try {
        $this->pdo = new PDO($dsn, null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
      } catch (PDOException $err) {
        $message = 'Database initialization error!<br />';
        $message .= sprintf("Error message is: %s", $err->getMessage());
        $this->set_error(__LINE__, __FUNCTION__, $message);
        return false;
      }
      $this->make_sqlite_tables();
    }
  }

  /**
   * Make database direcotry and .htaccess file
   * executed once while installation process
   */
  private function prepare_directory() {
    global $wpdb;
    $u = umask(0000);
    if (!is_dir(FQDBDIR)) {
      if (!@mkdir(FQDBDIR, 0707, true)) {
        umask($u);
        $message = 'Unable to create the required directory! Please check your server settings.';
        echo $message;
        return false;
      }
    }
    if (!is_writable(FQDBDIR)) {
      umask($u);
      $message = 'Unable to create a file in the directory! Please check your server settings.';
      echo $message;
      return false;
    }
    if (!is_file(FQDBDIR . '.htaccess')) {
      $fh = fopen(FQDBDIR . '.htaccess', "w");
      if (!$fh) {
        umask($u);
        $message = 'Unable to create a file in the directory! Please check your server settings.';
        echo $message;
        return false;
      }
      fwrite($fh, 'DENY FROM ALL');
      fclose($fh);
    }
    umask($u);
    return true;
  }
  /**
   * Make database file itself and WordPress tables
   * executed once while installation process
   */
  private function make_sqlite_tables() {
    require_once PDODIR . 'install.php';
  }
  
  public function query($query) {
    $this->flush();
    
    $this->queries[] = "Raw query:\t$query";
    $res = $this->determine_query_type($query);
    if (!$res) {
      $bailoutString = sprintf(__("<h1>Unknown query type</h1><p>Sorry, we cannot determine the type of query that is requested.</p><p>The query is %s</p>", 'sqlite-integration'), $query);
      $this->set_error(__LINE__, __FUNCTION__, $bailoutString);
    }
    switch (strtolower($this->query_type)) {
      case 'foundrows':
        $this->results = $this->found_rows_result;
        $this->num_rows = count($this->results);
        $this->found_rows_result = null;
        break;
      case 'insert':
        if ($this->can_insert_multiple_rows) {
          $this->execute_insert_query_new($query);
        } else {
          $this->execute_insert_query($query);
        }
        break;
      case 'create':
        $result = $this->execute_create_query($query);
        $this->return_value = $result;
        break;
      case 'alter':
        $result = $this->execute_alter_query($query);
        $this->return_value = $result;
        break;
      case 'show_variables':
        $result = $this->show_variables_workaround($query);
        break;
      case 'drop_index':
      	$pattern = '/^\\s*(DROP\\s*INDEX\\s*.*?)\\s*ON\\s*(.*)/im';
      	if (preg_match($pattern, $query, $match)) {
      		$drop_query = 'ALTER TABLE ' . trim($match[2]) . ' ' . trim($match[1]);
      		$this->query_type = 'alter';
      		$result = $this->execute_alter_query($drop_query);
      		$this->return_value = $result;
      	} else {
      		$this->return_value = false;
      	}
      	break;
      default:
        $engine = $this->prepare_engine($this->query_type);
        $this->rewritten_query = $engine->rewrite_query($query, $this->query_type);
        $this->queries[] = "Rewritten: $this->rewritten_query";
        $this->extract_variables();
        $statement = $this->prepare_query();
        $this->execute_query($statement);
        if (!$this->is_error) {
          $this->process_results($engine);
        } else {// Error
          ;
        }
        break;
    }
    if (defined('PDO_DEBUG') && PDO_DEBUG === true) {
      file_put_contents(FQDBDIR . 'debug.txt', $this->get_debug_info(), FLIE_APPEND);
    }
    return $this->return_value;
  }

  public function get_insert_id() {
    return $this->last_insert_id;
  }
  public function get_affected_rows() {
    return $this->affected_rows;
  }
  public function get_columns() {
    return $this->column_names;
  }
  public function get_query_results() {
    return $this->results;
  }
  public function get_num_rows() {
    return $this->num_rows;
  }
  public function get_return_value() {
    return $this->return_value;
  }

	public function get_error_message(){
		if (count($this->error_messages) === 0){
			$this->is_error = false;
			$this->error_messages = array();
			return '';
		}
		$output = '<div style="clear:both">&nbsp;</div>';
		if ($this->is_error === false){
// 			return $output;
      return '';
		}
		$output .= "<div class=\"queries\" style=\"clear:both; margin_bottom:2px; border: red dotted thin;\">Queries made or created this session were<br/>\r\n\t<ol>\r\n";
		foreach ($this->queries as $q){
			$output .= "\t\t<li>".$q."</li>\r\n";
		}
		$output .= "\t</ol>\r\n</div>";
		foreach ($this->error_messages as $num=>$m){
			$output .= "<div style=\"clear:both; margin_bottom:2px; border: red dotted thin;\" class=\"error_message\" style=\"border-bottom:dotted blue thin;\">Error occurred at line {$this->errors[$num]['line']} in Function {$this->errors[$num]['function']}. <br/> Error message was: $m </div>";
		}
		
		ob_start();
		debug_print_backtrace();
		$output .= '<pre>' . ob_get_contents() . '</pre>';
		ob_end_clean();
		return $output;
	
	}
	
	private function get_debug_info(){
	  $output = '';
	  foreach ($this->queries as $q){
	    $output .= $q ."\r\n";
	  }
	  return $output;
	}
	
	private function flush(){
	  $this->rewritten_query     = '';
	  $this->query_type          = '';
	  $this->results             = null;
	  $this->_results            = null;
	  $this->last_insert_id      = null;
	  $this->affected_rows       = null;
	  $this->column_names        = array();
	  $this->num_rows            = null;
	  $this->return_value        = null;
	  $this->extracted_variables = array();
	  $this->error_messages      = array();
	  $this->is_error            = false;
	  $this->queries             = array();
	  $this->param_num           = 0;
	}
	
	private function prepare_engine($query_type = null) {
	  if (stripos($query_type, 'create') !== false) {
	    require_once PDODIR . 'query_create.class.php';
	    $engine = new CreateQuery();
	  } elseif (stripos($query_type, 'alter') !== false) {
	    require_once PDODIR . 'query_alter.class.php';
	    $engine = new AlterQuery();
	  } else {
	    require_once PDODIR . 'query.class.php';
	    $engine = new PDOSQLiteDriver();
	  }
	  return $engine;
	}
	
  private function prepare_query(){
		$this->queries[] = 'Prepare: ' . $this->prepared_query;
		$reason = 0;
		$message = '';
		$statement = null;
		do {
		  try {
  			$statement = $this->pdo->prepare($this->prepared_query);
		  } catch (PDOException $err) {
		    $reason = $err->getCode();
		    $message = $err->getMessage();
		  }
		} while (5 == $reason || 6 == $reason);
		
		if ($reason > 0){
			$err_message = sprintf("Problem preparing the PDO SQL Statement.  Error was: %s", $message);
	    $this->set_error(__LINE__, __FUNCTION__, $err_message);
		}
		return $statement;
	}

	private function execute_query($statement) {
		$reason = 0;
		$message = '';
		if (!is_object($statement))
		  return;
		if (count($this->extracted_variables) > 0) {
			$this->queries[] = 'Executing: ' . var_export($this->extracted_variables, true);
			do {
				if ($this->query_type == 'update' || $this->query_type == 'replace') {
					try {
						$this->beginTransaction();
						$statement->execute($this->extracted_variables);
						$this->commit();
					} catch (PDOException $err) {
						$reason = $err->getCode();
						$message = $err->getMessage();
						$this->rollBack();
					}
				} else {
				  try {
	  				$statement->execute($this->extracted_variables);
				  } catch (PDOException $err) {
				    $reason = $err->getCode();
				    $message = $err->getMessage();
				  }
				}
			} while (5 == $reason || 6 == $reason);
		} else {
			$this->queries[] = 'Executing: (no parameters)';
			do{
			  if ($this->query_type == 'update' || $this->query_type == 'replace') {
			  	try {
			  		$this->beginTransaction();
			  		$statement->execute();
			  		$this->commit();
			  	} catch (PDOException $err) {
			  		$reason = $err->getCode();
			  		$message = $err->getMessage();
			  		$this->rollBack();
			  	}
			  } else {
					try {
		  				$statement->execute();
				  } catch (PDOException $err) {
				    $reason = $err->getCode();
				    $message = $err->getMessage();
				  }
			  }
			} while (5 == $reason || 6 == $reason);
		}
		if ($reason > 0) {
			$err_message = sprintf("Error while executing query! Error message was: %s", $message);
			$this->set_error(__LINE__, __FUNCTION__, $err_message);
			return false;
		} else {
			$this->_results = $statement->fetchAll(PDO::FETCH_OBJ);
		}
		//generate the results that $wpdb will want to see
		switch ($this->query_type) {
			case 'insert':
			case 'update':
			case 'replace':
				$this->last_insert_id = $this->pdo->lastInsertId();
				$this->affected_rows = $statement->rowCount();
				$this->return_value = $this->affected_rows;
			  break;
			case 'select':
			case 'show':
			case 'showcolumns':
			case 'showindex':
			case 'describe':
      case 'desc':
      case 'check':
      case 'analyze':
//  			case "foundrows":
				$this->num_rows = count($this->_results);
				$this->return_value = $this->num_rows;
			  break;
			case 'delete':
				$this->affected_rows = $statement->rowCount();
				$this->return_value = $this->affected_rows;
			  break;
			case 'alter':
			case 'drop':
			case 'create':
			case 'optimize':
			case 'truncate':
				if ($this->is_error) {
					$this->return_value = false;
				} else {
					$this->return_value = true;
				}
			  break;
		}
	}
	
	private function extract_variables() {
		if ($this->query_type == 'create') {
			$this->prepared_query = $this->rewritten_query;
			return;
		}
		
		//long queries can really kill this
		$pattern = '/(?<!\\\\)([\'"])(.*?)(?<!\\\\)\\1/imsx';
		$_limit = $limit = ini_get('pcre.backtrack_limit');
		do {
			if ($limit > 10000000) {
				$message = 'The query is too big to parse properly';
				$this->set_error(__LINE__, __FUNCTION__, $message);
				break; //no point in continuing execution, would get into a loop
			} else {
				ini_set('pcre.backtrack_limit', $limit);
				$query = preg_replace_callback($pattern, array($this,'replace_variables_with_placeholders'), $this->rewritten_query);	
			}
			$limit = $limit * 10;
		} while (empty($query));
		
		//reset the pcre.backtrack_limist
		ini_set('pcre.backtrack_limit', $_limit);
		$this->queries[]= 'With Placeholders: ' . $query;
		$this->prepared_query = $query;
	}
	
	private function replace_variables_with_placeholders($matches) {
		//remove the wordpress escaping mechanism
		$param = stripslashes($matches[0]);
		
		//remove trailing spaces
		$param = trim($param); 
		
		//remove the quotes at the end and the beginning
		if (in_array($param{strlen($param)-1}, array("'",'"'))) {
			$param = substr($param,0,-1) ;//end
		}
		if (in_array($param{0}, array("'",'"'))) {
			$param = substr($param, 1); //start
		}
		//$this->extracted_variables[] = $param;
		$key = ':param_'.$this->param_num++;
		$this->extracted_variables[] = $param;
		//return the placeholder
		//return ' ? ';
		return ' '.$key.' ';
	}

	/**
   * takes the query string ,determines the type and returns the type string
   * if the query is the type PDO for Wordpress can't executes, returns false
   * @param string $query
   * @return boolean|string
   */
  private function determine_query_type($query) {
    $result = preg_match('/^\\s*(EXPLAIN|PRAGMA|SELECT\\s*FOUND_ROWS|SELECT|INSERT|UPDATE|REPLACE|DELETE|ALTER|CREATE|DROP\\s*INDEX|DROP|SHOW\\s*\\w+\\s*\\w+\\s*|DESCRIBE|DESC|TRUNCATE|OPTIMIZE|CHECK|ANALYZE)/i', $query, $match);
    
    if (!$result) {
      return false;
    }
    $this->query_type = strtolower($match[1]);
    if (stripos($this->query_type, 'found') !== false) {
      $this->query_type = 'foundrows';
    }
    if (stripos($this->query_type, 'show') !== false) {
      if (stripos($this->query_type, 'show tables') !== false) {
        $this->query_type = 'show';
      } elseif (stripos($this->query_type, 'show columns') !== false || stripos($this->query_type, 'show fields') !== false) {
        $this->query_type = 'showcolumns';
      } elseif (stripos($this->query_type, 'show index') !== false || stripos($this->query_type, 'show indexes') !== false || stripos($this->query_type, 'show keys') !== false) {
        $this->query_type = 'showindex';
      } elseif (stripos($this->query_type, 'show variables') !== false || stripos($this->query_type, 'show global variables') !== false || stripos($this->query_type, 'show session variables') !== false) {
        $this->query_type = 'show_variables';
      } else {
        return false;
      }
    }
    if (stripos($this->query_type, 'drop index') !== false) {
    	$this->query_type = 'drop_index';
    }
    return true;
  }

  /**
   * SQLite version 3.7.11 began support multiple rows insert with values
   * clause. This is for that version or later.
   * @param string $query
   */
  private function execute_insert_query_new($query) {
  	$engine = $this->prepare_engine($this->query_type);
    $this->rewritten_query = $engine->rewrite_query($query, $this->query_type);
    $this->queries[] = 'Rewritten: ' . $this->rewritten_query;
    $this->extract_variables();
    $statement = $this->prepare_query();
    $this->execute_query($statement);
  }
  /**
   * executes the INSERT query for SQLite version 3.7.10 or lesser
   * @param string $query
   */
  private function execute_insert_query($query) {
    global $wpdb;
    $multi_insert = false;
    $statement = null;
    $engine = $this->prepare_engine($this->query_type);
    if (preg_match('/(INSERT.*?VALUES\\s*)(\(.*\))/imsx', $query, $matched)) {
      $query_prefix = $matched[1];
      $values_data = $matched[2];
      if (stripos($values_data, 'ON DUPLICATE KEY') !== false) {
        $exploded_parts = $values_data;
      } elseif (stripos($query_prefix, "INSERT INTO $wpdb->comments") !== false) {
        $exploded_parts = $values_data;
      } else {
        $exploded_parts = $this->parse_multiple_inserts($values_data);
      }
      $count = count($exploded_parts);
      if ($count > 1) {
        $multi_insert = true;
      }
    }
    if ($multi_insert) {
      $first = true;
      foreach ($exploded_parts as $value) {
        if (substr($value, -1, 1) === ')') {
          $suffix = '';
        } else {
          $suffix = ')';
        }
        $query_string = $query_prefix . ' ' . $value . $suffix;
        $this->rewritten_query = $engine->rewrite_query($query_string, $this->query_type);
        $this->queries[] = 'Rewritten: ' . $this->rewritten_query;
        $this->extracted_variables = array();
        $this->extract_variables();
        if ($first) {
          $statement = $this->prepare_query();
          $this->execute_query($statement);
          $first = false;
        } else {
          $this->execute_query($statement);
        }
      }
    } else {
      $this->rewritten_query = $engine->rewrite_query($query, $this->query_type);
      $this->queries[] = 'Rewritten: ' . $this->rewritten_query;
      $this->extract_variables();
      $statement = $this->prepare_query();
      $this->execute_query($statement);
    }
  }

  /**
   * helper function for execute_insert_query()
   * @param string $values
   * @return array
   */
  private function parse_multiple_inserts($values) {
    $tokens = preg_split("/(''|(?<!\\\\)'|(?<!\()\),(?=\s*\())/s", $values, -1, PREG_SPLIT_DELIM_CAPTURE);
    $exploded_parts = array();
    $part = '';
    $literal = false;
    foreach ($tokens as $token) {
      switch ($token) {
        case "),":
          if (!$literal) {
            $exploded_parts[] = $part;
            $part = '';
          } else {
            $part .= $token;
          }
          break;
        case "'":
          if ($literal) {
            $literal = false;
          } else {
            $literal = true;
          }
          $part .= $token;
          break;
        default:
          $part .= $token;
          break;
      }
    }
    if (!empty($part)) {
      $exploded_parts[] = $part;
    }
    return $exploded_parts;
  }
  
  /**
   * function to execute CREATE query
   * @param string
   * @return boolean
   */
  private function execute_create_query($query) {
    $engine = $this->prepare_engine($this->query_type);
    $rewritten_query = $engine->rewrite_query($query);
    $reason = 0;
    $message = '';
//     $queries = explode(";", $this->rewritten_query);
    try {
      $this->beginTransaction();
      foreach ($rewritten_query as $single_query) {
        $this->queries[] = "Executing:\t" . $single_query;
        $single_query = trim($single_query);
        if (empty($single_query)) continue;
          $this->pdo->exec($single_query);
      }
      $this->commit();
    } catch (PDOException $err) {
      $reason = $err->getCode();
      $message = $err->getMessage();
      if (5 == $reason || 6 == $reason) {
        $this->commit();
      } else {
        $this->rollBack();
      }
    }
    if ($reason > 0) {
      $err_message = sprintf("Problem in creating table or index. Error was: %s", $message);
      $this->set_error(__LINE__, __FUNCTION__, $err_message);
      return false;
    }
    return true;
  }

  /**
   * function to execute ALTER TABLE query
   * @param string
   * @return boolean
   */
  private function execute_alter_query($query) {
    $engine = $this->prepare_engine($this->query_type);
    $reason = 0;
    $message = '';
    $re_query = '';
    $rewritten_query = $engine->rewrite_query($query, $this->query_type);
    if (is_array($rewritten_query) && array_key_exists('recursion', $rewritten_query)) {
    	$re_query = $rewritten_query['recursion'];
    	unset($rewritten_query['recursion']);
    }
    try {
      $this->beginTransaction();
      if (is_array($rewritten_query)) {
        foreach ($rewritten_query as $single_query) {
          $this->queries[] = "Executing:\t" . $single_query;
          $single_query = trim($single_query);
          if (empty($single_query)) continue;
          $this->pdo->exec($single_query);
        }
      } else {
        $this->queries[] = "Executing:\t" . $rewritten_query;
        $rewritten_query = trim($rewritten_query);
        $this->pdo->exec($rewritten_query);
      }
      $this->commit();
    } catch (PDOException $err) {
      $reason = $err->getCode();
      $message = $err->getMessage();
      if (5 == $reason || 6 == $reason) {
        $this->commit();
        usleep(10000);
      } else {
        $this->rollBack();
      }
    }
    if ($re_query != '') {
    	$this->query($re_query);
    }
    if ($reason > 0) {
      $err_message = sprintf("Problem in executing alter query. Error was: %s", $message);
      $this->set_error(__LINE__, __FUNCTION__, $err_message);
      return false;
    }
    return true;
  }
 
   /**
   * function to execute SHOW VARIABLES query
   * 
   * This query is meaningless for SQLite. This function returns null data and
   * avoid the error message.
   * 
   * @param string
   * @return ObjectArray
   */
  private function show_variables_workaround($query) {
    $dummy_data = array('Variable_name' => '', 'Value' => null);
    $pattern = '/SHOW\\s*VARIABLES\\s*LIKE\\s*(.*)?$/im';
    if (preg_match($pattern, $query, $match)) {
      $value = str_replace("'", '', $match[1]);
      $dummy_data['Variable_name'] = trim($value);
      // this is set for Wordfence Security Plugin
      if ($value == 'max_allowed_packet') {
				$dummy_data['Value'] = 1047552;
			} else {
				$dummy_data['Value'] = '';
			}
    }
    $_results[] = new ObjectArray($dummy_data);
    $this->results = $_results;
    $this->num_rows = count($this->results);
    $this->return_value = $this->num_rows;
    return true;
  }
  
  private function process_results($engine) {
    if (in_array($this->query_type, array('describe', 'desc', 'showcolumns'))) {
      $this->convert_to_columns_object();
    } elseif ('showindex' === $this->query_type){
      $this->convert_to_index_object();
    } elseif (in_array($this->query_type, array('check', 'analyze'))) {
    	$this->convert_result_check_or_analyze();
    } else {
      $this->results = $this->_results;
    }
  }

  private function set_error ($line, $function, $message){
    global $wpdb;
    $this->errors[] = array("line"=>$line, "function"=>$function);
    $this->error_messages[] = $message;
    $this->is_error = true;
    if ($wpdb->suppress_errors) return false;
    if (!$wpdb->show_errors) return false;
    file_put_contents (FQDBDIR .'debug.txt', "Line $line, Function: $function, Message: $message \n", FILE_APPEND);
  }
  
  /**
   *	method that takes the associative array of query results and creates a numeric array of anonymous objects
   */
  private function convert_to_object(){
    $_results = array();
    if (count ($this->results) === 0){
      echo $this->get_error_message();
    } else {
      foreach($this->results as $row){
        $_results[] = new ObjectArray($row);
      }
    }
    $this->results = $_results;
  }
  
  /**
   * method to rewrite pragma results to mysql compatible array
   * when query_type is describe, we use sqlite pragma function.
   * see pdo_sqlite_driver.php
   */
  private function convert_to_columns_object() {
    $_results = array();
    $_columns = array( //Field names MySQL SHOW COLUMNS returns
        'Field'   => "",
        'Type'    => "",
        'Null'    => "",
        'Key'     => "",
        'Default' => "",
        'Extra'   => ""
    );
    if (count($this->_results) === 0) {
      echo $this->get_error_message();
    } else {
      foreach ($this->_results as $row) {
        $_columns['Field']   = $row->name;
        $_columns['Type']    = $row->type;
        $_columns['Null']    = $row->notnull ? "NO" : "YES";
        $_columns['Key']     = $row->pk ? "PRI" : "";
        $_columns['Default'] = $row->dflt_value;
        $_results[] = new ObjectArray($_columns);
      }
    }
    $this->results = $_results;
  }
  /**
   * rewrites the result of SHOW INDEX to the Object compatible with MySQL
   * added the WHERE clause manipulation (ver 1.3.1)
   */
  private function convert_to_index_object() {
    $_results = array();
    $_columns = array(
        'Table'        => "",
        'Non_unique'   => "",// unique -> 0, not unique -> 1
        'Key_name'     => "",// the name of the index
        'Seq_in_index' => "",// column sequence number in the index. begins at 1
        'Column_name'  => "",
        'Collation'    => "",//A(scend) or NULL
        'Cardinality'  => "",
        'Sub_part'     => "",// set to NULL
        'Packed'       => "",// How to pack key or else NULL
        'Null'         => "",// If column contains null, YES. If not, NO.
        'Index_type'   => "",// BTREE, FULLTEXT, HASH, RTREE
        'Comment'      => ""
    );
    if (count($this->_results) == 0) {
      echo $this->get_error_message();
    } else {
      foreach ($this->_results as $row) {
        if ($row->type == 'table' && !stripos($row->sql, 'primary'))
          continue;
        if ($row->type == 'index' && stripos($row->name, 'sqlite_autoindex') !== false)
          continue;
        switch ($row->type) {
          case 'table':
            $pattern1 = '/^\\s*PRIMARY.*\((.*)\)/im';
            $pattern2 = '/^\\s*(\\w+)?\\s*.*PRIMARY.*(?!\()/im';
            if (preg_match($pattern1, $row->sql, $match)) {
              $col_name = trim($match[1]);
              $_columns['Key_name']    = 'PRIMARY';
              $_columns['Non_unique']  = 0;
              $_columns['Column_name'] = $col_name;
            } elseif (preg_match($pattern2, $row->sql, $match)) {
              $col_name = trim($match[1]);
              $_columns['Key_name']    = 'PRIMARY';
              $_columns['Non_unique']  = 0;
              $_columns['Column_name'] = $col_name;
            }
            break;
          case 'index':
            if (stripos($row->sql, 'unique') !== false) {
              $_columns['Non_unique'] = 0;
            } else {
              $_columns['Non_unique'] = 1;
            }
            if (preg_match('/^.*\((.*)\)/i', $row->sql, $match)) {
              $col_name = str_replace("'", '', $match[1]);
              $_columns['Column_name'] = trim($col_name);
            }
            $_columns['Key_name'] = $row->name;
            break;
          default:
            break;
        }
        $_columns['Table']       = $row->tbl_name;
        $_columns['Collation']   = NULL;
        $_columns['Cardinality'] = 0;
        $_columns['Sub_part']    = NULL;
        $_columns['Packed']      = NULL;
        $_columns['Null']        = 'NO';
        $_columns['Index_type']  = 'BTREE';
        $_columns['Comment']     = '';
        $_results[] = new ObjectArray($_columns);
      }
      if (stripos($this->queries[0], 'WHERE') !== false) {
      	preg_match('/WHERE\\s*(.*)$/im', $this->queries[0], $match);
      	list($key, $value) = explode('=', $match[1]);
      	$key = trim($key);
      	$value = preg_replace("/[\';]/", '', $value);
      	$value = trim($value);
      	foreach ($_results as $result) {
      		if (stripos($value, $result->$key) !== false) {
      			unset($_results);
				    $_results[] = $result;
				    break;
      		}
      	}
      }
    }
    $this->results = $_results;
  }

  private function convert_result_check_or_analyze() {
  	$results = array();
  	if ($this->query_type == 'check') {
	  	$_columns = array(
	  			'Table' => '',
	  			'Op'    => 'check',
	  			'Msg_type' => 'status',
	  			'Msg_text' => 'OK'
	  		);
  	} else {
  		$_columns = array(
  				'Table'    => '',
  				'Op'       => 'analyze',
  				'Msg_type' => 'status',
  				'Msg_text' => 'Table is already up to date'
  			);
  	}
  	$_results[] = new ObjectArray($_columns);
  	$this->results = $_results;
  }
  /**
   * function to get SQLite library version
   * this is used for checking if SQLite can execute multiple rows insert
   * @return version number string or 0
   */
  private function get_sqlite_version() {
    try {
      $statement = $this->pdo->prepare('SELECT sqlite_version()');
      $statement->execute();
      $result = $statement->fetch(PDO::FETCH_NUM);
      return $result[0];
    } catch (PDOException $err) {
      return '0';
    }
  }
  /**
   * function call to PDO::beginTransaction()
   * @see PDO::beginTransaction()
   */
  public function beginTransaction() {
  	if ($this->has_active_transaction) {
  		return false;
  	} else {
  		$this->has_active_transaction = $this->pdo->beginTransaction();
  		return $this->has_active_transaction;
  	}
  }
  /**
   * function call to PDO::commit()
   * @see PDO::commit()
   */
  public function commit() {
  	$this->pdo->commit();
  	$this->has_active_transaction = false;
  }
  /**
   * function call to PDO::rollBack()
   * @see PDO::rollBack()
   */
  public function rollBack() {
  	$this->pdo->rollBack();
  	$this->has_active_transaction = false;
  }
}

class ObjectArray {
  function __construct($data = null,&$node= null) {
    foreach ($data as $key => $value) {
      if ( is_array($value) ) {
        if (!$node) {
          $node =& $this;
        }
        $node->$key = new stdClass();
        self::__construct($value,$node->$key);
      } else {
        if (!$node) {
          $node =& $this;
        }
        $node->$key = $value;
      }
    }
  }
}
?>