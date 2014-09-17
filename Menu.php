<?php

/**
* @file Menu.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Utility classes for generating and handling a menu
*/

namespace dophp;

/**
* Interface for every menu node
*/
interface MenuInterface {

	/**
	* Appends a child to this menu item
	*
	* @param $item object: The MenuItem instance
	*/
	public function append(MenuInterface $item);

	/**
	* Returns the item's label
	*/
	public function getLabel();

	/**
	* Returns the item's url
	*/
	public function getUrl();

	/**
	* Returns the item's childs
	*/
	public function getChilds();
	
	/**
	* Returns an array containing the current active path.
	*
	* @return array Array of MenuInterface instances. Null on failure
	*/
	public function getBreadcrumb();
}

/**
* Represents a Menu, as a collection of items.
*/
class Menu implements MenuInterface {

	/** The root MenuItem */
	protected $_root;

	/**
	* Constructs a menu from array
	*
	* @param $label string: The menu user-friendly label, null for separator
	* @param $url string: The url, if clickable
	* @param $items array: List of menu items, every item must be null to specify
	*                      a separator or an associative array. The following
	*                      keys are recognized:
	*                      <label>: The label for this menu item
	*                      <url>: The url for this menu item
	*                      <childs>: Array containing the childs for this menu,
	*                                defined as above
	*/
	public function __construct($label=null, $url=null, $items=null) {
		$this->_root = $this->_createChild(array('label'=>$label, 'url'=>$url));

		if( $items )
			foreach( $items as $i )
				$this->_root->append($this->_parseItem($i));
	}

	/**
	* Parses an item array definition and returns a MenuInterface instance
	*
	* @callgraph
	* @param $item array: Associative array defining child (<label>, <url>)
	* @return MenuInterface: The child instance
	*/
	protected function _parseItem($item) {
		if( $item === null )
			return $this->_createChild(array());
		if( ! is_array($item) )
			throw new \Exception('Unvalid item data');

		$el = $this->_createChild($item);

		if( isset($item['childs']) )
			foreach( $item['childs'] as $i )
				$el->append($this->_parseItem($i));

		return $el;
	}

	/**
	* Creates a child element from an array definition
	*
	* @param $item array: Associative array defining child (<label>, <url>)
	* @return MenuInterface: The child instance
	*/
	protected function _createChild($item) {
		$label = isset($item['label']) ? $item['label'] : null;
		$url = isset($item['url']) ? $item['url'] : null;
		return new MenuItem($label, $url);
	}

	public function append(MenuInterface $item) {
		return $this->_root->append($item);
	}

	public function getLabel() {
		return $this->_root->getLabel();
	}

	public function getUrl() {
		return $this->_root->getUrl();
	}

	public function getChilds() {
		return $this->_root->getChilds();
	}
	
	public function getBreadcrumb() {
		return $this->_root->getBreadcrumb();
	}

}

/**
* Represents a menu item
*/
class MenuItem implements MenuInterface {

	/** User-friendly label */
	protected $_label;
	/** Destination url */
	protected $_url;
	/** List of childs */
	protected $_childs = array();

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

	public function append(MenuInterface $item) {
		$this->_childs[] = $item;
	}

	public function getLabel() {
		return $this->_label;
	}

	public function getUrl() {
		return $this->_url;
	}

	public function getChilds() {
		return $this->_childs;
	}

	public function getBreadcrumb() {
		// First, check for first active child. If found, consider myself active too.
		$cbc = null;
		foreach( $this->_childs as $c )
			if( $cbc = $c->getBreadcrumb() )
				break;
		if( $cbc )
			return array_merge(array($this), $cbc);

		// Then check if my url matches query url
		if( $this->_url ) {
			$reqUrl = Utils::parseUrl($_SERVER['REQUEST_URI']);
			$myUrl = Utils::parseUrl($this->_url);
			if(
				( ! isset($myUrl['path']) || $myUrl['path'] == $reqUrl['path'] )
				&&
				(
					( ! isset($myUrl['query']) && ! isset($reqUrl['query']) )
					||
					( isset($myUrl['query']) && isset($reqUrl['query']) && ! array_diff($myUrl['query'], $reqUrl['query']) )
				)
			)
				return array($this);
		}

		// No luck
		return null;
	}

}
