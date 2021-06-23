<?php

/**
* @file crud.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @see Page.php
* @brief Content backend management page classes. Replaces old crud.php
*/

namespace dophp\page\backend;

require_once(__DIR__ . '/../Page.php');


/**
 * Common trait for Backend CRUD pages
 */
trait BackendComponent {

	/** The base class name, populated at crud init time */
	protected $_base;

	/** The action part of the class name, populated at crud init time */
	protected $_action;

	/**
	 * What is being inserted/edited, should override it in child
	 * If null, a default is assigned in self::_initBackendComponent()
	 */
	protected $_what = null;

	/** The gender of what is being inserted (m/f) */
	protected $_whatGender = 'm';

	/** Name of the base template to extend */
	protected $_baseTpl = 'base-backend.tpl';

	/**
	 * Inits common CRUD stuff
	 */
	protected function _initBackendComponent() {
		if( $this->_what === null )
			$this->_what = _('element(s)');

		// Checks for a valid class name and split it into base and action
		$cls = get_called_class();
		if( substr($cls,0,strlen(\DoPhp::BASE_KEY)) != \DoPhp::BASE_KEY )
			throw new \LogicException('Unexpected abnormal base key ');
		$p = explode('_', substr($cls, strlen(\DoPhp::BASE_KEY)), 2);
		if( count($p) != 2 )
			throw new \LogicException("Invalid class name \"$cls\"");
		list($base, $action) = $p;
		$this->_base = lcfirst($base);
		$this->_action = lcfirst($action);
	}

	/**
	 * Returns the base class name
	 */
	public function getBase(): string {
		if( ! $this->_base )
			throw new \LogicException('Backend CRUD not inited');
		return $this->_base;
	}

	/**
	 * Returns the action
	 */
	public function getAction(): string {
		if( ! $this->_action )
			throw new \LogicException('Backend CRUD not inited');
		return $this->_action;
	}
}


/**
 * An "admin" table, provides both the table HTML and the AJAX data
 */
abstract class TablePage extends \dophp\HybridRpcMethod {

	use BackendComponent;
	use \dophp\SmartyFunctionalities;

	// Possible sub-actions
	const ACTION_JSON = 'json';
	const ACTION_XLSX = 'xlsx';
	const ACTION_HTML = 'html';

	protected $_compress = -1;

	/** The data query, must be overridden in the child
	 * The query must contain some special annotations. Special annotations
	 * are opened with "--++" and closed with "----".
	 * - COLS: defines column start / end block
	 */
	protected $_query;

	/** The ajax URL, defined at smarty init time */
	protected $_ajaxURL;

	/** The page title, if null, generate it */
	public $title = null;

	/** The data table object class, used by _initTable() */
	protected $_tableClass;

	/** The instantiated table holder */
	protected $_table;

	/**
	 * Inits the table object, by default inits a new _tableClass instance,
	 * may be overridden
	 */
	protected function _initTable(): \dophp\widgets\DataTableInterface {
		if( ! isset($this->_tableClass) )
			throw new \LogicException('Missing Table class');
		return new $this->_tableClass($this);
	}

	/**
	 * Perform child init tasks, may be overridden in child
	 */
	protected function _initChild() {
	}

	/**
	 * Inits the menu (when interactive), may be overridden in child
	 */
	protected function _initMenu() {
	}

	/**
	 * Called before running the action, may be overridden in child
	 *
	 * @param $action string: See ACTION_ consts
	 */
	protected function _beforeAction(string $action) {
	}

