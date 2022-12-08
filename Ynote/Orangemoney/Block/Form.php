<?php

class Ynote_Orangemoney_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        $this->setTemplate('payment/form/om.phtml');
        parent::_construct();
    }

}
