<?php declare(strict_types=1);

/**
 * @file Form.php
 * @brief Whole form widgets
 * @author Gabriele Tozzi <gabriele@tozzi.eu>
 */

namespace dophp\widgets;

require_once 'Fields.php';
require_once 'FieldGroups.php';


/**
 * Handles a form
 */
class Form extends BaseWidget {

	/**
	 * The defined fields array
	 * The field definition array, should be overridden in child.
	 * The key is the unique field name, must match DMBS name.
	 * Every field is definied as an array with the following keys:
	 * - type: The field type, eg. "text"
	 * - sections: The field section(s) (string or array, all if missing)
	 **/
	protected $_fields = [];

	/**
	 * The defined field groups array, key is the unique name
	 */
	protected $_fieldGroups = [];

	/** Name of the default field group */
	protected $_defaultFieldGroup = null;

	/**
	 * The form's namespace
	 */
	protected $_namespace;

	/**
	 * \dophp\Url The form's action
	 */
	protected $_action;

	/**
	 * Construct the form
	 *
	 * @param $fields array: initial fields definition array, parsed if needed
	 * @param $namespace string: The namespace prefix used for data, in HTML
	 *                           compatible format (es. "data" or "data[values]")
	 * @param $action: \dophp\Url: The form's action (defaults to empty url)
	 * @param $fieldGroups: array: initial groups definition array, parsed if needed
	 * @see self::parseFieldArray()
	 */
	public function __construct(array $fields = [], array $namespace = [],
			\dophp\Url $action = null, array $fieldGroups = []) {
		parent::__construct();

		$this->_action = $action===null ? new \dophp\Url() : $action;
		$this->_namespace = $namespace;
		$this->_initFieldGroupArray($fieldGroups);
		$this->_initFieldArray($fields);
	}

	/**
	 * Post data to the form after validating it
	 *
	 * @param $data: array The data to be processed, usually $_POST.
	 *               Every keys is expected to be string, or it will be converted
	 *               to string. Missing keys are assumed to be empty.
	 */
	public function setDisplayValues(array $data) {
		foreach( $this->_fields as $f ) {
			$fd = $data[$f->getName()] ?? null;
			$f->setDisplayValue($fd, $data);
		}
	}

	/**
	 * Sets data for the form. Data is not validated and assumed to be ok.
	 *
	 * @param $data array: The data to be set, keys are the field names.
	 * @param $nullMissing bool: If true (default), missing keys are assumed to be null.
	 */
	public function setInternalValues(array $data, bool $nullMissing=true) {
		foreach( $this->_fields as $f ) {
			if( ! $nullMissing && ! array_key_exists($f->getName(), $data) )
				continue;

			$fd = $data[$f->getName()] ?? null;
			$f->setInternalValue($fd);
		}
	}

	/**
	 * Tells whether all the fields in this form are valid
	 */
	public function isValid(): bool {
		foreach( $this->_fields as $field )
			if( ! $field->isValid() )
				return false;
		return true;
	}

	/**
	 * Return a list of not valid fields
	 */
	public function invalidFields(): array {
		$ret = [];
		foreach( $this->_fields as $name => $field )
			if( ! $field->isValid() )
				$ret[] = $field;
		return $ret;
	}

	/**
	 * Returns form's internal values
	 *
	 * @param $excludeRo bool: If true, will exclude readonly fields
	 * @return array: Associative array name => value of form's internal values
	 */
	public function getInternalValues(bool $excludeRo = false): array {
		$ret = [];
		foreach( $this->_fields as $name => $field )
			if( ! $excludeRo || ! $field->isReadOnly() )
				$ret[$name] = $field->getInternalValue();
		return $ret;
	}

	/**
	 * Adds a field
	 *
	 * @return Field: The just-added field
	 */
	public function addField(Field $field, string $groupName = null): Field {
		if( array_key_exists($field->getName(), $this->_fields) )
			throw new \UnexpectedValueException('Duplicate field ' . $field->getName());
		if( $field->getForm() )
			throw new \UnexpectedValueException('Field ' . $field->getName() . ' already has a form');
		if( $groupName && ! array_key_exists($groupName, $this->_fieldGroups) )
			throw new \UnexpectedValueException("Unknown group $groupName");

		$this->_fields[$field->getName()] = $field;
		$field->setForm($this);
		$field->setNamespace($this->_namespace);

		if( $groupName )
			$this->_fieldGroups[$groupName]->addField($field);

		return $field;
	}

	/**
	 * Instantiates a new field from given array definition and adds it to the form
	 *
	 * @param $name string: The field's unique name
	 * @param $def array: Field definition array
	 * @return Field: The just-added field
	 */
	public function addFieldFromArray(string $name, array $def): Field {
		if( isset($def['group']) ) {
			$group = $def['group'];
			unset($def['group']);
		} else
			$group = $this->_defaultFieldGroup;

		if( ! isset($def['type']) )
			throw new \UnexpectedValueException("Missing field type for field \"$name\"");

		$cn = ucfirst($def['type']);
		$cls = '\\widgets\\' . $cn . 'Field';
		if( ! class_exists($cls) )
			$cls = '\\dophp\\widgets\\' . $cn . 'Field';

		unset($def['type']);

		$field = new $cls($name, $this->_namespace, $def);
		return $this->addField($field, $group);
	}

