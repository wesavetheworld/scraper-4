<?php if(!defined('ROUTER')) exit("Go away. Pass commands through router.php\n");

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** INCLUDES - A single file to manage all of the app includes
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
// ! Configuration files             	                                      //
// ===========================================================================//

// Instance specific settings (created in bootstrap at boot)
include('config/instance.config.php');

// Environment settings and DB credentials
include('config/environment.config.php');	

// All of the settings required for all controllers
include('config/settings.config.php'); 

// If this is a worker instance
if(CORE == "worker")
{
	// Include worker settings
	include('config/scraping.config.php');												
}

// ===========================================================================// 
// ! Required classes                	                                      //
// ===========================================================================//

// Include all utility static functions
include('classes/utilities.class.php');
	
// Include redis class
include('classes/redis.class.php'); 

// If this is a worker instance
if(CORE == "worker")
{
	// Include worker controller
	include('classes/worker.class.php');	

	// Include serp parsing class
	include('classes/parse.class.php');

	// Include scraping class
	include('classes/scrape.class.php'); 													
}

// If notifo notifications are turned on
if(NOTIFO)
{
	// Include notifo api class
	include('classes/notifo.class.php');  
}	

// If Twilio notifications are turned on
if(TWILIO)
{
	// Include twilio api class
	include('classes/twilio.class.php');
} 

// ===========================================================================// 
// ! Required data models              	                                      //
// ===========================================================================//

// Include queue model
include('models/queue.model.php'); 

// If this is a worker instance
if(CORE == "worker" )
{
	// Include proxy data model
	include('models/proxies.model.php');
	
	// Include domains data model
	include('models/domains.model.php');	
	
	// Include keywords data model
	include('models/keywords.model.php');													
}

// ===========================================================================// 
// ! Main core file to load          	                                      //
// ===========================================================================//	

// Include required core file
include("core/".CORE.".core.php");

// ********************************** END **********************************// 
	