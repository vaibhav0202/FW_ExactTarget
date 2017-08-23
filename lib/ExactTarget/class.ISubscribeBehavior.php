<?php
interface ISubscribeBehavior {
	//Adds or updates a user to system with data array.
	public function subAddUpdate($subscriberData);
	public function sendWelcomeEmail($emailAddress, $emailId);
}
?>