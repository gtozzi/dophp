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
class DoPhpNotInitedException extends \Exception {

	/** The exception message when usign a static method without instance */
	const INSTANCE_ERROR = 'Must instatiate DoPhp first';

	public function __construct() {
		parent::__construct(self::INSTANCE_ERROR);
	}
}
