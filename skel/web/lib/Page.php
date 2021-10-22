<?php declare(strict_types=1);


abstract class Page extends dophp\PageSmarty {

	protected function _build() {
		$this->_buildChild();
	}

	abstract protected function _buildChild();

}
