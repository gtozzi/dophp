<?php

/**
* @file Language.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Handles multilanguage web sites/services
*/

namespace dophp;

/**
* The main language class.
*
* Also handles database text storage and retrieve: a table <idxTable> contains
* a single field representing the unique localized text id. A second table <txtTable>
* is used for text storage and must have a composed primary key (index, language)
* and a `text` field for text storage.
*
* @example Database structure:
* CREATE TABLE IF NOT EXISTS `i18n` (
*  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
*  PRIMARY KEY (`id`))
* ENGINE = InnoDB
* COMMENT = 'Internationalization and localization';
*
* CREATE TABLE IF NOT EXISTS `translations` (
*  `i18n` INT UNSIGNED NOT NULL,
*  `languages` CHAR(2) NOT NULL,
*  `text` TEXT NOT NULL,
*  PRIMARY KEY (`i18n`, `languages`),
*  INDEX `fk_languages_has_i18n_i18n1_idx` (`i18n` ASC),
*  INDEX `fk_languages_has_i18n_languages1_idx` (`languages` ASC),
*  CONSTRAINT `fk_languages_has_i18n_languages1`
*    FOREIGN KEY (`languages`)
*    REFERENCES `languages` (`id`)
*    ON DELETE NO ACTION
*    ON UPDATE NO ACTION,
*  CONSTRAINT `fk_languages_has_i18n_i18n1`
*    FOREIGN KEY (`i18n`)
*    REFERENCES `i18n` (`id`)
*    ON DELETE NO ACTION
*    ON UPDATE NO ACTION)
* ENGINE = InnoDB;
*/
class Lang {

	/** Database instance */
	protected $_db;
	/** List of supported languages */
	protected $_supported = array();
	/** Character coding */
	protected $_coding = array();
	/** The language list table */
	protected $_langTable;
	/** The texts index table */
	protected $_idxTable;
	/** The texts table */
	protected $_txtTable;

	/** Current language */
	protected $_lang = null;
	/** Temporary container for switched text domain */
	protected $_oldDomain = null;

	/**
	* Creates and instance of the language class
	*
	* @param $db object: Database instance
	* @param $supported array: List of supported languages, in the form 'en' or
	*                          'en_US'. First one is assumed as default one.
	*                          $tables overrides this when specified.
	* @param $coding string: Character coding to use for all languages.
	* @param $tables array: associative array containing the list of database tables to use:
	*                       - lang: the table containing the list of supported
	*                               languages
	*                       - idx: the table containing the text indexes
	*                       - txt: the table containing the texts itself
	*/
	public function __construct($db, $supported, $coding, $tables=null) {
		$this->_db = $db;
		$this->_supported = $supported;
		$this->_coding = $coding;

		if( $tables ) {
			// database mode operation
			$this->_langTable = new Table($this->_db, $tables['lang']);
			$this->_idxTable = new Table($this->_db, $tables['idx']);
			$this->_txtTable = new Table($this->_db, $tables['txt']);

			// Retireve supported locales
			$this->_supported = array();
			$pk = $this->_langTable->getPk();
			if( count($pk) != 1 )
				throw new \Exception('Language table must have a single PK');
			$pk = $pk[0];
			foreach( $this->_langTable->select(null,true)[0] as $r )
				$this->_supported[] = $r[$pk];

			// Check the other tables
			if( count($this->_idxTable->getPk()) != 1 )
				throw new \Exception('Index table must have a single PK');
			if( count($this->_txtTable->getPk()) != 2 )
				throw new \Exception('Index table must have a composed PK');
			if( ! in_array('text', $this->_txtTable->getCols()) )
				throw new \Exception('Text table must have a `text` column');
		}

		// Sets the initial locale
		if( count($supported) )
			$this->setLanguage($this->_supported[0]);
	}

	/**
	* Try to automatically determine the best lamguage for the user and sets it
	* The methods used are, in order:
	* 1. User's manual choice
	* 2. Last locale manually chosen, from the session
	* 3. Brower's settings
	* 4. Fall back to default locale
	*
	* @param $manual string: Name of the locale manually chosen by the user, it
	*                        will be saved into the session if valid
	*/
	public function autoLanguage($manual=null) {
		if( $manual && in_array($manual, $this->_supported) ) {
			$this->setLanguage($manual);
			$_SESSION['dophp_manual_lang'] = $manual;
			return;
		}

		if( isset($_SESSION['dophp_manual_lang']) ) {
			$this->setLanguage($_SESSION['dophp_manual_lang']);
			return;
		}

		$this->setLanguage(Utils::getBrowserLanguage($this->_supported));
	}

	/**
	* Gets current language
	*
	* @return string: The code of the language currently set
	*/
	public function getCurrentLanguage() {
		return $this->_lang;
	}

	/**
	* Returns the list of supported languages
	*
	* @return array List of supported language codes
	*/
	public function getSupportedLanguages() {
		return $this->_supported;
	}

	/**
	* Given a language name, returns the locale name including character coding
	*
	* @param $lang string: The language name
	* @return string: The locale name
	*/
	public function getLocaleName($lang) {
		if( $this->_coding )
			return $lang .= '.' . $this->_coding;
	}

	/**
	* Switch to DoPhp framework text domain, saving current domain to be restored
	* later
	*/
	public function dophpDomain() {
		$this->_oldDomain = textdomain(null);
		textdomain(\DoPhp::TEXT_DOMAIN);
	}

	/**
	* Switch back to previous text domain
	* @see dophpDomain()
	*/
	public function restoreDomain() {
		if( ! $this->_oldDomain )
			throw new \Exception('No domain to restore');
		textdomain($this->_oldDomain);
	}

	/**
	* Sets the locale for the given language for LC_ALL, raising an exception
	* on failure.
	*
	* @param $lang string: Name of the language to set
	*/
	public function setLanguage($lang) {
		$locale = $this->getLocaleName($lang);
		if( ! setlocale(LC_ALL, $locale) )
			throw new \Exception("Unable to set locale $locale");
		$this->_lang = $lang;
	}

	/**
	* Stores a new text into the database. Returns the ID.
	*
	* @param $text array: Associative array of localized texts, in the form
	*                     <language> => <text>
	* @return integer: The new text ID
	*/
	public function newText($text) {
		$id = $this->_idxTable->insert(array($this->_idxTable->getPk()[0] => null));
		
		foreach( $text as $lang => $txt ) {
			$par = array_combine($this->_txtTable->getPk(), array($id,$lang));
			$par['text'] = $txt;
			$this->_txtTable->insert($par);
		}

		return $id;
	}

}
