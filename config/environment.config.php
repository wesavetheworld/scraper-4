<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");

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

	// The AWS elastic ip for the google 1 server
	define('GOOGLE_IP', '184.72.55.252');	
}	
// else production environment
else
{
	// The AWS elastic ip for the client server
	define('BOSS_IP', '50.18.180.36');

	// Redis listening port
	define("BOSS_PORT", 6379);

	// Redis boss database number
	define("BOSS_DB", 0);			

	// The AWS elastic ip for the google 1 server
	define('GOOGLE_IP', '184.72.55.25');		
}					

// ===========================================================================// 
// ! SERPS Database credentials                                               //
// ===========================================================================//

// If development environment
if(defined("DEV"))
{	
	// Ip of the redis database instance 
	define("REDIS_SERPS_IP", "50.18.172.44");

	// Redis listening port
	define("REDIS_SERPS_PORT", 6379);

	// Redis Proxy database number
	define("REDIS_SERPS_DB", 0);

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
	// Ip of the redis database instance 
	define("REDIS_SERPS_IP", "50.18.182.135");

	// Redis listening port
	define("REDIS_SERPS_PORT", 6379);

	// Redis Proxy database number
	define("REDIS_SERPS_DB", 0);

	// Database host
	define("DB_HOST", "serps.c7mnew97kkqx.us-west-1.rds.amazonaws.com");

	// Database username
	define("DB_SERP_USER", "seserps");

	// Database password
	define("DB_SERPS_PASS", "234k3k3LSJapBbr");

	// Database name
	define("DB_NAME_SERPS", "serps"); 		
}		

// ===========================================================================// 
// ! Proxy Database credentials                                               //
// ===========================================================================//          	  

// If development environment
if(defined("DEV"))
{	
	// Ip of the redis database instance 
	define("REDIS_PROXY_IP", "50.18.170.228");

	// Redis listening port
	define("REDIS_PROXY_PORT", 6379);

	// Redis Proxy database number
	define("REDIS_PROXY_DB", 0);	

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
	// Ip of the redis database instance 
	define("REDIS_PROXY_IP", "50.18.187.13");

	// Redis listening port
	define("REDIS_PROXY_PORT", 6379);

	// Redis Proxy database number
	define("REDIS_PROXY_DB", 0);	

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
