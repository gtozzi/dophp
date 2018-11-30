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

	/**
	 * Inits common CRUD stuff
	 */
	protected function _initBackendComponent() {
		if( $this->_what === null )
			$this->_what = _('element(s)');

		// Checks for a valid class name and split it into base and action
		$cls = get_called_class();
		if( substr($cls,0,strlen(\DoPhp::BASE_KEY)) != \DoPhp::BASE_KEY )
			throw new \Exception('Unexpected abnormal base key ');
		$p = explode('_', substr($cls, strlen(\DoPhp::BASE_KEY)), 2);
		if( count($p) != 2 )
			throw new \Exception("Invalid class name \"$cls\"");
		list($base, $action) = $p;
		$this->_base = lcfirst($base);
		$this->_action = lcfirst($action);
	}

	/**
	 * Returns the base class name
	 */
	public function getBase(): string {
		if( ! $this->_base )
			throw new \Exception('Backend CRUD not inited');
		return $this->_base;
	}

	/**
	 * Returns the action
	 */
	public function getAction(): string {
		if( ! $this->_action )
			throw new \Exception('Backend CRUD not inited');
		return $this->_action;
	}
}


/**
 * An "admin" table, provides both the table HTML and the AJAX data
 */
abstract class TablePage extends \dophp\HybridRpcMethod {

	use BackendComponent;
	use \dophp\SmartyFunctionalities;

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
	protected function _initTable(): \dophp\widgets\DataTable {
		if( ! isset($this->_tableClass) )
			throw new Exception('Missing Table class');
		return new $this->_tableClass($this);
	}

