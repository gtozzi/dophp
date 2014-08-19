<?php

/**
* @file Model.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Class used to represent a DoPhp Model.
*/

namespace dophp;

/**
* Represents a DoPhp Model.
* A model extends a database table handling the conversion of the data from
* machine to human-friendly format. It takes care about labels, number and date
* formatting, defining "virtual" columns, etc...
*/
abstract class Model {

	/** Database instance */
	protected $_db;
	/**
	* The name of the underlying database table, must be overridden.
	* Will be replaced with a real instance of the table by constructor.
	*/
	protected $_table = null;
	/**
	* Field description array, should be overriden in sub-class
	* defining an associative array or arrays in the format [ <column_id> => [
	*   'label'    => string: The label to display for this field
	*   'descr'    => string: Long description
	*   'rtype'    => string: The field renderring type:
	*                         - <null> [or missing]: The field is not rendered at all
	*                         - label: The field is rendered as a label
	*                         - select: The field is rendered as a select box
	*                         - check: The field is rendered as a checkbox
	*                         - auto: The field is renderes as a suggest (autocomplete)
	*                         - text: The field is rendered as a text box
	*   'dtype'    => string: The data type, used for validation, see Validator::__construct
	*                         If null or missing, the field is automatically set
	*                         from database and omitted in insert/update queries
	*   'dopts'    => array:  Data Validation options, see Validator::__construct
	*                         If null or missing, defaults to an empty array
	*   'refer'    => class:  Name of the referenced model, if applicable
	*   'rdata'    => array:  Associative array of data for a select box, if
	*                         applicable. Overrides refer.
	*   'postp'    => func:   Post-processor: parses the data before saving it,
	*                         if applicable
	*   'i18n'     => bool:   If true, this field is "multiplied" for every
	*                         supported language. Default: false
	*   'rtab'     => bool:   If false, this field is not rendered in table view.
	*                         Defaults to true.
	* ]
	* @see initFields()
	*/
	protected $_fields = null;
	/**
	* Hunam-readable names for table items, singular and plural forms
	* (es. ['user', 'users']), should be overriden in sub-class
	* @see initNames()
	*/
	protected $_names = null;
	/**
	* The base filter to apply on the table (by exaple, for access limiting),
	* associative array of field => <value(s)>. If value is an array, multiple
	* values are allower with and OR condition
	* @see initFilter()
	*/
	protected $_filter = null;
	/**
	* Where condition built from $_filter
	* @see $_filter
	* @see buildFilter()
	*/
	protected $_filterWhere;

	/**
	* Class constuctor
	*
	* @param $db object: Database instance
	*/
	public function __construct($db) {
		$this->_db = $db;

		if( $this->_fields === null )
			$this->_fields = $this->initFields();
		if( $this->_names === null )
			$this->_names = $this->initNames();
		if( $this->_filter === null )
			$this->_filter = $this->initFilter();
		$this->_table = new Table($this->_db, $this->_table);
		
		// Clean and validate the fields array
		foreach( $this->_fields as $f => & $d ) {
			if( ! isset($d['rtype']) )
				$d['rtype'] = null;
			if( ! isset($d['dtype']) )
				$d['dtype'] = null;
			if( ! isset($d['dopts']) )
				$d['dopts'] = array();
			if( ! isset($d['i18n']) )
				$d['i18n'] = false;
			if( ! isset($d['rtab']) )
				$d['rtab'] = true;

			if( ($d['rtype']=='select' || $d['rtype']=='auto') && ! (isset($d['refer']) || array_key_exists('rdata',$d)) )
					throw new \Exception('Missing referred model or data');
			if( array_key_exists('rdata',$d) && ! is_array($d['rdata']) )
				throw new \Exception('Unvalid rdata');
		}
		unset($d);

		$this->buildFilter();
	}

	/**
	* Returns fields for this table, called when $this->_fields is not defined.
	* This way of loading fields allows the usage of gettext.
	*
	* @return array in $_fields valid format
	* @see $_fields
	*/
	protected function initFields() {
		throw new Exception('Unimplemented');
	}

	/**
	* Returns names for this table's items, called when $this->_names is not
	* defined.
	* This way of loading names allows the usage of gettext.
	*
	* @return array in $_names valid format
	* @see $_names
	*/
	protected function initNames() {
		throw new Exception('Unimplemented');
	}

	/**
	* Returns filter to apply to this table's content, called when $this->_filter
	* is not defined.
	* Allows filter definition at runtime
	*
	* @return array in $_filter valid format
	* @see $_filter
	*/
	protected function initFilter() {
		return array();
	}

