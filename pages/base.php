<?php

/**
* @file base.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @see Page.php
* @brief Base Page classes
*/

namespace dophp;

require_once(__DIR__ . '/../Page.php');



/**
* Interface for implementing a page
*/
interface PageInterface {

	/**
	* Constructor
	*
	* @param $config array: Global config array
	* @param $db     object: Database instance
	* @param $user   object: Current user object instance
	* @param $name   string: Name of this page
	* @param $path   string: The relative path inside this page
	*/
	public function __construct(& $config, $db, $user, $name, $path);

	/**
	* Method called prior of page execution to determine if the page can be
	* retrieved from the cache.
	*
	* @return mixed: Must return an unique cache KEY that will be used as the
	*         key for storing the output after page had been executed. When
	*         running the page again, if a valid key will be found in the cache
	*         it will be used instead of calling run() again.
	*         Returning NULL disables caching.
	*/
	public function cacheKey();

	/**
	* Returns cache expiration time, in seconds
	* 0 means never, keep data forever, null disables cache storage
	*/
	public function cacheExpire();

	/**
	* Method called when the page is executed
	*
	* Must return page output (usually HTML) or lauch a PageError exception
	*/
	public function run();

	/**
	* Method called to retrieve page headers
	*
	* Must return associative array
	*/
	public function headers();

	/**
	 * Returns current page's name
	 */
	public function name();

	/**
	 * Returns current page's relative path
	 */
	public function path();

	/**
	 * Returns current DoPhp's config
	 */
	public function &config();

	/**
	 * Returns current DoPhp's Db instance
	 */
	public function db();

	/**
	 * Returns current DoPhp's user
	 */
	public function user();
}

/**
* Base class for easier page implementation
*/
abstract class PageBase {

	/** Config array */
	protected $_config;
	/** Database instance */
	protected $_db;
	/** User instance */
	protected $_user;
	/** Name of this page */
	protected $_name;
	/** Relative path inside this page */
	protected $_path;
	/** Headers to be output */
	protected $_headers = array();
	/**
	* ZLib deflate compression level for output
	* if different than 0, enables compression. -1 to 9 or bool true.
	* If true or -1, uses default zlib's compression level.
	*/
	protected $_compress = 0;
	/**
	* If false, use compression as preferred when configured above and accepted
	* by client in the Accept-Encoding header.
	* If true, refuse to send the page when compression is enabled but not accepted
	* by client
	*/
	protected $_forceCompress = false;
	/** If set, will not compress data below this size */
	protected $_minCompressSize = null;
	/** After how many seconds cache will expire (cache is not enabled by default) */
	protected $_cacheExpire = 300;

	/** Will get and store the alerts here, if any */
	protected $_alerts;
	/** Will store the last login error occurred, if any */
	protected $_loginError = null;

	/** The optional page title, mostly useful in interactive pages */
	protected $_pageTitle = null;

	/**
	* Constructor
	*
	* @see PageInterface::__construct
	*/
	public function __construct(& $config, $db, $user, $name, $path) {
		$this->_config = $config;
		$this->_db = $db;
		$this->_user = $user;
		$this->_name = $name;
		$this->_path = $path;

		$this->_alerts = \DoPhp::getAlerts();
		foreach( $this->_alerts as $alert )
			if( $alert instanceof LoginErrorAlert )
				$this->_loginError = $alert;
	}

	/**
	* Caching is disabled by default
	*/
	public function cacheKey() {
		return null;
	}

	/**
	* Return cache expiration time
	*/
	public function cacheExpire() {
		return $this->_cacheExpire;
	}

	public function headers() {
		return $this->_headers;
	}

	public function name() {
		return $this->_name;
	}

	public function path() {
		return $this->_path;
	}

	public function &config() {
		return $this->_config;
	}

	public function db() {
		return $this->_db;
	}

	public function user() {
		return $this->_user;
	}

	/**
	* Utility function: checks that a valid user is logged in.
	*
	* @param $redirect bool: When given, specify redirect preference for
	*                        DoPhp to handle this exception
	* @throws InvalidCredentials
	*/
	protected function _requireLogin(bool $redirect=null) {
		if( $this->_user->getUid() )
			return;

		$e = new InvalidCredentials(_('Invalid credentials'));
		$e->redirect = $redirect;
		throw $e;
	}

	/**
	* Utility function: compresses the output according to compression settings
	* and adds required header
	*
	* @param $str string: The uncompressed data
	* @return string: The data compressed or not according to compression settings
	*/
	protected function _compress($str) {
		if( $this->_compress === true )
			$this->_compress = -1;

		if( $this->_compress &&
			( $this->_minCompressSize === null || strlen($str) >= $this->_minCompressSize )
		) {
			$head = Utils::headers();
			$supported = array();
			if( isset($head['Accept-Encoding']) )
				$supported = array_map('trim', explode(',', $head['Accept-Encoding']));

			$accepted = false;
			foreach( $supported as $s )
				if( $s == 'gzip' || $s == 'deflate' ) {
					$accepted = $s;
					break;
				}

			if( $accepted ) {
				$this->_headers['Content-Encoding'] = $accepted;
				return gzcompress($str, $this->_compress, $accepted == 'gzip' ? ZLIB_ENCODING_GZIP : ZLIB_ENCODING_DEFLATE);
			} elseif( $this->_forceCompress )
				throw new NotAcceptable('Compression is required but not accepted');
		}

		return $str;
	}

	/**
	 * Provide a custom JSON pre-serialization for values
	 * Overridable in child
	 *
	 * @param $value mixed: The value to be pre-serialized, to be modified in place
	 */
	protected function _jsonSerialize(&$value) {
		if( is_string($value) || is_bool($value) || is_int($value)
				|| is_float($value) || is_null($value) )
			return;

		if( is_object($value) ) {
			if( $value instanceof \JsonSerializable )
				return;
			elseif( $value instanceof Date )
				$value = $value->format('Y-m-d');
			elseif( $value instanceof \DateTime )
				$value = $value->format('c');

			return;
		}

		if( is_array($value) ) {
			foreach( $value as &$v )
				$this->_jsonSerialize($v);
			unset($v);
			return;
		}
	}

	/**
	 * Utility function: returns a json encoded version of data, just like
	 * json_encode but failsafe and also calls pre-serialization
	 *
	 * @see self::_jsonSerialize
	 * @param $res mixed: The data to be encoded
	 * @param $opts int: Json options
	 * @return string: The json encoded data
	 * @throws Exception en json_encode error
	 */
	protected function _jsonEncode($res, $opts=0) {
		$this->_jsonSerialize($res);
		$encoded = json_encode($res, $opts);
		if( $encoded === false )
			throw new \UnexpectedValueException(json_last_error_msg(), json_last_error());
		return $encoded;
	}
}
