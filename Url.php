<?php

/**
* @file Url.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief URL Handling Utility
*/

namespace dophp;


/**
 * Represents an URL, also contains static methods for URL handling
 */
class Url {

	/** The URL scheme, eg. 'http' */
	public $scheme = null;
	/** The URL host, eg. 'www.example.com' */
	public $host = null;
	/** The URL port, eg. 80 */
	public $port = null;
	/** The URL username */
	public $user = null;
	/** The URL password */
	public $pass = null;
	/** The URL path */
	public $path = null;
	/** The query arguments, associative array */
	public $args = [];
	/** The url fragment (anchor), after the hashmark # */
	public $fragment;


	/**
	 * Constructs the URL based on given partial URL and current one
	 *
	 * @see self::parseUrl
	 */
	public function __construct($url='') {

		$ua = self::parseUrl($url);

		if( isset($ua['scheme']) )
			$this->scheme = $ua['scheme'];
		if( isset($ua['host']) )
			$this->host = $ua['host'];
		if( isset($ua['port']) )
			$this->port = (int)$ua['port'];
		if( isset($ua['user']) )
			$this->user = $ua['user'];
		if( isset($ua['pass']) )
			$this->pass = $ua['pass'];
		if( isset($ua['path']) )
			$this->path = $ua['path'];
		if( isset($ua['query']) )
			$this->args = $ua['query'];
		if( isset($ua['fragment']) )
			$this->fragment = $ua['fragment'];
	}

	/**
	 * Returns a string representation of this Url
	 *
	 * @return string: possibily partial url
	 */
	public function asString() {
		return self::buildUrl([
			'scheme' => $this->scheme,
			'host' => $this->host,
			'port' => $this->port,
			'user' => $this->user,
			'pass' => $this->pass,
			'path' => $this->path,
			'query' => $this->args,
			'fragment' => $this->fragment,
		]);
	}

	/**
	* Parses an url using parse_url + parse_str
	* This is the inverse of buildUrl
	*
	* @see buildUrl()
	* @param $url string: The URL string
	* @return array: The URL array
	* @throws \UnexpectedValueException when url is too bad
	*/
	public static function parseUrl($url) {
		$parsed = parse_url($url);
		if( $parsed === false )
			throw new \UnexpectedValueException('Seriously malformed URL');

		if( isset($parsed['query']) ) {
			$arr = array();
			parse_str($parsed['query'], $arr);
			$parsed['query'] = $arr;
		}

		return $parsed;
	}

	/**
	* Takes an url represented as array and recompose it usign http_build_query
	* This is the inverse of parseUrl
	*
	* @todo Use http_build_url when it will become available on standard installs
	* @see parseUrl()
	* @param $url array: The URL array
	* @return string: The URL string
	*/
	public static function buildUrl($url) {
		if( isset($url['query']) )
			$url['query'] = http_build_query($url['query']);

		$parsed = '';
		if( isset($url['scheme']) )
			$parsed .= $url['scheme'] . '://';
		if( isset($url['user']) || isset($url['pass']) )
			$parsed .= $url['user'] . ':' . $url['pass'] . '@';
		if( isset($url['host']) )
			$parsed .= $url['host'];
		if( isset($url['port']) && ( ! isset(self::$DEFAULT_PORTS[$url['scheme']]) || self::$DEFAULT_PORTS[$url['scheme']] != $url['port'] ) )
			$parsed .= ':' . $url['port'];
		if( strlen($parsed) )
			$parsed .= '/';
		if( isset($url['path']) ) {
			if( substr($parsed,-1,1) == '/' && substr($url['path'],0,1) == '/' )
				$parsed = substr($parsed, 0, -1);
			$parsed .= $url['path'];
		}
		if( isset($url['query']) )
			$parsed .= '?' . $url['query'];
		if( isset($url['fragment']) )
			$parsed .= '#' . $url['fragment'];

		return $parsed;
	}

	/**
	* Returns the full URL of a page
	*
	* @param $page string: The page name or relative url (if $do is null)
	* @param $key string: The name of the page parameter. If null, do not append any
	* @return string: The Full page URL
	*/
	public static function fullPageUrl($page, $key='do') {
		$url = $key === null ? $page : "?$key=$page";

		return self::fullUrl($url);
	}

