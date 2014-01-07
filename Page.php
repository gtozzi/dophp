<?php

/**
* @file Page.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Base classes for handling pages
*/

namespace dophp;

/**
* Interface for implementing a page
*/
interface PageInterface {

    /**
    * Constructor
    *
    * @param $config array: Global config array
    * @param $db     object: Database instance
    * @param $user   object: Current user object instance
    * @param $name   string: Name of this page
    */
    public function __construct(& $config, $db, $user, $name);

    /**
    * Method called when the page is executed
    *
    * Must return page output (usually HTML) or lauch a PageError exception
    */
    public function run();

    /**
    * Method called to retrieve page headers
    *
    * Must return associative array
    */
    public function headers();
}

/**
* Base class for easier page implementation
*/
abstract class PageBase {

    /** Config array */
    protected $_config;
    /** Database instance */
    protected $_db;
    /** User instance */
    protected $_user;
    /** Name of this page */
    protected $_name;
    /** Headers to be output */
    protected $_headers = array();

    /**
    * Contrsuctor
    *
    * @see PageInterface::__construct
    */
    public function __construct(& $config, $db, $user, $name) {
        $this->_config = $config;
        $this->_db = $db;
        $this->_user = $user;
        $this->_name = $name;
    }

    /**
    * Returns headers
    *
    * @see PageInterface::headers
    */
    public function headers() {
        return $this->_headers;
    }

}

/**
* Implements a page using Smarty template engine
*/
abstract class PageSmarty extends PageBase implements PageInterface {

    /** Using a custom delimiter for improved readability */
    const TAG_START = '{{';
    /** Using a custom delimiter for improved readability */
    const TAG_END = '}}';

    /** Smarty instance */
    protected $_smarty;

    /**
    * Main run method
    *
    * @see PageInterface::run
    */
    public function run() {
        // Init smarty
        $this->_smarty = new \Smarty();
        $this->_smarty->left_delimiter = self::TAG_START;
        $this->_smarty->right_delimiter = self::TAG_END;
        $this->_smarty->setTemplateDir("{$this->_config['paths']['tpl']}/");
        $this->_smarty->setCompileDir("{$this->_config['paths']['cac']}/");
        $this->_smarty->setCacheDir("{$this->_config['paths']['cac']}/");

        $this->_smarty->registerPlugin('modifier', 'formatTime', 'dophp\Utils::formatTime');
        $this->_smarty->registerPlugin('modifier', 'formatNumber', 'dophp\Utils::formatNumber');

        $this->_smarty->assign('page', $this->_name);

        $this->_build();

        $base_file = basename($_SERVER['PHP_SELF'], '.php');
        return $this->_smarty->fetch("$base_file.{$this->_name}.tpl");
    }

    /**
    * Build method to be overridden
    */
    abstract protected function _build();
}

/**
* Exception raised if something goes wrong during page rendering
*/
class PageError extends \Exception {
}
