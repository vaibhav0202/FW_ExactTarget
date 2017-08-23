<?php
$installer = $this;
$installer->startSetup();

/**
 * Add original_email attribute for customer entities
 */
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$entityTypeId     = $setup->getEntityTypeId('customer');
$attributeSetId   = $setup->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $setup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

$setup->addAttribute('customer', 'original_email', array(
    'input'         => 'text',
    'type'          => 'varchar',
    'label'         => 'Original Email',
    'visible'       => 1,
    'required'      => 0,
    'unique'        => 1,
    'user_defined' => 1,
));

$setup->addAttributeToGroup(
    $entityTypeId,
    $attributeSetId,
    $attributeGroupId,
    'original_email'
);

$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'original_email');
$oAttribute->setData('used_in_forms', array('adminhtml_customer'));
$oAttribute->save();

$installer->endSetup();

//Update all existing customers to set original_email to customer's current email