	/**
	 * Override child run determine whether to return the HTML or the JSON code
	 */
	public function run() {
		$this->_requireLogin();
		$this->_initBackendComponent();
		$this->_initChild();

		// Instantiate the table
		$this->_table = $this->_initTable();

		// Parses the super filter
		switch( $_SERVER['REQUEST_METHOD'] ) {
		case 'POST':
			$this->_table->setSFilter($_POST);
			break;
		case 'GET':
			$this->_table->setGParams($_GET);
			break;
		}

		if( \dophp\Utils::isAcceptedEncoding('application/json') )
			$action = self::ACTION_JSON;
		elseif( isset($_GET['export']) )
			$action = self::ACTION_XLSX;
		else
			$action = self::ACTION_HTML;

		$this->_beforeAction($action);

		switch( $action ) {
		case self::ACTION_JSON:
			// Return JSON data
			return parent::run();

		case self::ACTION_XLSX:
			// Return XLSX export
			if( $_GET['export'] != 'xlsx' )
				throw new \dophp\PageError('Only xlsx export is supported');

			// Run on low priority
			proc_nice(9);

			$spreadsheet = $this->_table->getXlsxData($_GET);
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
			$data = \dophp\Spreadsheet::writeToString($writer);

			$fh = \dophp\Utils::makeAttachmentHeaders(\dophp\Spreadsheet::XLSX_MIME,
				$this->getPageTitle() . '.xlsx');
			$this->_headers = array_merge($this->_headers, $fh);

			return $data;

		case self::ACTION_HTML:
			// Return HTML page
			$this->_headers['Content-type'] = 'text/html';

			// Returning HTML page
			$this->_initSmarty();
			$this->_initMenu();

			// Call subclass build
			$this->_buildSmarty();

			// Run smarty
			return $this->_compress($this->_smarty->fetch($this->_template));

		default:
			throw new \dophp\NotImplementedException("Invalid action $action");
		}
	}

	/**
	 * Inits the method
	 */
	protected function _init() {
		$this->_params = $this->_table->getParamStructure();

		parent::_init();
	}

	/**
	 * Returns full page title
	 */
	public function getPageTitle() {
		return $this->title ?? _('List') . ' ' .  ucwords($this->_what);
	}

	/**
	 * Builds the Smarty page data
	 */
	protected function _buildSmarty() {
		$this->_ajaxURL = \dophp\Url::getToStr($_GET);

		$this->_pageTitle = $this->getPageTitle();

		// If custom template does not exist, use the generic one
		$this->_templateFallback('backend/tablepage.tpl');

		$this->_smarty->assignByRef('baseTpl', $this->_baseTpl);
		$this->_smarty->assign('table', $this->_table);
		$this->_smarty->assignByRef('pageTitle', $this->_pageTitle);
		$this->_smarty->assign('action', '?'.\DoPhp::BASE_KEY."={$this->_name}");
		$this->_smarty->assignByRef('ajaxURL', $this->_ajaxURL);
	}

	/**
	 * Ajax build action
	 */
	protected function _build(&$pars) {
		return $this->_buildAjax($pars);
	}

	protected function _buildAjax( $pars ): array {
		return $this->_table->getData($pars);
	}
}


/**
 * An Insert/edit form page
 *
 * How does it work:
 * - When a GET request is sent, the page instantiates a new Form on
 *   $this->_form and it is stored in session
 * - When a POST request is sent, the new form is updated and validated
 */
abstract class FormPage extends \dophp\PageSmarty {

	/** When true, force some checks on input id */
	const ID_IS_INT = true;

	/** Edit permission */
	const PERM_EDIT = 'edit';
	/** Insert permission */
	const PERM_INS = 'ins';
	/** Delete permission */
	const PERM_DEL = 'del';
	/** View permission */
	const PERM_VIEW = 'view';

	/** Edit an element action */
	const ACT_EDIT = 'edit';
	/** Insert an element action */
	const ACT_INS = 'ins';
	/** Delete an element action */
	const ACT_DEL = 'del';

	/** Save button */
	const BTN_SAVE = 'save';
	/** Cancel button */
	const BTN_CANCEL = 'cancel';
	/** Delete button */
	const BTN_DELETE = 'delete';

	/**
	 * Session revision
	 *
	 * Can be used on certain updates to force switching to a new session
	 * This is a negative int
	 * When overridden in child classes, should use positive int
	 */
	const SESS_REV = -1;
	/** Session form key name */
	const SESS_FORM = 'form';
	/** Default session form expire time, in seconds (0/null = never) (lazy expire) */
	const SESS_FORM_EXPIRE = 60 * 60 * 24;

	/** POST key for form id */
	const POST_FORM_KEY = 'form';
	/** POST key for the data itself */
	const POST_DATA_KEY = 'data';

