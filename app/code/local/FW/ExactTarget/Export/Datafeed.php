<?php

class FW_ExactTarget_Export_Datafeed// extends Mage_Core_Model_Abstract
{
    protected $_exportFileName;
    protected $_exportFullFileName;
    protected $_exportDirectory;
    protected $_websiteBase;
    protected $_websiteName;
        
    public function __construct()
    {
        $this->_exportDirectory = Mage::getBaseDir().'/feed/exacttarget/';
    }
    
    /**
     * Generates ET Data Feed File, 1 per system store
     * 
     */
    public function exportProductFeed()
    {
        Mage::app()->setCurrentStore(Mage::getModel('core/store')->load(Mage_Core_Model_App::ADMIN_STORE_ID));
    	$errorFile =  'ExactTarget_DataFeed_Error.log';

        try 
        {
            $websites = Mage::getModel('core/website')->getCollection();
			
            foreach ($websites as $website) 
            {
                if($website->getName() != "Main Website")
                {
                    $this->_websiteName = $website->getName();
                    $this->_websiteBase = str_replace(" ", "", substr($this->_websiteName , 0, -4));
                    $this->_websiteBase = str_replace(".", "", $this->_websiteBase );
                    $this->_exportFileName = $this->_websiteBase .".txt";
                    $this->_exportFullFileName = $this->_exportDirectory.$this->_exportFileName;
                    $exportFileHandler = fopen($this->_exportFullFileName, 'w'); 
                    $fileLine = "NAME|URL|SKU|IMAGE|IMAGE2|IMAGE3|SHORT DESCRIPTION|RETAIL PRICE|SPECIAL PRICE|RATING|QUANTITY|BYLINE|SUBTITLE|ABOUT AUTHOR|SOLD BY LENGTH\r\n";
                    fwrite($exportFileHandler,$fileLine);
                    fclose($exportFileHandler);
                    
                    $products = Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect(array('name', 
                        'url_key', 
                        'price', 
                        'vista_edition_type',  
                        'status', 
                        'visibility',
                        'image',
                        'sold_by_length',
                        'about_author',
                        'author_speaker_editor',
                        'sub_title',
                        'short_description',
                        'special_price',
                        'special_from_date',
                        'special_from_date',
                        'special_to_date',
                    ), 'left')->addWebsiteFilter($website->getId());

                    Mage::getSingleton('core/resource_iterator')->walk($products->getSelect(),  array(array($this, 'productCallback')),array('websiteName' => $website->getName(), 'websiteId' => $website->getId()));

                    $filesToSend[$this->_exportFileName] = $this->_exportFullFileName;
                    fclose($exportFileHandler);
                    unset($products);
                }	
            }
			
           $this->sendFiles($filesToSend,$errorFile);
        } 
        catch (Exception $e) 
        {
                Mage::log("Exact Target Product Export Error: ".$e->getMessage(),null, $errorFile);
                $this->sendErrorEmail($e->getMessage());
        }
    }

    
    function productCallback($args)
    {
        $product= Mage::getModel('catalog/product')->setData($args['row']);
        $website = Mage::app()->getWebsite($args['websiteId']);
        $storeId = $website->getDefaultGroup()->getDefaultStore()->getId();

        //If the item is out of stock, then dont include in feed
        //If the product type is a bundle then have to load the full product to get the correct price and stock status
        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
        if($product->getTypeId() == 'bundle')
        {

            $bundleProduct = Mage::getModel('catalog/product')->load($product->getId());
            $statusObj = new Mage_CatalogInventory_Model_Stock_Status();
            $bundleStockStatusObj = $statusObj->getProductStatus($product->getId(),$args['websiteId'], 1);
            $stockStatus = $bundleStockStatusObj[$product->getId()];
            $price = $bundleProduct->getPrice();
            $bundleProduct->setStoreId($storeId);
            $priceModel  = $bundleProduct->getPriceModel();
            list($minimalPrice, $maximalPrice) = $priceModel->getTotalPrices($bundleProduct, null, null, false);

            if ($price < $minimalPrice){
                $price = $minimalPrice;
            }

        }
        else{
            $price = $product->getPrice();
            $stockStatus = "";
            if($product->getTypeId() == 'configurable') {

                foreach ($product->getTypeInstance(true)->getUsedProducts(null, $product) as $child) {
                    if ($child['is_in_stock']) {
                        $stockStatus = "1";
                        break;
                    }
                }
            }
            else if ($product->getTypeId() == 'grouped'){
                foreach ($product->getTypeInstance(true)->getAssociatedProducts($product) as $child) {
                    if ($child['is_in_stock']) {
                        $stockStatus = "1";
                        break;
                    }

                }
            } else {
                if($stockItem->getId()){
                    $stockStatus = $stockItem->getIsInStock();
                }
            }
        }

        if($stockStatus == "1") {

            if($product->getStatus() == 1 &&  $product->getVisibility() != 0 &&  $product->getVisibility() != 1) {
                $etHelper = Mage::helper('fw_exacttarget');
                $cdnAddress = $etHelper->getImageCdnAddress();

                $fileLine = $product->getName() . "|";
                $fileLine = $fileLine . "http://" . $this->_websiteName."/" . $product->getUrlKey() ."|";
                $fileLine  = $fileLine . $product->getSku() . "|";
                
                $baseImgUrl = Mage::getModel('catalog/product_media_config')->getMediaUrl( $product->getImage() );
                $baseLocation = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
                $baseImgUrl = str_replace($baseLocation, "", $baseImgUrl);

                //Images
                $imgUrl1 = $cdnAddress . "/image-server/120x120/".$baseImgUrl . "|";
                $fileLine = $fileLine . $imgUrl1;
                $imgUrl2 = $cdnAddress . "/image-server/200x200/".$baseImgUrl . "|";
                $fileLine = $fileLine . $imgUrl2;
                $imgUrl3 = $cdnAddress . "/image-server/500x500/".$baseImgUrl . "|";
                $fileLine = $fileLine . $imgUrl3;

                //Short Description
                $shortDescription = $product->getShortDescription();
                $shortDescription = str_replace(array("\r"), '', $shortDescription);
                $shortDescription = str_replace(PHP_EOL, '', $shortDescription);
                $shortDescription = str_replace("\t", "&#09;", $shortDescription);
                $shortDescription = str_replace("\"", "&#34;", $shortDescription);
                $shortDescription = str_replace("'", "&#39;", $shortDescription);
                $shortDescription = trim($shortDescription);
                $fileLine = $fileLine . $shortDescription  . "|";

                //Price
                //price is not valid then don't cast to formatted number
                if($price != "") {
                    $fileLine = $fileLine . str_replace(",", "", number_format($price, 2)) . "|";
                }
                else {
                    $fileLine = $fileLine . str_replace(",", "", $price) . "|";
                }

                //Special Price
                $specialPrice =  $this->retrieveSpecialPrice($product,$bundleProduct,$storeId);

                //if special price is not valid then don't cast to formatted number
                if($specialPrice != "") {
                    $fileLine = $fileLine . str_replace(",", "", number_format($specialPrice, 2)) . "|";
                }
                else {
                    $fileLine = $fileLine . str_replace(",", "", $specialPrice) . "|";
                }

                //Rating
                $rating ="";
                $this->addRatingVoteAggregateToProduct($product, $storeId);

                if($product->getVotes())
                {
                    $votes = $product->getVotes();
                    $voteValueSum = $votes['vote_value_sum'];
                    $voteCount = $votes['vote_count'];
                    $rating = number_format($voteValueSum/$voteCount,1);
                }
                $fileLine = $fileLine . $rating . "|";

                //Qty
                $fileLine = $fileLine . $stockItem->getQty(). "|";

                //Author Speaker Editor
                $authorSpeakerEditor = $product->getAuthorSpeakerEditor();
                $authorSpeakerEditor = str_replace("\t", "&#09;", $authorSpeakerEditor);
                $authorSpeakerEditor = str_replace("\"", "&#34;", $authorSpeakerEditor);
                $authorSpeakerEditor = str_replace("'", "&#39;", $authorSpeakerEditor);
                $fileLine = $fileLine . $authorSpeakerEditor. "|";

                //Subtitle
                $subTitle = $product->getSubTitle();
                $subTitle = str_replace("\t", "&#09;", $subTitle);
                $subTitle = str_replace("\"", "&#34;", $subTitle);
                $subTitle = str_replace("'", "&#39;", $subTitle);
                $fileLine = $fileLine . $subTitle . "|";

                //About the Author
                $aboutAuthor = $product->getAboutAuthor();
                $aboutAuthor = str_replace("\t", "&#09;", $aboutAuthor);
                $aboutAuthor = str_replace("\"", "&#34;", $aboutAuthor);
                $aboutAuthor = str_replace("'", "&#39;", $aboutAuthor);
                $fileLine = $fileLine . $aboutAuthor . "|";

                //Sold By Length
                $soldByLength = $product->getSoldByLength();

                if(!$soldByLength){
                    $soldByLength = 0;
                }

                $fileLine = $fileLine . $soldByLength . "\r\n";

                $exportFileHandler = fopen($this->_exportFullFileName, 'a'); 
                fwrite($exportFileHandler,$fileLine);
                fclose($exportFileHandler);
            }
        }
    }

