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
	// ! General server settings                                                  //
	// ===========================================================================//		
	
	// Turn off php notices
	error_reporting(E_ALL ^ E_NOTICE);

	// Set system php memory limit to unlimited
	ini_set('memory_limit', '-1');	

	// Set php time limit to unlimited
	set_time_limit(0);	
			
	// ===========================================================================// 
	// ! Server location settings DEV                                             //
	// ===========================================================================//		

	if(defined("DEV"))
	{
		// The AWS elastic ip for the client server
		define('CLIENT_IP', '');

		// The AWS elastic ip for the job server
		define('JOB_SERVER_IP', '');

		// The AWS elastic ip for the worker 1 server
		define('WORKER_IP', '');	

		// The gluster shared data drive location
		define('DATA_SERVER', 'ec2-50-18-187-16.us-west-1.compute.amazonaws.com:/gluster-data'); 
	}	

	// ===========================================================================// 
	// ! Server location settings LIVE                                            //
	// ===========================================================================//		

	else
	{
		echo "everything is fine\n";
		die();
		// The AWS elastic ip for the client server
		define('CLIENT_IP', '50.18.104.82');

		// The AWS elastic ip for the job server
		define('JOB_SERVER_IP', '50.18.187.13');

		// The AWS elastic ip for the worker 1 server
		define('WORKER_IP', '50.18.180.36');	

		// The gluster shared data drive location
		define('DATA_SERVER', 'ec2-50-18-187-16.us-west-1.compute.amazonaws.com:/gluster-data'); 	
	}	

	// ===========================================================================// 
	// ! Directory settings                                                       //
	// ===========================================================================//	

	// The gluster shared data drive location
	define('DATA_DIRECTORY', '/home/ec2-user/data/gluster'); 
	
	// Folder used for status files
	define("STATUS_DIRECTORY", "data/gluster/status/");	   

	// Folder used for status files
	define("SYSTEM_STATUS", "data/gluster/status/system.log");			

	// ===========================================================================// 
	// ! SERPS Database credentials                                                     //
	// ===========================================================================//
	
	//Database host
	define("DB_HOST", "serps.c7mnew97kkqx.us-west-1.rds.amazonaws.com");

	// Database username
	define("DB_SERP_USER", "seserps");

	// Database password
	define("DB_SERPS_PASS", "234k3k3LSJapBbr");

	// Database name
	define("DB_NAME_SERPS", "serps"); 
	
	//---LOCAL
	
	// define("DB_HOST", ":/Applications/MAMP/tmp/mysql/mysql.sock");

	// // Database username
	// define("DB_SERP_USER", "root");

	// // Database password
	// define("DB_SERPS_PASS", "root");

	// // Database name
	// define("DB_NAME_SERPS", "serps"); 		

	// ===========================================================================// 
	// ! Proxy Database credentials                                               //
	// ===========================================================================//          	  

	// Proxy Database host
	define("PROXY_HOST", "proxies.c7mnew97kkqx.us-west-1.rds.amazonaws.com");

	// Proxy Database username
	define("PROXY_USER", "seproxies");

	// Proxy Database password
	define("PROXY_PASS", "lskLPQVksidu34");

	// Proxy Database name
	define("PROXY_DB", "proxies"); 		
?>