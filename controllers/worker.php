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

class worker 
{  
	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================// 
	
	function __construct()
	{           
		// Include keywords data model
	 	include('models/keywords.model.php'); 
	    //include('models/keywords.mongo.php'); 
		
		// Include serp parsing class
		include('classes/parse.class.php');

		// Include scraping class
		include('classes/scrape.class.php');   		   	 			
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	
	
	public function worker()
	{    
		# Create our worker object.
		$gmworker= new GearmanWorker();

		# Add default server (localhost).
		$gmworker->addServer('10.170.102.159'); 

		# Register function "reverse" with the server. Change the worker function to
		$gmworker->addFunction("rankings", "worker::rankings"); 
		
		print "Waiting for jobs fuckd...\n"; 

		while($gmworker->work())
		{   
			// If job failed
			if ($gmworker->returnCode() != GEARMAN_SUCCESS)
			{
				echo "return_code: " . $gmworker->returnCode() . "\n";
				break;
			} 
			// If job was completed successfully 
			else
			{
				echo "job completed.\n"; 				
			} 
		}
	}  
	
	public static function rankings($job)
	{   
		return true; 
		// Reset benchmarking
		utilities::benchmark(false, false, true);

		// Log status
		utilities::notate("Job started");
		
		// Get the keywords from the job data				
		$keywords = unserialize($job->workload());
						 		   	
		// Call processing time
		utilities::benchmark('keywords selected: '); 

		// Connect to database
		utilities::databaseConnect();		
		        		        
		// Loop for as long as there are keywords left
		while($keywords->total > 0)
		{    
			// Check killswitch
			worker::killSwitch();
			 		
			// Create new scraping instance
			$scrape = new scraper; 
			
			// Build an array of search engine urls to scrape
			$scrape->urls = worker::getKeywordUrls($keywords->keywords); 
									
			// Execute the scraping
			$scrape->curlExecute();
			
			// Call processing time
			utilities::benchmark('scraping content: ');

			// Loop through each keyword
			foreach($keywords->keywords as $key => &$keyword)
			{   
				// If a valid search results page can be loaded (new scrape or saved file)
				if($searchResults = worker::getSearchResults($keyword, $scrape->results[$keyword->searchHash]))
				{  					
 	   				// Create new parsing object
					$parse = new parse;	 
				
					// Find the keyword's domain in one of the ranking urls
					$parse->findElements(PARSE_PATTERN, $searchResults)->findInElements($keyword->domain);			 
								   				
					// If domain was found or keyword on last search page
					if($parse->found || $keyword->searchPage == SEARCH_DEPTH - 1)
					{   
						// If a ranking was found 
						if($parse->found)
						{   
							// Set new keyword rank (amount of results per page + position on current page)
							$keyword->rank = $keyword->searchOffset + $parse->position; 
														
							// Set the matching url that was found ranking
							$keyword->found = $parse->found;  
						}
						// If no ranking was found
						else
						{    
							// "0" is used for "not found"
							$keyword->rank = 0;   
						}  
												
						// Calibrate keyword ranking (10/100 results)
						worker::calibration($keyword);   
					    
						// Decrease keywords remaining by one
						$keywords->total--; 
					}
					// Domain was not found ranking
					else
					{ 
						// Increase search results page for next scrape
						$keyword->searchPage++; 						
					} 
				}
			} 

			// Call processing time
			utilities::benchmark('Parse all content: ');  
						
			// Update finished keywords in DB
			$keywords->updateKeywords();                
			
			// Call processing time
			utilities::benchmark('update keywords: '); 
			
			echo "\nkeywords left: ".$keywords->total."\n";
		}
		
		// Return finished keywords array
		return "job complete";
	} 
	
	// ===========================================================================// 
	// ! Supporting methods                                                       //
	// ===========================================================================//	
	
	// Checks for killswitch file and if true kills script 
	private static function killSwitch()
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
	
	// Loop through keywords and return array of urls to scrape
	public static function getKeywordUrls($keywords)
	{    
		// Loop through each keyword
		foreach($keywords as $key => &$keyword)
		{                                      
			// Generate the search page url 
			$keyword->setSearchUrl();			  		
			 			                     			
			// If keyword's search hash is unique
			if(!$urls[$keyword->searchHash])
			{    				
				// If no saved search or saved search is from another hour
				if(!file_exists($keyword->searchFile) || 
					date("Y-m-d-G", filemtime($keyword->searchFile)) != date("Y-m-d-G") || 
					filesize($keyword->searchFile) < 500)
				{      
					// Add the keyword's search page url to scraping list
					$urls[$keyword->searchHash] = $keyword->url;   
				}
			}   	
		} 
				
		// Return the url array
		return $urls;				
	}
    
	// Load the correct source for the keyword's search results
	public static function getSearchResults($keyword, $scrapedContent = false)
	{   		
		// If a new url was scraped for this keyword
		if($scrapedContent)
		{  			        			
			// If the content has valid headers
			if($scrapedContent['status'] == 'success')
			{   				 				
				// Save the new search file
				worker::searchSave($keyword, $scrapedContent);
				
				// Set the new search as the source
				$search = $scrapedContent['output']; 						
			}
			else
			{
				static $errors = 0;
			}					
		} 
		else
		{    
  			// Load a valid saved search file as the source
			$search = file_get_contents($keyword->searchFile);
		} 
		
		return $search;
	}

	// Save search results to a file
	public static function searchSave($keyword, $scrapedContent)
	{   
		// Set header information to be saved with output
		$content  = "code: ".$scrapedContent['httpInfo']['http_code'];
		$content .= "\n size: ".$scrapedContent['httpInfo']['size_download'];
		$content .= "\n\n".$scrapedContent['output'];
		
		// Save search results to a file
		file_put_contents($keyword->searchFile, $content);		
	} 
	
	// If a keyword just switch result amount (10/100)
	public static function calibration($keyword)
	{	
		// If a change in ranking has occurred in which a new search with a different result count is needed
		if($keyword->lastRank > NUM_SWITCH_THRESHHOLD && $keyword->rank < NUM_SWITCH_THRESHHOLD || $keyword->lastRank && !$keyword->rank || !$keyword->lastRank && $keyword->rank )
		{   
			// If keyword is bouncing back and forth, give up
			if($keyword->calibrate == MAX_CALIBRATIONS || ENGINE == 'bing')
			{
				// No calibation needed
				$keyword->calibrate = 0;			
		    }
			else
			{
				// Set keyword to calibrate(rescrape with new result count)
				$keyword->calibrate++;			
			}
		}
		else
		{   
			// No calibation needed
			$keyword->calibrate = 0;
		}		
	}

	
	
	 
}	    