    /**
     * Determine the value to be used for a product's special price
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Catalog_Model_Product $bundleProduct
     * @param string $storeId
     * @return string $specialPrice
     */
    private function retrieveSpecialPrice($product, $bundleProduct, $storeId){
        //Special Price
        $specialPriceFromDate = strtotime($product->getSpecialFromDate());
        $specialPriceToDate = strtotime($product->getSpecialToDate());
        $specialPrice = "";

        if($specialPriceFromDate == false) {
            if($specialPriceToDate == false) {
                $specialPrice = $product->getSpecialPrice();
            }
            else {
                if(strtotime("now") <= $specialPriceToDate) {
                    $specialPrice = $product->getSpecialPrice();
                }
            }
        }
        else if($specialPriceToDate == false) {
            if($specialPriceFromDate == false) {
                $specialPrice = $product->getSpecialPrice();
            }
            else {
                if(strtotime("now") >= $specialPriceFromDate) {
                    $specialPrice = $product->getSpecialPrice();
                }
            }
        }
        else {
            if(strtotime("now") < $specialPriceToDate && strtotime("now") > $specialPriceFromDate) {
                $specialPrice = $product->getSpecialPrice();
            }
        }

        if($product->getTypeId() == 'bundle' && $specialPrice != "") {
            $specialPrice = number_format(($bundleProduct->getSpecialPrice() / 100)  * $bundleProduct->getPrice(), 2); //always a percentage special price adjustment
        }


        $productRulePrice = Mage::getResourceModel('catalogrule/rule')->getRulePrice(Mage::app()->getLocale()->storeTimeStamp($storeId), Mage::app()->getStore($storeId)->getWebsiteId(), 0,$product->getId());

        if($productRulePrice != FALSE) {
            $specialPrice = $productRulePrice;
        }

        return $specialPrice;
    }

