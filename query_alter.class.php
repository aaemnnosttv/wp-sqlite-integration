<?php
/**
 * The class for manipulating ALTER query
 * newly supports multiple variants
 * @package SQLite Integration
 * @author Kojima Toshiyasu
 */
class AlterQuery {
  public $_query = null;

  public function rewrite_query($query, $query_type) {
    $tokens = array();
    if (stripos($query, $query_type) === false) {
      return false;
    }
    $query = str_replace('`', '', $query);
    if (preg_match('/^\\s*(ALTER\\s*TABLE)\\s*(\\w+)?\\s*/ims', $query, $match)) {
      $command = str_ireplace($match[0], '', $query);
      $tmp_tokens['query_type'] = trim($match[1]);
      $tmp_tokens['table_name'] = trim($match[2]);
      $command_array = $this->split_multiple($command);
      foreach ($command_array as $single_command) {
        $command_tokens = $this->command_tokenizer($single_command);
        if (!empty($command_tokens)) {
          $tokens[] = array_merge($tmp_tokens, $command_tokens);
        } else {
          $this->_query = 'SELECT 1=1';
        }
      }
      foreach ($tokens as $token) {
        $command_name = $token['command'];
        switch ($command_name) {
          case 'add column': case 'rename to': case 'add index': case 'drop index':
            $this->_query = $this->handle_single_command($token);
            break;
          case 'add primary key':
            $this->_query = $this->handle_add_primary_key($token);
            break;
          case 'drop primary key':
            $this->_query = $this->handle_drop_primary_key($token);
            break;
          case 'modify column':
            $this->_query = $this->handle_modify_command($token);
            break;
          case 'change column':
            $this->_query = $this->handle_change_command($token);
            break;
          case 'alter column':
            $this->_query = $this->handle_alter_command($token);
            break;
          default:
            break;
        }
      }
    } else {
      $this->_query = 'SELECT 1=1';
    }
    return $this->_query;
  }

