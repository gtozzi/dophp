<?php

/**
* @file Utils.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Miscellaneous utility functions
*/

namespace dophp;

require_once 'Exceptions.php';


class Utils {

	/** Formatted version of NULL, for internal usage */
	const NULL_FMT = '-';

	/** Octet Stream MIME Type */
	const MIME_OCTET_STREAM = 'application/octet-stream';

	/** Proper DOCX MIME Type */
	const MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

	/**
	* Given a file by its path, it retrieves the MIME content type using the
	* default php mime_content_type(). The latter however could return
	* self::MIME_OCTET_STREAM in case of a .docx file path is passed. This gets
	* fixed by opening the file as a zip archive and checking if it contains
	* all the required stuff for having a MIME type such as self:MIME_DOCX and
	* in that case, it returns it.
	*
	* @param  $filePath string: Path of the file
	* @return string: The file's MIME type
	*/
	public static function mime_content_type(string $filePath) : string {
		$mime = mime_content_type($filePath);
		if ( $mime != self::MIME_OCTET_STREAM)
			return $mime;

		$zip = new \ZipArchive();
		if ( $zip->open($filePath) !== true )
			return $mime;
		$foundFolder = false;
		$foundFile = false;
		for ( $i = 0; $i < $zip->numFiles && ( ! $foundFolder || ! $foundFile ); $i++) {
			$elementName = $zip->getNameIndex($i);
			if ( substr($elementName, 0, 5) === 'word/' )
				$foundFolder = true;
			elseif ( $elementName === '[Content_Types].xml' )
				$foundFile = true;
		}
		return $foundFolder && $foundFile ? self::MIME_DOCX : $mime;
	}

	/** Default ports used for URL protocols */
	public static $DEFAULT_PORTS = array(
		'http' => 80,
		'https' => 443,
		'ftp' => 21,
	);

	/**
	 * Returns all possible combinations of the given length from array values
	 *
	 * @example Utils::combinations(['A','B','C'], 2) = ['A','B'], ['A','C'], ['B','C']
	 * @param $in mixed: Input to make combinations of (array or string)
	 * @param $num int: number of elements per combinations
	 * @yields combined elements
	 */
	public static function combinations($in, $num) {
		if( is_array($in) )
			$in = array_values($in);
		else
			$in = str_split($in);
		$ilen = count($in);

		if( $num < 1 || $num > $ilen )
			throw new \InvalidArgumentException('num must be > 1 and < count($in)');

		$makeComb = function($keys) use ($in) {
			$ret = [];
			foreach( $keys as $k )
				$ret[] = $in[$k];
			return $ret;
		};

		// Initial combination
		$keys = [];
		for( $i = 0; $i < $num; $i++ )
			$keys[$i] = $i;
		yield $makeComb($keys);

		while( true ) {
			$k = $num - 1;
			$keys[$k]++;

			while( $k > 0 && $keys[$k] >= $ilen - $num + 1 + $k ) {
				$k--;
				$keys[$k]++;
			}

			if ($keys[0] > $ilen - $num) {
				// Combination (n-k, n-k+1, ..., n) reached
				// No more combinations can be generated
				break;
			}

			// comb now looks like (..., x, n, n, n, ..., n).
			// Turn it into (..., x, x + 1, x + 2, ...)
			for( $k = $k + 1; $k < $num; $k++ )
				$keys[$k] = $keys[$k - 1] + 1;

			yield $makeComb($keys);
		}
	}

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
			throw new \InvalidArgumentException("Unvalid number of parts: " . count($parts));

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
			'<h3>' . self::strAsHTML(get_class($exception)) . "</h3>\n" .
			'<p>&quot;' . self::strAsHTML($exception->getCode()) . '.' . self::strAsHTML($exception->getMessage()) . "&quot;</p>\n" .
			'<ul>' .
			'<li><b>File:</b> ' . self::strAsHTML($exception->getFile()) . "</li>\n" .
			'<li><b>Line:</b> ' . self::strAsHTML($exception->getLine()) . "</li>\n" .
			'<li><b>Trace:</b> ' . self::strAsHTML($exception->getTraceAsString()) . "</li>";

		// Add extra useful information
		try {
			$db = \DoPhp::db();
		} catch( DoPhpNotInitedException $e ) {
			$db = null;
		}

