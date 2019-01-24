<?php

/**
* @file Utils.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Miscellaneous utility functions
*/

namespace dophp;

class Utils {

	/** Formatted version of NULL, for internal usage */
	const NULL_FMT = '-';

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
	* Returns a formatted version of a time
	*
	* @param $str string: Time string in the format hh:mm:ss
	* @param $format string: Format string accepted by DateTime::format()
	* @return string: The formatted time
	*/
	public static function formatTime($str, $format='H:i') {
		if( $str === null )
			return self::NULL_FMT;

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
	public static function formatNumber($num, $decimals=null, $dec_point=',', $thousands_sep='.') {
		if( $num === null )
			return self::NULL_FMT;
		if( $num instanceof Decimal )
			$num = $num->toDouble();

		if( $decimals === null )
			$decimals = self::guessDecimals($num);

		return number_format($num, $decimals, $dec_point, $thousands_sep);
	}

	/**
	 * Return a formatted version of a currency
	 *
	 * @see self::formatNumber
	 * @return string: ie. "€ 1.000,00"
	 */
	public static function formatCurrency($num, $symbol='€', $decimals=2, $dec_point=',', $thousands_sep='.') {
		return $symbol . ' ' . self::formatNumber($num, $decimals, $dec_point, $thousands_sep);
	}

	/**
	* Return a formatted version of a boolean
	*
	* @return string: The formatted version of the boolean
	*/
	public static function formatBool($bool) {
		if( $bool === null )
			return self::NULL_FMT;

		return $bool ? _('Yes') : _('No');
	}

	/**
	 * Returns a formatted version of given Exception
	 *
	 * Also tries to add useful extra data, like th4e last executed query for an
	 * SQL exception.
	 *
	 * @warning The output may contain sensitive data!
	 * @param $exception Exception: The exception
	 * @param $html bool: If true, will format as HTML
	 * @return string: text or html
	 */
	public static function formatException($exception, $html=false) {
		if( $exception === null )
			return self::NULL_FMT;

		$err =
			'<h3>' . htmlentities(get_class($exception)) . "</h3>\n" .
			'<p>&quot;' . htmlentities($exception->getCode()) . '.' . htmlentities($exception->getMessage()) . "&quot;</p>\n" .
			'<ul>' .
			'<li><b>File:</b> ' . htmlentities($exception->getFile()) . "</li>\n" .
			'<li><b>Line:</b> ' . htmlentities($exception->getLine()) . "</li>\n" .
			'<li><b>Trace:</b> ' . nl2br(htmlentities($exception->getTraceAsString())) . "</li>";

		// Add extra useful information
		try {
			$db = \DoPhp::db();
		} catch( \Exception $e ) {
			$db = null;
		}

		if( $db && ( $exception instanceof \PDOException
				|| $exception instanceof \dophp\StatementExecuteError ) ) {
			$err .= "\n<li><b>Last Query:</b> " . $db->lastQuery . "</li>\n" .
				'<li><b>Last Params:</b> ' . nl2br(print_r($db->lastParams,true)) . "</li>\n";
		}

		$err .= '</ul>';

		if( $html )
			return $err;

		return strip_tags(html_entity_decode($err));
	}

	/**
	* Format a value in a nice human-readable form based on value type or class
	*
	* @return string: The formatted value
	*/
	public static function format($value) {
		$type = gettype($value);
		$lc = localeconv();

		if( $type == 'NULL' )
			$val = self::NULL_FMT;
		elseif( $type == 'string' )
			$val = $value;
		elseif( $value instanceof Time )
			$val = $value->format('H:i:s');
		elseif( $value instanceof Date )
			$val = $value->format('d.m.Y');
		elseif( $value instanceof \DateTime )
			$val = $value->format('d.m.Y H:i:s');
		elseif( $type == 'boolean' )
			$val = self::formatBool($value);
		elseif( $type == 'integer' )
			$val = self::formatNumber($value, 0, $lc['decimal_point'], $lc['thousands_sep']);
		elseif( $value instanceof Decimal )
			$val = $value->format(null, $lc['decimal_point'], $lc['thousands_sep']);
		elseif( $type == 'double' )
			$val = self::formatNumber($value, null, $lc['decimal_point'], $lc['thousands_sep']);
		else
			throw new \Exception("Unsupported type $type class " . get_class($value));

		return $val;
	}

	/**
	 * Guesses the number of decimals in the given number
	 *
	 * @return int: The number of decimals
	 */
	public static function guessDecimals($num) {
		if( gettype($num) == 'integer' )
			return 0;

		Lang::pushLocale(LC_NUMERIC);
		$numStr = (string)$num;
		Lang::popLocale();

		$dot = strpos($numStr, '.');
		if( $dot === false )
			return 0;

		return strlen($numStr) - $dot - 1;
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
	* @todo Merge with Validator
	* @param $array array: The input array
	* @param $template array: Associative array <key>=><type>
	*                         If key is (int)0 multiple items are expected
	*                         If type is array, a sub-array is expected
	*
	* @return array: the parsed array. Cointains the keys specified on both
	*                input and template, type is casted according to template.
	*/
	public static function cleanArray(& $array, $template) {
		$out = array();
		foreach( $template as $k => $t )
			if( $k === 0 ) {
				foreach( $array as $n => $v )
					if( is_int($n) ) {
						if( is_array($t) )
							$out[$n] = self::cleanArray($v, $t);
						else
							$out[$n] = self::cleanValue($v, $t);
					}
			} elseif( array_key_exists($k, $array) ) {
				if( is_array($t) )
					$out[$k] = self::cleanArray($array[$k], $t);
				elseif( $array[$k] === null )
					$out[$k] = null;
				else
					$out[$k] = self::cleanValue($array[$k], $t);
			}
		return $out;
	}

	/**
	* Cleans a single value according to format
	*
	* @todo Merge with Validator
	* @param $value string: The input value
	* @param $type string: The type (null = bypass cleaning)
	* @return mixed: The correctly typed value
	*/
	public static function cleanValue(& $value, $type) {
		switch($type) {
		case 'string':
			return (string)$value;
		case 'int':
			return (int)$value;
		case 'double':
			return (double)$value;
		case 'bool':
			return (bool)$value;
		case null:
			return null;
		default:
			throw new \Exception("Uknown type $t");
		}
	}

	/**
	* Determines the language to be used based on Accept-Language HTTP header
	*
	* @param $supported array: List of supported languages, in the form
	*                          'en' or 'it_IT'
	* @return string: The preferred language, if any matched, or the first of
	*         the supported ones, if no match found
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
	* @param $name string: The class name to search for
	* @return string: The found class name, null on failure
	*/
	public static function findClass($name) {
		$classes = get_declared_classes();
		foreach( $classes as $c )
			if( strtolower($c) == strtolower($name) )
				return $c;
		return null;
	}

	/**
	* Makes a string filename-safe by replacing unsafe characters
	* Uses the most wide range un unsafe characters possible
	*
	* @param $name string: The input string
	* @param $replace char: The replace character
	* @return string: The sanitized string
	*/
	public static function safeFileName($name, $replace='_') {
		$unsafe = array('/','\\','?','<','>',':','*','|','^',"\x7f");
		for( $i=0; $i<=0x1f; $i++)
			$unsafe[] = chr($i);
		return str_replace($unsafe, $replace, $name);
	}

	/**
	* Returns distance between two points on a sphere
	*
	* @param $lat1 double: Latitude of first point in degress
	* @param $lng1 double: Longitude of first point in degress
	* @param $lat2 double: Latitude of second point in degress
	* @param $lng2 double: Longitude of second point in degress
	*
	* @return double: distance in degrees (1 degree = 60 nautical miles)
	*/
	public static function distance($lat1, $lng1, $lat2, $lng2) {
		$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lng1-$lng2));
		$dist = acos($dist);
		$dist = rad2deg($dist);

		return $dist;
	}

