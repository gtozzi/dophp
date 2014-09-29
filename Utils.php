<?php

/**
* @file Utils.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Miscellaneous utility functions
*/

namespace dophp;

class Utils {

	/** Default ports used for URL protocols */
	public static $DEFAULT_PORTS = array(
		'http' => 80,
		'https' => 443,
		'ftp' => 21,
	);

	/**
	* Tries to process a time string intelligently
	*
	* @param  $str string: The time to be processed
	* @return string: The processed time in the format hh:mm:ss
	*/
	public static function parseTime($str) {

		# Split by possible separators
		$parts = preg_split('/[.:-]/', trim($str));

		# Check number of resulting parts
		if( count($parts) > 3 || count($parts) < 1)
			throw new \Exception("Unvalid number of parts: " . count($parts));

		# Convert each part to a number and pad it back again
		foreach($parts as &$p)
			$p = str_pad(intval($p, 10), 2, '0', STR_PAD_LEFT);

		# Add missing parts
		for($i=0; $i<=2; $i++)
			if( ! array_key_exists($i, $parts) )
				$parts[$i] = '00';

		# Return formatted result
		return "$parts[0]:$parts[1]:$parts[2]";
	}

	/**
	* Parses an url using parse_url + parse_str
	* This is the inverse of buildUrl
	*
	* @see buildUrl()
	* @param $url string: The URL string
	* @return array: The URL array
	*/
	public static function parseUrl($url) {
		$parsed = parse_url($url);
		if( isset($parsed['query']) ) {
			$arr = array();
			parse_str($parsed['query'], $arr);
			$parsed['query'] = $arr;
		}
		return $parsed;
	}

	/**
	* Takes an url represented as array ann recompose it usign http_build_query
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
		if( isset($url['path']) )
			$parsed .= $url['path'];
		if( isset($url['query']) )
			$parsed .= '?' . $url['query'];
		if( isset($url['fragment']) )
			$parsed .= '#' . $url['fragment'];

		return $parsed;
	}

	/**
	* Returns a vormatted version of a time
	*
	* @param $str string: Time string in the format hh:mm:ss
	* @param $format string: Format string accepted by DateTime::format()
	* @return string: The formatted time
	*/
	public static function formatTime($str, $format='H:i') {
		$time = new \DateTime($str);
		return $time->format($format);
	}

	/**
	* Return a formatted version of a number
	*
	* @param $num int: The number to be formatted
	* @param $decimals int: Number of decimals
	* @param $dec_point str: Decimal point
	* @param $thousands_sep str: Thousands separator
	* @return string: The formatted version of the number
	*/
	public static function formatNumber($num, $decimals=2, $dec_point=',', $thousands_sep='.') {
		return number_format($num, $decimals, $dec_point, $thousands_sep);
	}

