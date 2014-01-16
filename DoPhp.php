<?php

/**
* @file DoPhp.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Main DoPhp framework class
*/

require_once(__DIR__ . '/Db.php');
require_once(__DIR__ . '/Auth.php');
require_once(__DIR__ . '/Page.php');
require_once(__DIR__ . '/Validator.php');
require_once(__DIR__ . '/Utils.php');
require_once(__DIR__ . '/smarty/libs/Smarty.class.php');

/**
* The main framework class
*/
class DoPhp {

	/** This is the base for $_GET parameters and page classes */
	const BASE_KEY = 'do';

	/**
	* Handles page rendering and everything
	*
	* Process request parameters and expect $key to contain the name of the
	* page to be loaded, then loads <inc_path>/<my_name>.<do>.php
	*
	* @param $conf array: Configuration associative array. Keys:
	*                 'paths' => array(
	*                     'inc'=> include files (page php files)
	*                     'med'=> media files (images, music, ...) and static files
	*                     'tpl'=> template files (for Smarty)
	*                     'cac'=> cache folder (must be writable)
	*                 )
	*                 'db' => array(
	*                     'dsn'=> Valid PDO dsn
	*                     'usr'=> Database username
	*                     'pwd'=> Database password
	*                 )
	* @param $locale string: The locale to set with setlocale()
	* @param $db     string: Name of the class to use for the database connection
	* @param $auth   string: Name of the class to use for user authentication
	* @param $sess   boolean: If true, starts the session and uses it
	* @param $def    string: Default page name, used when received missing or unvalid page
	* @param $key    string: the key containing the page name
	*/
	public function __construct($conf=null, $locale=null, $db='dophp\Db', $auth=null,
			$sess=true, $def='home', $key=self::BASE_KEY) {

		// Start the session
		if( $sess )
			session_start();

		// Build default config
		if( ! $conf['paths'] )
			$conf['paths'] = array();
		foreach( array('inc','med','tpl','cac') as $k )
			if( ! array_key_exists($k, $conf['paths'] ) )
				$conf['paths'][$k] = $k;

		//Set the locale
		if( $locale )
			if( ! setlocale(LC_ALL, $locale) )
				throw new Exception('Unable to set locale');

		// Creates database connection, if needed
		if( array_key_exists('db', $conf) )
			$db = new $db($conf['db']['dsn'], $conf['db']['user'], $conf['db']['pass']);

		// Authenticates the user, if applicable
		$user = null;
		if( $auth ) {
			$user = new $auth($conf, $db, $sess);
			if( ! $user instanceof dophp\AuthInterface )
				throw new Exception('Wrong auth interface');
			if( ! $user->getUid() )
				$user->login();
		}

		// Calculates the name of the page to be loaded
		$base_file = basename($_SERVER['PHP_SELF'], '.php');
		$inc_file = "{$conf['paths']['inc']}/$base_file.{$_REQUEST[$key]}.php";

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
				if( strtolower($c) == strtolower(self::BASE_KEY . $page) ) {
					$classname = $c;
					break;
				}
			if( ! $classname )
				throw new Exception('Page class not found');
			$pobj = new $classname($conf, $db, $user, $page );
			if( ! $pobj instanceof dophp\PageInterface )
				throw new Exception('Wrong page type');
			$out = $pobj->run();
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
}
