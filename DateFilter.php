<?php declare(strict_types = 1);

/**
* @file DateFilter.php
* @author Giorgio Sanfilippo <sanfilippogiorgio@gmail.com>
* @package DoPhp
* @brief Classes for handling Date Filter
*/

namespace dophp;


/**
* Define a date filter
*
*/
class DateFilter implements \JsonSerializable {

	const PRECISON_Y = 'y';
	const PRECISON_MY = 'my';
	const PRECISON_DMY = 'dmy';
	const DATE_SEPARATORS = ['.', '-', '/'];

	/// The date where filter start
	protected $_startDate = null;
	/// The date where filter end
	protected $_endDate = null;
	// true if the dateFilter is valid
	protected $_valid = true;

	/**
	* Constructs the DateFilter
	*
	* @param $search string: The string defining the filter parameters ('10.2018,11.2018' '19' '01.10.2010')
	* @param $divider The char/string used to separate two dates from a period
	*/
	public function __construct(string $search, string $divider=',') {

		// Parse the $search string, splitting in 1 or 2 dates
		$count = substr_count($search, $divider);

		switch($count) {
		case 0:
			$startDate = trim($search);
			$endDate = null;
		break;
		case 1:
			list($startDate, $endDate) = explode($divider, trim($search));
			$startDate = trim($startDate);
			$endDate = trim($endDate);
		break;
		default:
			$this->_valid = false;
			return;
		}

		// The filter set automatically the missing date, calculating it from the given date and precision
		// If precion it's PRECISON_DMY, leave the missing date as null, to implement 'from' and 'until'

		if((is_null($startDate) || $startDate == '') && (is_null($endDate) || $endDate == ''))
			throw new \InvalidArgumentException('Date filter need at least one date');

		if(is_null($startDate) || $startDate == '') {
			$this->_startDate = self::strToDateWithPrecision($endDate, true);
			if (!is_null($this->_startDate) && $this->_startDate->getPrecision() == self::PRECISON_DMY)
				$this->_startDate = null;
		}
		else
			$this->_startDate = self::strToDateWithPrecision($startDate, true);

		if(is_null($endDate) || $endDate == '') {
			$this->_endDate = self::strToDateWithPrecision($startDate, false);
			if (!is_null($this->_endDate) && $this->_endDate->getPrecision() == self::PRECISON_DMY)
				$this->_endDate = null;
		}
		else
			$this->_endDate = self::strToDateWithPrecision($endDate, false);

		if(is_null($this->_startDate) && is_null($this->_endDate))
			$this->_valid = false;

	}

	public function getStartDate(): ?DateWithPrecision {
		return $this->_startDate;
	}

	public function getEndDate(): ?DateWithPrecision {
		return $this->_EndDate;
	}

	public function isValid(): bool{
		return $this->_valid;
	}

	/**
	 * Returns the date separator found in the given unformatted $date
	 *
	 * @param $date string: The unformatted filter date (eg. 2018, 10.2018, 01-11-2018)
	 *
	 * @return string The found date separator, if it is in DATE_SEPARATORS array, false otherwise
	 *
	 */
	protected static function getDateSeparatot(string $date) {
		foreach (self::DATE_SEPARATORS as $ds)
			if (strpos($date, $ds) !== false )
				return $ds;
		return false;
	}

	/**
	 * Returns an array of strings representing the given unformatted $date
	 *
	 * @param $date string: The unformatted filter date (eg. 2018, 10.2018, 01-11-2018)
	 *
	 * @return array An array of strings representing the give date
	 *
	 */
	protected static function getSplittedDate(string $date): array {

		$ds = self::getDateSeparatot($date);
		if($ds)
			return explode($ds, $date);
		else
			return [$date];
	}