	use BackendComponent;

	/**
	 * The base table, if this is a simple page
	 * must define it in child or override DB methods
	 */
	protected $_table;

	/**
	 * The form destination url, inited at runtime
	 * @todo Remove it, use Form::action() directly
	 */
	protected $_formAction;

	/** GET Parameters that should be retained when going to a different tab */
	protected $_getKeep = [];

	/** Semi-static get arguments, but at runtime */
	protected $_getArgs = [];

	/**
	 * The field groups definition array, should be overridden in child.
	 * The key is the unique field group name.
	 * Every field group is definied as an array compatible with Form::__construct
	 * @see \\dophp\\widgets\\Form::__construct
	 */
	protected $_fieldGroups = [];

	/**
	 * The field definition array, should be overridden in child.
	 * The key is the unique field name, must match DMBS name.
	 * Every field is definied as an array compatible with Form::__construct
	 * @see \\dophp\\widgets\\Form::__construct
	 **/
	protected $_fields = [];

	/** The inited form instance */
	protected $_form;

	/** The returned permissions */
	protected $_perm;

	/** The requested form buttons */
	protected $_buttons;

	/** Name of the session data key */
	protected $_sesskey;

	/**
	 * Message to be displayed on save
	 * If null, a default is assigned at init time
	 * @see _initMessages
	 */
	protected $_saveMessage = null;

	/**
	 * Message to be displayed on cancel
	 * If null, a default is assigned at init time
	 * @see _initMessages
	 */
	protected $_cancelMessage = null;

	/**
	 * Message to be displayed before delete
	 * If null, a default is assigned at init time
	 * @see _initMessages
	 */
	protected $_deleteConfirmMessage = null;

	/** Disables insert (useful for non-primary tabs) */
	protected $_disableInsert = false;
	/** Disables edit (useful for insert-only tabs) */
	protected $_disableEdit = false;
	/** Disables delete */
	protected $_disableDelete = false;

	/** When true, will include readonly fields in insert data */
	protected $_insertRo = false;
	/** When true, will include readonly fields in edit data */
	protected $_editRo = false;

	/**
	 * When true, always adds the save button
	 * When false, never adds it
	 * When null (default) try to autodetect it
	 */
	protected $_addSaveButton = null;

	/**
	 * Inits $this->_form
	 *
	 * @warning The form gets inited only in some circustances. This may not get
	 *          called at all or called multiple times during a page load.
	 */
	protected function _initForm($id) {
		$this->_form = new \dophp\widgets\Form($this->_fields, [self::POST_DATA_KEY], null, $this->_fieldGroups);
	}

	/**
	 * Allows the child to init $this->_fields when the prop is not static
	 *
	 * By default, it does nothing
	 */
	protected function _initFields($id) {
	}

	/**
	 * Called as first init action, overridable in child
	 *
	 * Use this to init models
	 */
	protected function _initEarly($id) {
	}

	/**
	 * Inits messages, overridable in child
	 */
	protected function _initMessages($id) {
		if( $this->_saveMessage === null )
			$this->_saveMessage = _('Saving') . '…';

		if( $this->_cancelMessage === null )
			$this->_cancelMessage = _('Canceling') . '…';

		if( $this->_deleteConfirmMessage === null ) {
			$letter = $this->_whatGender=='f' ? 'a' : 'o';
			$this->_deleteConfirmMessage = "Confermi di voler eliminare definitivamente quest{$letter} {$this->_what}?";
		}
	}

	/**
	 * Returns ID from request args, may be null
	 */
	public static function getRequestId() {
		$id = isset($_REQUEST['id']) && $_REQUEST['id'] ? $_REQUEST['id'] : null;
		if( $id !== null && ! preg_match('/^[a-zA-Z0-9_]+$/', $id) )
			throw new \dophp\PageError("Invalid id $id");

		// Convert to int or float if numeric
		if( is_numeric($id) )
			$id = $id + 0;

		if( $id !== null && static::ID_IS_INT && ! is_int($id) )
			throw new \dophp\PageError("Non integer id $id");

		return $id;
	}

