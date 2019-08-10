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

	/** MySQL DB Type */
	const TYPE_MYSQL = 'mysql';
	/** Microsoft SQL Server DB TYPE */
	const TYPE_MSSQL = 'mssql';

	/** Debug object (or true for backward compatibility) */
	public $debug = false;

	/**
	 * If true, enables FreeTDS/ODBC/MSSQL fix
	 * Forces casting of all binded parameters to VARCHAR in run()
	 *
	 * @see http://stackoverflow.com/a/3853811/4210714
	 */
	public $vcharfix = false;

	/** PDO instance */
	protected $_pdo;

	/** Database type, one of:
	 * - null: Uninited/unknown
	 * - mysql: MySQL server
	 * - mssql: Microsoft SQL Server
	 */
	protected $_type = null;

	/** Tells lastInsertId() PDO's driver support */
	protected $_hasLid = true;

	/** Wiritten in debug mode, do not use for different purposes */
	public $lastQuery = null;
	/** Wiritten in debug mode, do not use for different purposes */
	public $lastParams = null;

	/**
	* Starts a PDO connection to DB
	*
	* @param $dsn  string: DSN, PDO valid
	* @param $user string: Username to access DBMS (if not included in DSN)
	* @param $pass string: Password to access DBMS (if not included in DSN)
	* @param $vcharfix bool: See Db::vcharfix docs
	*/
	public function __construct($dsn, $user=null, $pass=null, $vcharfix=false) {
		$this->vcharfix = $vcharfix;
		$this->_pdo = new \PDO($dsn, $user, $pass);
		$this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->_pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
		// When set, will cause MySQL to return INTEGER ans PHP int, but will
		// cause some concurrency problems
		//$this->_pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
		switch( $this->_pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) ) {
		case 'mysql':
			$this->_type = self::TYPE_MYSQL;
			$this->_pdo->exec('SET sql_mode = \'TRADITIONAL,STRICT_ALL_TABLES,NO_AUTO_VALUE_ON_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE\'');
			$this->_pdo->exec('SET NAMES utf8mb4');
			break;
		case 'odbc':
			$this->_hasLid = false;
		case 'sqlsrv':
		case 'mssql':
		case 'sybase':
		case 'dblib':
			$this->_type = self::TYPE_MSSQL;
			$this->_pdo->exec('SET ARITHABORT ON');
			break;
		}
	}

	/**
	 * Prepares this object to be serialized
	 *
	 * @warning This makes the object serializable, but unusable after
	 *          unserialization
	 * @return array: list of properties to include in serialized object
	 */
	public function __sleep() {
		$vars = get_object_vars($this);
		unset($vars['_pdo']);
		return array_keys($vars);
	}

	/**
	 * When waking up, try to get the actual PDO from the running db
	 */
	public function __wakeup() {
		$curDb = \DoPhp::db();
		if( ! $curDb )
			return;
		$this->_pdo = $curDb->_pdo;
	}

	/**
	 * Returns database type
	 */
	public function type() {
		return $this->_type;
	}

	/**
	* Prepares a statement, executes it with given parameters and returns it
	*
	* @param $query string: The query to be executed
	* @param $params mixed: Array containing the parameters or single parameter
	* @param $vcharfix boolean: like $this->vcharfix, ovverides it when used
	* @return \PDOStatement
	* @throws StatementExecuteError
	*/
	public function run($query, $params=array(), $vcharfix=null) {
		if( $this->debug && $this->debug instanceof debug\Request && $this->debug->isEnabled() )
			$dbgquery = new debug\DbQuery();
		else
			$dbgquery = null;

		if( ! is_array($params) )
			$params = array($params);
		if( $vcharfix === null )
			$vcharfix = $this->vcharfix;

		// Modify the query to cast all params to varchar
		if( $vcharfix )
			$query = preg_replace('/(\?|:[a-z]+)/', 'CAST($0 AS VARCHAR)', $query);

		foreach($params as & $p)
			if( gettype($p) == 'boolean' ) {
				// PDO would convert false into null otherwise
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

		if( $dbgquery )
			$dbgquery->built($query, $params);

		if( $this->debug ) {
			$this->lastQuery = $query;
			$this->lastParams = $params;
		}

		$st = $this->_pdo->prepare($query);

		if( $dbgquery )
			$dbgquery->prepared();

		if( ! $st->execute($params) )
			throw new StatementExecuteError($st);

		if( $dbgquery ) {
			$dbgquery->executed();

			$this->debug->add($dbgquery);
		}

		return $st;
	}

	/**
	 * Like Db::run(), but returns a Result instead
	 *
	 * @see run()
	 * @param $query string: The Query string
	 * @param $params array: Associative array of params
	 * @param $types array: Associative array name => type of requested return
	 *                      types. Omitted ones will be guessed from PDO data.
	 *                      Each type must be on of Table::DATA_TYPE_* constants
	 * @return Result
	 */
	public function xrun($query, $params=[], $types=[]) {
		$st = $this->run($query, $params);
		return new Result($st, $query, $params, $types);
	}

	/**
	* Runs an INSERT statement from an associative array and returns ID of the
	* last auto_increment value
	*
	* @see self::buildInsUpdQuery
	*/
	public function insert($table, $params) {
		list($q,$p) = $this->buildInsUpdQuery('ins', $table, $params);

		// Retrieve the ID by running a scope_identity query
		if( ! $this->_hasLid )
			$q .= '; SELECT SCOPE_IDENTITY() AS ' . $this->quoteObj('id');

		$st = $this->run($q, $p);

		if( $this->_hasLid )
			return $this->lastInsertId();
		else {
			$r = $st->fetch();
			if( ! $r )
				throw new DbException('Failed to retrieve id');
			return $r['id'];
		}
	}

	/**
	* Runs an UPDATE statement from an associative array
	*
	* @see self::buildInsUpdQuery
	* @see self::buildParams
	* @return int: Number of affected rows
	*/
	public function update($table, $params, $where) {
		list($s,$ps) = $this->buildInsUpdQuery('upd', $table, $params);
		list($w,$pw) = self::buildParams($where, ' AND ', $this->_type);

		$q = "$s WHERE $w";
		$p = array_merge($ps, $pw);

		return $this->run($q, $p)->rowCount();
	}

	/**
	* Runs a DELETE statement
	*
	* @see self::buildParams
	* @return int: Number of affected rows
	*/
	public function delete($table, $where) {
		list($w,$p) = self::buildParams($where, ' AND ', $this->_type);
		$q = 'DELETE FROM '.$this->quoteObj($table)." WHERE $w";

		return $this->run($q, $p)->rowCount();
	}

	/**
	* Runs an INSERT ON DUPLICATE KEY UPDATE statement from an associative array
	*
	* @see self::buildInsUpdQuery
	*/
	public function insertOrUpdate($table, $params) {
		list($q,$p) = $this->buildInsUpdQuery('insupd', $table, $params);

		$this->run($q, $p);
		// Does not return LAST_INSERT_ID because it is not updated on UPDATE
	}

	/**
	* Runs FOUND_ROWS() and returns result
	*
	* @return int: Number of found rows
	*/
	public function foundRows() {
		$q = 'SELECT FOUND_ROWS() AS '.$this->quoteObj('fr');

		$res = $this->run($q)->fetch();
		return $res['fr'] !== null ? (int)$res['fr'] : null;
	}

	/**
	* Begins a transaction
	*
	* @see \PDO::beginTransaction()
	* @param $useExisting bool: If true, will not try to open a new transaction
	*                     when there is already an active transaction
	* @return true when transaction has been started, false instead
	*/
	public function beginTransaction($useExisting = false) {
		if( $useExisting && $this->inTransaction() )
			return false;

		if( ! $this->_pdo->beginTransaction() )
			throw new DbException('Error opening transaction');

		return true;
	}

	/**
	* Checks if a transaction is currently active within the driver
	*
	* @see \PDO::inTransaction()
	*/
	public function inTransaction() {
		return $this->_pdo->inTransaction();
	}

	/**
	* Commits a transaction
	*
	* @see \PDO::commit()
	*/
	public function commit() {
		if( ! $this->_pdo->commit() )
			throw new DbException('Commit error');
	}

	/**
	* Rolls back a transaction
	*
	* @see \PDO::rollBack()
	*/
	public function rollBack() {
		if( ! $this->_pdo->rollBack() )
			throw new DbException('Rollback error');
	}

	/**
	* Returns last insert ID
	*
	* @see \PDO::lastInsertId()
	* @return string: The ID of the last inserted row
	*/
	public function lastInsertId() {
		$lid = $this->_pdo->lastInsertId();
		if( is_int($lid) )
			return $lid;
		if( is_numeric($lid) )
			return (int)$lid;
		return $lid;
	}

	/**
	 * Quotes a parameter
	 *
	 * @see \PDO::quote
	 * @param $param string: The string to be quoted
	 * @param $ptype int: The parameter type, as PDO constant
	 * @return string: The quoted string
	 */
	public function quote($param, $ptype = \PDO::PARAM_STR) {
		$quoted = $this->_pdo->quote($param, $ptype);
		if( $quoted === false )
			throw new DbQuoteException('Error during quoting');
		return $quoted;
	}

	/**
	 * Quotes a schema object (table, column, ...)
	 *
	 * @param $name string: The unquoted object name
	 * @return string: The quoted object name
	 */
	public function quoteObj($name) {
		return self::quoteObjFor($name, $this->_type);
	}

	/**
	 * Quotes a schema object (table, column, ...)
	 *
	 * @param $name string: The unquoted object name
	 * @param $type string: The DBMS type (ansi, mysql, mssql)
	 * @return string: The quoted object name
	 */
	public static function quoteObjFor($name, $type) {
		switch( $type ) {
		case self::TYPE_MYSQL:
			$name = str_replace('`', '``', $name);
			return "`$name`";
		case self::TYPE_MSSQL:
			return "[$name]";
		case 'ansi':
			return "\"$name\"";
		default:
			throw new NotImplementedException("Type \"$type\" not implemented");
		}
	}

	/**
	 * Converts a string using SQL-99 "\"" quoting into a string using
	 * DBMS' native quoting
	 */
	public function quoteConv($query) {
		$spat = '/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/';

		switch( $this->_type ) {
		case self::TYPE_MYSQL:
			$reppat = '`$1`';
			break;
		case self::TYPE_MSSQL:
			$reppat = '[$1]';
			break;
		default:
			throw new NotImplementedException('Not Implemented');
		}

		return preg_replace($spat, $reppat, $query);
	}

	/**
	* Builds a partial query suitable for insert or update in the format
	* "SET col1 = val1, col2 = val2, ..."
	* The "insupd" type builds and INSERT query with an
	* "ON DUPLICATE KEY UPDATE" condition for all the values
	*
	* @param $type string: The query type (ins, upd, insupd)
	* @param $table string: The name of the table
	* @see self::buildParams
	* @return array [query string, params]
	*/
	public function buildInsUpdQuery($type, $table, $params) {
		switch( $type ) {
		case 'ins':
		case 'insupd':
			$q = 'INSERT INTO';
			$ins = true;
			break;
		case 'upd':
			$q = 'UPDATE';
			$ins = false;
			break;
		default:
			throw new NotImplementedException("Unknown type $type");
		}

		$q .= ' ' . $this->quoteObj($table);

		if( $ins ) {
			list($cols, $p) = self::processParams($params, $this->_type);
			$q .= '(' . implode(',', array_keys($cols)) . ')';
			$q .= ' VALUES (' . implode(',', array_values($cols)) . ')';
			if( $type == 'insupd' ) {
				$updates = array();
				foreach( $cols as $k => $v )
					$updates[] = "$k=VALUES($k)";
				$q .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);;
			}
		} else {
			list($sql, $p) = self::buildParams($params, ', ', $this->_type);
			$q .= " SET $sql";
		}
		return array($q, $p);
	}

	/**
	 * Utility function to build an IN() clause
	 *
	 * @param $params array: list of paramaters
	 * @param $emptyok bool: If true, accepts an empty params array instead of
	 *                       throwing and exception
	 * @return string: The in statement, or empty string if no params
	 * @throws \InvalidArgumentException
	 */
	public static function buildInStatement($params, $emptyok=false) {
		if( ! $params ) {
			if( $emptyok )
				return '';

			throw new \InvalidArgumentException('Empty list of params received and emptyok is not set');
		}

		return 'IN(' . implode(',', array_fill(0, count($params), '?')) . ')';
	}

	/**
	* Processes an associative array of parameters into an array of SQL-ready
	* elements
	*
	* @param $params array: Associative array with parameters. Key is the name
	*                       of the column. If the data is an array, 2nd key is
	*                       the custom sql funcion to use. By example:
	*                       'password' => [ '123456', 'SHA1(?)' ]
	*                       If also 1st key is an array, then multiple arguments
	*                       will be bound to the custom sql function, By example:
	*                       'password' => [['12345', '45678'], 'AES_ENCRYPT(?,?)'],
	* @params $type string: The DBMS type, used for quoting (see Db::QuoteObjFor())
	* @return array [ sql array, params array ]
	*         sql array format: [ column (quoted) => parameter placeholder ]
	*         params array format: [ parameter placeholder => value ]
	*/
	public static function processParams($params, $type=self::TYPE_MYSQL) {
		if( ! $params )
			return array([], []);
		$cols = array();
		$vals = array();
		foreach( $params as $k => $v ) {
			$sqlCol = self::quoteObjFor($k, $type);
			if( is_array($v) ) {
				if( count($v) != 2 || ! array_key_exists(0,$v) || ! array_key_exists(1,$v) )
					throw new \InvalidArgumentException('Invalid number of array components');
				if( is_array($v[0]) ) {
					$f = $v[1];
					foreach( $v[0] as $n => $vv ) {
						$kk = $k . $n;
						$f = preg_replace('/\?/', ":$kk", $f, 1);
						if( $vv !== null )
							$vals[$kk] = $vv;
					}
					$sqlPar = $f;
				} else {
					$sqlPar = str_replace('?', ":$k", $v[1]);
					if( $v[0] !== null )
						$vals[$k] = $v[0];
				}
			} else {
				$sqlPar = ":$k";
				$vals[$k] = $v;
			}
			$cols[$sqlCol] = $sqlPar;
		}
		return array($cols, $vals);
	}

	/**
	* Formats an associative array of parameters into a query string and an
	* associative array ready to be passed to pdo
	*
	* @deprecated
	* @see self::processParams
	* @param $glue string: The string to join the arguments, usually ', ' or ' AND '
	* @return array [query string, params array]
	*/
	public static function buildParams($params, $glue=', ', $type=self::TYPE_MYSQL) {
		list($cols, $vals) = self::processParams($params, $type);
		$sql = '';
		foreach( $cols as $c => $p )
			$sql .= (strlen($sql) ? $glue : '') . "$c = $p";

		return array($sql, $vals);
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

	/**
	* Builds an "order by" statement
	*
	* @param $order array: Names of the columns to order by
	* @return string: the ORDER BY statement
	*/
	public static function buildOrderBy($order=null) {
		if( ! $order )
			return '';

		$ord = 'ORDER BY ';
		$ord .= implode(', ', $order);

		return $ord;
	}

	/**
	 * Returns a dophp\Table instance (invokes Table::__construct)
	 *
	 * @param $name string: The table name
	 * @return \dophp\Table
	 * @throws \Exception on invalid name
	 * @see \dophp\Table::__construct
	 */
	public function table($name) {
		return new Table($this, $name);
	}

}