	/**
	 * Perform child init tasks, may be overridden in child
	 */
	protected function _initChild() {
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
		if( $_SERVER['REQUEST_METHOD'] == 'POST' )
			$this->_table->setSFilter($_POST);

		if( \dophp\Utils::isAcceptedEncoding('application/json') ) {
			// Returning JSON data
			return parent::run();
		} else {
			$this->_headers['Content-type'] = 'text/html';

			// Returning HTML page
			$this->_initSmarty();
			$this->_initMenu();

			// Call subclass build
			$this->_buildSmarty();

			// Run smarty
			return $this->_compress($this->_smarty->fetch($this->_template));
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
	 * Builds the Smarty page data
	 */
	protected function _buildSmarty() {
		$this->_ajaxURL = '';
		foreach( $_GET as $n => $v ) {
			$this->_ajaxURL .= $this->_ajaxURL ? '&' : '?';
			$this->_ajaxURL .= urlencode($n) . '=' . urlencode($v);
		}

		$this->_pageTitle = $this->title ?? _('List') . ' ' .  ucwords($this->_what);

		// By default, use the generic "admin" template
		$this->_template = 'crud/admin.tpl';

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
 *   $this->_form and it is store din session
 * - When a POST request is sent, the new form is updated and validated
 */
abstract class FormPage extends \dophp\PageSmarty {

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
	 * @see \dophp\widgets\Form::__construct
	 */
	protected $_fieldGroups = [];

	/**
	 * The field definition array, should be overridden in child.
	 * The key is the unique field name, must match DMBS name.
	 * Every field is definied as an array compatible with Form::__construct
	 * @see \dophp\widgets\Form::__construct
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
	 */
	protected $_saveMessage = null;

	/** Message to be displayed on cancel */
	protected $_cancelMessage = null;

	/** Disables insert (useful for non-primary tabs) */
	protected $_disableInsert = false;
	/** Disables edit (useful for insert-only tabs) */
	protected $_disableEdit = false;
	/** Disables delete */
	protected $_disableDelete = false;

	/**
	 * Inits $this->_form
	 */
	protected function _initForm($id) {
		$this->_form = new \dophp\widgets\Form($this->_fields, [self::POST_DATA_KEY], null, $this->_fieldGroups);
	}

	/**
	 * Allows the child to init $this->_fields when the prop is not static
	 *
	 * By default, it does nothing
	 */
	protected function _initFields() {
	}

	/**
	 * Called as first init action, overridable in child
	 */
	protected function _initEarly() {
	}

	protected function _build() {
		if( $this->_saveMessage === null )
			$this->_saveMessage = _('Saving') . '…';
		if( $this->_cancelMessage === null )
			$this->_cancelMessage = _('Canceling') . '…';

		$this->_buttons = new \dophp\buttons\ButtonBar();

		// Determine the session data key and init it
		$this->_sesskey = get_class($this) . '::' . ((string)self::SESS_REV);
		if( ! array_key_exists($this->_sesskey, $_SESSION) || ! is_array($_SESSION[$this->_sesskey]) )
			$_SESSION[$this->_sesskey] = [];

		$this->_initBackendComponent();
		$this->_initEarly();
		$this->_initFields();

		// Determine if editing or inserting
		$id = isset($_REQUEST['id']) && $_REQUEST['id'] ? $_REQUEST['id'] : null;
		$this->_smarty->assign('id', $id);

		foreach( $this->_getKeep as $v )
			if( isset($_GET[$v]) )
				$this->_getArgs[$v] = $_GET[$v];
		$this->_smarty->assignByRef('getArgs', $this->_getArgs);

		// Determine action
		if( $id )
			$action = $_SERVER['REQUEST_METHOD']=='DELETE' ? self::ACT_DEL : self::ACT_EDIT;
		else
			$action = self::ACT_INS;

		// Check permissions
		$this->_perm = $this->_getPermissions($id);
		switch( $action ) {
		case self::ACT_INS:
			if( $this->_disableInsert )
				throw new \Exception('Insert is disabled');
			$perm = self::PERM_INS;
			break;
		case self::ACT_EDIT:
			if( $this->_disableEdit )
				throw new \Exception('Insert is disabled');
			$perm = [ self::PERM_EDIT, self::PERM_VIEW ];
			break;
		case self::ACT_DEL:
			if( $this->_disableDelete )
				throw new \Exception('Insert is disabled');
			$perm = self::PERM_DEL;
			break;
		default:
			throw new \Exception("Action $action not supported");
		}
		$this->needPerm($perm);

		// Inits models, if any
		$this->_initModels($id);

		// Add buttons
		$this->_addButtons($id);
		$this->_smarty->assignByRef('buttons', $this->_buttons);

		// Build the form action URL
		$this->_formAction = new \dophp\Url();
		$this->_formAction->args = $this->_getArgs;
		$this->_formAction->args[\DoPhp::BASE_KEY] = $this->_name;

		$this->_smarty->assign('formkey', self::POST_FORM_KEY);
		$this->_smarty->assignByRef('savemessage', $this->_saveMessage);
		$this->_smarty->assignByRef('cancelmessage', $this->_cancelMessage);

		// If template does not exist, use the generic "mod" template
		$tplFound = false;
		foreach( $this->_smarty->getTemplateDir() as $td )
			if( file_exists($td . '/' . $this->_template) ) {
				$tplFound = true;
				break;
			}
		if( ! $tplFound )
			$this->_template = 'crud/mod.tpl';

		// Child init tasks, if any
		$this->_initChild($id);

		// Handle user-submitted data
		$posted = false;
		switch( $_SERVER['REQUEST_METHOD'] ) {
		case 'POST':
			$posted = true;
		case 'PATCH':
			// POST sends full form data, while PATCH updates just some fields
			// for validation

			// Get the form from SESSION
			$this->_loadFormFromSession();

			$data = \dophp\Utils::getPostData();
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

			// Check the form-id
			if( ! isset($data[self::POST_FORM_KEY]) ) {
				// Form key is not set, may be a different kind of post,
				// like an inside DataTable request
				break;
			}
			if( $data[self::POST_FORM_KEY] != $this->_form->getId() )
				throw new \Exception('Form ID mismatch');

			// Update the form
			if( ! isset($data[self::POST_DATA_KEY]) )
				throw new \Exception('Missing data key');
			$this->_form->setDisplayValues($data[self::POST_DATA_KEY]);

			if ($_SERVER['REQUEST_METHOD'] == 'PATCH' ) {
				$this->_headers['Content-Type'] = 'application/json';
				return json_encode($this->getFormValidationData());
			}
			break;
		case 'DELETE':
			// User requested to delete an element
			break;
		case 'GET':
			if( isset($_GET['ajaxField']) ) {
				// Requested an AJAX integration
				$this->_loadFormFromSession();

				$field = $this->_form->field($_GET['ajaxField']);
				return json_encode($field->ajaxQuery($_GET));
			} else {
				// Instantiate a new form for the fields
				$this->_initForm($id);
				if( ! $this->hasPerm(self::PERM_EDIT) )
					$this->_form->setReadOnly(true);
				$_SESSION[$this->_sesskey][self::SESS_FORM] = $this->_form;

				$data = null;
				$errors = null;
			}
			break;
		default:
			throw new \Exception("Unsupported method {$_SERVER['REQUEST_METHOD']}");
		}
		$this->_form->action( $this->_formAction );
		$this->_smarty->assignByRef('form', $this->_form);

		// Assign useful smarty variables
		$this->_smarty->assignByRef('what', $this->_what);
		$this->_smarty->assignByRef('whatGender', $this->_whatGender);

		switch( $action ) {
		case self::ACT_INS:
			$this->_setDefaultInsertPageTitle();
			$res = $this->_buildInsert($posted);
			break;
		case self::ACT_EDIT:
			if( $posted )
				$this->needPerm(self::PERM_EDIT);
			$this->_setDefaultEditPageTitle($id);
			$this->_formAction->args['id'] = $id;
			$res = $this->_buildEdit($id, $posted);
			break;
		case self::ACT_DEL:
			$res = $this->_buildDelete($id);
			break;
		default:
			throw new \Exception("Action $action not supported");
		}

		return $res;
	}

	/**
	 * Perform child init tasks, may be overridden in child
	 */
	protected function _initChild($id) {
	}

	/**
	 * Initialize models, may be overridden in child
	 */
	protected function _initModels($id) {
	}

	/**
	 * Loads the form from session and assign it to $this->_form
	 *
	 * @throws Exception
	 */
	protected function _loadFormFromSession() {
		if( ! isset($_SESSION[$this->_sesskey][self::SESS_FORM]) )
			throw new \Exception('Form not found');
		$this->_form = $_SESSION[$this->_sesskey][self::SESS_FORM];
		if( ! ($this->_form instanceof \dophp\widgets\Form) )
			throw new \Exception('Invalid form class');
	}

	/**
	 * Adds buttons to the page, may be overridden in child
	 *
	 * @param $id mixed: The element id
	 */
	protected function _addButtons($id) {
		$this->_buttons->add(new \dophp\buttons\SaveButton());
		$this->_buttons->add(new \dophp\buttons\CancelButton());
		$this->_buttons->add(new \dophp\buttons\DeleteButton());

		if( $this->hasPerm(self::PERM_EDIT) ) {
			$this->_buttons->enable(\dophp\buttons\SaveButton::DEFAULT_ID);
			$this->_buttons->enable(\dophp\buttons\CancelButton::DEFAULT_ID);
		}
		if( $this->hasPerm(self::PERM_DEL) )
			$this->_buttons->enable(\dophp\buttons\DeleteButton::DEFAULT_ID);
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
	 * @throws \dophp\PageDenied
	 */
	public function needPerm($perm) {
		if( $this->_perm === true )
			return;

		if( ! is_array($perm) )
			$perm = [ $perm, ];

		if( array_intersect($this->_perm, $perm) )
			return;

		throw new PageDeniedPermissions();
	}

	/**
	 * Sets the default "insert" page title
	 */
	protected function _setDefaultInsertPageTitle() {
		$new = $this->_whatGender=='f' ? _("female\x04new") : _("male\x04new");
		$this->_pageTitle = _('Insert') . ' ' . $new .  ' ' . ucwords($this->_what);
	}

	/**
	 * Sets the default "edit" page title
	 *
	 * @param $id mixed: The current edit ID
	 */
	protected function _setDefaultEditPageTitle($id) {
		$title = 'Modifica ' . ucwords($this->_what);
		$sd = $this->_getShortDescr($id);
		if( $sd !== null )
			$title .= " “{$sd}„";
		$this->_pageTitle = $title;
	}

	/**
	 * Process the "insert" action
	 *
	 * @param $posted bool: Whether data has been posted
	 */
	protected function _buildInsert(bool $posted) {
		// User submitted valid data
		if( $posted && $this->_form->isValid() ) {
			$id = $this->_insDbData($this->_form->getInternalValues());

			// Redirect to edit
			$location = $this->_getInsertRedirectUrl($id);
			$this->_headers['Location'] = $location;
			$this->_smarty->assign('location', $location);
			$this->_template = 'redirect.tpl';
			return;
		} elseif( ! $posted ) {
			$this->_form->setInternalValues($this->_defDbData(), false);
		}
	}

	/**
	 * Returns redirect URL after insert, overridable in child
	 *
	 * @param $id mixed: ID of the inserted element
	 */
	protected function _getInsertRedirectUrl($id) {
		$url = clone $this->_formAction;
		$url->args['id'] = $id;
		return $url->asString();
	}

	/**
	 * Process the "edit" action
	 *
	 * @param $id mixed: The ID of the element being edited
	 * @param $posted bool: Whether data has been posted
	 */
	protected function _buildEdit($id, bool $posted) {
		// User submitted valid data
		if( $posted && $this->_form->isValid() )
			$this->_updDbData($id, $this->_form->getInternalValues());

		if( ! $posted || $this->_form->isValid() )
			$this->_form->setInternalValues($this->_selDbData($id), false);
	}

	/**
	 * Process the "delete" action
	 *
	 * @param $id mixed: The ID of the element to be deleted
	 */
	protected function _buildDelete($id) {
		$this->_delDbData($id);
		return _('Delete succesful');
	}

	/**
	 * Inits the table, internal
	 */
	private function __getTable() : \dophp\Table {
		if( ! isset($this->_table) )
			throw new \Exception('Table is not defined');

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
			throw new \Exception('Composed PK not yet implemented');
		$p = [ $pk[0] => $id ];

		foreach( $t->select($p) as $r )
			return $r;
		throw new \Exception("Element $id not found");
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
	 */
	protected function _updDbData($id, array $data) {
		$t = $this->__getTable();
		$cnt = $t->update($id, $data);
	}

	/**
	 * Deletes a record from the DB
	 *
	 * @param $id mixed: The element's ID
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
		foreach( $this->_form->fields() as $f )
			$ret[$f->getId()] = [
				'status' => $f->getVStatus(),
				'feedback' => $f->getVFeedback(),
			];
		return $ret;
	}
}

