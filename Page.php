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
	* Constructor
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

	/**
	* Utility function: checks that a valid user is logged in.
	*
	* @throws PageDenied
	*/
	protected function _requireLogin() {
		if( $this->_user->getUid() )
			return;

		throw new PageDenied('Invalid login');
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

		// Assign utility variables
		$this->_smarty->assign('this', $this);
		$this->_smarty->assign('page', $this->_name);
		foreach( $this->_config['paths'] as $k => $v )
			$this->_smarty->assign($k, $v);
		$this->_smarty->assign('config', $this->_config);
		$this->_smarty->assignByRef('user', $this->_user);

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
* Implements a RPC method, returning JSON response
*/
abstract class JsonBaseMethod extends PageBase implements PageInterface {

	/**
	* Associative array defining accepted parameters, used for request
	* validation.
	*
	* @see dophp\Validator
	*/
	protected $_params = array();

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
		$req = $this->_getInput();
		$val = new Validator($req, $_FILES, $this->_params);
		list($pars, $errors) = $val->validate();
		if( $errors ) {
			$mex = "Unvalid arguments:<br/>\n";
			foreach( $errors as $n=>$e )
				$mex .= "- <b>$n</b>: $e<br/>\n";
			throw new PageError($mex);
		}
		
		$res = $this->_build($pars);
		
		$opt = JSON_NUMERIC_CHECK;
		if(PHP_VERSION_ID >= 50303)
			$opt |= JSON_PRETTY_PRINT;
		return json_encode($res, $opt);
	}

	/**
	* Returns input parameters
	*
	* @return array Input data
	*/
	abstract protected function _getInput();

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
* Implements a RPC method, expecting a JSON-Based request
*/
abstract class JsonRpcMethod extends JsonBaseMethod {

	/**
	* Parses input JSON
	*/
	public function _getInput() {
		return json_decode(file_get_contents("php://input"), true);
	}

}

/**
* Implements a RPC method, expecting a standard POST request
*/
abstract class HybridRpcMethod extends JsonBaseMethod {

	/**
	* Returns $_POST
	*/
	public function _getInput() {
		return $_POST;
	}

}

/**
* Exception raised if something goes wrong during page rendering
*/
class PageError extends \Exception {
}

/**
* Exception raised when user is not authorized to see the page
*/
class PageDenied extends PageError {
}