/**
 * Exception thrown when statement execution fails
 */
class StatementExecuteError extends \Exception {

	/** The PDOStatement::errorCode() result */
	public $errorCode;
	/** The PDOStatement::errorInfo() result */
	public $errorInfo;
	/** The PDOStatement */
	public $statement;
	/** The Query */
	public $query;
	/** The params */
	public $params;

	public function __construct( $statement ) {
		$this->statement = $statement;
		$this->errorCode = $this->statement->errorCode();
		$this->errorInfo = $this->statement->errorInfo();

		parent::__construct("{$this->errorInfo[0]} [{$this->errorInfo[1]}]: {$this->errorInfo[2]}");
	}

}


/**
 * Small utility class to hold a Result's column info
 */
class ResultCol {
	/** The column name */
	public $name;
	/** The column data type, one of Table::DATA_TYPE_* constants */
	public $type;

	/**
	 * Set the properties
	 */
	public function __construct($name, $type) {
		$this->name = $name;
		$this->type = $type;
	}

	/**
	 * Disallow modifying attributes
	 *
	 * @throws \Exception
	 */
	public function __set($name, $value) {
		throw new \LogicException('Readonly class');
	}
}


/**
 * Represents a query result, it can be iterated, but onlyce once
 */
class Result implements \Iterator {

