<?php
    chdir(dirname(__FILE__));  // Change working directory to script location
    require_once '../../../../../Mage.php';  // Include Mage
    Mage::app('admin');  // Run Mage app() and set scope to admin
    
    $etFeed = new FW_ExactTarget_Export_Datafeed();
    $etFeed->exportProductFeed();
?>
