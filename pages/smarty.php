<?php

/**
* @file smarty.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @see Page.php
* @brief Smarty Page handling stuff
*/

namespace dophp;

require_once(__DIR__ . '/../Page.php');


/**
 * Trait for adding Smarty functionalities to a Page
 */
trait SmartyFunctionalities {

	/** Using a custom delimiter for improved readability */
	public static $TAG_START = '{{';
	/** Using a custom delimiter for improved readability */
	public static $TAG_END = '}}';

	/** Smarty instance */
	protected $_smarty;
	/** Name of the template to be used */
	protected $_template;

	/**
	 * Creates an DoPhp-inited instance of smarty and returns it
	 *
	 * Useful when also using smarty in different context (eg. when sending
	 * emails)
	 *
	 * @param $config array: DoPhp config array
	 * @return \Smarty instance
	 */
	public static function newSmarty(& $config) {
		$smarty = new \Smarty();

		$smarty->left_delimiter = self::$TAG_START;
		$smarty->right_delimiter = self::$TAG_END;
		$smarty->setTemplateDir(array(
			"{$config['paths']['tpl']}/",
			'dophp' => "{$config['dophp']['path']}/tpl/"
		));
		$smarty->setCompileDir("{$config['paths']['cac']}/");
		$smarty->setCacheDir("{$config['paths']['cac']}/");

		$smarty->registerPlugin('modifier', 'format', 'dophp\Utils::format');
		$smarty->registerPlugin('modifier', 'formatTime', 'dophp\Utils::formatTime');
		$smarty->registerPlugin('modifier', 'formatNumber', 'dophp\Utils::formatNumber');
		$smarty->registerPlugin('modifier', 'formatCurrency', 'dophp\Utils::formatCurrency');
		$smarty->registerPlugin('modifier', 'formatCFloat', 'dophp\Utils::formatCFloat');
		$smarty->registerPlugin('modifier', 'escapeJsTpl', 'dophp\Utils::escapeJsTpl');

		$smarty->assign('config', $config);

		return $smarty;
	}

	/**
	* Prepares the template system
	*/
	protected function _initSmarty() {
		// Init smarty
		$this->_smarty = self::newSmarty($this->_config);

		// Init custom plugins
		$this->_smarty->registerPlugin('block', 'mstrip', ['dophp\SmartyFunctionalities','mStrip']);
		$this->_smarty->registerPlugin('modifier', 'diedump', ['dophp\SmartyFunctionalities','dieDump']);

		// Assign utility variables
		$this->_smarty->assign('this', $this);
		if( property_exists($this, '_name') )
			$this->_smarty->assign('page', $this->_name);
		foreach( $this->_config['paths'] as $k => $v )
			$this->_smarty->assign($k, $v);
		if( property_exists($this, '_user') )
			$this->_smarty->assignByRef('user', $this->_user);

		if( property_exists($this, '_alerts') )
			$this->_smarty->assignByRef('alerts', $this->_alerts);
		if( property_exists($this, '_loginError') )
			$this->_smarty->assignByRef('loginError', $this->_loginError);
		if( property_exists($this, '_pageTitle') )
			$this->_smarty->assignByRef('pageTitle', $this->_pageTitle);

		// Init default template name
		$base_file = basename($_SERVER['PHP_SELF'], '.php');
		$this->_template = "$base_file.{$this->_name}.tpl";
	}

	/**
	 * Searches for current template and falls back to given one if not found
	 *
	 * @param $fbtpl string: Fallback template
	 */
	protected function _templateFallback($fbtpl) {
		if( isset($this->_template) && $this->_template )
			foreach( $this->_smarty->getTemplateDir() as $td )
				if( file_exists($td . '/' . $this->_template) )
					return;

		$this->_template = $fbtpl;
	}

	/**
	 * Smarty plugin
	 *
	 * Leaves only one space where multiple spaces are found
	 */
	public static function mStrip($params, $content, $smarty, &$repeat) {
		if ( ! isset($content) )
			return;

		$out = preg_replace('/\s+/m', ' ', $content);
		if ( $out === null )
			throw new \Exception('Error in estrip block');

		return $out;
	}

	/**
	 * Smarty plugin
	 *
	 * Die and dump variable, useful for debug
	 */
	public static function dieDump($var) {
		die(var_dump($var));
	}

}


/**
* Implements a page using Smarty template engine
*
* @see CrudFunctionalities
*/
abstract class PageSmarty extends PageBase implements PageInterface {

	use SmartyFunctionalities;

	/**
	* Prepares the template system and passes execution to _build()
	*
	* @see PageInterface::run
	*/
	public function run() {
		$this->_initSmarty();

		// Call subclass build, return its custom data if given
		$custom = $this->_build();
		if( $custom !== null )
			return $custom;

		// Run smarty
		return $this->_compress($this->_smarty->fetch($this->_template));
	}

	/**
	* Build method to be overridden
	*
	* @return null too keep using smarty or custom data to be returned
	*/
	abstract protected function _build();
}
