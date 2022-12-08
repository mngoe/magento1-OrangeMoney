<?php 

class Ynote_Orangemoney_Model_Resource_Notification extends Mage_Core_Model_Resource_Db_Abstract {

    public function _construct() {
        $this->_init('orangemoney/notification','id_order');
		$this->_isPkAutoIncrement = false;
    }

}

?>