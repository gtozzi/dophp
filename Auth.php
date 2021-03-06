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
	const SESS_VAR = 'DoPhp::Auth';
	/** Name of the session array username key */
	const SESS_VUSER = 'username';
	/** Name of the session array password key */
	const SESS_VPASS = 'password';

	/** String (char) used to concatenate salt and password */
	const PWD_SALT_GLUE = '$';

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
			throw new \LogicException('Must logout first');
	}

	/**
	 * Process and apply login result
	 *
	 * @param $uid mixed: The user id, ad returned from _doLogin()
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
		$this->clearSession();
	}

	/**
	* Save login credentials in session
	*
	* @param $user string: The username
	* @param $tok string: The hash or token (using password directly is unsafe)
	* @return bool: True if session has been saved
	*/
	public function saveSession($user, $tok) {
		if( ! $this->_sess )
			return false;


		if( ! isset($_SESSION[self::SESS_VAR]) || ! is_array($_SESSION[self::SESS_VAR]) )
			$_SESSION[self::SESS_VAR] = [];
		$_SESSION[self::SESS_VAR][self::SESS_VUSER] = $user;
		$_SESSION[self::SESS_VAR][self::SESS_VPASS] = $tok;
		return true;
	}

	/**
	 * Load login credentials from session
	 *
	 * @return array [ $username, $token ] or null
	 */
	public function loadSession() {
		if( ! $this->_sess )
			return null;

		if( ! isset($_SESSION[self::SESS_VAR]) || ! is_array($_SESSION[self::SESS_VAR]) )
			return null;

		if( ! isset($_SESSION[self::SESS_VAR][self::SESS_VUSER]) || ! $_SESSION[self::SESS_VAR][self::SESS_VUSER] )
			return null;

		if( ! array_key_exists(self::SESS_VPASS, $_SESSION[self::SESS_VAR]) )
			return null;

		return [
			$_SESSION[self::SESS_VAR][self::SESS_VUSER],
			$_SESSION[self::SESS_VAR][self::SESS_VPASS]
		];
	}

	/**
	 * Erase session credentials
	 */
	public function clearSession() {
		if( isset($_SESSION[self::SESS_VAR]) )
			unset($_SESSION[self::SESS_VAR]);
	}

	/**
	 * Creates a sha512-based password hash
	 *
	 * @param $password string: The password to be hashed
	 * @param $salt string: The salt; if missing, use a random one
	 *
	 * @return "{$salt}${$salt}§{$password}§{$salt}"
	 */
	public static function encryptPasswordSHA512(string $password, string $salt=null): string {
		if( ! $salt ) {
			$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$salt = '';
			for($i = 0; $i < 8; $i++)
				$salt .= $chars[rand(0, strlen($chars) - 1)];
		}

		$merged = "{$salt}§{$password}§{$salt}";
		return $salt . self::PWD_SALT_GLUE . hash('sha512', $merged);
	}

	/**
	 * Compares a plain password with a SHA512 password
	 *
	 * @see self::encryptPasswordSHA512
	 * @param $plain_password The plain password
	 * @param $hashed_password The hashed password, in the format "salt$password"
	 * @return true on success
	 */
	public static function comparePasswordsSHA512(string $plain_password, string $hashed_password): bool {
		$parts = explode(self::PWD_SALT_GLUE, $hashed_password, 2);
		if( count($parts) < 2 ) {
			// Missing salt
			return false;
		}
		$salt = $parts[0];

		if(trim($hashed_password) == trim(self::encryptPasswordSHA512($plain_password, $salt)))
			return true;

		return false;
	}

}


/**
* Implements HTTP Basic authentication
*
* Uses the HTTP "Authorization" header
*
* Database MUST implement a login($user, $password) method returning the user's
* ID on succesfull login
*/
abstract class AuthBasic extends AuthBase {

	/** Standard HTTP Authorization header */
	const HEAD_HTTP_AUTH = 'AUTHORIZATION';

	const SOURCE_HEADERS = 'headers';
	const SOURCE_HAND = 'hand';
	const SOURCE_USER = 'user';
	const SOURCE_SESSION = 'session';

