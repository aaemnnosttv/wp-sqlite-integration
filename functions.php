<?php
/**
 * @package SQLite Integration
 * @author Kojima Toshiyasu, Justin Adie
 *
 */

/**
 * This class defines user defined functions(UDFs) for PDO
 * This replaces the functions used in the SQL statement with the PHP functions.
 * If you want another, add the name in the array and define the function with
 * PHP script.
 */
class PDOSQLiteUDFS {
  public function __construct(&$pdo){
    foreach ($this->functions as $f=>$t) {
      $pdo->sqliteCreateFunction($f, array($this, $t));
    }
  }

  private $functions = array(
      'month'          => 'month',
      'year'           => 'year',
      'day'            => 'day',
      'unix_timestamp' => 'unix_timestamp',
      'now'            => 'now',
      'char_length'    => 'char_length',
      'md5'            => 'md5',
      'curdate'        => 'curdate',
      'rand'           => 'rand',
      'substring'      => 'substring',
      'dayofmonth'     => 'day',
      'second'         => 'second',
      'minute'         => 'minute',
      'hour'           => 'hour',
      'date_format'    => 'dateformat',
      'from_unixtime'  => 'from_unixtime',
      'date_add'       => 'date_add',
      'date_sub'       => 'date_sub',
      'adddate'        => 'date_add',
      'subdate'        => 'date_sub',
      'localtime'      => 'now',
      'localtimestamp' => 'now',
      //'date'=>'date',
      'isnull'         => 'isnull',
      'if'             => '_if',
      'regexpp'        => 'regexp',
      'concat'         => 'concat',
      'field'          => 'field',
      'log'            => 'log',
      'least'          => 'least',
      'replace'        => 'replace',
      'get_lock'       => 'get_lock',
      'release_lock'   => 'release_lock',
      'ucase'          => 'ucase',
      'lcase'          => 'lcase',
      'inet_ntoa'      => 'inet_ntoa',
      'inet_aton'      => 'inet_aton',
      'datediff'       => 'datediff',
  		'locate'         => 'locate',
			'version'        => 'version'
  );

