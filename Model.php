<?php

/**
* @file Model.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Class used to represent a DoPhp Model and related classes
* @deprecated Classes in this file where work in progress, some functions are
*          incomplete and backward compatibility could be broken without notice
*          in future versions. It will not be completed anytime soon and may even
*          be removed.
*          The new backend, form and field classes are replacing it.
*/

namespace dophp;

/**
* Represents a DoPhp Model.
* A model extends a database table handling the conversion of the data from
* machine to human-friendly format. It takes care about labels, number and date
* formatting, defining "virtual" columns, etc...
*
* @deprecated See the new backend, form and field classes
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
	* defining an associative array or arrays in the format accepted by
	* FieldDefinition::__construct()
	*
	* Array keys are used as \<name\> attribute when not numeric if a name has not
	* already been provided inside the field definition. Numeric keys are ignored.
	*
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
	* associative array of field => \<value(s)\>. If value is an array, multiple
	* values are allowed with and OR condition.
	* initFilter() also supports advanced filters.
	* @see initFilter()
	* @see SimpleAccessFilter::__construct()
	*/
	protected $_filter = null;
	/**
	* The default order to apply when selecting (if any)
	*/
	protected $_order = null;

	/**
	* Used internally for caching summary cols
	*/
	private $__sumColsCache = [];
	/**
	* Used internally for caching relations
	*/
	private $__relsCache = [];

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
		if( $this->_order === null )
			$this->_order = $this->initOrder();
		$this->_table = new Table($this->_db, $this->_table);

		// Build and validate the filter
		if( ! $this->_filter )
			$this->_filter = new NullAccessFilter();
		elseif( gettype($this->_filter) == 'object' ) {
			if( ! $this->_filter instanceof AccessFilterInterface )
				throw new \InvalidArgumentException('Invalid filter class');
		} elseif( ! is_array($this->_filter) )
			throw new \UnexpectedValueException('Invalid filter format');
		else
			$this->_filter = new SimpleAccessFilter($this->_filter);

		// Clean and validate the fields array
		if( ! $this->_fields || ! is_array($this->_fields) )
			throw new \InvalidArgumentException('Invalid fields');
		foreach( $this->_fields as $f => & $d )
			if( ! $d instanceof FieldDefinition )
				if( is_array($d) ) {
					if( ! isset($d['name']) && ! is_int($f) )
						$d['name'] = $f;
					$d = new FieldDefinition($d);
				} else {
					throw new \InvalidArgumentException('Every field must be array or FieldDefinition');
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
		throw new \dophp\NotImplementedException('Unimplemented');
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
		throw new \dophp\NotImplementedException('Unimplemented');
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
	* Returns default order to apply when selecting, called when $this->_order
	* is not defined.
	* Allows order definition at runtime
	*
	* @return array in $_order valid order
	* @see $_order
	*/
	protected function initOrder() {
		return array();
	}

	/**
	* Returns singular and plural names for the elements of this model
	*
	* @return array [\<singular\>, \<plural\>]
	*/
	public function getNames() {
		return $this->_names;
	}

	/**
	* Returns all column labels
	*
	* @return array Associative array of column [ \<name\> =\> \<label\> ]
	*/
	public function getLabels() {
		$labels = array();
		foreach( $this->_fields as $k => $f )
			if( $f->label )
				$labels[$k] = $f->label;
		return $labels;
	}

	/**
	* Returns validation rules
	*
	* @param $mode   string: The action to get the rules for (insert or update).
	*                If unvalid or null acton is given, only rules common to all
	*                actions are returned
	* @return array Associative array [ \<name\> => [\<type\>, [\<rules\>]] ]
	*/
	public function getRules($mode=null) {
		$rules = array();
		foreach( $this->_fields as $k => $f )
			if( isset($f->dtype) ) {

				if( $mode=='edit' && ! $f->edit )
					continue; // Skip rules for non-editable fields on edit mode

				if( $f->i18n ) {
					// Copy main rule to all childs
					$sub = array();
					foreach( \DoPhp::lang()->getSupportedLanguages() as $l )
						$sub[$l] = array($f->dtype, $f->dopts);
					$rules[$k] = array('array', array('rules'=>$sub));
					continue;
				}

				$rules[$k] = array($f->dtype, $f->dopts);
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
	* @return array Associative array of column [ \<name\> => \<label\> ] or
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
				if( isset($f->value) )
					$post[$k] = $f->value;

				// Remove non editable fields on edit mode
				if( $mode=='edit' && ! $f->edit && array_key_exists($k, $post) )
					unset($post[$k]);
			}

			// Data has been submitted
			list($data,$errors) = $this->validate($post, $files, $mode, $pk);

			$related = array();
			if( ! $errors ) {
				if( ! $this->isAllowed($data, true) )
					throw new \RuntimeException('Saving forbidden data');

				foreach( $this->_fields as $k => $f ) {

					// Do not update empty password and file fields
					if( $mode == 'edit' && in_array($f->rtype,array('password','file')) && array_key_exists($k,$data) && ! $data[$k] )
						unset($data[$k]);

					// Runs postprocessors
					if( isset($f->postp) && array_key_exists($k,$data) ) {
						$postp = $f->postp;
						$data[$k] = $postp($data[$k]);
					}

					// Save files
					if( $f->rtype == 'file' && isset($data[$k]) )
						$data[$k] = $this->_saveFile($name, $data[$k]);

					// Handle multi fields
					if( $f->dtype == 'array' )
						if( ! $f->nmtab )
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
						if( isset($this->_fields[$k]) && $this->_fields[$k]->i18n ) {
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
						if( $this->_fields[$k]->i18n ) {
							// Insert text into text table and replace l18n field
							// with its text ID
							$txtid = \DoPhp::lang()->newText($v);
							$v = $txtid;
						}
					unset($v);
					$pk = $this->_table->insert($data);
				} else
					throw new \LogicException('This should never happen');

				// Update related data, if needed
				foreach( $related as $k => $v ) {
					if( $v === null )
						continue;

					$rinfo = $this->__analyzeRelation($this->_fields[$k]);

					// Normalize $v syntax: $v is now an array of associative arrays: $pk => [ extra fields ]
					// Convert fields using the old syntax (array of pks) to the new one
					$new = array();
					foreach( $v as $kk => $vv )
						if( ! is_array($vv) )
							$new[$vv] = array();
						else
							$new[$kk] = $vv;
					$v = $new;

					// Delete unwanted relations and update all relations already present
					// that could have different data
					foreach( $rinfo['nm']->select(array($rinfo['ncol'] => $pk), true) as $x => $r ) {
						if( ! $v || ! array_key_exists($r[$rinfo['mcol']], $v) )
							$rinfo['nm']->delete($r);
						elseif( array_key_exists($r[$rinfo['mcol']], $v) && is_array($v[$r[$rinfo['mcol']]]) ) {
							if( count($v[$r[$rinfo['mcol']]]) ) {
								$pkdata = array($rinfo['ncol'] => $pk, $rinfo['mcol'] => $r[$rinfo['mcol']]);
								$rinfo['nm']->update($pkdata, $v[$r[$rinfo['mcol']]]);
							}
						}
					}

					// Insert missing relations
					if( $v )
						foreach( $v as $kk => $vv ) {
							$pkdata = array($rinfo['ncol'] => $pk, $rinfo['mcol'] => $kk);

							$existing = false;
							foreach( $rinfo['nm']->select($pkdata,true,true) as $r ) {
								$existing = true;
								break;
							}
							if( ! $existing )
								$rinfo['nm']->insert(array_merge($pkdata, $vv));
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
				if( $f->dtype == 'array' )
					if( ! $f->nmtab )
						$record[$n] = unserialize($record[$n]);
					else { // Read data from relation
						$rinfo = $this->__analyzeRelation($f);
						$record[$n] = array();
						foreach( $rinfo['nm']->select(array($rinfo['ncol']=>$pk), array($rinfo['mcol'])) as $r )
							$record[$n][] = $r[$rinfo['mcol']];
					}

			if( ! $this->isAllowed($record, true) )
				throw new \RuntimeException('Loading forbidden data');
		}

		// Build fields array
		$fields = array();
		foreach( $this->_fields as $k => $f ) {
			if( ! $f->rtype )
				continue;

			if( $mode=='edit' && ! $f->edit ) // Don't render non editable fields in edit form
				continue;

			if( $f->i18n ) {
				// Explode l18n field
				foreach( \DoPhp::lang()->getSupportedLanguages() as $l ) {
					$fl = $f;
					$fl->label = $this->__buildLangLabel($fl->label, $l);
					$val = $data&&isset($data[$k][$l]) ? $data[$k][$l] : (isset($record)?\DoPhp::lang()->getText($record[$k],$l):$f->getDefault());
					$err = $errors&&isset($errors[$k][$l]) ? $errors[$k][$l] : null;
					$fields["{$k}[{$l}]"] = $this->__buildField($k, $fl, $val, $err);
				}
			} else {
				$val = $data&&isset($data[$k]) ? $data[$k] : (isset($record)?$record[$k]:$f->getDefault());
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
			throw new \UnexpectedValueException('Unvalid or missing pk');

		list($data, $count) = $this->__readData('view', new Where($this->_table->parsePkArgs($pk)));

		if( ! $data )
			throw new \RuntimeException('Loading forbidden data');
		if( count($data) != 1 )
			throw new \RuntimeException('Received too many data rows: ' . count($data) . '/' . $count);

		return array_shift($data);
	}

	/**
	* Returns the data for rendering a summary table
	*
	* @todo Will supporto filtering, ordering, etc...
	* @return Array of data: associative array of data as \<pk\> => \<Field\>
	*                  count: total number of records found
	*                  heads: column headers
	*/
	public function table() {
		return $this->__readData('admin');
	}

	/**
	* Returns the data for rendering a summary table or a view page
	*
	* @todo Support filtering, ordering, etc... (datatables server-side)
	* @param $action string: The action name (admin|view)
	* @param $pk Where instance: The PK to use for view action
	* @return Array of data: associative array of data as \<pk\> => \<Field\>
	*                  count: total number of records found
	*                  heads: column headers
	*/
	private function __readData($action, Where $pk=null) {
		if( $action != 'admin' && $action != 'view' )
			throw new \UnexpectedValueException("Invalid action $action");
		if( $action == 'view' && ! $pk )
			throw new \UnexpectedValueException("Must provide a PK for view action");

		// Init variables
		$cols = array();
		$refs = array();
		$joins = array();
		$labels = array();
		$allLabels = $this->getLabels();

		// Add main columns from field definitions
		foreach( $this->_fields as $k => $f )
			if( ( ($action=='admin' && $f->rtab) || ($action=='view' && $f->rview) ) ) {
				if( $f->name && ! $f->nmtab )
					$cols[] = $k;
				$labels[$k] = $allLabels[$k];
				if( isset($f->ropts['refer']) && ! $f->nmtab ) {
					$refmod = \DoPhp::model($f->ropts['refer']['model']);
					if( ! in_array($refmod, $refs) )
						$refs[] = $refmod;
				}
			}

		// Add component columns from field definitions, if missing
		foreach( $this->_fields as $f )
			if( isset($f->ropts['comp']) )
				foreach( $f->ropts['comp'] as $col )
					if( ! in_array($col, $cols) )
						$cols[] = $col;

		// Add PK columns, if missing (required to itenfity the row)
		foreach( $this->_table->getPk() as $c )
			if( ! in_array($c, $cols) )
				$cols[] = $c;

		// Build joins
		foreach( $refs as $r )
			$joins[] = new Join($r->getTable(), $r->summaryCols());

		// Prepare filter
		if( $action == 'view' ) {
			$filter = $pk;
			$filter->add($this->_filter->getRead());
		} else
			$filter = $this->_filter->getRead();

		// Run the query and process data
		$data = array();
		foreach( $this->_table->select($filter, $cols, null, $joins, $this->_order) as $x => $res ) {
			$row = array();
			foreach( $this->_fields as $k => $f )
				if( ( ($action=='admin' && $f->rtab) || ($action=='view' && $f->rview) ) )
					$row[$k] = isset($f->ropts['func']) ? new RenderedField($res,$f) : new Field($res[$k],$f,$res);

			$data[$this->formatPk($res)] = $row;
		}
		$count = $this->_db->foundRows();

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
			throw new \UnexpectedValueException('Unvalid or missing pk');

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
	* Builds related data for the given field as an associative array of key => FormFieldData
	*
	* @param $name string: The name of the field
	* @param $q string: The search query string to filter (null = don't filter)
	* @param $pks mixed: If given, only return the given PK or PKs if array
	*
	* @return array: associative array of key => FormFieldData
	*/
	public function fieldData($name, $q=null, $pks=null) {
		if( $pks !== null && ! is_array($pks) )
			$pks = array($pks);
		$data = null;
		$f = & $this->_fields[$name];

		if( $f->rtype == 'select' || $f->rtype == 'multi' || $f->rtype == 'auto' ) {
			// Retrieve data
			$groups = array();
			if( array_key_exists('data',$f->ropts) )
				$data = $f->ropts['data'];
			else {
				if( ! isset($f->ropts['refer']) )
					throw New \UnexpectedValueException("Need refer or data for $k field");
				$rmodel = \DoPhp::model($f->ropts['refer']['model']);
				$data = $rmodel->summary($f->ropts['refer']['filter'], $f->ropts['refer']['summary']);
				if( isset($f->ropts['group']) )
					foreach( $data as $pk => $v )
						$groups[$pk] = $rmodel->read($pk)[$f->ropts['group']]->format();
			}

			// Filter data, apply query and PKs
			if( isset($this->_filter) || ($q !== null && $q !== '') || $pks )
				foreach( $data as $pk => $v )
					if(
						( isset($this->_filter) && ! $this->_filter->isAllowed($pk, $v, false) )
						||
						( $q !== null && $q !== '' && strpos(strtolower($v), $q) === false )
						||
						( $pks && ! in_array($pk, $pks) )
					)
						unset($data[$pk]);

			// Assemble data
			foreach( $data as $k => & $v )
				$v = new FormFieldData($k, $v, isset($groups[$k])?$groups[$k]:null);
			unset($v);
		}

		return $data;
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
		if( ! ($f->ropts && isset($f->ropts['ajax']) && $f->ropts['ajax']) )
			$data = $this->fieldData($k);
		else
			$data = $this->fieldData($k, null, $value);

		if( $f->rtype == 'password' ) // Do not show password
			$value = null;

		return new FormField($value, $f, $error, $data);
	}

	/**
	* Analyzes a relation, internal function
	*
	* @return array: Associative array with informations about the relation:
	*                'ropts' => [ 'refer' => The reference definition, associative array ]
	*                'mn'    => The n:m Table instance
	*                'ncol'  => Name of the column referring to my table in NM table's PK
	*                'mcol'  => Name of the column referring to referred in NM table's PK
	*/
	private function __analyzeRelation($field) {
		if( ! isset($field->ropts['refer']) )
			throw new \UnexpectedValueException('Can\'t analize an unspecified relation');

		// Use caching to avoid multiple long queries
		if( isset($this->__relsCache[$field->name]) )
			return $this->__relsCache[$field->name];

		// If cache is not available, do the full analysis
		$refer = \DoPhp::model($field->ropts['refer']['model']);
		$nm = new Table($this->_db, $field->nmtab);
		$npk = $this->_table->getPk();
		if( count($npk) != 1 )
			throw new \dophp\NotImplementedException('Unsupported composed or missing PK');
		$npk = $npk[0];
		$mpk = $refer->getTable()->getPk();
		if( count($mpk) != 1 )
			throw new \dophp\NotImplementedException('Unsupported composed or missing PK');
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
			throw new \RuntimeException('Couldn\'t find relations on n:m table ' . $nm->getName() . ' referred by ' . $refer->getTable()->getName());
		$nmpk = $nm->getPk();
		if( count($nmpk) < 2 )
			throw new \RuntimeException('m:m table must have a composite PK');
		elseif( count($nmpk) != 2 )
			throw new \dophp\NotImplementedException('Unsupported PK in n:m table');

		if( array_search($ncol, $nmpk) === false || array_search($mcol, $nmpk) === false )
			throw new \RuntimeException('Couldn\'t find columns in relation');

		$this->__relsCache[$field->name] = array(
			'refer' => $refer,
			'nm' => $nm,
			'ncol' => $ncol,
			'mcol' => $mcol,
		);
		return $this->__relsCache[$field->name];
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
				throw new \UnexpectedValueException("PK Column $k is not part of row");

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
	* @see \dophp\Validator
	*/
	public function validate(&$post, &$files, $mode=null, $pk=null) {
		$val = new Validator($post, $files, $this->getRules($mode));
		return $val->validate();
	}

	/**
	* Returns a short representation of model content, to be used in a select box
	* By default, only selects first non-hidden field
	*
	* @param $filter string: The filter condition as Where instance or the PK
	* @param $col string: The name of the column to use as display field
	* @return array: Associative array [ \<pk\> =\> \<description\> ], or just \<description\>
	*                if PK is given
	*/
	public function summary($filter=null, $col=null) {
		if( $col ) {
			$cols = $this->_table->getPk();
			$cols[] = $col;
		} else
			$cols = $this->summaryCols();

		$pars = new Where();
		$pars->add($this->_filter->getRead());
		if( $filter instanceof Where ) {
			$pars->add($filter);
			$pk = false;
		} elseif( $filter === null ) {
			$pk = false;
		} else {
			$pars->add($this->_table->parsePkWhere($pk));
			$pk = true;
		}

		$ret = array();
		foreach( $this->_table->select($pars, $cols, null, null, $this->_order) as $r )
			$ret[$this->formatPk($r)] = $this->summaryRow($r, false, $col);

		if( $pk ) {
			if( count($ret) > 1 )
				throw new \RuntimeException('More than one row returned when filtering by PK');
			return array_shift($ret);
		}
		return $ret;
	}

	/**
	* Returns a string resume of a given data row
	*
	* @param $row array: The row
	* @param $prefix bool: If true, expect column name in the format \<table\>.\<column\>
	* @param $prefix string: Prefix prepended to column names in data
	* @param $summary string: If given, use this column name as summary column
	*/
	public function summaryRow(& $row, $prefix=false, $summary=null) {
		if( $summary ) {
			$displayCol = $summary;
		} else {
			$sc = $this->summaryCols();
			$displayCol = end($sc);
		}

		if( $prefix )
			$prefix = $this->_table->getName() . '.';
		else
			$prefix = '';
		$f = new Field($row["$prefix$displayCol"], $this->_fields[$displayCol]);

		return $f->format();
	}

	/**
	* Returns list of columns needed to build a summary, last one is the
	* column displayed
	*
	* @return array: List of column names
	*/
	public function summaryCols() {
		// Use caching
		if( ! $this->__sumColsCache ) {
			$pks = $this->_table->getPk();

			$displayCol = null;
			$intCol = null;
			foreach( $this->_fields as $n => $f )
				if( ! in_array($n,$pks) && $f->rtype )
					if( $f->i18n || $this->_table->getColumnType($n) == 'string' ) {
						$displayCol = $n;
						break;
					} elseif( ! $intCol )
						$intCol = $n;
			if( ! $displayCol )
				if( $intCol )
					$displayCol = $intCol;
				else
					$displayCol = $pks[0];

			$this->__sumColsCache = $pks;
			$this->__sumColsCache[] = $displayCol;
		}

		return $this->__sumColsCache;
	}

	/**
	* Saves a file, should override
	*
	* @param $name string: the field name
	* @param $data array: File data as returned in $_FILES (\<name\>, \<type\>, \<tmp_name\>, \<error\>, \<size\>)
	* @return Mixed: the value to store in the database
	*/
	protected function _saveFile($name, $data) {
		throw new \dophp\NotImplementedException('saveFile not implemented');
	}

	/**
	* Checks if a given record is allowed based on current filter
	*
	* @param $record array: a query result or post record
	* @param $write boolean: if true, use is trying write access, read if false
	* @return boolean: True when allowed
	*/
	protected function isAllowed($record, $write) {
		if( ! $this->_filter )
			return true;

		foreach( $record as $c => $v )
			if( ! $this->_filter->isAllowed($c, $v, $write) )
				return false;

		return true;
	}

	/**
	* Runs custom actions before an item has to be inserted
	* does nothing by default, may be overridden
	* Database operations run inside a transaction
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
* Defines the characteristics for a field
*
* @deprecated See the new backend, form and field classes
*/
class FieldDefinition {

	/**
	* string: The database column name for this field.
	*         Must be omitted only for composed read-only fields
	*/
	public $name = null;

	/** string: The label to display for this field */
	public $label = null;

	/** string: Long description */
	public $descr = null;

	/**
	* string: The field renderring type in insert/edit mode:
	*         - \<null\> [or missing]: The field is not rendered at all
	*         - label: The field is rendered as a label
	*         - select: The field is rendered as a select box
	*         - multi: The field is rendered as multiple select box
	*         - check: The field is rendered as a checkbox
	*         - auto: The field is renderes as a suggest (autocomplete)
	*         - text: The field is rendered as a text box
	*/
	public $rtype = null;

	/**
	* mixed: If false this field is not rendered in table view.
	* Defaults to true.
	*/
	public $rtab = true;

	/**
	* mixed: If false, this field is not rendered in view mode
	* Defaults to true.
	*/
	public $rview = true;

	/**
	* string: The data type, used for validation, see Validator::__construct
	*         If null or missing, the field is automatically set
	*         from database and omitted in insert/update queries
	*         If array, data will be serialized prior of being written
	*         unless 'nmtab' is specified
	*/
	public $dtype = null;

	/**
	* array: Data Validation options, see Validator::__construct
	*        If null or missing, defaults to an empty array
	*        The "required" key can be a string specifying a
	*        single action on which the field if required (insert/update)
	*/
	public $dopts = [];

	/**
	* array: Data rendering options, associative array.
	*        'refer' => mixed:  Name of the referenced model class, if applicable
	*                           or associative array defining an extended reference.
	*                           Keys:
	*                           'model' => Name of the referenced model class. Mandatory.
	*                           'summary' => Name of the field to use when printing
	*                                        referenced data summary
	*                           'filter' => A Where instance for filtering
	*        'data' => array:  Associative array of data for a select box, if
	*                          applicable. Overrides 'refer'.
	*        'func' => array: Provides a custom rendered. The full row is the only
	*                         argument passed (byRef) and a string must be returned.
	*                         Ex: function( & row ) { return $row['col1'] . $row['col2'] }
	*                         Overrides 'refer' and 'data'
	*        'group' => string: Name of the field in the referenced model
	*                           to use for grouping elements
	*        'comp' => array: List of components column names when 'func' is specified.
	*                         This columns will be included in the query.
	*        'ajax' => bool: If true, use select2 ajax functionalities to load data
	*        'step' => float: HTML "step" attribute for type="number" rendering
	*/
	public $ropts = [];

	/**
	* func: Post-processor: parses the data before saving it, if applicable
	*/
	public $postp = null;

	/**
	* mixed: If given, this will be the default value suggested on insert
	*        it can also be a lambda function that will be called with no arguments
	*/
	public $default = null;

	/**
	* mixed: If given, this field will always be set to this static value
	*/
	public $value = null;

	/**
	* bool: If true, this field is "multiplied" for every supported language.
	* Default: false
	*/
	public $i18n = false;

	/**
	* bool: If false, this field is not altered in edit mode.
	* Defaults to true.
	*/
	public $edit = true;

	/**
	* string: The name of the N:M relation tab for an array field
	*/
	public $nmtab = null;

	/**
	* Set the properties from an associative array. See property descriptions
	* for list of available keys
	*/
	public function __construct($array) {
		foreach( $array as $k => $v ) {
			if( ! property_exists($this, $k) )
				throw new \UnexpectedValueException("Unknown property $k");
			$this->$k = $v;
		}

		// Normalize refer options
		if( isset($this->ropts['refer']) ) {
			if( ! is_array($this->ropts['refer']) )
				$this->ropts['refer'] = ['model' => $this->ropts['refer']];
			if( ! isset($this->ropts['refer']['summary']) )
				$this->ropts['refer']['summary'] = null;
			if( ! isset($this->ropts['refer']['filter']) )
				$this->ropts['refer']['filter'] = null;
			if( ! isset($this->ropts['refer']['ajax']) )
				$this->ropts['refer']['ajax'] = false;
		}

		// Perform some sanity checks
		if( $this->name === null && $this->rtype !== null )
			throw new \UnexpectedValueException('Fields without a name cannot be rendered');
		if( ($this->rtype=='select' || $this->rtype=='auto') && ! (isset($this->ropts['refer']['model']) || array_key_exists('data',$this->ropts)) )
			throw new \UnexpectedValueException('Missing referred model or data for select or auto field');
		if( array_key_exists('data',$this->ropts) && ! is_array($this->ropts['data']) )
			throw new \UnexpectedValueException('Unvalid referred data');
		if( array_key_exists('data',$this->ropts) && array_key_exists('refer',$this->ropts) )
			throw new \UnexpectedValueException('"refer" and "data" options are mutually exclusive');
	}

	/**
	* Returns default value on insert
	*/
	public function getDefault() {
		$def = & $this->default;
		if( is_callable($def) )
			return $def();
		return $def;
	}

}


/**
* Represents a data field, carrying a raw value
*
* @deprecated See the new backend, form and field classes
*/
class Field {

	/** The raw value, ready to be written into DB */
	protected $_value;
	/** The field definition */
	protected $_def;
	/** Format cache */
	protected $_format = null;

	/**
	* Creates the field
	*
	* @param $value mixed: The raw value
	* @param $def FieldDefinition: The field definition
	* @param $data array: Optional related fields data, speed up rendering of
	*                     related fields
	*/
	public function __construct($value, FieldDefinition $def, $data=null) {
		$this->_value = $value;
		$this->_def = $def;

		// Populate format cache now using related data and drop $data to save memory
		if( isset($this->_def->ropts['refer']) && $data )
			$this->_format = \DoPhp::model($this->_def->ropts['refer']['model'])->summaryRow($data, true, $this->_def->ropts['refer']['summary']);
	}

	/**
	* Returns the raw value for this field
	*/
	public function value() {
		return $this->_value;
	}

	/**
	* Returns the value for this field, formatted for the html input "value" attribute
	*
	* @return string
	*/
	public function formValue() {
		Lang::pushLocale(LC_NUMERIC);
		$val = (string)$this;
		Lang::popLocale(LC_NUMERIC);
		return $val;
	}

	/**
	* Formats the raw value into human-readable data
	*
	* @return string: the formatted value
	*/
	public function format() {
		// Use the cache
		if( isset($this->_format) )
			return $this->_format;

		// Runtime formatting
		$val = Utils::format($this->_value);

		// Handle i18n and relations
		if( $this->_def->i18n )
			$val = $this->__reprLangLabel($val);
		elseif( isset($this->_def->ropts['refer']) )
			$val = \DoPhp::model($this->_def->ropts['refer']['model'])->summary($val, $this->_def->ropts['refer']['summary']);
		elseif( isset($this->_def->ropts['data']) )
			$val = $this->_def->ropts['data'][$val];

		$this->_format = $val;
		return $this->_format;
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

	public function def() {
		return $this->_def;
	}
	public function label() {
		return $this->_def->label;
	}
	public function type() {
		return $this->_def->rtype;
	}
	public function descr() {
		return $this->_def->descr;
	}
	public function name() {
		return $this->_def->name;
	}
	public function ajax() {
		return $this->_def->ropts && $this->_def->ropts['ajax'];
	}
	public function step() {
		return isset($this->_def->ropts['step']) ? $this->_def->ropts['step'] : null;
	}

}


/**
* Represents a rendered field
*
* @deprecated See the new backend, form and field classes
*/
class RenderedField extends Field {

	/**
	* Creates the field
	*
	* @param $row string: The raw row
	* @param $def FieldDefinition: The field definition
	*/
	public function __construct(& $row, FieldDefinition $def) {
		if( ! isset($def->ropts['func']) || ! is_callable($def->ropts['func']) )
			throw new \UnexpectedValueException('RenderedField must have a callable "func" option');

		$func = & $def->ropts['func'];
		parent::__construct($func($row), $def);
	}

}


/**
* Represents a form field
*
* @deprecated See the new backend, form and field classes
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
	* @param $def FieldDefinition: The field definition (see Field)
	* @param $error string: The error message
	* @param $data array: The related data, array of FormFieldData objects
	*/
	public function __construct($value, FieldDefinition $def, $error=null, $data=null) {
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
*
* @deprecated See the new backend, form and field classes
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
*
* @deprecated See the new backend, form and field classes
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
	* @param $write bool: if true, user is requetsing write access
	*/
	public function isAllowed($field, $val, $write);

}

/**
* Simple basic access filter implementation
*
* @deprecated See the new backend, form and field classes
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

	public function isAllowed($field, $val, $write) {
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
*
* @deprecated See the new backend, form and field classes
*/
class NullAccessFilter implements AccessFilterInterface {

	protected $_where;

	public function __construct() {
		$this->_where = new Where();
	}

	public function getRead() {
		return $this->_where;
	}

	public function isAllowed($field, $val, $write) {
		return true;
	}

}
