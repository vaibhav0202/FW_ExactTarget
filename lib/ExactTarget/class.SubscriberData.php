<?php
 class SubscriberData {
     var $id;
	 var $subscriberKey;
 	 var $firstName;
 	 var $lastName;
 	 var $address;
 	 var $email;
 	 var $phone;
	 var $lids;
	 var $lidRemoval;
	 var $mid;
	 var $thankYouPage;
	 var $errorPage;
	 var $customData;    //When using ET 2.0, custom data will be populated in the Data Extension "List" attribute.
	 var $customDataET2; //ET 2.0 Fields where the value won't be populated in the Data Extension "List" attribute.
	 var $url;
	 var $masterDeKey; //ET 2.0 Master Data Extension external key
 	 var $communityDeKey; //ET 2.0 Community Data Extension external key
	 var $emailSource; //Added for ET 2.0
	 var $brand;

	 public function getMidString()
	 {
		 $ret = $this->mid;
		 if (is_array($this->mid)) {
			 $ret = implode(',', $this->mid);
		 }
		 return trim($ret);
	 }

	 public function getMid() {
		 $ret = $this->mid;
		 if(!is_array($ret)) {
			 $ret = explode(",", $ret);
		 }
		 return $ret;
	 }

	 public static function buildFromObj($obj) {
	 	 $ret = new SubscriberData();
         $ret->id = ($obj->id) ? $obj->id : null;
		 $ret->subscriberKey = ($obj->subscriberKey) ? $obj->subscriberKey : $obj->email;
		 $ret->firstName = $obj->firstName;
		 $ret->lastName = $obj->lastName;
		 $ret->email = $obj->email;
		 $ret->address = new Address();
		 $ret->address->address1 = $obj->address->address1;
		 $ret->address->address2 = $obj->address->address2;
		 $ret->address->postalCode = $obj->address->postalCode;
		 $ret->address->city = $obj->address->city;
		 $ret->address->state = $obj->address->state;
		 $ret->address->country = $obj->address->country;
		 $ret->phone = $obj->phone;
		 $ret->lids = (array) $obj->lids;
		 $ret->mid = (array) $obj->mid;
		 $ret->thankYouPage = $obj->thankYouPage;
		 $ret->errorPage = $obj->errorPage;

		 $tmpEt2Data = (array) $obj->customDataET2;
		 $tmpCustomData = array();

		 foreach($tmpEt2Data as $mid=>$tmpObj) {
			 $tmpCustomData[$mid] = (array) $tmpObj;
		 }

		 $ret->customDataET2 = $tmpCustomData;

		 $ret->url = $obj->url;
		 $ret->masterDeKey = (array) $obj->masterDeKey;
		 $ret->communityDeKey = (array) $obj->communityDeKey;
		 $ret->emailSource = $obj->emailSource;

		 return $ret;
	 }

     /**
      * @param $mid - ExactTarget MID
      * @param $array - Array with MID's as key, where value is based off the property you need
      * @return mixed
      * Pass it an associative array that uses MID's as a key, and return the value of the array that has the MID key.
      * Reason for this is, MID's are int's, and you can't access the element directly, using the MID if the array isn't back filled
      * by int's
      */
     public function getMidProperty($mid, $array) {
         $ret = null;
         if(is_array($array)) {
             foreach($array as $k=>$v) {
                 if($k == $mid) {
                     $ret = $v;
                     break;
                 }
             }
         }
         return $ret;
     }

     //Send getMidProperty function the communityDeKey array to retrieve the key for the community data extension
     public function getCommunityDeKey($mid) {
         return $this->getMidProperty($mid, $this->communityDeKey);
     }

     //Send getMidProperty function the masterDeKey array to retrieve the key for the master data extension
     public function getMasterDeKey($mid) {
         return $this->getMidProperty($mid, $this->masterDeKey);
     }

     //Send getMidProperty function the customDataET2 array to retrieve the key for the custom data
     public function getCustomDataET2($mid) {
         return $this->getMidProperty($mid, $this->customDataET2);
     }

     //Send getMidProperty function the lids array to retrieve the LID's for the specified MID
     public function getLidArray($mid) {
         return $this->getMidProperty($mid, $this->lids);
     }
 }