  private function command_tokenizer($command) {
    $tokens = array();
    if (preg_match('/^(ADD|DROP|RENAME|MODIFY|CHANGE|ALTER)\\s*(\\w+)?\\s*(\\w+)?\\s*/ims', $command, $match)) {
      $the_rest = str_ireplace($match[0], '', $command);
      $match_1 = strtolower(trim($match[1]));
      $match_2 = strtolower(trim($match[2]));
      $match_3 = isset($match[3]) ? strtolower(trim($match[3])) : '';
      switch ($match_1) {
        case 'add':
          if (in_array($match_2, array('fulltext', 'constraint', 'foreign'))) {
            break;
          } elseif ($match_2 == 'column') {
            $tokens['command'] = $match_1.' '.$match_2;
            $tokens['column_name'] = $match_3;
            $tokens['column_def'] = trim($the_rest);
          } elseif ($match_2 == 'primary') {
            $tokens['command'] = $match_1.' '.$match_2.' '.$match_3;
            $tokens['column_name'] = $the_rest;
          } elseif ($match_2 == 'unique') {
            list($index_name, $col_name) = preg_split('/[\(\)]/s', trim($the_rest), -1, PREG_SPLIT_DELIM_CAPTURE);
            $tokens['unique'] = true;
            $tokens['command'] = $match_1.' '.$match_3;
            $tokens['index_name'] = trim($index_name);
            $tokens['column_name'] = '('.trim($col_name).')';
          } elseif (in_array($match_2, array('index', 'key'))) {
            $tokens['command'] = $match_1.' '.$match_2;
            if ($match_3 == '') {
            	$tokens['index_name'] = str_replace(array('(', ')'), '', $the_rest);
            } else {
	            $tokens['index_name'] = $match_3;
            }
            $tokens['column_name'] = trim($the_rest);
          } else {
            $tokens['command'] = $match_1.' column';
            $tokens['column_name'] = $match_2;
            $tokens['column_def'] = $match_3.' '.$the_rest;
          }
          break;
        case 'drop':
          if ($match_2 == 'column') {
            $tokens['command'] = $match_1.' '.$match_2;
            $tokens['column_name'] = trim($match_3);
          } elseif ($match_2 == 'primary') {
            $tokens['command'] = $match_1.' '.$match_2.' '.$match_3;
          } elseif (in_array($match_2, array('index', 'key'))) {
            $tokens['command'] = $match_1.' '.$match_2;
            $tokens['index_name'] = $match_3;
          } elseif ($match_2 == 'primary') {
            $tokens['command'] = $match_1.' '.$match_2.' '.$match_3;
          } else {
          	$tokens['command'] = $match_1.' column';
          	$tokens['column_name'] = $match_2;
          }
          break;
        case 'rename':
          if ($match_2 == 'to') {
            $tokens['command'] = $match_1.' '.$match_2;
            $tokens['column_name'] = $match_3;
          } else {
            $tokens['command'] = $match_1.' to';
            $tokens['column_name'] = $match_2;
          }
          break;
        case 'modify':
          if ($match_2 == 'column') {
            $tokens['command'] = $match_1.' '.$match_2;
            $tokens['column_name'] = $match_3;
            $tokens['column_def'] = trim($the_rest);
          } else {
            $tokens['command'] = $match_1.' column';
            $tokens['column_name'] = $match_2;
            $tokens['column_def'] = $match_3.' '.trim($the_rest);
          }
          break;
        case 'change':
          if ($match_2 == 'column') {
            $tokens['command'] = $match_1.' '.$match_2;
            $tokens['old_column'] = $match_3;
            list($new_col) = preg_split('/\s/s', trim($the_rest), -1, PREG_SPLIT_DELIM_CAPTURE);
            $tokens['new_column'] = $new_col;
            $col_def = str_ireplace($new_col, '', $the_rest);
            $tokens['column_def'] = trim($col_def);
          } else {
            $tokens['command'] = $match_1.' column';
            $tokens['old_column'] = $match_2;
            $tokens['new_column'] = $match_3;
            $tokens['column_def'] = trim($the_rest);
          }
          break;
        case 'alter':
          if ($match_2 == 'column') {
            $tokens['command'] = $match_1.' '.$match_2;
            $tokens['column_name'] = $match_3;
            list($set_or_drop) = explode(' ', $the_rest);
            if ($set_or_drop == 'set') {
              $tokens['default_command'] = 'set default';
              $default_value = str_ireplace('set default', '', $the_rest);
              $tokens['default_value'] = trim($default_value);
            } else {
              $tokens['default_command'] = 'drop default';
            }
          } else {
            $tokens['command'] = $match_1.' column';
            $tokens['column_name'] = $match_2;
            if ($match_3 == 'set') {
              $tokens['default_command'] = 'set default';
              $default_value = str_ireplace('default', '', $the_rest);
              $tokens['default_value'] = trim($default_value);
            } else {
              $tokens['default_command'] = 'drop default';
            }
          }
          break;
        default:
          break;
      }
      return $tokens;
    }
  }
  
