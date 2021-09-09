<?php

/**
* @file Exceptions.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Common exception clsses.
*        Most exceptions are file-specific but general ones are defined here
*/

namespace dophp;


/**
 * Exception thrown by DoPhp when DoPhp instance is required but not inited
 * @see \DoPhp
 */
class DoPhpNotInitedException extends \LogicException {

	/** The exception message when usign a static method without instance */
	const INSTANCE_ERROR = 'Must instatiate DoPhp first';

	public function __construct() {
		parent::__construct(self::INSTANCE_ERROR);
	}
}


/**
 * Exception thrown when an error is handled and "converted" into an Exception
 */
class PHPErrorException extends \Exception {
}


/**
 * Exception thrown when some program logic is missing to handle the case
 */
class NotImplementedException extends \LogicException {
}

/**
 * Exception thrown when security standards cannot be guaranteed
 * I.E. when the code expects to be run via an HTTPS connection, but an HTTP one is found
 */
class SecurityException extends \Exception {
}