	/**
	 * Returns date precision as string from given $date
	 *
	 * Supported date formats
	 * 18, 2018
	 * 1.18, 10.18, 1.2018, 10.2018
	 * 1.1.18, 1.10.18, 10.1.18, 10.10.18
	 * 1.1.2018, 1.10.2018, 10.1.2018, 10.10.2018
	 *
	 * @param $date string: The unformatted filter date (eg. 2018, 10.2018, 01-11-2018)
	 *
	 * @return string date precision as string
	 * @throws WrongFormatException
	 */
	protected static function getDatePrecision(string $date): string {

		$separator = self::getDateSeparatot($date);

		if(!$separator)
			return self::PRECISON_Y;

		$count = substr_count($date, $separator);

		switch ($count) {
			case 1:
				return self::PRECISON_MY;
			break;
			case 2:
				return self::PRECISON_DMY;
			break;
			default:
				throw new WrongFormatException('Wrong Date Format');
		}
	}


	/**
	 * Returns true if the given date it's well formed
	 *
	 * eg. true: '19', '1.18', '2-10-2018'
	 * eg. false: '9', 'a', '10.209', '01-011-2019'
	 *
	 * @param string $date: The date to check validity (eg. 2018, 10.2018, 01-11-2018)
	 * @param bool $precision: the date precision
	 *
	 * @return bool true if it's a supported date format, false otherwise
	 *
	 */

	protected static function isValidYear(string $year) : bool {
		$len = \strlen($year);
		// Check if the year is numeric and it's 2 o 4 digit
		if(($len != 2 && $len != 4 ) || !\is_numeric($year))
			return false;
		return true;
	}

	protected static function isValidMonth(string $month) : bool {
		// Check if $month is numeric
		if(!\is_numeric($month))
			return false;
		// Check if the $month is 2 o 4 digit
		$len = \strlen($month);
		if($len < 1 || $len >2 )
			return false;
		$monthNum = \intval($month);
		if($monthNum < 1 || $monthNum > 12)
			return false;
		return true;
	}

	protected static function isValidDay(string $day) : bool {
		// Check if $day is numeric
		if(!\is_numeric($day))
			return false;
		// Check if the $day is 2 o 4 digit
		$len = \strlen($day);
		if($len < 1 || $len >2 )
			return false;
		$dayNum = \intval($day);
		if($dayNum < 1 || $dayNum > 31)
			return false;
		return true;
	}

	/**
	 * Returns true if the given $date it's formatted like the supported formats
	 *
	 * @param string $date: The date to be validated (eg. 2018, 10.2018, 01-11-2018)
	 * @param string $precision: The precisiono of the given $date
	 *
	 * @return bool: true if all the parts of the $date are in the right format
	 *
	 */
	protected static function validateFormat($date, $precision) : bool {

		switch($precision) {
		case self::PRECISON_Y :
			return self::isValidYear($date);
		break;

		case self::PRECISON_MY :
			$splitDate = self::getSplittedDate($date);
			return self::isValidMonth($splitDate[0]) && self::isValidYear($splitDate[1]);
		break;

		case self::PRECISON_DMY :
			$splitDate = self::getSplittedDate($date);
			return
				self::isValidDay($splitDate[0]) &&
				self::isValidMonth($splitDate[1]) &&
				self::isValidYear($splitDate[2]);
		break;

		default :
			return false;
		}

	}

	/**
	 * Returns a new DateTime object from given string $date, according to $isStart parameter
	 *
	 * @param string $date: The unformatted filter date (eg. 2018, 10.2018, 01-11-2018)
	 * @param bool $isStart: It's true if the $date have to be considered a start date
	 *
	 * @return DateTime object from the given $date according to $isStart parameter or
	 * null if $date format is wrong or not supported
	 *
	 */
	public static function formatDate(string $date, bool $isStart = true): ?\DateTime {

		// Supported date formats
		// 18, 2018, 1.18, 10.18, 1.2018, 10.2018
		// 1.1.18, 1.10.18, 10.1.18, 10.10.18
		// 1.1.2018, 1.10.2018, 10/1/2018, 10-10-2018

		$df = self::getDatePrecision($date);

		// Check if $date it's well formed (eg. not well formed date 201.1.2019)
		if(!self::validateFormat($date, $df))
			return null;

		switch($df) {
		case self::PRECISON_Y :
			$year = $date;
			if(strlen($year) == 2)
				$year = \DateTime::createFromFormat('y', $year)->format('Y');
			return $isStart ?
				new \DateTime('first day of January '.$year) :
				new \DateTime('last day of December '.$year);
		break;

		case self::PRECISON_MY :
			$splitDate = self::getSplittedDate($date);
			$year = $splitDate[1];
			if(strlen($year) == 2)
				$year = \DateTime::createFromFormat('y', $year)->format('Y');
			$monthName = \DateTime::createFromFormat('!m', $splitDate[0])->format('F');

			return $isStart ?
				new \DateTime('first day of '.$monthName.' '. $year) :
				new \DateTime('last day of '.$monthName.' '. $year);
		break;

		case self::PRECISON_DMY :
			$splitDate = self::getSplittedDate($date);
			$year = $splitDate[2];
			if(strlen($year) == 2)
				$year = \DateTime::createFromFormat('y', $year)->format('Y');
			$monthName = \DateTime::createFromFormat('!m', $splitDate[1])->format('F');
			return \DateTime::createFromFormat('j-F-Y',  $splitDate[0].'-'.$monthName.'-'.$year);
		break;
		}

		throw new \dophp\NotImplementedException("Unsupported format $df");
	}

