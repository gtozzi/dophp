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

	/**
	* The name of the underlying database table, must be overridden.
	* Will be replaced with a real instance of the table by constructor.
	*/
	protected $_table = null;
	/**
	* Human-readable labels for columns, should be overriden in sub-class
	* defining an associative array in the format [ <column> => <label> ]
	* @see initLabels()
	*/
	protected $_labels = null;
	/**
	* Hunam-readable names for table items, singular and plural forms
	* (es. ['user', 'users']), should be overriden in sub-class
	* @see initNames()
	*/
	protected $_names = null;

	/**
	* Class constuctor
	*
	* @param $db object: Database instance
	*/
	public function __construct($db) {
		if( $this->_labels === null )
			$this->_labels = $this->initLabels();
		if( $this->_names === null )
			$this->_names = $this->initNames();
		$this->_table = new Table($db, $this->_table);
	}

	/**
	* Returns labels for this table, called when $this->_labels is not defined.
	* This way of loading labels allows the usage of gettext.
	*
	* @return array in $_labels valid format
	* @see $_labels
	*/
	protected function initLabels() {
		throw new Exception('Unimplemented');
	}

	/**
	* Returns names for this table's items, called when $this->_names is not
	* defined.
	* This way of loading labels allows the usage of gettext.
	*
	* @return array in $_names valid format
	* @see $_names
	*/
	protected function initNames() {
		throw new Exception('Unimplemented');
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
		return $this->_labels;
	}

	/**
	* Returns the table object instance
	*/
	public function getTable() {
		return $this->_table;
	}

}