	/**
	* Returns the full URL for a partial URI
	*
	* @param $url string: Any url combination. Missing informations are filled
	*             with current URL's ones
	* @return array: The URL array
	*/
	public static function fullUrlArray($url='') {
		$url = self::parseUrl($url);

		if( ! isset($url['scheme']) )
			$url['scheme'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https' : 'http';
		if( ! isset($url['host']) ) {
			$url['host'] = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:$_SERVER['SERVER_NAME']);
			$url['host'] = explode(':', $url['host']);
			$url['host'] = $url['host'][0];
		}
		if( ! isset($url['port']) )
			$url['port'] = $_SERVER['SERVER_PORT'];
		if( ! isset($url['user']) && isset($_SERVER['PHP_AUTH_USER']) )
			$url['user'] = $_SERVER['PHP_AUTH_USER'];
		if( ! isset($url['pass']) && isset($_SERVER['PHP_AUTH_PW']) )
			$url['pass'] = $_SERVER['PHP_AUTH_PW'];
		if( ! array_key_exists('path', $url) ) {
			$uri = self::parseUrl($_SERVER['REQUEST_URI']);
			$url['path'] = $uri['path'];
		} elseif( ! strlen($url['path']) || $url['path'][0] !== '/' ) {
			// Relative path, add folder if available
			$uri = self::parseUrl($_SERVER['REQUEST_URI']);
			$pathi = explode('/', $uri['path']);
			if( count($pathi) > 1 ) {
				array_pop($pathi);
				$url['path'] = implode('/',$pathi) . '/' . $url['path'];
			}
		}

		return $url;
	}

	/**
	* Returns the full URL for a partial URI
	*
	* @see self::fullUrlArray
	* @param $url string: Any url combination. Missing informations are filled
	*             with current URL's ones
	* @return string: The full page URL
	*/
	public static function fullUrl($url='') {
		return self::buildUrl(self::fullUrlArray($url));
	}

	/**
	 * Returns actual URL for the invoked page
	 *
	 * @return string
	 */
	public static function myUrl() {
		return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
			. "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	}

	/**
	 * Transforms a $_GET array into an URL string
	 *
	 * @param $get array: The GET array, usually $_GET
	 * @return The URL params string (starting with '?')
	 */
	public static function getToStr($get) {
		if( ! $get )
			return '';

		$ret = [];

		foreach( $get as $n => $v )
			if( is_array($v) )
				$ret = array_merge($ret, self::__getArrayArgToString([$n,], $v));
			else
				$ret[] = urlencode($n) . '=' . urlencode($v);

		if( ! $ret )
			return '';

		return '?' . implode('&', $ret);
	}

	/**
	 * Tells wether right url is a subpath of left one.
	 * An url is a subpath of another one if both share the same protocol, port and host,
	 * and the first's uri starts with the second's uri.
	 * Eventual query arguments are ignored.
	 *
	 * @param $pUrl Url parent url
	 * @param $cUrl Url children url
	 * @return bool True if $pUrl is parent of $cUrl, False otherwise
	 */
	public static function isSubPath(Url $pUrl, Url $cUrl): bool {
		if ( $pUrl->scheme !== $cUrl->scheme )
			return false;

		if ( ($pUrl->port ?? 80) !== ($cUrl->port ?? 80) )
			return false;

		if ( $pUrl->host !== $cUrl->host )
			return false;

		if ( $pUrl->path === null || $pUrl->path === '' )
			return true;

		if ( $cUrl->path === null || $cUrl->path === '' )
			return false;

		$pPath = self::__normPath($pUrl->path);
		$cPath = self::__normPath($cUrl->path);

		if ( count($cPath) < count($pPath) )
			return false;

		// Check path one piece a time to avoid matching /abcd as valid child of /abc
		for ( $i = 0; $i < count($pPath); $i++ ) {
			if ( $pPath[$i] !== $cPath[$i] )
				return false;
		}

		return true;
	}

	/**
	 * Explode given (path usually starts and ends with / so first and last
	 * explodes are supposed to be empty: trim them)
	 *
	 * @return array of exploded path parts (may be empty)
	 */
	private static function __normPath(string $path): array {
		$parts = explode('/', $path);
		if( count($parts) && $parts[0] === '' )
			array_shift($parts);
		if( count($parts) && end($parts) === '' )
			array_pop($parts);
		reset($parts);
		return $parts;
	}

	/**
	 * Tells wether given url is a subpath of this one
	 *
	 * @see self::isSubPath
	 */
	public function isChild(Url $cUrl): bool {
		return self::isSubPath($this, $cUrl);
	}

	/**
	 * Tells wether this url given url is a subpath of given one
	 *
	 * @see self::isSubPath
	 */
	public function isParent(Url $pUrl): bool {
		return self::isSubPath($pUrl, $this);
	}

	private static function __getArrayArgToString($namespace, $v) {
		$ret = [];
		foreach($v as $nn => $vv)
			if( is_array($vv) )
				$ret = array_merge($ret, self::__getArrayArgToString(array_merge($namespace, [$nn, ]), $vv));
			else{
				$key = '';
				foreach( $namespace as $n )
					if( ! strlen($key) )
						$key .= $n;
					else
						$key .= "[$n]";
				$key .= "[$nn]";
				$ret[] = urlencode($key) . '=' . urlencode($vv);
			}
		return $ret;
	}
}
