<?php

/**
* @file Debug.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Debug-related classes
*/

namespace dophp\debug;


/**
 * Holds the whole debug data, singleton
 */
abstract class Debug {

	/** Stores the current instance */
	private static $__instance = null;

	/**
	 * To be called only internally
	 */
	protected function __construct() {
	}

	/**
	 * Returns the singleton instance
	 */
	public static function instance() {
		if ( ! self::$__instance )
			throw new \Exception('Debug has not been instantiated');

		return self::$__instance;
	}

	/**
	 * Stores the new instance, must be called by child's init()
	 */
	protected static function _init(Debug $inst) {
		if ( self::$__instance )
			throw new \Exception('Can\'t init twice');

		self::$__instance = $inst;
	}

	/**
	 * Adds a request to the debug data
	 *
	 * @param $request Request: The request to be added
	 * @return $id mixed: An unique request ID
	 */
	abstract public function add(Request $request);

	/**
	 * This is called to inform the container that it should refresh its data
	 *
	 * @param $id mixed: The ID of the request to be updated
	 */
	abstract public function update($id, Request $request);

	/**
	 * Removes given request from debug
	 */
	abstract public function del($id);

	/**
	 * Yields all the stored requests, from most recent to the oldest
	 *
	 * @yield Request
	 */
	abstract public function getRequests();

	/**
	 * Counts how many requests are stored
	 */
	abstract public function countRequests();
}


/**
 * Debug container holding the whole data in a session variable
 */
class SessionDebug extends Debug {

	const SESS_KEY = 'DoPhp::Debug';

	/**
	 * Inits the debug container
	 */
	public static function init() {
		parent::_init(new self());
	}

	public function add(Request $request) {
		if( session_status() != PHP_SESSION_ACTIVE )
			return;

		if( ! isset($_SESSION[self::SESS_KEY]) )
			$_SESSION[self::SESS_KEY] = [];

		$_SESSION[self::SESS_KEY][] = $request;

		end($_SESSION[self::SESS_KEY]);
		$id = key($_SESSION[self::SESS_KEY]);
		reset($_SESSION[self::SESS_KEY]);

		return $id;
	}

	public function update($id, Request $request) {
		// This does nothing, since session is updated automagically
	}

	public function del($id) {
		if( session_status() != PHP_SESSION_ACTIVE )
			return;

		if( ! isset($_SESSION[self::SESS_KEY]) )
			return;

		unset($_SESSION[self::SESS_KEY][$id]);
	}

	protected function _getSessArray() {
		if( session_status() != PHP_SESSION_ACTIVE )
			return [];

		if( ! isset($_SESSION[self::SESS_KEY]) )
			return [];

		if( ! is_array($_SESSION[self::SESS_KEY]) )
			throw new \Exception('Malformed session data');

		return $_SESSION[self::SESS_KEY];
	}

	public function getRequests() {
		foreach( array_reverse($this->_getSessArray()) as $req )
			yield $req;
	}

	public function countRequests() {
		return count($this->_getSessArray());
	}

}


/**
 * Debug container holding the whole data in memcached
 */
class MemcacheDebug extends Debug {

	const CACHE_PREFIX = 'DoPhp::Debug::';
	const CACHE_KEY_IDX = self::CACHE_PREFIX . 'idx';
	const CACHE_FLAGS = 0;
	const CACHE_EXPIRE_SEC = 60 * 5; // 5 minutes

	/** The Memcache object */
	protected $_cache;

	/**
	 * Inits the debug container
	 *
	 * $cache Memcache The cache object instance
	 */
	public static function init(\Memcache $cache) {
		parent::_init(new self($cache));
	}

	protected function __construct(\Memcache $cache) {
		parent::__construct();

		$this->_cache = $cache;
	}

