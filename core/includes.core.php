<?php
	
	// ===========================================================================// 
	// ! Dependencies and helper classes 	                                      //
	// ===========================================================================//

	// Instance specific settings (created in bootstrap at boot)
	include('config/instance.config.php');

	// Environment settings and DB credentials
	include('config/environment.config.php');	
	
	// All of the settings required for all controllers
	include('config/settings.config.php'); 

	// Class for loading controllers
   	include('core/load.core.php');
	
	// Include all utility static functions
   	include('classes/utilities.class.php');
   	
	// Include redis class
	include('classes/redis.php'); 	
	
	// Include queue model
	include('models/queue.model.php'); 		   		
    
	// If notifo notifications are turned on
	if(NOTIFO)
	{
		// Include notifo api class
		include('classes/notifo_api.class.php');  
	}	
	
	// If Twilio notifications are turned on
	if(TWILIO)
	{
		// Include twilio api class
		include('classes/twilio_api.class.php');
	} 