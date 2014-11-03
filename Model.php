<?php

/**
* @file Model.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Class used to represent a DoPhp Model and related classes
* @warning Classes in this file are still a work in progress, some functions are
*          incomplete and backward compatibility could be broken without notice
*          in future versions
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
	*                         - multi: The field is rendered as multiple select box
	*                         - check: The field is rendered as a checkbox
	*                         - auto: The field is renderes as a suggest (autocomplete)
	*                         - text: The field is rendered as a text box
	*   'dtype'    => string: The data type, used for validation, see Validator::__construct
	*                         If null or missing, the field is automatically set
	*                         from database and omitted in insert/update queries
	*                         If array, data will be serialized prior of being written
	*                         unless 'nmtab' is specified
	*   'dopts'    => array:  Data Validation options, see Validator::__construct
	*                         If null or missing, defaults to an empty array
	*                         The "required" key can be a string specifying a
	*                         single action on which the field if required (insert/update)
	*   'ropts'    => array:  Data rendering options, associative array.
	*                         'refer' => class:  Name of the referenced model, if applicable
	*                         'data' => array:  Associative array of data for a select box, if
	*                                   applicable. Overrides 'refer'.
	*                         'group' => string: Name of the field in the referenced model
	*                                    to use for grouping elements
	*   'postp'    => func:   Post-processor: parses the data before saving it,
	*                         if applicable
	*   'value'    => mixed:  If given, this field will always be set to this static value
	*   'i18n'     => bool:   If true, this field is "multiplied" for every
	*                         supported language. Default: false
	*   'edit'     => bool:   If false, this field is not altered in edit mode.
	*                         Defaults to true.
	*   'rtab'     => bool:   If false, this field is not rendered in table view.
	*                         Defaults to true.
	*   'nmtab'    => string: The name of the N:M relation tab for an array field
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
	* values are allowed with and OR condition.
	* initFilter() also supports advanced filters.
	* @see initFilter()
	* @see SimpleAccessFilter::__construct()
	*/
	protected $_filter = null;

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

		// Build and validate the filter
		if( ! $this->_filter )
			$this->_filter = new NullAccessFilter();
		elseif( gettype($this->_filter) == 'object' ) {
			if( ! $this->_filter instanceof AccessFilterInterface )
				throw new \Exception('Unvalid filter class');
		} elseif( ! is_array($this->_filter) )
			throw new \Exception('Unvalid filter format');
		else
			$this->_filter = new SimpleAccessFilter($this->_filter);

		// Clean and validate the fields array
		if( ! $this->_fields || ! is_array($this->_fields) )
			throw new \Exception('Unvalid fields');
		foreach( $this->_fields as $f => & $d ) {
			if( ! isset($d['rtype']) )
				$d['rtype'] = null;
			if( ! isset($d['dtype']) )
				$d['dtype'] = null;
			if( ! isset($d['dopts']) )
				$d['dopts'] = array();
			if( ! isset($d['ropts']) )
				$d['ropts'] = array();
			if( ! isset($d['i18n']) )
				$d['i18n'] = false;
			if( ! isset($d['edit']) )
				$d['edit'] = true;
			if( ! isset($d['rtab']) )
				$d['rtab'] = true;
			if( ! isset($d['nmtab']) )
				$d['nmtab'] = null;

			if( ($d['rtype']=='select' || $d['rtype']=='auto') && ! (isset($d['ropts']['refer']) || array_key_exists('data',$d['ropts'])) )
				throw new \Exception("Missing referred model or data for field \"$f\"");
			if( array_key_exists('data',$d['ropts']) && ! is_array($d['ropts']['data']) )
				throw new \Exception('Unvalid referred data');
		}
		unset($d);
	}

	/**
	* Returns fields for this table, called when $this->_fields is not defined.
	* This way of loading fields allows the usage of gettext.
	*
	* @return array in $_fields valid format
	* @see $_fields
	*/
	protected function initFields() {
		throw new \Exception('Unimplemented');
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
		throw new \Exception('Unimplemented');
	}

	/**
	* Returns filter to apply to this table's content, called when $this->_filter
	* is not defined.
	* Allows filter definition at runtime
	*
	* @return array in $_filter valid format OR AccessFilterInterface instance
	* @see $_filter
	* @see SimpleAccessFilter::__construct()
	*/
	protected function initFilter() {
		return array();
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
	* @param $mode   The action to get the rules for (insert or update). If
	*                unvalid or null acton is given, only rules common to all
	*                actions are returned
	* @return array Associative array [ <name> => [<type>, [<rules>]] ]
	*/
	public function getRules($mode=null) {
		$rules = array();
		foreach( $this->_fields as $k => $f )
			if( isset($f['dtype']) ) {

				if( $mode=='edit' && ! $f['edit'] )
					continue; // Skip rules for non-editable fields on edit mode

				if( $f['i18n'] ) {
					// Copy main rule to all childs
					$sub = array();
					foreach( \DoPhp::lang()->getSupportedLanguages() as $l )
						$sub[$l] = array($f['dtype'], $f['dopts']);
					$rules[$k] = array('array', array('rules'=>$sub));
					continue;
				}

				$rules[$k] = array($f['dtype'], $f['dopts']);
			}

		// Parse "required" fields according to mode
		foreach( $rules as & $r ) {
			if( ! isset($r[1]['required']) )
				continue;
			if( gettype($r[1]['required']) == 'boolean' )
				continue;
			$r[1]['required'] = ($r[1]['required']==$mode);
		}
		unset($r);

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
			foreach( $this->_fields as $k => $f ) {
				// Set static values
				if( array_key_exists('value', $f) )
					$post[$k] = $f['value'];

				// Remove non editable fields on edit mode
				if( $mode=='edit' && ! $f['edit'] && array_key_exists($k, $post) )
					unset($post[$k]);
			}

			// Data has been submitted
			list($data,$errors) = $this->validate($post, $files, $mode, $pk);

			$related = array();
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
					if( $f['rtype']=='file' && isset($data[$k]) )
						$data[$k] = $this->_saveFile($name, $data[$k]);

					// Handle multi fields
					if( $f['dtype']=='array' )
						if( ! $f['nmtab'] )
							$data[$k] = serialize($data[$k]);
						else { // Move data into "related" array and handle it later
							$related[$k] = $data[$k];
							unset($data[$k]);
						}
				}

				// Start tansaction
				$this->_db->beginTransaction();

				// Data is good, write the update
				if( $mode == 'edit' ) {
					$this->_beforeEdit($pk, $data, $related);

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
					$this->_beforeInsert($data, $related);

					foreach( $data as $k => & $v )
						if( $this->_fields[$k]['i18n'] ) {
							// Insert text into text table and replace l18n field
							// with its text ID
							$txtid = \DoPhp::lang()->newText($v);
							$v = $txtid;
						}
					unset($v);
					$pk = $this->_table->insert($data);
				} else
					throw new \Exception('This should never happen');

				// Update related data, if needed
				foreach( $related as $k => $v ) {
 					$rinfo = $this->__analyzeRelation($this->_fields[$k]);

					// Delete unwanted relations
					list($oldrels, $cnt) = $rinfo['nm']->select(array($rinfo['ncol'] => $pk), true);
					if( $oldrels )
						foreach( $oldrels as $k => $r )
							if( ! $v || ! in_array($r[$rinfo['mcol']], $v) )
								$rinfo['nm']->delete($r);

					// Insert missing relations
					if( $v )
						foreach( $v as $vv ) {
							$insdata = array($rinfo['ncol'] => $pk, $rinfo['mcol'] => $vv);
							if( ! $rinfo['nm']->select($insdata,true,true) )
								$rinfo['nm']->insert($insdata);
						}

				}

				// Run after insert/edit methods
				switch($mode) {
				case 'insert':
					$this->_afterInsert($pk, $data, $related);
					break;
				case 'edit':
					$this->_afterEdit($pk, $data, $related);
					break;
				}

				// Commit
				$this->_db->commit();

				return null;
			}

		}

		// Retrieve hard data from the DB
		if( $mode == 'edit' ) {
			$record = $this->_table->get($pk);

			foreach( $this->_fields as $n => $f )
				if( $f['dtype']=='array' )
					if( ! $f['nmtab'] )
						$record[$n] = unserialize($record[$n]);
					else { // Read data from relation
						$rinfo = $this->__analyzeRelation($f);
						list($res,$cnt) = $rinfo['nm']->select(array($rinfo['ncol']=>$pk), array($rinfo['mcol']));
						$record[$n] = array();
						foreach( $res as $r )
							$record[$n][] = $r[$rinfo['mcol']];
					}

			if( ! $this->isAllowed($record) )
				throw new \Exception('Loading forbidden data');
		}

		// Build fields array
		$fields = array();
		foreach( $this->_fields as $k => $f ) {
			if( ! $f['rtype'] )
				continue;

			if( $mode=='edit' && ! $f['edit'] ) // Don't render non editable fields in edit form
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
	* @return Array of Field instances
	*/
	public function read($pk) {
		if( ! $pk )
			throw new \Exception('Unvalid or missing pk');
		$res = $this->_table->get($pk);
		if( ! $this->isAllowed($res) )
			throw new \Exception('Loading forbidden data');

		$data = array();
		foreach( $res as $k => $v )
			$data[$k] = new Field($v, $this->_fields[$k]);

		return $data;
	}

	/**
	* Returns the data for rendering a summary table
	*
	* @todo Will supporto filtering, ordering, etc...
	* @return Array of <data>: associative array of data as <pk> => <Field>
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
		list($items, $count) = $this->_table->select($this->_filter->getRead(), $cols);

		$data = array();
		foreach( $items as $i ) {
			foreach( $i as $k => & $v )
				$v = new Field($v, $this->_fields[$k]);
			unset($v);

			$data[$this->formatPk($i)] = $i;
		}

		return array($data, $count, $labels);
	}

	/**
	* Try to delete an element, returns a human.friendly error when failed
	*
	* @param $pk mixed: The PK to select the record to be read
	* @return array: [User errror message, Detailed error message] or NULL on success
	*/
	public function delete($pk) {
		if( ! $pk )
			throw new \Exception('Unvalid or missing pk');

		$this->_db->beginTransaction();

		$this->_beforeDelete($pk);

		try {
			$this->_table->delete($pk);
		} catch( \PDOException $e ) {
			$this->_db->rollBack();

			list($scode, $mcode, $mex) = $e->errorInfo;

			if( $scode == '23000' && $mcode == 1451 )
				return array(_('item is in use'), $e->getMessage());
			else
				return array($e->getMessage(), $e->getMessage());
		}

		$this->_afterDelete($pk);

		$this->_db->commit();

		return null;
	}

	/**
	* Builds a localized label
	*/
	private function __buildLangLabel($label, $lang) {
		return $label . ' (' . \Locale::getDisplayLanguage($lang) . ')';
	}

	/**
	* Builds a single field, internal function
	*
	* @param $k string: The field name
	* @param $f array: The field definition
	* @param $value mixed: The field value
	* @param $error string: The error message
	*
	* @return FormField: The built field
	*/
	private function __buildField($k, & $f, $value, $error) {
		$data = null;
		if( $f['rtype'] == 'select' || $f['rtype'] == 'multi' || $f['rtype'] == 'auto' ) {
			// Retrieve data
			$groups = array();
			if( array_key_exists('data',$f['ropts']) )
				$data = $f['ropts']['data'];
			else {
				if( ! isset($f['ropts']['refer']) )
					throw New \Exception("Need refer or data for $k field");
				$rmodel = \DoPhp::model($f['ropts']['refer']);
				$data = $rmodel->summary();
				if( isset($f['ropts']['group']) )
					foreach( $data as $pk => $v )
						$groups[$pk] = $rmodel->read($pk)[$f['ropts']['group']]->format();
			}

			// Filter data
			if( isset($this->_filter) )
				foreach( $data as $pk => $v )
					if( ! $this->_filter->isAllowed($k, $pk) )
						unset($data[$pk]);

			// Assemble data
			foreach( $data as $k => & $v )
				$v = new FormFieldData($k, $v, isset($groups[$k])?$groups[$k]:null);
		}

		if( $f['rtype'] == 'password' ) // Do not show password
			$value = null;

		return new FormField($value, $f, $error, $data);
	}

	/**
	* Analyzes a relation, internal function
	*
	* @return array: Associative array with informations about the relation:
	*                'ropts' => [ 'refer' => The referred Model instance ]
	*                'mn'    => The n:m Table instance
	*                'ncol'  => Name of the column referring to my table in NM table's PK
	*                'mcol'  => Name of the column referring to referred in NM table's PK
	*/
	private function __analyzeRelation($field) {

		// Use caching to avoid multiple long queries
		static $cache = null;
		if( $cache !== null )
			return $cache;

		// If cache is not available, do the full analysis
		$refer = \DoPhp::model($field['ropts']['refer']);
		$nm = new Table($this->_db, $field['nmtab']);

		$npk = $this->_table->getPk();
		if( count($npk) != 1 )
			throw new \Exception('Unsupported composed or missing PK');
		$npk = $npk[0];
		$mpk = $refer->getTable()->getPk();
		if( count($mpk) != 1 )
			throw new \Exception('Unsupported composed or missing PK');
		$mpk = $mpk[0];
		$ncol = null; // Name of the column referring my table in n:m
		$mcol = null; // Name of the column referring other table in n:m
		foreach( $nm->getRefs() as $col => list($rtab, $rcol) ) {
			if( ! $ncol && $rtab == $this->_table->getName() && $rcol == $npk )
				$ncol = $col;
			elseif( ! $mcol && $rtab == $refer->getTable()->getName() && $rcol == $mpk )
				$mcol = $col;

			if( $ncol && $mcol )
				break;
		}

		if( ! $ncol || ! $mcol )
			throw new \Exception('Couldn\'t find relations on n:m table');
		$nmpk = $nm->getPk();
		if( count($nmpk) < 2 )
			throw new \Exception('m:m table must have a composite PK');
		elseif( count($nmpk) != 2 )
			throw new \Exception('Unsupported PK in n:m table');

		if( array_search($ncol, $nmpk) === false || array_search($mcol, $nmpk) === false )
			throw new \Exception('Couldn\'t find columns in relation');

		$cache = array(
			'refer' => $refer,
			'nm' => $nm,
			'ncol' => $ncol,
			'mcol' => $mcol,
		);
		return $cache;
	}

	/**
	* Returns the table object instance
	*/
	public function getTable() {
		return $this->_table;
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
	*
	* @param $post array: POST data
	* @param $files array: FILES data
	* @param $mode string: Running mode ('insert', 'edit', null if unknown)
	* @param $pk mixed: The PK on edit mode, null if unknown (unused, may be used in subclass)
	* @see getRules()
	* @see dophp\Validator
	*/
	public function validate(&$post, &$files, $mode=null, $pk=null) {
		$val = new Validator($post, $files, $this->getRules($mode));
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
		$pars = $this->_filter->getRead();
		if( $pk )
			$pars->add(new Where($this->_table->parsePkArgs($pk)));
		list($res, $cnt) = $this->_table->select($pars, $cols);
		$ret = array();
		foreach( $res as $r ) {
			$f = new Field($r[$displayCol], $this->_fields[$displayCol]);
			$ret[$this->formatPk($r)] = $f->format();
		}

		if( $pk ) {
			if( count($ret) > 1 )
				throw new \Exception('More than one row returned when filtering by PK');
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
		throw new \Exception('saveFile not implemented');
	}

	/**
	* Checks if a given record is allowed based on current filter
	*
	* @param $record array: a query result or post record
	* @return boolean: True when allowed
	*/
	protected function isAllowed($record) {
		if( ! $this->_filter )
			return true;

		foreach( $record as $c => $v )
			if( ! $this->_filter->isAllowed($c, $v) )
				return false;

		return true;
	}

	/**
	* Runs custom actions before an item has to be inserted
	* does nothing by default, may be overridden
	*
	* @param $data array: The data to be inserted, may be modified byRef
	* @param $related array: Optional related data
	*/
	protected function _beforeInsert( & $data, & $related ) { }

	/**
	* Runs custom actions before an item has to be edited
	* does nothing by default, may be overridden
	*
	* @param $pk mixed: The primary key
	* @param $data array: The data to be edited, may be modified byRef
	* @param $related array: Optional related data
	*/
	protected function _beforeEdit($pk, & $data, & $related ) { }

	/**
	* Runs custom actions before an item has to be deleted
	* does nothing by default, may be overridden
	*
	* @param $pk mixed: The primary key
	*/
	protected function _beforeDelete($pk) { }

	/**
	* Runs custom actions after an item has been inserted
	* does nothing by default, may be overridden
	*
	* @param $pk mixed: The primary key
	* @param $data array: The data just inserted
	* @param $related array: Optional related data
	*/
	protected function _afterInsert($pk, & $data, & $related ) { }

	/**
	* Runs custom actions after an item has been edited
	* does nothing by default, may be overridden
	*
	* @param $pk mixed: The primary key
	* @param $data array: The data just edited
	* @param $related array: Optional related data
	*/
	protected function _afterEdit($pk, & $data, & $related ) { }

	/**
	* Runs custom actions after an item has been deleted
	* does nothing by default, may be overridden
	*
	* @param $pk mixed: The primary key
	*/
	protected function _afterDelete($pk) { }

}


/**
* Represents a data field, carrying a raw value
*/
class Field {

	/** The raw value, ready to be written into DB */
	protected $_value;
	/** The field definition */
	protected $_def;

	/**
	* Creates the field
	*
	* @param array def: The field definition
	* @param mixed value: The raw value
	*/
	public function __construct($value, & $def) {
		$this->_value = $value;
		$this->_def = $def;
	}

	/**
	* Returns the raw value for this field
	*/
	public function value() {
		return $this->_value;
	}

	/**
	* Formats the raw value into human-readable data
	*
	* @return string: the formatted value
	*/
	public function format() {
		$type = gettype($this->_value);
		$lc = localeconv();

		if( $type == 'NULL' )
			$val = '-';
		elseif( $type == 'string' )
			$val = $this->_value;
		elseif( $this->_value instanceof Time )
			$val = $this->_value->format('H:i:s');
		elseif( $this->_value instanceof Date )
			$val = $this->_value->format('d.m.Y');
		elseif( $this->_value instanceof \DateTime )
			$val = $this->_value->format('d.m.Y H:i:s');
		elseif( $type == 'boolean' )
			$val = $this->_value ? _('Yes') : _('No');
		elseif( $type == 'integer' )
			$val = number_format($this->_value, 0, $lc['decimal_point'], $lc['thousands_sep']);
		elseif( $type == 'double' )
			$val = number_format($this->_value, -1, $lc['decimal_point'], $lc['thousands_sep']);
		else
			throw new \Exception("Unsupported type $type");

		// Handle i18n and relations
		if( $this->_def['i18n'] )
			$val = $this->__reprLangLabel($val);
		elseif( $this->_def['rtype'] == 'select' || $this->_def['rtype'] == 'auto' )
			if( array_key_exists('data',$this->_def['ropts']) )
				$val = $this->_def['ropts']['data'][$val];
			else
				$val = \DoPhp::model($this->_def['ropts']['refer'])->summary($val);

		return $val;
	}

	/**
	* Returns a string version of this field
	*/
	public function __toString() {
		if( $this->_value instanceof Time || $this->_value instanceof Date || $this->_value instanceof \DateTime )
			return $this->format();
		return (string) $this->_value;
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

	public function label() {
		return $this->_def['label'];
	}
	public function type() {
		return $this->_def['rtype'];
	}
	public function descr() {
		return $this->_def['descr'];
	}

}


/**
* Represents a form field
*/
class FormField extends Field {

	protected $_label;
	protected $_type;
	protected $_error;
	protected $_data;

	/**
	* Creates a new form field
	*
	* @see Field::__construct
	* @param $label string: The label for the field
	* @param $type string: The field's type
	* @param $error string: The error message
	* @param $data array: The related data, array of FormFieldData objects
	*/
	public function __construct($value, $def, $error, $data) {
		parent::__construct($value, $def);
		$this->_error = $error;
		$this->_data = $data;
	}

	public function error() {
		return $this->_error;
	}
	public function data() {
		return $this->_data;
	}

}

/**
* Data for a form field
*/
class FormFieldData {

	protected $_value;
	protected $_descr;
	protected $_group;

	/**
	* Creates a new data instance
	*/
	public function __construct($value, $descr, $group=null) {
		$this->_value = $value;
		$this->_descr = $descr;
		$this->_group = $group;
	}

	public function value() {
		return $this->_value;
	}
	public function descr() {
		return $this->_descr;
	}
	public function group() {
		return $this->_group;
	}

}


/**
* Interface for bullding custom filter classes
*/
interface AccessFilterInterface {

	/**
	* Returns a filter to apply to "read" queries
	*
	* @return object: Where instance
	*/
	public function getRead();

	/**
	* Checks if a value is allowed for a given field
	*
	* @param $field string: name of the field
	* @param $val mixed: the value to be assigned
	*/
	public function isAllowed($field, $val);

}

/**
* Simple basic access filter implementation
*/
class SimpleAccessFilter implements AccessFilterInterface {

	protected $conditions;
	protected $_where;

	/**
	* Builds the filter from an array of where conditions to be concatenated with
	* AND operator
	*
	* @param $conditions array: Associative array of conditions
	*/
	public function __construct($conditions) {
		$this->_conditions = $conditions;

		$cond = '';
		$parm = array();
		foreach($conditions as $f => $v) {
			if( strlen($cond) )
				$cond .= ' AND ';

			if( ! is_array($v) ) {
				$cond .= " `$f`=? ";
				$parm[] = $v;
			} else {
				if( count($v) ) {
					$cond .= ' ( ';
					$i = 0;
					foreach( $v as $vv ) {
						if( ++$i > 1 )
							$cond .= ' OR ';
						$cond .= " `$f`=? ";
						$parm[] = $vv;
					}
					$cond .= ' ) ';
				} else
					$cond .= ' FALSE ';
			}
		}

		$this->_where = new Where($parm, $cond);
	}

	public function getRead() {
		return $this->_where;
	}

	public function isAllowed($field, $val) {
		if( array_key_exists($field, $this->_conditions) ) {
			if( ( is_array($this->_conditions[$field]) && in_array($val, $this->_conditions[$field]) ) || $this->_conditions[$field] === $val )
				return true;
			else
				return false;
		} else
			return true;
	}

}

/**
* This filter simply does nothing
*/
class NullAccessFilter implements AccessFilterInterface {

	protected $_where;

	public function __construct() {
		$this->_where = new Where();
	}

	public function getRead() {
		return $this->_where;
	}

	public function isAllowed($field, $val) {
		return true;
	}

}
