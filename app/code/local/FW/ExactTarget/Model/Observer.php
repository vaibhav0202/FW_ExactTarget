<?php
//Include necessary ET library classes
require_once(Mage::getBaseDir('lib') . '/ExactTarget/class.ET2SoapSubscribe.php');
require_once(Mage::getBaseDir('lib') . '/ExactTarget/class.SubscriberData.php');
require_once(Mage::getBaseDir('lib') . '/ExactTarget/class.PurchaseItem.php');
require_once(Mage::getBaseDir('lib') . '/ExactTarget/class.CampaignData.php');
require_once(Mage::getBaseDir('lib') . '/ExactTarget/class.ZenithData.php');

class FW_ExactTarget_Model_Observer {

    //This is called after a user signs up in the newsletter form.
    public function exactTargetNewsLetterQuickSubscribe($observer)
    {
        $params = $observer->getControllerAction()->getRequest()->getParams();
        $postFieldsArray['Email Address'] = $params['email'];
        $postFieldsArray['original_email'] = $params['email'];
        $postFieldsArray['store_id'] = Mage::app()->getStore()->getStoreId();
        $postFieldsArray['website'] = Mage::getModel('core/store')->load($postFieldsArray['store_id'])->getName();
        $thankYouPage = Mage::getStoreConfig('thirdparty/exacttarget/thankyou_page');
        $postFieldsArray['brand'] = $this->loadBrandValue($postFieldsArray['website']);

        //Create queue item for email list
        $this->createQueueItem('exacttarget_quicksubscribe',
            'Exact Target Quick Subscribe. Email: ' . $postFieldsArray['Email Address'] . ' Website: ' . $postFieldsArray['website'],
            $postFieldsArray);

        //Get GA Campaign values and create Campaign Queue item
        $utmArray = Mage::helper('fw_exacttarget')->getUtmCampaign();
        $postFieldsArray['campaign'] = $utmArray['utm_campaign'];
        $postFieldsArray['source'] = $utmArray['utm_source'];
        $postFieldsArray['medium'] = $utmArray['utm_medium'];
        $this->createQueueItem('exacttarget_campaign',
            'Exact Target Campaign from QuickSubscribe. Email: ' . $postFieldsArray['Email Address'] . ' Website: ' . $postFieldsArray['website'] . '. Campaign: ' . $postFieldsArray['campaign'],
            $postFieldsArray);

        header("Location: " . $thankYouPage);
        exit;
    }

    //This is called after a user tries to register.
    public function exactTargetSignupAfterRegister($observer)
    {
        //Grab the user 
        $session = Mage::getSingleton('customer/session');

        //Grab the post fields
        $params = $this->_getParams($observer);

        //Make sure the user is logged in because this function is triggered even if the user
        //tries to register and an error occurs.  The user being logged in means registration was a success.
        //It also checks to make sure the user clicked the "Signup for Newsletter, and that the ET registration is active.
        if ($session->isLoggedIn()) {
            //Set original_email to retain for when a user updates their email address at a later date
            $email = $params['email'];

            $customer = $session->getCustomer();
            $customer->setOriginalEmail($email);
            $customer->save();

            if (isset($params['is_subscribed']) && $params['is_subscribed'] == 1) {

                $params['original_email'] = $email;
                $params['website'] = Mage::getModel('core/store')->load($params['store_id'])->getName();
                $params['brand'] = $this->loadBrandValue($params['website']);

                //Create queue item for email list
                $this->createQueueItem('exacttarget_register',
                    'Exact Target Register for: ' . $params['email'] . ' Website: ' . $params['website'],
                    $params);

                //Get GA Campaign values and create Campaign Queue item
                $utmArray = Mage::helper('fw_exacttarget')->getUtmCampaign();
                $params['campaign'] = $utmArray['utm_campaign'];
                $params['source'] = $utmArray['utm_source'];
                $params['medium'] = $utmArray['utm_medium'];
                $this->createQueueItem('exacttarget_campaign',
                    'Exact Target Campaign from Registration. Email: ' . $params['email'] . ' Website: ' . $params['website'] . '. Campaign: ' . $params['campaign'],
                    $params);
            }
        }
    }