	/** The initial key value */
	const INIT_KEY = -1;

	/** The PDO statement used to run the query */
	protected $_st;
	/** The query SQL */
	protected $_query;
	/** The query params */
	protected $_params;
	/** The explicit column types */
	protected $_types;
	/** The column info cache, array of ResultCol objects */
	protected $_cols = [];
	/** Next result to be returned */
	protected $_current = null;
	/** Next key to be returned (-1 = to be started) */
	protected $_key = self::INIT_KEY;

	/**
	 * Creates the result object from the given PDO statament
	 *
	 * @param $st \PDOStatement: The executed PDO statement
	 * @param $query string: The query string for the statement
	 * @param $params array: The used query parameters
	 * @param $types array: The requested explicit return types
	 */
	public function __construct( \PDOStatement $st, $query, $params, $types ) {
		$this->_st = $st;
		$this->_query = $query;
		$this->_params = $params;
		$this->_types = $types;
	}

	/**
	 * Returns the column type, with caching
	 *
	 * @see Table::getType()
	 * @param $idx int: The column index
	 * @return ResultCol
	 */
	public function getColumnInfo( $idx ) {
		if( ! array_key_exists($idx, $this->_cols) ) {
			// Cached value not found, generate it
			$meta = $this->_st->getColumnMeta( $idx );
			if( ! $meta )
				throw new \InvalidArgumentException("No meta for column $idx");

			if( isset($this->_types[$meta['name']]) )
				$type = $this->_types[$meta['name']];
			elseif( ! array_key_exists('native_type', $meta) ) {
				// Apparently JSON fields have no native_type, assuming string
				// TODO: use some different detection?
				error_log('DoPhp: missing native_type, assuming string; meta: ' . print_r($meta,true));
				$type = Table::DATA_TYPE_STRING;
			} else
				$type = Table::getType($meta['native_type'], $meta['len']);

			$this->_cols[$idx] = new ResultCol($meta['name'], $type);
		}

		// Return from cache
		return $this->_cols[$idx];
	}

	/**
	 * Returns next result
	 *
	 * @return array: The associative result, with properyl typed data
	 */
	public function fetch() {
		$this->_key++;

		$raw = $this->_st->fetch( \PDO::FETCH_NUM );
		if( $raw === false )
			return false;

		$res = [];
		foreach( $raw as $idx => $val ) {
			$col = $this->getColumnInfo($idx);
			$res[$col->name] = Table::castVal($val, $col->type);
		}

		return $res;
	}

	/**
	 * Returns all the results as array or arrays
	 *
	 * @return array: Array of arrays, see fetch()
	 */
	public function fetchAll() {
		$ret = [];
		foreach( $this as $k => $v )
			$ret[$k] = $v;
		return $ret;
	}

	/**
	 * Returns all the results as array or arrays, using the given col as key
	 *
	 * @param $col string: Name of the column to use as key
	 * @return array: Like fetchAll(), but using given col as key
	 */
	public function fetchAllBy($col) {
		$ret = [];
		foreach( $this as $v )
			$ret[$v[$col]] = $v;
		return $ret;
	}

	/**
	 * Returns all the results as array, with only values.
	 * Available only when the query has a single column
	 *
	 * @return array: List of col's values
	 */
	public function fetchAllVals() {
		$ret = [];
		$first = true;
		foreach( $this as $k => $v ) {
			if( $first ) {
				$first = false;

				// Get column info on first loop
				if( count($v) < 1 )
					throw new \LogicException('Columns not found');
				elseif( count($v) > 1 )
					throw new \LogicException('The query returned multiple columns');

				$cname = $this->getColumnInfo(0)->name;
			}

			$ret[$k] = $v[$cname];
		}
		return $ret;
	}

