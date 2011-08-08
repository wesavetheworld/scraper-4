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
	// What job is the worker performing
	private $task;

	// Data model to use
	private $model;	

	// Search engine
	private $engine;

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
		utilities::notate("Job started", $this->task.".log");		  		   	 			
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	 
	
	public function worker($data)
	{  	
		// Get the items model
		$this->model = $data['model'];	
		
   		// Remove "s" from object for singular item class
		$this->class = substr($this->model, 0, -1); 	
		
		// Task is this worker performing
		$this->task = $data['task'];
		
		// Search engine used (for proxy use)
		$this->engine = $data['engine'];
		
		echo "engine: ". $this->engine;

		print_r($data);
		
		return true;					

		// Include items data model
	 	require_once("models/".$this->model.".model.php"); 		
				
		// Get the keywords from the job data				
		$jobData = unserialize($data['jobData']);			

		// Get the items from the job data				
		$items = $jobData[$this->model];

		// Set the task for the data model
		$items->task = $this->task;			
			 		   	
		// Call processing time
		utilities::benchmark('items selected: ', $this->task.".log"); 		
		        		        
		// Loop for as long as there are keywords left
		while($items->total > 0)
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
			$scrape->task = $this->task;

			// Build an array of search engine urls to scrape
			$scrape->urls = $this->getUrls($items->{$this->model}); 	
									
			// Execute the scraping
			$scrape->curlExecute();
			
			// Call processing time
			utilities::benchmark('scraping content: ', $this->task.".log");

			// Loop through each keyword
			foreach($items->{$this->model} as $key => &$item)
			{
				// Create new parsing object
				$parse = new parse;	

				// Content for domains
				if($this->model == "domains")
				{
					// If a valid search results page can be loaded (new scrape or saved file)
					if($content = $this->getContent($item, $scrape->results[$item->url]))
					{  						
						if($this->task == "backlinks")
						{
							// Find the keyword's domain in one of the ranking urls
							$parse->findElements(PARSE_PATTERN_BACKLINKS, $content); 
							
							// Set backlinks for domain
							$item->backlinks =  str_replace(",","",$parse->elements[0]); 
						}
						elseif($this->task == "pr")
						{    
							// Set the pagerank for domain
							$item->pr = $parse->pageRank($content); 

							echo $item->pr."\n";
						} 
						elseif($this->task == "alexa")
						{    
							// Set the alexa rank for domain
							$item->alexa = $parse->alexa($content); 
									
							echo "alexa: ".$item->alexa."\n";						
						}

						// Add keyword to completed list
						$items->updated[$key] = $item;

						// Remove keyword from keyword id array
						unset($items->{$this->model}->$key); 						

						// Decrease total domains remaining
						$items->total--; 

						echo  "\ndomains remaining: ". $items->total."\n";
					}	
					else
					{
						echo $item->url."\n";
					}
				}	
				// Content for keywords
				elseif($this->model == "keywords")
				{
					// If a valid search results page can be loaded (new scrape or saved file)
					if($content = $this->getContent($item, $scrape->results[$item->searchHash]))
					{  							
						// Find the keyword's domain in one of the ranking urls
						$parse->findElements($this->parsePattern(), $content)->findInElements($item->domain);			 
									   				
						// If domain was found or keyword on last search page
						if($parse->found || $item->searchPage == SEARCH_DEPTH - 1)
						{   
							// If a ranking was found 
							if($parse->found)
							{   
								// Set new keyword rank (amount of results per page + position on current page)
								$item->rank = $item->searchOffset + $parse->position; 
															
								// Set the matching url that was found ranking
								$item->found = $parse->found;  
							}
							// If no ranking was found
							else
							{    
								// "0" is used for "not found"
								$item->rank = 0;   
							}  
													
							// Calibrate keyword ranking (10/100 results)
							$this->calibration($item);   

							// Add keyword to completed list
							$items->updated[$key] = $item;

							// Remove keyword from keyword id array
							unset($items->{$this->model}->$key);  
						    
							// Decrease keywords remaining by one
							$items->total--; 
						}
						// Domain was not found ranking
						else
						{ 
							// Increase search results page for next scrape
							$item->searchPage++; 						
						} 
					}	
				}					
			} 

			// Call processing time
			utilities::benchmark('Parse all content: ', $this->task.".log");  
			
			echo "\nkeywords left: ".$items->total."\n";
		}

		// Connect to database
		utilities::databaseConnect();

		echo $this->model." updated: ".count($items->updated);

		// If updating keywords
		if($this->model == "keywords")
		{
			// Update finished keywords in DB
			$items->updateKeywords();                
		}
		// If updating domains
		elseif($this->model == "domains")
		{
			// Update finished domains in DB
			$items->updateDomains();  			
		}	

		// Update DB with new data
		//$this->updateItems();
		
		// Call processing time
		utilities::benchmark('update items: ', $this->task.".log"); 		

		// Retrun total execution time
		return utilities::benchmark(' ', $this->task.".log", true, false, true); 		
	} 
	
	// ===========================================================================// 
	// ! Supporting methods                                                       //
	// ===========================================================================//	
	
	// Loop through keywords and return array of urls to scrape
	public function getUrls($items)
	{    
		// Loop through each keyword
		foreach($items as $key => &$item)
		{  
			// Generate the search page url 
			$item->setSearchUrl();			  		
			
			// If getting domain urls
			if($this->model == "domains")
			{ 			                     	
				// If keyword's search hash is unique
				if(!$urls[$item->url])
				{    				
					// Add the keyword's search page url to scraping list
					$urls[$item->url] = $item->url;   
				}
			}
			// If getting keyword urls
			else
			{	    			                     			
				// If keyword's search hash is unique
				if(!$urls[$item->searchHash])
				{    				
					// If no saved search or saved search is from another hour
					if(!file_exists($item->searchFile) || date("Y-m-d-G", filemtime($item->searchFile)) != date("Y-m-d-G") || filesize($item->searchFile) < 500)
					{      
						// Add the keyword's search page url to scraping list
						$urls[$item->searchHash] = $item->url; 
						
						// This is a new search
						$item->searchType = "new";
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
				
				// Set the new search as the source
				$search = $content['output']; 

				if($this->task == "pr" && empty($search))
				{
					$search = "99";
				}				
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
			$items->updateKeywords();                
		}
		// If updating domains
		elseif($this->model == "domains")
		{
			// Update finished domains in DB
			$items->updateDomains();  			
		}			
	}

	private function parse($content, $class)
	{
			
	}

	
	
	 
}	    