	/**
	* Validate and build the filter into a where object
	*/
	protected function buildFilter() {
		$cond = '';
		$parm = array();
		foreach($this->_filter as $f => $v) {
			if( ! in_array($this->_fields[$f]['rtype'], array('select','auto')) )
				throw new \Exception('Filter is supported only on related fields (select,auto)');

			if( strlen($cond) )
				$cond .= ' AND ';

			if( ! is_array($v) ) {
				$cond .= " `$f`=? ";
				$parm[] = $v;
			} else {
				$cond .= ' ( ';
				$i = 0;
				foreach( $v as $vv ) {
					if( ++$i > 1 )
						$cond .= ' OR ';
					$cond .= " `$f`=? ";
					$parm[] = $vv;
				}
				$cond .= ' ) ';
			}
		}
		$this->_filterWhere = new Where($parm, $cond);
	}

	/**
	* Returns singular and plural names for the elements of this model
	*
	* @return array [<singular>, <plural>]
	*/
	public function getNames() {
		return $this->_names;
	}

	/**
	* Returns all column labels
	*
	* @return array Associative array of column [ <name> => <label> ]
	*/
	public function getLabels() {
		$labels = array();
		foreach( $this->_fields as $k => $f )
			if( isset($f['label']) )
				$labels[$k] = $f['label'];
		return $labels;
	}

	/**
	* Returns validation rules
	*
	* @return array Associative array [ <name> => [<type>, [<rules>]] ]
	*/
	public function getRules() {
		$rules = array();
		foreach( $this->_fields as $k => $f )
			if( isset($f['dtype']) ) {

				if( $f['i18n'] ) {
					// Copy main rule to all childs
					$sub = array();
					foreach( \DoPhp::lang()->getSupportedLanguages() as $l )
						$sub[$l] = array($f['dtype'], $f['dopts']);
					$rules[$k] = array($sub, array());
					continue;
				}

				$rules[$k] = array($f['dtype'], $f['dopts']);
			}
		return $rules;
	}

	/**
	* Returns the data for rendering an insert form and runs the insert query
	* when data is submitted
	*
	* @see _insertOrEdit()
	*/
	public function insert(& $post, & $files) {
		return $this->_insertOrEdit(null, $post, $files);
	}

	/**
	* Returns the data for rendering an edit form and runs the update query
	* when data is submitted
	*
	* @see _insertOrEdit()
	*/
	public function edit($pk, & $post, & $files) {
		return $this->_insertOrEdit($pk, $post, $files);
	}

	/**
	* Returns the data for rendering an insert or edit form and runs the
	* insert or update query when data is submitted
	*
	* @param $pk mixed: The PK to select the record to be edited, null on insert
	* @param $post array: Associative array of data, usually $_POST
	* @param $post array: Associative array of file data, usually $_FILES
	* @return array Associative array of column [ <name> => <label> ] or
	*         null on success
	*/
	protected function _insertOrEdit($pk, & $post, & $files) {
		if( $pk === null )
			$mode = 'insert';
		else
			$mode = 'edit';

		// Check if data has been submitted
		$data = null;
		$errors = null;
		if( $post ) {
			// Data has been submitted
			list($data,$errors) = $this->validate($post, $files);

			if( ! $errors ) {
				if( ! $this->isAllowed($data) )
					throw new \Exception('Saving forbidden data');

				foreach( $this->_fields as $k => $f ) {

					// Do not update empty password and file fields
					if( $mode == 'edit' && in_array($f['rtype'],array('password','file')) && array_key_exists($k,$data) && ! $data[$k] )
						unset($data[$k]);

					// Runs postprocessors
					if( isset($f['postp']) && array_key_exists($k,$data) )
						$data[$k] = $f['postp']($data[$k]);

					// Save files
					if( $f['rtype'] == 'file' )
						$data[$k] = $this->_saveFile($name, $data[$k]);
				}

				// Data is good, write the update
				if( $mode == 'edit' ) {
					foreach( $data as $k => $v )
						if( $this->_fields[$k]['i18n'] ) {
							// Leave text ID untouched and update text instead
							$txtid = $this->_table->get($pk, [$k])[$k];
							\DoPhp::lang()->updText($txtid, $v);
							unset($data[$k]);
						}
					if( count($data) )
						$this->_table->update($pk, $data);
				} elseif( $mode == 'insert' ) {
					foreach( $data as $k => & $v )
						if( $this->_fields[$k]['i18n'] ) {
							// Insert text into text table and replace l18n field
							// with its text ID
							$txtid = \DoPhp::lang()->newText($v);
							$v = $txtid;
						}
					unset($v);
					$this->_table->insert($data);
				} else
					throw new \Exception('This should never happen');

				return null;
			}

		}

		// Retrieve hard data from the DB
		if( $mode == 'edit' ) {
			$record = $this->_table->get($pk);
			if( ! $this->isAllowed($record) )
				throw new \Exception('Loading forbidden data');
		}

		// Build fields array
		$fields = array();
		foreach( $this->_fields as $k => $f ) {
			if( ! $f['rtype'] )
				continue;

			if( $f['i18n'] ) {
				// Explode l18n field
				foreach( \DoPhp::lang()->getSupportedLanguages() as $l ) {
					$fl = $f;
					$fl['label'] = $this->__buildLangLabel($fl['label'], $l);
					$val = $data&&isset($data[$k][$l]) ? $data[$k][$l] : (isset($record)?\DoPhp::lang()->getText($record[$k],$l):null);
					$err = $errors&&isset($errors[$k][$l]) ? $errors[$k][$l] : null;
					$fields["{$k}[{$l}]"] = $this->__buildField($k, $fl, $val, $err);
				}
			} else {
				$val = $data&&isset($data[$k]) ? $data[$k] : (isset($record)?$record[$k]:null);
				$err = $errors&&isset($errors[$k]) ? $errors[$k] : null;
				$fields[$k] = $this->__buildField($k, $f, $val, $err);
			}
		}

		return $fields;
	}