	/**
	 * @see \Iterator::revind()
	 */
	public function rewind() {
		if( $this->_key != self::INIT_KEY )
			throw new \LogicException('The result has already been fetched');
		$this->next();
	}

	/**
	 * @see \Iterator::valid()
	 */
	public function valid() {
		return $this->_current ? true : false;
	}

	/**
	 * @see \Iterator::current()
	 */
	public function current() {
		return $this->_current;
	}

	/**
	 * @see \Iterator::key()
	 */
	public function key() {
		return $this->_key;
	}

	/**
	 * @see \Iterator::next()
	 */
	public function next() {
		$this->_current = $this->fetch();
	}

}


/**
* Represents a table on the database for easy automated access
*/
class Table {

	// Column types
	const DATA_TYPE_INTEGER  = 'integer';
	const DATA_TYPE_BOOLEAN  = 'boolean';
	const DATA_TYPE_DOUBLE   = 'double';
	const DATA_TYPE_DECIMAL  = 'Decimal';
	const DATA_TYPE_STRING   = 'string';
	const DATA_TYPE_DATE     = 'Date';
	const DATA_TYPE_DATETIME = 'DateTime';
	const DATA_TYPE_TIME     = 'Time';

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
			throw new \LogicException('Db must be a valid dophp\Db instance');
		if( ! $this->_name || gettype($this->_name) !== 'string' )
			throw new \InvalidArgumentException('Invalid table name');

		// Determine the object to use to refer to "self" db
		switch( $this->_db->type() ) {
		case Db::TYPE_MYSQL:
			$sqlSelfDb = 'DATABASE()';
			$colKey = true;
			$hasReferences = true;
			break;
		case Db::TYPE_MSSQL:
			$sqlSelfDb = '\'dbo\'';
			$colKey = false;
			$hasReferences = false;
			break;
		default:
			throw new NotImplementedException('Not Implemented');
		}

		// Makes sure that table exists
		$q = '
			SELECT
				"TABLE_TYPE"
			FROM "information_schema"."TABLES"
			WHERE "TABLE_SCHEMA" = '.$sqlSelfDb.'
				AND "TABLE_NAME" = ?
		';
		if( ! $this->_db->run($this->_db->quoteConv($q), array($this->_name))->fetch() )
			throw new \LogicException("Table {$this->_name} not found");

		// Read and cache table structure
		$q = '
			SELECT
				"COLUMN_NAME",
				"COLUMN_DEFAULT",
				"IS_NULLABLE",
				"DATA_TYPE",
				"CHARACTER_MAXIMUM_LENGTH",
				"NUMERIC_PRECISION",
				"NUMERIC_SCALE"';
		if( $colKey )
			$q .= ",\n\t\t\t\t\"COLUMN_KEY\"";
		$q .= '
			FROM "information_schema"."COLUMNS"
			WHERE "TABLE_SCHEMA" = '.$sqlSelfDb.'
				AND "TABLE_NAME" = ?
			ORDER BY "ORDINAL_POSITION"
		';
		foreach( $this->_db->run($this->_db->quoteConv($q), array($this->_name))->fetchAll() as $c ) {
			if( isset($this->_cols['COLUMN_NAME']) )
				throw new \LogicException("Duplicate definition found for column {$c['COLUMN_NAME']}");

			$this->_cols[$c['COLUMN_NAME']] = $c;

			if( $colKey && $c['COLUMN_KEY'] == 'PRI' )
				$this->_pk[] = $c['COLUMN_NAME'];
		}

		// Read primary keys (if not done earlier)
		if( ! $colKey ) {
			$q = '
				SELECT
					"COLUMN_NAME"
				FROM
					"information_schema"."TABLE_CONSTRAINTS" AS "tab",
					"information_schema"."CONSTRAINT_COLUMN_USAGE" AS "col"
				WHERE
					"col"."CONSTRAINT_NAME" = "tab"."CONSTRAINT_NAME"
					AND "col"."TABLE_NAME" = "tab"."TABLE_NAME"
					AND "CONSTRAINT_TYPE" = \'PRIMARY KEY\'
					AND "col"."TABLE_SCHEMA" = '.$sqlSelfDb.'
					AND "col"."TABLE_NAME" = ?
			';
			foreach( $this->_db->run($this->_db->quoteConv($q), array($this->_name))->fetchAll() as $c )
				$this->_pk[] = $c['COLUMN_NAME'];
		}

