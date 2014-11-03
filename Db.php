<?php

/**
* @file Db.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Simple class for database connection handling
*/

namespace dophp;


/**
* Simple class for database connection handling
*/
class Db {

	/** PDO instance */
	protected $_pdo;
	
	/**
	* Starts a PDO connection to DB
	*
	* @param $dsn  string: DSN, PDO valid
	* @param $user string: Username to access DBMS
	* @param $pass string: Password to access DBMS
	*/
	public function __construct($dsn, $user, $pass) {
		$this->_pdo = new \PDO($dsn, $user, $pass);
		$this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->_pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
		$this->_pdo->exec('SET sql_mode = \'TRADITIONAL,STRICT_ALL_TABLES,NO_AUTO_VALUE_ON_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE\'');
		$this->_pdo->exec('SET NAMES utf8');
	}

	/**
	* Prepares a statement, executes it with given parameters and returns it
	*
	* @param $query string: The query to be executed
	* @param $params mixed: Array containing the parameters or single parameter
	*/
	public function run($query, $params=array()) {
		if( ! is_array($params) )
			$params = array($params);
		foreach($params as & $p)
			if( gettype($p) == 'boolean' ) { // PDO would convert false into null otherwise
				if( $p === true )
					$p = 1;
				elseif( $p === false )
					$p = 0;
			}
			elseif( $p instanceof Date )
				$p = $p->format('Y-m-d');
			elseif( $p instanceof Time )
				$p = $p->format('H:i:s');
			elseif( $p instanceof \DateTime )
				$p = $p->format('Y-m-d H:i:s');
		$st = $this->_pdo->prepare($query);
		$st->execute($params);
		return $st;
	}

	/**
	* Runs an INSERT statement from an associative array and returns ID of the
	* last auto_increment value
	*
	* @see buildInsUpdQuery
	*/
	public function insert($table, $params) {
		list($q,$p) = $this->buildInsUpdQuery('ins', $table, $params);

		$this->run($q, $p);
		return $this->_pdo->lastInsertId();
	}

	/**
	* Runs an UPDATE statement from an associative array
	*
	* @see buildInsUpdQuery
	* @see buildParams
	*/
	public function update($table, $params, $where) {
		list($s,$ps) = self::buildInsUpdQuery('upd', $table, $params);
		list($w,$pw) = self::buildParams($where, ' AND ');

		$q = "$s WHERE $w";
		$p = array_merge($ps, $pw);

		$this->run($q, $p);
	}

	/**
	* Runs a DELETE statement
	*
	* @see buildParams
	*/
	public function delete($table, $where) {
		list($w,$p) = self::buildParams($where, ' AND ');
		$q = "DELETE FROM `$table` WHERE $w";
		
		$this->run($q, $p);
	}

	/**
	* Runs FOUND_ROWS() and returns result
	*
	* @return int: Number of found rows
	*/
	public function foundRows() {
		$q = "SELECT FOUND_ROWS() AS `fr`";

		$res = $this->run($q)->fetch();
		return $res['fr'] !== null ? (int)$res['fr'] : null;
	}

	/**
	* Begins a transaction
	*
	* @see PDO::beginTransaction()
	*/
	public function beginTransaction() {
		$this->_pdo->beginTransaction();
	}

	/**
	* Commits a transaction
	*
	* @see PDO::commit()
	*/
	public function commit() {
		$this->_pdo->commit();
	}

	/**
	* Rolls back a transaction
	*
	* @see PDO::rollBack()
	*/
	public function rollBack() {
		$this->_pdo->rollBack();
	}

	/**
	* Builds a partial query suitable for insert or update in the format
	* "SET col1 = val1, col2 = val2, ..."
	*
	* @param $type string: The query type (ins, upd)
	* @param $table string: The name of the table
	* @see buildParams
	* @return array [query string, params]
	*/
	public static function buildInsUpdQuery($type, $table, $params) {
		switch( $type ) {
		case 'ins':
			$q = 'INSERT INTO';
			break;
		case 'upd':
			$q = 'UPDATE';
			break;
		default:
			throw new \Exception("Unknown type $type");
		}

		list($cols, $p) = self::buildParams($params);
		$q .= " `$table` SET $cols" ;
		return array($q, $p);
	}

