<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Jeff Hays' BLDR class <3

class db extends stdClass{
	// Default credentials
	private $dbhost = "localhost";
	private $db = "test";
	private $dbuser = "root";
	private $dbpass = "root";

	// Private variables
	private $conn;
	private $table = false;
	private $columns = false;
	private $values = false;
	private $where = false;
	private $order = false;
	private $sql = false;

	// Static instantiations
	static $select;
	static $update;
	static $insert;
	static $delete;
	
	// Construct
	public function __construct(){
		$this->connect($this->dbhost, $this->dbuser, $this->dbpass, $this->db);
		$this->table = $this->columns = $this->values = $this->where = $this->order = $this->sql = false;
	}
	
	// Select instance
	public static function select($arg=false){
    	// Setup or reset instance
		$c = __CLASS__;
		self::$select = new $c;

		if (is_array($arg) || $arg == false){
		  	// Array input (better on resources)
  			self::$select->columns = (!$arg) ? "*" : implode(', ', $arg);
		} else {
			// Infinite input
			$args = func_get_args();
			self::$select->columns = (is_array($args) && count($args) > 0) ? implode(', ', $args) : "*";
		}
		self::$select->sql = "SELECT " . self::$select->columns;
		return self::$select;
	}

	// Update instance
	public static function update($arg){
		// Setup or reset instance
		$c = __CLASS__;
		self::$update = new $c;

		self::$update->table = "`$arg`";
		self::$update->sql = "UPDATE " . self::$update->table;

		return self::$update;
	}

	// Insert instance
	public static function insert($table=false, $columns=false){
		// Setup or reset instance
		$c = __CLASS__;
		self::$insert = new $c;

		if (is_array($columns) && count($columns) > 0){
			// Array of columns passed
			self::$insert->table = $table;
			self::$insert->columns = $columns;
			self::$insert->sql = "INSERT INTO `" . self::$insert->table . "` (`" . implode("`,`", self::$insert->columns) . "`)";
		} else {
			// No columns passed (must be passing associative array in values())
			self::$insert->table = $table;
			self::$insert->sql = "INSERT INTO `" . self::$insert->table . "`";
		}
		return self::$insert;
	}

	// Delete instance
	public static function delete($arg=false){
		$c = __CLASS__;
		self::$delete = new $c;
		self::$delete->table = $arg ? "`$arg`" : false;
		self::$delete->sql = "DELETE FROM " . self::$delete->table;
		return self::$delete;
	}
	
	// Connect
	public function connect($dbhost=false, $dbuser=false, $dbpass=false, $db=false){
		if (!$dbhost) $dbhost = $this->dbhost;
		if (!$dbuser) $dbuser = $this->dbuser;
		if (!$dbpass) $dbpass = $this->dbpass;
		if (!$db) $db = $this->db;
		if ($dbhost && $dbuser && $dbpass && $db){
			$this->conn = mysql_connect($dbhost, $dbuser, $dbpass);
			if (!$this->conn) die("Could not connect to $dbhost");
			$this->log .= "Connected Successfully!\n";
			try {
				mysql_select_db($db);
			} catch (exception $err) {
				echo $err;
			}
			$this->log .= "Successfully selected database: $db\n";
		}
	}

	// Disconnect
	public function disconnect(){
		if ($this->conn){
			if (@mysql_close($this->conn)) {
				return true;
			} else {
				return false;
			}
		}
	}

	// Change DB (with same creds)
	public function setdb($db){
		if ($this->isdb($db)){
			try {
				mysql_select_db($db);
			} catch (exception $err) {
				echo $err;
			}
			$this->log .= "Successfully selected database: $db\n";
		}
		if (self::$select->columns) {
			return self::$select;
		} elseif(self::$insert->columns) {
			return self::$insert;
		}
	}
    
	// Plain ol' query
	public function query($str){
    	return mysql_query($str, $this->conn);
	}

	// From tables (select only)
	public function from($args=false, $moreargs=false){
		if (self::$select->columns) {
		  // Allow both array or comma delimited input (array uses less resources)
		  if (!$moreargs && !is_array($args)) $args = array($args);
			$args = $moreargs ? func_get_args() : $args;
			self::$select->table = (is_array($args) && count($args) > 0) ? implode(', ', $args) : false;
			self::$select->sql .= " FROM " . (strstr(self::$select->table, '`') ? self::$select->table : '`' . self::$select->table . '`');
		}
		return self::$select;
	}

