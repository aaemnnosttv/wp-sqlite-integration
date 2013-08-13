<?php
/**
 * @package SQLite Integration
 * @author Kojima Toshiyasu, Justin Adie
 */
 
/**
 * Provides a class for rewriting create queries
 * this class borrows its inspiration from the work done on tikiwiki.
 */
class CreateQuery{

	private $_query = '';
	private $index_queries = array();
	private $_errors = array();
	private $table_name = '';
	private $has_primary_key = false;
	
	/**
	 *	initialises the object properties
	 *	@param string $query	the query being processed
	 *	@return string|array	the processed (rewritten) query
	 */
	public function rewrite_query($query){
		$this->_query = $query;
		$this->_errors [] = '';
		if (preg_match('/^CREATE\\s*(UNIQUE|FULLTEXT|)\\s*INDEX/ims', $this->_query, $match)) {
			if (isset($match[1]) && stripos($match[1], 'fulltext') !== false) {
				return 'SELECT 1=1';
			} else {
				return $this->_query;
			}
		} elseif (preg_match('/^CREATE\\s*(TEMP|TEMPORARY|)\\s*TRIGGER\\s*/im', $this->_query)) {
			return $this->_query;
		}
		$this->get_table_name();
		$this->rewrite_comments();
		$this->rewrite_field_types();
		$this->rewrite_character_set();
		$this->rewrite_engine_info();
		$this->rewrite_unsigned();
		$this->rewrite_autoincrement();
		$this->rewrite_primary_key();
		$this->rewrite_unique_key();
		$this->rewrite_enum();
		$this->rewrite_set();
		$this->rewrite_key();
		$this->add_if_not_exists();
		$this->strip_backticks();
		
		return $this->post_process();
	}
	
	/**
	 * Method for getting the table name from the create query.
	 * taken from PDO for WordPress
	 * we don't need 'IF NOT EXISTS', so we changed the pattern.
	 */
	private function get_table_name(){
		// $pattern = '/^\\s*CREATE\\s*(TEMP|TEMPORARY)?\\s*TABLE\\s*(IF NOT EXISTS)?\\s*([^\(]*)/imsx';
    $pattern = '/^\\s*CREATE\\s*(?:TEMP|TEMPORARY)?\\s*TABLE\\s*(?:IF\\s*NOT\\s*EXISTS)?\\s*([^\(]*)/imsx';
		if (preg_match($pattern, $this->_query, $matches)) {
      $this->table_name = trim($matches[1]);
    }
	}
	
	/**
	 * Method for changing the field type to SQLite compatible type.
	 */
	private function rewrite_field_types(){
		$array_types = array (
		    'bit'        => 'integer', 'bool'       => 'integer',
		    'boolean'    => 'integer', 'tinyint'    => 'integer',
		    'smallint'   => 'integer', 'mediumint'  => 'integer',
		    'int'        => 'integer', 'integer'    => 'integer',
		    'bigint'     => 'integer', 'float'      => 'real',
		    'double'     => 'real',    'decimal'    => 'real',
		    'dec'        => 'real',    'numeric'    => 'real',
		    'fixed'      => 'real',    'date'       => 'text',
		    'datetime'   => 'text',    'timestamp'  => 'text',
		    'time'       => 'text',    'year'       => 'text',
		    'char'       => 'text',    'varchar'    => 'text',
		    'binary'     => 'integer', 'varbinary'  => 'blob',
		    'tinyblob'   => 'blob',    'tinytext'   => 'text',
		    'blob'       => 'blob',    'text'       => 'text',
		    'mediumblob' => 'blob',    'mediumtext' => 'text',
		    'longblob'   => 'blob',    'longtext'   => 'text'
		    );
		foreach ($array_types as $o=>$r){
			$pattern = "/\\b(?<!`)$o\\b\\s*(\([^\)]*\)*)?\\s*/ims";
			if (preg_match("/^\\s*.*?\\s*\(.*?$o.*?\)/im", $this->_query)) {
				;
			} else {
				$this->_query = preg_replace($pattern, " $r ", $this->_query);
			}
		}
	}