	protected function _build() {
		$this->_buttons = new \dophp\buttons\ButtonBar();

		// Determine the session data key and init it
		$this->_sesskey = get_class($this) . '::' . ((string)self::SESS_REV);
		if( ! array_key_exists($this->_sesskey, $_SESSION) || ! is_array($_SESSION[$this->_sesskey]) )
			$_SESSION[$this->_sesskey] = [];

		// Determine if editing or inserting
		$id = static::getRequestId();
		$this->_smarty->assign('id', $id);

		foreach( $this->_getKeep as $v )
			if( isset($_GET[$v]) )
				$this->_getArgs[$v] = $_GET[$v];
		$this->_smarty->assignByRef('getArgs', $this->_getArgs);

		$this->_initBackendComponent();
		$this->_initEarly($id);
		$this->_perm = $this->_getPermissions($id);
		$this->_initMessages($id);
		$this->_initFields($id);

		// Determine action
		if( $id )
			$action = $_SERVER['REQUEST_METHOD']=='DELETE' ? self::ACT_DEL : self::ACT_EDIT;
		else
			$action = self::ACT_INS;

		// Check permissions
		switch( $action ) {
		case self::ACT_INS:
			if( $this->_disableInsert )
				throw new \dophp\PageGone('Insert is disabled');
			$perm = self::PERM_INS;
			break;
		case self::ACT_EDIT:
			if( $this->_disableEdit )
				throw new \dophp\PageGone('Edit is disabled');
			$perm = [ self::PERM_EDIT, self::PERM_VIEW ];
			break;
		case self::ACT_DEL:
			if( $this->_disableDelete )
				throw new \dophp\PageGone('Delete is disabled');
			$perm = self::PERM_DEL;
			break;
		default:
			throw new \dophp\NotImplementedException("Action $action not supported");
		}
		$this->needPerm($perm);

		// Add buttons
		$this->_addButtons($id);
		$this->_smarty->assignByRef('buttons', $this->_buttons);

		// Build the form action URL
		$this->_formAction = new \dophp\Url();
		$this->_formAction->args = $this->_getArgs;
		$this->_formAction->args[\DoPhp::BASE_KEY] = $this->_name;

		$this->_smarty->assign('formkey', self::POST_FORM_KEY);
		$this->_smarty->assignByRef('saveMessage', $this->_saveMessage);
		$this->_smarty->assignByRef('cancelMessage', $this->_cancelMessage);
		$this->_smarty->assignByRef('deleteConfirmMessage', $this->_deleteConfirmMessage);

		// If custom template does not exist, use the generic one
		$this->_templateFallback('backend/formpage.tpl');

		// Child init tasks, if any
		$this->_initChild($id);

		// Handle user-submitted data
		$posted = false;
		switch( $_SERVER['REQUEST_METHOD'] ) {
		case 'POST':
			$posted = true;
			// intentional no-break
		case 'PATCH':
			// POST sends full form data, while PATCH updates just some fields
			// for validation
			$data = \dophp\Utils::getPostData();

			// Check the form-id
			if( ! isset($data[self::POST_FORM_KEY]) ) {
				// Form key is not set, may be a different kind of post,
				// like an inside DataTable request
				break;
			}

			// Get the form from SESSION
			$this->_initForm($id);
			$this->_loadFormDataFromSession($data[self::POST_FORM_KEY]);

			// Process $_FILES info
			// This should be unused since file upload is now handled asyncronously
			if( isset($_FILES) && isset($_FILES[self::POST_DATA_KEY]) ) {
				if( ! isset($data[self::POST_DATA_KEY]) )
					$data[self::POST_DATA_KEY] = [];

				foreach( $_FILES[self::POST_DATA_KEY]  as $key => $content )
					foreach( $content as $name => $val ) {
						if( ! isset($data[self::POST_DATA_KEY][$name]) )
							$data[self::POST_DATA_KEY][$name] = [];
						$data[self::POST_DATA_KEY][$name][$key] = $val;
					}
			}

			// Update the form if data key is provided
			if( isset($data[self::POST_DATA_KEY]) )
				$this->_form->setDisplayValues($data[self::POST_DATA_KEY]);
			else
				error_log('Warning: missing data key');

			if ($_SERVER['REQUEST_METHOD'] == 'PATCH' ) {
				$this->_headers['Content-Type'] = 'application/json';
				return json_encode($this->getFormValidationData());
			}
			break;
		case 'DELETE':
			// User requested to delete an element
			break;
		case 'GET':
			$this->_initForm($id);

			if( isset($_GET['ajaxField']) ) {
				// Requested an AJAX integration
				$formId = $_GET['ajaxForm'] ?? null;
				if( ! $formId )
					throw new \dophp\PageError('Missing ajaxForm');
				$this->_loadFormDataFromSession($formId);

				//TODO: This is a bit hacky, better use namespace or something
				//      more generic
				if( $this->_form->hasField($_GET['ajaxField']) )
					$field = $this->_form->field($_GET['ajaxField']);
				elseif( strpos($_GET['ajaxField'], '.') !== false ) {
					$p = explode('.', $_GET['ajaxField']);
					if( ! $this->_form->hasField($p[0]) )
						throw new \dophp\PageError("Unknown field {$_GET['ajaxField']}");

					$parent = $this->_form->field($p[0]);
					if( ! $parent instanceof \dophp\widgets\TableField )
						throw new \dophp\PageError("Unknown field {$_GET['ajaxField']}");

					$childs = $parent->childs();
					if( ! isset($childs[$_GET['ajaxField']]) )
						throw new \dophp\PageError("Unknown field {$_GET['ajaxField']}");

					$field = $childs[$_GET['ajaxField']];
				} else
					throw new \dophp\PageError("Unknown field {$_GET['ajaxField']}");
				return json_encode($field->ajaxQuery($_GET));
			} else {
				// Instantiate a new form for the fields
				if( ! $this->hasPerm(self::PERM_EDIT) )
					$this->_form->setReadOnly(true);

				$data = null;
				$errors = null;
			}
			break;
		default:
			throw new \dophp\NotImplementedException("Unsupported method {$_SERVER['REQUEST_METHOD']}");
		}

		if( isset($this->_form) ) {
			// Form is not set on DELETE
			$this->_form->action( $this->_formAction );
			$this->_smarty->assignByRef('form', $this->_form);
		}

		// Assign useful smarty variables
		$this->_smarty->assignByRef('baseTpl', $this->_baseTpl);
		$this->_smarty->assignByRef('what', $this->_what);
		$this->_smarty->assignByRef('whatGender', $this->_whatGender);

		switch( $action ) {
		case self::ACT_INS:
			$this->_setDefaultInsertPageTitle();
			$res = $this->_buildInsert($posted);
			if( $this->_form )
				$this->_saveFormDataToSession();
			break;
		case self::ACT_EDIT:
			if( $posted )
				$this->needPerm(self::PERM_EDIT);
			$this->_setDefaultEditPageTitle($id);
			$this->_formAction->args['id'] = $id;
			$res = $this->_buildEdit($id, $posted);
			if( $this->_form )
				$this->_saveFormDataToSession();
			break;
		case self::ACT_DEL:
			$this->_headers['Content-Type'] = 'text/plain';
			try {
				$res = $this->_buildDelete($id);
			} catch( FormPageDeleteConstraintError $e ) {
				header("HTTP/1.0 409 Conflict");
				if( $e->messageIsUserFriendly() )
					$res = $e->getMessage();
				else
					$res = '';
				if( $this->_config['debug'] )
					$this->_headers['X-DoPhp-Debug-DeleteReson'] = $e->getMessage();
			}
			break;
		default:
			throw new \dophp\NotImplementedException("Action $action not supported");
		}

		return $res;
	}

