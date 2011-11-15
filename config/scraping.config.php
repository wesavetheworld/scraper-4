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
// ! Keyword search depth and error settings                                  //
// ===========================================================================//  

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

// ===========================================================================// 
// ! Proxy settings - These get overruled if settings updated in redis        //
// ===========================================================================//	

// Should the scraper use proxies
define("PROXY_USE", TRUE);

// Amount of time to rest a proxy when it gets blocked (in seconds)
define('PROXY_WAIT_BLOCKED', 60 * 5);			 	

// Amount of time to rest before using a proxy again (in seconds)
define('PROXY_WAIT_USE', 20);		

// ===========================================================================// 
// ! Regular expressions used for finding data on the page scraped            //
// ===========================================================================//	  

// The regular expression for parsing google rankings
define("PARSE_PATTERN_GOOGLE","(<h3 class=\"r\"><a href=\"(.*)\".*>(.*)</a></h3>)siU");	

// The regular expression for saving a google search results
define("PARSE_PATTERN_GOOGLE_SAVE","(<div id=\"search\">(.*)<!--z-->)siU");	

// The regular expression for parsing google rankings
define("PARSE_PATTERN_BING", '(<div class="sb_tlst">.*<h3>.*<a href="(.*)".*>(.*)</a>.*</h3>.*</div>)siU');				

// The regular expression for parsing bing rankings
define("PARSE_PATTERN_BACKLINKS", '/Inlinks \((.*)\)/Us'); 

// ===========================================================================// 
// ! cURL header information (user agents and referrers)                      //
// ===========================================================================//

// Chrome
$userAgents[] = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_2) AppleWebKit/535.2 (KHTML, like Gecko) Chrome/15.0.874.120 Safari/535.2";
$userAgents[] = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/535.2 (KHTML, like Gecko) Chrome/18.6.872.0 Safari/535.2 UNTRUSTED/1.0 3gpp-gba UNTRUSTED/1.0";
$userAgents[] = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.36 Safari/535.7";
$userAgents[] = "Mozilla/5.0 (Windows NT 6.0; WOW64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.36 Safari/535.7";

// Safari
$userAgents[] = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_2) AppleWebKit/534.51.22 (KHTML, like Gecko) Version/5.1.1 Safari/534.51.22";
$userAgents[] = "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_8; de-at) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1";
$userAgents[] = "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; da-dk) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1";
$userAgents[] = "Mozilla/5.0 (Windows; U; Windows NT 6.1; tr-TR) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27";
$userAgents[] = "Mozilla/5.0 (Windows; U; Windows NT 6.1; sv-SE) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4";
$userAgents[] = "Mozilla/5.0 (Windows; U; Windows NT 6.1; ja-JP) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4";
$userAgents[] = "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; de-de) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27";
$userAgents[] = "Mozilla/5.0 (Macintosh; U; PPC Mac OS X 10_5_8; zh-cn) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27";
$userAgents[] = "Mozilla/5.0 (Windows; U; Windows NT 6.1; zh-HK) AppleWebKit/533.18.1 (KHTML, like Gecko) Version/5.0.2 Safari/533.18.5";
$userAgents[] = "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.2 Safari/533.18.5";
$userAgents[] = "Mozilla/5.0 (Windows; U; Windows NT 6.0; tr-TR) AppleWebKit/533.18.1 (KHTML, like Gecko) Version/5.0.2 Safari/533.18.5";;

// Firefox
$userAgents[] = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:5.0) Gecko/20100101 Firefox/5.0";
$userAgents[] = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:6.0) Gecko/20100101 Firefox/6.0";
$userAgents[] = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:7.0) Gecko/20100101 Firefox/7.0";
$userAgents[] = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:8.0) Gecko/20100101 Firefox/8.0";
$userAgents[] = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:9.0a2) Gecko/20111101 Firefox/9.0a2";
$userAgents[] = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:9.0) Gecko/20100101 Firefox/9.0";
$userAgents[] = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:6.0a2) Gecko/20110613 Firefox/6.0a2";
$userAgents[] = "Mozilla/5.0 (X11; Linux i686; rv:6.0) Gecko/20100101 Firefox/6.0";
$userAgents[] = "Mozilla/5.0 (Windows NT 5.1; rv:6.0) Gecko/20100101 Firefox/6.0 FirePHP/0.6";
$userAgents[] = "Mozilla/5.0 (X11; Linux i686 on x86_64; rv:5.0a2) Gecko/20110524 Firefox/5.0a2";
$userAgents[] = "Mozilla/5.0 (Windows NT 6.1; U; ru; rv:5.0.1.6) Gecko/20110501 Firefox/5.0.1 Firefox/5.0.1";
$userAgents[] = "Mozilla/5.0 (X11; U; Linux i586; de; rv:5.0) Gecko/20100101 Firefox/5.0";
$userAgents[] = "Mozilla/5.0 (X11; U; Linux amd64; rv:5.0) Gecko/20100101 Firefox/5.0 (Debian)";

// User agents to send in cURL headers 
define('USER_AGENTS', json_encode($userAgents));	

// Build array of possible referrers
$referrers[] = "";

// Referrers to send in cURL headers 
define('REFERRERS', json_encode($referrers));
	
// ********************************** END **********************************// 