  public function month($field){
    $t = strtotime($field);
    return date('n', $t);
  }
  public function year($field){
    $t = strtotime($field);
    return date('Y', $t);
  }
  public function day($field){
    $t = strtotime($field);
    return date('j', $t);
  }
  public function unix_timestamp($field = null){
    return is_null($field) ? time() : strtotime($field);
  }
  public function second($field){
    $t = strtotime($field);
    return intval( date("s", $t) );
  }
  public function minute($field){
    $t = strtotime($field);
    return  intval(date("i", $t));
  }
  public function hour($time){
    list($hours, $minutes, $seconds) = explode(":", $time);
    return intval($hours);
  }
  public function from_unixtime($field, $format=null){
    // $field is a timestamp
    //convert to ISO time
    $date = date("Y-m-d H:i:s", $field);
    //now submit to dateformat

    return is_null($format) ? $date : $self->dateformat($date, $format);
  }
  public function now(){
    return date("Y-m-d H:i:s");
  }
  public function curdate() {
    return date("Y-m-d");
  }
  public function char_length($field){
    return strlen($field);
  }
  public function md5($field){
    return md5($field);
  }
  public function rand(){
    return rand(0,1);
  }
  public function substring($text, $pos, $len=null){
    if (is_null($len)) return substr($text, $pos-1);
    else return substr($text, $pos-1, $len);
  }
  public function dateformat($date, $format){
    $mysql_php_dateformats = array ( '%a' => 'D', '%b' => 'M', '%c' => 'n', '%D' => 'jS', '%d' => 'd', '%e' => 'j', '%H' => 'H', '%h' => 'h', '%I' => 'h', '%i' => 'i', '%j' => 'z', '%k' => 'G', '%l' => 'g', '%M' => 'F', '%m' => 'm', '%p' => 'A', '%r' => 'h:i:s A', '%S' => 's', '%s' => 's', '%T' => 'H:i:s', '%U' => 'W', '%u' => 'W', '%V' => 'W', '%v' => 'W', '%W' => 'l', '%w' => 'w', '%X' => 'Y', '%x' => 'o', '%Y' => 'Y', '%y' => 'y', );
    $t = strtotime($date);
    $format = strtr($format, $mysql_php_dateformats);
    $output =  date($format, $t);
    return $output;
  }
  public function date_add($date, $interval) {
    $interval = $this->deriveInterval($interval);
    switch (strtolower($date)) {
      case "curdate()":
        $objDate = new Datetime($this->curdate());
        $objDate->add(new DateInterval($interval));
        $returnval = $objDate->format("Y-m-d");
        break;
      case "now()":
        $objDate = new Datetime($this->now());
        $objDate->add(new DateInterval($interval));
        $returnval = $objDate->format("Y-m-d H:i:s");
        break;
      default:
        $objDate = new Datetime($date);
        $objDate->add(new DateInterval($interval));
        $returnval = $objDate->format("Y-m-d H:i:s");
    }
    return $returnval;
  }
  public function date_sub($date, $interval) {
    $interval = $this->deriveInterval($interval);
    switch (strtolower($date)) {
      case "curdate()":
        $objDate = new Datetime($this->curdate());
        $objDate->sub(new DateInterval($interval));
        $returnval = $objDate->format("Y-m-d");
        break;
      case "now()":
        $objDate = new Datetime($this->now());
        $objDate->sub(new DateInterval($interval));
        $returnval = $objDate->format("Y-m-d H:i:s");
        break;
      default:
        $objDate = new Datetime($date);
        $objDate->sub(new DateInterval($interval));
        $returnval = $objDate->format("Y-m-d H:i:s");
    }
    return $returnval;
  }
  private function deriveInterval($interval) {
    $interval = trim(substr(trim($interval), 8));
    $parts = explode(' ', $interval);
    foreach ($parts as $part) {
      if (!empty($part)) {
        $_parts[] = $part;
      }
    }
    $type = strtolower(end($_parts));
    switch ($type) {
      case "second": $unit = 'S'; return 'PT' . $_parts[0] . $unit; break;
      case "minute": $unit = 'M'; return 'PT' . $_parts[0] . $unit; break;
      case "hour":   $unit = 'H'; return 'PT' . $_parts[0] . $unit; break;
      case "day":    $unit = 'D'; return 'P'  . $_parts[0] . $unit; break;
      case "week":   $unit = 'W'; return 'P'  . $_parts[0] . $unit; break;
      case "month":  $unit = 'M'; return 'P'  . $_parts[0] . $unit; break;
      case "year":   $unit = 'Y'; return 'P'  . $_parts[0] . $unit; break;
      case "minute_second":
        list($minutes, $seconds) = explode(':', $_parts[0]);
        return 'PT' . $minutes . 'M' . $seconds . 'S';
        break;
      case "hour_second":
        list($hours, $minutes, $seconds) = explode (':', $_parts[0]);
        return 'PT' . $hours . 'H' . $minutes . 'M' . $seconds . 'S';
        break;
      case "hour_minute":
        list($hours, $minutes) = explode (':', $_parts[0]);
        return 'PT' . $hours . 'H' . $minutes . 'M';
        break;
      case "day_second":
        $days = intval($_parts[0]);
        list($hours, $minutes, $seconds) = explode (':', $_parts[1]);
        return 'P' . $days . 'D' . 'T' . $hours . 'H' . $minutes . 'M' . $seconds . 'S';
        break;
      case "day_minute":
        $days = intval($_parts[0]);
        list($hours, $minutes) = explode(':', $parts[1]);
        return 'P' . $days . 'D' . 'T' . $hours . 'H' . $minutes . 'M';
        break;
      case "day_hour":
        $days = intval($_parts[0]);
        $hours = intval($_parts[1]);
        return 'P' . $days . 'D' . 'T' . $hours . 'H';
        break;
      case "year_month":
        list($years, $months) = explode ('-', $_parts[0]);
        return 'P' . $years . 'Y' . $months . 'M';
        break;
    }
  }

