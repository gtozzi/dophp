<?php

/**
* @file Debug.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Debug-related classes
*/

namespace dophp\debug;


/**
 * Holds the whole debug data
 */
abstract class Debug {

	const SESS_KEY = 'DoPhp::Debug';

	/**
	 * Adds a request to the session data
	 *
	 * @param $request Request: The request to be added
	 */
	public static function add(Request $request) {
		if( session_status() != PHP_SESSION_ACTIVE )
			return;

		if( ! isset($_SESSION[self::SESS_KEY]) )
			$_SESSION[self::SESS_KEY] = [];
		$_SESSION[self::SESS_KEY][] = $request;
	}

	/**
	 * Yields all the requests in session, from most recent to the oldest
	 *
	 * @yield Request
	 */
	public static function getRequests() {
		if( session_status() != PHP_SESSION_ACTIVE )
			return;

		if( ! isset($_SESSION[self::SESS_KEY]) )
			return;

		if( ! is_array($_SESSION[self::SESS_KEY]) )
			throw new \Exception('Malformed session data');

		foreach( array_reverse($_SESSION[self::SESS_KEY]) as $req )
			yield $req;
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
		Debug::add($this);
		register_shutdown_function([$this, 'end']);
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
	 * Adds a query debug information to this request
	 *
	 * @param $query DbQuery The query debug info object
	 */
	public function addQuery(DbQuery $query) {
		$this->_actions[] = $query;
	}

	/**
	 * Ends the request time counter, may not always be called
	 */
	public function end() {
		$this->_etime = microtime(true);
	}

	/**
	 * Returns an HTML representation of this request
	 */
	public function asHtml() {
		$out = '<div class="request">';
		$out .= '<h1>Request</h1><ul>';
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

}


/**
 * Holds debug info for a database query
 */
class DbQuery {

	use OutputsHtml;

	/** The start time */
	protected $_tstart = null;
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
		$this->_tstart = microtime(true);
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

	/**
	 * Returns an HTML representation of this action
	 *
	 * @param $request Request: The parent request, used for comparison
	 */
	public function asHtml(Request $request) {
		$out = '<div class="action">';
		$out .= '<h3>Query</h3><ul>';
		$out .= $this->_fKvli('SQL', $this->_query);
		$out .= $this->_fKvli('Params', print_r($this->_params, true));
		$reltime = $this->_tstart - $request->getTime();
		$out .= $this->_fKvli('RelTime', '+' . $this->_fDur($reltime));
		if( $this->_tbuilt ) {
			$duration = $this->_tbuilt - $this->_tstart;
			$out .= $this->_fKvli('Build Time', $this->_fDur($duration));

			if( $this->_tprepared ) {
				$duration = $this->_tprepared - $this->_tbuilt;
				$out .= $this->_fKvli('Prepare Time', $this->_fDur($duration));

				if( $this->_texecuted ) {
					$duration = $this->_texecuted - $this->_tstart;
					$out .= $this->_fKvli('Execute Time', $this->_fDur($duration));
				}
			}
		}
		$out .= '</ul>';
		$out .= "</div>\n";
		return $out;
	}

}

