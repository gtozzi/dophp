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
*
* @see CrudFunctionalities
*/
abstract class PageSmarty extends PageBase implements PageInterface {

	/** Using a custom delimiter for improved readability */
	const TAG_START = '{{';
	/** Using a custom delimiter for improved readability */
	const TAG_END = '}}';

	/** Smarty instance */
	protected $_smarty;
	/** Name of the template to be used */
	protected $_template;

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
		$this->_smarty->setTemplateDir(array(
			"{$this->_config['paths']['tpl']}/",
			'dophp' => "{$this->_config['dophp']['path']}/tpl/"
		));
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

		// Init default template name
		$base_file = basename($_SERVER['PHP_SELF'], '.php');
		$this->_template = "$base_file.{$this->_name}.tpl";

		// Call subclass build
		$this->_build();

		// Run smarty
		return $this->_smarty->fetch($this->_template);
	}

	/**
	* Build method to be overridden
	*/
	abstract protected function _build();
}

/**
* Simple template for implementing a CRUD-based backend page by using the base
* Smarty implementation and the new Model system. CRUD actions are:
*
* Create: show a form for creating a new element
* Read: show a read-only representation of the element (often not used, used update in place)
* Update: show a form for editing the element
* Delete: handles deletion of the element
*
* One extra action is implemented:
* Admin: show a table for displaying a resume of the data. Usually the entry
*        point for other actions
*
* This is intended to apply to PageSmarty-based classes
*
* @see PageSmarty
* @see Model
*/
trait CrudFunctionalities {

	/** Name of the underlying model class */
	protected $_model;
	/**
	* Supported actions' definitions array. Keys:
	* - pk: if true, action requires PK as input
	* - url: if given, specify a custom string URL.
	*        '{page}', '{action}' and '{pk}' placeholders are replaced accordingly
	* every action MUST have a corresponding _build<Name>() method.
	*
	* @see _initActions()
	*/
	protected $_actions;
	/** Should display buttons on admin (resume table) page? */
	protected $_buttons = true;

	/**
	* Process and run the CRUD actions, should be called inside _build()
	*/
	protected function _crud() {
		$this->_requireLogin();

		$this->_actions = $this->_initActions();

		// Validate GET parameters
		if( ! isset($_GET['action']) || ! array_key_exists($_GET['action'], $this->_actions) )
			throw new PageError('Unvalid or missing action');
		$action = $_GET['action'];
		$pk = isset($_GET['pk']) ? $_GET['pk'] : null;

		// Load the model
		$this->_model = \DoPhp::model($this->_model);

		// Assigns general utility variables
		$this->_smarty->assign('action', $action);
		$this->_smarty->assign('buttons', $this->_buttons);
		$this->_smarty->assign('localeconv', localeconv());

		// Process the requested action
		$method = '_build' . ucfirst($action);
		if( ! method_exists($this, $method) )
			throw new Exception("Unimplemented action \"$action\"");

		if( isset($this->_actions[$action]['pk']) && $this->_actions[$action]['pk'] )
			$this->$method($pk);
		else
			$this->$method();
	}

	/**
	* Inits the actions variable, allows for more wide syntax
	*
	* @see _actions
	* @return array: actions variable
	*/
	protected function _initActions() {
		return [
			'admin'   => [],
			'create'  => [],
			'read'    => ['pk'=>true, 'icon'=>'zoom-in', 'descr'=>_('Show')],
			'update'  => ['pk'=>true, 'icon'=>'pencil',  'descr'=>_('Edit')],
			'delete'  => ['pk'=>true, 'icon'=>'remove',  'descr'=>_('Delete'), 'confirm'=>_('Are you sure?')],
		];
	}

	/**
	* Runs the "admin" crud action
	*/
	protected function _buildAdmin() {
		list($data, $count, $heads) = $this->_model->table();

		$this->_smarty->assign('pageTitle', $this->_model->getNames()[1]);
		$this->_smarty->assignByRef('items', $data);
		$this->_smarty->assign('count', $count);
		$this->_smarty->assign('cols', $heads);

		$this->_template = $this->_templateName('crud/admin.tpl');
	}