	/**
	* Return a formatted version of a boolean
	*
	* @return string: The formatted version of the boolean
	*/
	public static function formatBool($bool) {
		if( $bool === null )
			return null;
		return $bool ? 'Yes' : 'No';
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
	* @param $url Any url combination. Missing informations are filled with current
	*             URL's ones
	* @return string: The full page URL
	*/
	public static function fullUrl($url) {
		$url = self::parseUrl($url);

		if( ! isset($url['scheme']) )
			$url['scheme'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https' : 'http';
		if( ! isset($url['host']) ) {
			$url['host'] = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:$_SERVER['SERVER_NAME'];
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
		}

		return self::buildUrl($url);
	}

	/**
	* Returns the file name for a given page to be included
	*
	* @param $conf array: The configuration variable
	* @param $page string: The page name
	* @return string: The path
	*/
	public static function pagePath($conf, $page) {
		$base_file = basename($_SERVER['PHP_SELF'], '.php');
		return "{$conf['paths']['inc']}/$base_file.$page.php";
	}

	/**
	* Returns all request headers as associative array, on any webserver
	*/
	public static function headers() {
		if( function_exists('apache_request_headers') )
			return apache_request_headers();

		$headers = array();
		foreach( $_SERVER as $name => $value )
			if( substr($name,0,5) == 'HTTP_' ) {
				$h = str_replace(' ','-',ucwords(strtolower(str_replace('_',' ',substr($name,5))))); 
				$headers[$h] = $value;
			} else if( $name == 'CONTENT_TYPE' )
				$headers['Content-Type'] = $value;
			else if( $name == 'CONTENT_LENGTH' )
				$headers['Content-Length'] = $value;

		return $headers;
	}

	/**
	* Cleans an array according to template array
	*
	* @param $array array: The input array
	* @param $template array: Associative array <key>=><type>
	*                         If key is (int)0 multiple items are expected
	*                         If type is array, a sub-array is expected
	*
	* @return array: the parsed array. Cointains the keys specified on both
	*                input and template, type is casted according to template.
	*/
	public static function cleanArray($array, $template) {
		$out = array();
		foreach( $template as $k => $t )
			if( $k === 0 ) {
				foreach( $array as $n => $v )
					if( is_int($n) )
						$out[$n] = self::cleanArray($v, $t);
			}elseif( array_key_exists($k, $array) ) {
				if( is_array($t) )
					$out[$k] = self::cleanArray($array[$k], $t);
				elseif( $array[$k] === null )
					$out[$k] = null;
				else
					switch($t) {
					case 'string':
						$out[$k] = (string)$array[$k];
						break;
					case 'int':
						$out[$k] = (int)$array[$k];
						break;
					case 'double':
						$out[$k] = (double)$array[$k];
						break;
					case 'bool':
						$out[$k] = (bool)$array[$k];
						break;
					default:
						throw new Exception("Uknown type $t");
					}
			}
		return $out;
	}

	/**
	* Determines the language to be used based on Accept-Language HTTP header
	*
	* @param $supported array: List of supported languages, in the form
	*                          'en' or 'it_IT'
	* @return The preferred language, if any matched, or the first of the
	*         supported ones, if no match found
	*/
	public static function getBrowserLanguage($supported) {
		if( ! array_key_exists('HTTP_ACCEPT_LANGUAGE',$_SERVER) || ! $_SERVER['HTTP_ACCEPT_LANGUAGE'] )
			return $supported[0];

		// Prepare a sorted array of accepted languages in the format
		// [ [<name>,<country>], <quality> ], ...
		// es. [ ['en', null], 1.0 ], [ ['it', 'IT'], 0.8 ], ...
		$prefs = explode(',', trim($_SERVER['HTTP_ACCEPT_LANGUAGE']));
		foreach( $prefs as & $p ) {
			$p = array_slice(explode(';', $p), 0, 2);

			if( count($p) < 2 )
				$p[1] = 1.0;
			else
				$p[1] = (float)trim($p[1], " \t\n\r\0\x0Ba..zA..Z=");

			$p[0] = array_slice(explode('-', trim($p[0])), 0, 2);
			$p[0][0] = strtolower(trim($p[0][0]));
			if( count($p[0]) > 1 )
				$p[0][1] = strtoupper(trim($p[0][1]));
			else
				$p[0][1] = null;
		}
		function cmp($a, $b) {
			if($a[1] < $b[1])
				return -1;
			if($a[1] > $b[1])
				return 1;
			return 0;
		}
		usort($prefs, 'dophp\\cmp');

		// Does the matching
		$partial_match = null;
		$sup_low = array_map('strtolower', $supported);
		function sub(& $item) {
			$item = substr($item, 0, 2);
		}
		$sup_sub = $sup_low;
		array_walk($sup_sub, 'dophp\\sub');
		foreach( $prefs as $p ) {
			$lang = strtolower($p[0][0] . ( $p[0][1] ? '_'.$p[0][1] : '' ));

			if( $idx = array_search($lang, $sup_low) )
				return $supported[$idx]; //Exact match

			if( ! $partial_match && strlen($lang) == 2 && array_search($lang, $sup_sub) )
				$partial_match = $supported[$idx]; //Matched only language code
		}
		if( $partial_match )
			return $partial_match;

		// Nothing found
		return $supported[0];
	}

	/**
	* Find a class name case-insensitive
	*
	* @param $name The class name to search for
	* @return string: The found class name, null on failure
	*/
	public static function findClass($name) {
		$classes = get_declared_classes();
		foreach( $classes as $c )
			if( strtolower($c) == strtolower($name) )
				return $c;
		return null;
	}

}