	/**
	* Returns the raw input data, after decoding it
	*
	* @return string: The raw decoded input
	*/
	public static function decodeInput() {
		if( isset($_SERVER['HTTP_CONTENT_ENCODING']) && $_SERVER['HTTP_CONTENT_ENCODING'] == 'gzip' ) {
			$input = gzdecode(file_get_contents("php://input"));
			if( $input === false )
				throw new PageError('Couldn\'t decode gzip input');
		} else
			$input = file_get_contents("php://input");

		return $input;
	}

	/**
	 * Returns list of accepted HTTP encodings based on the Accept header
	 *
	 * @yields string: Accepted HTTP encodings, sorted by preference
	 */
	public static function listHttpAccept() {
		if( ! isset($_SERVER['HTTP_ACCEPT']) )
			return;

		$encodings = [];
		// first iterate encodings
		foreach( explode(',', $_SERVER['HTTP_ACCEPT']) as $astr ) {
			// then seperate params
			$aspl = explode(';', $astr, 2);
			$encoding = trim($aspl[0]);

			if( isset($aspl[1]) ) {
				$matches = [];
				$m = preg_match('/^\s*q=([0-9.]+)\s*$/', $aspl[1], $matches);
				if( ! $m )
					throw new \Exception("Could not decode Accept params: \"$aspl[1]\"");
				$pri = (double)$matches[1];
			} else
				$pri = 1;

			$encodings[$encoding] = $pri;
		}

		asort($encodings);

		foreach( $encodings as $encoding => $pri )
			yield $encoding;
	}

	/**
	 * Tells whether a given encoding is accepted
	 *
	 * @param $encoding string: The encoding to check for
	 * @return bool
	 */
	public static function isAcceptedEncoding($encoding) {
		foreach( self::listHttpAccept() as $enc ) {
			if( $enc == $encoding )
				return true;
			if( substr($enc, -1) == '*' ) {
				$base = substr($enc, 0, -1);
				if( substr($encoding, 0, strlen($base)) == $base )
					return true;
			}
		}
		return false;
	}

	/**
	 * Escape a string to be included in a Javascript Template Literal
	 *
	 * @param $html string: The code to be escaped
	 * @return string
	 */
	public static function escapeJsTpl($html) {
		$html = str_replace('`', '\`', $html);
		$html = str_replace('</script>', '</scr`+`ipt>', $html);
		return $html;
	}

	/**
	 * Returns decoded POST data, tries to automaticaly guess it from
	 * HTML headers
	 */
	public static function getPostData() {
		$headers = self::headers();

		if ( ! isset($headers['Content-Type']) )
			throw new \Exception('Missing Content-Type header');

		$parts = explode(';', $headers['Content-Type']);
		$ctype = trim($parts[0]);

		switch ($ctype) {
		case 'application/json':
			// Decode JSON
			return json_decode(self::decodeInput(), true);
		case 'multipart/form-data':
		case 'application/x-www-form-urlencoded':
			// This is decoded builtin
			return $_POST;
		}

		throw new \Exception("Unsupported Content-Type \"$ctype\"");
	}

	/**
	 * Converts a float to string non locale-aware
	 */
	public static function formatCFloat($float) {
		return sprintf('%F', $float);
	}
}
