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
	* Method called prior of page execution to determine if the page can be
	* retrieved from the cache.
	*
	* @return Must return an unique cache KEY that will be used as the key for
	*         storing the output after page had been executed. When running the
	*         page again, if a valid key will be found in the cache it will be
	*         used instead of calling run() again.
	*         Returning NULL disables caching.
	*/
	public function cacheKey();

	/**
	* Returns cache expiration time, in seconds
	* 0 means never, keep data forever, null disables cache storage
	*/
	public function cacheExpire();

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
	* ZLib deflate compression level for output
	* if different than 0, enables compression. -1 to 9 or bool true.
	* If true or -1, uses default zlib's compression level.
	*/
	protected $_compress = 0;
	/**
	* If false, use compression as preferred when configured above and accepted
	* by client in the Accept-Encoding header.
	* If true, refuse to send the page when compression is enabled but not accepted
	* by client
	*/
	protected $_forceCompress = false;
	/** If set, will not compress data below this size */
	protected $_minCompressSize = null;
	/** After how many seconds cache will expire (cache is not enabled by default) */
	protected $_cacheExpire = 300;

	/** Will store the last login error occurred, if any */
	protected $_loginError = null;

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

		if( isset($_SESSION[\DoPhp::SESS_LOGIN_ERROR]) ) {
			$this->_loginError = $_SESSION[\DoPhp::SESS_LOGIN_ERROR];
			unset($_SESSION[\DoPhp::SESS_LOGIN_ERROR]);
		}
	}

	/**
	* Caching is disabled by default
	*/
	public function cacheKey() {
		return null;
	}

	/**
	* Return cache expiration time
	*/
	public function cacheExpire() {
		return $this->_cacheExpire;
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
	* @throws InvalidCredentials
	*/
	protected function _requireLogin() {
		if( $this->_user->getUid() )
			return;

		throw new InvalidCredentials('Invalid login');
	}

	/**
	* Utility function: compresses the output according to compression settings
	* and adds required header
	*
	* @param $str string: The uncompressed data
	* @return string: The data compressed or not according to compression settings
	*/
	protected function _compress($str) {
		if( $this->_compress === true )
			$this->_compress = -1;

		if( $this->_compress &&
			( $this->_minCompressSize === null || strlen($str) >= $this->_minCompressSize )
		) {
			$head = Utils::headers();
			$supported = array();
			if( isset($head['Accept-Encoding']) )
				$supported = array_map('trim', explode(',', $head['Accept-Encoding']));

			$accepted = false;
			foreach( $supported as $s )
				if( $s == 'gzip' || $s == 'deflate' ) {
					$accepted = $s;
					break;
				}

			if( $accepted ) {
				$this->_headers['Content-Encoding'] = $accepted;
				return gzcompress($str, $this->_compress, $accepted == 'gzip' ? ZLIB_ENCODING_GZIP : ZLIB_ENCODING_DEFLATE);
			} elseif( $this->_forceCompress )
				throw new NotAcceptable('Compression is required but not accepted');
		}

		return $str;
	}

	/**
	 * Utility function: returns a json encoded version of data, just like
	 * json_encode but failsafe
	 *
	 * @see json_encode
	 * @param $res mixed: The data to be encoded
	 * @param $opts int: Json options
	 * @return The json encoded data
	 * @throws RuntimeError en json_encode error
	 */
	protected function _jsonEncode(&$res, $opts=0) {
		$encoded = json_encode($res, $opts);
		if( $encoded === false )
			throw new \Exception(json_last_error_msg(), json_last_error());
		return $encoded;
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
	 * Creates an DoPhp-inited instance of smarty and returns it
	 *
	 * Useful when also using smarty in different context (eg. when sending
	 * emails)
	 *
	 * @param $config array: DoPhp config array
	 * @return Smarty instance
	 */
	public static function newSmarty(& $config) {
		$smarty = new \Smarty();

		$smarty->left_delimiter = self::TAG_START;
		$smarty->right_delimiter = self::TAG_END;
		$smarty->setTemplateDir(array(
			"{$config['paths']['tpl']}/",
			'dophp' => "{$config['dophp']['path']}/tpl/"
		));
		$smarty->setCompileDir("{$config['paths']['cac']}/");
		$smarty->setCacheDir("{$config['paths']['cac']}/");

		$smarty->registerPlugin('modifier', 'format', 'dophp\Utils::format');
		$smarty->registerPlugin('modifier', 'formatTime', 'dophp\Utils::formatTime');
		$smarty->registerPlugin('modifier', 'formatNumber', 'dophp\Utils::formatNumber');

		$smarty->assign('config', $config);

		return $smarty;
	}

	/**
	* Prepares the template system and passes execution to _build()
	*
	* @see PageInterface::run
	*/
	public function run() {
		// Init smarty
		$this->_smarty = self::newSmarty($this->_config);

		// Assign utility variables
		$this->_smarty->assign('this', $this);
		$this->_smarty->assign('page', $this->_name);
		foreach( $this->_config['paths'] as $k => $v )
			$this->_smarty->assign($k, $v);
		$this->_smarty->assignByRef('user', $this->_user);
		$this->_smarty->assignByRef('loginError', $this->_loginError);

		// Init default template name
		$base_file = basename($_SERVER['PHP_SELF'], '.php');
		$this->_template = "$base_file.{$this->_name}.tpl";

		// Call subclass build
		$this->_build();

		// Run smarty
		return $this->_compress($this->_smarty->fetch($this->_template));
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
	/** If set to true, will not call _requireLogin() */
	protected $_public = false;
	/** Name of the base template to extend */
	protected $_baseTpl = 'base-backend.tpl';

	/**
	* Process and run the CRUD actions, should be called inside _build()
	*/
	protected function _crud() {
		if( ! $this->_public )
			$this->_requireLogin();

		$this->_actions = $this->_initActions();

		// Validate GET parameters
		if( ! isset($_GET['action']) || ! array_key_exists($_GET['action'], $this->_actions) )
			throw new PageError('Invalid or missing action');
		$action = $_GET['action'];
		$pk = isset($_GET['pk']) ? $_GET['pk'] : null;

		// Load the model
		$this->_model = \DoPhp::model($this->_model);

		// Assign localized strings
		$this->_smarty->assign('strEdit', _('Edit'));
		$this->_smarty->assign('strInsert', _('Insert'));

		// Assigns general utility variables
		$this->_smarty->assign('action', $action);
		$this->_smarty->assign('buttons', $this->_buttons);
		$this->_smarty->assign('localeconv', localeconv());
		$this->_smarty->assign('baseTpl', $this->_baseTpl);

		// Process the requested action
		$method = '_build' . ucfirst($action);
		if( ! method_exists($this, $method) )
			throw new PageError("Unimplemented action \"$action\"");

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
			'ajax'    => [],
		];
	}

	/**
	* Runs the "admin" crud action
	*/
	protected function _buildAdmin() {
		list($data, $count, $heads) = $this->_model->table();

		$this->_smarty->assign('strDT', [
			'sEmptyTable' => _('No data available in table'),
			'sInfo' => _('Showing _START_ to _END_ of _TOTAL_ entries'),
			'sInfoEmpty' => _('Showing 0 to 0 of 0 entries'),
			'sInfoFiltered' => _('(filtered from _MAX_ total entries)'),
			'sInfoPostFix' => '',
			'sInfoThousands' => localeconv()['thousands_sep'],
			'sLengthMenu' => _('Show _MENU_ elements'),
			'sLoadingRecords' => _('Loading...'),
			'sProcessing' => _('Processing...'),
			'sSearch' => _('Search') . ':',
			'sZeroRecords' => _('Search returned zero records') . '.',
			'oPaginate' => [
				'sFirst' => _('First'),
				'sPrevious' => _('Previous'),
				'sNext' => _('Next'),
				'sLast' => _('Last'),
			],
			'oAria' => [
				'sSortAscending' => ': ' . _('sort the column in ascending order'),
				'sSortDescending' => ': ' . _('sort the column in descending order'),
			],
			'cInsert' => _('Insert'),
		]);

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

		if( $fields === null ) // Data has been created correctly
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
			throw new PageError('Invalid or missing pk');

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

		if( $fields === null ) // Data has been updated correctly
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

		$this->_smarty->assign('strCantDelete', _('Can\'t delete'));

		$this->_smarty->assign('pageTitle', _('Delete') . ' ' . $this->_model->getNames()[0] . " #$pk");
		$this->_smarty->assign('pk', $pk);
		$this->_smarty->assign('errors', $errors);

		$this->_template = $this->_templateName('crud/delete.tpl');
	}

	/**
	* Runs the "ajax" crud action: searches on this model. This is a special action
	*/
	protected function _buildAjax() {
		$field = $_GET['field'];
		$q = $_GET['q'];

		$data = [];
		foreach( $this->_model->fieldData($field, $q) as $d )
			$data[] = [ 'id'=>$d->value(), 'text'=>$d->descr() ];

		$this->_headers['Content-type'] = 'application/json';

		$this->_template = $this->_templateName('crud/ajax.tpl');
		$this->_smarty->assign('data', $this->_jsonEncode($data));
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
* Implements a generic RPC method
*/
abstract class BaseMethod extends PageBase implements PageInterface {

	/**
	* Associative array defining accepted parameters, used for request
	* validation.
	*
	* @see dophp\Validator
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
	* Called when arguments are invalid. By default, triggers a PageError exception.
	* May be overridden to provide custom error handling.
	*
	* @param $pars array: Associative array of invalid parameters, passed ByRef
	* @param $errors array: Associative array of errors, passed ByRef
	* @throws PageError by default
	* @return The value to be json-encoded and returned to the client
	*/
	protected function _invalid(& $pars, & $errors) {
		$mex = "Invalid arguments:<br/>\n";
		foreach( $errors as $n=>$e ) {
			$mex .= "- <b>$n</b>: $e<br/>\n";
			if( $this->_config['debug'] )
				$mex .= '  (received: "' . print_r($pars[$n],true) . "\")<br/>\n";
		}
		throw new PageError($mex);
	}

	/**
	* Returns the raw input data, after decoding
	* This is not used directly, but it may be useful when called inside _getInput()
	*
	* @return The raw decoded input
	*/
	protected function _decodeInput() {
		if( isset($_SERVER['HTTP_CONTENT_ENCODING']) && $_SERVER['HTTP_CONTENT_ENCODING'] == 'gzip' ) {
			$input = gzdecode(file_get_contents("php://input"));
			if( $input === false )
				throw new PageError('Couldn\'t decode gzip input');
		} else
			$input = file_get_contents("php://input");

		return $input;
	}

	/**
	* Returns input parameters
	*
	* @see _decodeInput()
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
	* @return The value to be formatted and returned to the client
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
		return json_decode($this->_decodeInput(), true);
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
* Exception raised when client is asking for an unavailable encoding
*/
class NotAcceptable extends PageError {
}

/**
* Exception raised when user is not authorized to see the page
*/
class PageDenied extends PageError {
}

/**
* Exception raised when user is providing invalid credentials
*/
class InvalidCredentials extends PageDenied {
}
