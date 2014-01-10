<?php

/**
* @file Auth.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Base classes for handling authentication
*/

namespace dophp;

/**
* Interface for implementing an authenticator
*/
interface AuthInterface {

	/**
	* Constructor
	*
	* @param $config array: Global config array
	* @param $db     object: Database instance
	*/
	public function __construct(& $config, $db);

	/**
	* Method called to log in an user
	*
	* @param $headers array: The headers
	* @param $post array: The $_POST data
	* @param $data array: The full sent data to be validated (if supported)
	* @return boolean: True on success or False on failure
	*/
	public function login($headers, & $post, $data);

	/**
	* Method called to get the current user's ID
	*
	* @return integer: Current user's ID or null is not authenticated
	*/
	public function getUid();

	/**
	* Method called to log out the user
	*/
	public function logout();
}

/**
* Base class for authenticator
*/
abstract class AuthBase {

	/** Config array */
	protected $_config;
	/** Database instance */
	protected $_db;
	/** Current user's ID */
	protected $_uid = null;

	/**
	* Contrsuctor
	*
	* @see AuthInterface::__construct
	*/
	public function __construct(& $config, $db) {
		$this->_config = $config;
		$this->_db = $db;
	}

	/**
	* @see AuthInterface::getUid
	*/
	public function getUid() {
		return $this->_uid;
	}

	/**
	* @see AuthInterface::logout
	*/
	public function logout() {
		$this->_uid = null;
	}

}

/**
* Class for Signature-based stateless authentication
*
* Authenticates against X-Auth-Username and X-Auth-Sign headers.
* X-Auth-Username: the username
* X-Auth-Sign: sha1($username . $password . $raw_content)
*
* Database MUST implement a getUserPwd($user) method returning the user's
* password (maybe encrypted)
*/
class AuthSign extends AuthBase implements AuthInterface {

	/**
	* @see AuthInterface::login
	*/
	public function login($headers, & $post, $data) {
		$user = $headers['X-Auth-Username'];
		$sign = $headers['X-Auth-Sign'];
		$pwd = $this->_db->getUserPwd($user);

		if( ! $user || ! $sign || ! $pwd )
			return false;

		$countersign = sha1($user . $password . $raw_content);
		if( $sign !== $countersign )
			return false;

		return true;
	}

}
