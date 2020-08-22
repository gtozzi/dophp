<?php

/**
* @file Imager.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Image caching manipulator
*/

namespace dophp;


/**
* DoPhp's standalone image caching maniuplator
*
* Performs simple image tasks, caches and server the result
*/
class Imager {

	/** The image directory */
	protected $_imgFolder;
	/** The cache directory */
	protected $_cacheFolder;
	/** The image name */
	protected $_imgName;

	/**
	* Initialize the imager
	*
	* @param $imgFolder string: The directory where to read images from
	* @param $cacheFolder string: The directory to use for caching images
	*/
	public function __construct($imgFolder, $cacheFolder) {
		$this->_imgFolder = $imgFolder;
		$this->_cacheFolder = $cacheFolder;
		if( ! is_dir($this->_imgFolder) ) {
			header("HTTP/1.1 500 Internal Server Error");
			throw new \LogicException('Invalid image folder');
		}
		if( ! is_dir($this->_cacheFolder) ) {
			header("HTTP/1.1 500 Internal Server Error");
			throw new \LogicException('Invalid cache folder');
		}
	}

	/**
	* Processes GET data
	*
	* Recognized GET options:
	* - name: The relative file name of the image to load, mandatory
	* - fill: Fills the image with a gradient rectangle x1,y1,x2,y2,c1,c2
	*
	* @param $get array: Process parameters, usually $_GET
	*/
	public function process(& $get) {
		if( ! isset($get['name']) ) {
			header("HTTP/1.1 400 Bad Request");
			throw new \LogicException('Missing image name');
		}
		$this->_imgName = $get['name'];
		if( substr($this->_imgName, 0, strlen($this->_imgFolder)) == $this->_imgFolder)
			$this->_imgName = substr($this->_imgName, strlen($this->_imgFolder));
		$path = $this->_imgFolder . '/' . $this->_imgName;
		if( ! is_file($path) ) {
			header("HTTP/1.1 404 Not Found");
			throw new \UnexpectedValueException('Image not found');
		}
		if( strpos(realpath($path), realpath($this->_imgFolder)) !== 0 ) {
			header("HTTP/1.1 403 Forbidden");
			throw new \UnexpectedValueException('Image is out of authorized path');
		}

		// Check if image is cached, if yes, does nothing
		$outFile = $this->_getCachePath();
		if( file_exists($outFile) && filemtime($outFile) >= filemtime($path) )
			return;

		// Load the image
		$finfo = getimagesize($path);
		$width = $finfo[0];
		$height = $finfo[1];
		switch($finfo['mime']) {
		case 'image/jpeg':
			$img = imagecreatefromjpeg($path);
			break;
		default:
			header("HTTP/1.1 415 Unsupported Media Type");
			throw new \dophp\NotImplementedException("Unsupported image type {$finfo['mime']}");
		}
		if( ! $img ) {
			header("HTTP/1.1 500 Internal Server Error");
			throw new \RuntimeException('Error loading image');
		}

		// Parses parameters
		$params = array();
		if( isset($get['fill']) ) {
			$par = array('x1','y1','x2','y2','color1','color2');
			$fill = $this->_parseParams($par, $get['fill']);
			$params['fill'] = array(
				'x1' => $this->_parsePosition($width, $fill['x1']),
				'y1' => $this->_parsePosition($height, $fill['y1']),
				'x2' => $this->_parsePosition($width, $fill['x2']),
				'y2' => $this->_parsePosition($height, $fill['y2']),
				'c1' => $this->_parseColor($fill['color1']),
				'c2' => $this->_parseColor($fill['color2']),
			);
		}

		// Perform fill transformations
		if( isset($params['fill']) ) {
			extract($params['fill']);
			for($y=$y1; $y<=$y2; $y++) {
				// Calculate the gradient color
				$pct = ($y-$y1) / ($y2-$y1);
				$c = array();
				for($i=0; $i<=3; $i++)
					$c[$i] = round( $c1[$i] + ($c2[$i]-$c1[$i]) * $pct );
				$col = imagecolorallocatealpha($img, $c[0], $c[1], $c[2], $c[3]);
				if( $col === false ) {
					header("HTTP/1.1 500 Internal Server Error");
					throw new \RuntimeException("Color ($c[0],$c[1],$c[2],$c[3]) allocation failed");
				}
				if( ! imagefilledrectangle($img, $x1, $y, $x2, $y+1, $col) ) {
					header("HTTP/1.1 500 Internal Server Error");
					throw new \RuntimeException('Fill failed');
				}
			}
		}

		// Save the file to cache
		switch($finfo['mime']) {
		case 'image/jpeg':
			imagejpeg($img, $outFile);
			return;
		default:
			header("HTTP/1.1 415 Unsupported Media Type");
			throw new \dophp\NotImplementedException("Unsupported image type {$finfo['mime']}");
		}
	}

	/**
	* Streams the result
	*/
	public function stream() {
		$out = $this->_getCachePath();
		$finfo = new \finfo(FILEINFO_MIME_TYPE);

		header('Content-Type: ' . $finfo->file($out));
		readfile($out);
	}

	/**
	* Parses parameters from a comma-separated string returning an associative array
	*/
	protected function _parseParams($names, $value) {
		$exp = explode(',', $value);
		if( count($exp) != count($names) ) {
			header("HTTP/1.1 400 Bad Request");
			throw new \InvalidArgumentException('Invalid param count (' . count($exp) . '/' . count($names) . ')');
		}

		return array_combine($names, $exp);
	}

	/**
	* Parses a relative or absolute position
	*/
	protected function _parsePosition($size, $value) {
		if( substr($value,-1) == '%' )
			return (int)($size / 100 * substr($value,0,-1));
		return (int)$value;
	}

	/**
	* Parses an hex color
	*
	* @return array <r>,<g>,<b>,<a>
	*/
	protected function _parseColor($hex) {
		$hex = strtolower($hex);
		if( strlen($hex) != 8 ) {
			header("HTTP/1.1 400 Bad Request");
			throw new \UnexpectedValueException('Invalid color');
		}
		$ret = array_map(hexdec, str_split($hex, 2));
		$ret[3] = floor($ret[3] / 2);
		return $ret;
	}

	/**
	* Returns cache path for current image
	*/
	protected function _getCachePath() {
		if( ! $this->_imgName )
			throw new \LogicException('Must process() first');
		$pinfo = pathinfo($this->_imgName);
		$fname = $this->_sanitize($pinfo['filename']);
		$query = array();
		parse_str($_SERVER['QUERY_STRING'], $query);
		unset($query['name']);
		$query = $this->_sanitize(serialize($query));
		return "{$this->_cacheFolder}/$fname@$query.{$pinfo['extension']}";
	}

	/**
	* Sanitize a string to be filename-safe
	*/
	protected function _sanitize($str) {
		return str_replace(array('/','\\'), '_-_', $str);
	}

}
