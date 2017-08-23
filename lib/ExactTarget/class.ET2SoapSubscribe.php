<?php
include_once('class.ISubscribeBehavior.php');
include_once('class.SubscriberResponse.php');
include_once('exacttarget_soap_client.php');

class ET2SoapSubscribe implements ISubscribeBehavior {
	
	var $username;
	var $password;
	var $client;
	var $mid;
	var $midClient;
	var $wsdl;
	
	public function __construct($mid=null) {
        $this->username = "sitesupport2@fwmedia.com";
        $this->password = "SiteSupport2!";
        
        $wsdl = $this->getWsdl();
		$this->client = new ExactTargetSoapClient($wsdl, array('trace'=>1));
		
		$this->client->username = $this->username;
		$this->client->password = $this->password;
		
		if($mid != null) {
			$this->setMid($mid);
		}            
	}
	
	public function setMid($mid) {

		$this->mid = (!is_array($mid)) ? array($mid) : $mid;

		foreach($this->mid as $midValue) {
			$this->midClient[$midValue] = new ExactTarget_ClientID();
			$this->midClient[$midValue]->ID = $midValue;
		}
	}

	public function getMidClientArray() {
		return $this->midClient;
	}

	public function getMidClient($mid=null) {
		$ret = null;
		if($mid == null || !isset($this->midClient[$mid])) {
			$ret = reset($this->midClient);
		} else {
			$ret = $this->midClient[$mid];
		}
		return $ret;
	}

	public function setUsername($username) {
		$this->username = $username;
	}
	
	public function setPassword($password) {
		$this->password = $password;
	}
	
	public function setCredentials($username, $password) {
		$this->username = $username;
		$this->password = $password;
	}
	
	public function setWsdl($wsdl="https://webservice.s4.exacttarget.com/etframework.wsdl") {
		$this->wsdl = $wsdl;
	}
	
	public function getWsdl() {
		if($this->wsdl == null) {
        	$this->wsdl = "https://webservice.s4.exacttarget.com/etframework.wsdl";
		}
		return $this->wsdl;
	}

	//returns page to be redirected to.
	public function subAddUpdate($subscriberData) {

		//$wsdl = $this->getWsdl();
		//Set the MID client
		$this->setMid($subscriberData->mid);
		
		//Account Info
		$email        = $subscriberData->email;
		$thankYouPage = $subscriberData->thankYouPage;
		$errorPage    = $subscriberData->errorPage;

		try {
			$sendWelcomeEmail = false;
			//Check if email exists. If not, add to master DE.

			$objects = array();
            foreach ($subscriberData->mid as $mid) {

                $communityDe = new ExactTarget_DataExtensionObject();
                $communityDeKey = $subscriberData->getCommunityDeKey($mid);
                $communityDe->CustomerKey = $communityDeKey; //external key/unique identifier for the data extension

                //Need to keep initiating a new object here as ET service doesn't like the same object instance used in other requests.
                $midClient = new ExactTarget_ClientID();
                $midClient->ID = $mid;
                $communityDe->Client = $midClient;

                $communityDe->Properties = array();
                /*% ExactTarget_APIProperty */
                $subscriberKeyProperty = new ExactTarget_APIProperty();
                $subscriberKeyProperty->Name = "SubscriberKey"; // name of DE field
                $subscriberKeyProperty->Value = $subscriberData->subscriberKey; // value for DE field

                /*% ExactTarget_APIProperty */
                $emailProperty = new ExactTarget_APIProperty();
                $emailProperty->Name = "EmailAddress"; // name of DE field
                $emailProperty->Value = $email; // value for DE field

                $customDataET2 = $subscriberData->customDataET2;


                if (is_array($customDataET2)) {
                    foreach ($customDataET2 as $k => $v) {
                        $property = new ExactTarget_APIProperty();
                        $property->Name = stripslashes($k);
                        $property->Value = stripslashes($v);
                        $communityDe->Properties[] = $property;
                    }
                }

                $communityDe->Properties[] = $emailProperty;
                $communityDe->Properties[] = $subscriberKeyProperty;

                $objects[] = new SoapVar($communityDe, SOAP_ENC_OBJECT, 'DataExtensionObject', "http://exacttarget.com/wsdl/partnerAPI");

            }


            /*% Create the ExactTarget_SaveOption Object */ 
            $saveOption = new ExactTarget_SaveOption();                
            $saveOption->PropertyName="DataExtensionObject";
            $saveOption->SaveAction=ExactTarget_SaveAction::UpdateAdd; // set the SaveAction to add/update

            // Apply options and object to request and perform update of data extension
            $updateOptions = new ExactTarget_UpdateOptions();
            $updateOptions->SaveOptions[] = new SoapVar($saveOption, SOAP_ENC_OBJECT, 'SaveOption', "http://exacttarget.com/wsdl/partnerAPI");
			$request = new stdClass();
            $request->Options = new SoapVar($updateOptions, SOAP_ENC_OBJECT, 'UpdateOptions', "http://exacttarget.com/wsdl/partnerAPI");
            $request = new ExactTarget_CreateRequest();
            $request->Options = $updateOptions;
            $request->Objects = $objects;
            
            $results = $this->client->Update($request);

	        $ret = new SubscriberResponse();

	        if($results->OverallStatus == "OK") {
	            $ret->error = false;
	            $ret->sendWelcomeEmail = $sendWelcomeEmail;
	            $ret->errorString = "";
	            $ret->redirectLocation = $thankYouPage;
	        }
			else {
				$ret->error = true;
				if(is_array($results->Results)) {
                    $result = $results->Results[0];
					if (property_exists($result, 'ValueErrors')) {
						if (is_array($result->ValueErrors->ValueError)) {
							$valueError = $result->ValueErrors->ValueError[0];
							$ret->errorCode = $valueError->ErrorCode;
							$ret->errorString = $valueError->ErrorMessage;
						} else {
							$ret->errorCode = $result->ValueErrors->ValueError->ErrorCode;
							$ret->errorString = $result->ValueErrors->ValueError->ErrorMessage;
						}
					} else {
						if (property_exists($result, 'KeyErrors')) {
							$ret->errorCode = $result->KeyErrors->KeyError->ErrorCode;
							$ret->errorString = $result->KeyErrors->KeyError->ErrorMessage;
						} else {
							$ret->errorCode = $result->ErrorCode;
							$ret->errorString = $result->ErrorMessage;
						}
					}
				}
				else {
					$result = $results->Results;
					if (property_exists($result, 'ValueErrors')) {
						$ret->errorCode = $result->ValueErrors->ValueError->ErrorCode;
						$ret->errorString = $result->ValueErrors->ValueError->ErrorMessage;
					} else if (property_exists($results->Results, 'ErrorCode')) {
						$ret->errorCode = $result->ErrorCode;
						$ret->errorString = $result->ErrorMessage;
					} else {
						$ret->errorCode = 0;
						$ret->errorString = $result->StatusMessage;
					}
				}

				$ret->redirectLocation = $errorPage;
                //Check if error code is for invalid email
                if($ret->errorCode == 70006) {
                    $ret->invalidEmail = true;
                }
			}

           return $ret;

	        
		} catch (SoapFault $e) {
            $ret = new SubscriberResponse();
            $ret->error = true;
            $ret->errorString = "SOAP Fault Exception: " . $e->getMessage();
            $ret->redirectLocation = $errorPage;
            return $ret;
		}  		
	}

	public function sendWelcomeEmail($emailAddress, $emailId) {

	}
	
}
