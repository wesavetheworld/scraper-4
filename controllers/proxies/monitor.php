<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

	// ******************************* INFORMATION ******************************//
	
	// **************************************************************************//
	//  
	// ** MONITOR - Monitor the health of the proxies in the database and notify 
	// ** admin if any issues are found 
	// ** 
	// ** @author	Joshua Heiland <thezenman@gmail.com>
	// ** @date	 2011-06-18
	// ** @access	private
	// ** @param	
	// ** @return	nothing or push notification on error 
	//  	
	// ***************************************************************************//
	
	// ********************************** START **********************************// 
 
	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================// 
	                                  
	// Include scraping class
	include('classes/scraper.class.php');  
	
	// Test all proxies if true
	$all = $argv['2'];

	// Connect to database
	utilities::databaseConnect();
                
	// ===========================================================================// 
	// ! Check for proxies that need testing                                      //
	// ===========================================================================//

	// Select all blocked proxies
    $query = "	SELECT
					*
				FROM
					proxies
				WHERE
					blocked_google != 0
				OR
					status = 'disabled'";  
					
    // If argument passed to test all proxies
	if($all)
	{
				// Select all blocked proxies
			    $query = "	SELECT
								*
							FROM
								proxies";  
  	}
    
	// Execute query
	$result = mysql_query($query) or utilities::reportErrors(mysql_error()); 
	
	// If no proxies are blocked 
	if(mysql_num_rows($result) == 0)
	{
	 	// Log current state
		utilities::notate("\tno proxies are blocked.");
	  	
		// Finish final tasks and end execution
		utilities::complete(); 		
	}
	
	// Loop through blocked proxies
	while($proxy = mysql_fetch_array($result, MYSQL_ASSOC))
	{	   
	 	// Add the url that will return the requesting ip if live
		$statusUrls[] = "http://50.23.212.129/tools/manage/showip.php"; 
		 
		// A google url to scrape to see if the proxy is blocked
		$googleUrls[] = "http://www.google.com/search?sourceid=chrome&ie=UTF-8&q=its+alive";

		// Add blocked proxy to be tested
		$proxies[] = $proxy; 
	}  
	
	// ===========================================================================// 
	// ! Test that the proxies are live                                           //
	// ===========================================================================//	
	            
	// Create new scraper object
	$scrape = new scraper;
	
	// Change the url array to the googl urls
	$scrape->urls = $statusUrls;    

	// Pass the modified proxy array to scraper
	$scrape->proxies = $proxies; 
	
	// Execute the scraping
	$scrape->curlExecute();	 
	
	// Log current state
 	utilities::notate("\tStatus test:");

	foreach($scrape->results as $key => $result)
	{       
		// If the response ip matches the proxy ip    
	    if($scrape->proxies[$key]['proxy'] == $result['output'])
		{    
			// Log current state
		 	utilities::notate("\t\tlive: ".$scrape->proxies[$key]['proxy']." - ".$scrape->proxies[$key]['source']);
			
	 		// Add proxy to the live array
			$live[] = $scrape->proxies[$key]['proxy'];
		}
		else
		{    
			// Log current state
		 	utilities::notate("\t\tdead: ".$scrape->proxies[$key]['proxy']." - ".$scrape->proxies[$key]['source']);
					  
 	 		// Add proxy to the dead array
			$dead[] = $scrape->proxies[$key]['proxy'];  

			// Remove this proxy from the next google test
			unset($proxies[$key]);	
			
			// Remove the google test url for this proxy
			unset($googleUrls[$key]);			
		}	
	} 
	
	// If dead proxies found
	if(count($dead) > 0)
	{
		$query = "UPDATE proxies SET status = 'disabled' WHERE proxy IN('".implode("','", $dead)."')";
		mysql_query($query) or utilities::reportErrors(mysql_error());
	} 
	
	// Set all live proxies to active
	if(count($live) > 0)
	{
		$query = "UPDATE proxies SET status = 'active' WHERE proxy IN('".implode("','", $live)."')";
		mysql_query($query) or utilities::reportErrors(mysql_error());
	} 
	
	// ===========================================================================// 
	// ! Test if the proxies are blocked by google                                //
	// ===========================================================================//	
 	
	// Create new scraper object
	$scrape = new scraper;    
	
	// Pass the urls to scraper
	$scrape->urls = $googleUrls;    
	   
	// Pass the proxies to scraper
	$scrape->proxies = $proxies; 
	
	// Execute the scraping
	$scrape->curlExecute();	  
	
	// Log current state
 	utilities::notate("\tGoogle test:");	
	 
	// Loop through scraped google pages
	foreach($scrape->results as $key => $result)
	{       
		// If response header is a 200 success
		if($result['httpInfo']['http_code'] == 200)
		{        
			// Log current state
		 	utilities::notate("\t\tlive: ".$scrape->proxies[$key]['proxy']." - ".$scrape->proxies[$key]['source'] );
					
		    // Google is not blocking this proxy
			$googleLive[] = $scrape->proxies[$key]['proxy'];
		}
		else
		{   
			// Log current state
		 	utilities::notate("\t\tblocked: ".$scrape->proxies[$key]['proxy']." - ".$scrape->proxies[$key]['source'] );			
			 
			// Google is blocking this proxy
			$googleBlocked[] = $scrape->proxies[$key]['proxy'];
		} 
	} 
	
	// If proxies are not blocked
	if(count($googleBlocked) > 0)
	{
		$query = "UPDATE proxies SET blocked_google = '1' WHERE proxy IN('".implode("','", $googleBlocked)."')";
		mysql_query($query) or utilities::reportErrors(mysql_error());
	} 

	// If proxies are not blocked
	if(count($googleLive) > 0)
	{
		$query = "UPDATE proxies SET blocked_google = '0' WHERE proxy IN('".implode("','", $googleLive)."')";
		mysql_query($query) or utilities::reportErrors(mysql_error());
	}
	
	// ===========================================================================// 
	// ! Show final stats                                                         //
	// ===========================================================================//	     
	
	// Log current state
 	utilities::notate("\tProxies dead: ".count($dead));	  

	// Log current state
 	utilities::notate("\tProxies Live: ".count($live));  

	// Log current state
 	utilities::notate("\tProxies blocked: ".count($googleBlocked));	  

	// Log current state
 	utilities::notate("\tProxies not blocked: ".count($googleLive)); 
    
	// If testing all proxies
	if($all)
	{    
		
		
		// Log current state
	  	utilities::notate("\tProxies ready for use: "); 
	}

?>	