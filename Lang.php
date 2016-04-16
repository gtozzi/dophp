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

	/** The name of the column containing texts */
	const TEXT_COL = 'text';
	/** The key to use in session and cookies */
	const LANG_KEY = 'lang';

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
			foreach( $this->_langTable->select(null,true) as $r )
				$this->_supported[] = $r[$pk];

			// Check the other tables
			if( count($this->_idxTable->getPk()) != 1 )
				throw new \Exception('Index table must have a single PK');
			if( count($this->_txtTable->getPk()) != 2 )
				throw new \Exception('Index table must have a composed PK');
			if( ! in_array(self::TEXT_COL, $this->_txtTable->getCols()) )
				throw new \Exception('Text table must have a '.self::TEXT_COL.' column');
		}

		if( ! $this->_supported )
			throw new \Exception('Must support at least one language');

		// Sets the initial locale
		$this->autoLanguage();
	}

	/**
	* Try to automatically determine the best lamguage for the user and sets it
	* The methods used are, in order:
	* 1. User's $manual choice
	* 2. Locale manually chosen via $_GET (first) or $_POST (last)
	* 3. Last locale manually chosen, from the session
	* 4. Last locale manually chosen, from cookie
	* 5. Brower's settings
	* 6. Fall back to default locale
	*
	* @param $manual string: Name of the locale manually chosen by the user, it
	*                        will be saved into the session if valid
	*/
	public function autoLanguage($manual=null) {
		if( $manual && in_array($manual, $this->_supported) )
			return $this->setLanguage($manual);

		if( isset($_GET[self::LANG_KEY]) ) {
			$lang = $this->getSupportedLanguageName(trim($_GET[self::LANG_KEY]));
			if( $lang )
				return $this->setLanguage($lang);
		}

		if( isset($_POST[self::LANG_KEY]) ) {
			$lang = $this->getSupportedLanguageName(trim($_POST[self::LANG_KEY]));
			if( $lang )
				return $this->setLanguage($lang);
		}

		if( isset($_SESSION[self::LANG_KEY]) )
			return $this->setLanguage($_SESSION[self::LANG_KEY]);

		if( isset($_COOKIE[self::LANG_KEY]) ) {
			$lang = $this->getSupportedLanguageName($_COOKIE[self::LANG_KEY]);
			if( $lang )
				return $this->setLanguage($lang);
		}

		return $this->setLanguage(Utils::getBrowserLanguage($this->_supported));
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
	* @return array: List of supported language codes
	*/
	public function getSupportedLanguages() {
		return $this->_supported;
	}

	/**
	* Checks if a language name is supported, returns the closest matching
	* supported language
	*
	* @return string: The full supported name, or null if not supported
	* @example getSupportedLanguageName('en') could return 'en_US'
	*/
	public function getSupportedLanguageName($lang) {
		if( ! $lang )
			return null;

		if( in_array($lang, $this->_supported) )
			return $lang;

		$country = $this->getCountryCode($lang);
		if( ! $country )
			return null;

		foreach( $this->_supported as $l )
			if( strlen($l) >= strlen($lang) && substr($l,0,strlen($lang)) == $lang )
				return $l;

		return null;
	}

	/**
	* Returns the name of the default language
	*
	* @return string: The name of the default language
	*/
	public function getDefaultLanguage() {
		return $this->_supported[0];
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
	* Given a language name, returns only the country code
	*
	* @param $lang string: The language name
	* @return string: The 2-letter country code
	*/
	public function getCountryCode($lang) {
		$e = explode('_', $lang);
		return $e[0];
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
	* @param $save bool: If true, also saves the locale in session and cookie
	*/
	public function setLanguage($lang, $save=true) {
		$locale = $this->getLocaleName($lang);
		if( ! setlocale(LC_ALL, $locale) )
			throw new \Exception("Unable to set locale $locale");
		$this->_lang = $lang;

		if( $save ) {
			$_SESSION[self::LANG_KEY] = $lang;
			if( ! isset($_COOKIE[self::LANG_KEY]) || $_COOKIE[self::LANG_KEY] != $lang )
				setcookie(self::LANG_KEY, $lang, time() + 60*60*24*1500, '/');
		}
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
			$par[self::TEXT_COL] = $txt;
			$this->_txtTable->insert($par);
		}

		return $id;
	}

	/**
	* Updates an existing text
	*
	* @param $id int: The text ID
	* @param $text array: Associative array of localized texts, in the form
	*                     <language> => <text>
	*/
	public function updText($id, $text) {
		foreach( $text as $lang => $txt ) {
			$pk = array_combine($this->_txtTable->getPk(), array($id,$lang));
			$par = array( self::TEXT_COL => $txt );
			$this->_txtTable->update($pk, $par);
		}
	}

	/**
	* Retirieves a text from the database
	*
	* @param $id int: The text ID
	* @param $lang string: The language code
	* @return string: The retrieved text
	*/
	public function getText($id, $lang) {
		$ret = $this->_txtTable->get(array($id, $lang), array(self::TEXT_COL));
		return $ret[self::TEXT_COL];
	}

	/**
	* Returns the list of available languages for a given text
	*
	* @param $id int: The text ID
	* @return array: The list of languages specified for this text
	*/
	public function getTextLangs($id) {
		$pk = $this->_txtTable->getPk();

		$texts = array();
		foreach( $this->_txtTable->select(array($pk[0] => $id), array($pk[1])) as $r )
			$texts[] = $r[$pk[1]];

		return $texts;
	}

}