		// Read and cache references structure
		if( $hasReferences )
			$q = '
				SELECT
					"CONSTRAINT_NAME",
					"COLUMN_NAME",
					"REFERENCED_TABLE_NAME",
					"REFERENCED_COLUMN_NAME"
				FROM "information_schema"."KEY_COLUMN_USAGE"
				WHERE "CONSTRAINT_SCHEMA" = '.$sqlSelfDb.'
					AND "TABLE_SCHEMA" = '.$sqlSelfDb.'
					AND "REFERENCED_TABLE_SCHEMA" = '.$sqlSelfDb.'
					AND "TABLE_NAME" = ?
				ORDER BY "ORDINAL_POSITION",
					"POSITION_IN_UNIQUE_CONSTRAINT"
			';
		else
			$q = '
				SELECT
					"kcu1"."CONSTRAINT_NAME",
					"kcu1"."COLUMN_NAME",
					"kcu2"."TABLE_NAME" AS "REFERENCED_TABLE_NAME",
					"kcu2"."COLUMN_NAME" AS "REFERENCED_COLUMN_NAME"
				FROM "information_schema"."REFERENTIAL_CONSTRAINTS" AS "rc"
				INNER JOIN "information_schema"."KEY_COLUMN_USAGE" AS "kcu1"
					ON "kcu1"."CONSTRAINT_CATALOG" = "rc"."CONSTRAINT_CATALOG"
					AND "kcu1"."CONSTRAINT_SCHEMA" = "rc"."CONSTRAINT_SCHEMA"
					AND "kcu1"."CONSTRAINT_NAME" = "rc"."CONSTRAINT_NAME"
				INNER JOIN "information_schema"."KEY_COLUMN_USAGE" AS "kcu2"
					ON "kcu2"."CONSTRAINT_CATALOG" = "rc"."UNIQUE_CONSTRAINT_CATALOG"
					AND "kcu2"."CONSTRAINT_SCHEMA" = "rc"."UNIQUE_CONSTRAINT_SCHEMA"
					AND "kcu2"."CONSTRAINT_NAME" = "rc"."UNIQUE_CONSTRAINT_NAME"
					AND "kcu2"."ORDINAL_POSITION" = "kcu1"."ORDINAL_POSITION"
				WHERE "kcu1"."CONSTRAINT_SCHEMA" = '.$sqlSelfDb.'
					AND "kcu1"."TABLE_SCHEMA" = '.$sqlSelfDb.'
					AND "kcu2"."TABLE_SCHEMA" = '.$sqlSelfDb.'
					AND "kcu1"."TABLE_NAME" = ?
				ORDER BY "kcu1"."ORDINAL_POSITION",
					"kcu2"."ORDINAL_POSITION"
			';
		foreach( $this->_db->run($this->_db->quoteConv($q), array($this->_name))->fetchAll() as $c ) {
			if( isset($this->_refs['COLUMN_NAME']) )
				throw new \LogicException("More than one reference detected for column {$c['COLUMN_NAME']}");
			$this->_refs[$c['COLUMN_NAME']] = $c;
		}

	}

	/**
	* Gets a record by PK
	*
	* @param $pk mixed The primary key, array if composite (associative or numeric)
	* @param $cols array Names of the columns. null to select all. true to
	*                    select only PKs.
	* @return mixed: The fetched row or null if not found
	*/
	public function get($pk, $cols=null) {
		$pk = $this->parsePkArgs($pk);

		$res = [];
		foreach( $this->select($pk, $cols) as $r )
			$res[] = $r;
		if( ! $res )
			return null;
		if( count($res) != 1 )
			throw new \LogicException('Multiple rows for get. This should never happen');

		return $res[0];
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
	* @param $joins array Array list Join objects
	* @param $order array Names of the columns to order by as strings.
	* @see \dophp\Db::buildParams
	* @see \dophp\Db::buildLimit
	* @return mixed Generator of fetched rows.
	*         Every element is converted to the right data type.
	*         When using joins, joined column names are in the form table.column
	*/
	public function select($params=null, $cols=null, $limit=null, $joins=null, $order=null) {
		$q = "SELECT\n\t";
		$p = array();

		if( $joins ) {
			$i = 1;
			foreach( $joins as $j ) {
				$j->setAlias("j$i");
				$i++;
			}
		}

		$q .= $this->_buildColumList($cols, 't', $joins);

		$q .= ' FROM '.$this->_db->quoteObj($this->_name).' AS '.$this->_db->quoteObj('t')."\n";

		if( $joins )
			foreach( $joins as $j )
				$q .= $this->_db->quoteConv($j->getJoin($this, 't'));

		if( ! $params instanceof Where )
			$params = new Where($params);
		$params->setAlias('t');

		if( $w = $this->_db->quoteConv($params->getCondition()) ) {
			$q .= "WHERE $w\n";
			$p = array_merge($p, $params->getParams());
		}

		$q .= $this->_db->buildOrderBy($order);

		if( is_array($limit) )
			list($limit, $skip) = $limit;
		else
			$skip = null;
		$q .= $this->_db->buildLimit($limit, $skip);
		$st = $this->_db->run($q, $p);
		while( $row = $st->fetch() )
			yield $this->cast($row, $joins);
	}

	/**
	* Runs an update query for a single record
	*
	* @param $pk   mixed: The primary key, array if composite (associative or numeric)
	* @param $data array: Associative array of <column>=><data> to update
	* @return int: Number of affected rows
	*/
	public function update($pk, $data) {
		return $this->_db->update($this->_name, $data, $this->parsePkArgs($pk));
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
	* @return int: Number of affected rows
	*/
	public function delete($pk) {
		return $this->_db->delete($this->_name, $this->parsePkArgs($pk));
	}

	/**
	* Returns data type for a given column
	*
	* @param $col string: the column name
	* @see Table::getType()
	* @return string: Same as Table::getType()
	*/
	public function getColumnType($col) {
		if( ! array_key_exists($col, $this->_cols) )
			throw new \LogicException("Column $col does not exist in table {$this->_name}");

		$dtype = strtoupper($this->_cols[$col]['DATA_TYPE']);
		$nprec = (int)$this->_cols[$col]['NUMERIC_PRECISION'];

		return self::getType($dtype, $nprec);
	}

	/**
	 * Returns the data type given column type and numeric precision
	 *
	 * @param $dtype string: The SQL data type (VARCHAR, INT, etc…)
	 * @param $len int: The field length (0 when not applicable)
	 * @return string: One of integer, boolean, double, Decimal, string, Date,
	 *                 DateTime, Time (see DATA_TYPE_* constants)
	 */
	public static function getType($dtype, $len) {
		switch(strtoupper($dtype)) {
		case 'SMALLINT':
		case 'MEDIUMINT':
		case 'INT':
		case 'INTEGER':
		case 'BIGINT':
		case 'LONG':
		case 'LONGLONG':
		case 'SHORT':
			return self::DATA_TYPE_INTEGER;
		case 'BIT':
		case 'BOOL':
		case 'BOOLEAN':
			return self::DATA_TYPE_BOOLEAN;
		case 'TINYINT':
		case 'TINY':
			if( $len == 1 )
				return self::DATA_TYPE_BOOLEAN;
			return self::DATA_TYPE_INTEGER;
		case 'FLOAT':
		case 'DOUBLE':
			return self::DATA_TYPE_DOUBLE;
		case 'DECIMAL':
		case 'DEC':
		case 'NEWDECIMAL':
			return self::DATA_TYPE_DECIMAL;
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
		case 'VAR_STRING':
		case 'STRING':
			return self::DATA_TYPE_STRING;
		case 'DATE':
			return self::DATA_TYPE_DATE;
		case 'DATETIME':
		case 'TIMESTAMP':
			return self::DATA_TYPE_DATETIME;
		case 'TIME':
			return self::DATA_TYPE_TIME;
		}

		throw new NotImplementedException("Unsupported column type $dtype");
	}

	/**
	 * Tells whether a column can be null or not
	 *
	 * @param $col string: the column name
	 * @return boolean
	 */
	public function isColumnNullable($col) {
		if( ! array_key_exists($col, $this->_cols) )
			throw new \LogicException("Column $col does not exist");

		switch( strtoupper($this->_cols[$col]['IS_NULLABLE']) ) {
		case 'YES':
			return true;
		case 'NO':
			return false;
		default:
			throw new NotImplementedException("Unsupported nullable value {$this->_cols[$col]['IS_NULLABLE']}");
		}
	}

	/**
	* Put every res element into the right type according to column definition
	*
	* @param $res array: Associative array representing a row
	* @param $joins array: List of Join objects used for the query
	* @return array: Associative array, with values correctly casted into the
	*                appropriate type
	*/
	public function cast($res, $joins=null) {
		// PDOStatement::fetch returns false when no results are found,
		// this is a bug in my opinion, so working around it
		if( $res === null || $res === false )
			return null;
		$ret = array();

		foreach( $res as $k => $v ) {

			$type = null;
			if( in_array($k, $this->getCols()) )
				$type = $this->getColumnType($k);
			elseif( preg_match('/^([^.]+)\.([^.]+)$/', $k, $matches) ) {
				if( $joins )
					foreach( $joins as $j )
						if( $j->getTable()->getName() == $matches[1] ) {
							$type = $j->getTable()->getColumnType($matches[2]);
							break;
						}
				if( ! $type )
					throw new \LogicException("Unknown join column $matches[2] in table $matches[1]");
			} else
				throw new \LogicException("Unknown column $k");

			$ret[$k] = self::castVal($v, $type);
		}

		return $ret;
	}

	/**
	 * Given a value and a data type, cast it into the given data type
	 *
	 * @param $val mixed: The input value, usually a string
	 * @param $type string: The desired data type, see DATA_TYPE_* constants
	 * @return mixed: The casted value
	 */
	public static function castVal($val, $type) {
		if( $val === null )
			return null;

		// Using self::normNumber() because it looks like PDO may return
		// numbers in localized format

		switch($type) {
		case self::DATA_TYPE_INTEGER:
			return (int)self::normNumber($val);
		case self::DATA_TYPE_BOOLEAN:
			return $val && $val != -1 ? true : false;
		case self::DATA_TYPE_DOUBLE:
			return (double)self::normNumber($val);
		case self::DATA_TYPE_DECIMAL:
			return new Decimal(self::normNumber($val));
		case self::DATA_TYPE_STRING:
			return (string)$val;
		case self::DATA_TYPE_DATE:
			return new Date($val);
		case self::DATA_TYPE_DATETIME:
			return new \DateTime($val);
		case self::DATA_TYPE_TIME:
			return new Time($val);
		}

		throw new NotImplementedException("Unsupported data type $type");
	}

	/**
	 * De-localize a number, takes a number in locale format and converts it
	 * into a standard xx.xxx number
	 *
	 * @param $num string: The number as string
	 * @return string: The number as string
	 */
	public static function normNumber($num) {
		$locInfo = localeconv();
		return str_replace($locInfo['decimal_point'], '.', $num);
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
			throw new \LogicException('Table doesn\'t have a Primary Key');
		if( ! is_array($pk) )
			$pk = array($pk);
		if( count($this->_pk) != count($pk) )
			throw new \LogicException('Number of columns in Primary Key doesn\'t match');

		// Match arguments, replace numeric with associative array elements
		foreach( $pk as $k => $v )
			if( is_int($k) ) {
				$pk[$this->_pk[$k]] = $v;
				unset($pk[$k]);
			} else
				if( ! in_array($k, $this->_pk) )
					throw new \LogicException("Unknown column name in PK: $k");

		return $pk;
	}

	/**
	* Gets a primary key argument and format them into a Where object
	*
	* @see self::parsePkArgs
	* @param $pk mixed: The primary key, array if composite (associative or numeric)
	* @return Where: The built Where instance
	*/
	public function parsePkWhere($pk) {
		return new Where($this->parsePkArgs($pk));
	}

	/**
	* Builds a column list for a select query
	*
	* @param $cols array List of columns to select. Null to select all. True to
	*                    select only PK columns
	* @param $alias string: The alias to use for main column names, null if omitted
	* @param $joins array: List of Join instances
	*/
	protected function _buildColumList($cols, $alias=null, $joins=null) {
		if( ! $cols )
			return "\t*";

		if( $cols === true )
			$cols = $this->_pk;

		$buildcol = function($name, $alias, Table $table, $as=null) {
			$str = '';
			static $first = true;

			if( ! in_array($name, $table->getCols()) )
				throw new \LogicException("Unknown column name: $name in table " . $table->getName());

			if( $first )
				$first = false;
			else
				$str .= ",\n\t";

			if( $alias )
				$str .= $this->_db->quoteObj($alias) . '.';

			$str .= $this->_db->quoteObj($name);

			if( $as )
				$str .= ' AS ' . $this->_db->quoteObj($as);

			return $str;
		};

		$cl = '';
		foreach( $cols as $c )
			$cl .= $buildcol ($c, $alias, $this);
		if( $joins )
			foreach( $joins as $j )
				foreach( $j->getCols() as $c )
					$cl .= $buildcol ($c, $j->getAlias(), $j->getTable(), $j->getTable()->getName().'.'.$c);
		$cl .= "\n";

		return $cl;
	}

}


/**
 * Generic DoPhp Db exception
 */
class DbException extends \Exception {
}


/**
 * Exception thrown during quoting
 */
class QuoteException extends \Exception {
}


/**
 * An error occurred while parsing a Select Query string
 */
class SelectQueryStringParseException extends \Exception {
	/** The queryb being parsed */
	public $query;

	public function __construct( string $query, string $message ) {
		$this->query = $query;

		parent::__construct($message . ' in query ' . $query);
	}
}


/**
 * Represents a SELECT query
 *
 * @warning This class is experimental and will be subject to modifications
 */
class SelectQuery {

	/** Internal usage, make private in PHP 7.2 */
	const _PARTS = [ 'SELECT', 'FROM', 'WHERE', 'ORDER BY' ];

	/**
	 * The columns definitions. Array
	 */
	protected $_cols;

	/**
	 * The FROM part of the query to be executed to get data for the table,
	 * without the "FROM" keyword, must be defined in child
	 */
	protected $_from;

	/** Where clause, if any, without "WHERE" keyword */
	protected $_where = null;

	/** Group by, if any, without "GROUP BY" keyword */
	protected $_groupBy = null;

	/** Order by, if any, without "ORDER BY" keyword */
	protected $_orderBy = null;

	/** Limit clause, if any, without "LIMIT" keyword */
	protected $_limit = null;

	/**
	 * Construct the query from a string or an array of parameters
	 *
	 * @see self::_constructFromArray
	 * @see self::_constructFromString
	 */
	public function __construct($query) {
		if( is_array($query) )
			$this->_constructFromArray($query);
		elseif( is_string($query) )
			$this->_constructFromString($query);
		else
			throw new \InvalidArgumentException('Invalid argument type: must be array or string');
	}

	/** Constructs a query from an array of parameters
	 *
	 * @param $query array: associative array:
	 *        - cols: Column definitions, array or string.
	 *                Every index is the unique column name (alias).
	 *                Possible keys in definition array:
	 *                - qname: Name of the column as defined in the query
	 *                  (ie. `a`.`id`). Mandatory. This will be the only field
	 *                  if a string is given
	 *                - pk: Tells whether this column is part of the PK
	 *        - from: The FROM part of the query, without the FROM keyword.
	 *                Also includes joins. Mandatory.
	 *        - where: The WHERE part of the query, without the WHERE keyword
	 *        - groupBy: The GROUP BY part of the query, without the GROUP BY
	 *                   keyword
	 *        - orderBy: The ORDER BY part of the query, without the ORDER BY
	 *                   keyword
	 *        - limit: The LIMIT part of the query, without the LIMIT keyword
	 */
	protected function _constructFromArray(array $query) {
		if( ! is_array($query) )
			throw new \InvalidArgumentException('Query definition must be an array');

		if( ! isset($query['cols']) )
			throw new \InvalidArgumentException('Columns definitions are mandatory');
		if( ! is_array($query['cols']) )
			throw new \InvalidArgumentException('Columns definitions must be an array');

		$this->_cols = $query['cols'];
		foreach( $this->_cols as &$col ) {
			if( is_string($col) ) {
				$col = [ 'qname' => $col ];
				continue;
			}

			if( ! is_array($col) )
				throw new \InvalidArgumentException('Column must be an array');
			if( ! isset($col['qname']) )
				throw new \InvalidArgumentException('Column must define qname');
		}
		unset($col);

		if( ! isset($query['from']) )
			throw new \InvalidArgumentException('From part is mandatory');
		if( ! is_string($query['from']) )
			throw new \InvalidArgumentException('From must be a string');

		$this->_from = $query['from'];

		if( isset($query['where']) ) {
			if( ! is_string($query['where']) )
				throw new \InvalidArgumentException('Where must be a string');

			$this->_where = $query['where'];
		}

		if( isset($query['groupBy']) ) {
			if( ! is_string($query['groupBy']) )
				throw new \InvalidArgumentException('Group BY must be a string');

			$this->_groupBy = $query['groupBy'];
		}

		if( isset($query['orderBy']) ) {
			if( ! is_string($query['orderBy']) )
				throw new \InvalidArgumentException('Order BY must be a string');

			$this->_orderBy = $query['orderBy'];
		}

		// TO DO: Modify type check. Use is_string instead of is_int, because $query['limit'] could be (val_1, val_2)
		if( isset($query['limit']) ) {
			if( ! is_int($query['limit']) && ! is_string($query['limit']) )
				throw new \InvalidArgumentException('Limit must be a string or int');

			$this->_limit = (string)$query['limit'];
		}
	}

	/**
	 * Tries to parse a select query, only very simple queries are supported
	 */
	protected function _constructFromString(string $query) {
		//TODO: Use cache if available
		if( ! is_string($query) )
			throw new SelectQueryStringParseException($query, 'Query must be a string');

		// Will build an array query to be used later
		$arrQuery = [];

		// Remove spaces and ;
		$query = trim(rtrim(trim($query), ';'));

		// Find query parts
		$partpos = [];
		$last = null;
		foreach( self::_PARTS as $pk ) {
			$ppos = self::__strposnc($query, $pk);
			if( $ppos === false )
				continue;

			if( isset($last) && $ppos <= $last['start'] )
				throw new SelectQueryStringParseException($query, "Found $pk in wrong order");
			$partpos[$pk] = [ 'start' => $ppos, 'end' => null ];
			if( isset($last) )
				$last['end'] = $ppos;
			$last = & $partpos[$pk];
		}
		unset($last);

		// Split query into parts
		$parts = [];
		$parsedParts = [];
		foreach( $partpos as $pk => $pi ) {
			if( $pi['end'] === null )
				$pi['end'] = strlen($query);
			$parts[$pk] = trim(substr($query, $pi['start'], $pi['end']-$pi['start']));
		}

		// Parse SELECT
		if( ! isset($parts['SELECT']) )
			throw new SelectQueryStringParseException('Missing SELECT');
		$colDefs = substr($parts['SELECT'], strlen('SELECT'));
		if( $colDefs === false )
			throw new SelectQueryStringParseException($query, 'Error parsing column definitions');
		$cols = self::__parseColDefs($colDefs);
		$arrQuery['cols'] = $cols;
		$parsedParts['SELECT'] = true;

		// Parse FROM
		if( ! isset($parts['FROM']) )
			throw new SelectQueryStringParseException($query, 'Missing FROM');
		$from = substr($parts['FROM'], strlen('FROM'));
		$arrQuery['from'] = $from;
		$parsedParts['FROM'] = true;

		// Parse WHERE
		if( isset($parts['WHERE']) ) {
			$where = substr($parts['WHERE'], strlen('WHERE'));
			$arrQuery['where'] = $where;
			$parsedParts['WHERE'] = true;
		}

		// Parse ORDER BY
		if( isset($parts['ORDER BY']) ) {
			$orderBy = substr($parts['ORDER BY'], strlen('ORDER BY'));
			$arrQuery['orderBy'] = $orderBy;
			$parsedParts['ORDER BY'] = true;
		}

		// Consinstency check
		foreach( $parts as $pk => $pi )
			if( ! isset($parsedParts[$pk]) || ! $parsedParts[$pk] )
				throw new SelectQueryStringParseException($query, "$pk parsing not implemented");

		$this->_constructFromArray($arrQuery);
	}

	/** Parses a string column definition into an array column definition */
	private static function __parseColDefs(string $colDefs) {
		//TODO: improve, many bugs
		$cols = [];
		$colRe = '/^\s*([^\s]+)(?:\s+AS\s+([^\s]+))?\s*$/';
		foreach( explode(',', $colDefs) as $cd ) {
			$matches = [];
			$r = preg_match($colRe, $cd, $matches);
			if( ! $r )
				throw new \InvalidArgumentException("Unparsable column definition: \"$cd\"");

			$parsed = self::__parseColName($matches[1]);
			end($parsed);
			$lk = key($parsed);
			reset($parsed);

			$col = [ 'qname' => $matches[1] ];
			$colk = isset($matches[2]) ? $matches[2] : $parsed[$lk];

			if( array_key_exists($colk, $cols) )
				throw new \InvalidArgumentException("Duplicate column alias \"$colk\"");
			$cols[$colk] = $col;
		}
		return $cols;
	}

	/** Parses a column name: splits it into parts and unquote it */
	private static function __parseColName(string $colname) {
		//TODO: improve, many bugs
		$parts = explode('.', $colname);
		foreach( $parts as &$p )
			$p = trim($p, '`');
		unset($p);
		return $parts;
	}

	/** Case- insensitive strpos */
	private static function __strposnc(string $haystack, string $needle, int $offset = 0) {
		$lower = strpos(strtolower($haystack), strtolower($needle), $offset);
		if( $lower !== false )
			return $lower;

		$upper = strpos(strtoupper($haystack), strtoupper($needle), $offset);
		return $upper;
	}

	/**
	 * Returns column definitions
	 */
	public function cols(): array {
		return $this->_cols;
	}

	/**
	 * Returns a single column
	 *
	 * @param $ck string: The column's unique ID
	 * @return string
	 */
	public function col(string $ck): array {
		if( ! isset($this->_cols[$ck]) )
			throw new \InvalidArgumentException("Unknown column \"$ck\"");
		return $this->_cols[$ck];
	}

	/**
	 * Returns the query as SQL
	 *
	 * @todo Use Caching
	 */
	public function asSql(): string {
		foreach( $this->_cols as $name => $def )
			$select[] = $def['qname'] . " AS $name";

		$sql = 'SELECT ' . implode(', ', $select) . "\nFROM " . $this->_from;
		if( $this->_where )
			$sql .= "\nWHERE {$this->_where}";
		if( $this->_groupBy )
			$sql .= "\nGROUP BY {$this->_groupBy}";
		if( $this->_orderBy )
			$sql .= "\nORDER BY {$this->_orderBy}";
		if( $this->_limit )
			$sql .= "\nLIMIT {$this->_limit}";

		return $sql;
	}

	/**
	 * @see self::asSql()
	 */
	public function __toString() {
		return $this->asSql();
	}

	/**
	 * Appends a where condition to this query
	 *
	 * @param $where string: The where condition
	 * @param $op string: Operator: 'AND' or 'OR'
	 */
	public function addWhere(string $where, string $op='AND') {
		if( $op != 'AND' && $op != 'OR' )
			throw new \InvalidArgumentException("Unknown operator $op");

		if( $this->_where === null ) {
			$this->_where = $where;
			return;
		}

		$this->_where = "( {$this->_where} ) $op ( $where )";
	}

	/**
	 * Sets a new limit clause
	 *
	 * @param $limit string: The new clause, wihout LIMIT keyword
	 */
	public function setLimit($limit) {
		$this->_limit = (string)$limit;
	}
}


/**
* Represents a where condition query
*
* @deprecated
*/
class Where {

	/** Condition SQL */
	protected $_cond = '';
	/** Parameters to bind array */
	protected $_params = array();
	/** The assigned SQL alias for all columns */
	protected $_alias = null;

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
			list($this->_cond, $this->_params) = Db::buildParams($params, " $condition ", 'ansi');
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
						throw new \LogicException("Couldn't find a new unique name for $n");

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
						throw new \LogicException("Couldn't find a new unique name for $n");
					$this->_params[$nn] = $v;
					$count = null;
					$this->_cond = preg_replace('/\\?/', ":$nn", $this->_cond, 1, $count);
					if( $count != 1 )
						throw new \LogicException("Error $count replacing argument");
				}
	}

	/**
	* Return the condition with SQL99 quoting, adding specified alias if needed
	*
	* @see Db::quoteConv()
	*/
	public function getCondition() {
		if( ! $this->_alias )
			return $this->_cond;

		return preg_replace('/"[^"]+"/', '"'.$this->_alias.'".$0', $this->_cond);
	}

	public function getParams() {
		return $this->_params;
	}

	/**
	* Assign a new alias to all columns
	*/
	public function setAlias($alias) {
		$this->_alias = $alias;
	}

}