	public function set($args){
		if (self::$update->table && is_array($args) && count($args) > 0) {
			if (array_keys($args) !== range(0, count($args) - 1)) {
				// Associative array passed with values
				self::$update->sql .= ' SET';
				$c = '';
				foreach($args as $k=>$val){
					self::$update->columns[] = $k;
					self::$update->sql .= is_numeric($val) ? "$c `$k` = $val" : "$c `$k` = '$val'";
					$c = ',';
				}
			} else {
				$this->log .= "Must use associative array in set() method.\n";
			}
		}
		return self::$update;
	}

	public function values($args=false){
		if (self::$insert->table) {
			// Insert
			if (is_array($args)) {
				// Array of values
				if (array_keys($args) !== range(0, count($args) - 1)) {
					// Associative array passed ('columnName' => 'value')
					if (self::$insert->columns) {
						$this->log .= "Error: Insert columns already set - don't pass columns in insert() when using associative array in values()\n";
					} else {
						// Set columns
						self::$insert->columns = array_keys($args);
						self::$insert->sql .= ' (`' . implode('`, `', self::$insert->columns) . '`)';
					}
				}
				// Set values
					self::$insert->values = $this->sanitize(array_values($args));
					self::$insert->sql .= " VALUES ('" . implode("', '", self::$insert->values) . "')";
			} else {
				// Comma delimited list of values (slower)
				$args = func_get_args();
				self::$insert->values = (is_array($args) && count($args) > 0) ? $this->sanitize($args) : false;
				self::$insert->sql .= " VALUES ('" . implode("', '", self::$insert->values) . "')";
			}
			return $this->query(self::$insert->sql);
		}
	}

	public function where($str=false, $operand=false, $condition=false){
		if($str && $operand && $condition){
			// Start where
			if((is_object(self::$select) || is_object(self::$update) || is_object(self::$delete)) && $this->columns && $this->table){
				// Select
				$this->where = "WHERE `$str` $operand ";
				$this->sql .= " WHERE `$str` $operand ";
				switch(strtoupper($operand)){
					case 'IN':
					case 'NOT IN':
						if(is_array($condition)){
							// IN and NOT IN
							$this->where .= "(";
							$this->sql .= "(";
							foreach($condition as $k=>$c){
								$this->where .= (is_numeric($c) ? $c : "'" . $this->sanitize($c) . "'") . ($k == count($condition) - 1 ? '' : ', ');
								$this->sql .= (is_numeric($c) ? $c : "'" . $this->sanitize($c) . "'") . ($k == count($condition) - 1 ? '' : ', ');
							}
							$this->where .= ")";
							$this->sql .= ")";
						}
						break;
					default:
						// Other operators
						$this->where .= (is_numeric($condition) ? $condition : "'" . $this->sanitize($condition) . "'");
						$this->sql .= (is_numeric($condition) ? $condition : "'" . $this->sanitize($condition) . "'");
						break;
				}
			}
		}elseif($str){
			// Literal string was passed in where()
			if($this->columns && $this->table){
				$this->where = "WHERE $str";
				$this->sql .= " WHERE $str";
			}
		}
		// Return
		if(is_object(self::$select)){
			return self::$select;
		}elseif(is_object(self::$update)){
			return $this->query($this->sql);
		}elseif(is_object(self::$delete)){

		//return $this->query($this->sql);
		}
	}

	public function order($cols=false, $direction=false){
		if(self::$select->columns && self::$select->table){
			if(is_array($cols) && count($cols) > 0){
				self::$select->order = implode(', ', $cols);
			}
			self::$select->sql .= " ORDER BY " . self::$select->order . " " . (isset($direction) && strlen($direction) > 0 ? $direction : "ASC");
		}
		return self::$select;
	}

	public function execute(){
		if(is_object(self::$insert) && self::$insert->columns && self::$insert->values){
			return $this->query(self::$insert->sql);
		}
	}

