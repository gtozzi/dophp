<?php

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
class DateFilter {

	const PRECISON_Y = 'y';
	const PRECISON_MY = 'my';
	const PRECISON_DMY = 'dmy';
	const DATE_SEPARATOR = ['.', '-', '/'];

	protected $_startDate = null;
	protected $_endDate = null;

	/**
	* Constructs the DateFilter
	*
	* @param $startDate : The date where filter start
	* @param $endDate : The date where filter end
	*/
	public function __construct(string $startDate = null, string $endDate = null) {

		if(!is_null($startDate))
			$this->_startDate = self::strToDateWithPrecision($startDate, true);
		if(!is_null($endDate))
			$this->_endDate = self::strToDateWithPrecision($endDate, false);

	}

	public function getStartDate() {
		return $this->_startDate;
	}

	public function setStartDate(DateWithPrecision $date) {
		$this->_startDate = $date;
	}

	public function getEndDate() {
		return $this->_EndDate;
	}

	public function setEndDate(DateWithPrecision $date) {
		$this->_EndDate = $date;
	}

	/**
	 * Returns the date separator found in the given unformatted $date
	 *
	 * @param $date string: The unformatted filter date (eg. 2018, 10.2018, 01-11-2018)
	 *
	 * @return string The found date separator, if it is in DATE_SEPARATOR array, false otherwise
	 *
	 */
	protected static function getDateSeparatot(string $date) {

		foreach (self::DATE_SEPARATOR as $ds)
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
	 * @param $date string: The unformatted filter date (eg. 2018, 10.2018, 01-11-2018)
	 *
	 * @return string date precision as string
	 *
	 */
	protected static function getDatePrecision(string $date) {

		// Supported date formats
		// 18, 2018
		// 1.18, 10.18, 1.2018, 10.2018
		// 1.1.18, 1.10.18, 10.1.18, 10.10.18
		// 1.1.2018, 1.10.2018, 10.1.2018, 10.10.2018

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
				throw new Exception ('Wrong Date Format');
		}
	}

	/**
	 * Returns a new DateTime object from given string $date, according to $isStart parameter
	 *
	 * @param $date string: The unformatted filter date (eg. 2018, 10.2018, 01-11-2018)
	 * @param $isStart bool: It's true if the $date have to be considered a start date
	 *
	 * @return DateTime object from the given $date according to $isStart parameter
	 *
	 */
	public static function formatDate(string $date, bool $isStart = true): \DateTime {

		// Supported date formats
		// 18, 2018
		// 1.18, 10.18, 1.2018, 10.2018
		// 1.1.18, 1.10.18, 10.1.18, 10.10.18
		// 1.1.2018, 1.10.2018, 10.1.2018, 10.10.2018

		$df = self::getDatePrecision($date);

		switch($df) {
			case self::PRECISON_Y :
				return $isStart ?
					new \DateTime('first day of January '.$date) :
					new \DateTime('last day of December '.$date);
			break;
			case self::PRECISON_MY :
				$monthNum  = self::getSplittedDate($date)[0];
				$dateObj   = \DateTime::createFromFormat('!m', $monthNum);
				$monthName = $dateObj->format('F');
				return $isStart ?
					new \DateTime('first day of '.$monthName.' '.$date) :
					new \DateTime('last day of '.$monthName.' '.$date);
			break;
			case self::PRECISON_DMY :
				$splitDate = self::getSplittedDate($date);
				return new \DateTime($splitDate[2].'-'.$splitDate[1].'-'.$splitDate[0]);
			break;
		}
	}

	/**
	 * Returns a new DateWithPrecision object from given string $date, according to $isStart parameter
	 *
	 * @param $date string: The unformatted filter date (eg. 2018, 10.2018, 01-11-2018)
	 * @param $isStart bool: It's true if the $date have to be considered a start date
	 *
	 * @return DateWithPrecision
	 *
	 */
	public static function strToDateWithPrecision(string $date, bool $isStart): DateWithPrecision {

		$dateWithPrec = null;
		$prec = self::getDatePrecision($date);
		$formattedDate = self::formatDate($date, $isStart);

		return new DateWithPrecision($formattedDate, $prec);
	}

	public function serializeJSON() {

		$serialized = json_encode(
			[
				'startDate' => $this->_startDate,
				'endDate' =>  $this->_endDate
			]
		);
		return $serialized;

	}

}

class DateWithPrecision extends Date {

	const SUPPORTED_PRECISON = [
		DateFilter::PRECISON_Y,
		DateFilter::PRECISON_MY,
		DateFilter::PRECISON_DMY
	];

	protected $_precision;

	public function __construct(\DateTime $date, string $precision) {

		if(!in_array($precision, self::SUPPORTED_PRECISON))
			throw new Exception("Unsupported Precision");

		$this->_precision = $precision;
		parent::__construct($date);
	}

	public function getPrecision() {
		return $this->_precision;
	}

	public function setPrecision($precision) {
		if(!in_array($precision, self::SUPPORTED_PRECISON))
			throw new Exception("Unsupported Precision");
		$this->_precision = $precision;
	}

}


