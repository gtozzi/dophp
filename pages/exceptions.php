<?php

/**
* @file exceptions.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @see Page.php
* @brief Page related exceptions
*/

namespace dophp;

require_once(__DIR__ . '/../Page.php');



/**
* Exception raised if something goes wrong during page rendering
*/
class PageError extends \Exception {

	/**
	 * May be set to ask DoPhp to do (not) perform an interactive redirect
	 * when catching this exception (i.e. do not redirect on login for InvalidCredentials.)
	 * Values: true = please redirect, false = please do not redirect, null = default
	 */
	public $redirect = null;

	protected $_debugData = null;

	/**
	 * Adds extra debug data to this exception
	 */
	public function setDebugData($data) {
		$this->_debugData = $data;
	}

	/**
	 * Returns debug data, if any
	 */
	public function getDebugData() {
		return $this->_debugData;
	}
}

/**
* Exception raised when client is asking for an unavailable encoding
*/
class NotAcceptable extends PageError {
}

/**
* Exception raised when user is not authorized to see the page
*/
class PageDenied extends PageError {
}

/**
 * Exception raised when the page has been removed (i.e. missing id)
 */
class PageGone extends PageError {
}

/**
* Exception raised when user is providing invalid credentials
*/
class InvalidCredentials extends PageDenied {
}

/**
 * Exception raised to create an internal transparent redirect
 */
class PageRedirect extends \Exception {

	protected $_to;

	/**
	 * Construct the redirect
	 *
	 * @param $to PageInterface: New page to redirect to, already instantiated
	 */
	public function __construct(PageInterface $to) {
		parent::__construct();
		$this->_to = $to;
	}

	/**
	 * Returns the redirect destination
	 */
	public function getPage() {
		return $this->_to;
	}

}


/**
 * Exception raised to immediately redirect to a url
 */
class UrlRedirect extends \Exception {

	protected $_url;

	/**
	 * Construct the redirect
	 *
	 * @param $url string: Redirect destination
	 */
	public function __construct($url) {
		parent::__construct();
		$this->_url = $url;
	}

	/**
	 * @see PageInterface::headers()
	 */
	public function headers() {
		return [
			'Location' => $this->_url,
		];
	}

	/**
	 * Returns redirect body
	 */
	public function body() {
		return "Redirecting to {$this->_url}";
	}
}