/**
* Represents a join in a query
*
* @deprecated
*/
class Join {

	/** The table to be joined */
	protected $_table = '';
	/** The columns to be selected */
	protected $_cols = [];
	/** The assigned alias */
	protected $_alias = null;

	/**
	* Construct the join
	*
	* @param $table Table: table instance to be joined
	* @param $cols array: List of columns to be selected
	*/
	public function __construct(Table $table, $cols) {
		$this->_table = $table;
		$this->_cols = $cols;
	}

	/**
	* Returns join SQL for joining with a given table (with SQL99 quoting)
	*
	* @see Db::quoteConv()
	* @param $table Table: the table to join with
	* @param $alias string: the alias assigned to the table
	* @return string: The SQL code
	*/
	public function getJoin(Table $table, $alias) {
		$sql = 'LEFT JOIN "' . $this->_table->getName() . '" AS "' . $this->getAlias() . "\"\n";
		$refs = 0;

		foreach( $table->getRefs() as $col => $ref )
			if( $ref[0] == $this->_table->getName() ) {
				$refs++;
				$sql .= "\t";

				if( $refs == 1 )
					$sql .= "ON ";
				else
					$sql .= "AND ";

				$sql .= "\"$alias\".\"$col\" = \"" . $this->getAlias() . "\".\"$ref[1]\"\n";
			}

		if( ! $refs )
			throw new \InvalidArgumentException('Unable to parse reference for join');

		return $sql;
	}

