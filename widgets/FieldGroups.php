<?php declare(strict_types=1);

/**
 * @file FieldGroups.php
 * @brief Form-field-group widgets
 * @author Gabriele Tozzi <gabriele@tozzi.eu>
 */

namespace dophp\widgets;

require_once 'FormWidget.php';
require_once 'Fields.php';


/**
 * Interface for a Field Group: a collection of fields, more aestethic than functional
 */
interface FieldGroup extends FormWidget {
}


/**
 * Base class for a Field Group
 */
abstract class BaseFieldGroup extends BaseFormWidget implements FieldGroup {

	/** The fields that are part of this group */
	protected $_fields = [];

	public function addField(Field $field) {
		if( array_key_exists($field->getName(), $this->_fields) )
			throw new \Exception('Field ' . $field->getName() . ' is already in group ' . $this->getName());
		if( $field->getForm() !== $this->getForm() )
			throw new \Exception('Field ' . $field->getName() . ' is not in same form as group ' . $this->getName());
		if( $field->getGroup() )
			throw new \Exception('Field ' . $field->getName() . ' already has a group');

		$this->_fields[$field->getName()] = $field;
		$field->setGroup($this);
	}

	/**
	 * Returns all fields for this group
	 */
	public function fields(): array {
		return $this->_fields;
	}
}


/**
 * Simple field group
 */
class SimpleFieldGroup extends BaseFieldGroup {

	/**
	 * Constructs the field group
	 *
	 * @param $name string: The field groups's name
	 * @param $namespace array: The field groups's namespace
	 * @param $opt array: Optional attributes to set (label)
	 */
	public function __construct(string $name, array $namespace = [], array $opts = []) {
		$label = isset($opts['label']) ? $opts['label'] : null;
		parent::__construct($name, $namespace, $label);
	}

}
