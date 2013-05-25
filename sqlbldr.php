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
	private $table = false;
	private $columns = false;
	private $values = false;
	private $join = false;
	private $where = false;
	private $order = false;
	private $sql = false;

	// Static instantiations
	static $select;
	static $update;
	static $insert;
	static $delete;
	
	// Construct
	public function __construct() {
		$this->connect($this->dbhost, $this->dbuser, $this->dbpass, $this->db);
		$this->table = $this->columns = $this->values = $this->where = $this->order = $this->sql = false;
	}
	
	// Select instance
	public static function select($arg=false) {
		// Setup or reset instance
		$c = __CLASS__;
		self::$select = new $c;

		if(is_array($arg) || $arg == false) {
			// Array input (better on resources)
			self::$select->columns = (!$arg) ? "*" : '`' . implode('`, `', $arg) . '`';
		} else {
			// Infinite comma delimited string input (uses more resources)
			$args = func_get_args();
			self::$select->columns = (is_array($args) && count($args) > 0) ? implode(', ', $args) : "*";
		}
		self::$select->sql = "SELECT " . self::$select->columns;
		return self::$select;
	}

	// Update instance
	public static function update($arg) {
		// Setup or reset instance
		$c = __CLASS__;
		self::$update = new $c;

		self::$update->table = "`$arg`";
		self::$update->sql = "UPDATE " . self::$update->table;

		return self::$update;
	}

	// Insert instance
	public static function insert($table=false, $columns=false) {
		// Setup or reset instance
		$c = __CLASS__;
		self::$insert = new $c;

		if(is_array($columns) && count($columns) > 0) {
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
	public static function delete($arg=false) {
		$c = __CLASS__;
		self::$delete = new $c;
		self::$delete->table = $arg ? "`$arg`" : false;
		self::$delete->sql = "DELETE FROM " . self::$delete->table;
		return self::$delete;
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
		if(self::$select->columns) {
			return self::$select;
		} else if(self::$insert->columns) {
			return self::$insert;
		}
	}
    
	// Plain ol' query
	public function query($str) {
		if(is_object(self::$insert)) {
			mysql_query($str, $this->conn);
			return mysql_insert_id();
		} else {
			return mysql_query($str, $this->conn);
		}
	}

	// Get columns from table (select only)
	public function columns($args=false) {
		if($args) {
			$sql = "SHOW COLUMNS FROM " . (strstr($args, '`') ? $args : '`' . $args . '`');
			return $this->obj($sql);
		}
		return false;
	}

	// From tables (select only)
	public function from($args=false, $moreargs=false) {
		if(self::$select->columns) {
		  // Allow both array or comma delimited input (array uses less resources)
		  if(!$moreargs && !is_array($args)) $args = array($args);
			$args = $moreargs ? func_get_args() : $args;
			self::$select->table = (is_array($args) && count($args) > 0) ? implode(', ', $args) : false;
			self::$select->sql .= " FROM " . (strstr(self::$select->table, '`') ? self::$select->table : '`' . self::$select->table . '`');
		}
		return self::$select;
	}

	public function set($args) {
		if(self::$update->table && is_array($args) && count($args) > 0) {
			if(array_keys($args) !== range(0, count($args) - 1)) {
				// Associative array passed with values
				self::$update->sql .= ' SET';
				$c = '';
				foreach($args as $k=>$val) {
					$val = $this->sanitize($val);
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

	public function values($args=false) {
		if(self::$insert->table) {
			// Insert
			if(is_array($args)) {
				// Array of values
				if(array_keys($args) !== range(0, count($args) - 1)) {
					// Associative array passed ('columnName' => 'value')
					if(self::$insert->columns) {
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
			return self::$insert;
		}
	}

	public function where($str=false, $operand=false, $condition=false) {
		// Initialize temp variables for string building
		$tmpwhere = $tmpsql = '';
		if($str && $operand && $condition) {
			// Start where
			if(is_object(self::$select) || is_object(self::$update) || is_object(self::$delete)) {
				$tmpwhere .= "WHERE `$str` $operand ";
				$tmpsql .= " WHERE `$str` $operand ";
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
		if(is_object(self::$select)) {
			// Select
			self::$select->where = $tmpwhere;
			self::$select->sql .= $tmpsql;
			return self::$select;
		} else if(is_object(self::$insert)) {
			// Insert
			self::$insert->where = $tmpwhere;
			self::$insert->sql .= $tmpsql;
			return self::$insert;
		} else if(is_object(self::$update)) {
			// Update
			self::$update->where = $tmpwhere;
			self::$update->sql .= $tmpsql;
			return self::$update;
		} else if(is_object(self::$delete)) {
			// Delete
			self::$delete->where = $tmpwhere;
			self::$delete->sql .= $tmpsql;
			return self::$delete;
		}
	}

	public function andwhere($str=false, $operand=false, $condition=false) {
		// Initialize temp variables for string building
		$tmpwhere = $tmpsql = '';
		if($str && $operand && $condition) {
			// Start where
			if(is_object(self::$select) || is_object(self::$update) || is_object(self::$delete)) {
				$tmpwhere .= "AND `$str` $operand ";
				$tmpsql .= " AND `$str` $operand ";
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
		if(is_object(self::$select)) {
			// Select
			self::$select->where = $tmpwhere;
			self::$select->sql .= $tmpsql;
			return self::$select;
		} else if(is_object(self::$insert)) {
			// Insert
			self::$insert->where = $tmpwhere;
			self::$insert->sql .= $tmpsql;
			return self::$insert;
		} else if(is_object(self::$update)) {
			// Update
			self::$update->where = $tmpwhere;
			self::$update->sql .= $tmpsql;
			return self::$update;
		} else if(is_object(self::$delete)) {
			// Delete
			self::$delete->where = $tmpwhere;
			self::$delete->sql .= $tmpsql;
			return self::$delete;
		}
	}
	
	// Join
	public function join($table, $direction=false) {
		if(self::$select->columns && self::$select->table && $table) {
			self::$select->sql .= ($direction ? strtoupper($direction) : '') . " JOIN " . (strstr('`', $table) ? $table : "`$table`");
			self::$select->join = ($direction ? strtoupper($direction) : '') . " JOIN " . (strstr('`', $table) ? $table : "`$table`");
		} else {
			$this->log .= "Error: Must have select columns, select table, and join table set for join()\n";
		}
		return self::$select;
	}
	
	// On (required after join)
	public function on($col1, $operand, $col2) {
		if(self::$select->join) {
			$col1array = explode('.', preg_replace('/`/', '', $col1));
			$col2array = explode('.', preg_replace('/`/', '', $col2));
			$col1 = '`' . $col1array[0] . '`.`' . $col1array[1] . '`';
			$col2 = '`' . $col2array[0] . '`.`' . $col2array[1] . '`';
			self::$select->sql .= " ON $col1 $operand $col2";
			self::$select->join = " ON $col1 $operand $col2";
		} else {
			$this->log .= "Error: on() requires a join()\n";
		}
		return self::$select;
	}

	public function order($cols=false, $direction=false) {
		if(self::$select->columns && self::$select->table) {
			if(is_array($cols) && count($cols) > 0) {
				self::$select->order = (strstr($cols[0], '`')) ? implode(', ', $cols) : '`' . implode('`, `', $cols) . '`';
			}
			self::$select->sql .= " ORDER BY " . self::$select->order . " " . (isset($direction) && strlen($direction) > 0 ? $direction : "ASC");
		}
		return self::$select;
	}

	// This function or run() below are required after insert, update, and delete commands
	public function execute() {
		if(is_object(self::$insert) && self::$insert->columns && self::$insert->values) {
			return $this->query(self::$insert->sql);
		} else if(is_object(self::$update) && self::$update->columns) {
			return $this->query(self::$update->sql);
		} else if(is_object(self::$delete)) {
			return $this->query(self::$delete->sql);
		} 
	}

	// This function or execute() below are required after insert, update, and delete commands
	public function run() {
		if(is_object(self::$insert) && self::$insert->columns && self::$insert->values) {
			return $this->query(self::$insert->sql);
		} else if(is_object(self::$update) && self::$update->columns) {
			return $this->query(self::$update->sql);
		} else if(is_object(self::$delete)) {
			return $this->query(self::$delete->sql);
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
		if(self::$select->columns && self::$select->table) {
			$res = $this->query(self::$select->sql);
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
	public function asarray() {
		if(self::$select->columns && self::$select->table) {		
			$res = $this->query(self::$select->sql);
			$assoc = false;
			if($res && mysql_num_rows($res) > 0) {
				while($row = mysql_fetch_assoc($res)) {
					$assoc[] = $row;
				}
			}
			return $assoc;
		} else {
			return "SELECT {$this->select} FROM `{$this->table}`";
		}
	}

	// Export as .csv (must run this in the header before any information is printed to the browser)
	public function ascsv($fname=false) {
		if(self::$select->table && self::$select->columns) {
			// Initialize
			$csv = '';
			$fname = ($fname && strpos($fname, ".csv") === false) ? $fname .= ".csv" : 'output.csv';

			// Execute current select SQL and set associative array
			$res = $this->query(self::$select->sql);
			$assoc = false;
			if($res && mysql_num_rows($res)>0) {
				while($row = mysql_fetch_assoc($res)) {
					$assoc[] = $row;
				}
			}

			// Loop through results and build records and headings
			if(is_array($assoc) && count($assoc) > 0) {
				$heading = '"' . implode('","', array_keys($assoc[0])) . '"';
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
		if(self::$select->columns && self::$select->table) {
			$res = $this->query(self::$select->sql);
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