	/**
	* Formats an associative array of parameters into a query string and an
	* associative array ready to be passed to pdo
	*
	* @param $params array: Associative array with parameters. Key is the name
	*                       of the column. If the data is an array, 2nd key is
	*                       the custom placeholder to use. By example:
	*                       'password' => [ '123456', 'SHA1(?)' ]
	* @param $glue string: The string to join the arguments, usually ', ' or ' AND '
	* @return array [query string, params array]
	*/
	public static function buildParams($params, $glue=', ') {
		if( ! $params )
			return array('', []);
		$cols = array();
		$vals = array();
		foreach( $params as $k => $v ) {
			$c = "`$k` = ";
			if( is_array($v) ) {
				$c .= str_replace('?', ":$k", $v[1]);
				if( $v[0] !== null )
					$vals[$k] = $v[0];
			} else {
				$c .= ":$k";
				$vals[$k] = $v;
			}
			$cols[] = $c;
		}
		return array(implode($glue, $cols), $vals);
	}

	/**
	* Builds a "limit" statement
	*
	* @param $limit mixed: If True, returns only first element,
	*                      if int, returns first $limit elements
	* @param $skip integer: Number of records to skip
	* @return string: the LIMIT statement
	*/
	public static function buildLimit($limit=null, $skip=0) {
		if( $skip || $limit )
			$lim = 'LIMIT ';
		else
			$lim = '';

		if( $skip )
			$lim .= (int)$skip . ',';

		if( $limit === true )
			$lim .= '1';
		elseif( $limit )
			$lim = (int)$limit;

		return $lim;
	}

}


/**
* Represents a table on the database for easy automated access
*/
class Table {

	/** Database table name, may be overridden in sub-class or passed by constructor */
	protected $_name = null;
	/** Database object instance, passed by constructor */
	protected $_db = null;
	/** Column definition cache, populated at runtime */
	protected $_cols = array();
	/** references cache, populated at runtime */
	protected $_refs = array();
	/** Primary key cache, populated at runtime */
	protected $_pk = array();

	/**
	* Creates the table object
	*
	* @param $db object: The Db instance
	* @param $name string: The table name, override the given one
	*/
	public function __construct($db, $name=null) {

		// Assign and check parameters
		$this->_db = $db;
		if( $name )
			$this->_name = $name;
		if( ! $this->_db instanceof Db )
			throw new \Exception('Db must be a valid dophp\Db instance');
		if( ! $this->_name || gettype($this->_name) !== 'string' )
			throw new \Exception('Unvalid table name');

		// Read and cache table structure
		$q = "
			SELECT
				`COLUMN_NAME`,
				`COLUMN_DEFAULT`,
				`IS_NULLABLE`,
				`DATA_TYPE`,
				`COLUMN_TYPE`,
				`CHARACTER_MAXIMUM_LENGTH`,
				`NUMERIC_PRECISION`,
				`NUMERIC_SCALE`,
				`COLUMN_KEY`,
				`EXTRA`
			FROM `information_schema`.`COLUMNS`
			WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ?
			ORDER BY `ORDINAL_POSITION`
		";
		foreach( $this->_db->run($q, array($this->_name))->fetchAll() as $c ) {
			if( isset($this->_cols['COLUMN_NAME']) )
				throw new \Exception("Duplicate definition found for column {$c['COLUMN_NAME']}");
			$this->_cols[$c['COLUMN_NAME']] = $c;
			if( $c['COLUMN_KEY'] == 'PRI' )
				$this->_pk[] = $c['COLUMN_NAME'];
		}

		// Read and cache references structure
		$q = "
			SELECT
				`CONSTRAINT_NAME`,
				`COLUMN_NAME`,
				`REFERENCED_TABLE_NAME`,
				`REFERENCED_COLUMN_NAME`
			FROM `information_schema`.`KEY_COLUMN_USAGE`
			WHERE `CONSTRAINT_SCHEMA` = DATABASE()
				AND `TABLE_SCHEMA` = DATABASE()
				AND `REFERENCED_TABLE_SCHEMA` = DATABASE()
				AND `TABLE_NAME` = ?
			ORDER BY `ORDINAL_POSITION`, `POSITION_IN_UNIQUE_CONSTRAINT`
		";
		foreach( $this->_db->run($q, array($this->_name))->fetchAll() as $c ) {
			if( isset($this->_refs['COLUMN_NAME']) )
				throw new \Exception("More than one reference detected for column {$c['COLUMN_NAME']}");
 			$this->_refs[$c['COLUMN_NAME']] = $c;
 		}

	}

