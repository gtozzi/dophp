<?php

/**
* @file Menu.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Utility classes for generating and handling a menu
*/

namespace dophp;

/**
* Represents a Menu, as a collection of items.
*/
class Menu extends MenuItem {

	/**
	* Constructs a menu from array
	*
	* @param $items array: List of menu items, every item must be null to specify
	*                      a separator or an associative array. The following
	*                      keys are recognized:
	*                      <label>: The label for this menu item
	*                      <url>: The url for this menu item
	*                      <childs>: Array containing the childs for this menu,
	*                                defined as above
	*/
	public function __construct($items=null) {
		if( ! $items ) {
			$this->_label = null;
			$this->_url = null;
			return;
		}

		foreach( $items as $i )
			$this->append($this->__parseItem($i));
	}

	private function __parseItem($item) {
		if( $item === null )
			return new MenuItem();
		if( ! is_array($item) )
			throw new \Exception('Unvalid item data');

		$label = isset($item['label']) ? $item['label'] : null;
		$url = isset($item['url']) ? $item['url'] : null;
		$el = new MenuItem($label, $url);

		if( isset($item['childs']) )
			foreach( $item['childs'] as $i )
				$el->append($this->__parseItem($i));

		return $el;
	}

}

/**
* Represents a menu item
*/
class MenuItem {

	/** User-friendly label */
	private $_label;
	/** Destination url */
	private $_url;
	/** List of childs */
	private $_childs = array();

	/**
	* Creates a new menu item
	*
	* @param $label string: The menu user-friendly label, null for separator
	* @param $url string: The url, if clickable
	*/
	public function __construct($label=null, $url=null) {
		$this->_label = $label;
		$this->_url = $url;
	}

	/**
	* Appends a child to this menu item
	*
	* @param $item object: The MenuItem instance
	*/
	public function append(MenuItem $item) {
		$this->_childs[] = $item;
	}

	public function getLabel() {
		return $this->_label;
	}

	public function getUrl() {
		return $this-_url;
	}

	public function getChilds() {
		return $this->_childs;
	}

}