	/**
	* Returns the data for rendering a display page
	*
	* @param $pk mixed: The PK to select the record to be read
	* @return Array of label => value pairs
	*/
	public function read($pk) {
		if( ! $pk )
			throw new \Exception('Unvalid or missing pk');
		$res = $this->format($this->_table->get($pk));
		if( ! $this->isAllowed($res) )
			throw new \Exception('Loading forbidden data');

		$data = array();
		foreach( $res as $k => $v )
			if( $this->_fields[$k]['i18n'] )
				foreach( \DoPhp::lang()->getSupportedLanguages() as $l ) {
					$label = $this->__buildLangLabel($this->_fields[$k]['label'], $l);
					$data[$label] = $this->__reprLangLabel($v);
				}
			else
				$data[$this->_fields[$k]['label']] = $v;

		return $data;
	}

	/**
	* Returns the data for rendering a summary table
	*
	* @todo Will supporto filtering, ordering, etc...
	* @return Array of <data>: associative array of data as <pk> => <item>
	*                  <count>: total number of records found
	*                  <heads>: column headers
	*/
	public function table() {
		$cols = array();
		$labels = array();
		$allLabels = $this->getLabels();
		foreach($this->_fields as $k => $f )
			if( $f['rtab'] ) {
				$cols[] = $k;
				$labels[$k] = $allLabels[$k];
			}
		list($items, $count) = $this->_table->select($this->_filterWhere, $cols);

		$data = array();
		foreach( $items as $i ) {
			$for = $this->format($i);
			foreach( $for as $k => & $v )
				if( $this->_fields[$k]['i18n'] )
					$v = $this->__reprLangLabel($v);
				elseif( $this->_fields[$k]['rtype'] == 'select' || $this->_fields[$k]['rtype'] == 'auto' )
					if( array_key_exists('rdata',$this->_fields[$k]) )
						$v = $this->_fields[$k]['rdata'][$v];
					else
						$v = \DoPhp::model($this->_fields[$k]['refer'])->summary($v);
			unset($v);

			$data[$this->formatPk($i)] = $for;
		}

		return array($data, $count, $labels);
	}

	/**
	* Builds a localized label
	*/
	private function __buildLangLabel($label, $lang) {
		return $label . ' (' . \Locale::getDisplayLanguage($lang) . ')';
	}

	/**
	* Short representation of localize label
	*/
	private function __reprLangLabel($id) {
		$lang = \DoPhp::lang();
		$ll = $lang->getTextLangs($id);
		foreach( $ll as & $l )
			$l = $lang->getCountryCode($l);
		unset($l);
		return $lang->getText($id, $lang->getDefaultLanguage()) . ' (' . implode(',',$ll) . ')';
	}

	/**
	* Builds a single field, internal function
	*/
	private function __buildField($k, $f, $val, $err) {
		$field = array(
			'label' => $f['label'],
			'type'  => $f['rtype'],
			'descr' => $f['descr'],
			'value' => $val,
			'error' => $err,
			'data'  => null,
		);

		if( $f['rtype'] == 'select' || $f['rtype'] == 'auto' ) {
			// Retrieve data
			if( array_key_exists('rdata',$f) )
				$data = $f['rdata'];
			else
				$data = \DoPhp::model($f['refer'])->summary();

			// Filter data
			if( isset($this->_filter[$k]) ) {
				$allowed = $this->_filter[$k];
				if( ! is_array($allowed) )
					$allowed = array($allowed);
				foreach( $data as $pk => $v )
					if( ! in_array($pk, $allowed) )
						unset($data[$pk]);
			}

			$field['data'] = $data;
		}

		if( $f['rtype'] == 'password' ) // Do not show password
			$field['value'] = '';

		return $field;
	}