	public function add(Request $request) {
		// Reads last used ID
		$lid = $this->_cache->get(self::CACHE_KEY_IDX);
		if( $lid === false )
			$lid = -1;

		$id = $lid + 1;
		$k = self::CACHE_PREFIX . $id;

		$this->_cache->set($k, $request, self::CACHE_FLAGS, self::CACHE_EXPIRE_SEC);
		$this->_cache->set(self::CACHE_KEY_IDX, $id, self::CACHE_FLAGS, self::CACHE_EXPIRE_SEC);

		return $id;
	}

	public function update($id, Request $request) {
		$k = self::CACHE_PREFIX . $id;
		$this->_cache->replace($k, $request, self::CACHE_FLAGS, self::CACHE_EXPIRE_SEC);
	}

	public function del($id) {
		$k = self::CACHE_PREFIX . $id;
		$this->_cache->delete($k);
	}

	public function getRequests() {
		$lid = $this->_cache->get(self::CACHE_KEY_IDX);
		if( $lid === false )
			return;

		for( $id=$lid; $id>=0; $id-- ) {
			$k = self::CACHE_PREFIX . $id;
			$req = $this->_cache->get($k);
			if( $req !== false )
				yield $req;
		}
	}

	public function countRequests() {
		$lid = $this->_cache->get(self::CACHE_KEY_IDX);
		if( $lid === false )
			return 0;
		return $lid + 1;
	}

}


/**
 * Utility methods for outputting HTML
 */
trait OutputsHtml {

	/**
	 * Formats a duration from int to human-friendly string
	 *
	 * @param $time double: Time in seconds
	 * @return string: Human.friendly time in milliseconds
	 */
	protected function _fDur($time) {
		return number_format($time*1000, 3, ',', '.') . ' ms';
	}

	/**
	 * Outputs a key/value list pair
	 *
	 * @param $key string
	 * @param $val string
	 * @return string HTML
	 */
	protected function _fKvli($key, $val) {
		return '<li>' . htmlentities($key) . ': <i>' . htmlentities($val) . '</i></li>';
	}

}


/**
 * Holds debug information for a single call (requires session)
 */
class Request {

	use OutputsHtml;

	/** The request's id */
	protected $_id;
	/** Tells whether debugging is enabled */
	protected $_enabled = false;
	/** Stores debugged actions, in order */
	protected $_actions = [];
	/** The requested URL */
	protected $_uri;
	/** The request time */
	protected $_rtime;
	/** The end time */
	protected $_etime = null;

	/**
	 * Should be called as soon as possible when the request is received
	 *
	 * @param $enabled bool: If false, disables recording of debug messages
	 */
	public function __construct($enabled=false) {
		$this->_enabled = $enabled;
		$this->_rtime = $_SERVER['REQUEST_TIME_FLOAT'];
		$this->_uri = $_SERVER['REQUEST_URI'];

		$this->_id = Debug::instance()->add($this);
		register_shutdown_function([$this, 'shutdown']);
	}

	/**
	 * Tells if this request has debug enabled
	 *
	 * @return bool
	 */
	public function isEnabled() {
		return $this->_enabled;
	}

	/**
	 * Adds an action to this request
	 *
	 * Action may be a Query, a checkpoint, etc...
	 *
	 * @param $query DbQuery The query debug info object
	 */
	public function add(Action $action) {
		$this->_actions[] = $action;
		Debug::instance()->update($this->_id, $this);
	}

	/**
	 * Removes current request from debug info
	 */
	public function hide() {
		Debug::instance()->del($this->_id);
	}

	/**
	 * Returns an HTML representation of this request
	 */
	public function asHtml() {
		$out = '<div class="request">';
		$out .= '<h1>Request #' . $this->_id . '</h1><ul>';
		$out .= $this->_fKvli('URI', $this->_uri);
		$out .= $this->_fKvli('Time', date('d M Y H:i:s', $this->_rtime));
		if( $this->_etime ) {
			$duration = $this->_etime - $this->_rtime;
			$out .= $this->_fKvli('Duration', $this->_fDur($duration));
		}
		$out .= '</ul>';
		if( $this->_actions ) {
			$out .= '<h2>Actions</h2>';
			foreach( $this->_actions as $act )
				$out .= $act->asHtml($this);
		}
		$out .= "</div>\n";
		return $out;
	}