    //This is called after a user updates their profile
    public function exactTargetProfileUpdate($observer)
    {
        //Grab the user
        $session = Mage::getSingleton('customer/session');

        //Make sure the user is logged in because this function is triggered even if the user
        //tries to register and an error occurs.  The user being logged in means registration was a success.
        //It also checks to make sure the user clicked the "Signup for Newsletter, and that the ET registration is active.
        if ($session->isLoggedIn()) {
            $customer = $session->getCustomer();
            $originalEmail = $customer->getOriginalEmail();

            //Grab the post fields
            $params = $this->_getParams($observer);
            $params['original_email'] = $originalEmail;

            //Create queue item for email list
            $this->createQueueItem('exacttarget_profile_update',
                'Exact Target Profile Update for: ' . $params['email'],
                $params);
        }
    }

    private function _getParams($observer)
    {
        $params = $observer->getControllerAction()->getRequest()->getParams();

        //Grab the user
        $session = Mage::getSingleton('customer/session');

        if($session->isLoggedIn()) {
            $customer = $session->getCustomer();

            //Grab all the users attributes
            $customerAttributes = $customer->getAttributes();

            //Loop through each attribute and determine if it's a dropdown and no a system attribute
            foreach ($customerAttributes as $attribute) {
                if ($attribute->getFrontend()->getInputType() == "select" && !$attribute->getIsSystem()) {
                    $attributeCode = $attribute->getAttributeCode();

                    //If the attribute code is in the params array - we need to override the value with the option label
                    if (isset($params[$attributeCode])) {
                        $attributeValue = $params[$attributeCode];
                        $selectOptions = $attribute->getFrontend()->getSelectOptions();
                        foreach ($selectOptions as $option) {
                            if ($attributeValue == $option['value']) {
                                $params[$attributeCode] = $option['label'];
                                break;
                            }
                        }
                    }
                }
            }
        }

        $params['Email Address'] = $params['email'];
        $params['FIRST NAME']    = $params['firstname'];
        $params['LAST NAME']     = $params['lastname'];
        $params['store_id']      = Mage::app()->getStore()->getStoreId();

        //Remove password fields from $params[] so we're not adding cleartext passwords to Queue item
        unset($params['password']);
        unset($params['confirmation']);

        Mage::log(print_r($params,true), null, 'params.log');

        return $params;
    }

    public function onOrderPlace($observer)
    {
        $session = Mage::getSingleton('customer/session');

        $originalEmail = null;

        //Make sure the user is logged in because this function is triggered even if the user
        //tries to register and an error occurs.  The user being logged in means registration was a success.
        if ($session->isLoggedIn()) {
            $customer = $session->getCustomer();
            $originalEmail = $customer->getOriginalEmail();

            //Set customer's original email on a new order
            if($originalEmail == null) {
                $originalEmail = $customer->getEmail();
                $customer->setOriginalEmail($originalEmail);
                $customer->save();
            }
        }

        //GET ORDER IDs
        $order = $observer->getOrder();
        $orderId[0] = $order->getId();
        $orderIncrementId = $order->getIncrementId();
        $storeId = $order->getStoreId();

        $params = array();

        //Grab the post fields
        $params['orders'] = $orderId;
        $params['original_email'] = $originalEmail;
        $params['store_id'] = $storeId;
        $params['website'] = Mage::getModel('core/store')->load($storeId)->getName();
        $params['brand'] = $this->loadBrandValue($params['website']);

        //Get GA Campaign values and create Campaign Queue item
        $utmArray = Mage::helper('fw_exacttarget')->getUtmCampaign();
        $params['campaign'] = $utmArray['utm_campaign'];
        $params['source'] = $utmArray['utm_source'];
        $params['medium'] = $utmArray['utm_medium'];
        $this->createQueueItem('exacttarget_campaign',
            'Exact Target Campaign from Order. Website: ' . $params['website'] . '. Campaign: ' . $params['campaign'] . '. Order #' . $orderIncrementId,
            $params);

        //Create queue item for order data
        $this->createQueueItem('exacttarget_order',
            'Exact Target Order Data from ' . $params['website'] . '. Order #' . $orderIncrementId,
            $params);

        //Create queue item for email list
        $this->createQueueItem('exacttarget_email_from_order',
            'Exact Target Email from Order. Website: ' . $params['website'] . ' Order #'. $orderIncrementId,
            $params);
    }