	/**
	* Gets a record by PK
	*
	* @param $pk mixed The primary key, array if composite (associative or numeric)
	* @param $cols array Names of the columns. null to select all. true to
	*                    select only PKs.
	* @return The fetched row
	*/
	public function get($pk, $cols=null) {
		$pk = $this->parsePkArgs($pk);
		
		list($w, $p) = Db::buildParams($pk, ' AND ');
		$q = "
			SELECT
		" . $this->_buildColumList($cols) . "
			FROM `{$this->_name}`
			WHERE $w
		";

		return $this->cast($this->_db->run($q,$p)->fetch());
	}

	/**
	* Runs a select query
	*
	* @param $params array Null, Array passed to Where::Construct or Where instance
	* @param $cols array Names of the columns. null to select all. true to
	*                    select only PKs.
	* @param $limit mixed Numeric limit, boolean TRUE means 1. If array,
	*                     must contain two elements: first element is $limit,
	*                     second is $skip. If null, no limit.
	*                     See dophp\Db::buildLimit
	* @see dophp\Db::buildParams
	* @see dophp\Db::buildLimit
	* @return mixed The single fetched row or array(fetched rows, number of records found)
	*         every element is converted to the right data type
	*/
	public function select($params=null, $cols=null, $limit=null) {
		$q = "SELECT\n\t";
		$p = array();

		$q .= $this->_buildColumList($cols);

		$q .= "FROM `{$this->_name}`\n";

		if( ! $params instanceof Where )
			$params = new Where($params);

		if( $w = $params->getCondition() ) {
			$q .= "WHERE $w\n";
			$p = array_merge($p, $params->getParams());
		}

		if( is_array($limit) )
			list($limit, $skip) = $limit;
		else
			$skip = null;
		$q .= $this->_db->buildLimit($limit, $skip);
		$st = $this->_db->run($q, $p);
		if( $limit === true || $limit === 1 )
			return $this->cast($st->fetch());
		else
			return array($this->castMany($st->fetchAll()), $this->_db->foundRows());
	}

	/**
	* Runs an update query for a single record
	*
	* @param $pk   mixed: The primary key, array if composite (associative or numeric)
	* @param $data array: Associative array of <column>=><data> to update
	*/
	public function update($pk, $data) {
		$this->_db->update($this->_name, $data, $this->parsePkArgs($pk));
	}

	/**
	* Runs an insert query for a single record
	*
	* @param $data array: Associative array of <column>=><data> to insert
	* @return int: Last Insert ID
	*/
	public function insert($data) {
		return $this->_db->insert($this->_name, $data);
	}

	/**
	* Runs a delete query for a single record
	*
	* @param $pk mixed: The primary key, array if composite (associative or numeric)
	*/
	public function delete($pk) {
		$this->_db->delete($this->_name, $this->parsePkArgs($pk));
	}

	/**
	* Returns column type for a given column
	*
	* @param $col string: the column name
	* @return string: One of integer, boolean, double, Decimal, string, Date,
	*                 DateTime, Time
	*/
	public function getColumnType($col) {
		$dtype = strtoupper($this->_cols[$col]['DATA_TYPE']);
		$ctype = strtoupper($this->_cols[$col]['COLUMN_TYPE']);
			
		switch($dtype) {
		case 'SMALLINT':
		case 'MEDIUMINT':
		case 'INT':
		case 'INTEGER':
		case 'BIGINT':
			return 'integer';
		case 'BIT':
		case 'TINYINT':
		case 'BOOL':
		case 'BOOLEAN':
			if( $ctype == 'TINYINT(1)' )
				return 'boolean';
			return 'integer';
		case 'FLOAT':
		case 'DOUBLE':
			return 'double';
		case 'DECIMAL':
		case 'DEC':
			return 'Decimal';
		case 'CHAR':
		case 'VARCHAR':
		case 'BINARY':
		case 'VARBINARY':
		case 'TINYBLOB':
		case 'BLOB':
		case 'MEDIUMBLOB':
		case 'LONGBLOB':
		case 'TINYTEXT':
		case 'TEXT':
		case 'MEDIUMTEXT':
		case 'LONGTEXT':
		case 'ENUM':
			return 'string';
		case 'DATE':
			return 'Date';
		case 'DATETIME':
		case 'TIMESTAMP':
			return 'DateTime';
		case 'TIME':
			return 'Time';
		default:
			throw new \Exception("Unsupported column type $dtype");
		}
	}