	/**
	 * Returns request start time
	 *
	 * @return double
	 */
	public function getTime() {
		return $this->_rtime;
	}

	/**
	 * Called by the shutdown handler
	 */
	public function shutdown() {
		$this->_etime = microtime(true);
		Debug::instance()->update($this->_id, $this);
	}
}


/**
 * A generic debug action
 */
interface Action {

	/**
	 * Returns an HTML representation of this action
	 *
	 * @param $request Request: The parent request, used for comparison
	 */
	public function asHtml(Request $request);

}


/**
 * A base helper class for implementing an action
 */
abstract class BaseAction implements Action {

	use OutputsHtml;

	/** The start time */
	protected $_tstart;

	/**
	 * Sets the start timer
	 *
	 * @param $time float: The start microtime (use current time by default)
	 */
	public function __construct($time=null) {
		$this->_tstart = $time===null ? microtime(true) : $time;
	}

	public function asHtml(Request $request) {
		$info = $this->getInfo();

		$out = '<div class="action">';
		$out .= '<h3>' . get_class($this) . '</h3><ul>';
		$reltime = $this->_tstart - $request->getTime();
		$out .= $this->_fKvli('RelTime', '+' . $this->_fDur($reltime));
		foreach( $info as $k => $v )
			$out .= $this->_fKvli($k, $v);
		$out .= '</ul>';
		$out .= "</div>\n";
		return $out;
	}

	/**
	 * Returns an associative array of information to be displayed
	 * in the HTML output
	 */
	abstract public function getInfo();

}


/**
 * A simple checkpoint in the code
 */
class CheckPoint extends BaseAction {

	protected $_name;

	/**
	 * Sets a checkpoint
	 *
	 * @param $name string: The checkpoint descriptive name
	 * @param $time double: The checkpoint's microtime (current time by default)
	 */
	public function __construct($name, $time=null) {
		parent::__construct($time);

		$this->_name = $name;
	}

	public function getInfo() {
		return [
			'Name' => $this->_name,
		];
	}

}


/**
 * Holds debug info for a database query
 */
class DbQuery extends BaseAction {

	/** The build completion time */
	protected $_tbuilt = null;
	/** The prepared query time */
	protected $_tprepared = null;
	/** The executed query time */
	protected $_texecuted = null;

	/** The executed SQL */
	protected $_query = null;
	/** The associated params */
	protected $_params = null;

	/** Called when the query creation starts */
	public function __construct() {
		parent::__construct();
	}

	/** Called when the query has been created */
	public function built($query, $params) {
		$this->_tbuilt = microtime(true);
		$this->_query = $query;
		$this->_params = $params;
	}

	/** Called when the query has been prepared */
	public function prepared() {
		$this->_tprepared = microtime(true);
	}

	/** Called when the query has been prepared */
	public function executed() {
		$this->_texecuted = microtime(true);
	}

	public function getInfo() {
		$ret = [
			'SQL' => $this->_query,
			'Params' => print_r($this->_params, true),
		];

		if( $this->_tbuilt ) {
			$duration = $this->_tbuilt - $this->_tstart;
			$ret['Build Time'] = $this->_fDur($duration);

			if( $this->_tprepared ) {
				$duration = $this->_tprepared - $this->_tbuilt;
				$ret['Prepare Time'] = $this->_fDur($duration);

				if( $this->_texecuted ) {
					$duration = $this->_texecuted - $this->_tstart;
					$ret['Execute Time'] = $this->_fDur($duration);
				}
			}
		}

		return $ret;
	}

}
