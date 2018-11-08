<?php declare(strict_types=1);


namespace dophp\widgets;


/**
 * Base interface for an UI widget
 */
interface Widget {

	/** Returns element's unique ID, must be HTML's ID safe */
	public function getId(): string;

	/**
	 * Returns this object's "class" unique identifier, to be used as key for
	 * storing/retrieving data, must be HTML's ID safe
	 */
	public function getClsId(): string;
}


/**
 * Base class for a generic UI widget
 */
abstract class BaseWidget implements Widget {

	/** This element's unique id, generated at init time */
	protected $_id;

	/** This element's class id, generated at init time */
	private $__clsId;

	/**
	 * Base constructor, generates the unique id
	 */
	public function __construct() {
		$this->__clsId = str_replace('\\','_',get_class($this));
		$prefix = strtolower($this->__clsId) . '_';
		$this->_id = uniqid($prefix);
	}

	public function getId(): string {
		return $this->_id;
	}

	public function getClsId(): string {
		return $this->__clsId;
	}
}


require_once('widgets/DataTable.php');
require_once('widgets/Form.php');
require_once('widgets/FieldGroups.php');
require_once('widgets/Fields.php');
