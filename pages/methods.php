<?php

/**
* @file methods.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @see Page.php
* @brief RPC handling stuff
*/

namespace dophp;

require_once(__DIR__ . '/../Page.php');


/**
* Implements a generic RPC method
*/
abstract class BaseMethod extends PageBase implements PageInterface {

	/**
	* Associative array defining accepted parameters, used for request
	* validation.
	*
	* @see \dophp\Validator
	*/
	protected $_params = array();

	/** Default headers to output */
	protected $_headers = array();

	/**
	* Prepares the enviroment and passes excution to _build() or _inavlid()
	* If succesfull, calls _output()
	*
	* @see PageInterface::run
	*/
	public function run() {
		$this->_init();

		$req = $this->_getInput();
		$val = new Validator($req, $_FILES, $this->_params);
		list($pars, $errors) = $val->validate();
		if( $errors )
			$res = $this->_invalid($pars, $errors);
		else
			$res = $this->_build($pars);

		return $this->_output($res);
	}

	/**
	 * Called before processing, useful for initing $this->_params at runtime
	 * in subclass when overridden
	 */
	protected function _init() {
	}

	/**
	* Called when arguments are invalid. By default, triggers a PageError exception.
	* May be overridden to provide custom error handling.
	*
	* @param $pars array: Associative array of invalid parameters, passed ByRef
	* @param $errors array: Associative array of errors, passed ByRef
	* @throws PageError by default
	*/
	protected function _invalid(& $pars, & $errors) {
		$mex = "Invalid arguments:<br/>\n";
		foreach( $errors as $n=>$e ) {
			$mex .= "- <b>$n</b>: " . (is_array($e)?print_r($e,true):$e) . "<br/>\n";
			if( $this->_config['debug'] )
				$mex .= '  (received: "' . print_r($pars[$n],true) . "\")<br/>\n";
		}
		throw new PageError($mex);
	}

	/**
	* Returns input parameters
	*
	* @see Utils::decodeInput()
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
			throw new PageError("Invalid type $type");
		}

		return $val;
	}

	/**
	* Build method to be overridden
	*
	* @param $pars array: The parameters associative array, passed byRef
	* @return mixed: The value to be formatted and returned to the client
	*/
	abstract protected function _build(& $pars);

	/**
	* Formats and outputs the built data
	*
	* @param $res mixed: The value to be formatted and outputted
	*/
	abstract protected function _output(& $res);
}


/**
* Implements a RPC method, returning JSON response
*/
abstract class JsonBaseMethod extends BaseMethod {

	/** Default headers for JSON output */
	protected $_headers = array(
		'Content-type' => 'application/json',
	);

	/** JSON options */
	protected $_jsonOpts = array(JSON_PRETTY_PRINT);

	/** Encodes the response into JSON */
	protected function _output(& $res) {
		$opt = 0;
		foreach( $this->_jsonOpts as $o )
			$opt |= $o;
		if(PHP_VERSION_ID < 50303)
			$opt ^= JSON_PRETTY_PRINT;
		return $this->_compress($this->_jsonEncode($res, $opt));
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
		return json_decode(Utils::decodeInput(), true);
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