	/**
	 * Returns a new DateWithPrecision object from given string $date, according to $isStart parameter
	 *
	 * @param $date string: The unformatted filter date (eg. 2018, 10.2018, 01-11-2018)
	 * @param $isStart bool: It's true if the $date have to be considered a start date
	 *
	 * @return DateWithPrecision or null on error
	 *
	 */
	public static function strToDateWithPrecision(string $date, bool $isStart): ?DateWithPrecision {

		$prec = self::getDatePrecision($date);
		$formattedDate = self::formatDate($date, $isStart);

		return !is_null($formattedDate) ? new DateWithPrecision($formattedDate, $prec) : null;
	}

	public function jsonSerialize() {
		$json = array();
		foreach($this as $key => $value) {
			$json[$key] = $value;
		}
		return $json;
	}

	/**
	 * Returns the SQL code and params for building a date filter
	 *
	 * When this is not valid or empty, returns null instead of SQL
	 *
	 * @param $columnName string: The column to search into (already quoted if needed)
	 * @param $parPrefix string: The prefix for parameters (must begin with ':')
	 *
	 * @return [ string: sql or null, array: associative array of params ]
	 */
	public function getSqlSearchFilter(string $columnName, string $parPrefix=':dateFilter_'): array {
		if( ! $this->isValid() )
			return [ null, [] ];

		if( $this->_startDate && $this->_endDate )
			return [
				"$columnName BETWEEN {$parPrefix}start AND {$parPrefix}end",
				[ "{$parPrefix}start" => $this->_startDate, "{$parPrefix}end" => $this->_endDate ]
			];

		if( $this->_startDate )
			return [
				"$columnName >= {$parPrefix}start",
				[ "{$parPrefix}start" => $this->_startDate ]
			];

		if( $this->endDate )
			return [
				"$columnName <= {$parPrefix}end",
				[ "{$parPrefix}end" => $this->_endDate ]
			];

		// Empty / no filter
		return [ null, [] ];
	}
}


/**
* Define a date class with precision level
*
*/
class DateWithPrecision extends \dophp\Date implements \JsonSerializable {

	const SUPPORTED_PRECISON = [
		DateFilter::PRECISON_Y,
		DateFilter::PRECISON_MY,
		DateFilter::PRECISON_DMY
	];

	protected $_precision;

	public function __construct(\DateTime $date, string $precision) {

		if(!in_array($precision, self::SUPPORTED_PRECISON))
			throw new UnsupportedPrecisionException();

		$this->_precision = $precision;
		parent::__construct($date);
	}

	public function getPrecision() {
		return $this->_precision;
	}

	public function setPrecision($precision) {
		if(!in_array($precision, self::SUPPORTED_PRECISON))
			throw new UnsupportedPrecisionException();
		$this->_precision = $precision;
	}

	public function jsonSerialize() {
		$json = array();
		foreach($this as $key => $value) {
			$json[$key] = $value;
		}
		return $json;
	}

}


class UnsupportedPrecisionException extends \Exception {}
class WrongFormatException extends \Exception {}