    public function createQueueItem($code, $desc, $params)
    {
        $queueArgs = array('function' => 'processQueue',
            'code' => $code,
            'desc' => $desc);
        $queue = Mage::getModel('fw_queue/queue');
        $queue->addToQueue('fw_exacttarget/observer', $queueArgs['function'], $params, $queueArgs['code'], $queueArgs['desc']);
    }

    public function processQueue($queueModel)
    {
        $queueCode = $queueModel->getCode();
        $queueData = $queueModel->getQueueData();

        //Load ET Helper and Store ID
        $helper = Mage::helper('fw_exacttarget');
        $storeId = $queueData['store_id'];

        //Check if ET admin config is set
        $active = $helper->isExactTargetAvailable($storeId);

        if ($active) {
            switch ($queueCode) {
                case "exacttarget_campaign":
                    $this->_exactTargetSubmitCampaign($queueData, $helper, $storeId);
                    break;
                case "exacttarget_quicksubscribe":
                    $this->_exactTargetSubmitEmail($queueData, $helper, $storeId);
                    break;
                case "exacttarget_register":
                    $this->_exactTargetSubmitEmail($queueData, $helper, $storeId);
                    break;
                case "exacttarget_email_from_order":
                    $this->_exactTargetSubmitEmail($queueData, $helper, $storeId);
                    break;
                case "exacttarget_email_from_catalogrequest":
                    $this->_exactTargetSubmitEmail($queueData, $helper, $storeId);
                    break;
                case "exacttarget_order":
                    $this->_exactTargetSubmitOrder($queueData, $helper, $storeId);
                    break;
                case "exacttarget_profile_update":
                    $this->_exactTargetSubmitEmail($queueData, $helper, $storeId);
                    break;
                case "exacttarget_zenith_vip":
                    $this->_exactTargetSubmitZenith($queueData, $storeId);
            }
        } else {
            throw new Exception("Exact Target module settings are not configured properly");
        }
    }

    public function loadEmailFromOrder($orderArray, $originalEmail)
    {
        if (!empty($orderArray) && is_array($orderArray)) {

            foreach ($orderArray as $oid) {

                //Load Order Mage Model
                $order = Mage::getSingleton('sales/order');
                if ($order->getId() != $oid) $order->reset()->load($oid);

                $email = $order->getCustomerEmail();
                $firstName = $order->getCustomerFirstname();
                $lastName = $order->getCustomerLastname();

                //Null original email indicates user checked out as guest, so set original email to their guest checkout email
                if ($originalEmail == null) {
                    $originalEmail = $email;
                }

                $ret = array("originalEmail" => $originalEmail, "email" => $email, "firstName" => $firstName, "lastName" => $lastName);
            }
            return $ret;
        }
    }

    public function loadBrandValue($website)
    {
        switch ($website) {
            case 'KeepSakeQuilting.com':
                $brand = 'brand_KQ';
                break;
            case 'PatternWorks.com':
                $brand = 'brand_PW';
                break;
            case 'CraftOfQuilting.com':
                $brand = 'brand_COQ';
                break;
            case 'KeepSakeNeedleArts.com':
                $brand = 'brand_KNA';
                break;
            case 'ShopAtSky.com':
                $brand = 'brand_skyandtel';
                break;
            case 'InterweaveStore.com':
                $brand = 'brand_IWStore';
        }
        $brand = (empty($brand)) ? false : $brand;

        return $brand;
    }

