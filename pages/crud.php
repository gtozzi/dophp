<?php

/**
* @file oldcrud.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @see Page.php
* @brief Old CRUD handling stuff
* 
* @deprecated
*/

namespace dophp;

require_once(__DIR__ . '/../Page.php');


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
* @deprecated Use the the CRUD system instead
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
			$this->_headers['Location'] = Url::fullPageUrl($this->actionUrl('admin'),null);

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
			$this->_headers['Location'] = Url::fullPageUrl($this->actionUrl('admin',$pk),null);

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
			$this->_headers['Location'] = Url::fullPageUrl($this->actionUrl('admin',$pk),null);

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
