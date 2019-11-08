<?php

/**
* @file Log.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Logging-related classes
*/

namespace dophp\log;


/**
 * Basic interface for a DoPhp logger
 */
interface Logger {

	/**
	 * Constructs the logger
	 *
	 * @param $start  int: DoPhp execution start microtime()
	 * @param $config array: DoPhp configuration array
	 * @param $db     \dophp\Db: Database instance
	 * @param $auth   \dophp\Auth: Auth instance
	 */
	public function __construct(int $start, array $config, \dophp\Db $db=null, \dophp\AuthInterface $user=null);

	/**
	 * Called before the page is loaded
	 *
	 * @param $found  bool: Whether the page include file has been found
	 * @param $name   string: Name of the page
	 * @param $path   string: The relative path inside this page
	 */
	public function logPageRequest(bool $found, string $name, string $path=null);
}


/**
 * Base class for a logger
 */
abstract class BaseLogger implements logger {

	/** DoPhp start microtime */
	protected $_start;
	/** DoPhp config array */
	protected $_conf;
	/** Database instance */
	protected $_db;
	/** Auth instance */
	protected $_user;

	public function __construct(int $start, array $config, \dophp\Db $db=null, \dophp\AuthInterface $user=null) {
		$this->_start = $start;
		$this->_conf = $config;
		$this->_db = $db;
		$this->_user = $user;
	}

	abstract public function logPageRequest(bool $found, string $name, string $path=null);
}
