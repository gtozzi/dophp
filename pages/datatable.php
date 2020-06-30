<?php

namespace dophp;


/**
 * Generic method for returning DataTable data
 */
class DataTableMethod extends \dophp\HybridRpcMethod {

	/** The data table */
	protected $_table;

	public function setTable(\dophp\widgets\DataTableInterface $table) {
		$this->_table = $table;
	}

	public function _init() {
		parent::_init();
		$this->_params = $this->_table->getParamStructure();
	}

	public function _build( &$pars ): array {
		return $this->_table->getData($pars);
	}
}