	public function run(){
		if(is_object(self::$insert) && self::$insert->columns && self::$insert->values){
			return $this->query(self::$insert->sql);
		}
	}

	public function sanitize($args){
		if(is_array($args) && count($args) > 0){
			$sanitized = array();
			foreach($args as $arg){
	  			$sanitized[] = mysql_real_escape_string($arg);
			}
		}else{
			$sanitized = mysql_real_escape_string($args);
		}
		return $sanitized;
	}

	public function asobject(){
		if(self::$select->columns && self::$select->table){
			$res = $this->query(self::$select->sql);
			$obj = false;
			if($res && mysql_num_rows($res) > 0){
				while($row = mysql_fetch_object($res)){
					$obj[] = $row;
				}
			}
			return $obj;
		}
	}

	public function asarray(){
		if(self::$select->columns && self::$select->table){		
			$res = $this->query(self::$select->sql);
			$assoc = false;
			if($res && mysql_num_rows($res) > 0){
				while($row = mysql_fetch_assoc($res)){
					$assoc[] = $row;
				}
			}
			return $assoc;
		}else{
			return "SELECT {$this->select} FROM `{$this->table}`";
		}
	}

	public function isdb($db){
		$dbresult = @mysql_query("SHOW DATABASES LIKE '$db'");
		if($dbresult){
			if(mysql_num_rows($dbresult) == 1){
				return true;
			}else{
				return false;
			}
		}
	}

	public function istable($table){
		$dbtables = @mysql_query("SHOW TABLES FROM '{$this->db}' LIKE '{$table}'");
		if($dbtables){
			if(mysql_num_rows($dbtables) == 1){
				return true;
			}else{
				return false;
			}
		}
	}

// Legacy functions
/*
	public function insert($table, $arr){
		if(is_array($arr)){
			$cols = $vals = "(";
			foreach($arr as $k=>$y){
				$cols .= $k . ',';
				$vals .= (is_numeric($y)) ? $y . ',' : "'" . $y . "',";
			}
			$cols = substr($cols, 0, strlen($cols)-1) . ")";
			$vals = substr($vals, 0, strlen($vals)-1) . ")";
			$insert = $this->query("INSERT INTO `$table` $cols VALUES $vals");
			return ($insert) ? mysql_insert_id($this->conn) : false;
		}
	}
*/
    
/*
    public function update($table, $arr, $where=false){
    	//Setting up the where clause
    	$update = null;
    	if(is_array($where)){
    		$clause = "WHERE ";
    		foreach($where as $k=>$w) $clause .= $k . '=' . (!is_numeric($w) ? "'" . $w . "'," : $w . ',');
    		$clause = substr($clause, 0, strlen($clause)-1);
    	}else{
    		$clause = $where;
    	}
    	if(is_array($arr) && count($arr) > 0){
			foreach($arr as $k=>$y){
				$updates .= $k . '=' . "'" . $y . "',";
			}
			$updates = substr($updates, 0, strlen($updates) - 1);
    		return $this->query("UPDATE `$table` SET $updates $clause");
    	}else{
    		$this->log .= "db update function must use type array\n";
    	}
    }
*/

	// Run query and export as CSV
	public function csv($str, $fname){
		$csv = '';
		if(strpos($fname, ".csv") === false) $fname .= ".csv";
		$rows = $this->assoc($str);
		foreach($rows as $col=>$row){
			foreach($row as $key=>$val){
				if(!isset($heading[count($row)-1])) $heading[] = $key;
				$records[$col][] = $val;
			}
		}
		foreach($heading as $test=>$header) $csv .= "$header,";
		$csv .= "\r\n";
		foreach($records as $record){
			foreach($record as $r){
				$csv .= "\"$r\",";
			}
			$csv .= "\r\n";
		}
		//spit out the results to header
		header("Content-type: application/octet-stream");
		header("Content-Disposition: attachment; filename=\"$fname\"");
		echo $csv;
	}

	public function assoc($str){
		$res = $this->query($str);
		$assoc = false;
		if($res && mysql_num_rows($res)>0){
			while($row = mysql_fetch_assoc($res)){
				$assoc[] = $row;
			}
		}
		return $assoc;
	}
	// End legacy functions
}
