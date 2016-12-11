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
	* Method called automatically by DoPhp to log in an user
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
abstract class AuthBase implements AuthInterface {

	/** Name of the session variable */
	const SESS_VAR = 'DoPhpAuth_';

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
	}

	/**
	* @see AuthInterface::login
	*/
	public function login() {
		$this->_beforeLogin();

		return $this->_processLogin($this->_doLogin());
	}

	/**
	 * Does pre-login checks
	 */
	protected function _beforeLogin() {
		if( $this->_uid )
			throw new \Exception('Must logout first');
	}

	/**
	 * Process and apply login result
	 *
	 * @param $uid The user id, ad returned from _doLogin()
	 * @return boolean: True on success or False on failure
	 */
	protected function _processLogin($uid) {
		if( ! $uid )
			return false;

		$this->_uid = $uid;
		return true;
	}

	/**
	* Called from login(), does the real login job. Must be overridden.
	* Must also take care of session save and login, if $this->_sess
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
			foreach( $_SESSION as $k => $v )
				if( substr($k,0,strlen(self::SESS_VAR)) == self::SESS_VAR )
					unset($_SESSION[$k]);
	}

}

/**
* Class for username/password authentication
*
* First checks for X-Auth-User and X-Auth-Pass headers, if not found, then 
* checks $_REQUEST for 'username' and 'password' variables. 'login' must be true
* for security reasons
*
* Database MUST implement a login($user, $password) method returning the user's
* ID on succesfull login
*/
class AuthPlain extends AuthBase {

	/** UserId header name ($_SERVER key name) */
	const HEAD_USER = 'HTTP_X_AUTH_USER';
	/** Password header name ($_SERVER key name) */
	const HEAD_PASS = 'HTTP_X_AUTH_PASS';

	/**
	 * Method the may be called manually by a page script to login an user
	 *
	 * @return boolean: True on success or False on failure
	 */
	public function handLogin($username, $password) {
		$this->_beforeLogin();

		$uid = $this->_login($username, $password, 'hand');
		if( $uid ) {
			$this->saveSession($username, $password);
		}

		return $this->_processLogin($uid);
	}

	/**
	* @see AuthBase::_doLogin
	*/
	protected function _doLogin() {
		$detected = $this->_detectLogin();
		if( $detected === null )
			return null;

		list( $user, $pwd, $source ) = $detected;

		$uid = $this->_login($user, $pwd, $source);
		if( ! $uid )
			return null;

		$this->saveSession($user, $pwd);

		return $uid;
	}

	/**
	 * Detect login request
	 *
	 * @return [$username, $password, $source] or null
	 */
	protected function _detectLogin() {
		if( isset($_SERVER[self::HEAD_USER]) && isset($_SERVER[self::HEAD_PASS]) )
			return [ $_SERVER[self::HEAD_USER], $_SERVER[self::HEAD_PASS], 'headers' ];

		if( isset($_REQUEST['login']) && $_REQUEST['login']
				&& isset($_REQUEST['username']) && isset($_REQUEST['password']) )
			return [ $_REQUEST['username'], $_REQUEST['password'], 'user' ];

		$svaruser = self::SESS_VAR . 'username';
		$svarpass = self::SESS_VAR . 'password';
		if( $this->_sess && isset($_SESSION[$svaruser]) && isset($_SESSION[$svarpass]) )
			return [ $_SESSION[$svaruser], $_SESSION[$svarpass], 'session' ];

		return null;
	}

	/**
	* Save login credentials in session
	*
	* @param $user string: The username
	* @param $pwd string: The password
	* @return bool: True if session has been saved
	*/
	public function saveSession($user, $pwd) {
		if( $this->_sess ) {
			$_SESSION[self::SESS_VAR.'username'] = $user;
			$_SESSION[self::SESS_VAR.'password'] = $pwd;
			return true;
		}
		return false;
	}

	/**
	* Reads user's ID from database, if password is correct
	*
	* @param $user string: The username
	* @param $pwd string: The password
	* @param $source string: The source for the credendials (headers|user|session|hand)
	* @return integer The user's ID on success
	*/
	protected function _login($user, $pwd, $source) {
		return $this->_db->login($user, $pwd, $source);
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
class AuthSign extends AuthBase {

	/** Separator to use for hash concatenation */
	const SEP = '~';
	/** UserId header name ($_SERVER key name) */
	const HEAD_USER = 'HTTP_X_AUTH_USER';
	/** Signature header name ($_SERVER key name) */
	const HEAD_SIGN = 'HTTP_X_AUTH_SIGN';

	/**
	* @see AuthBase::_doLogin
	*/
	public function _doLogin() {
		$data = file_get_contents("php://input");

		$user = null;
		$sign = null;
		if( isset($_SERVER[self::HEAD_USER]) && isset($_SERVER[self::HEAD_SIGN]) ) {
			$user = $_SERVER[self::HEAD_USER];
			$sign = $_SERVER[self::HEAD_SIGN];
		}elseif( $this->_sess ) {
			$user = $_SESSION[self::SESS_VAR.'user'];
			$sign = $_SESSION[self::SESS_VAR.'sign'];
		}
		list($uid, $pwd) = $this->_getUserPwd($user);

		if( ! $user || ! $sign || ! $pwd )
			return null;

		$countersign = sha1($user . self::SEP . $pwd . self::SEP . $data);
		if( $sign !== $countersign )
			return null;

		$this->saveSession($user, $sign);

		return $uid;
	}

	/**
	* Save login credentials in session
	*
	* @param $user string: The username
	* @param $sign string: The signature
	* @return bool: True if session has been saved
	*/
	public function saveSession($user, $sign) {
		if( $this->_sess ) {
			$_SESSION[self::SESS_VAR.'user'] = $user;
			$_SESSION[self::SESS_VAR.'sign'] = $sign;
			return true;
		}
		return false;
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