    private function _exactTargetSubmitOrder($postFieldsArray, $helper, $storeId)
    {
        //Load additional data
        $mid = $helper->getMid($storeId);
        $originalEmail = $postFieldsArray['original_email'];
        $websiteId = $postFieldsArray['website'];
        $exactTargetSoap = new ET2SoapSubscribe($mid);
        $businessList = array();

        if (!empty($postFieldsArray['orders']) && is_array($postFieldsArray['orders'])) {
            $orderArray = $postFieldsArray['orders'];
            $purchaseKey = $helper->getPurchaseKey($storeId);
            $purchaseDataArray = array();

            foreach ($orderArray as $oid) {

                //Load Order Mage Model
                $order = Mage::getSingleton('sales/order');
                if ($order->getId() != $oid) $order->reset()->load($oid);

                $email = $order->getCustomerEmail();

                //Null original email indicates user checked out as guest, so set original email to their guest checkout email
                if ($originalEmail == null) {
                    $originalEmail = $email;
                }

                $postFieldsArray['Email Address'] = $email;
                $orderNumber = $order->getIncrementId();
                $orderDate = $order->getCreatedAtStoreDate()->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);
		        $billingAddress = $order->getBillingAddress();
		
                $items = $order->getAllVisibleItems();

                foreach($items as $item) {

                    //Load product data
                    $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($item->getProductId());

                    //Load custom design attribute and strip the package prefix
                    $customDesign = substr($product->getCustomDesign(), ($pos = strpos($product->getCustomDesign(), '/')) !== false ? $pos + 1 : 0);

                    if($customDesign == null || !$helper->isValidDesign($customDesign)) {
                        $customDesign = $websiteId . ' - default';
                    } else {
                        $customDesign = $websiteId . ' - ' . $customDesign;
                        $businessList[$customDesign] = true;
                    }

                    $purchaseItem = new PurchaseItem();
                    $purchaseItem->sku = $item->getSku();
                    $purchaseItem->category = $customDesign;
                    $purchaseItem->orderNumber = $orderNumber;
                    $purchaseItem->email = $order->getCustomerEmail();
                    $purchaseItem->subscriberKey = $originalEmail;
                    $purchaseItem->qty = $item->getQtyOrdered();
                    $purchaseItem->mid = $mid[0];
                    $purchaseItem->dataExtensionKey = $purchaseKey;
                    $purchaseItem->pricePerItem = $item->getPrice();
                    $purchaseItem->purchaseAmount = ($item->getPrice() * $item->getQtyOrdered());
                    $purchaseItem->title = substr($item->getName(), 0, 50);
                    $purchaseItem->purchaseDate = $orderDate;
                    $purchaseItem->campaign = $postFieldsArray['campaign'];
                    //Set address data
                    $purchaseItem->state = $billingAddress->getRegionCode();
                    $purchaseItem->country = $billingAddress->getCountryId();
                    $purchaseItem->postalCode = $billingAddress->getPostcode();

                    $purchaseDataArray[] = $purchaseItem;
                }
            }

            $response = $exactTargetSoap->submitPurchaseData($purchaseDataArray);
        }
    }

    private function _exactTargetSubmitCampaign($postFieldsArray, $helper, $storeId)
    {
        //Load additional data
        $mid = $helper->getMid($storeId);
        $campaignDeKey = $helper->getCampaignKey($storeId);
        $websiteId = $postFieldsArray['website'];
        $exactTargetSoap = new ET2SoapSubscribe($mid);
        $originalEmail = $postFieldsArray['original_email'];

        //Load email if queue item is from order
        if (!empty($postFieldsArray['orders'])) {
            $orderArray = $postFieldsArray['orders'];
            $emailsArray = $this->loadEmailFromOrder($orderArray, $originalEmail);
            $originalEmail = $emailsArray['originalEmail'];
            $postFieldsArray['Email Address'] = $emailsArray['email'];
        }

        //Build Object
        $campaignData = new CampaignData();
        $campaignData->subscriberKey = (!empty($originalEmail)) ? $originalEmail : $postFieldsArray['Email Address'];
        $campaignData->email = $postFieldsArray['Email Address'];
        $campaignData->campaign = $postFieldsArray['campaign'];
        $campaignData->source = $postFieldsArray['source'];
        $campaignData->medium = $postFieldsArray['medium'];
        $campaignData->mid = $mid[0];
        $campaignData->website = $websiteId;
        $campaignData->dataExtensionKey = $campaignDeKey;

        $response = $exactTargetSoap->submitCampaignData($campaignData);
    }

    private function _exactTargetSubmitEmail($postFieldsArray, $helper, $storeId)
    {
        //Load additional data
        $mid = $helper->getMid($storeId);
        $listArray = $helper->getLists($storeId);
        $thankYouPage = $helper->getThankyouPage($storeId);
        $errorPage = $helper->getErrorPage($storeId);
        $communityDeKey = $helper->getCommunityKey($storeId);
        $masterDeKey = $helper->getMasterKey($storeId);
        $exactTargetSoap = new ET2SoapSubscribe($mid);
        $originalEmail = $postFieldsArray['original_email'];
        $mbu = $helper->isMbuEnabled($storeId);

        //Load email if queue item is from order
        if (!empty($postFieldsArray['orders'])) {
            $orderArray = $postFieldsArray['orders'];
            $emailsArray = $this->loadEmailFromOrder($orderArray, $originalEmail);
            $originalEmail = $emailsArray['originalEmail'];
            $postFieldsArray['Email Address'] = $emailsArray['email'];
            $postFieldsArray['firstname'] = $emailsArray['firstName'];
            $postFieldsArray['lastname'] = $emailsArray['lastName'];
        }

        //Load config data and lists for Multiple Business Units if we have IWS orders
        if (!empty($postFieldsArray['orders']) && !empty($mbu) && $storeId == 35) {
            $mbuConfig = $this->loadMbuConfig($postFieldsArray, $helper, $storeId);

            if (!empty($mbuConfig)) {
                //submit email for each business unit
                foreach ($mbuConfig as $config) {
                    $mbuListArray = array();
                    $mbuListArray[$config['mid']] = array_map('trim', explode(',', $config['lists']));
                    $mbuExactTargetSoap = new ET2SoapSubscribe(array($config['mid']));
                    $subscriberData = new SubscriberData();
                    $subscriberData->mid = array($config['mid']);
                    $subscriberData->subscriberKey = (!empty($originalEmail)) ? $originalEmail : $postFieldsArray['Email Address'];
                    $subscriberData->email = $postFieldsArray['Email Address'];
                    $subscriberData->firstName = (!empty($postFieldsArray['firstname'])) ? $postFieldsArray['firstname'] : "";
                    $subscriberData->lastName = (!empty($postFieldsArray['lastname'])) ? $postFieldsArray['lastname'] : "";
                    $subscriberData->emailSource = "Magento";
                    $subscriberData->communityDeKey = array($config['mid'] => $config['communityde']);
                    $subscriberData->masterDeKey = array($config['mid'] => $config['masterde']);
                    $subscriberData->thankYouPage = $thankYouPage;
                    $subscriberData->errorPage = $errorPage;
                    $subscriberData->lids = $mbuListArray;
                    $subscriberData->brand = (!empty($postFieldsArray['brand'])) ? $postFieldsArray['brand'] : null;

                    $subscriberResponse = $mbuExactTargetSoap->subAddUpdate($subscriberData);
                }
            }
        }

        //Build Object
        $subscriberData = new SubscriberData();
        $subscriberData->mid = $mid;
        $subscriberData->subscriberKey = (!empty($originalEmail)) ? $originalEmail : $postFieldsArray['Email Address'];
        $subscriberData->email = $postFieldsArray['Email Address'];
        $subscriberData->firstName = (!empty($postFieldsArray['firstname'])) ? $postFieldsArray['firstname'] : "";
        $subscriberData->lastName = (!empty($postFieldsArray['lastname'])) ? $postFieldsArray['lastname'] : "";
        $subscriberData->emailSource = "Magento";
        $subscriberData->communityDeKey = $communityDeKey;
        $subscriberData->masterDeKey = $masterDeKey;
        $subscriberData->thankYouPage = $thankYouPage;
        $subscriberData->errorPage = $errorPage;
        $subscriberData->lids = $listArray;
        $subscriberData->brand = (!empty($postFieldsArray['brand'])) ? $postFieldsArray['brand'] : null;

        $subscriberResponse = $exactTargetSoap->subAddUpdate($subscriberData);
    }

    private function _exactTargetSubmitZenith($postFieldsArray, $storeId)
    {
        //Load additional data
        $mid = array(Mage::helper('fw_zenithvip')->getZenithMid($storeId));
        $exactTargetSoap = new ET2SoapSubscribe($mid);
        $zenithDeKey = Mage::helper('fw_zenithvip')->getZenithDeKey($storeId);

        //Generate Zenith Coupon Code and set customer group/attributes
        $zenithObserver = Mage::getModel('fw_zenithvip/observer');
        $couponCode = $zenithObserver->generateCouponCode($storeId);
        if (empty($couponCode)) {
            throw new Exception("Failed to get coupon code from iTelescope API");
        }
        $zenithObserver->setCustomerExpiration($postFieldsArray['email'], $couponCode, $storeId);

        //Build Object
        $zenithData = new ZenithData();
        $zenithData->subscriberKey = !empty($postFieldsArray['original_email']) ? $postFieldsArray['original_email'] : $postFieldsArray['email'];
        $zenithData->email = $postFieldsArray['email'];
        $zenithData->couponCode = $couponCode;
        $zenithData->mid = $mid[0];
        $zenithData->dataExtensionKey = $zenithDeKey;

        $response = $exactTargetSoap->submitZenithData($zenithData);
    }

    public function loadMbuConfig($postFieldsArray, $helper, $storeId)
    {
        $orderArray = $postFieldsArray['orders'];
        foreach($orderArray as $oid) {
            //Load Order Mage Model
            $order = Mage::getSingleton('sales/order');
            if ($order->getId() != $oid) $order->reset()->load($oid);
            $items = $order->getAllVisibleItems();

            foreach($items as $item) {
                //Load product data
                $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($item->getProductId());

                //Load custom design attribute and strip the package prefix
                $customDesign = substr($product->getCustomDesign(), ($pos = strpos($product->getCustomDesign(), '/')) !== false ? $pos + 1 : 0);
                $businessList[$customDesign] = true;
            }
        }

        if (!empty($businessList)) {
            foreach($businessList as $bu => $bool) {
                if(!empty($bu) && $helper->isValidDesign($bu)) {
                    $mbuConfig = (empty($mbuConfig)) ? $helper->getMbuConfig($bu) : array_merge($mbuConfig, $helper->getMbuConfig($bu));
                }
            }
        }

        $ret = (!empty($mbuConfig)) ? $mbuConfig : false;
        return $ret;
    }

    public function setUtmCookie()
    {
        $utmNames = array('utm_campaign', 'utm_source', 'utm_medium');
        foreach ($utmNames as $utmName) {
            if (!empty($_GET[$utmName])) {
                $utmValue = $_GET[$utmName];
                setcookie($utmName, $utmValue, time() + (86400 * 60), '/', null, null, true);
            }
        }
    }

    public function checkQueueItems()
    {
        // Query filters
        $filterLast24Hrs = array('from' => strtotime('-1 day', time()), 'to' => time(),'datetime' => true);
        $filterCampaign = array('eq' => 'exacttarget_campaign');
        $filterEmail = array('eq' => 'exacttarget_email_from_order');
        $filterOrder = array('eq' => 'exacttarget_order');

        // Get orders from last 24 hours
        $orderItems = Mage::getModel('sales/order')
            ->getCollection()
            ->addFieldToFilter('created_at', $filterLast24Hrs);

        $missingQueues = array();

        // Foreach order find ET queue items with same Magento order ID, should be 3 queue items
        foreach ($orderItems as $order) {

            // Get order increment ID
            $incrementId = $order->getIncrementId();

            // Load queue items from last 24 hrs that match the codes and
            $queueItems = Mage::getModel('fw_queue/queue')
                ->getCollection()
                ->addFieldToFilter('created_at', $filterLast24Hrs)
                ->addFieldToFilter('short_description', array('like' => '%' . $incrementId))
                ->addFieldToFilter('code', array($filterCampaign, $filterEmail, $filterOrder))
                ->getData();

            // If we have missing queue items, add to array
            if (count($queueItems) !== 3) {
                $missingQueues[] = $incrementId;
            }
        }

        // If we have missing queue items, send email
        if (count($missingQueues) !== 0) {
            $subject = "Warning: Missing ET Queue Items";
            $email_message = "Orders with missing queue items:\r\n";
            foreach ($missingQueues as $queue) {
                $email_message .= $queue . "\r\n";
            }
            //Send email
            $this->sendEmail($subject, $email_message);
        }
    }

    public function sendEmail($subject, $body)
    {
        try {
            $mail = new Zend_Mail('utf-8');                          // Create the Zend_Mail object
            $mail->addTo('devteam@fwmedia.com');                     // Add recipients
            $mail->setSubject($subject)->setBodyText($body);         // Add subject and body
            $mail->setFrom("noreply@fwmedia.com");
            $mail->send();                                           // Send the email
        } catch (Exception $e) {                                     // Catch errors
            Mage::log($e, 'Error', Zend_Log::ERR);                   // Log errors
        }
    }
}
