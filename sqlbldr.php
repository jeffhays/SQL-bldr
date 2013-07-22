<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Jeff Hays' BLDR class <3

class db extends stdClass {
	// Default credentials
	private $dbhost = "localhost";
	private $db = "test";
	private $dbuser = "root";
	private $dbpass = "root";

	// Private variables
	private $conn;
	private $querytype = false;
	private $table = false;
	private $columns = false;
	private $values = false;
	private $join = false;
	private $where = false;
	private $order = false;
	private $limit = false;
	private $sql = false;

	// Static instance
	static $i;
	
	// Setup or reset instance
	public static function i(){
		$c = __CLASS__;
		self::$i = new $c;
		
		return self::$i;
	}

	// Construct
	public function __construct() {
		$this->connect($this->dbhost, $this->dbuser, $this->dbpass, $this->db);
		$this->table = $this->columns = $this->values = $this->where = $this->order = $this->sql = false;
	}

	// Connect
	public function connect($dbhost=false, $dbuser=false, $dbpass=false, $db=false) {
		if(!$dbhost) $dbhost = $this->dbhost;
		if(!$dbuser) $dbuser = $this->dbuser;
		if(!$dbpass) $dbpass = $this->dbpass;
		if(!$db) $db = $this->db;
		if($dbhost && $dbuser && $dbpass && $db) {
			$this->conn = mysql_connect($dbhost, $dbuser, $dbpass);
			if(!$this->conn) die("Could not connect to $dbhost");
			$this->log .= "Connected Successfully!\n";
			try {
				mysql_select_db($db);
			} catch(exception $err) {
				echo $err;
			}
			$this->log .= "Successfully selected database: $db\n";
		}
	}

	// Disconnect
	public function disconnect() {
		if($this->conn) {
			if(@mysql_close($this->conn)) {
				return true;
			} else {
				return false;
			}
		}
	}

	// Change DB (with same creds)
	public function setdb($db) {
		if($this->isdb($db)) {
			try {
				mysql_select_db($db);
			} catch(exception $err) {
				echo $err;
			}
			$this->log .= "Successfully selected database: $db\n";
		}
		if(self::$i->columns) {
			return self::$i;
		} else if(self::$i->columns) {
			return self::$i;
		}
	}
	
	// Select
	public function select($arg=false) {
		self::$i->querytype = 'SELECT';
		if(is_array($arg) || $arg == false) {
			// Array input (better on resources)
			if($arg) {
				self::$i->columns = array();
				foreach($arg as $a) self::$i->columns[] = strstr('`', $a) ? $a : "`$a`";
			} else {
				self::$i->columns = '*';				
			}
		} else {
			// They passed a string so let's just use what they passed
			self::$i->columns = $arg;
		}
		self::$i->sql = "SELECT " . self::$i->columns;
		return self::$i;
	}
	
	// Update
	public function update($arg) {
		self::$i->querytype = 'UPDATE';
		self::$i->table = "`$arg`";
		self::$i->sql = "UPDATE " . self::$i->table;

		return self::$i;
	}

	// Insert
	public function insert($table=false, $columns=false) {
		self::$i->querytype = 'INSERT';
		if(is_array(self::$i->columns)) self::$i->columns = false;
		if(is_array($columns) && count($columns) > 0) {
			// Array of columns passed
			self::$i->table = $table;
			self::$i->columns = $columns;
			self::$i->sql = "INSERT INTO `" . self::$i->table . "` (`" . implode("`,`", self::$i->columns) . "`)";
		} else {
			// No columns passed (must be passing associative array in values())
			self::$i->table = $table;
			self::$i->sql = "INSERT INTO `" . self::$i->table . "`";
		}
		return self::$i;
	}

	// Delete
	public function delete($arg=false) {
		self::$i->querytype = 'DELETE';
		self::$i->table = $arg ? "`$arg`" : false;
		self::$i->sql = "DELETE FROM " . self::$i->table;
		return self::$i;
	}
    
	// Query
	public function query($str=false) {
		if(is_object(self::$i) && self::$i->querytype == 'INSERT' && $str) {
			// Insert
			mysql_query($str, $this->conn);
			return mysql_insert_id();
		} else if(is_object(self::$i) && $str) {
			// Everything else
			return mysql_query($str, $this->conn);
		}
	}

	// Distinct
	public function distinct() {
		self::$i->sql = str_replace('SELECT', 'SELECT DISTINCT', self::$i->sql);
		return self::$i;
	}