	/**
	* Put every res element into the right type according to column definition
	*
	* @param $res array: Associative array representing a row
	* @return array: Associative array, with values correctly casted into the
	*                appropriate type
	*/
	public function cast($res) {
		// PDOStatement::fetch returns false when no results are found,
		// this is a bug in my opinion, so working around it
		if( $res === null || $res === false )
			return null;
		$ret = array();
		
		foreach( $res as $k => $v ) {

			if( $v !== null )
				switch($this->getColumnType($k)) {
				case 'integer':
					$v = (int)$v;
					break;
				case 'boolean':
					$v = $v && $v != -1 ? true : false;
					break;
				case 'double':
					$v = (double)$v;
					break;
				case 'Decimal':
					$v = new Decimal($v);
					break;
				case 'string':
					$v = (string)$v;
					break;
				case 'Date':
					$v = new Date($v);
					break;
				case 'DateTime':
					$v = new \DateTime($v);
					break;
				case 'Time':
					$v = new Time($v);
					break;
				default:
					throw new \Exception("Unsupported column type $dtype");
				}

			$ret[$k] = $v;
		}

		return $ret;
	}

	/**
	* Similar to cast, but expects an array of rows instead of a single one.
	* @see cast
	* @param $res array: Rows data
	* @return array: Casted rows data
	*/
	public function castMany($res) {
		$ret = array();
		foreach( $res as $i => $r )
			$ret[$i] = $this->cast($r);
		return $ret;
	}

	/**
	* Returns table's name
	*
	* @return string: This table's name
	*/
	public function getName() {
		return $this->_name;
	}

	/**
	* Returns the table's primary key
	*
	* @return array: List of fields composing the primary key
	*/
	public function getPk() {
		return $this->_pk;
	}

	/**
	* Returns the list of table's columns
	*
	* @return array: List of columns
	*/
	public function getCols() {
		return array_keys($this->_cols);
	}

	/**
	* Returns array of table's references
	*
	* @return array: Associative array of arrays ['col' => [0=>referenced table, 1=>referenced column], ...]
	*/
	public function getRefs() {
		$ret = array();
		foreach( $this->_refs as $k => $r )
			$ret[$k] = array($r['REFERENCED_TABLE_NAME'], $r['REFERENCED_COLUMN_NAME']);
		return $ret;
	}

	/**
	* Gets a primary key argument and format them into associative array
	*
	* @param $pk mixed: The primary key, array if composite (associative or numeric)
	* @return array: Associative array with PK arguments
	*/
	public function parsePkArgs($pk) {
		// Check parameters
		if( ! $this->_pk )
			throw new \Exception('Table doesn\'t have a Primary Key');
		if( ! is_array($pk) )
			$pk = array($pk);
		if( count($this->_pk) != count($pk) )
			throw new \Exception('Number of columns in Primary Key doesn\'t match');

		// Match arguments, replace numeric with associative array elements
		foreach( $pk as $k => $v )
			if( is_int($k) ) {
				$pk[$this->_pk[$k]] = $v;
				unset($pk[$k]);
			} else
				if( ! in_array($k, $this->_pk) )
					throw new \Exception("Unknown column name in PK: $k");

		return $pk;
	}

	/**
	* Builds a column list for a select query
	*
	* @param $cols array List of columns to select. Null to select all. True to
	*                    select only PK columns
	*/
	protected function _buildColumList($cols) {
		if( ! $cols )
			return "\t*";
		
		if( $cols === true )
			$cols = $this->_pk;

		$cl = '';
		$first = true;
		foreach( $cols as $c ) {
			if( ! array_key_exists($c, $this->_cols) )
				throw new \Exception("Unknown column name: $c");
			if( $first )
				$first = false;
			else
				$cl .= ",\n\t";
			$cl .= "`$c`";
		}
		$cl .= "\n";

		return $cl;
	}

}