	/**
	* Runs the "create" crud action
	*/
	protected function _buildCreate() {

		$fields = $this->_model->insert($_POST, $_FILES);

		if( ! $fields ) // Data has been created correctly
			$this->_headers['Location'] = Utils::fullPageUrl($this->actionUrl('admin'),null);

		$this->_smarty->assign('pageTitle', _('Insert') . ' ' . $this->_model->getNames()[0]);
		$this->_smarty->assign('fields', $fields);
		$this->_smarty->assign('submitUrl', $this->actionUrl('create'));

		$this->_template = $this->_templateName('crud/create.tpl');
	}

	/**
	* Runs the "read" crud action
	*/
	protected function _buildRead($pk) {
		if( ! $pk )
			throw new PageError('Unvalid or missing pk');

		$this->_smarty->assign('pageTitle', $this->_model->getNames()[0] . " #$pk");
		$this->_smarty->assign('item', $this->_model->read($pk));

		$this->_template = $this->_templateName('crud/read.tpl');
	}

	/**
	* Runs the "update" crud action
	*
	* @param mixed $pk: The PK to use
	*/
	protected function _buildUpdate($pk) {

		$fields = $this->_model->edit($pk, $_POST, $_FILES);

		if( ! $fields ) // Data has been updated correctly
			$this->_headers['Location'] = Utils::fullPageUrl($this->actionUrl('admin',$pk),null);

		$this->_smarty->assign('pageTitle', _('Edit') . ' ' . $this->_model->getNames()[0] . " #$pk");
		$this->_smarty->assign('pk', $pk);
		$this->_smarty->assign('fields', $fields);
		$this->_smarty->assign('submitUrl', $this->actionUrl('update',$pk));

		$this->_template = $this->_templateName('crud/update.tpl');
	}

	/**
	* Runs the "delete" crud action
	*
	* @param mixed $pk: The PK to use
	*/
	protected function _buildDelete($pk) {

		$errors = $this->_model->delete($pk);

		if( ! $errors ) // Delete succesful
			$this->_headers['Location'] = Utils::fullPageUrl($this->actionUrl('admin',$pk),null);

		$this->_smarty->assign('pageTitle', _('Delete') . ' ' . $this->_model->getNames()[0] . " #$pk");
		$this->_smarty->assign('pk', $pk);
		$this->_smarty->assign('errors', $errors);

		$this->_template = $this->_templateName('crud/delete.tpl');
	}

	/**
	* Returns relative URL for the given action
	*
	* @param $action string: Action ID
	* @return string: The relative URL
	*/
	public function actionUrl($action, $pk=null) {
		if( isset($this->_actions[$action]['url']) ) {
			$url = $this->_actions[$action]['url'];
			$url = str_replace('{page}', $this->_name, $url);
			$url = str_replace('{action}', $action, $url);
			$url = str_replace('{pk}', $pk, $url);
		} else {
			$url = "?do={$this->_name}&action=$action";
			if( isset($this->_actions[$action]['pk']) && $this->_actions[$action]['pk'] )
				$url .= "&pk=$pk";
		}
		return $url;
	}

	/**
	* Returns template name to use, giving priority to local one and falling
	* back to DoPhp's one if not found
	*/
	protected function _templateName($name) {
		if( $this->_smarty->templateExists($name) )
			return $name;

		return "file:[dophp]$name";
	}

	/**
	* Returns list of defined actions
	*/
	public function getActions() {
		return $this->_actions;
	}

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

	/** JSON options */
	protected $_jsonOpts = array(JSON_PRETTY_PRINT);

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
		
		$opt = 0;
		foreach( $this->_jsonOpts as $o );
			$opt |= $o;
		if(PHP_VERSION_ID < 50303)
			$opt ^= JSON_PRETTY_PRINT;
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