	/**
	* Returns the table object instance
	*/
	public function getTable() {
		return $this->_table;
	}

	/**
	* Formats a row into human-readable values
	*
	* @param $row array: Associative array, row to be formatted
	* @return array: Associative array of string, the formatted values
	*/
	public function format( $row ) {
		$ret = array();
		foreach( $row as $k => $v ) {
			
			$type = gettype($v);
			$lc = localeconv();

			if( $type == 'NULL' )
				$v = '-';
			elseif( $type == 'string' )
				$v;
			elseif( $v instanceof Time )
				$v = $v->format('H:i:s');
			elseif( $v instanceof Date )
				$v = $v->format('d.m.Y');
			elseif( $v instanceof \DateTime )
				$v = $v->format('d.m.Y');
			elseif( $type == 'boolean' )
				$v = $v ? _('Yes') : _('No');
			elseif( $type == 'integer' )
				$v = number_format($v, 0, $lc['decimal_point'], $lc['thousands_sep']);
			elseif( $type == 'double' )
				$v = number_format($v, -1, $lc['decimal_point'], $lc['thousands_sep']);
			else
				throw new \Exception("Unsupported type $type");
			
			$ret[$k] = $v;
		}
		return $ret;
	}

	/**
	* Extracts the primary key from a row and formats it into a string
	*
	* @param $row array: Associative array, row (must include PK fields)
	* @return string: The PK formatted as string
	*/
	public function formatPk( $row ) {
		$pk = $this->_table->getPk();
		
		foreach( $pk as $k )
			if( ! isset($row[$k]) )
				throw new \Exception("PK Column $k is not part of row");
		
		if( count($pk) < 2 )
			return (string)$row[$pk[0]];
		
		$ret = '[';
		foreach( $pk as $k )
			$ret .= $row[$k] . ',';
		rtrim($ret, ',');
		$ret .= ']';
		
		return $ret;
	}

	/**
	* Validates form data
	* @see dophp\Validator
	*/
	public function validate(&$post, &$files) {
		$val = new Validator($post, $files, $this->getRules());
		return $val->validate();
	}

	/**
	* Returns a short representation of model content, to be used in a select box
	* By default, only selects first non-hidden field
	*
	* @param $pk mixed: The primary key to filter for
	* @return array: Associative array [ <pk> => <description> ], or just <description>
	*                if PK is given
	*/
	public function summary($pk=null) {
		$pks = $this->_table->getPk();

		// Decide which field to use as name
		$displayCol = null;
		$intCol = null;
		foreach( $this->_fields as $n => $f )
			if( ! in_array($n,$pks) && $f['rtype'] )
				if( $f['i18n'] || $this->_table->getColumnType($n) == 'string' ) {
					$displayCol = $n;
					break;
				} elseif( ! $intCol )
					$intCol = $n;
		if( ! $displayCol )
			if( $intCol )
				$displayCol = $intCol;
			else
				$displayCol = $pks[0];

		// Retrieve and format data
		$cols = $pks;
		$cols[] = $displayCol;
		$pars = null;
		if( $pk )
			$pars = $this->_table->parsePkArgs($pk);
		list($res, $cnt) = $this->_table->select($pars, $cols);
		$ret = array();
		foreach( $res as $r ) {
			if( $this->_fields[$displayCol]['i18n'] )
				$v = $this->__reprLangLabel($r[$displayCol]);
			else
				$v = $r[$displayCol];
			$ret[$this->formatPk($r)] = $v;
		}

		if( $pk ) {
			if( count($ret) > 1 )
				throw new Exception('More than one row returned when filtering by PK');
			return array_shift($ret);
		}
		return $ret;
	}

	/**
	* Saves a file, should override
	*
	* @param $name string: the field name
	* @param $data array: File data as returned in $_FILES (<name>, <type>, <tmp_name>, <error>, <size>)
	* @return Mixed: the value to store in the database
	*/
	protected function _saveFile($name, $data) {
		throw new \Exception('Not implcmented');
	}

	/**
	* Checks if a given record is allowed base don current filter
	*
	* @param $record array: a query result or post record
	* @return boolean: True when allowed
	*/
	protected function isAllowed($record) {
		foreach( $this->_filter as $c => $v )
			if( ! isset($record[$c]) )
				return false;
			if( is_array($v) && ! in_array($record[$c], $v) )
				return false;
			if( ! is_array($v) && $record[$c] != $v )
				return false;

		return true;
	}

}