	/**
	 * Perform child init tasks, may be overridden in child
	 */
	protected function _initChild($id) {
	}

	/**
	 * Loads the form data from session and pass it to $this->_form->restore()
	 *
	 * @param $formId string: The form widget's unique ID
	 */
	protected function _loadFormDataFromSession(string $formId) {
		if( ! isset($_SESSION[$this->_sesskey]) || ! is_array($_SESSION[$this->_sesskey]) )
			return;
		if( ! isset($_SESSION[$this->_sesskey][static::SESS_FORM]) || ! is_array($_SESSION[$this->_sesskey][static::SESS_FORM]) )
			return;
		if( ! isset($_SESSION[$this->_sesskey][static::SESS_FORM][$formId]) )
			return;

		$sess = $_SESSION[$this->_sesskey][static::SESS_FORM][$formId];
		if( ! is_array($sess) )
			return;

		// Lazy expire: if for some reason data is still present, still use it
		// (does not check for expire here)
		if( ! isset($sess['data']) || ! is_array($sess['data']) )
			return;

		$this->_form->restore($sess['data']);
	}

	/**
	 * Stores $this->_form into session
	 */
	protected function _saveFormDataToSession() {
		if( ! ($this->_form instanceof \dophp\widgets\Form) )
			throw new \LogicException('Invalid form class');

		if( ! isset($_SESSION[$this->_sesskey]) || ! is_array($_SESSION[$this->_sesskey]) )
			$_SESSION[$this->_sesskey] = [];
		if( ! isset($_SESSION[$this->_sesskey][static::SESS_FORM]) || ! is_array($_SESSION[$this->_sesskey][static::SESS_FORM]) )
			$_SESSION[$this->_sesskey][static::SESS_FORM] = [];
		$_SESSION[$this->_sesskey][static::SESS_FORM][$this->_form->getId()] = [
			'data' => $this->_form->dump(),
			'expire' => static::SESS_FORM_EXPIRE ? time() + static::SESS_FORM_EXPIRE : null,
		];

		// Since session will be serialized by PHP, this looks like a good moment
		// to perform gc at almost-zero cost
		$this->_garbageCollectSessionFormData();
	}

