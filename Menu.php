<?php

/**
* @file Menu.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Utility classes for generating and handling a menu
*/

namespace dophp;


require_once('Widgets.php');


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
	 * Returns the item's badges
	 */
	public function getBadges();

	/**
	* Returns an array containing the current active path.
	*
	* @param $url string: Url to get the breadcrumb for (use current URL if missing)
	* @return array Array of MenuInterface instances. Null on failure
	*/
	public function getBreadcrumb($url=null);

	/**
	* Checks if this menu item is active (based on url and alt), NOT recursvie:
	* does not account for active childrens
	*
	* @param $url string: Url to check active for (use current URL if missing)
	* @return boolean True if active, False otherwise
	*/
	public function isActive($url=null);
}

/**
* Interface for every menu badge
*/
interface BadgeInterface {

	/**
	* Returns the item's label
	*/
	public function getLabel();

	/**
	* Returns the item's url
	*/
	public function getUrl();

	/**
	* Returns the item's HTML class
	*/
	public function getClass();
}

/**
* Represents a Menu, as a collection of items.
*/
class Menu implements MenuInterface {

	/** The root MenuItem */
	protected $_root;
	/** The current URL */
	protected $_currentUrl;

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
		$this->_currentUrl = $_SERVER['REQUEST_URI'];
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
			throw new \InvalidArgumentException('Invalid item data');

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
		$alt = isset($item['alt']) ? $item['alt'] : null;
		return new MenuItem($label, $url, $alt);
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

	public function getBreadcrumb($url=null) {
		if( $url === null )
			$url = $this->_currentUrl;
		return $this->_root->getBreadcrumb($url);
	}

	public function isActive($url=null) {
		if( $url === null )
			$url = $this->_currentUrl;
		return $this->_root->isActive($url);
	}

	/**
	* Sets a different url to use for self::isActive() checks in place of
	* current script's URL
	*
	* @param $url string: A valid url
	*/
	public function setCurrentUrl($url) {
		$this->_currentUrl = $url;
	}

	/**
	* Returns the current URL
	*/
	public function getCurrentUrl() {
		return $this->_currentUrl;
	}

	public function getBadges() {
		return $this->_root->getBadges();
	}

}

/**
* Represents a menu item
*/
class MenuItem extends \dophp\widgets\BaseWidget implements MenuInterface {

	/** User-friendly label */
	protected $_label;
	/** Destination url */
	protected $_url;
	/** List of childs */
	protected $_childs = array();
	/** Alternative regex for determing when this element is active, to me tahced against full URL */
	protected $_alt;
	/** The name of the icon to be displayed */
	protected $_icon;
	/** List of badges */
	protected $_badges = [];

	/**
	* Creates a new menu item
	*
	* @param $label string: The menu user-friendly label, null for separator
	* @param $url string: The url, if clickable
	* @param $alt string: Regular expression matching alternative URLs for this element
	*                     (used in breadcrumb building)
	* @param $icon string: The optional icon name, if used
	*/
	public function __construct($label=null, $url=null, $alt=null, $icon=null) {
		parent::__construct();

		$this->_label = $label;
		$this->_url = $url;
		$this->_alt = $alt;
		$this->_icon = $icon;
	}

	public function append(MenuInterface $item) {
		$this->_childs[] = $item;
	}

	public function appendBadge(BadgeInterface $badge) {
		$this->_badges[] = $badge;
	}

	/**
	 * Deletes all badges
	 */
	public function clearBadges() {
		$this->_badges = [];
	}

	public function getLabel() {
		return $this->_label;
	}

	public function getUrl() {
		return $this->_url;
	}

	public function getIcon() {
		return $this->_icon;
	}

	public function getChilds() {
		return $this->_childs;
	}

	public function getBadges() {
		return $this->_badges;
	}

	public function getBreadcrumb($url=null) {
		if( $url === null )
			$url = $_SERVER['REQUEST_URI'];

		// First, check for first active child. If found, consider myself active too.
		$cbc = null;
		foreach( $this->_childs as $c )
			if( $cbc = $c->getBreadcrumb($url) )
				break;
		if( $cbc )
			return array_merge(array($this), $cbc);

		// Then check if im an active myself
		if( $this->isActive($url) )
			return array($this);

		// No luck
		return null;
	}

	public function isActive($url=null) {
		if( $url === null )
			$url = $_SERVER['REQUEST_URI'];

		if( ! ( $this->_url || $this->_alt ) )
			return false;

		if( $this->_alt )
			return preg_match($this->_alt, Url::fullUrl($url));

		if( $this->_url ) {
			$reqUrl = Url::parseUrl($url);
			$myUrl = Url::parseUrl($this->_url);
			if( $this->__recursiveArrayCompare($myUrl, $reqUrl) )
				return true;
		}

		return false;
	}

	/**
	 * Recursively compare all key/values in $search array against $base array
	 *
	 * For internal usage only.
	 */
	private function __recursiveArrayCompare($search, $base) {
		foreach( $search as $k => $v ) {
			if( ! array_key_exists($k, $base) )
				return false;

			$bv = $base[$k];

			if( is_array($v) ) {
				if( ! is_array($bv) )
					return false;

				if( ! $this->__recursiveArrayCompare($v, $bv) )
					return false;
			} else {
				if( is_array($bv) )
					return false;

				if( $bv != $v )
					return false;
			}
		}

		return true;
	}

}


/**
 * A simple badge
 */
class Badge implements BadgeInterface {

	/** User-friendly label */
	protected $_label;
	/** Destination url */
	protected $_url;
	/** The HTML class */
	protected $_class;

	/**
	* Creates a new menu item badge
	*
	* @param $label string: The user-friendly label
	* @param $class string: The HTML class, defaults to 'secondary'
	* @param $url string: The url, if clickable
	*/
	public function __construct($label, $class='secondary', $url=null) {
		$this->_label = $label;
		$this->_class = $class;
		$this->_url = $url;
	}

	public function getLabel() {
		return $this->_label;
	}

	public function getClass() {
		return $this->_class;
	}

	public function getUrl() {
		return $this->_url;
	}
}