	// Show columns from table
	public function columns($args=false) {
		if($args) {
			$sql = "SHOW COLUMNS FROM " . (strstr($args, '`') ? $args : '`' . $args . '`');
			return $this->obj($sql);
		}
		return false;
	}

	// From tables (select only)
	public function from($args=false, $moreargs=false) {
		if(self::$i->querytype == 'SELECT' && self::$i->columns) {
		  // Allow both array or comma delimited input (array uses less resources)
			if(is_array($args) && count($args) > 0) {
				self::$i->table = '`' . implode('`, `', $args) . '`';
				self::$i->sql .= " FROM `" . implode('`, `', $args) . "`";
			} else {
				self::$i->table = (strstr('`', $args) ? $args : "`$args`");
				self::$i->sql .= " FROM " . (strstr('`', $args) ? $args : "`$args`");
			}
		}
		return self::$i;
	}

	// Set values (update only)
	public function set($args=false) {
		if(self::$i->querytype == 'UPDATE' && self::$i->table && is_array($args) && count($args) > 0) {
			if(array_keys($args) !== range(0, count($args) - 1)) {
				// Associative array passed with values
				self::$i->sql .= ' SET';
				$c = '';
				foreach($args as $k=>$val) {
					$val = $this->sanitize($val);
					self::$i->columns[] = $k;
					self::$i->sql .= is_numeric($val) ? "$c `$k` = $val" : "$c `$k` = '$val'";
					$c = ',';
				}
			} else {
				$this->log .= "Must use associative array in set() method.\n";
			}
		}
		return self::$i;
	}

	// Insert values (insert only)
	public function values($args=false) {
		if($args && self::$i->querytype == 'INSERT' && self::$i->table) {
			// Insert
			if(is_array($args)) {
				// Array of values
				if(array_keys($args) !== range(0, count($args) - 1)) {
					// Associative array passed ('columnName' => 'value')
					if(self::$i->columns) {
						$this->log .= "Error: Insert columns already set - don't pass columns in insert() when using associative array in values()\n";
					} else {
						// Set columns
						self::$i->columns = array_keys($args);
						self::$i->sql .= ' (`' . implode('`, `', self::$i->columns) . '`)';
					}
				}
				// Set values
				self::$i->values = $this->sanitize(array_values($args));
				self::$i->sql .= " VALUES ('" . implode("', '", self::$i->values) . "')";
			} else {
				// Comma delimited list of values (slower)
				$args = func_get_args();
				self::$i->values = (is_array($args) && count($args) > 0) ? $this->sanitize($args) : false;
				self::$i->sql .= " VALUES ('" . implode("', '", self::$i->values) . "')";
			}
			return self::$i;
		}
	}

	public function where($str=false, $operand=false, $condition=null) {
		// Initialize temp variables for string building
		$tmpwhere = $tmpsql = '';
		if($str && $operand && $condition != null) {
			$str = strstr($str, '`') ? $str : "`$str`";
			// Start where
			if(is_object(self::$i)) {
				$tmpwhere .= "WHERE $str $operand ";
				$tmpsql .= " WHERE $str $operand ";
				switch(strtoupper($operand)) {
					case 'IN':
					case 'NOT IN':
						// IN and NOT IN
						if(is_array($condition)) {
							$tmpwhere .= "(";
							$tmpsql .= "(";
							foreach($condition as $k=>$c) {
								$tmpwhere .= (is_numeric($c) ? $c : "'" . $this->sanitize($c) . "'") . ($k == count($condition) - 1 ? '' : ', ');
								$tmpsql .= (is_numeric($c) ? $c : "'" . $this->sanitize($c) . "'") . ($k == count($condition) - 1 ? '' : ', ');
							}
							$tmpwhere .= ")";
							$tmpsql .= ")";
						}
						break;
					default:
						// Other operators
						$tmpwhere .= (is_numeric($condition) ? $condition : "'" . $this->sanitize($condition) . "'");
						$tmpsql .= (is_numeric($condition) ? $condition : "'" . $this->sanitize($condition) . "'");
						break;
				}
			}
		} else if($str) {
			// Literal string was passed in where()
			if($this->columns && $this->table) {
				$tmpwhere .= "WHERE $str";
				$tmpsql .= " WHERE $str";
			}
		}
		// Return
		self::$i->where = $tmpwhere;
		self::$i->sql .= $tmpsql;
		return self::$i;
	}

