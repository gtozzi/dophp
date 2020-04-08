<?php

/**
* @file Alert.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Classes for handling Alerts
*/

namespace dophp;


/**
 * Interface for implementing an alert.
 * Alerts are special classes that may be set when session is active and then
 * are kept during the session
 */
interface Alert {

	// Alert type consts
	const TYPE_SUCCESS = 'success';
	const TYPE_INFO = 'info';
	const TYPE_WARNING = 'warning';
	const TYPE_DANGER = 'danger';

	/**
	 * Returns alert type, usually one of the TYPE_ constants
	 */
	public function getType();

	/**
	 * Returns alert creation date and time
	 */
	public function getTime();

	/**
	 * Returns alert message
	 */
	public function getMessage();
}


/**
 * Base class for implementing an Alert
 */
abstract class AlertBase implements Alert {

	protected $_time;
	protected $_type;

	/**
	 * Constructs the alert
	 *
	 * @param $type string: The alert type
	 */
	public function __construct($type) {
		$this->_type = $type;
		$this->_time = new \DateTime();
	}

	public function getType() {
		return $this->_type;
	}

	public function getTime() {
		return $this->_time;
	}

	abstract public function getMessage();

	public function __toString() {
		return $this->getMessage();
	}

}


/**
 * Alert stored when a login error occurs
 */
class LoginErrorAlert extends AlertBase {

	protected $_exception;

	/**
	 * Constructs the alert from the exception
	 *
	 * @param $exception PageDenied The original exception
	 */
	public function __construct(PageDenied $exception) {
		$this->_exception = $exception;
		parent::__construct(self::TYPE_WARNING);
	}

	public function getMessage() {
		$mex = $this->_exception->getMessage();
		return $mex ? $mex : _('Access denied');
	}

	/**
	 * Returns the original exception
	 *
	 * @return PageDenied
	 */
	public function getException() {
		return $this->_exception;
	}

}
