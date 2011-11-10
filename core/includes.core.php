<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** require_onceS - A single file to manage all of the app require_onces
// ** 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-17
// ** @access	private
// ** @param	
// ** @return	Main controller router     
//  	
// ***************************************************************************//

// ********************************** START **********************************// 

// ===========================================================================// 
// ! Main core file to load          	                                      //
// ===========================================================================//	

// require_once required core file
require_once("core/".CORE.".core.php");

// ===========================================================================// 
// ! Configuration files             	                                      //
// ======================n====================================================//
	
// All of the settings required for all controllers
require_once('config/settings.config.php'); 

// Only include files after bootstrapping 
if(defined("UPDATED") || CORE != "bootstrap")
{
	// Instance specific settings (created in bootstrap at boot)
	require_once('config/instance.config.php');		
			
	// Environment settings and DB credentials
	require_once('config/environment.config.php');	
}		

// If this is a worker instance
if(CORE == "worker")
{
	// require_once worker settings
	require_once('config/scraping.config.php');												
}

// ===========================================================================// 
// ! Required classes                	                                      //
// ===========================================================================//

// require_once all utility static functions
require_once('classes/utilities.class.php');
	
// require_once redis class
require_once('classes/redis.class.php'); 

// If this is a worker instance
if(CORE == "worker")
{
	// require_once worker controller
	require_once('classes/worker.class.php');	

	// require_once serp parsing class
	require_once('classes/parse.class.php');

	// require_once scraping class
	require_once('classes/scrape.class.php'); 													
}

// If notifo notifications are turned on
if(NOTIFO)
{
	// require_once notifo api class
	require_once('classes/notifo.class.php');  
}	

// If Twilio notifications are turned on
if(TWILIO)
{
	// require_once twilio api class
	require_once('classes/twilio.class.php');
} 

// ===========================================================================// 
// ! Required data models              	                                      //
// ===========================================================================//

// require_once queue model
require_once('models/queue.model.php'); 

// If this is a worker instance
if(CORE == "worker" )
{
	// require_once proxy data model
	require_once('models/proxies.model.php');
	
	// require_once domains data model
	require_once('models/domains.model.php');	
	
	// require_once keywords data model
	require_once('models/keywords.model.php');													
}

// ********************************** END **********************************// 
	