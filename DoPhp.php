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
	/** The is the key used for the login failed info in $_SESSION */
	const SESS_LOGIN_ERROR = 'DoPhp::LoginError';

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
	*                 'memcache' => array( // Memcached configuration
	*                     // see http://php.net/manual/en/memcache.connect.php
	*                     'host'=> Valid host or unix socket
	*                     'port'=> Valid port or 0
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
	*                 'cors' => array( // Handles CORS (Cross Origin Resource Sharing) support
	*                     'origins' => array list of origins to allow or string '*' for any.
	*                                  Null disables CORS (default).
	*                     'headers' => array list of accepted headers or string '*' to accept all.
	*                                  Default: empty
	*                     'credentials' => boolean, allow credentials? Default: false
	*                     'maxage' => int: indication of max preflight cached age, in seconds
	*                                 Default: 86400
	*                 )
	*                 'dophp' => array( // Internal DoPhp configurations
	*                     'url'  => relative path for accessing DoPhp folder from webserver.
	*                               Default: try to guess it
	*                     'path' => relative or absolute DoPhp root path
	*                               Default: automatically detect it
	*                 )
	*                 'debug' => enables debug info, should be false in production servers
	*
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
		if( ! array_key_exists('cors', $this->__conf) )
			$this->__conf['cors'] = array();
		if( ! array_key_exists('origins', $this->__conf['cors']) )
			$this->__conf['cors']['origins'] = null;
		if( ! array_key_exists('headers', $this->__conf['cors']) )
			$this->__conf['cors']['headers'] = array();
		if( ! array_key_exists('credentials', $this->__conf['cors']) )
			$this->__conf['cors']['credentials'] = false;
		if( ! array_key_exists('maxage', $this->__conf['cors']) )
			$this->__conf['cors']['maxage'] = 86400;
		if( ! array_key_exists('debug', $this->__conf) )
			$this->__conf['debug'] = false;

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
			$this->__db = new $db(
				$this->__conf['db']['dsn'],
				isset($this->__conf['db']['user']) ? $this->__conf['db']['user'] : null,
				isset($this->__conf['db']['pass']) ? $this->__conf['db']['pass'] : null,
				isset($this->__conf['db']['vcharfix']) ? $this->__conf['db']['vcharfix'] : false
			);
		if( $this->__conf['debug'] )
			if( $this->__db )
				$this->__db->debug = true;

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
			if( isset($_REQUEST[$key]) && $def == $_REQUEST[$key] ) {
				// Prevent loop redirection
				header("HTTP/1.1 500 Internal Server Error");
				echo("SERVER ERROR: Invalid default page \"$def\"");
				return;
			}

			$to = dophp\Utils::fullPageUrl($def, $key);
			header("HTTP/1.1 301 Moved Permanently");
			header("Location: $to");
			echo $to;
			return;
		} else {
			header("HTTP/1.1 404 Not Found");
			echo('Unknown Page');
			return;
		}

		// List of allowed methods, used later in CORS preflight and OPTIONS
		// TODO: Do not hardcode it, handle it nicely
		$allowMethods = 'OPTIONS, GET, HEAD, POST';

		// Handle CORS
		// (https://www.html5rocks.com/static/images/cors_server_flowchart.png)
		// (https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods/OPTIONS)
		$reqHeads = dophp\Utils::headers();
		if( isset($reqHeads['Origin']) && $this->__conf['cors']['origins'] ) {
			// The client is requesting CORS and dophp is configured to handle it
			$origin = $reqHeads['Origin'];
			$preflight = $_SERVER['REQUEST_METHOD'] == 'OPTIONS';

			// Set the Access-Control-Allow-Origin header
			$oh = null;
			if( $this->__conf['cors']['origins'] == '*' )
				$oh = $origin;
			else {
				header('Vary: Origin');
				foreach( $this->__conf['cors']['origins'] as $o )
					if( $o == $origin ) {
						$oh = $o;
						break;
					}
			}
			if( $oh )
				header("Access-Control-Allow-Origin: $oh");

			// Set the Access-Control-Request-Headers header
			$hh = null;
			if( $preflight && isset($reqHeads['Access-Control-Request-Headers']) ) {
				if( $this->__conf['cors']['headers'] == '*' )
					$hh = $reqHeads['Access-Control-Request-Headers'];
				else {
					$rhs = array_map('trim', explode(',', $reqHeads['Access-Control-Request-Headers']));
					$hh = '';
					foreach( $rhs as $h )
						if( in_array($h, $this->__conf['cors']['headers']) )
							$hh .= ( strlen($hh) ? ', ' : '' ) . $h;
				}
			}
			if( $hh )
				header("Access-Control-Allow-Headers: $hh");

			// Set the other headers
			if( $oh || $hh ) {
				if( $preflight ) {
					header("Access-Control-Allow-Methods: $allowMethods");
					header("Access-Control-Max-Age: {$this->__conf['cors']['maxage']}");
				}
				if( $this->__conf['cors']['credentials'] )
					header("Access-Control-Allow-Credentials: true");
			}
		}

		// When an OPTIONS request is received, must not serve a body,
		// so no need to go futher
		if( $_SERVER['REQUEST_METHOD'] == 'OPTIONS' ) {
			header("Allow: $allowMethods");
			return;
		}

		// Init memcached if in use
		$cache = null;
		if( isset($conf['memcache']) && isset($conf['memcache']['host']) && isset($conf['memcache']['port']) ) {
			if( class_exists('Memcache') ) {
				$cache = new Memcache;
				if( ! $cache->connect($conf['memcache']['host'], $conf['memcache']['port']) ) {
					$cache = null;
					error_log("Couldn't connect to memcached at {$conf['memcache']['host']}:{$conf['memcache']['port']}");
				}
			} else
				error_log("DoPhp is configured to use memcached but memcached extension is not loaded");
		}

		// Init return var and execute page
		$fromCache = false;
		try {
			require $inc_file;
			$classname = dophp\Utils::findClass(self::className($page));
			if( ! $classname )
				throw new Exception('Page class not found');
			$pobj = new $classname($this->__conf, $this->__db, $this->__auth, $page );
			if( ! $pobj instanceof dophp\PageInterface )
				throw new Exception('Wrong page type');
			if( $cache !== null ) {
				$cacheKey = $pobj->cacheKey();
				if( $cacheKey !== null ) {
					// Try to retrieve data from the cache
					$headers = $cache->get("$page::$cacheKey::headers");
					$out = $cache->get("$page::$cacheKey::output");
					if( $headers !== false && $out !== false )
						$fromCache = true;
				}
			}
			if( ! $fromCache )
				$out = $pobj->run();
		} catch( dophp\PageDenied $e ) {
			if( $def ) {
				if( $def == $page ) {
					// Prevent loop redirection
					header("HTTP/1.1 500 Internal Server Error");
					echo('SERVER ERROR: Login required in login page');
					return;
				}

				if( $sess )
					$_SESSION[self::SESS_LOGIN_ERROR] = $e;

				$to = dophp\Utils::fullPageUrl($def, $key);
				header("HTTP/1.1 303 Login Required");
				header("Location: $to");
				echo $e->getMessage();
				echo "\nPlease login at: $to";
				return;
			} elseif( $e instanceof dophp\InvalidCredentials ) {
				header("HTTP/1.1 401 Unhautorized");
				// Required by RFC 7235
				header("WWW-Authenticate: Custom");
				echo $e->getMessage();
				return;
			} else {
				header("HTTP/1.1 403 Forbidden");
				echo $e->getMessage();
				return;
			}
		} catch( dophp\NotAcceptable $e ) {
			header("HTTP/1.1 406 Not Acceptable");
			echo $e->getMessage();
			error_log($e->getMessage());
			return;
		} catch( dophp\PageError $e ) {
			header("HTTP/1.1 400 Bad Request");
			echo $e->getMessage();
			error_log($e->getMessage());
			return;
		} catch( Exception $e ) {
			header("HTTP/1.1 500 Internal Server Error");
			$err = "<html><h1>DoPhp Catched Exception</h1>\n" .
				'<p>&#8220;' . $e->getCode() . '.' . $e->getMessage() . "&#8220;</p>\n" .
				'<ul>' .
				'<li><b>File:</b> ' . $e->getFile() . "</li>\n" .
				'<li><b>Line:</b> ' . $e->getLine() . "</li>\n" .
				'<li><b>Trace:</b> ' . nl2br($e->getTraceAsString()) . "</li>";
			if( $this->__conf['debug'] ) {
				// Add extra useful information
				if( $e instanceof PDOException )
					$err .= "\n<li><b>Last Query:</b> " . $this->__db->lastQuery . "</li>\n" .
						'<li><b>Last Params:</b> ' . nl2br(print_r($this->__db->lastParams,true)) . "</li>\n";
			}
			$err .= '</ul></html>';
			echo($err);
			error_log(strip_tags($err));
			return;
		}

		//Get the headers
		if( ! $fromCache )
			$headers = $pobj->headers();

		// Write cache if needed
		if( $cache && $cacheKey !== null && ! $fromCache ) {
			$expire = $pobj->cacheExpire();
			if( $expire !== null ) {
				$cache->set("$page::$cacheKey::headers", $headers, 0, $expire);
				$cache->set("$page::$cacheKey::output", $out, 0, $expire);
			}
		}

		//Output headers and content
		foreach( $headers as $k=>$v )
			header("$k: $v");
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
