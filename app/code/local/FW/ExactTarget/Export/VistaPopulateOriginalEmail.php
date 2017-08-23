<?php
/**
 * Created by IntelliJ IDEA.
 * User: dmatriccino
 * Date: 9/23/15
 * Time: 1:42 PM
 */

require_once '../../../../../Mage.php';  // Include Mage

Mage::app('admin');

if(!isset($argv[1])) {
    echo "Usage: php VistaPopulateOriginalEmail.php {fileName}\n";
    exit;
}

$fileName = $argv[1];

function getNullCollection() {
    $collection = Mage::getModel('customer/customer')->getCollection()
        ->setPageSize(2000)
        ->addAttributeToSelect('original_email', 'vistacustomer_id')
        ->addAttributeToFilter('original_email', array('null' => true), 'left');
    if($collection->count() > 1) {
	return $collection;
    }
    return false;
}

function getVistaCollection($vistaCustomerId) {
    $collection = Mage::getModel('customer/customer')->getCollection()
        ->addAttributeToSelect('*')
        ->addAttributeToFilter('vistacustomer_id', array('eq' => $vistaCustomerId))
        ->addAttributeToFilter('original_email', array('null' => true), 'left');
    
    return $collection;
}

$row = 1;
if (($handle = fopen($fileName, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
	$zirconId = trim($data[0]);
	$originalEmail = strtolower(trim($data[1]));
	$collection = getVistaCollection($zirconId);
	foreach($collection as $customer) {
	    if($customer != null) {
		try {
	            $customer->setOriginalEmail($originalEmail);
	            $customer->save();
		    Mage::log("$zirconId successful save", null, "original_email_success.log");
		}
		catch(Exception $ex) {
		    Mage::log("Zircon customer $zirconId: " . $ex->getMessage(), null, "original_email_exception.log");
		} 
	    }
        }
    }
    fclose($handle);
}

//Loop through any customer without an vistaId
while($customerCollection = getNullCollection()) {
    foreach ($customerCollection as $customer) {
        $originalEmail = $customer->getEmail();
        try {
            $customer->setOriginalEmail($originalEmail);
            $customer->save();
            Mage::log("$zirconId successful save", null, "original_email_success.log");
	}
        catch(Exception $ex) {
                Mage::log("Zircon customer $zirconId: " . $ex->getMessage(), null, "original_email_exception.log");
        }
    }
}
