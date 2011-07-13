<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** RANKINGS - Scrapes search engines for rankings. Required settings can be 
// ** set in config/rankings.php 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-21
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class stats 
{  

	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================// 
	
	function __construct()
	{           
		// Include keywords data model
	 	include('models/domains.model.php'); 
		
		// Include serp parsing class
		include('classes/parse.class.php');

		// Include scraping class
		include('classes/scrape.class.php'); 

		// Check for required arguments before continuing
		utilities::argumentCheck(json_decode(REQUIRED_ARGS, TRUE));
		
	  	// Initiate benchmarking
		utilities::benchmark();		
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	
	
	public function stats()
	{
		// Get the keywords from the db
       	$domains = $this->selectDomains();  

		// Loop for as long as there are keywords left
		while($domains->total > 0)
		{    
			// Check killswitch
			$this->killSwitch();
			 		
			// Create new scraping instance
			$scrape = new scraper; 
			
			// Build an array of search engine urls to scrape
			$scrape->urls = $this->getDomainUrls($domains->domains); 
						
			// Execute the scraping
			$scrape->curlExecute();
									
			// Loop through each domain
			foreach($domains->domains as $key => &$domain)
			{   								 
				// If a valid search results page can be loaded (new scrape or saved file)
				if($searchResults = $this->getSearchResults($domain, $scrape->results[$domain->url]))
				{    
 	   				// Create new parsing object
					$parse = new parse;	
					
					if(STAT == "backlinks")
					{
						// Find the keyword's domain in one of the ranking urls
						$parse->findElements(PARSE_PATTERN, $searchResults); 
						
						// Set backlinks for domain
						$domain->backlinks =  str_replace(",","",$parse->elements[0]); 
					}
					elseif(STAT == "pr")
					{    
						// Set the pagerank for domain
						$domain->pr = $parse->pageRank($searchResults); 
					} 
					elseif(STAT == "alexa")
					{    
						// Set the alexa rank for domain
						$domain->alexa = $parse->alexa($searchResults); 
								
						echo "alexa: ".$domain->alexa."\n";						
					}
					
					// Decrease total domains remaining
					$domains->total--; 
				} 
				 							   				
			}
			
			//print_r($domains);
			// Update finished keywords in DB
			$domains->updateDomains();   
			
			// Keep track of script execuion time
			utilities::benchmark();			
		}  
				
	  	// Finish execution
		utilities::complete();
	} 
	
	// ===========================================================================// 
	// ! Supporting methods                                                       //
	// ===========================================================================//	
	
	// Checks for killswitch file and if true kills script 
	private function killSwitch()
	{   
		// If above death
		if(!ZOMBIE)
		{
			// If killswitch file exists
			if(file_exists(KILL_SWITCH_FILE))
			{    
				// Get file contents
				$kill = file_get_contents(KILL_SWITCH_FILE);
			
				// If kill switch turned on
				if($kill)
				{   
					// Log current state
					utilities::notate("Kill switch flicked"); 
				
				  	// Finish execution
					utilities::complete();
				}
			}
		}   
	}
	// Get the keywords that will be updated
	private function selectDomains()
	{
		// Grab the keywords to update
		$domains = new domains();
						
		// If fewer keywords returned than requested
		if(count((array)$domains) < DOMAIN_AMOUNT)
		{			   
		 	// Reset next offset
			//$this->setNextOffset('reset');	 			
	   	}
	    
		// Return finished keyword array
		return $domains;
	} 
	
	// Loop through keywords and return array of urls to scrape
	private function getDomainUrls($domains)
	{       
		// Loop through each keyword
		foreach($domains as $key => &$domain)
		{   
			// Generate the search page url 
			$domain->setSearchUrl();			  		
			 			                     			
			// If keyword's search hash is unique
			if(!$urls[$domain->url])
			{    				
				// Add the keyword's search page url to scraping list
				$urls[$domain->url] = $domain->url;   
			}   	
		} 
				
		// Return the url array
		return $urls;				
	}
    
	// Load the correct source for the keyword's search results
	private function getSearchResults($domain, $scrapedContent = false)
	{   		
		// If a new url was scraped for this keyword
		if($scrapedContent)
		{  			       
			// If the content has valid headers
			if($scrapedContent['status'] == 'success')
			{   				 								
				// Set the new search as the source
				$search = $scrapedContent['output'];
				
				//file_put_contents('backlinks/'.$domain->domain.'.html', $search);		
			}
			// If pagerank instance and a 403 code is returned
			elseif(STAT == 'pr' && $scrapedContent['httpInfo']['http_code'] == 403)
			{    
				// 403 is Google's way of saying there is no pagerank for a domain
				$search = "no pagerank";
			}								
		}
	
		return $search;
	}

	
	 
}	    






