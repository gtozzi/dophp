<?php

/**
* @file Utils.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Miscellaneous utility functions
*/

namespace dophp;

class Utils {

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
	* @param $page string: The page name
	* @param $key string: The name of the page parameter
	* @return string: The Full page URL
	*/
	public static function fullPageUrl($page, $key='do') {
		$s = & $_SERVER;
		$ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on') ? true : false;
		$sp = strtolower($s['SERVER_PROTOCOL']);
		$protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
		$port = $s['SERVER_PORT'];
		$port = ((!$ssl && $port=='80') || ($ssl && $port=='443')) ? '' : ':'.$port;
		$host = isset($s['HTTP_X_FORWARDED_HOST']) ? $s['HTTP_X_FORWARDED_HOST'] : isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : $s['SERVER_NAME'];
		$host = explode(':', $host);
		$host = $host[0];
		$uri = $protocol . '://' . $host . $port . $s['REQUEST_URI'];
		$segments = explode('?', $uri, 2);
		$url = $segments[0];
		return "$url?$key=$page";
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
	* @param $array array: The input array
	* @param $template array: Associative array <key>=><type>
	*
	* @return array: the parsed array. Cointains the keys specified on both
	*                input and template, type is casted according to template.
	*/
	public static function cleanArray($array, $template) {
		$out = array();
		foreach( $template as $k => $t )
			if( array_key_exists($k, $array) )
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
		return $out;
	}
}
