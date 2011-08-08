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
	// Search engine
	private $engine;

	// Data model to use
	private $model;

	// The type of stat to collect for domains
	private $stat = false;

	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================// 
	
	function __construct()
	{           		

		// Include serp parsing class
		require_once('classes/parse.class.php');

		// Include scraping class
		require_once('classes/scrape.class.php'); 	
		
		// Reset benchmarking
		utilities::benchmark(false, false, false, true);

		// Log status
		utilities::notate("Job started", "rankings.log");		  		   	 			
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	 
	
	public function worker($data)
	{  	
		// Get the items model
		$this->model = $data['model'];			

		// Include items data model
	 	require_once("models/".$this->model.".model.php"); 		
				
		// Get the keywords from the job data				
		$jobData = unserialize($data['jobData']);	

   		// Remove "s" from object for singular item class
		$this->class = substr($this->model, 0, -1); 		
		
		if($data['stat'])
		{
			$this->stat = $data['stat'];

			${$this->model}->stat = $this->stat;	
			
			$this->engine = "google";
		}	
		else
		{
			// Set the search engine to use
			$this->engine = $jobData['engine'];					
		}	

		// Get the items from the job data				
		${$this->model} = $jobData[$this->model];
	 		   	
		// Call processing time
		utilities::benchmark('items selected: ', "rankings.log"); 		
		        		        
		// Loop for as long as there are keywords left
		while(${$this->model}->total > 0)
		{    
			// Check killswitch
			utilities::checkStatus();
			 		
			// Connect to database
			utilities::databaseConnect();		
					 		
			// Create new scraping instance
			$scrape = new scraper; 

			// Set search engine to scrape
			$scrape->engine = $this->engine;

			// If a domain stats connection
			$scrape->stat = $this->stat;

			// Build an array of search engine urls to scrape
			$scrape->urls = $this->getUrls(${$this->model}->{$this->model}); 	
									
			// Execute the scraping
			$scrape->curlExecute();
			
			// Call processing time
			utilities::benchmark('scraping content: ', "rankings.log");
			
			// Loop through each keyword
			foreach(${$this->model}->{$this->model} as $key => &${$this->class})
			{
				// Create new parsing object
				$parse = new parse;	

				// Content for domains
				if($this->model == "domains")
				{
					// If a valid search results page can be loaded (new scrape or saved file)
					if($content = $this->getContent(${$this->class}, $scrape->results[${$this->class}->url]))
					{  						
						if($this->stat == "backlinks")
						{
							// Find the keyword's domain in one of the ranking urls
							$parse->findElements(PARSE_PATTERN, $content); 
							
							// Set backlinks for domain
							${$this->class}->backlinks =  str_replace(",","",$parse->elements[0]); 
						}
						elseif($this->stat == "pr")
						{    
							// Set the pagerank for domain
							${$this->class}->pr = $parse->pageRank($content); 

							echo ${$this->class}->pr."\n";
						} 
						elseif($this->stat == "alexa")
						{    
							// Set the alexa rank for domain
							${$this->class}->alexa = $parse->alexa($content); 
									
							echo "alexa: ".${$this->class}->alexa."\n";						
						}

						// Add keyword to completed list
						${$this->model}->updated[$key] = ${$this->class};

						// Remove keyword from keyword id array
						unset(${$this->model}->{$this->model}->$key); 						

						// Decrease total domains remaining
						${$this->model}->total--; 

						echo  "\ndomains remaining: ". ${$this->model}->total."\n";
					}	
					else
					{
						echo ${$this->class}->url."\n";
					}
				}	
				// Content for keywords
				elseif($this->model == "keywords")
				{
					// If a valid search results page can be loaded (new scrape or saved file)
					if($content = $this->getContent(${$this->class}, $scrape->results[${$this->class}->searchHash]))
					{  							
						// Find the keyword's domain in one of the ranking urls
						$parse->findElements($this->parsePattern(), $content)->findInElements(${$this->class}->domain);			 
									   				
						// If domain was found or keyword on last search page
						if($parse->found || ${$this->class}->searchPage == SEARCH_DEPTH - 1)
						{   
							// If a ranking was found 
							if($parse->found)
							{   
								// Set new keyword rank (amount of results per page + position on current page)
								${$this->class}->rank = ${$this->class}->searchOffset + $parse->position; 
															
								// Set the matching url that was found ranking
								${$this->class}->found = $parse->found;  
							}
							// If no ranking was found
							else
							{    
								// "0" is used for "not found"
								${$this->class}->rank = 0;   
							}  
													
							// Calibrate keyword ranking (10/100 results)
							$this->calibration(${$this->class});   

							// Add keyword to completed list
							${$this->model}->updated[$key] = ${$this->class};

							// Remove keyword from keyword id array
							unset(${$this->model}->{$this->model}->$key);  
						    
							// Decrease keywords remaining by one
							${$this->model}->total--; 
						}
						// Domain was not found ranking
						else
						{ 
							// Increase search results page for next scrape
							${$this->class}->searchPage++; 						
						} 
					}	
				}					
			} 

			// Call processing time
			utilities::benchmark('Parse all content: ', "rankings.log");  
			
			echo "\nkeywords left: ".${$this->model}->total."\n";
		}

		// Connect to database
		utilities::databaseConnect();

		echo "keywords updated: ".count(${$this->model}->updated);

		// If updating keywords
		if($this->model == "keywords")
		{
			// Update finished keywords in DB
			${$this->model}->updateKeywords();                
		}
		// If updating domains
		elseif($this->model == "domains")
		{
			// Update finished domains in DB
			${$this->model}->updateDomains();  			
		}	

		// Update DB with new data
		//$this->updateItems();
		
		// Call processing time
		utilities::benchmark('update items: ', "rankings.log"); 		

		// Retrun total execution time
		return utilities::benchmark(' ', "rankings.log", true, false, true); 		
	} 
	
	// ===========================================================================// 
	// ! Supporting methods                                                       //
	// ===========================================================================//	
	
	// Loop through keywords and return array of urls to scrape
	public function getUrls($items)
	{    
		// Loop through each keyword
		foreach($items as $key => &${$this->class})
		{  
			// Generate the search page url 
			${$this->class}->setSearchUrl();			  		
			
			// If getting domain urls
			if($this->model == "domains")
			{ 			                     	
				// If keyword's search hash is unique
				if(!$urls[${$this->class}->url])
				{    				
					// Add the keyword's search page url to scraping list
					$urls[${$this->class}->url] = ${$this->class}->url;   
				}
			}
			// If getting keyword urls
			else
			{	    			                     			
				// If keyword's search hash is unique
				if(!$urls[${$this->class}->searchHash])
				{    				
					// If no saved search or saved search is from another hour
					if(!file_exists(${$this->class}->searchFile) || date("Y-m-d-G", filemtime(${$this->class}->searchFile)) != date("Y-m-d-G") || filesize(${$this->class}->searchFile) < 500)
					{      
						// Add the keyword's search page url to scraping list
						$urls[${$this->class}->searchHash] = ${$this->class}->url; 
						
						// This is a new search
						${$this->class}->searchType = "new";
					}
				} 
			}	 	
		} 
				
		// Return the url array
		return $urls;				
	}
    
	// Load the correct source for the keyword's search results
	public function getContent($item, $content = false)
	{   
		// If a new url was scraped for this keyword
		if($content)
		{  						        			
			// If the content has valid headers
			if($content['status'] == 'success')
			{   
				// If the search is new for the first keyword
				if($item->searchType == "new")
				{				 				
					// Save the new search file
					$this->searchSave($item, $content);
				}	

				if($this->stat == "pr" && empty($content))
				{
					$content = "0";
				}
				
				// Set the new search as the source
				$search = $content['output']; 						
			}				
		} 
		elseif($this->model != 'domains')
		{    
  			// Load a valid saved search file as the source
			$search = file_get_contents($item->searchFile);
		} 	
		
		return $search;
	}

	// Save search results to a file
	public function searchSave($item, $content)
	{   
		// Set header information to be saved with output
		$save  = "code: ".$content['httpInfo']['http_code'];
		$save .= "\n size: ".$content['httpInfo']['size_download'];
		$save .= "\n\n".$content['output'];
		$save .= "\nthe end\n";
		
		// Save search results to a file
		file_put_contents($item->searchFile, $save, LOCK_EX);		
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

	// Determine the correct regex pattern to use for parsing
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

	// Update database with new items
	private function updateItems()
	{
		// If updating keywords
		if($this->model == "keywords")
		{
			// Update finished keywords in DB
			${$this->model}->updateKeywords();                
		}
		// If updating domains
		elseif($this->model == "domains")
		{
			// Update finished domains in DB
			${$this->model}->updateDomains();  			
		}			
	}

	private function parse($content, $class)
	{
			
	}

	
	
	 
}	    






