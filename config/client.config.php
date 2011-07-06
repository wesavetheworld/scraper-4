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
	// ! Passed arguments through CLI ($argv[1] used for controller)              //
	// ===========================================================================//    
	
	// Amount of keywords to scrape
	define("KEYWORD_AMOUNT", 2);
		    
	// Avoid potential runaway scripts by limiting max execution time      
	define("MAX_EXECUTION_TIME", 300);
	
	// What search engine to scrape
	define("ENGINE", 'google');
	
	// What search engine to scrape
	define("ENGINE", 'hourly');
	
	// Scrape just new keywords for above engine if set to true
	define("NEW_KEYWORDS", $argv[5]);  
	
	// Only update keywords for this user
	define("ONLY_USER", 65);    
	
	
	// Only update keywords for this user
	define("SCHEDULE", 'hourly');