  public function date($date){
    return date("Y-m-d", strtotime($date));
  }

  public function isnull($field){
    return is_null($field);
  }

  public function _if($expression, $true, $false){
    return ($expression == true) ? $true : $false;
  }

  public function regexp($field, $pattern){
    $pattern = str_replace('/', '\/', $pattern);
    $pattern = "/" . $pattern ."/i";
    return preg_match ($pattern, $field);
  }

  public function concat() {
    $returnValue = "";
    $argsNum = func_num_args();
    $argsList = func_get_args();
    for ($i = 0; $i < $argsNum; $i++) {
      if (is_null($argsList[$i])) {
        return null;
      }
      $returnValue .= $argsList[$i];
    }
    return $returnValue;
  }

  public function field() {
    $numArgs = func_num_args();
    if ($numArgs < 2 or is_null(func_get_arg(0))) {
      return null;
    }
    $arr = func_get_args();
    $searchString = strtolower(array_shift($arr));
    for ($i = 0; $i < $numArgs-1; $i++) {
      if ($searchString === strtolower($arr[$i])) return $i + 1;
    }
    return null;
  }

  public function log() {
    $numArgs = func_num_args();
    if ($numArgs == 1) {
      $arg1 = func_get_arg(0);
      return log($arg1);
    } else if ($numArgs == 2) {
      $arg1 = func_get_arg(0);
      $arg2 = func_get_arg(1);
      return log($arg1)/log($arg2);
    } else {
      return false;
    }
  }

  public function least() {
    $arr = func_get_args();
    return min($arr);
  }
  
  public function replace($haystack, $needle, $replace) {
    return str_replace($needle, $replace, $haystack);
  }
  
  /**
   * These two functions are meaningless in SQLite
   * So we return meaningless statement and do nothing
   * @param string $name
   * @param integer $timeout
   * @return string
   */
  public function get_lock($name, $timeout) {
    return '1=1';
  }
  public function release_lock($name) {
    return '1=1';
  }
  
  /**
   * MySQL aliases for upper and lower functions
   * @param $string
   * @return string
   */
  public function ucase($string) {
    return "upper($string)";
  }
  public function lcase($string) {
    return "lower($string)";
  }
  
  /**
   * MySQL aliases for INET_NTOA and INET_ATON functions
   * @param unsigned integer, string respectively
   * @return string, unsigned integer respectively
   */
  public function inet_ntoa($num) {
    return long2ip($num);
  }
  public function inet_aton($addr) {
    $int_data = ip2long($addr);
    $unsigned_int_data = sprintf('%u', $address);
    return $unsigned_int_data;
  }
  
  /**
   * MySQL aliase for DATEDIFF function
   * @param string, string
   * @return string
   */
  public function datediff($start, $end) {
    /* PHP 5.3.2 has a serious bug in DateTime::diff()
     * see https://bugs.php.net/bug.php?id=51184
     */
    if (version_compare(PHP_VERSION, '5.3.2', '==')) {
      $start_date = strtotime($start);
      $end_date   = strtotime($end);
      $interval = floor(($start_date - $end_date)/(3600*24));
      return $interval;
    } else {
      $start_date = new DateTime($start);
      $end_date = new DateTime($end);
      $interval = $end_date->diff($start_date, false);
      return $interval->format('%r%a');
    }
  }
  /**
   * emulates MySQL LOCATE() function
   */
  public function locate($substr, $str, $pos = 0) {
  	if (!extension_loaded('mbstring')) {
  		if (($val = stros($str, $substr, $pos)) !== false) {
  			return $val + 1;
  		} else {
  			return 0;
  		}
  	} else {
	  	if (($val = mb_strpos($str, $substr, $pos)) !== false) {
	  		return $val + 1;
	  	} else {
	  		return 0;
	  	}
  	}
  }
	/**
	 * 
	 */
	public function version() {
    global $required_mysql_version;
    return $required_mysql_version;
	}
}
?>