<?php

	/*
	*	Subscriber.php version 1.0
	*
	*	This file contain a class for rewrite the Mage_Newsletter_Model_Subscriber
	*	functions ( sendConfirmationSuccessEmail() and sendUnsubscriptionEmail() ).
	*	With this, if the module is activated, the newsletter module not sent
	*	success email when customer is subscribed and unsubscribed.
	*
	*
	*	Copyright (c) 2011 Facon Solutions
	*
	*	Authors:	Paul Marclay (paul@xagax.com)
	*
	*/
 
class FW_ExactTarget_Model_Subscriber extends Mage_Newsletter_Model_Subscriber
{
    public function sendConfirmationSuccessEmail()
    {
       	return $this;
    }

    public function sendUnsubscriptionEmail()
    {
       	return $this;
    }	
}
