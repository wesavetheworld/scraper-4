<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

	// ******************************* INFORMATION ******************************//
	
	// **************************************************************************//
	//  
	// ** BACKLINKS.CONFIG - All of the settings that are generally constant and only 
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
	// ! Passed arguments through CLI ($argv[1] used for controller)              //
	// ===========================================================================//  
	
	// Which domain stat to collect (backlinks,pr,alexa)
	define("STAT", $argv[2]);
	
	// The amount of records to update
	define("AMOUNT", $argv[3]);
	
	// Scrape just new keywords for above engine if set to true
	define("NEW_DOMAINS", $argv[4]);
	                                 
	// Only update keywords for this user
	define("ONLY_USER", $argv[5]);
	
	// ===========================================================================// 
	// ! General settings                                                         //
	// ===========================================================================// 
	
	// Build array of required arguments
	$requiredArgs = array('stat'=>STAT, 'amount'=>AMOUNT);
    
    // Json array of rquired arguments (only way to save array as a constant)                                              
	define("REQUIRED_ARGS", json_encode($requiredArgs));	   	 
	
	// Avoid potential runaway scripts by limiting max execution time      
	define("MAX_EXECUTION_TIME", 240);

	// Define engine for database update
	define("ENGINE", 'yahoo');
	
	// The regular expression for parsing google rankings
	define("PARSE_PATTERN", '/Inlinks \((.*)\)/Us');  
	
	// Should the scraper use proxies
 	define("PROXY_USE", TRUE); 

	// ===========================================================================// 
	// ! Logging and offset file settings                                         //
	// ===========================================================================// 
		  
	// If scraping new keywords only
	if(NEW_DOMAINS)
	{     		
		// The file used to save the select query offset
		define("STATUS_FILE", STATUS_DIRECTORY.ENGINE."-new.txt");	
	} 
	elseif(ONLY_USER)
	{   
		// The file used to save the select query offset
		define("STATUS_FILE", STATUS_DIRECTORY.ENGINE."-".ONLY_USER.".txt");		
	}
	else
	{   
		// The file used to save the select query offset
		define("STATUS_FILE", STATUS_DIRECTORY.ENGINE.".txt");		
	} 
	
   