  private function split_multiple($command) {
    $out = true;
    $command_array = array();
    $command_string = '';
    $tokens = preg_split('/\b/s', $command, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($tokens as $token) {
      switch (trim($token)) {
        case ';':
          break;
        case '(':
          $command_string .= $token;
          $out = false;
          break;
        case ')':
          $command_string .= $token;
          $out = true;
          break;
        case '),':
          $command_array[] = $command_string;
          $command_string = '';
          $out = true;
          break;
        case ',':
          if ($out) {
            $command_array[] = $command_string;
            $command_string = '';
          } else {
            $command_string .= $token;
          }
          break;
        default:
          $command_string .= $token;
          break;
      }
    }
    if (!empty($command_string)) {
      $command_array[] = $command_string;
    }
    return $command_array;
  }
  
  private function handle_single_command($queries) {
    $tokenized_query = $queries;
    if (stripos($tokenized_query['command'], 'add column') !== false) {
      $column_def = $this->convert_field_types($tokenized_query['column_name'], $tokenized_query['column_def']);
      $query = "ALTER TABLE {$tokenized_query['table_name']} ADD COLUMN {$tokenized_query['column_name']} $column_def";
    } elseif (stripos($tokenized_query['command'], 'rename') !== false) {
      $query = "ALTER TABLE {$tokenized_query['table_name']} RENAME TO {$tokenized_query['column_name']}";
    } elseif (stripos($tokenized_query['command'], 'add index') !== false) {
      $unique = isset($tokenized_query['unique']) ? 'UNIQUE' : '';
      $query = "CREATE $unique INDEX IF NOT EXISTS {$tokenized_query['index_name']} ON {$tokenized_query['table_name']} {$tokenized_query['column_name']}";
    } elseif (stripos($tokenized_query['command'], 'drop index') !== false) {
      $query = "DROP INDEX IF EXISTS {$tokenized_query['index_name']}";
    } else {
      $query = 'SELECT 1=1';
    }
    return $query;
  }

  private function handle_add_primary_key($queries) {
    $tokenized_query = $queries;
    $tbl_name = $tokenized_query['table_name'];
    $temp_table = 'temp_'.$tokenized_query['table_name'];
    $_wpdb = new PDODB();
    $query_obj = $_wpdb->get_results("SELECT sql FROM sqlite_master WHERE tbl_name='$tbl_name'");
    $_wpdb = null;
    for ($i = 0; $i < count($query_obj); $i++) {
      $index_queries[$i] = $query_obj[$i]->sql;
    }
    $table_query = array_shift($index_queries);
    $table_query = str_replace($tokenized_query['table_name'], $temp_table, $table_query);
    $table_query = rtrim($table_query, ')');
    $table_query = ", PRIMARY KEY {$tokenized_query['column_name']}";
    $query[] = $table_query;
    $query[] = "INSERT INTO $temp_table SELECT * FROM {$tokenized_query['table_name']}";
    $query[] = "DROP TABLE IF EXISTS {$tokenized_query['table_name']}";
    $query[] = "ALTER TABLE $temp_table RENAME TO {$tokenized_query['table_name']}";
    foreach ($index_queries as $index) {
      $query[] = $index;
    }
    return $query;
  }
  
  private function handle_drop_primary_key($queries) {
    $tokenized_query = $queries;
    $temp_table = 'temp_'.$tokenized_query['table_name'];
    $_wpdb = new PDODB();
    $query_obj = $_wpdb->get_results("SELECT sql FROM sqlite_master WHERE tbl_name='{$tokenized_query['table_name']}'");
    $_wpdb = null;
    for ($i = 0; $i < count($query_obj); $i++) {
      $index_queries[$i] = $query_obj[$i]->sql;
    }
    $table_query = array_shift($index_queries);
    $pattern1 = '/^\\s*PRIMARY\\s*KEY\\s*\(.*\)/im';
    $pattern2 = '/^\\s*.*(PRIMARY\\s*KEY\\s*(:?AUTOINCREMENT|))\\s*(?!\()/im';
    if (preg_match($pattern1, $table_query, $match)) {
      $table_query = str_replace($match[0], '', $table_query);
    } elseif (preg_match($pattern2, $table_query, $match)) {
      $table_query = str_replace($match[1], '', $table_query);
    }
    $table_query = str_replace($tokenized_query['table_name'], $temp_table, $table_query);
    $query[] = $table_query;
    $query[] = "INSERT INTO $temp_table SELECT * FROM {$tokenized_query['table_name']}";
    $query[] = "DROP TABLE IF EXISTS {$tokenized_query['table_name']}";
    $query[] = "ALTER TABLE $temp_table RENAME TO {$tokenized_query['table_name']}";
    foreach ($index_queries as $index) {
      $query[] = $index;
    }
    return $query;
  }
  
  private function handle_modify_command($queries) {
    $tokenized_query = $queries;
    $temp_table = 'temp_'.$tokenized_query['table_name'];
    $column_def = $this->convert_field_types($tokenized_query['column_name'], $tokenized_query['column_def']);
    $_wpdb = new PDODB();
    $query_obj = $_wpdb->get_results("SELECT sql FROM sqlite_master WHERE tbl_name='{$tokenized_query['table_name']}'");
    $_wpdb = null;
    for ($i =0; $i < count($query_obj); $i++) {
      $index_queries[$i] = $query_obj[$i]->sql;
    }
    $create_query = array_shift($index_queries);
    if (stripos($create_query, $tokenized_query['column_name']) === false) {
      return 'SELECT 1=1';
    } elseif (preg_match("/{$tokenized_query['column_name']}\\s*{$tokenized_query['column_def']}\\s*[,)]/i", $create_query)) {
      return 'SELECT 1=1';
    }
    $create_query = preg_replace("/{$tokenized_query['table_name']}/i", $temp_table, $create_query);
    if (preg_match("/\\b{$tokenized_query['column_name']}\\s*.*(?=,)/ims", $create_query)) {
      $create_query = preg_replace("/\\b{$tokenized_query['column_name']}\\s*.*(?=,)/ims", "{$tokenized_query['column_name']} {$tokenized_query['column_def']}", $create_query);
    } elseif (preg_match("/\\b{$tokenized_query['column_name']}\\s*.*(?=\))/ims", $create_query)) {
      $create_query = preg_replace("/\\b{$tokenized_query['column_name']}\\s*.*(?=\))/ims", "{$tokenized_query['column_name']} {$tokenized_query['column_def']}", $create_query);
    }
    $query[] = $create_query;
    $query[] = "INSERT INTO $temp_table SELECT * FROM {$tokenized_query['table_name']}";
    $query[] = "DROP TABLE IF EXISTS {$tokenized_query['table_name']}";
    $query[] = "ALTER TABLE $temp_table RENAME TO {$tokenized_query['table_name']}";
    foreach ($index_queries as $index) {
      $query[] = $index;
    }
    return $query;
  }
  
  private function handle_change_command($queries) {
    $col_check = false;
    $old_fields = '';
    $new_fields = '';
    $tokenized_query = $queries;
    $temp_table = 'temp_'.$tokenized_query['table_name'];
    $column_def = $this->convert_field_types($tokenized_query['new_column'], $tokenized_query['column_def']);
    $_wpdb = new PDODB();
    $col_obj = $_wpdb->get_results("SHOW COLUMNS FROM {$tokenized_query['table_name']}");
    foreach ($col_obj as $col) {
      if ($col->Field == $tokenized_query['old_column']) $col_check = true;
      $old_fields .= $col->Field . ',';
    }
    if ($col_check == false) {
      $_wpdb = null;
      return 'SELECT 1=1';
    }
    $old_fields = rtrim($old_fields, ',');
    $new_fields = str_replace($tokenized_query['old_column'], $tokenized_query['new_column'], $old_fields);
    $query_obj = $_wpdb->get_results("SELECT sql FROM sqlite_master WHERE tbl_name='{$tokenized_query['table_name']}'");
    $_wpdb = null;
    for ($i = 0; $i < count($query_obj); $i++) {
      $index_queries[$i] = $query_obj[$i]->sql;
    }
    $create_query = array_shift($index_queries);
    $create_query = preg_replace("/{$tokenized_query['table_name']}/i", $temp_table, $create_query);
    if (preg_match("/\\b{$tokenized_query['old_column']}\\s*(.+?)(?=,)/ims", $create_query, $match)) {
      if ($tokenized_query['column_def'] == trim($match[1])) {
        return 'SELECT 1=1';
      } else {
        $create_query = preg_replace("/\\b{$tokenized_query['old_column']}\\s*.*?(?=,)/ims", "{$tokenized_query['new_column']} {$tokenized_query['column_def']}", $create_query);
      }
    } elseif (preg_match("/\\b{$tokenized_query['old_column']}\\s*(.+?)(?=\))/ims", $create_query, $match)) {
      if ($tokenized_query['column_def'] == trim($match[1])) {
        return 'SELECT 1=1';
      } else {
        $create_query = preg_replace("/\\b{$tokenized_query['old_column']}\\s*.*?(?=\))/ims", "{$tokenized_query['new_column']} {$tokenized_query['column_def']}", $create_query);
      }
    }
    $query[] = $create_query;
    $query[] = "INSERT INTO $temp_table ($new_fields) SELECT $old_fields FROM {$tokenized_query['table_name']}";
    $query[] = "DROP TABLE IF EXISTS {$tokenized_query['table_name']}";
    $query[] = "ALTER TABLE $temp_table RENAME TO {$tokenized_query['table_name']}";
    foreach ($index_queries as $index) {
      $query[] = $index;
    }
    return $query;
  }
  
  private function handle_alter_command($queries) {
    $tokenized_query = $queries;
    $temp_table = 'temp_'.$tokenized_query['table_name'];
    if (stripos($tokenized_query['default_command'], 'set') !== false) {
      $def_value = $this->convert_field_types($tokenized_query['column_name'], $tokenized_query['default_value']);
      $def_value = 'DEFAULT '.$def_value;
    } else {
      $def_value = '';
    }
    $_wpdb = new PDODB();
    $query_obj = $_wpdb->get_results("SELECT sql FROM sqlite_master WHERE tbl_name='{$tokenized_query['table_name']}'");
    $_wpdb = null;
    for ($i =0; $i < count($query_obj); $i++) {
      $index_queries[$i] = $query_obj[$i]->sql;
    }
    $create_query = array_shift($index_queries);
    if (stripos($create_query, $tokenized_query['column_name']) === false) {
      return 'SELECT 1=1';
    }
    if (preg_match("/\\s*({$tokenized_query['column_name']}\\s*.*?)\\s*(DEFAULT\\s*.*)[,)]/im", $create_query, $match)) {
      $col_def = trim($match[1]);
      $old_default = trim($match[2]);
      $create_query = preg_replace("/($col_def)\\s*$old_default/im", "\\1 $def_value", $create_query);
      $create_query = str_ireplace($tokenized_query['table_name'], $temp_table, $create_query);
    } else {
      return 'SELECT 1=1';
    }
    $query[] = $create_query;
    $query[] = "INSERT INTO $temp_table SELECT * FROM {$tokenized_query['table_name']}";
    $query[] = "DROP TABLE IF EXISTS {$tokenized_query['table_name']}";
    $query[] = "ALTER TABLE $temp_table RENAME TO {$tokenized_query['table_name']}";
      foreach ($index_queries as $index) {
      $query[] = $index;
    }
    return $query;
  }
  /**
   * Change the field definition to SQLite compatible data type.
   * @param string $col_name
   * @param string $col_def
   * @return string
   */
  private function convert_field_types($col_name, $col_def){
    $array_types = array(
        'bit'        => 'INTEGER', 'bool'       => 'INTEGER',
        'boolean'    => 'INTEGER', 'tinyint'    => 'INTEGER',
        'smallint'   => 'INTEGER', 'mediumint'  => 'INTEGER',
        'int'        => 'INTEGER', 'integer'    => 'INTEGER',
        'bigint'     => 'INTEGER', 'float'      => 'REAL',
        'double'     => 'REAL',    'decimal'    => 'REAL',
        'dec'        => 'REAL',    'numeric'    => 'REAL',
        'fixed'      => 'REAL',    'date'       => 'TEXT',
        'datetime'   => 'TEXT',    'timestamp'  => 'TEXT',
        'time'       => 'TEXT',    'year'       => 'TEXT',
        'char'       => 'TEXT',    'varchar'    => 'TEXT',
        'binary'     => 'INTEGER', 'varbinary'  => 'BLOB',
        'tinyblob'   => 'BLOB',    'tinytext'   => 'TEXT',
        'blob'       => 'BLOB',    'text'       => 'TEXT',
        'mediumblob' => 'BLOB',    'mediumtext' => 'TEXT',
        'longblob'   => 'BLOB',    'longtext'   => 'TEXT'
    );
    $array_curtime = array('current_timestamp', 'current_time', 'current_date');
    $array_reptime = array("'0000-00-00 00:00:00'", "'0000-00-00 00:00:00'", "'0000-00-00'");
    $def_string = str_replace('`', '', $col_def);
    foreach ($array_types as $o=>$r){
      $pattern = "/\\b" . $o . "\\s*(\([^\)]*\))?\\s*/imsx";
      if (preg_match($pattern, $def_string)) {
        $def_string = preg_replace($pattern, "$r ", $def_string);
        break;
      }
    }
    $def_string = preg_replace('/unsigned/im', '', $def_string);
    $def_string = preg_replace('/auto_increment/im', 'PRIMARY KEY AUTOINCREMENT', $def_string);
    // when you use ALTER TABLE ADD, you can't use current_*. so we replace
    $def_string = str_ireplace($array_curtime, $array_reptime, $def_string);
    // colDef is enum
    $pattern_enum = '/enum\((.*?)\)([^,\)]*)/ims';
    if (preg_match($pattern_enum, $col_def, $matches)) {
      $def_string = 'TEXT' . $matches[2] . ' CHECK (' . $col_name . ' IN (' . $matches[1] . '))';
    }
    return $def_string;
  }
}
?>