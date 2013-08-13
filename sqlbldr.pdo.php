<?php
/*
 *	Jeff Hays' BLDR class (now uses PDO!)
 */

class db extends PDO {

	// Default connection
  private $engine;
  private $host = 'localhost';
  private $db = 'test';
  private $user = 'root';
  private $pass = 'root';

	// Private variables
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
	public static function i($engine=false){
		$c = __CLASS__;
		self::$i = new $c($engine ? $engine : 'mysql');

		return self::$i;
	}

	// Constructor
  public function __construct($engine){
    $this->engine = $engine;
    $dns = $this->engine.':dbname='.$this->db.';host='.$this->host;
    parent::__construct($dns, $this->user, $this->pass);
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
			self::$i->columns = strstr('`', $arg) ? $arg : "`$arg`";
		}
		self::$i->sql .= "SELECT " . self::$i->columns;
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
    $query = $this->prepare($str);
		try {
      $this->beginTransaction();
			$query->execute();
			$this->commit();
		} catch(PDOException $e) {
			$this->rollback();
			$this->log('Error: ' . $e->getMessage());
		}
		unset($query);
		return self::$i;
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
					self::$i->sql .= "$c `$k` = $val";
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
				self::$i->sql .= " VALUES (" . implode(", ", self::$i->values) . ")";
			} else {
				// Comma delimited list of values (slower)
				$args = func_get_args();
				self::$i->values = (is_array($args) && count($args) > 0) ? $this->sanitize($args) : false;
				self::$i->sql .= " VALUES (" . implode(", ", self::$i->values) . ")";
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
								$tmpwhere .= (is_numeric($c) ? $c : $this->sanitize($c)) . ($k == count($condition) - 1 ? '' : ', ');
								$tmpsql .= (is_numeric($c) ? $c : $this->sanitize($c)) . ($k == count($condition) - 1 ? '' : ', ');
							}
							$tmpwhere .= ")";
							$tmpsql .= ")";
						}
						break;
					case 'LIKE':
					case 'NOT LIKE':
						// LIKE and NOT LIKE
						$tmpwhere .= strstr($condition, '%') ? $this->sanitize($condition) : $this->sanitize("%".$condition."%");
						$tmpsql .= strstr($condition, '%') ? $this->sanitize($condition) : $this->sanitize("%".$condition."%");
						break;
					default:
						// Other operators
						$condition = strstr($condition, '.') && strstr($condition, '`') ? explode('.', $condition) : $condition;
						if(is_array($condition)){
							$condition[0] = strstr($condition[0], '`') ? $condition[0] : '`'.$condition[0].'`';
							$condition[1] = strstr($condition[1], '`') ? $condition[1] : '`'.$condition[1].'`';
							$condition = implode('.', $condition);
						}
						$tmpwhere .= (is_numeric($condition) || strstr($condition, '`') ? $condition : $this->sanitize($condition));
						$tmpsql .= (is_numeric($condition) || strstr($condition, '`') ? $condition : $this->sanitize($condition));
						break;
				}
			}
		} else if($str && $operand) {
			// String and operand passed but no condition
			$str = strstr($str, '`') ? $str : "`$str`";
		if($this->columns && $this->table) {
				$tmpwhere .= "WHERE $str $operand";
				$tmpsql .= " WHERE $str $operand";
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
								$tmpwhere .= (is_numeric($c) ? $c : $this->sanitize($c)) . ($k == count($condition) - 1 ? '' : ', ');
								$tmpsql .= (is_numeric($c) ? $c : $this->sanitize($c)) . ($k == count($condition) - 1 ? '' : ', ');
							}
							$tmpwhere .= ")";
							$tmpsql .= ")";
						}
						break;
					case 'LIKE':
					case 'NOT LIKE':
						// LIKE and NOT LIKE
						$tmpwhere .= strstr($condition, '%') ? $this->sanitize($condition) : $this->sanitize("%".$condition."%");
						$tmpsql .= strstr($condition, '%') ? $this->sanitize($condition) : $this->sanitize("%".$condition."%");
						break;
					default:
						// Other operators
						$condition = strstr($condition, '.') && strstr($condition, '`') ? explode('.', $condition) : $condition;
						if(is_array($condition)){
							$condition[0] = strstr($condition[0], '`') ? $condition[0] : '`'.$condition[0].'`';
							$condition[1] = strstr($condition[1], '`') ? $condition[1] : '`'.$condition[1].'`';
							$condition = implode('.', $condition);
						}
						$tmpwhere .= (is_numeric($condition) || strstr($condition, '`') ? $condition : $this->sanitize($condition));
						$tmpsql .= (is_numeric($condition) || strstr($condition, '`') ? $condition : $this->sanitize($condition));
						break;
				}
			}
		} else if($str && $operand) {
			// String and operand passed but no condition
			$str = strstr($str, '`') ? $str : "`$str`";
		if($this->columns && $this->table) {
				$tmpwhere .= "AND $str $operand";
				$tmpsql .= " AND $str $operand";
			}
		} else if($str) {
			// Literal string was passed in where()
			if($this->columns && $this->table) {
				$tmpwhere .= "AND $str";
				$tmpsql .= " AND $str";
			}
		}
		// Return
		self::$i->where = $tmpwhere;
		self::$i->sql .= $tmpsql;
		return self::$i;
	}
	
	public function orwhere($str=false, $operand=false, $condition=null) {
		// Initialize temp variables for string building
		$tmpwhere = $tmpsql = '';
		if($str && $operand && $condition != null) {
			$str = strstr($str, '`') ? $str : "`$str`";
			// Start where
			if(is_object(self::$i)) {
				$tmpwhere .= "OR $str $operand ";
				$tmpsql .= " OR $str $operand ";
				switch(strtoupper($operand)) {
					case 'IN':
					case 'NOT IN':
						// IN and NOT IN
						if(is_array($condition)) {
							$tmpwhere .= "(";
							$tmpsql .= "(";
							foreach($condition as $k=>$c) {
								$tmpwhere .= (is_numeric($c) ? $c : $this->sanitize($c)) . ($k == count($condition) - 1 ? '' : ', ');
								$tmpsql .= (is_numeric($c) ? $c : $this->sanitize($c)) . ($k == count($condition) - 1 ? '' : ', ');
							}
							$tmpwhere .= ")";
							$tmpsql .= ")";
						}
						break;
					case 'LIKE':
					case 'NOT LIKE':
						// LIKE and NOT LIKE
						$tmpwhere .= strstr($condition, '%') ? $this->sanitize($condition) : $this->sanitize("%".$condition."%");
						$tmpsql .= strstr($condition, '%') ? $this->sanitize($condition) : $this->sanitize("%".$condition."%");
						break;
					default:
						// Other operators
						$condition = strstr($condition, '.') && strstr($condition, '`') ? explode('.', $condition) : $condition;
						if(is_array($condition)){
							$condition[0] = strstr($condition[0], '`') ? $condition[0] : '`'.$condition[0].'`';
							$condition[1] = strstr($condition[1], '`') ? $condition[1] : '`'.$condition[1].'`';
							$condition = implode('.', $condition);
						}
						$tmpwhere .= (is_numeric($condition) || strstr($condition, '`') ? $condition : $this->sanitize($condition));
						$tmpsql .= (is_numeric($condition) || strstr($condition, '`') ? $condition : $this->sanitize($condition));
						break;
				}
			}
		} else if($str && $operand) {
			// String and operand passed but no condition
			$str = strstr($str, '`') ? $str : "`$str`";
		if($this->columns && $this->table) {
				$tmpwhere .= "OR $str $operand";
				$tmpsql .= " OR $str $operand";
			}
		} else if($str) {
			// Literal string was passed in where()
			if($this->columns && $this->table) {
				$tmpwhere .= "OR $str";
				$tmpsql .= " OR $str";
			}
		}
		// Return
		self::$i->where = $tmpwhere;
		self::$i->sql .= $tmpsql;
		return self::$i;
	}
	
	// Add a ( in the query
	public function open($type=false) {
		switch(strtoupper(preg_replace('/ /', '', $type))) {
			case 'AND':
				self::$i->sql .= ' AND';
				break;
			case 'OR':
				self::$i->sql .= ' OR';
				break;
			case 'IN':
				self::$i->sql .= ' IN';
				break;
			case 'NOTIN':
				self::$i->sql .= ' NOT IN';
				break;
		}
		self::$i->sql .= ' (';
		return self::$i;
	}

	// Add a ) in the query
	public function close() {
		self::$i->sql .= ')';
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
		return self::$i;
	}

	// This function or execute() below are required after insert, update, and delete commands
	public function run() {
		return is_object(self::$i) ? $this->query(self::$i->sql) : false;
	}

	// This function is just an alias for the run() function above
	public function execute() {
		return is_object(self::$i) ? $this->query(self::$i->sql) : false;
	}

	// Export as object
	public function asobject($sql=false) {
		// Check if SQL is passed or if asobject() is being used as a chained method
		$sql = $sql ? $sql : self::$i->sql;
		// Check if SQL builder object is passed instead of string
		$sql = is_object($sql) ? $sql->sql : $sql;
		$query = $this->prepare($sql);
		$query->execute();
		$result = array();
		while($row = $query->fetchObject())	$result[] = $row;
		return $result;
	}

	// Export as array
	public function asarray($sql=false) {
		$sql = $sql ? $sql : self::$i->sql;
		$query = $this->prepare($sql);
		$query->execute();
		return $query->fetchAll(PDO::FETCH_ASSOC);
	}

	// Export as .csv (must run this in the header before any information is printed to the browser)
	public function ascsv($fname=false, $heading=false) {
		if(self::$i->table && self::$i->columns) {
			// Initialize
			$csv = '';
			$fname = ($fname && strpos($fname, ".csv") === false) ? $fname . ".csv" : 'output.csv';

			// Execute current select SQL and set associative array
			$result = $this->asarray();
			$assoc = false;
			if(is_array($result) && count($result)){
				$assoc = array();
				foreach($result as $r){
					$assoc[] = $r;
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

	// Return first row (or # of passed index)
	public function row($index=false) {
		if(self::$i->columns && self::$i->table) {
			$obj = $this->asobject();
			return $obj[$index ? $index : 0];
		}
	}

	// Return # of rows
	public function rows() {
		if(self::$i->querytype == 'SELECT' && self::$i->columns && self::$i->table) {
			$query = $this->prepare(self::$i->sql);
			$query->execute();
			return $query->rowCount();
		}
	}

	// Check if a db exists
	public function isdb($db) {
		$query = @$this->prepare("SHOW DATABASES LIKE '$db'");
		$query->execute();
		return $query->rowCount() == 1 ? true : false;
	}

	// Check if a table exists
	public function istable($table) {
		$query = @$this->prepare("SHOW TABLES FROM `{$this->db}` LIKE '$table'");
		$query->execute();
		return $query->rowCount() == 1 ? true : false;
	}

	// Debug method
	public function debug($stuff=false, $die=true) {
		echo '<pre>';
		print_r($stuff ? $stuff : self::$i);
		echo '</pre>';
		if($die) die();
	}
	
// Aliases

	// Run query and return associative array
	public function assoc($str) {
		return $this->asarray($str);
	}
	
	// Run query and return array of objects
	public function obj($str) {
		return $this->asobject($str);
	}
	
	// Sanitize method
	public function sanitize($in) {
		if(is_array($in) && count($in)) {
			foreach($in as $k => $v) $in[$k] = $this->quote($v);
		} else {
			return $this->quote($in);
		}
		return $in;
	}
}