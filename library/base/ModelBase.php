<?php
namespace library\base;

use library\util\Utils;
use library\define\Constant;

class ModelBase{
    public $_db = null;
    public function __construct() {
        $name = Utils::getCurrUser();
        $this->_db = RpcClient::getDb($name);
	}

	public function rollback() {
		$this->_db->rollback();
	}

	public function commit() {
		$this->_db->commit();
	}

	public function startTransaction() {
		$this->_db->startTransaction();
	}

}