	/**
	 * Method the may be called manually by a page script to login an user
	 *
	 * @return boolean: True on success or False on failure
	 */
	public function handLogin($username, $password) {
		$this->_beforeLogin();

		list( $uid, $token ) = $this->__checkedLogin($username, $password, self::SOURCE_HAND);
		if( $uid )
			$this->saveSession($username, $token);
		else
			$this->clearSession();

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
		list( $uid, $token ) = $this->__checkedLogin($user, $pwd, $source);

		if( $uid )
			$this->saveSession($user, $token);
		else
			$this->clearSession();

		return $uid;
	}

	/**
	 * Detect login request
	 *
	 * @return [$username, $password, $source] or null
	 */
	protected function _detectLogin() {
		$headers = Utils::headers(true);

		if( isset($headers[self::HEAD_HTTP_AUTH]) ) {
			$parts = explode(' ', $headers[self::HEAD_HTTP_AUTH]);

			if( count($parts) == 2 ) {
				$method = strtolower(trim($parts[0]));
				$auth = base64_decode(trim($parts[1]), true);

				if( $method == 'basic' && $auth !== false ) {
					$parts2 = explode(':', $auth);

					if( count($parts2) == 2 )
						return [ $parts2[0], $parts2[1], self::SOURCE_HEADERS ];
				}
			}
		}

		$sess = $this->loadSession();
		if( $sess )
			return [
				$sess[0],
				$sess[1],
				self::SOURCE_SESSION
			];

		return null;
	}

	/**
	 * Wrapper over $this->_login, checks output for backward compatibility
	 */
	private function __checkedLogin($user, $pwd, $source): array {
		$ret = $this->_login($user, $pwd, $source);
		if( ! $ret )
			return [ null, null ];

		if( ! is_array($ret) )
			throw new \UnexpectedValueException('Returning a single value from _login() is deprecated');
		if( count($ret) != 2 )
			throw new \UnexpectedValueException('_login() array must contain two values [id,token]');

		return $ret;
	}

	/**
	* Perform the login, must be implemented in child
	*
	* @param $user string: The username
	* @param $pwd string: The password or the token, depends on $source
	* @param $source string: The source for the credendials (headers|user|session|hand),
	*                        SOURCE_* consts
	* @return array The [ user's ID, session token ] array on success, null on failure
	*               Session token will be saved in session and passed
	*               back as $pwd argument on subsequent login calls
	*/
	abstract protected function _login($user, $pwd, $source);

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
abstract class AuthPlain extends AuthBasic {

	/** UserId header name ($_SERVER key name) */
	const HEAD_USER = 'HTTP_X_AUTH_USER';
	/** Password header name ($_SERVER key name) */
	const HEAD_PASS = 'HTTP_X_AUTH_PASS';

	/**
	 * Detect login request
	 *
	 * @return [$username, $password, $source] or null
	 */
	protected function _detectLogin() {
		if( isset($_SERVER[self::HEAD_USER]) && isset($_SERVER[self::HEAD_PASS]) )
			return [ $_SERVER[self::HEAD_USER], $_SERVER[self::HEAD_PASS], self::SOURCE_HEADERS ];

		if( isset($_REQUEST['login']) && $_REQUEST['login']
				&& isset($_REQUEST['username']) && isset($_REQUEST['password']) )
			return [ $_REQUEST['username'], $_REQUEST['password'], self::SOURCE_USER ];

		$sess = $this->loadSession();
		if( $sess )
			return [
				$sess[0],
				$sess[1],
				self::SOURCE_SESSION
			];

		return null;
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
		$sess = $this->loadSession();
		if( isset($_SERVER[self::HEAD_USER]) && isset($_SERVER[self::HEAD_SIGN]) ) {
			$user = $_SERVER[self::HEAD_USER];
			$sign = $_SERVER[self::HEAD_SIGN];
		} elseif( $sess )
			list( $user, $sign ) = $sess;
		list($uid, $pwd) = $this->_getUserPwd($user);

		if( ! $user || ! $sign || ! $pwd )
			return null;

		$countersign = hash('sha512', $user . self::SEP . $pwd . self::SEP . $data);
		if( $sign !== $countersign )
			return null;

		$this->saveSession($user, $sign);

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