	/**
	 * Clears current form's data from session
	 */
	protected function _delFormDataFromSession() {
		if( ! ($this->_form instanceof \dophp\widgets\Form) )
			throw new \LogicException('Invalid form class');

		unset($_SESSION[$this->_sesskey][static::SESS_FORM][$this->_form->getId()]);
	}

	/**
	 * Removes expired session form data
	 */
	protected function _garbageCollectSessionFormData() {
		if( ! isset($_SESSION[$this->_sesskey]) || ! is_array($_SESSION[$this->_sesskey]) )
			return;
		if( ! isset($_SESSION[$this->_sesskey][static::SESS_FORM]) || ! is_array($_SESSION[$this->_sesskey][static::SESS_FORM]) )
			return;

		$now = time();

		foreach( $_SESSION[$this->_sesskey][static::SESS_FORM] as $formId => $sess ) {
			if( ! is_array($sess) || ! isset($sess['expire']) )
				continue;

			if( ! $sess['expire'] || $sess['expire'] > $now )
				continue;

			unset($_SESSION[$this->_sesskey][static::SESS_FORM][$formId]);
		}
	}

	/**
	 * Adds buttons to the page, may be overridden in child
	 *
	 * @param $id mixed: The element id
	 */
	protected function _addButtons($id) {
		if( $this->_addSaveButton || (
			$this->_addSaveButton === null && ( ! $this->_disableInsert || ! $this->_disableEdit )
		)) {
			$this->_buttons->add(new \dophp\buttons\SaveButton());
			$this->_buttons->add(new \dophp\buttons\CancelButton());

			if( $this->hasPerm(self::PERM_EDIT) ) {
				$this->_buttons->enable(\dophp\buttons\SaveButton::DEFAULT_ID);
				$this->_buttons->enable(\dophp\buttons\CancelButton::DEFAULT_ID);
			}
		}

		if( ! $this->_disableDelete ) {
			$this->_buttons->add(new \dophp\buttons\DeleteButton());

			if( $this->hasPerm(self::PERM_DEL) )
				$this->_buttons->enable(\dophp\buttons\DeleteButton::DEFAULT_ID);
		}
	}

	/**
	 * Gets page permissions, returns both mod and insert by default
	 *
	 * @throws \dophp\PageDenied
	 * @return array of custom permissions to set, or true to mean any
	 */
	protected function _getPermissions($id) {
		return true;
	}

