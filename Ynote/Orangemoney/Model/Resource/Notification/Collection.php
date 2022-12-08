<?php 

class Ynote_Orangemoney_Model_Resource_Notification_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract {

    public function _construct() {
    	parent::_construct();
        $this->_init('orangemoney/notification');
    }

}

?>