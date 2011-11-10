<?php if(!defined('ROUTER')) exit("Go away. Pass commands through router.php\n");

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** ENVIRONMENT - All environment settings like server location and credentials
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

// Define core file to use to use
define("CORE", $_SERVER['argv'][1]);	

// If no core provided, bitch
if(!CORE)
{
	echo "No core specified\n";
}
		
// ===========================================================================// 
// ! Boss server settings                                                     //
// ===========================================================================//	

// If development environment
if(defined("DEV"))
{
	// The AWS elastic ip for the client server
	define('BOSS_IP', '184.72.45.180');

	// Redis listening port
	define("BOSS_PORT", 6379);

	// Redis boss database number
	define("BOSS_DB", 0);		

	// The AWS elastic ip for the worker 1 server
	define('WORKER_IP', '184.72.55.252');	
}	
// else production environment
else
{
	// The AWS elastic ip for the client server
	define('BOSS_IP', '');

	// Redis listening port
	define("BOSS_PORT", 6379);

	// Redis boss database number
	define("BOSS_DB", 0);			

	// The AWS elastic ip for the worker 1 server
	define('WORKER_IP', '50.18.180.36');		
}					

// ===========================================================================// 
// ! SERPS Database credentials                                               //
// ===========================================================================//

// Ip of the redis database instance 
define("REDIS_SERPS_IP", "50.18.172.44");

// Redis listening port
define("REDIS_SERPS_PORT", 6379);

// Redis Proxy database number
define("REDIS_SERPS_DB", 0);

// If development environment
if(defined("DEV"))
{	
	// Database host
	define("DB_HOST", "serps.c7mnew97kkqx.us-west-1.rds.amazonaws.com");

	// Database username
	define("DB_SERP_USER", "seserps");

	// Database password
	define("DB_SERPS_PASS", "234k3k3LSJapBbr");

	// Database name
	define("DB_NAME_SERPS", "serps"); 
}	
// else production environment
else
{
	// Database host
	define("DB_HOST", "serps.c7mnew97kkqx.us-west-1.rds.amazonaws.com");

	// Database username
	define("DB_SERP_USER", "seserps");

	// Database password
	define("DB_SERPS_PASS", "234k3k3LSJapBbr");

	// Database name
	define("DB_NAME_SERPS", "serps"); 		
}	

	// Database host
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

// Ip of the redis database instance 
define("REDIS_PROXY_IP", "50.18.170.228");

// Redis listening port
define("REDIS_PROXY_PORT", 6379);

// Redis Proxy database number
define("REDIS_PROXY_DB", 0);	

// If development environment
if(defined("DEV"))
{	
	// Proxy Database host
	define("PROXY_HOST", "proxies.c7mnew97kkqx.us-west-1.rds.amazonaws.com");

	// Proxy Database username
	define("PROXY_USER", "seproxies");

	// Proxy Database password
	define("PROXY_PASS", "lskLPQVksidu34");

	// Proxy Database name
	define("PROXY_DB", "proxies"); 	
}	
// else production environment
else
{
	// Proxy Database host
	define("PROXY_HOST", "proxies.c7mnew97kkqx.us-west-1.rds.amazonaws.com");

	// Proxy Database username
	define("PROXY_USER", "seproxies");

	// Proxy Database password
	define("PROXY_PASS", "lskLPQVksidu34");

	// Proxy Database name
	define("PROXY_DB", "proxies"); 			
}

// ********************************** END **********************************// 			
