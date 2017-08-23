<?php
require_once("class.ISubscribeBehavior.php");
require_once("class.ETCurlSubscribe.php");
require_once("class.ETSoapSubscribe.php");
require_once("class.ET2SoapSubscribe.php");
require_once("class.StrongMailSubscribe.php");
include_once('class.YesMailSubscribe.php');

class SubscribeBehaviorFactory {
	static function getSubscribeBehavior($behaviorString, $mid=null, $testMode=false) {
		switch($behaviorString) {
			case 'exactTargetCurl':
                return new ETCurlSubscribe();
                break;
			
			case 'exactTargetSoap':
                return new ETSoapSubscribe();
                break;
			
            case 'exactTarget2Soap':
                return new ET2SoapSubscribe($mid);
                break;
                            
			case 'localDatabase':
                return new LocalSubscribe();
                break;

			default:
                return new ET2SoapSubscribe();
                break;
		}
	}
}
?>