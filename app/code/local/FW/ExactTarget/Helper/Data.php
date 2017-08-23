<?php
/**
 * @category    FW
 * @package     FW_ExactTarget
 * @copyright   Copyright (c) 2015 F+W, Inc. (http://www.fwmedia.com)
 */
class FW_ExactTarget_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Config path for using throughout the code
     * @var string $XML_PATH
     */
    const DATAFEED_XML_PATH = 'thirdparty/exacttarget_datafeed/';

    /**
     * Whether ET is enabled
     * @param mixed $store
     * @return bool
     */
    public function isExactTargetEnabled($store = null)
    {
        return Mage::getStoreConfig('thirdparty/exacttarget/active', $store);
    }

    /**
     * Get the MID
     * @param mixed $store
     * @return string
     */
    public function getMid($store = null)
    {
        $mid = Mage::getStoreConfig('thirdparty/exacttarget/mid', $store);
        return ($mid != null) ? array($mid) : null;
    }

    public function getMasterKey($store = null) {
        $mid = Mage::getStoreConfig('thirdparty/exacttarget/mid', $store);
        if($mid != null) {
            $masterKey = Mage::getStoreConfig('thirdparty/exacttarget/master_de_key', $store);
        }
        return ($masterKey) ? array($mid => $masterKey) : null;
    }

    public function getPurchaseKey($store = null) {
        $mid = Mage::getStoreConfig('thirdparty/exacttarget/mid', $store);
        if($mid != null) {
            $purchaseKey = Mage::getStoreConfig('thirdparty/exacttarget/purchase_de_key', $store);
        }
        return $purchaseKey;
        //return ($purchaseKey) ? array($mid => $purchaseKey) : null;
    }

    public function getCommunityKey($store = null) {
        $mid = Mage::getStoreConfig('thirdparty/exacttarget/mid', $store);
        if($mid != null) {
            $communityKey = Mage::getStoreConfig('thirdparty/exacttarget/community_de_key', $store);
        }
        return ($communityKey) ? array($mid => $communityKey) : null;
    }

    public function getCampaignKey($store = null) {
        $mid = Mage::getStoreConfig('thirdparty/exacttarget/mid', $store);
        if($mid != null) {
            $campaignKey = Mage::getStoreConfig('thirdparty/exacttarget/campaign_de_key', $store);
        }
        return $campaignKey;
    }

    public function getUtmCampaign() {
        $utmNames   = array('utm_campaign', 'utm_source', 'utm_medium');
        $utmValues  = array();
        foreach ($utmNames as $utmName) {
            $utmValues[$utmName] = (!empty($_COOKIE[$utmName])) ? $_COOKIE[$utmName] : 'direct';
        }
        return $utmValues;
    }

    public function getLists($store=null) {
        $ret = null;
        $mid = Mage::getStoreConfig('thirdparty/exacttarget/mid', $store);

        if($mid != null) {
            $lists = Mage::getStoreConfig('thirdparty/exacttarget/lists', $store);
            if($lists != null) {
                $listArray = explode(',', $lists);
                foreach ($listArray as $key => $lid) {
                    $listArray[$key] = trim($lid);
                }
                $ret[$mid] = $listArray;
            }
        }
        return $ret;
    }

    public function getThankyouPage($store=null) {
        return Mage::getStoreConfig('thirdparty/exacttarget/thankyou_page', $store);
    }

    public function getErrorPage($store=null) {
        return Mage::getStoreConfig('thirdparty/exacttarget/error_page', $store);
    }

    public function getAvailableDesigns() {
        return array(
            'beading',
            'crochet',
            'jewelrymaking',
            'knitting',
            'mixedmedia',
            'needlework',
            'quilting',
            'sewing',
            'spinning',
            'weaving',
        );
    }

    public function isValidDesign($design) {
        $designs = $this->getAvailableDesigns();
        return in_array($design, $designs);
    }

    /**
     * Whether ET is ready to use
     * @param mixed $store
     * @return bool
     */
    public function isExactTargetAvailable($store = null)
    {
        $enabled = $this->isExactTargetEnabled($store);

        $mid = $this->getMid($store);
        $communityKey = $this->getCommunityKey($store);
        $masterKey = $this->getMasterKey($store);
        $purchaseKey = $this->getPurchaseKey($store);
        $campaignKey = $this->getCampaignKey($store);
        $lists = $this->getLists($store);

        //All config values must be populated before ET is enabled
        $ret = ($enabled && $mid && $masterKey && $communityKey && $lists && $purchaseKey && $campaignKey);

        return $ret;
    }

    public function isMbuEnabled()
    {
        $enabled = Mage::getStoreConfig('thirdparty/exacttarget_mbu/active');
        return $enabled;
    }

    public function getMbuConfig($businessUnit)
    {
        $buConfig = Mage::getStoreConfig('thirdparty/exacttarget_mbu');
        $buConfig = unserialize($buConfig[$businessUnit.'_lists']);

        $ret = (is_array($buConfig)) ? $buConfig : false;
        return $ret;
    }

    /**
     * Get the FTP Host
     * @param mixed $store
     * @return string
     */
    public function getFtpHost($store = null)
    {
        return Mage::getStoreConfig(self::DATAFEED_XML_PATH.'ftp_host', $store);
    }

    /**
     * Get the FTP User
     * @param mixed $store
     * @return string
     */
    public function getFtpUser($store = null)
    {
        return Mage::getStoreConfig(self::DATAFEED_XML_PATH.'ftp_user', $store);
    }

    /**
     * Get the FTP Password
     * @param mixed $store
     * @return string
     */
    public function getFtpPassword($store = null)
    {
        return Mage::getStoreConfig(self::DATAFEED_XML_PATH.'ftp_password', $store);
    }

    /**
     * Get the FTP Location
     * @param mixed $store
     * @return string
     */
    public function getFtpLocation($store = null)
    {
        return Mage::getStoreConfig(self::DATAFEED_XML_PATH.'ftp_location', $store);
    }

    /**
     * Get the Image CDN Address
     * @param mixed $store
     * @return string
     */

    public function getImageCdnAddress($store = null)
    {
        return Mage::getStoreConfig(self::DATAFEED_XML_PATH.'image_cdn_address', $store);
    }

    /**
     * Get the Email Notice Address(s)
     * @param mixed $store
     * @return string
     */
    public function getEmailNotice($store = null)
    {
        return Mage::getStoreConfig(self::DATAFEED_XML_PATH.'emailnotice', $store);
    }
}
