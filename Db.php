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
	* @param $params array: Array containing the parameters
	*/
	protected function _run($query, $params=array()) {
		$st = $this->_pdo->prepare($query);
		$st->execute($params);
		return $st;
	}

	/**
	* Runs an INSERT statement from an associative array and returns ID of the
	* last auto_increment value
	*
	* @param $table string: The name of the table
	* @param $params array: Associative array with parameters. Key is the name
	*                       of the column. If the data is an array, 2nd key is
	*                       the custom placeholder to use. By example:
	*                       'password' => [ '123456', 'SHA1(?)' ]
	*/
	protected function _insert($table, $params) {
		$cols = array();
		$vals = array();
		foreach( $params as $k => & $v ) {
			$cols[] = "`$k`";
			if( is_array($v) ) {
				$vals[] = str_replace('?', ":$k", $v[1]);
				$v = $v[0];
			}else
				$vals[] = ":$k";
		}
		$cols = implode(',', $cols);
		$vals = implode(',', $vals);

		$q = "INSERT INTO `$table` ($cols) VALUES($vals)";

		$this->_run($q, $params);
		return $this->_pdo->lastInsertId();
	}

}
