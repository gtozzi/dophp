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
	protected function _run($query, $params=array()) {
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
	protected function _insert($table, $params) {
		list($q,$p) = $this->__buildInsUpdQuery('ins', $table, $params);

		$this->_run($q, $p);
		return $this->_pdo->lastInsertId();
	}

	/**
	* Runs an UPDATE statement from an associative array
	*
	* @see __buildInsUpdQuery
	* @param $where array: where conditions
	*/
	protected function _update($table, $params, $where) {
		list($s,$ps) = $this->__buildInsUpdQuery('upd', $table, $params);
		list($w,$pw) = $this->__buildParams($where);

		$q = "$s WHERE $w";
		$p = array_merge($ps, $pw);

		$this->_run($q, $p);
	}

	/**
	* Runs FOUND_ROWS() and returns result
	*
	* @return int: Number of found rows
	*/
	protected function _foundRows() {
		$q = "SELECT FOUND_ROWS() AS `fr`";

		$res = $this->_run($q)->fetch();
		return $res['fr'] !== null ? (int)$res['fr'] : null;
	}

	/**
	* Builds a partial query suitable for insert or update in the format
	* "SET col1 = val1, col2 = val2, ..."
	*
	* @param $type string: The query type (ins, upd)
	* @param $table string: The name of the table
	* @see __buildParams
	* @return array [query string, params]
	*/
	private function __buildInsUpdQuery($type, $table, $params) {
		switch( $type ) {
		case 'ins':
			$q = 'INSERT INTO';
			break;
		case 'upd':
			$q = 'UPDATE';
			break;
		default:
			throw \Exception("Unknown type $type");
		}

		list($cols, $p) = $this->__buildParams($params);
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
	* @return array [query string, params]
	*/
	private function __buildParams($params) {
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
		return array(implode(', ', $cols), $vals);
	}

	/**
	* Builds a "limit" statement
	*
	* @param $limit mixed: If True, returns only first element,
	*                      if int, returns first $limit elements
	* @param $skip integer: Number of records to skip
	* @return string: the LIMIT statement
	*/
	protected function _buildLimit($limit=null, $skip=0) {
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