	/**
	* Returns the table instance
	*/
	public function getTable() {
		return $this->_table;
	}

	/**
	* Returns list of columns
	*/
	public function getCols() {
		return $this->_cols;
	}

	/**
	* Assign a new alias to this join
	*/
	public function setAlias($alias) {
		$this->_alias = $alias;
	}

	/**
	* Retrieve the alias
	*/
	public function getAlias() {
		if( $this->_alias === null )
			throw new \InvalidArgumentException('No alias assigned');
		return $this->_alias;
	}

}


/**
* Represents a Decimal, since PHP doesn't really support it
*/
class Decimal implements \JsonSerializable {
	/** String value, to maintain precision, integer part */
	private $__int;
	/** String value, to maintain precision, decimal part */
	private $__dec;

	/**
	* Construct from string value
	*
	* @param $val string: The string decimal value
	*/
	public function __construct($val) {
		if( $val === null ) {
			$this->__int = null;
			$this->__dec = null;
		} else {
			$parts = explode('.', $val);

			if( count($parts) > 2 )
				throw new \InvalidArgumentException("Malformed decimal $val");

			if( ! isset($parts[1]) )
				$parts[1] = null;

			foreach( $parts as $p )
				if( ! is_numeric($p) && $p !== null )
					throw new \InvalidArgumentException("Non-numeric decimal $val");

			list($this->__int, $this->__dec) = $parts;
		}
	}

