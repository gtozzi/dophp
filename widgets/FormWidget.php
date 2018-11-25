<?php declare(strict_types=1);

/**
 * @file FormWidget.php
 * @brief Generic classes and interfaces for form widgets
 * @author Gabriele Tozzi <gabriele@tozzi.eu>
 */


namespace dophp\widgets;


/**
 * Interface for a Widget that is part of a Form
 */
interface FormWidget extends Widget {

	/** Returns parent form object, may be null */
	public function getForm() /** TODO:PHP 7.2 :?Form */;

	/** Sets the parent form object */
	public function setForm(Form $form);

	/** Returns widgets's name */
	public function getName(): string;

	/** Returns widgets's namespace */
	public function getNamespace(): array;

	/** Sets widgets's namespace */
	public function setNamespace(array $namespace);

	/** Returns widgets's User-friendly name */
	public function getLabel(): string;
}


/**
 * Base class for a Widget that is part of a Form
 */
abstract class BaseFormWidget extends BaseWidget implements FormWidget {

	/** The parent form */
	protected $_form = null;
	/** The widgets's unique Name */
	protected $_name;
	/** The widgets's suggested label */
	protected $_label;
	/** The widgets's namespace */
	protected $_namespace = [];

	/**
	 * Constructs the object
	 *
	 * @param $name: string: The widgets's unique name
	 * @param $namespace: array: The widgets's namespace
	 * @param $label: string: The widgets's label. If not given, generates a default one
	 *                based on name.
	 */
	public function __construct(string $name, array $namespace = [], string $label=null) {
		parent::__construct();

		$this->_name = $name;
		$this->_namespace = $namespace;
		$this->_label = $label ?? str_replace('_', ' ', ucfirst($name));
	}

	public function getForm() /** TODO:PHP 7.2 :?Form */ {
		return $this->_form;
	}

	public function setForm(Form $form) {
		$this->_form = $form;
	}

	public function getName(): string {
		return $this->_name;
	}

	public function getNameSpace(): array {
		return $this->_namespace;
	}

	public function setNamespace(array $namespace) {
		$this->_namespace = $namespace;
	}

	public function getLabel(): string {
		return $this->_label;
	}
}