	/**
	 * Method for stripping the comments from the SQL statement
	 */	
	private function rewrite_comments(){
		$this->_query = preg_replace("/# --------------------------------------------------------/","-- ******************************************************",$this->_query);
		$this->_query = preg_replace("/#/","--",$this->_query);
	}
	
	/**
	 * Method for stripping the engine and other stuffs
	 */	
	private function rewrite_engine_info(){
		$this->_query = preg_replace("/\\s*(TYPE|ENGINE)\\s*=\\s*.*(?<!;)/ims",'',$this->_query);
		$this->_query = preg_replace("/ AUTO_INCREMENT\\s*=\\s*[0-9]*/ims",'',$this->_query);
	}
	
	/**
	 * Method for stripping unsigned
	 */		
	private function rewrite_unsigned(){
		$this->_query  = preg_replace('/\\bunsigned\\b/ims', ' ', $this->_query);
	}
	
	/**
	 * Method for rewriting auto_increment
	 * if the field type is 'integer primary key', it is automatically autoincremented
	 */	
	private function rewrite_autoincrement(){
	  $this->_query = preg_replace('/\\bauto_increment\\s*primary\\s*key\\s*(,)?/ims', ' PRIMARY KEY AUTOINCREMENT \\1', $this->_query, -1, $count);
		$this->_query  = preg_replace('/\\bauto_increment\\b\\s*(,)?/ims', ' PRIMARY KEY AUTOINCREMENT $1', $this->_query, -1, $count);
		if ($count > 0){
			$this->has_primary_key = true;
		}
	}
	
	/**
	 * Method for rewriting primary key
	 */	
	private function rewrite_primary_key(){
		if ($this->has_primary_key) {
			$this->_query  = preg_replace('/\\bprimary key\\s*\([^\)]*\)/ims', ' ', $this->_query);
		} else {
		  // If primary key has an index name, we remove that name.
		  $this->_query = preg_replace('/\\bprimary\\s*key\\s*.*?\\s*(\(.*?\))/im', 'PRIMARY KEY \\1', $this->_query);
		}
	}
	
	/**
	 * Method for rewriting unique key
	 */	
	private function rewrite_unique_key(){
		$this->_query  = preg_replace_callback('/\\bunique key\\b([^\(]*)(\([^\)]*\))/ims', array($this, '_rewrite_unique_key'), $this->_query);
	}
	
	/**
	 * Callback method for rewrite_unique_key
	 * @param array	$matches	an array of matches from the Regex
	 */
	private function _rewrite_unique_key($matches){
	  $index_name = trim($matches[1]);
	  $col_name = trim($matches[2]);
    $tbl_name = $this->table_name;
	  $_wpdb = new PDODB();
	  $results = $_wpdb->get_results("SELECT name FROM sqlite_master WHERE type='index'");
	  $_wpdb = null;
	  if ($results) {
	    foreach ($results as $result) {
	      if ($result->name == $index_name) {
	        $r = rand(0, 50);
	        $index_name = $index_name . "_$r";
	        break;
	      }
	    }
	  }
		$index_name = str_replace(' ', '', $index_name);
		$this->index_queries[] = "CREATE UNIQUE INDEX $index_name ON " . $tbl_name .$col_name;
		return '';
	}
	
	/**
	 * Method for handling ENUM fields
	 * SQLite doesn't support enum, so we change it to check constraint
	 */
	private function rewrite_enum(){
		$pattern = '/(,|\))([^,]*)enum\((.*?)\)([^,\)]*)/ims';
		$this->_query  = preg_replace_callback($pattern, array($this, '_rewrite_enum'), $this->_query);
	}
	
	/**
	 * Method for the callback function rewrite_enum and rewrite_set
	 */
	private function _rewrite_enum($matches){
		$output = $matches[1] . ' ' . $matches[2]. ' TEXT '. $matches[4].' CHECK ('.$matches[2].' IN ('.$matches[3].')) ';
		return $output;
	}

	/**
	 * Method for rewriting usage of set
	 * whilst not identical to enum, they are similar and sqlite does not
	 * support either.
	 */
	private function rewrite_set(){
		$pattern = '/\b(\w)*\bset\\s*\((.*?)\)\\s*(.*?)(,)*/ims';
		$this->_query  = preg_replace_callback($pattern, array($this, '_rewrite_enum'), $this->_query);
	}
	
