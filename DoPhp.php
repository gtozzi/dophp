<?php

/**
* @file DoPhp.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Main DoPhp framework class
*/

require_once(__DIR__ . '/Lang.php');
require_once(__DIR__ . '/Db.php');
require_once(__DIR__ . '/Auth.php');
require_once(__DIR__ . '/Page.php');
require_once(__DIR__ . '/Validator.php');
require_once(__DIR__ . '/Utils.php');
require_once(__DIR__ . '/smarty/libs/Smarty.class.php');

/**
* The main framework class, only one instance is possible
*/
class DoPhp {

	/** This is the base for $_GET parameters and page classes */
	const BASE_KEY = 'do';
	/** This is the text domain used by the framework */
	const TEXT_DOMAIN = 'dophp';

	/** Stores the current instance */
	private static $__instance = null;

	/** The Database object instance */
	private $__db = null;
	/** The Authentication object instance */
	private $__auth = null;
	/** The Language object instance */
	private $__lang = null;

	/**
	* Handles page rendering and everything
	*
	* Process request parameters and expect $key to contain the name of the
	* page to be loaded, then loads <inc_path>/<my_name>.<do>.php
	*
	* @param $conf array: Configuration associative array. Keys:
	*                 'paths' => array( //Where files are located
	*                     'inc'=> include files (page php files)
	*                     'med'=> media files (images, music, ...) and static files
	*                     'tpl'=> template files (for Smarty)
	*                     'cac'=> cache folder (must be writable)
	*                 )
	*                 'db' => array( //Database configuration
	*                     'dsn'=> Valid PDO dsn
	*                     'usr'=> Database username
	*                     'pwd'=> Database password
	*                 )
	*                 'lang' => array(
	*                     'supported' => array() List of supported languages,
	*                                    in the form 'en' or 'en_US'. First one is
	*                                    assumed as default one.
	*                     'coding' => Character coding to use for all languages.
	*                     'texts' => associative array (name => directory) containing
	*                                the list of text domains to bind. 'dophp' is
	*                                used for framework strings. First one is
	*                                initially set as default.
	*                 )
	* @param $db     string: Name of the class to use for the database connection
	* @param $auth   string: Name of the class to use for user authentication
	* @param $lang   string: Name of the class to use for mumtilanguage handling
	* @param $sess   boolean: If true, starts the session and uses it
	* @param $def    string: Default page name, used when received missing or unvalid page
	* @param $key    string: the key containing the page name
	*/
	public function __construct($conf=null, $db='dophp\\Db', $auth=null, $lang='dophp\\Lang',
			$sess=true, $def='home', $key=self::BASE_KEY) {

		// Don't allow multiple instances of this class
		if( self::$__instance )
			throw new Exception('DoPhp is already instantiated');
		else
			self::$__instance = $this;

		// Start the session
		if( $sess )
			session_start();

		// Build default config
		if( ! array_key_exists('paths', $conf) )
			$conf['paths'] = array();
		foreach( array('inc','med','tpl','cac') as $k )
			if( ! array_key_exists($k, $conf['paths'] ) )
				$conf['paths'][$k] = $k;

		//Set the locale
		if( ! array_key_exists('lang', $conf) )
			$conf['lang'] = array();
		if( ! array_key_exists('supported', $conf['lang']) )
			$conf['lang']['supported'] = array();
		if( ! array_key_exists('coding', $conf['lang']) )
			$conf['lang']['coding'] = null;
		if( ! array_key_exists('texts', $conf['lang']) )
			$conf['lang']['texts'] = array();
		bindtextdomain(self::TEXT_DOMAIN, __DIR__ . '/locale');
		$def_domain = null;
		foreach( $conf['lang']['texts'] as $n => $d ) {
			if( ! $def_domain )
				$def_domain = $n;
			bindtextdomain($n, $d);
		}
		if( ! $def_domain )
			$def_domain = self::TEXT_DOMAIN;
		textdomain($def_domain);
		$this->__lang = new $lang($conf['lang']['supported'], $conf['lang']['coding']);

		// Creates database connection, if needed
		if( array_key_exists('db', $conf) )
			$this->__db = new $db($conf['db']['dsn'], $conf['db']['user'], $conf['db']['pass']);

		// Authenticates the user, if applicable
		if( $auth ) {
			$this->__auth = new $auth($conf, $this->__db, $sess);
			if( ! $this->__auth instanceof dophp\AuthInterface )
				throw new Exception('Wrong auth interface');
			$this->__auth->login();
		}

		// Calculates the name of the page to be loaded
		$inc_file = dophp\Utils::pagePath($conf, $_REQUEST[$key]);

		if(array_key_exists($key, $_REQUEST) && $_REQUEST[$key] && !strpos($_REQUEST[$key], '/') && file_exists($inc_file))
			$page = $_REQUEST[$key];
		elseif( $def ) {
			$to = dophp\Utils::fullPageUrl($def, $key);
			header("HTTP/1.1 301 Moved Permanently");
			header("Location: $to");
			echo $to;
			return;
		}else{
			header("HTTP/1.1 400 Bad Request");
			echo('Unknown Page');
			return;
		}

		// Init return var and execute page
		try {
			require $inc_file;
			$classes = get_declared_classes();
			$classname = null;
			foreach( $classes as $c )
				if( strtolower($c) == strtolower(self::className($page)) ) {
					$classname = $c;
					break;
				}
			if( ! $classname )
				throw new Exception('Page class not found');
			$pobj = new $classname($conf, $this->__db, $this->__auth, $page );
			if( ! $pobj instanceof dophp\PageInterface )
				throw new Exception('Wrong page type');
			$out = $pobj->run();
		} catch( dophp\PageDenied $e ) {
			if( $def ) {
				$to = dophp\Utils::fullPageUrl($def, $key);
				header("HTTP/1.1 303 Login Required");
				header("Location: $to");
				echo $e->getMessage();
				echo "\nPlease login at: $to";
			} else {
				header("HTTP/1.1 403 Forbidden");
				echo $e->getMessage();
				exit();
			}
		} catch( dophp\PageError $e ) {
			header("HTTP/1.1 400 Bad Request");
			echo $e->getMessage();
			error_log($e->getMessage());
			exit();
		} catch( Exception $e ) {
			header("HTTP/1.1 500 Internal Server Error");
			$err = 'Catched Exception: ' . $e->getCode() . '.' . $e->getMessage() . 
				"\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() .
				"\nTrace:\n" . $e->getTraceAsString();
			echo $err;
			error_log($err);
			exit();
		}

		//Output the headers
		foreach( $pobj->headers() as $k=>$v )
			header("$k: $v");

		//Output the content
		echo $out;
	}

	/**
	* Returns class name for a given page
	*
	* @param $page string: The page name
	* @return string: The class name
	*/
	public static function className($page) {
		return self::BASE_KEY . ucfirst($page);
	}

	/**
	* Returns Database class instance, if available
	*
	* @return object: A dophp\Db instance
	*/
	public static function db() {
		if( ! self::$__instance )
			throw new Exception('Must instatiate DoPhp first');
		if( ! self::$__instance->__db )
			throw new Exception('Database is not available');
		return self::$__instance->__db;
	}

	/**
	* Returns Authentication class instance, if available
	*
	* @return object: A dophp\Auth instance
	*/
	public static function auth() {
		if( ! self::$__instance )
			throw new Exception('Must instatiate DoPhp first');
		if( ! self::$__instance->__auth )
			throw new Exception('Authentication is not available');
		return self::$__instance->__auth;
	}

	/**
	* Returns Language class instance, if available
	*
	* @return object: A dophp\Lang instance
	*/
	public static function lang() {
		if( ! self::$__instance )
			throw new Exception('Must instatiate DoPhp first');
		if( ! self::$__instance->__lang )
			throw new Exception('Language support is not available');
		return self::$__instance->__lang;
	}

}
