<?php

class FW_ExactTarget_Block_Knittinglists
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    public function _prepareToRender()
    {
        $this->addColumn('masterde', array(
            'label' => Mage::helper('adminhtml')->__('Master DE'),
            'style' => 'width:150px',
        ));
        $this->addColumn('communityde', array(
            'label' => Mage::helper('adminhtml')->__('Community DE'),
            'style' => 'width:150px',
        ));
        $this->addColumn('mid', array(
            'label' => Mage::helper('adminhtml')->__('MID'),
            'style' => 'width:150px',
        ));
        $this->addColumn('lists', array(
            'label' => Mage::helper('adminhtml')->__('List Names'),
            'style' => 'width:250px',
        ));

        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add');
    }
}