<?php

/**
* @file Page.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Base classes for handling pages
*/

namespace dophp;

/**
* Interface for implementing a page
*/
interface PageInterface {

	/**
	* Constructor
	*
	* @param $config array: Global config array
	* @param $db     object: Database instance
	* @param $user   object: Current user object instance
	* @param $name   string: Name of this page
	*/
	public function __construct(& $config, $db, $user, $name);

	/**
	* Method called when the page is executed
	*
	* Must return page output (usually HTML) or lauch a PageError exception
	*/
	public function run();

	/**
	* Method called to retrieve page headers
	*
	* Must return associative array
	*/
	public function headers();
}

/**
* Base class for easier page implementation
*/
abstract class PageBase {

	/** Config array */
	protected $_config;
	/** Database instance */
	protected $_db;
	/** User instance */
	protected $_user;
	/** Name of this page */
	protected $_name;
	/** Headers to be output */
	protected $_headers = array();

	/**
	* Contrsuctor
	*
	* @see PageInterface::__construct
	*/
	public function __construct(& $config, $db, $user, $name) {
		$this->_config = $config;
		$this->_db = $db;
		$this->_user = $user;
		$this->_name = $name;
	}

	/**
	* Returns headers
	*
	* @see PageInterface::headers
	*/
	public function headers() {
		return $this->_headers;
	}

}

/**
* Implements a page using Smarty template engine
*/
abstract class PageSmarty extends PageBase implements PageInterface {

	/** Using a custom delimiter for improved readability */
	const TAG_START = '{{';
	/** Using a custom delimiter for improved readability */
	const TAG_END = '}}';

	/** Smarty instance */
	protected $_smarty;

	/**
	* Prepares the template system and passes execution to _build()
	*
	* @see PageInterface::run
	*/
	public function run() {
		// Init smarty
		$this->_smarty = new \Smarty();
		$this->_smarty->left_delimiter = self::TAG_START;
		$this->_smarty->right_delimiter = self::TAG_END;
		$this->_smarty->setTemplateDir("{$this->_config['paths']['tpl']}/");
		$this->_smarty->setCompileDir("{$this->_config['paths']['cac']}/");
		$this->_smarty->setCacheDir("{$this->_config['paths']['cac']}/");

		$this->_smarty->registerPlugin('modifier', 'formatTime', 'dophp\Utils::formatTime');
		$this->_smarty->registerPlugin('modifier', 'formatNumber', 'dophp\Utils::formatNumber');

		$this->_smarty->assign('page', $this->_name);

		$this->_build();

		$base_file = basename($_SERVER['PHP_SELF'], '.php');
		return $this->_smarty->fetch("$base_file.{$this->_name}.tpl");
	}

	/**
	* Build method to be overridden
	*/
	abstract protected function _build();
}

/**
* Implements a RPC method, expecting a JSON-Based request
*/
abstract class JsonRpcMethod extends PageBase implements PageInterface {

	/** Associative array defining accepted parameters, used for request
	    validation. Keys are names of the parameters, while the content
	    describes the type. MUST be overridden.
	*/
	protected $_params;

	/** Default headers for JSON output */
	protected $_headers = array(
		'Content-type' => 'application/json',
	);

	/**
	* Prepares the enviroment and passes excution to _build()
	*
	* @see PageInterface::run
	*/
	public function run() {
		$req = json_decode(file_get_contents("php://input"), true);
		$pars = array();
		foreach( $this->_params as $k => $p )
			if( $req && array_key_exists($k, $req) )
				$pars[$k] = $this->__getParam($req, $k);
			else
				throw new PageError("Missing parameter $k");
		
		$res = $this->_build($pars);
		
		$opt = JSON_NUMERIC_CHECK;
		if(PHP_VERSION_ID >= 50303)
			$opt |= JSON_PRETTY_PRINT;
		return json_encode($res, $opt);
	}

	/**
	* Build method to be overridden
	*
	* @param $req mixed: JSON validated request decoded as array
	* @return mixed: The response to be JSON-Encoded
	*/
	abstract protected function _build(& $req);

	/**
	* Reads and validate a parameter
	*/
	private function __getParam(& $request, $name) {
		if( ! array_key_exists($name, $this->_params) )
			throw new PageError("No validation rules defined for param $name");

		$type = $this->_params[$name];
		$val = $request[$name];

		switch( $type ) {
		case 'int':
		case 'double':
		case 'string':
			if( gettype($val) != $type )
				throw new PageError("Wrong type for parameter $name");
			break;
		case 'date':
			$val = new \DateTime($val);
			break;
		default:
			throw new PageError("Unvalid type $type");
		}

		return $val;
	}
}

/**
* Exception raised if something goes wrong during page rendering
*/
class PageError extends \Exception {
}
