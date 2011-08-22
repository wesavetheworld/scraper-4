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
	echo "config loaded!";
	// Build array of required arguments
	$requiredArgs = array('engine'=>ENGINE, 'schedule'=>SCHEDULE, 'keyword amount'=>KEYWORD_AMOUNT);
    
    // Json array of rquired arguments (only way to save array as a constant)                                              
	define("REQUIRED_ARGS", json_encode($requiredArgs));

   	// Should the scraper use proxies
 	define("PROXY_USE", TRUE);

	// How many times to retry scraping of a keyword before failing
	define("MAX_FAILED_HTTP_ERRORS", 100);
	
	// How many pages deep to search on google/bing
	define("SEARCH_DEPTH", 5);	
	
	// What ranking to switch scraping from 10/100 results
	define("NUM_SWITCH_THRESHHOLD", 49);
	
	// The amount of sequential calibrations before stopping
	define("MAX_CALIBRATIONS", 2);	
	
	// Avoid potential runaway scripts by limiting max execution time      
	define("MAX_EXECUTION_TIME", 10);
	
	// Set to longer than max time above
	set_time_limit(400);   	   

	// ===========================================================================// 
	// ! Logging and offset file settings                                         //
	// ===========================================================================// 

	// If the value returns true the script will die
	define('KILL_SWITCH_FILE', STATUS_DIRECTORY."killswitch.txt"); 
		
	// If a keyword fails to update, write the error to this file
	define("KEYWORD_ERROR_FILE", LOG_DIRECTORY."1bad_keywords.txt");	
	
	// Directory to save search result files
	define("SAVED_SEARCH_DIR", "data/searches/success/");	
	
	// Directory to save search result files
	define("ERROR_PAGE_DIR", "data/searches/error/");	
	
	// ===========================================================================// 
	// ! Google constants                                                         //
	// ===========================================================================//	  
	
	// The regular expression for parsing google rankings
	define("PARSE_PATTERN_GOOGLE","(<h3 class=\"r\"><a href=\"(.*)\".* class=l>(.*)</a></h3>)siU");	
	
	// ===========================================================================// 
	// ! Bing constants                                                           //
	// ===========================================================================//	
	
	// The regular expression for parsing google rankings
	define("PARSE_PATTERN_BING", '(<div class="sb_tlst">.*<h3>.*<a href="(.*)".*>(.*)</a>.*</h3>.*</div>)siU');				

	// ===========================================================================// 
	// ! Database structure and update time depending on search engine            //
	// ===========================================================================//

	define("PARSE_PATTERN_BACKLINKS", '/Inlinks \((.*)\)/Us');  	
	