/**
* Represents a where condition query
*/
class Where {

	/** Condition SQL */
	protected $_cond = '';
	/** Parameters to bind array */
	protected $_params = array();

	/**
	* Construct the statement
	*
	* @param $params array: Associative array of <column> => <value> or null
	* @param $condition string: Condition string to use. If 'AND' or 'OR' simply
	*                   glues all the elements with the defined logical operator.
	*                   Must be a valid PDO sql statement instead
	*/
	public function __construct($params=null, $condition='AND') {
		if( $condition == 'AND' || $condition == 'OR' ) {
			list($this->_cond, $this->_params) = Db::buildParams($params, " $condition ");
			return;
		}

		$this->_cond = trim($condition);
		$this->_params = $params;

	}

	/**
	* Add conditions from a different Where instance to this one
	*
	* @param $where object: The other Where instance
	* @param $glue string: SQL Operator to glue the conditions together:
	*              usually AND or OR
	*/
	public function add(Where $where, $glue='AND') {
		// Check for parameters with same name
		$cond = $where->getCondition();
		$params = $where->getParams();

		if( $this->_params && $params ) {
			foreach( $this->_params as $n => $v )
				if( ! is_int($n) && array_key_exists($n,$params) && $params[$n] !== $v ) {
					$nn = null;
					for( $i=2; $i<=PHP_INT_MAX; $i++ ) {
						$k  = $n . $i;
						if( ! array_key_exists($k,$this->_params) && ! array_key_exists($k,$params) ) {
							$nn = $k;
							break;
						}
					}
					if( ! $nn )
						throw new \Exception("Couldn't find a new unique name for $n");

					$params[$nn] = $params[$n];
					unset($params[$n]);
					$cond = str_replace(":$n", ":$nn", $cond);
				}
			$this->_params = array_merge($this->_params, $params);
		} elseif( $params )
			$this->_params = $params;

		if( $this->_cond && $cond )
			$this->_cond = "( {$this->_cond} ) $glue ( $cond )";
		elseif( $cond )
			$this->_cond = $cond;

		// PDO doesn't allow mixed named and positional parameters
		$int = false;
		$str = false;
		foreach( $this->_params as $n => $v )
			if( is_int($n) )
				$int = true;
			else
				$str = true;

		// Convert numeric parameters into positional
		if( $int && $str )
			foreach( $this->_params as $n => $v )
				if( is_int($n) ) {
					unset($this->_params[$n]);
					$nn = null;
					for( $i=$n; $i<=PHP_INT_MAX; $i++ ) {
						$k = "p$n";
						if( ! array_key_exists($k,$this->_params) ) {
							$nn = $k;
							break;
						}
					}
					if( ! $nn )
						throw new \Exception("Couldn't find a new unique name for $n");
					$this->_params[$nn] = $v;
					$count = null;
					$this->_cond = preg_replace('/\\?/', ":$nn", $this->_cond, 1, $count);
					if( $count != 1 )
						throw new \Exception("Error $count replacing argument");
				}
	}

	public function getCondition() {
		return $this->_cond;
	}

	public function getParams() {
		return $this->_params;
	}

}


/**
* Represents a Decimal, since PHP doesn't really support it
*/
class Decimal {
	/** String value, to maintain precision */
	private $__val;

	/**
	* Construct from string value
	*
	* @param $val string: The string decimal value
	*/
	public function __construct($val) {
		$this->__val = $val;
	}
}


/**
* Represents a Date without time
*/
class Date extends \DateTime {

	/**
	* Strips out time and timezone data
	*
	* @protected $date mixed: The date, as accepted by DateTime::__construct()
	*                         or a DateTime instance
	*/
	public function __construct($date='now') {
		if( gettype($date) == 'object' && $date instanceof \DateTime )
			$date = $date->format('Y-m-d');
		parent::__construct($date, new \DateTimeZone('UTC'));
		$this->setTime(0, 0, 0);
	}

}


/**
* Represents a time without a date
*/
class Time extends \DateTime {
}