	public function andwhere($str=false, $operand=false, $condition=null) {
		// Initialize temp variables for string building
		$tmpwhere = $tmpsql = '';
		if($str && $operand && $condition != null) {
			$str = strstr($str, '`') ? $str : "`$str`";
			// Start where
			if(is_object(self::$i)) {
				$tmpwhere .= "AND $str $operand ";
				$tmpsql .= " AND $str $operand ";
				switch(strtoupper($operand)) {
					case 'IN':
					case 'NOT IN':
						// IN and NOT IN
						if(is_array($condition)) {
							$tmpwhere .= "(";
							$tmpsql .= "(";
							foreach($condition as $k=>$c) {
								$tmpwhere .= (is_numeric($c) ? $c : "'" . $this->sanitize($c) . "'") . ($k == count($condition) - 1 ? '' : ', ');
								$tmpsql .= (is_numeric($c) ? $c : "'" . $this->sanitize($c) . "'") . ($k == count($condition) - 1 ? '' : ', ');
							}
							$tmpwhere .= ")";
							$tmpsql .= ")";
						}
						break;
					default:
						// Other operators
						$tmpwhere .= (is_numeric($condition) ? $condition : "'" . $this->sanitize($condition) . "'");
						$tmpsql .= (is_numeric($condition) ? $condition : "'" . $this->sanitize($condition) . "'");
						break;
				}
			}
		} else if($str) {
			// Literal string was passed in where()
			if($this->columns && $this->table) {
				$tmpwhere .= "AND $str";
				$tmpsql .= " AND $str";
			}
		}
		// Return
		self::$i->where .= ' ' . $tmpwhere;
		self::$i->sql .= $tmpsql;
		return self::$i;
	}
	
	// Join
	public function join($table, $direction=false) {
		if(self::$i->columns && self::$i->table && $table) {
			self::$i->sql .= ($direction ? strtoupper($direction) : '') . " JOIN " . (strstr('`', $table) ? $table : "`$table`");
			self::$i->join = ($direction ? strtoupper($direction) : '') . " JOIN " . (strstr('`', $table) ? $table : "`$table`");
		} else {
			$this->log .= "Error: Must have select columns, select table, and join table set for join()\n";
		}
		return self::$i;
	}
	
	// On (required after join)
	public function on($col1, $operand, $col2) {
		if(self::$i->join) {
			$col1array = explode('.', preg_replace('/`/', '', $col1));
			$col2array = explode('.', preg_replace('/`/', '', $col2));
			$col1 = '`' . $col1array[0] . '`.`' . $col1array[1] . '`';
			$col2 = '`' . $col2array[0] . '`.`' . $col2array[1] . '`';
			self::$i->sql .= " ON $col1 $operand $col2";
			self::$i->join = " ON $col1 $operand $col2";
		} else {
			$this->log .= "Error: on() requires a join()\n";
		}
		return self::$i;
	}
	
	// Order
	public function order($cols=false, $direction=false) {
		if(self::$i->querytype == 'SELECT' && self::$i->columns && self::$i->table) {
			if(is_array($cols) && count($cols) > 0) {
				self::$i->order = strstr($cols[0], '`') ? implode(', ', $cols) : '`' . implode('`, `', $cols) . '`';
			} else if($cols) {
				self::$i->order = !strstr($cols, ',') ? '`' . $cols . '`' : $cols;
			}
			self::$i->sql .= " ORDER BY " . self::$i->order . " " . (isset($direction) && strlen($direction) > 0 ? $direction : "ASC");
		}
		return self::$i;
	}
	
	// Limit
	public function limit($limit) {
		if(self::$i->querytype == 'SELECT' && self::$i->columns && self::$i->table) {
			self::$i->limit = $limit;
			self::$i->sql .= " LIMIT $limit";
		}
	}

	// This function or execute() below are required after insert, update, and delete commands
	public function run() {
		if(is_object(self::$i) && self::$i->querytype == 'INSERT' && self::$i->columns && self::$i->values) {
			// Insert
			mysql_query(self::$i->sql, $this->conn);
			return mysql_insert_id();
		} else if(is_object(self::$i)) {
			// Other query types
			return $this->query(self::$i->sql);
		}
	}

	// This function is just an alias for the run() function above
	public function execute() {
		if(is_object(self::$i) && self::$i->querytype == 'INSERT' && self::$i->columns && self::$i->values) {
			// Insert
			mysql_query(self::$i->sql, $this->conn);
			return mysql_insert_id();
		} else if(is_object(self::$i)) {
			// Other query types
			return $this->query(self::$i->sql);
		}
	}