	/**
	 * Adds a field group
	 */
	public function addFieldGroup(FieldGroup $fieldGroup) {
		if( array_key_exists($fieldGroup->getName(), $this->_fieldGroups) )
			throw new \UnexpectedValueException('Duplicate field group ' . $fieldGroup->getName());
		if( $fieldGroup->getForm() )
			throw new \UnexpectedValueException('Field group ' . $fieldGroup->getName() . ' already has a form');

		$this->_fieldGroups[$fieldGroup->getName()] = $fieldGroup;
		$fieldGroup->setForm($this);
	}

	/**
	 * Deletes a field group and all its fields
	 *
	 * @param $name string: The field group's unique name
	 * @param $missingOk: If true, do not throw when not found
	 */
	public function delFieldGroup(string $name, bool $missingOk=false) {
		if( ! array_key_exists($name, $this->_fieldGroups) )
			if( $missingOk )
				return;
			else
				throw new \UnexpectedValueException("Missing field group $name");

		$fg = $this->_fieldGroups[$name];
		foreach( $fg->fields() as $field )
			unset($this->_fields[$field->getName()]);
		unset($this->_fieldGroups[$name]);
	}

	/**
	 * Parses given field array structures and ensures all fields are converted
	 * into objects
	 *
	 * @see self::_fieldFromArray()
	 * @param $fields: array of fields, associative, in the form name => field
	 *                 every field must be a Field instance or an array
	 */
	protected function _initFieldArray(array $fields) {
		foreach( $fields as $name => $f ) {
			if( $f instanceof Field ) {
				$this->addField($f);
				continue;
			}

			if( ! is_array($f) )
				throw new \UnexpectedValueException("Invalid field \"$name\" definition");

			$this->addFieldFromArray($name, $f);
		}
	}

	/**
	 * Parses given field group array structures and ensures all fields groups are converted
	 * into objects
	 *
	 * @param $fieldsGroups: array of field groups, associative, in the form name => fieldGroup
	 *                 every field group must be a FieldGroup instance or an array
	 */
	protected function _initFieldGroupArray(array $fieldGroups) {
		foreach( $fieldGroups as $name => $fg ) {
			if( $fg instanceof FieldGroup ) {
				$this->addFieldGroup($fg);
				continue;
			}

			if( ! is_array($fg) )
				throw new \UnexpectedValueException("Invalid field group \"$name\" definition");

			if( isset($fg['default']) ) {
				$default = (bool)$fg['default'];
				unset($fg['default']);
			} else
				$default = false;

			$fg = new SimpleFieldGroup($name, $this->_namespace, $fg);
			$this->addFieldGroup($fg);
			if( $default )
				$this->_defaultFieldGroup = $name;
		}
	}

	/**
	 * Returns all fields
	 */
	public function fields(): array {
		return $this->_fields;
	}

	/**
	 * Returns all field groups
	 */
	public function fieldGroups(): array {
		return $this->_fieldGroups;
	}

	/**
	 * Tells whether a field with given name is known
	 *
	 * @param $name string: The field's name
	 * @return bool
	 */
	public function hasField(string $name): bool {
		return isset($this->_fields[$name]);
	}

	/**
	 * Returns a single field by name
	 *
	 * @param $name string: The field's name
	 * @return Field instance
	 */
	public function field(string $name): Field {
		if( ! isset($this->_fields[$name] ) )
			throw new \UnexpectedValueException("Field \"$name\" not found");
		return $this->_fields[$name];
	}

	/**
	 * Sets/unsets the whole form as read-only
	 *
	 * @param $status boool: The new readonly status
	 */
	public function setReadOnly(bool $status) {
		foreach( $this->_fields as $f )
			$f->setReadOnly($status);
	}

	/**
	 * Get/Set internal action object, can be modified
	 *
	 * @param $action \dophp\Url: When given, sets a new action
	 * @return \dophp\Url: The current action
	 */
	public function action(\dophp\Url $action = null): \dophp\Url {
		if( $action !== null )
			$this->_action = $action;

		return $this->_action;
	}

	/**
	 * Returns an array representing the current form status
	 *
	 * @see self::restore()
	 * @return array Internal format, intended to be used with self::restore() only
	 */
	public function dump(): array {
		$ret = [
			'_id' => $this->_id,
			'fields' => [],
		];
		foreach( $this->_fields as $name => $field )
			$ret['fields'][$name] = $field->dump();
		return $ret;
	}

	/**
	 * Restores a previously dumped form status
	 *
	 * This is meant to be fault-tolerant (i.e. ignoring extra fields) since Form
	 * may have been slightly modified between dump/restore (i.e. by software upgrades)
	 *
	 * @see self::dump()
	 */
	public function restore(array $dump) {
		if( isset($dump['_id']) )
			$this->_id = $dump['_id'];

		if( isset($dump['fields']) && is_array($dump['fields']) )
			foreach( $this->_fields as $name => $field )
				if( array_key_exists($name, $dump) )
					$field->restore($dump[$name]);
	}
}
