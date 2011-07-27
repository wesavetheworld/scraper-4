<?php
	
	// ===========================================================================// 
	// ! Basic server setup               	                                      //
	// ===========================================================================//

	// Checked for in all othere files to prevent direct access   
	define('HUB', TRUE);

	// Get passed server arguments
	$argv = $_SERVER['argv'];	

   	// Set working directory for correct file includes etc 
	chdir(dirname($argv[0]));	
	
	// ===========================================================================// 
	// ! Dependencies and helper classes 	                                      //
	// ===========================================================================//

	// Instance specific settings (created in bootstrap at boot)
	include('config/instance.config.php');

	// Environment settings and DB credentials
	include('config/environment.config.php');	
	
	// All of the settings required for all controllers
	include('config/settings.config.php'); 

	// Class for routing controllers
   	include('core/router.core.php');

	// Class for configuring server
   	include('core/bootstrap.core.php');
	
	// Include all utility static functions
   	include('classes/utilities.class.php');	   		
    
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