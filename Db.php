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
			if( gettype($p) == 'boolean' ) // PDO would convert false into null otherwise
				if( $p === true )
					$p = 1;
				elseif( $p === false )
					$p = 0;
		$st = $this->_pdo->prepare($query);
		$st->execute($params);
		return $st;
	}

	/**
	* Runs an INSERT statement from an associative array and returns ID of the
	* last auto_increment value
	*
	* @see __buildInsUpdQuery
	*/
	public function insert($table, $params) {
		list($q,$p) = $this->buildInsUpdQuery('ins', $table, $params);

		$this->run($q, $p);
		return $this->_pdo->lastInsertId();
	}

	/**
	* Runs an UPDATE statement from an associative array
	*
	* @see __buildInsUpdQuery
	* @param $where array: where conditions
	*/
	public function update($table, $params, $where) {
		list($s,$ps) = $this->buildInsUpdQuery('upd', $table, $params);
		list($w,$pw) = $this->buildParams($where, ' AND ');

		$q = "$s WHERE $w";
		$p = array_merge($ps, $pw);

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
	* Builds a partial query suitable for insert or update in the format
	* "SET col1 = val1, col2 = val2, ..."
	*
	* @param $type string: The query type (ins, upd)
	* @param $table string: The name of the table
	* @see buildParams
	* @return array [query string, params]
	*/
	public function buildInsUpdQuery($type, $table, $params) {
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

		list($cols, $p) = $this->buildParams($params);
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
	* @return array [query string, params]
	*/
	public function buildParams($params, $glue=', ') {
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
	public function buildLimit($limit=null, $skip=0) {
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
			$this->_cols[$c['COLUMN_NAME']] = $c;
			if( $c['COLUMN_KEY'] == 'PRI' )
				$this->_pk[] = $c['COLUMN_NAME'];
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
		$pk = $this->_parsePkArgs($pk);
		
		list($w, $p) = $this->_db->buildParams($pk, ' AND ');
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
	* @param $params array See dophp\Db::buildParams. Null to select all.
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

		$q .= $this->_buildColumList($cols);

		$q .= "FROM `{$this->_name}`\n";

		if( $params ) {
			$q .= "WHERE ";
			list($w, $p) = $this->_db->buildParams($params, ' AND ');
			$q .= "$w\n";
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
		$this->_db->update($this->_name, $data, $this->_parsePkArgs($pk));
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
			
			$dtype = strtoupper($this->_cols[$k]['DATA_TYPE']);
			$ctype = strtoupper($this->_cols[$k]['COLUMN_TYPE']);
			
			if( $v !== null )
				switch($dtype) {
				case 'SMALLINT':
				case 'MEDIUMINT':
				case 'INT':
				case 'INTEGER':
				case 'BIGINT':
					$v = (int)$v;
					break;
				case 'BIT':
				case 'TINYINT':
				case 'BOOL':
				case 'BOOLEAN':
					if( $ctype == 'TINYINT(1)' )
						$v = $v && $v != -1 ? true : false;
					else
						$v = (int)$v;
					break;
				case 'FLOAT':
				case 'DOUBLE':
					$v = (double)$v;
					break;
				case 'DECIMAL':
				case 'DEC':
					$v = new Decimal($v);
					break;
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
					$v = (string)$v;
					break;
				case 'DATE':
					$v = new Date($v);
					break;
				case 'DATETIME':
				case 'TIMESTAMP':
					$v = new \DateTime($v);
					break;
				case 'TIME':
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
	* Gets a primary key argument and format them into associative array
	*
	* @param $pk mixed: The primary key, array if composite (associative or numeric)
	* @return array: Associative array with PK arguments
	*/
	protected function _parsePkArgs($pk) {
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
				if( ! array_key_exists($k, $this->_pk) )
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
}

/**
* Represents a time without a date
*/
class Time extends \DateTime {
}