	// Function to clean SQL
	public function sanitize($args) {
		if(is_array($args) && count($args) > 0) {
			$sanitized = array();
			foreach($args as $arg) {
  			$sanitized[] = mysql_real_escape_string($arg);
			}
		} else {
			$sanitized = mysql_real_escape_string($args);
		}
		return $sanitized;
	}

	// Export as object
	public function asobject() {
		if(self::$i->columns && self::$i->table) {
			$res = $this->query(self::$i->sql);
			$obj = false;
			if($res && mysql_num_rows($res) > 0) {
				while($row = mysql_fetch_object($res)) {
					$obj[] = $row;
				}
			}
			return $obj;
		}
	}

	// Export as array
	public function asarray($keys=true) {
		if(self::$i->columns && self::$i->table) {
			$res = $this->query(self::$i->sql);
			$assoc = false;
			if($res && mysql_num_rows($res) > 0) {
				while($row = mysql_fetch_assoc($res)) {
					// Check to see if false is passed and there's only one column and return an array of values without keys, otherwise return the associative arrays as expected
					$assoc[] = (!$keys && ((is_array(self::$i->columns) && count(self::$i->columns) == 1) || self::$i->columns != '*')) ? $row[preg_replace('/`/', '', self::$i->columns)] : $row;
				}
			}
			return $assoc;
		} else {
			return "SELECT {$this->select} FROM `{$this->table}`";
		}
	}

	// Export as .csv (must run this in the header before any information is printed to the browser)
	public function ascsv($fname=false, $heading=false) {
		if(self::$i->table && self::$i->columns) {
			// Initialize
			$csv = '';
			$fname = ($fname && strpos($fname, ".csv") === false) ? $fname . ".csv" : 'output.csv';

			// Execute current select SQL and set associative array
			$res = $this->query(self::$i->sql);
			$assoc = false;
			if($res && mysql_num_rows($res)>0) {
				while($row = mysql_fetch_assoc($res)) {
					$assoc[] = $row;
				}
			}

			// Loop through results and build records and headings
			if(is_array($assoc) && count($assoc) > 0) {
				// Either set the heading to the column names or use whatever is passed in as $heading
				$heading = !$heading ? '"' . implode('","', array_keys($assoc[0])) . '"' : '"' . implode('","', $heading) . '"';
				foreach($assoc as $col=>$row) {
					foreach($row as $key=>$val) {
						$records[$col][] = $val;
					}
				}

				// Add heading and records to CSV output
				$csv .= $heading . "\r\n";
				foreach($records as $record) {
					foreach($record as $k=>$r) {
						$csv .= '"' . $r . '"' . ($k < count($record) - 1 ? ',' : '');
					}
					$csv .= "\r\n";
				}
				
				// Setup file headers and echo file contents as CSV
				header("Content-type: application/octet-stream");
				header("Content-Disposition: attachment; filename=\"$fname\"");
				echo $csv;
			}
		} else {
			$this->log .= "No table or columns selected\n";
		}
	}


// Global utility functions

	// Return # of rows
	public function rows() {
		if(self::$i->querytype == 'SELECT' && self::$i->columns && self::$i->table) {
			$res = $this->query(self::$i->sql);
			return mysql_num_rows($res);
		}
	}

	// Check if a db exists
	public function isdb($db) {
		$dbresult = @mysql_query("SHOW DATABASES LIKE '$db'");
		if($dbresult) {
			if(mysql_num_rows($dbresult) == 1) {
				return true;
			} else {
				return false;
			}
		}
	}

	// Check if a table exists
	public function istable($table) {
		$dbtables = @mysql_query("SHOW TABLES FROM '{$this->db}' LIKE '{$table}'");
		if($dbtables) {
			if(mysql_num_rows($dbtables) == 1) {
				return true;
			} else {
				return false;
			}
		}
		return false;
	}

	// Run query and return associative array
	public function assoc($str) {
		$res = $this->query($str);
		$assoc = false;
		if($res && mysql_num_rows($res)>0) {
			while($row = mysql_fetch_assoc($res)) {
				$assoc[] = $row;
			}
		}
		return $assoc;
	}
	// Run query and return array of objects
	public function obj($str) {
		$res = $this->query($str);
		$obj = false;
		if($res && mysql_num_rows($res) > 0) {
			while($row = mysql_fetch_object($res)) {
				$obj[] = $row;
			}
		}
		return $obj;
	}
}