	/**
	 * Tells whether current user has requested permission
	 *
	 * @param $perm mixed: The permission ID (usually string)
	 * @return bool
	 */
	public function hasPerm($perm): bool {
		if( $this->_perm === true )
			return true;

		return in_array($perm, $this->_perm);
	}

	/**
	 * Checks for given permission in $this->_perms
	 *
	 * @param $perm mixed: The permission ID (usually string), or array for
	 *                     multiple permissions with OR clause
	 * @throws \\dophp\\PageDenied
	 */
	public function needPerm($perm) {
		if( $this->_perm === true )
			return;

		if( ! is_array($perm) )
			$perm = [ $perm, ];

		if( array_intersect($this->_perm, $perm) )
			return;

		$e = new \dophp\PageDenied(_('Missing required permissions'));
		$e->setDebugData($perm);
		throw $e;
	}

	/**
	 * Sets the default "insert" page title
	 */
	protected function _setDefaultInsertPageTitle() {
		//TODO: How to use gettext contexts in PHP?
		$new = $this->_whatGender=='f' ? _('(f)new') : _('(m)new');
		$this->_pageTitle = _('Insert') . ' ' . $new .  ' ' . ucwords($this->_what);
	}

	/**
	 * Sets the default "edit" page title
	 *
	 * @param $id mixed: The current edit ID
	 */
	protected function _setDefaultEditPageTitle($id) {
		$title = _('Edit') . ' ' . ucwords($this->_what);
		$sd = $this->_getShortDescr($id);
		if( $sd !== null )
			$title .= " “{$sd}„";
		$this->_pageTitle = $title;
	}

	/**
	 * Process the "insert" action
	 *
	 * @param $posted bool: Whether data has been posted
	 * @throws \dophp\UrlRedirect
	 */
	protected function _buildInsert(bool $posted) {
		// User submitted valid data
		if( $posted && $this->_form->isValid() ) {
			$id = $this->_insDbData($this->_form->getInternalValues(! $this->_insertRo));

			// Clean session: no longer needed
			$this->_delFormDataFromSession();

			// Redirect to edit
			$this->_redirectAfterInsert($id);
		} elseif( ! $posted ) {
			$this->_form->setInternalValues($this->_defDbData(), false);
		}
	}

	/**
	 * Returns redirect URL after insert, overridable in child
	 *
	 * @param $id mixed: ID of the inserted element
	 */
	public function getInsertRedirectUrl($id) {
		$url = clone $this->_formAction;
		$url->args['id'] = $id;
		return $url->asString();
	}

	/**
	 * Redirects after an isert
	 */
	protected function _redirectAfterInsert($id) {
		$location = $this->getInsertRedirectUrl($id);
		throw new \dophp\UrlRedirect($location);
	}

	/**
	 * Process the "edit" action
	 *
	 * @param $id mixed: The ID of the element being edited
	 * @param $posted bool: Whether data has been posted
	 * @throws \dophp\UrlRedirect
	 */
	protected function _buildEdit($id, bool $posted) {
		// User submitted valid data
		if( $posted && $this->_form->isValid() ) {
			$newid = $this->_updDbData($id, $this->_form->getInternalValues(! $this->_editRo));

			// Clean session: no longer needed
			$this->_delFormDataFromSession();

			// Page needs to be reloaded since data has changed
			$this->_redirectAfterEdit($newid ?? $id);
		}

		if( ! $posted || $this->_form->isValid() )
			$this->_form->setInternalValues($this->_selDbData($id), false);
	}

	/**
	 * Returns redirect URL after edit, overridable in child
	 *
	 * @param $id mixed: ID of the edited element
	 */
	public function getEditRedirectUrl($id) {
		$url = clone $this->_formAction;
		$url->args['id'] = $id;
		return $url->asString();
	}

	/**
	 * Redirects after an edit
	 */
	protected function _redirectAfterEdit($id) {
		$location = $this->getEditRedirectUrl($id);
		throw new \dophp\UrlRedirect($location);
	}