	public function __toString() {
		if( $this->__dec === null ) {
			if( $this->__int === null )
				return null;
			return $this->__int;
		}
		return $this->__int . '.' . $this->__dec;
	}

	public function toDouble() {
		return (double)($this->__int . '.' . $this->__dec);
	}

	public function jsonSerialize() {
		return $this->toDouble();
	}

	/**
	* Returns a formatted version of this decimal
	*
	* @param $decimals int: Number of decimals (null = all)
	* @param $dec_point string: Decimal point
	* @param $thousands_sep string: Thousands separator
	*/
	public function format($decimals=null, $dec_point='.', $thousands_sep=null) {
		if( $this->__int === null && $this->__dec === null )
			return '-';

		$str = (string)abs($this->__int);
		$li = strlen($str) - 1;
		if( $thousands_sep )
			for($i=$li; $i>=0; $i--)
				if($i != $li && ($li-$i) % 3 == 0)
					$str = substr_replace($str, '.', $i+1, 0);

		if( $this->__int < 0 )
			$str = '-' . $str;

		// Deprecated and undocumented: -1 is the same as null
		if( $decimals == -1 ) {
			trigger_error('Deprecated usage of -1 as format() argument, ' .
					'please use null instead', E_USER_WARNING);
			$decimals = null;
		}
		if( $decimals === null )
			$decimals = strlen($this->__dec);

		if( $decimals != 0 && $this->__dec !== null ) {
			$str .= $dec_point;
			if( strlen($this->__dec) < $decimals )
				$str .= str_pad($this->__dec, $decimals, '0', STR_PAD_RIGHT);
			else
				$str .= substr($this->__dec, 0, $decimals);
		}

		return $str;
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
