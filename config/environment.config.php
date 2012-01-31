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

// If local environment
if(defined("LOCAL"))
{
	// The AWS elastic ip for the client server
	define('BOSS_IP', '127.0.0.1');

	// Redis listening port
	define("BOSS_PORT", 6379);

	// Redis boss database number
	define("BOSS_DB", 0);		

	// The AWS elastic ip for the google 1 server
	define('GOOGLE_IP', '127.0.0.1');
}
// If development environment
elseif(defined("DEV"))
{
	// The AWS elastic ip for the client server
	define('BOSS_IP', '184.72.45.180');

	// Redis listening port
	define("BOSS_PORT", 6379);

	// Redis boss database number
	define("BOSS_DB", 0);		

	// The AWS elastic ip for the google 1 server
	define('GOOGLE_IP', '50.18.187.16');	
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
	define('GOOGLE_IP', '184.72.55.252');		
}					

// ===========================================================================// 
// ! SERPS Database credentials                                               //
// ===========================================================================//

// If local environment
if(defined("LOCAL"))
{
	// Ip of the redis database instance 
	define("REDIS_SERPS_IP", "127.0.0.1");

	// Redis listening port
	define("REDIS_SERPS_PORT", 6379);

	// Redis Proxy database number
	define("REDIS_SERPS_DB", 1);

	// Database host
	define("DB_HOST", ":/tmp/mysql.sock");

	// Database username
	define("DB_SERP_USER", "root");

	// Database password
	define("DB_SERPS_PASS", "root");

	// Database name
	define("DB_NAME_SERPS", "serps"); 
}
// If development environment
elseif(defined("DEV"))
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

	// Ip of the redis database instance 
	define("REDIS_SERPS_SLAVE_IP", "");

	// Redis listening port
	define("REDIS_SERPS_SLAVE_PORT", 6379);

	// Redis Proxy database number
	define("REDIS_SERPS_SLAVE_DB", 0);	

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
// ! Front end cache Database credentials (just for boot management)          //
// ===========================================================================// 	

// Ip of the redis database instance 
define("REDIS_CACHE_IP", "50.18.181.89");

// Redis listening port
define("REDIS_CACHE_PORT", 6379);

// Redis Proxy database number
define("REDIS_CACHE_DB", 0);

// ===========================================================================// 
// ! Proxy Database credentials                                               //
// ===========================================================================//          	  

// If local environment
if(defined("LOCAL"))
{	
	// Ip of the redis database instance 
	define("REDIS_PROXY_IP", "127.0.0.1");

	// Redis listening port
	define("REDIS_PROXY_PORT", 6379);

	// Redis Proxy database number
	define("REDIS_PROXY_DB", 2);	

	// Proxy Database host
	define("PROXY_HOST", "");

	// Proxy Database username
	define("PROXY_USER", "");

	// Proxy Database password
	define("PROXY_PASS", "");

	// Proxy Database name
	define("PROXY_DB", ""); 	
}
// If development environment
elseif(defined("DEV"))
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
