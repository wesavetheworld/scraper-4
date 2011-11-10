<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** SETTINGS - All of the settings that are generally constant and only 
// ** need to be changed if relocating server or changing database structure
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-04-22
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//   

// ===========================================================================// 
// ! General settings                                                         //
// ===========================================================================//	  

// Set default php timezone
date_default_timezone_set('UTC'); 

// Report errors to admin (utilities function)
define('REPORT_ERRORS', TRUE);

// Display benchmarking stats in output of script
define("BENCHMARK", TRUE);
                         
// Display helpful notation in output of script
define('NOTATION', TRUE);  

// Folder used for logs
define("LOG_DIRECTORY", "logs/"); 

// Turn off php notices
error_reporting(E_ALL ^ E_NOTICE);

// Set system php memory limit to unlimited
ini_set('memory_limit', '-1');	

// Set php time limit to unlimited
set_time_limit(0);	

// ===========================================================================// 
// ! Worker settings                                                         //
// ===========================================================================//

// Set the data model to be used	
define("SOURCE", $_SERVER['argv'][2]);

// If worker number is non-zero
if($_SERVER['argv'][3])
{			
	// Set worker id for the job queue	
	define("WORKER_ID", $_SERVER['argv'][3]);
}	
// If worker id is "0"
else
{
	define("WORKER_ID", "0");
}		

// ===========================================================================// 
// ! Notifo API                                                               //
// ===========================================================================//	
                       
// Should errors be sent using notifo
define("NOTIFO", TRUE);

// API username
define("NOTIFO_API_USERNAME", "sescout_errors");

// API secret
define("NOTIFO_API_SECRET", "91d2418e11bfb939c9c45325634c3708a8dc4e58");

// API secret
define("NOTIFO_NOTIFY_USERNAME", "sescout");

// ===========================================================================// 
// ! Twilio API                                                               //
// ===========================================================================//

// Should errors be sent using Twilio
define("TWILIO", FALSE);

// API version
// define("TWILIO_API_VERSION", "2010-04-01");
// 
// // API number or sandbox test number
// define("TWILIO_API_NUMBER", "415-599-2671");
// 
// // API username
// define("TWILIO_API_ACCOUNT_SID", "AC9d8bbb613f0cc4ee15278e85c9593deb");
// 
// // API secret
// define("TWILIO_API_AUTH_TOKEN", "3ec65631ae3f4d89ce7e5be22088d5e3");
// 
// // API text mobile numbers
// define("TWILIO_API_NOTIFY_MOBILE", serialize(array("6263941441")));	 

// ********************************** END **********************************// 
