<?php
/**
 * Created by IntelliJ IDEA.
 * User: dmatriccino
 * Date: 9/23/15
 * Time: 1:42 PM
 */

require_once '../../../../../Mage.php';  // Include Mage

Mage::app('admin');
function getCollection() {
    $collection = Mage::getModel('customer/customer')->getCollection()
        ->setPageSize(2000)
        ->addAttributeToSelect('original_email')
        ->addAttributeToFilter('original_email', array('null' => true), 'left');
    if($collection->count() > 1) {
        return $collection;
    }
    return false;
}

while($customerCollection = getCollection()) {
    foreach ($customerCollection as $customer) {
        $email = $customer->getEmail();
        $customer->setOriginalEmail($email);
        $customer->save();
    }
}
