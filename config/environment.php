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
	elseif(strpos($argv[0], 'scoutftp'))
	{
		define('ENVIRONMENT', 'production');	
	}   
	else
	{
		define('ENVIRONMENT', 'ec2');	
	} 	
	
	echo ENVIRONMENT;
	
	// ===========================================================================// 
	// ! General server settings                                                  //
	// ===========================================================================//	

   	// Set working directory for correct file includes etc 
	chdir(dirname($argv[0]));	

	// Set default php timezone
	date_default_timezone_set('America/Los_Angeles'); 
	                                  
	// Turn off php notices
	error_reporting(E_ALL ^ E_NOTICE);	

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
    elseif(ENVIRONMENT == 'production')
    {
		// Database host
		define("DB_HOST", "localhost");
	
		// Database username
		define("DB_SERP_USER", "scoutftp_sescout");
	
		// Database password
		define("DB_SERPS_PASS", "Q0GifmVK");
	
		// Database name
		define("DB_NAME_SERPS", "scoutftp_serps"); 
    }
    elseif(ENVIRONMENT == 'cloud')
    {
		// Database host
		define("DB_HOST", "localhost");
	
		// Database username
		define("DB_SERP_USER", "scoutftp");
	
		// Database password
		define("DB_SERPS_PASS", "890uWQ2t");
	
		// Database name
		define("DB_NAME_SERPS", "serps"); 
    } 
    elseif(ENVIRONMENT == 'ec2')
    {
		// Database host
		define("DB_HOST", "localhost");
	
		// Database username
		define("DB_SERP_USER", "ec2-user");
	
		// Database password
		define("DB_SERPS_PASS", "R2VrwMF5");
	
		// Database name
		define("DB_NAME_SERPS", "serps"); 
    }       	  
?>