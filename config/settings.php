<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

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
	
	// Used to identify this instance from others
	define("INSTANCE", date("i", time()));     
	
	// Report errors to admin (utilities function)
	define('REPORT_ERRORS', TRUE);
	
	// Display benchmarking stats in output of script
	define("BENCHMARK", TRUE);
	                         
	// Display helpful notation in output of script
	define('NOTATION', TRUE);  
	
	// cURL connection timout limit
	define('CURL_TIMEOUT', 5);  
	
	// Folder used for logs
	define("LOG_DIRECTORY", "data/logs/"); 
	
	// Folder used for status files
	define("STATUS_DIRECTORY", "data/status/");	   
	
	// ===========================================================================// 
	// ! Time and Date constants                                                  //
	// ===========================================================================//	
	
	// Todays date
	define("DATE_TODAY", date("Y-m-d"));
	
	// Tomorrow's date
	define("DATE_YESTERDAY", date("Y-m-d", time()-86400));
	
	// Current time
	define("TIME_CURRENT", date("H:i"));
	
	// Saved file time
	define("TIME_SAVED_FILE", date("G")); 

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
?>