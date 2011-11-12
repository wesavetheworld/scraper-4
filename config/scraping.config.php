<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");

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

// Should the scraper use proxies
define("PROXY_USE", TRUE);

// How many times to retry scraping of a keyword before failing
define("MAX_FAILED_HTTP_ERRORS", 100);

// How many pages deep to search on google/bing
define("SEARCH_DEPTH", 5);	

// What ranking to switch scraping from 10/100 results
define("NUM_SWITCH_THRESHHOLD", 29);
	
// The amount of sequential calibrations before stopping
define("MAX_CALIBRATIONS", 2);	

// ===========================================================================// 
// ! Scraping settings                                                        //
// ===========================================================================//	

// cURL connection timout limit
define('CURL_TIMEOUT', 5);  

// Amount of time to rest a proxy when it gets blocked (in seconds)
define('PROXY_WAIT_BLOCKED', 60 * 5);			 	

// Amount of time to rest before using a proxy again (in seconds)
define('PROXY_WAIT_USE', 20);		

// ===========================================================================// 
// ! Regular expressions used for finding data on the page scraped            //
// ===========================================================================//	  

// The regular expression for parsing google rankings
define("PARSE_PATTERN_GOOGLE","(<h3 class=\"r\"><a href=\"(.*)\".*>(.*)</a></h3>)siU");	

// The regular expression for parsing google rankings
define("PARSE_PATTERN_BING", '(<div class="sb_tlst">.*<h3>.*<a href="(.*)".*>(.*)</a>.*</h3>.*</div>)siU');				

// The regular expression for parsing bing rankings
define("PARSE_PATTERN_BACKLINKS", '/Inlinks \((.*)\)/Us'); 
	
// ********************************** END **********************************// 
