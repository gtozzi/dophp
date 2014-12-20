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
require_once(__DIR__ . '/Menu.php');
require_once(__DIR__ . '/Page.php');
require_once(__DIR__ . '/Validator.php');
require_once(__DIR__ . '/Utils.php');
require_once(__DIR__ . '/Model.php');
require_once(__DIR__ . '/smarty/libs/Smarty.class.php');

/**
* The main framework class, only one instance is possible
*/
class DoPhp {

	/** This is the base for $_GET parameters and prefix for page classes */
	const BASE_KEY = 'do';
	/** This is the text domain used by the framework */
	const TEXT_DOMAIN = 'dophp';
	/** This is the prefix used for model classes */
	const MODEL_PREFIX = 'm';

	/** Stores the current instance */
	private static $__instance = null;

	/** The configuration array */
	private $__conf = null;
	/** The Database object instance */
	private $__db = null;
	/** The Authentication object instance */
	private $__auth = null;
	/** The Language object instance */
	private $__lang = null;
	/** Stores the execution start time */
	private $__start = null;
	/** Models instances cache */
	private $__models = [];

	/**
	* Handles page rendering and everything
	*
	* Process request parameters and expect $key to contain the name of the
	* page to be loaded, then loads <inc_path>/<my_name>.<do>.php
	*
	* @param $conf array: Configuration associative array. Keys:
	*                 'paths' => array( //Where files are located, defaults to key name
	*                     'inc'=> include files (page php files)
	*                     'mod'=> model files
	*                     'med'=> media files (images, music, ...) and static files
	*                     'tpl'=> template files (for Smarty)
	*                     'cac'=> cache folder (must be writable)
	*                 )
	*                 'db' => array( //Database configuration
	*                     'dsn'=> Valid PDO dsn
	*                     'usr'=> Database username
	*                     'pwd'=> Database password
	*                 )
	*                 'lang' => array( //see Lang class description
	*                     'supported' => array() List of supported languages,
	*                                    in the form 'en' or 'en_US'. First one is
	*                                    assumed as default one. If tables is specified,
	*                                    overrides this setting
	*                     'coding' => Character coding to use for all languages.
	*                     'texts' => associative array (name => directory) containing
	*                                the list of text domains to bind. 'dophp' is
	*                                used for framework strings. First one is
	*                                initially set as default.
	*                     'tables' => associative array containing the list of
	*                                 database table to use:
	*                                 - lang: the table containing the list of supported
	*                                         languages
	*                                 - idx: the table containing the text indexes
	*                                 - txt: the table containing the texts itself
	*                 )
	*                 'dophp' => array( // Internal DoPhp configurations
	*                     'url'  => relative path for accessing DoPhp folder from webserver.
	*                               Default: try to guess it
	*                     'path' => relative or absolute DoPhp root path
	*                               Default: automatically detect it
	*                 )
	* @param $db     string: Name of the class to use for the database connection
	* @param $auth   string: Name of the class to use for user authentication
	* @param $lang   string: Name of the class to use for multilanguage handling
	* @param $sess   boolean: If true, starts the session and uses it
	* @param $def    string: Default page name, used when received missing or unvalid page
	* @param $key    string: the key containing the page name
	* @param $url    string: base relative URL for accessing dophp folder in webserver
	*/
	public function __construct($conf=null, $db='dophp\\Db', $auth=null, $lang='dophp\\Lang',
			$sess=true, $def='home', $key=self::BASE_KEY) {

		// Don't allow multiple instances of this class
		if( self::$__instance )
			throw new Exception('DoPhp is already instantiated');
		self::$__instance = $this;
		$this->__start = microtime(true);

		// Start the session
		if( $sess )
			session_start();

		// Build default config
		$this->__conf = $conf;
		if( ! array_key_exists('paths', $this->__conf) )
			$this->__conf['paths'] = array();
		foreach( array('inc','mod','med','tpl','cac') as $k )
			if( ! array_key_exists($k, $this->__conf['paths'] ) )
				$this->__conf['paths'][$k] = $k;
		if( ! array_key_exists('lang', $this->__conf) )
			$this->__conf['lang'] = array();
		if( ! array_key_exists('supported', $this->__conf['lang']) )
			$this->__conf['lang']['supported'] = array();
		if( ! array_key_exists('coding', $this->__conf['lang']) )
			$this->__conf['lang']['coding'] = null;
		if( ! array_key_exists('texts', $this->__conf['lang']) )
			$this->__conf['lang']['texts'] = array();
		if( ! array_key_exists('tables', $this->__conf['lang']) )
			$this->__conf['lang']['tables'] = array();
		if( ! array_key_exists('dophp', $this->__conf) )
			$this->__conf['dophp'] = array();
		if( ! array_key_exists('dophp', $this->__conf) )
			$this->__conf['dophp'] = array();
		if( ! array_key_exists('url', $this->__conf['dophp']) )
			$this->__conf['dophp']['url'] = preg_replace('/^'.preg_quote($_SERVER['DOCUMENT_ROOT'],'/').'/', '', __DIR__, 1);
		if( ! array_key_exists('path', $this->__conf['dophp']) )
			$this->__conf['dophp']['path'] = __DIR__;

		//Set the locale
		bindtextdomain(self::TEXT_DOMAIN, __DIR__ . '/locale');
		$def_domain = null;
		foreach( $this->__conf['lang']['texts'] as $n => $d ) {
			if( ! $def_domain )
				$def_domain = $n;
			bindtextdomain($n, $d);
		}
		if( ! $def_domain )
			$def_domain = self::TEXT_DOMAIN;
		textdomain($def_domain);

		// Creates database connection, if needed
		if( array_key_exists('db', $this->__conf) )
			$this->__db = new $db($this->__conf['db']['dsn'], $this->__conf['db']['user'], $this->__conf['db']['pass']);

		// Creates the locale object
		$this->__lang = new $lang($this->__db, $this->__conf['lang']['supported'], $this->__conf['lang']['coding'], $this->__conf['lang']['tables']);

		// Authenticates the user, if applicable
		if( $auth ) {
			$this->__auth = new $auth($this->__conf, $this->__db, $sess);
			if( ! $this->__auth instanceof dophp\AuthInterface )
				throw new Exception('Wrong auth interface');
			$this->__auth->login();
		}

		// Calculates the name of the page to be loaded
		$inc_file = dophp\Utils::pagePath($this->__conf, isset($_REQUEST[$key])?$_REQUEST[$key]:null);

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
			$classname = dophp\Utils::findClass(self::className($page));
			if( ! $classname )
				throw new Exception('Page class not found');
			$pobj = new $classname($this->__conf, $this->__db, $this->__auth, $page );
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
	* Returns Configuration array
	*
	* @return array: Configuration array
	*/
	public static function conf() {
		if( ! self::$__instance )
			throw new Exception('Must instatiate DoPhp first');
		return self::$__instance->__conf;
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

	/**
	* Returns a model by name
	*
	* @param $name The case-sensitive model's name (without prefix)
	* @return object: A model's instance
	*/
	public static function model($name) {
		if( ! self::$__instance )
			throw new Exception('Must instatiate DoPhp first');
		if( ! $name )
			throw new Exception('Must give a model name');

		// Use caching if available, load the model instead
		if( ! isset(self::$__instance->__models[$name]) ) {
			require_once self::$__instance->__conf['paths']['mod'] . '/' . ucfirst($name) . '.php';
			$classname = dophp\Utils::findClass(self::MODEL_PREFIX . $name);
			if( ! $classname )
				throw new Exception('Model class not found');
			$mobj = new $classname(self::$__instance->__db);
			if( ! $mobj instanceof dophp\Model )
				throw new Exception('Wrong model type');

			self::$__instance->__models[$name] = $mobj;
		}

		return self::$__instance->__models[$name];
	}

	/**
	* Returns execution time so far
	*
	* @return double: The execution time, in seconds
	*/
	public static function duration() {
		if( ! self::$__instance )
			throw new Exception('Must instatiate DoPhp first');

		return microtime(true) - self::$__instance->__start;
	}

}