    /**
     * Manually add the Rating to a Product
     * @param Mage_Catalog_Model_Product $product
     * @param string $storeId
     */
    private function addRatingVoteAggregateToProduct(Mage_Catalog_Model_Product $product, $storeId)
    {
        $read = Mage::getSingleton('core/resource')->getConnection('catalog_read');
        $ratingValues = $read->fetchAll('SELECT rova.vote_count, rova.vote_value_sum  FROM `rating_option_vote_aggregated` AS `rova`WHERE rova.entity_pk_value = ' . $product->getId() . ' AND rova.store_id = ' . $storeId);

        if($ratingValues)
        {
            $product->setData('votes',$ratingValues[0]);
            $product->setData('vote_value_sum', $ratingValues[1]);
        }
    }

    /**
     * Send files generated via FTP to Exact Target
	 * @param $filesToSend Array
	 * @param $errorFile string
     */
    private function sendFiles($filesToSend,$errorFile)
    {
        # ftp-login
        $etHelper = Mage::helper('fw_exacttarget');
        $ftp_server = $etHelper->getFtpHost();
        $ftp_user = $etHelper->getFtpUser();
        $ftp_pw = $etHelper->getFtpPassword();
        $ftp_dir = $etHelper->getFtpLocation();

        // set up basic connection
        $conn_id = ftp_connect($ftp_server);
		
        // login with username and password
        if($conn_id == false)
        {
            echo "Connection to ftp server failed\n";
            Mage::log('Connection to ftp server failed',null,$errorFile);
            $this->sendErrorEmail();
            return;
        }
		
        // login with username and password
        $login_result = ftp_login($conn_id, $ftp_user, $ftp_pw);
        
        if($login_result == false) 
        { 
            echo "Login to ftp server failed\n"; 
            Mage::log('Login to ftp server failed\r\n',null,$errorFile); 
            $this->sendErrorEmail();
            return; 
        }
		
        // turn passive mode on
        ftp_pasv($conn_id, true);
        if($ftp_dir != null && $ftp_dir != "") {
            ftp_chdir($conn_id, $ftp_dir);
        }

        foreach($filesToSend as $fileName=>$fullFileName)
        {
            ftp_put($conn_id, $fileName, $fullFileName, FTP_BINARY, 0) ;
        }

        // close the connection
        ftp_close($conn_id);
    }
	
    /**
     * Send Email signifying error occurred
     */
    public function sendErrorEmail($error)
    {
        //EMAIL ERROR NOTICE
        $etHelper = Mage::helper('fw_exacttarget');
        $to = $etHelper->getEmailNotice();
        $subject = "Data Feed Error (Exact Target)";
        $body = $error;
        mail($to, $subject, $body);
    }
}


