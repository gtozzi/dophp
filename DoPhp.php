<?php

/**
* @file DoPhp.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Main DoPhp framework class
*/

require_once(__DIR__ . '/Exceptions.php');
if( class_exists('Memcache') )
	require_once(__DIR__ . '/Cache.php');
require_once(__DIR__ . '/Url.php');
require_once(__DIR__ . '/Log.php');
require_once(__DIR__ . '/Debug.php');
require_once(__DIR__ . '/Lang.php');
require_once(__DIR__ . '/Db.php');
require_once(__DIR__ . '/Auth.php');
require_once(__DIR__ . '/Menu.php');
require_once(__DIR__ . '/Alert.php');
require_once(__DIR__ . '/Page.php');
require_once(__DIR__ . '/Validator.php');
require_once(__DIR__ . '/Utils.php');
require_once(__DIR__ . '/Buttons.php');
require_once(__DIR__ . '/Widgets.php');
require_once(__DIR__ . '/Model.php');
require_once(__DIR__ . '/smarty/libs/Smarty.class.php');
require_once(__DIR__ . '/PhpSpreadsheet.php');


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
	/** The is the key used to store alerts in $_SESSION */
	const SESS_ALERTS = 'DoPhp::Alerts';
	/** Default values for arguments */
	const DEFAULT_ARGS = [
		'conf'   => [],
		'db'     => 'dophp\\Db',
		'auth'   => null,
		'lang'   => 'dophp\\Lang',
		'log'    => null,
		'sess'   => true,
		'def'    => 'home',
		'key'    => self::BASE_KEY,
		'strict' => false,
	];
	/** Const unsed to force arguments to be loaded from config instead */
	const SEE_CONFIG = -1;

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
	/** The logger object instance */
	private $__log = null;
	/** Stores the execution start time */
	private $__start = null;
	/** Models instances cache */
	private $__models = [];
	/** Memcache instance, if used */
	private $__cache = null;
	/** The debugger object */
	private $__debug;
	/** Custom excetion printer, if any */
	private $__customExceptionPrinter = null;

	/**
	* Handles page rendering and everything
	*
	* Process request parameters and expect $key to contain the name of the
	* page to be loaded, then loads <inc_path>/<my_name>.<do>.php
	*
	* @param $args array: Parameters associative array. Keys:
	*             'conf' => array( // Configuration associative array. Keys:
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
	*                 'session' => array( // Session configuration
	*                     'name' => The name to set on session_name()
	*                 )
	*                 'memcache' => array( // Memcached configuration
	*                     // see http://php.net/manual/en/memcache.connect.php
	*                     'host'=> Valid host or unix socket
	*                     'port'=> Valid port or 0
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
	*                                   // can also be delegated from root arguments (see below)
	*                     'url'    => relative path for accessing DoPhp folder from webserver.
	*                                 Default: try to guess it
	*                     'path'   => relative or absolute DoPhp root path
	*                                 Default: automatically detect it
	*                 )
	*                 'debug' => enables debug info, should be false in production servers
	*                 'strict' => triggers an error on any notice
	*             ),
	*
	*             // For any of the following arguments, self::SEE_CONFIG can be
	*             // specified to have the param loaded from ['conf']['dophp'] array instead
	*             'db'     => name of the class to use for the database connection
	*                         Default: 'dophp\\Db'
	*             'auth'   => name of the class to use for user authentication
	*                         Default: null
	*             'lang'   => name of the class to use for multilanguage handling
	*                         Default: 'dophp\\Lang'
	*             'log'    => name of the class to use for logging
	*                         Default: null
	*             'sess'   => ff true, starts the session and uses it
	*                         Default: true
	*             'def'    => default page name, used when received missing or unvalid page
	*                         Default: 'home'
	*             'key'    => the key containing the page name
	*                         Default: self::BASE_KEY
	*             'strict' => if true, return a 500 status on ANY error
	*                         Default: false
	*             'strict' => overrides ['conf']['strict'] if given
	*/
	public function __construct(...$input) {
		// Uses ...$input for rough backward-compatibility, so it can trigger an
		// exception when deprecated calling syntax is used

		$start = microtime(true);

		// Don't allow multiple instances of this class
		if( self::$__instance )
			throw new \LogicException('DoPhp is already instantiated');
		self::$__instance = $this;
		$this->__start = $start;

		// Sanity checks over arguments list
		if( count($input) != 1 )
			throw new \InvalidArgumentException('only a single array argument should be passed');
		$args = $input[0];
		if( ! is_array($args) )
			throw new \InvalidArgumentException('args must be an array');

		// Check for invalid arguments
		foreach( $args as $k => $v )
			if( ! array_key_exists($k, self::DEFAULT_ARGS) )
				throw new \InvalidArgumentException("unknown argument \"$k\"");

		// Extract dophp arguments and assign them to local variables for convenience
		// using default values when missing or processing config delegation
		foreach( self::DEFAULT_ARGS as $k => $v ) {
			if( array_key_exists($k, $args) ) {
				$value = $args[$k];

				if( $value === self::SEE_CONFIG ) {
					// Argument must be overridden in config
					if( ! isset($args['conf']['dophp']) || ! array_key_exists($k, $args['conf']['dophp']) )
						throw new \InvalidArgumentException("argument \"$k\" must be overridden in config");

					$value = $args['conf']['dophp'][$k];
				}
			} else
				$value = $v;

			$$k = $value;
		}

		// Start the session
		if( isset($conf['session']['name']) )
			session_name($conf['session']['name']);
		if( $sess )
			session_start();

		$sesstime = microtime(true);

		// Build default config
		if( ! is_array($conf) )
			throw new \InvalidArgumentException('conf must be an array');
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
		if( ! array_key_exists('url', $this->__conf['dophp']) )
			$this->__conf['dophp']['url'] = $this->__guessMyBaseUrl();
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

		if( ! array_key_exists('strict', $this->__conf) )
			$this->__conf['strict'] = false;

		// Sets the error handler and register a shutdown function to catch fatal errors
		if( $strict || $this->__conf['strict'] ) {
			set_error_handler(array($this, 'error_handler'));
			register_shutdown_function(array('DoPhp', 'shutdown_handler'));
		}

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

		// Init memcached if in use
		if( isset($conf['memcache']) && isset($conf['memcache']['host']) ) {
			if( ! isset($conf['memcache']['port']) )
				$conf['memcache']['port'] = 11211;

			if( class_exists('Memcache') ) {
				$this->__cache = new \dophp\cache\Memcache;
				if( ! $this->__cache->connect($conf['memcache']['host'], $conf['memcache']['port']) ) {
					$this->__cache = null;
					error_log("Couldn't connect to memcached at {$conf['memcache']['host']}:{$conf['memcache']['port']}");
				}
			} else
				error_log("DoPhp is configured to use memcached but memcached extension is not loaded");
		}

		// Creates the debug object
		if( $this->__cache )
			dophp\debug\MemcacheDebug::init($this->__cache);
		else
			dophp\debug\SessionDebug::init();
		$this->__debug = new dophp\debug\Request($this->__conf['debug']);
		$this->__debug->add(new dophp\debug\CheckPoint('DoPhp::start', $this->__start));
		$this->__debug->add(new dophp\debug\CheckPoint('DoPhp::session started', $sesstime));

		// Creates database connection, if needed
		if( array_key_exists('db', $this->__conf) )
			$this->__db = new $db(
				$this->__conf['db']['dsn'],
				isset($this->__conf['db']['user']) ? $this->__conf['db']['user'] : null,
				isset($this->__conf['db']['pass']) ? $this->__conf['db']['pass'] : null,
				isset($this->__conf['db']['vcharfix']) ? $this->__conf['db']['vcharfix'] : false
			);
		$this->__db->debug = $this->__debug;

		// Creates the locale object
		$this->__lang = new $lang($this->__db, $this->__conf['lang']['supported'], $this->__conf['lang']['coding'], $this->__conf['lang']['tables']);

		// Authenticates the user, if applicable
		if( $auth ) {
			$this->__auth = new $auth($this->__conf, $this->__db, $sess);
			if( ! $this->__auth instanceof dophp\AuthInterface )
				throw new \LogicException('Wrong auth interface');
			$this->__auth->login();
		}

		// Creates the logger
		if( $log ) {
			$this->__log = new $log($start, $this->__conf, $this->__db, $this->__auth);
			if( ! $this->__log instanceof \dophp\log\Logger )
				throw new \LogicException('Wrong logger interface');
		}

		// Calculates the name of the page to be loaded
		if( array_key_exists($key, $_REQUEST) && $_REQUEST[$key] ) {
			// Page specified, use it and also explode the sub-path
			$parts = explode('/', $_REQUEST[$key], 2);
			$page = $parts[0];
			$path = isset($parts[1]) ? $parts[1] : null;
		} elseif( $def ) {
			// Page not specified, redirect to default page (if configured)
			if( isset($_REQUEST[$key]) && $def == $_REQUEST[$key] ) {
				// Prevent loop redirection
				header("HTTP/1.1 500 Internal Server Error");
				echo("SERVER ERROR: Invalid default page \"$def\"");
				return;
			}

			$to = dophp\Url::fullPageUrl($def, $key);
			header("HTTP/1.1 301 Moved Permanently");
			header("Location: $to");
			echo $to;
			return;
		} else {
			// Page not specified and no default, give a 404
			header("HTTP/1.1 400 Bad Request");
			echo('Missing "' . $key . '" argument');
			return;
		}

		// Check for existing include page file
		$pagefound = false;
		foreach( $this->__pagePaths($this->__conf, $page) as $inc_file )
			if( file_exists($inc_file) ) {
				$pagefound = true;
				break;
			}
		if( ! $pagefound ) {
			header("HTTP/1.1 404 Not Found");
			echo('Page Not Found');
			return;
		}

		// Logs the request
		$logid = null;
		if( $this->__log )
			$logid = $this->__log->logPageRequest($pagefound, $page, $path);

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

			// Sanity check on the header, just to be extra-sure
			// Origin: <scheme> "://" <hostname> [ ":" <port> ]
			if( preg_match('/^http(s)?:\/\/[a-zA-Z0-9-.]+(:[0-9]{1,5})?$/', $origin) ) {
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
			}

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

		// Init return var and execute page
		try {
			require $inc_file;
			$findName = self::className($page);
			$classname = \dophp\Utils::findClass($findName);
			if( ! $classname )
				if( $this->__conf['debug'] )
					throw new \RuntimeException("Page class \"$findName\" not found in file \"$inc_file\"");
				else
					throw new \RuntimeException('Page class not found');
			$pobj = new $classname($this->__conf, $this->__db, $this->__auth, $page, $path, $logid );

			// Inject the debug object
			$pobj->debug = $this->__debug;

			list($out, $headers) = $this->__runPage($pobj);
		} catch( \dophp\PageDenied $e ) {
			if( $def && $e->redirect !== false ) {
				if( $def == $page ) {
					// Prevent loop redirection
					header("HTTP/1.1 500 Internal Server Error");
					echo('SERVER ERROR: Login required in login page');
					return;
				}

				self::addAlert(new \dophp\LoginErrorAlert($e));

				$to = \dophp\Url::fullPageUrl($def, $key);
				header("HTTP/1.1 303 Login Required");
				header("Location: $to");
				echo $e->getMessage();
				echo "\nPlease login at: $to";
				return;
			} elseif( $e instanceof \dophp\InvalidCredentials ) {
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
		} catch( \dophp\NotAcceptable $e ) {
			header("HTTP/1.1 406 Not Acceptable");
			echo $e->getMessage();
			error_log('Not Acceptable: ' . $e->getMessage());
			return;
		} catch( \dophp\PageError $e ) {
			header("HTTP/1.1 400 Bad Request");
			echo $e->getMessage();
			error_log('Bad Request: ' . $e->getMessage());
			return;
		} catch( \dophp\PageGone $e ) {
			header("HTTP/1.1 410 Gone");
			echo $e->getMessage();
			error_log('Resource is Gone: ' . $e->getMessage());
			return;
		} catch( \Throwable $e ) {
			header("HTTP/1.1 500 Internal Server Error");
			$this->__printException($e);
			return;
		}

		//Output headers and content
		foreach( $headers as $k=>$v )
			header("$k: $v");
		echo $out;
	}

	/**
	 * Try guessing my own url
	 */
	private function __guessMyBaseUrl() {
		$maindir = dirname(get_included_files()[0]);
		$mydir = __DIR__;
		$myreldir = preg_replace('/^'.preg_quote($maindir,'/').'/', '', $mydir, 1);
		return dirname($_SERVER['PHP_SELF']) . '/' . $myreldir;
	}

	/**
	* Generates the possible file names for a given page to be included
	*
	* @param $conf array: The configuration variable
	* @param $page string: The page name
	* @return yields possible paths to search for
	*/
	private function __pagePaths($conf, $page) {
		if( strpos('/', $page) !== false || strpos('\\', $page) !== false )
			throw new \UnexpectedValueException('Page name can\'t include slashes');

		$base_file = basename($_SERVER['PHP_SELF'], '.php');
		$base_name = "$base_file.$page";
		foreach( $this->__pageNameCombos($base_name) as $name )
			yield "{$conf['paths']['inc']}/$name.php";
	}

	/**
	 * Generates possible page name combinations
	 *
	 * @param $page string: The input page name
	 * @example 'a.b.c' generates [ 'a.b.c', 'a/b.c', 'a.b/c', 'a/b/c' ]
	 * @return yields string
	 */
	private function __pageNameCombos($page) {
		yield $page;

		$dots = [];
		foreach( str_split($page) as $pos => $char )
			if( $char == '.' )
				$dots[] = $pos;

		for( $n = 1; $n <= count($dots); $n++ )
			foreach( \dophp\Utils::combinations($dots, $n) as $c ) {
				$ret = $page;
				foreach( $c as $pos )
					$ret = substr_replace($ret, '/', $pos, 1);
				yield $ret;
			}
	}

	/**
	 * Internal function. Runs a page, handles internal redirect, returns data
	 *
	 * @param $page PageInterface: The Page instance
	 * @param $depth int: The current redirect depth
	 * @param $maxDepth int: The maximum redirect depth
	 * @return array [ output string, headers associative array ]
	 */
	private function __runPage($page, $depth = 1, $maxDepth = 10) {
		if( ! $page instanceof \dophp\PageInterface )
			throw new \InvalidArgumentException('Invalid page class');

		// First attempt to retrieve data from the cache
		if( $this->__cache !== null ) {
			$cacheKey = $page->cacheKey();
			if( $cacheKey !== null ) {
				$cacheBase = $page->name() . '::' . $cacheKey;
				$headers = $cache->get("$cacheBase::headers");
				$out = $cache->get("$cacheBase::output");
				if( $headers !== false && $out !== false )
					return [ $out, $headers ];
			}
		}

		// Then run the page instead
		try {
			$out = $page->run();
			$headers = $page->headers();
		} catch( \dophp\PageRedirect $e ) {
			if( $depth >= $maxDepth )
				throw new \RuntimeException("Maximum internal redirect depth of $maxDepth reached");
			return $this->__runPage($e->getPage(), $depth + 1);
		} catch( \dophp\UrlRedirect $e ) {
			$out = $e->body();
			$headers = $e->headers();
		}

		// Write cache if needed
		if( $this->__cache !== null && $cacheKey !== null ) {
			$expire = $page->cacheExpire();
			if( $expire !== null ) {
				$cache->set("$cacheBase::headers", $headers, 0, $expire);
				$cache->set("$cacheBase::output", $out, 0, $expire);
			}
		}

		return [ $out, $headers ];
	}

	/**
	 * Throws an exception if given PHP extension is not installed
	 *
	 * @param $name string: The extension name
	 * @throws \Exception
	 */
	public static function requirePhpExt($name) {
		if ( ! extension_loaded($name))
			throw new \RuntimeException("Required PHP extension \"$name\" is not loaded");
	}

	/**
	* Returns class name for a given page
	*
	* @param $page string: The page name
	* @return string: The class name
	*/
	public static function className($page) {
		return str_replace('.','_', self::BASE_KEY . ucfirst($page));
	}

	/**
	* Returns Configuration array
	*
	* @return array: Configuration array
	*/
	public static function conf() {
		if( ! self::$__instance )
			throw new \dophp\DoPhpNotInitedException();
		return self::$__instance->__conf;
	}

	/**
	* Returns Database class instance, if available
	*
	* @return object: A dophp\Db instance
	*/
	public static function db() {
		if( ! self::$__instance )
			throw new \dophp\DoPhpNotInitedException();
		return self::$__instance->__db;
	}

	/**
	* Returns Authentication class instance, if available
	*
	* @return object: A dophp\Auth instance
	*/
	public static function auth() {
		if( ! self::$__instance )
			throw new \dophp\DoPhpNotInitedException();
		return self::$__instance->__auth;
	}

	/**
	* Returns Language class instance, if available
	*
	* @return object: A dophp\Lang instance
	*/
	public static function lang() {
		if( ! self::$__instance )
			throw new \dophp\DoPhpNotInitedException();
		return self::$__instance->__lang;
	}

	/**
	* Returns Memcache instance, if available
	*
	* @return object: A Memcache instance or null
	*/
	public static function cache() {
		if( ! self::$__instance )
			throw new \dophp\DoPhpNotInitedException();
		return self::$__instance->__cache;
	}

	/**
	* Returns Logger instance, if available
	*
	* @return \dophp\log\Logger: A Logger instance or null
	*/
	public static function log() {
		if( ! self::$__instance )
			throw new \dophp\DoPhpNotInitedException();
		return self::$__instance->__log;
	}

	/**
	* Returns a model by name
	*
	* @param $name string: The case-sensitive model's name (without prefix)
	* @return object: A model's instance
	*/
	public static function model($name) {
		if( ! self::$__instance )
			throw new \dophp\DoPhpNotInitedException();
		if( ! $name )
			throw new \InvalidArgumentException('Must give a model name');

		// Use caching if available, load the model instead
		if( ! isset(self::$__instance->__models[$name]) ) {
			require_once self::$__instance->__conf['paths']['mod'] . '/' . ucfirst($name) . '.php';
			$classname = dophp\Utils::findClass(self::MODEL_PREFIX . $name);
			if( ! $classname )
				throw new \UnexpectedValueException('Model class not found');
			$mobj = new $classname(self::$__instance->__db);
			if( ! $mobj instanceof dophp\Model )
				throw new \InvalidArgumentException('Wrong model type');

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
			throw new \dophp\DoPhpNotInitedException();

		return microtime(true) - self::$__instance->__start;
	}

	/**
	 * Adds an alert to the current alerts
	 * If session is not enabled does nothing
	 *
	 * @param $alert \dophp\Alert object to append to list
	 */
	public static function addAlert(dophp\Alert $alert) {
		if( ! self::$__instance )
			throw new \dophp\DoPhpNotInitedException();
		if( session_status() !== PHP_SESSION_ACTIVE )
			return;

		if( ! isset($_SESSION[self::SESS_ALERTS]) )
			$_SESSION[self::SESS_ALERTS] = [];
		$_SESSION[self::SESS_ALERTS][] = $alert;
	}

	/**
	 * Returns all alerts and clears the list
	 *
	 * @return array List of Alert objects (always empty if session disabled)
	 */
	public static function getAlerts() {
		if( ! self::$__instance )
			throw new \dophp\DoPhpNotInitedException();
		if( session_status() !== PHP_SESSION_ACTIVE )
			return [];
		if( ! isset($_SESSION[self::SESS_ALERTS]) )
			return [];

		$alerts = $_SESSION[self::SESS_ALERTS];
		if( ! is_array($alerts) )
			$alerts = [];
		$_SESSION[self::SESS_ALERTS] = [];
		return $alerts;
	}

	/**
	 * Returns all alerts and without clearing the list
	 *
	 * @return array List of Alert objects (always empty if session disabled)
	 */
	public static function peekAlerts() {
		if( ! self::$__instance )
			throw new \dophp\DoPhpNotInitedException();
		if( session_status() !== PHP_SESSION_ACTIVE )
			return [];
		if( ! isset($_SESSION[self::SESS_ALERTS]) )
			return [];

		return $_SESSION[self::SESS_ALERTS];
	}

	/**
	 * Sets a custom printer to be called when an exception/error is catched
	 *
	 * @param $callable callable: A callable, the following parameters are passed:
	 *                  - throwable: The raised exception or error
	 *                  - code: A fairly unique code to better find the exception
	 *                          in log files and identify it
	 *                  If may return true to also trigger the default exception
	 *                  printing
	 */
	public static function setCustomExceptionPrinter( $callable ) {
		if( ! self::$__instance )
			throw new \dophp\DoPhpNotInitedException();

		if( ! is_callable($callable) )
			throw new \InvalidArgumentException('Custom exception printer must be callable');

		self::$__instance->__customExceptionPrinter = $callable;
	}

	/**
	 * DoPhp's error handler, just takes care of setting a 500 header
	 * and leaves the rest to the default handler
	 */
	public function error_handler( $errno, $errstr, $errfile, $errline ) {
		header("HTTP/1.1 500 Internal Server Error");

		switch ($errno) {
		default:
			$et = "E$errno";
			break;
		case E_ERROR:
			$et = 'ERROR';
			break;
		case E_WARNING:
			$et = 'WARNING';
			break;
		case E_PARSE:
			$et = 'PARSE ERROR';
			break;
		case E_NOTICE:
			$et = 'NOTICE';
			break;
		case E_USER_ERROR:
			$et = 'USER ERROR';
			break;
		case E_USER_WARNING:
			$et = 'USER WARNING';
			break;
		case E_USER_NOTICE:
			$et = 'USER NOTICE';
			break;
		}

		try {
			throw new \dophp\PHPErrorException("$et $errno: '$errstr' in '$errfile', $errline");
		} catch (\dophp\PHPErrorException $e) {
			$this->__printException($e);
		}
		exit();
	}

	/**
	 * Called at shutdown, trick to catch fatal errors, sets a 500 header
	 * when possible
	 */
	public static function shutdown_handler() {
		if( error_get_last() && ! headers_sent() )
			header("HTTP/1.1 500 Internal Server Error");
	}

	/**
	 * Prints and logs an exception, internal usage
	 *
	 * @param $e Exception
	 */
	private function __printException( $e ) {
		// Assigns a faily unique ID to the exception so it can be easily found
		// in logs
		$code = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
		$user = $this->__auth ? $this->__auth->getUid() : '-';
		$url = \dophp\Url::myUrl();
		$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '-';
		error_log("DoPhp Catched Exception $code, user $user, method $method, url \"$url\": "
			. dophp\Utils::formatException($e, false));

		if( $this->__customExceptionPrinter && is_callable($this->__customExceptionPrinter))
			$print = ($this->__customExceptionPrinter)($e, $code);
		else
			$print = true;

		if( $print ) {
			if( $this->__conf['debug'] ) {
				$title = "DoPhp Catched Exception $code";
				if( dophp\Utils::isAcceptedEncoding('text/html') )
					echo "<html><h1>$title</h1>\n"
						. dophp\Utils::formatException($e, true)
						. "\n</html>";
				else
					echo $title . "\n\n" . dophp\Utils::formatException($e);
			} else
				echo _('Internal Server Error, please contact support or try again later') . '.';
		}
	}
}
