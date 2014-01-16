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
	* @param $sess   boolean: If true, use session-aware auth
	*/
	public function __construct(& $config, $db, $sess);

	/**
	* Method called to log in an user
	*
	* @return boolean: True on success or False on failure
	*/
	public function login();

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

	/** Name of the session variable */
	const SESS_VAR = 'DoPhpAuthUid';

	/** Config array */
	protected $_config;
	/** Database instance */
	protected $_db;
	/** Current user's ID */
	protected $_uid = null;
	/** If true, use session for authentication caching */
	protected $_sess = null;

	/**
	* Contrsuctor
	*
	* @see AuthInterface::__construct
	*/
	public function __construct(& $config, $db, $sess) {
		$this->_config = $config;
		$this->_db = $db;
		$this->_sess = $sess;

		if( $this->_sess )
			$this->_uid = $_SESSION[self::SESS_VAR];
	}

	/**
	* @see AuthInterface::login
	*/
	public function login() {
		if( $this->_uid )
			throw new \Exception('Must logout first');

		$uid = $this->_doLogin();
		if( ! $uid )
			return false;

		$this->_uid = $uid;
		if( $this->_sess )
			$_SESSION[self::SESS_VAR] = $this->_uid;
		return true;
	}

	/**
	* Called from login(), does the real login job. Must be overridden.
	*
	* @return int: The user's ID on success or null on failure
	*/
	abstract protected function _doLogin();

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
		if( $this->_sess )
			$_SESSION[self::SESS_VAR] = null;
	}

}

/**
* Class for username/password authentication
*
* Checks $_REQUEST for 'username' and 'password' variables. 'login' must be true
* for security reasons
*
* Database MUST implement a login($user, $password) method returning the user's
* ID on succesfull login
*/
class AuthPlain extends AuthBase implements AuthInterface {

	/**
	* @see AuthBase::_doLogin
	*/
	protected function _doLogin() {
		if( ! $_REQUEST['login'] )
			return null;

		$user = $_REQUEST['username'];
		$pwd = $_REQUEST['password'];

		if( ! $user || ! $pwd )
			return null;

		$uid = $this->_login($user, $pwd);
		if( ! $uid )
			return null;

		return $uid;
	}

	/**
	* Reads user's ID from database, if password is correct
	*
	* @param $user string: The username
	* @param $pwd string: The password
	* @return integer The user's ID on success
	*/
	protected function _login($user, $pwd) {
		return $this->_db->login($user, $pwd);
	}

}

/**
* Class for Signature-based stateless authentication
*
* Authenticates against X-Auth-Username and X-Auth-Sign headers.
* X-Auth-Username: the username
* X-Auth-Sign: sha1($username . SEP . $password . SEP . $raw_content)
*
* Database MUST implement a getUserPwd($user) method returning the user's
* password (maybe encrypted)
*/
class AuthSign extends AuthBase implements AuthInterface {

	/** Separator to use for hash concatenation */
	const SEP = '~';

	/**
	* @see AuthBase::_doLogin
	*/
	public function _doLogin() {
		$headers = apache_request_headers();
		$data = file_get_contents("php://input");

		$user = $headers['X-Auth-User'];
		$sign = $headers['X-Auth-Sign'];
		list($uid, $pwd) = $this->_getUserPwd($user);

		if( ! $user || ! $sign || ! $pwd )
			return null;

		$countersign = sha1($user . self::SEP . $pwd . self::SEP . $data);
		if( $sign !== $countersign )
			return null;

		return $uid;
	}

	/**
	* Reads user's ID and password from database, may be overridden
	*
	* @param $user string: The username
	* @return array: (id, password: may be hashed)
	*/
	protected function _getUserPwd($user) {
		return $this->_db->getUserPwd($user);
	}

}
