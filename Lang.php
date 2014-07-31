<?php

/**
* @file Language.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Handles multilanguage web sites/services
*/

namespace dophp;

/**
* The main language class
*/
class Lang {

	/** List of supported languages */
	private $__supported = array();
	/** Character coding */
	private $__coding = array();
	/** Current language */
	private $__lang = null;

	/** Temporary container for switched text domain */
	private $__oldDomain = null;

	/**
	* Creates and instance of the language class
	*
	* @param $supported array: List of supported languages, in the form 'en' or
	*                          'en_US'. First one is assumed as default one.
	* @param $coding string: Character coding to use for all languages.
	*/
	public function __construct($supported, $coding) {
		$this->__supported = $supported;
		$this->__coding = $coding;

		// Sets the initial locale
		if( count($supported) )
			$this->setLanguage($this->__supported[0]);
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
		if( $manual && in_array($manual, $this->__supported) ) {
			$this->setLanguage($manual);
			$_SESSION['dophp_manual_lang'] = $manual;
			return;
		}

		if( isset($_SESSION['dophp_manual_lang']) ) {
			$this->setLanguage($_SESSION['dophp_manual_lang']);
			return;
		}

		$this->setLanguage(Utils::getBrowserLanguage($this->__supported));
	}

	/**
	* Gets current language
	*
	* @return string: The code of the language currently set
	*/
	public function getCurrentLanguage() {
		return $this->__lang;
	}

	/**
	* Returns the list of supported languages
	*
	* @return array List of supported language codes
	*/
	public function getSupportedLanguages() {
		return $this->__supported;
	}

	/**
	* Given a language name, returns the locale name including character coding
	*
	* @param $lang string: The language name
	* @return string: The locale name
	*/
	public function getLocaleName($lang) {
		if( $this->__coding )
			return $lang .= '.' . $this->__coding;
	}

	/**
	* Switch to DoPhp framework text domain, saving current domain to be restored
	* later
	*/
	public function dophpDomain() {
		$this->__oldDomain = textdomain(null);
		textdomain(\DoPhp::TEXT_DOMAIN);
	}

	/**
	* Switch back to previous text domain
	* @see dophpDomain()
	*/
	public function restoreDomain() {
		if( ! $this->__oldDomain )
			throw new \Exception('No domain to restore');
		textdomain($this->__oldDomain);
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
		$this->__lang = $lang;
	}

}
