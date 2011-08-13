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
	// ! Set current environment                                                  //
	// ===========================================================================//  

	if(strpos($argv[0], 'Applications'))
	{
		define('ENVIRONMENT', 'local');
	}   
	else
	{
		define('ENVIRONMENT', 'ec2');	
	} 
	
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
	// ! Server location settings                                                 //
	// ===========================================================================//		

	// The AWS elastic ip for the client server
	define('CLIENT_IP', '50.18.104.82');

	// The AWS elastic ip for the job server
	define('JOB_SERVER_IP', '50.18.187.13');

	// The AWS elastic ip for the worker 1 server
	define('WORKER_IP', '50.18.188.157');	

	// The gluster shared data drive location
	define('DATA_SERVER', 'ec2-50-18-187-16.us-west-1.compute.amazonaws.com:/gluster-data'); 

	// The gluster shared data drive location
	define('DATA_DIRECTORY', '/home/ec2-user/data'); 	

	// ===========================================================================// 
	// ! Database credentials                                                     //
	// ===========================================================================//
	
	if(ENVIRONMENT == 'local')
	{
		// Database host
		define("DB_HOST", ":/Applications/MAMP/tmp/mysql/mysql.sock");
	
		// Database username
		define("DB_SERP_USER", "root");
	
		// Database password
		define("DB_SERPS_PASS", "root");
	
		// Database name
		define("DB_NAME_SERPS", "mongo"); 
    }  
    elseif(ENVIRONMENT == 'ec2')
    {
		// Database host
		define("DB_HOST", "dbmaster.c7mnew97kkqx.us-west-1.rds.amazonaws.com");
	
		// Database username
		define("DB_SERP_USER", "scout");
	
		// Database password
		define("DB_SERPS_PASS", "tF1xFmMu");
	
		// Database name
		define("DB_NAME_SERPS", "serps"); 	
    } 

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