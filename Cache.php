<?php declare(strict_types=1);

/**
* @file Cache.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Classes for caching
*/

namespace dophp\cache;


require_once 'Exceptions.php';


/**
 * Wrapper over \Memcache
 *
 * prepends a prefix to every key, accept array prefixes
 *
 * @see \Memcache
 */
class Memcache {

	/** The prefix banner */
	const BANNER = 'DoPhp';
	/** The separator for prefix parts */
	const SEP = '::';

	/** The key prefix */
	protected $_prefix;
	/** The wrapped cache object */

	/**
	 * Constructor for the class
	 *
	 * @param $prefix str: The prefix to prepend to every key; if null,
	 *                     try to generate an unique one for this install
	 * @param $prepend bool: if true, prepends const DoPhp banner to prefix
	 */
	public function __construct(string $prefix=null, bool $banner=true) {
		$this->_cache = new \Memcache();

		$this->_prefix = $prefix ?? hash('crc32', __FILE__) . self::SEP;
		if( $banner )
			$this->_prefix = self::BANNER . self::SEP . $this->_prefix;
	}

	public function addServer( string $host, int $port=11211, bool $persistent=true,
			int $weight=null, int $timeout=null, int $retry_interval=null,
			bool $status=true, callable $failure_callback=null, int $timeoutms=null ) {

		return $this->_cache->assServer($host, $port, $persistent, $weight,
			$timeout, $retry_interval, $status, $failure_callback, $timeoutms);
	}

	public function connect( string $host, int $port=null, int $timeout=1 ) {
		return $this->_cache->connect($host, $port, $timeout);
	}

	public function close() {
		return $this->_cache->close();
	}

	public function flush() {
		return $this->_cache->flush();
	}

	public function getStats(string $type, int $slabid=null, int $limit=100) {
		return $this->_cache->getStats($type, $slabid, $limit);
	}

	public function getExtendedStats(string $type, int $slabid=null, int $limit=100) {
		return $this->_cache->getExtendedStats($type, $slabid, $limit);
	}

	public function getServerStatus(string $host, int $port=11211) {
		return $this->_cache->getServerStatus($host, $port);
	}

	public function setCompressThreshold(int $threshold, float $min_savings=0.2) {
		return $this->_cache->setCompressThreshold($threshold, $min_savings);
	}

	public function setServerParams(string $host, int $port=11211, int $timeout=1,
			int $retry_interval=15, bool $status=true, callable $failure_callback=null ) {

		return $this->_cache->setServerParams($host, $port, $timeout,
			$retry_interval, $status, $failure_callback);
	}

	/**
	 * Normalized a key into a full standard key string, prepends prefix
	 *
	 * @param $key mixed: string or array; if array, values are joined using self::SEP
	 */
	protected function _normKey($key) {
		if( is_array($key) )
			$key = implode(self::SEP, $key);

		return $this->_prefix . $key;
	}

	public function get($key, int &$flags=0) {
		$key = $this->_normKey($key);
		return $this->_cache->get($key, $flags);
	}

	public function add($key, $var, int $flag=0, int $expire=0) {
		$key = $this->_normKey($key);
		return $this->_cache->add($key, $var, $flag, $expire);
	}

	public function set($key, $var, int $flag=0, int $expire=0) {
		$key = $this->_normKey($key);
		return $this->_cache->set($key, $var, $flag, $expire);
	}

	public function replace($key, $var, int $flag=0, int $expire=0) {
		$key = $this->_normKey($key);
		return $this->_cache->replace($key, $var, $flag, $expire);
	}

	public function delete($key, $var, int $timeout=0) {
		$key = $this->_normKey($key);
		return $this->_cache->delete($key, $var, $timeout);
	}

	public function decrement($key, int $value=1) {
		$key = $this->_normKey($key);
		return $this->_cache->decrement($key, $value);
	}

	public function increment($key, int $value=1) {
		$key = $this->_normKey($key);
		return $this->_cache->increment($key, $value);
	}

}