	/**
	 * Process the "delete" action
	 *
	 * @param $id mixed: The ID of the element to be deleted
	 * @return The delete message on success
	 * @throws FormPageDeleteConstraintError
	 */
	protected function _buildDelete($id) {
		try {
			$this->_delDbData($id);
		} catch( \PDOException $e ) {
			if( $this->_db->inTransaction() )
				$this->_db->rollback();

			if( $e->getCode() != 23000 )
				throw $e;

			$ce = new FormPageDeleteConstraintError($e->getMessage(), $e->getCode(), $e);
			throw $ce;
		}
		return _('Delete succesful');
	}

	/**
	 * Returns redirect URL after delete, overridable in child
	 *
	 * @note This will be called BEFORE the delete operation occurs,
	 *       so the given ID is still valid
	 * @param $id mixed: ID of the deleted element
	 */
	public function getDeleteRedirectUrl($id) {
		$name = $this->name();
		$name = substr($name, 0, strpos($name, '.mod')).'.admin';
		return \dophp\Url::fullPageUrl($name);
	}

	/**
	 * Inits the table, internal
	 */
	private function __getTable() : \dophp\Table {
		if( ! isset($this->_table) )
			throw new \LogicException('Table is not defined');

		return new \dophp\Table($this->_db, $this->_table);
	}

	/**
	 * Gets the default edit data from the DBMS, may be overridden in child
	 * by default, runs $this->_getQuery
	 *
	 * @param $id mixed: The ID of the element to be recovered
	 * @return array: associative array of element's attributes
	 */
	protected function _selDbData($id): array {
		$t = $this->__getTable();
		$pk = $t->getPk();

		if( count($pk) != 1 )
			throw new \dophp\NotImplementedException('Composed PK not yet implemented');
		$p = [ $pk[0] => $id ];

		foreach( $t->select($p) as $r )
			return $r;
		throw new \UnexpectedValueException("Element $id not found");
	}

	/**
	 * Gets the default data, should be overridden in child
	 * by default, returns an empty set
	 *
	 * @return array: associative array of element's attributes
	 */
	protected function _defDbData(): array {
		return [];
	}

	/**
	 * Inserts data into the DB
	 *
	 * @param $data array: associative array of data
	 * @return mixed: Returns the inserted ID
	 */
	protected function _insDbData(array $data) {
		$t = $this->__getTable();
		return $t->insert($data);
	}

	/**
	 * Updates database data
	 *
	 * @param $id mixed: The element's ID
	 * @param $data array: associative array of data
	 * @return mixed: May return a new ID, if changed (null means unchanged)
	 */
	protected function _updDbData($id, array $data) {
		$t = $this->__getTable();
		$cnt = $t->update($id, $data);
	}

	/**
	 * Deletes a record from the DB
	 *
	 * @param $id mixed: The element's ID
	 * @throws FormPageDeleteConstraintError
	 */
	protected function _delDbData($id) {
		$t = $this->__getTable();
		$t->delete($id);
	}

	/**
	 * Returns a short description of the record, used by default pageTitle
	 *
	 * Should be overridden in child, default implementation just returns null
	 *
	 * @param $id mixed: The current element ID
	 * @return string: The short description, or null
	 */
	protected function _getShortDescr($id) {
		return null;
	}

	/**
	 * Returns form validation data
	 */
	public function getFormValidationData(): array {
		$ret = [];
		foreach( $this->_form->fields() as $k => $f )
			$ret[$k] = [
				'status' => $f->getVStatus(),
				'feedback' => $f->getVFeedback(),
			];
		return $ret;
	}
}


/**
 * Exception thrown in FormPage when a delete operation fails
 * because of a constraint
 */
class FormPageDeleteConstraintError extends \Exception {

	private $_friendlyMex;

	/**
	 * @param $friendlyMex bool: Tells whether message should be show to user
	 */
	public function __construct(string $message='', int $code=0, \Throwable $previous=null, bool $friendlyMex=false) {
		parent::__construct($message, $code, $previous);
		$this->_friendlyMex = $friendlyMex;
	}

	public function messageIsUserFriendly(): bool {
		return $this->_friendlyMex;
	}
}
