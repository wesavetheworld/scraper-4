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

class rankings 
{  
	// Search engine
	private $engine;

	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================// 
	
	function __construct()
	{           
		// Include keywords data model
	 	require_once('models/keywords.model.php'); 
		
		// Include serp parsing class
		require_once('classes/parse.class.php');

		// Include scraping class
		require_once('classes/scrape.class.php'); 	
		
		// Reset benchmarking
		utilities::benchmark(false, false, true);

		// Log status
		utilities::notate("Job started");		  		   	 			
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	 
	
	public function rankings($jobData)
	{  
			
		// Get the keywords from the job data				
		$jobData = unserialize($jobData);

		// Get the keywords from the job data				
		$keywords = $jobData['keywords'];

		// Set the search engine to use
		$this->engine = $jobData['engine'];
	 		   	
		// Call processing time
		utilities::benchmark('keywords selected: '); 

		// Connect to database
		utilities::databaseConnect();		
		        		        
		// Loop for as long as there are keywords left
		while($keywords->total > 0)
		{    
			// Check killswitch
			utilities::checkStatus();
			 		
			// Create new scraping instance
			$scrape = new scraper; 

			// Set search engine to scrape
			$scrape->engine = $this->engine;

			// Build an array of search engine urls to scrape
			$scrape->urls = $this->getKeywordUrls($keywords->keywords); 
									
			// Execute the scraping
			//$scrape->curlExecute();
			
			// Call processing time
			utilities::benchmark('scraping content: ');

			// Loop through each keyword
			foreach($keywords->keywords as $key => &$keyword)
			{   


				$test = "hey look at this! ";

				$count = 100;
				while($count != 0)
				{
					$content .= $test;
					$count--;
				}

				// Save search results to a file
				file_put_contents($keyword->searchFile, $content);	
			}
			return 'done';


				// If a valid search results page can be loaded (new scrape or saved file)
				if($searchResults = $this->getSearchResults($keyword, $scrape->results[$keyword->searchHash]))
				{  					
 	   				// Create new parsing object
					$parse = new parse;	 
				
					// Find the keyword's domain in one of the ranking urls
					$parse->findElements($this->parsePattern(), $searchResults)->findInElements($keyword->domain);			 
								   				
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
						$this->calibration($keyword);   
					    
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

		// Retrun total execution time
		return utilities::benchmark(' ', true, false, true); 		
	} 
	
	// ===========================================================================// 
	// ! Supporting methods                                                       //
	// ===========================================================================//	
	
	// Loop through keywords and return array of urls to scrape
	public function getKeywordUrls($keywords)
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
					
					// This is a new search
					$keyword->searchType = "new";
				}
			}  	
		} 
				
		// Return the url array
		return $urls;				
	}
    
	// Load the correct source for the keyword's search results
	public function getSearchResults($keyword, $scrapedContent = false)
	{   		
		// If a new url was scraped for this keyword
		if($scrapedContent)
		{  			        			
			// If the content has valid headers
			if($scrapedContent['status'] == 'success')
			{   
				// If the search is new for the first keyword
				if($keyword->searchType == "new")
				{				 				
					// Save the new search file
					$this->searchSave($keyword, $scrapedContent);
				}	
				
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
	public function searchSave($keyword, $scrapedContent)
	{   
		// Set header information to be saved with output
		$content  = "code: ".$scrapedContent['httpInfo']['http_code'];
		$content .= "\n size: ".$scrapedContent['httpInfo']['size_download'];
		$content .= "\n size: ".$scrapedContent['httpInfo']['size_download'];
		$content .= "\n\n".$scrapedContent['output'];
		
		// Save search results to a file
		file_put_contents($keyword->searchFile, $content);		
	} 
	
	// If a keyword just switch result amount (10/100)
	public function calibration($keyword)
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

	// Determine the correct parsing regex pattern to use
	public function parsePattern()
	{
		// Search engine is google
		if($this->engine == "google")
		{
			return PARSE_PATTERN_GOOGLE;
		}
		// Search engine is bing
		elseif($this->engine == "bing")
		{
			return PARSE_PATTERN_BING;
		}
	}

	
	
	 
}	    