	/**
	 * Method for rewriting usage of key to create an index
	 * sqlite cannot create non-unique indices as part of the create query
	 * so we need to create an index by hand and append it to the create query
	 */
	private function rewrite_key(){
		$this->_query = preg_replace_callback('/,\\s*(KEY|INDEX)\\s*(\\w+)?\\s*(\(.*(?<!\\d)\))/im', array($this, '_rewrite_key'), $this->_query);
	}

	/**
	 * Callback method for rewrite_key
	 * @param array	$matches	an array of matches from the Regex
	 */	
	private function _rewrite_key($matches){
	  $index_name = trim($matches[2]);
	  $col_name = trim($matches[3]);
	  if (preg_match('/\([0-9]+?\)/', $col_name, $match)) {
	    $col_name = preg_replace_callback('/\([0-9]+?\)/', array($this, '_remove_length'), $col_name);
	  }
	  $tbl_name = $this->table_name;
	  $_wpdb = new PDODB();
	  $results = $_wpdb->get_results("SELECT name FROM sqlite_master WHERE type='index'");
	  $_wpdb = null;
	  if ($results) {
  	  foreach ($results as $result) {
  	    if ($result->name == $index_name) {
  	      $r = rand(0, 50);
  	      $index_name = $index_name . "_$r";
  	      break;
  	    }
  	  }
	  }
		$this->index_queries[] = 'CREATE INDEX '. $index_name . ' ON ' . $tbl_name . $col_name ;
		return '';
	}
	private function _remove_length($match) {
	  return '';
	}
	/**
	 * Method to assemble the main query and index queries into an array
	 * to be returned to the base class
	 * @return	array
	 */
	private function post_process(){
		$mainquery = $this->_query;
		do{
			$count = 0;
			$mainquery = preg_replace('/,\\s*\)/imsx',')', $mainquery, -1, $count);
		} while ($count > 0);
		do {
		  $count = 0;
		  $mainquery = preg_replace('/\(\\s*?,/imsx', '(', $mainquery, -1, $count);
		} while ($count > 0);
		$return_val[] = $mainquery;
		$return_val = array_merge($return_val, $this->index_queries);
    return $return_val;
	}
	/**
	 * Method to add IF NOT EXISTS to query defs
	 * sometimes, if upgrade.php is being called, wordpress seems to want to run
	 * new create queries. this stops the query from throwing an error and halting
	 * output
	 */
	private function add_if_not_exists(){
		$pattern_table = '/^\\s*CREATE\\s*(TEMP|TEMPORARY)?\\s*TABLE\\s*(IF NOT EXISTS)?\\s*/ims';
		$this->_query = preg_replace($pattern_table, 'CREATE $1 TABLE IF NOT EXISTS ', $this->_query);
		$pattern_index = '/^\\s*CREATE\\s*(UNIQUE)?\\s*INDEX\\s*(IF NOT EXISTS)?\\s*/ims';
		for ($i = 0; $i < count($this->index_queries); $i++) {
		  $this->index_queries[$i] = preg_replace($pattern_index, 'CREATE $1 INDEX IF NOT EXISTS ', $this->index_queries[$i]);
		}
	}

	/**
	 * Method to strip back ticks
	 */	
	private function strip_backticks(){
		$this->_query = str_replace('`', '', $this->_query);
    foreach ($this->index_queries as &$query) {
      $query = str_replace('`', '', $query);
    }
	}
	
	/**
	 * Method to remove the character set information from within mysql queries
	 */	
	private function rewrite_character_set(){
		$pattern_charset = '/\\b(default\\s*character\\s*set|default\\s*charset|character\\s*set)\\s*(?<!\()[^ ]*/im';
		$pattern_collate1 = '/\\s*collate\\s*[^ ]*(?=,)/im';
    $pattern_collate2 = '/\\s*collate\\s*[^ ]*(?<!;)/im';
    $patterns = array($pattern_charset, $pattern_collate1, $pattern_collate2);
		$this->_query = preg_replace($patterns, '', $this->_query);
	}
}