		if( $db && ( $exception instanceof \PDOException
				|| $exception instanceof \dophp\StatementExecuteError ) ) {
			$err .= "\n<li><b>Last Query:</b> " . self::strAsHTML($db->lastQuery) . "</li>\n" .
				'<li><b>Last Params:</b> ' . self::strAsHTML(print_r($db->lastParams,true)) . "</li>\n";
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
			throw new NotImplementedException("Unsupported type $type class " . get_class($value));

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
	* Returns all request headers as associative array, on any webserver
	*
	* @param $upper bool: If true, returns all headers keys as uppercase
	*/
	public static function headers($upper=false) {
		if( function_exists('apache_request_headers') ) {
			$ah = apache_request_headers();
			if( ! $upper )
				return $ah;

			$headers = [];
			foreach( $ah as $k => $h )
				$headers[strtoupper($k)] = $h;
			return $headers;
		}

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
		if( $type === null )
			return null;

		switch($type) {
		case 'string':
			return (string)$value;
		case 'int':
			return (int)$value;
		case 'double':
			return (double)$value;
		case 'bool':
			return (bool)$value;
		default:
			throw new NotImplementedException("Uknown type $t");
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

			if( $encoding == 'application/signed-exchange' ) {
				// Chrome is sending "application/signed-exchange;v=b3",
				// just ignoring it
				// https://wicg.github.io/webpackage/draft-yasskin-http-origin-signed-responses.html#application-signed-exchange
				$pri = 1;

			} elseif( isset($aspl[1]) ) {
				$matches = [];
				$m = preg_match('/^\s*q=([0-9.]+)\s*$/', $aspl[1], $matches);
				if( $m )
					$pri = (double)$matches[1];
				else {
					//TODO: Issue a DoPhp warning when implemented?
					error_log("Could not decode Accept params: \"$aspl[1]\" in \"{$_SERVER['HTTP_ACCEPT']}\"");
					$pri = 1;
				}

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
			throw new \UnexpectedValueException('Missing Content-Type header');

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

		throw new NotImplementedException("Unsupported Content-Type \"$ctype\"");
	}

	/**
	 * Converts a float to string non locale-aware
	 */
	public static function formatCFloat($float) {
		return sprintf('%F', $float);
	}

	/**
	 * Creates attachment headers
	 *
	 * @param $mime string: The mime content/type
	 * @param $filename string: The optional file name
	 * @return array: associative array of headers
	 */
	public static function makeAttachmentHeaders(string $mime, string $filename=null): array {
		$headers = [
			'Content-type' => $mime,
			'Content-Description' => 'File Transfer',
			'Content-Disposition' => 'attachment',
		];

		if( $filename )
			$headers['Content-Disposition'] .= "; filename*=UTF-8''" . rawurlencode($filename);

		return $headers;
	}

	/**
	 * Returns memory_limit ini setting in megabytes
	 *
	 * @return float: The current PHP memory limit, in megabytes
	 */
	public static function getMemoryLimitMb(): float {
		$memory_limit_txt = trim(ini_get('memory_limit'));
		$memory_limit = intval($memory_limit_txt);

		switch( substr($memory_limit_txt, -1) ) {
		case 'K':
			$memory_limit *= 1024;
			break;
		case 'M':
			$memory_limit *= 1024 ** 2;
			break;
		case 'G':
			$memory_limit *= 1024 ** 3;
			break;
		default:
			throw new \dophp\NotImplementedException("Unparsable memory limit value \"$memory_limit_txt\"");
		}

		return $memory_limit / 1024 ** 2;
	}

	/**
	 * Convert a raw text string into its HTML representation
	 */
	public static function strAsHTML(string $str): string {
		$str = nl2br(htmlentities($str));
		$str = str_replace("\t", '&emsp;', $str);
		return $str;
	}

	/**
	 * Returns true if a string starts with another string
	 *
	 * @param $haystack string: The string to search into
	 * @param $needle string: The string to search for
	 * @return true when $haystack starts with $needle, always false when $haystack is null
	 */
	public static function startsWith(string $haystack=null, string $needle): bool {
		if( $haystack === null )
			return false;

		$nlen = strlen($needle);

		if( substr($haystack, 0, $nlen) == $needle )
			return true;

		return false;
	}
}
