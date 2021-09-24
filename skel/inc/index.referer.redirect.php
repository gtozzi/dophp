<?php


/**
 * Enpoint used to implement redirect to referer.
 * Useful, i.e., after login.
 */
class doReferer_Redirect extends Page {

	// Only logged users
	protected $_access = [];

	protected function _buildChild() {
		if ($this->_referer)
			throw new \dophp\UrlRedirect($this->_referer);

		throw new \InvalidArgumentException("referer parameter is mandatory");
	}

}
