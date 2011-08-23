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

	// Contains the objects for the task
	private $items;

	// Will be set to true when the job is complete
	public $complete = false;

	// ===========================================================================// 
	// ! Dependencies                                                             //
	// ===========================================================================// 
	
	function __construct()
	{           		
		// Include serp parsing class
		require_once('classes/parse.class.php');

		// Include scraping class
		require_once('classes/scrape.class.php'); 	

		// Include proxy data model
		require_once('models/proxies.model.php'); 		
		
		// Reset benchmarking
		utilities::benchmark(false, false, false, true);		  		   	 			
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	 
	
	public function worker($data)
	{  	
		// Construct job object
		$this->buildJob($data);	
		        		        
		// Loop for as long as there are keywords left
		while($this->items->total > 0)
		{    		
			// Scrape content for items
			$this->scrapeContent();

			// Parse the scraped content
			$this->parseContent();

			// If job has run for too long
			if(utilities::benchmark('checkTimeOut'))
			{
				exit("\nworker timed out\n");
			}
		}

		// Update DB with new data
		$this->updateItems();

		// Job has been completed
		$this->complete = TRUE;

		die('finished!! :)');
	} 
	// ===========================================================================// 
	// ! Core worker functions                                                    //
	// ===========================================================================//
	
	// Construct job object
	private function buildJob(&$data)
	{
		// Get the items model
		$this->model = $data['model'];	
		
   		// Remove "s" from object for singular item class
		$this->class = substr($this->model, 0, -1); 
		
		// Include items data model
	 	require_once("models/".$this->model.".model.php"); 
	 					
		// Get the keywords from the job data				
		$jobData = unserialize($data['jobData']);						
		
		// Task is this worker performing
		$this->task = $jobData['task'];
		
		// Search engine used (for proxy use)
		$this->engine = $jobData['engine'];					

		// Get the items from the job data				
		$this->items = $jobData[$this->model];

		// Set the task for the data model
		$this->items->task = $this->task;		
	}

	private function scrapeContent()
	{
		// Create new scraping instance
		$this->scrape = new scraper; 

		// Set search engine to scrape
		$this->scrape->engine = $this->engine;

		// If a domain stats connection
		$this->scrape->task = $this->task;

		// Build an array of search engine urls to scrape and the proxies needed
		$prepare = $this->getUrls($this->items->{$this->model}); 	

		// Build an array of search engine urls to scrape
		$this->scrape->urls = $prepare['urls']; 				
		
		// Build an array of proxies to use for scraping
		$this->scrape->proxies = $prepare['proxies']; 				
								
		// Execute the scraping
		$this->scrape->curlExecute();	

		// Update status of proxies uses in scraping
		$this->updateProxies();		
	}

	private function parseContent()
	{	
		// Loop through each keyword
		foreach($this->items->{$this->model} as $key => &$item)
		{		
			// Create new parsing object
			$this->parse = new parse;	

			// Content for domains
			if($this->model == "domains")
			{
				$this->parseDomains($key, $item);
			}	
			// Content for keywords
			elseif($this->model == "keywords")
			{
				$this->parseKeywords($key, $item);
			}
		}								
	}

	private function parseKeywords($key, &$item)
	{
		// If a valid search results page can be loaded (new scrape or saved file)
		if($content = $this->getContent($item, $this->scrape->results[$item->searchHash]))
		{  							
			// Find the keyword's domain in one of the ranking urls
			$this->parse->findElements($this->parsePattern(), $content)->findInElements($item->domain);			 
						   				
			// If domain was found or keyword on last search page
			if($this->parse->found || $item->searchPage == SEARCH_DEPTH - 1)
			{   
				// If a ranking was found 
				if($this->parse->found)
				{   
					// Set new keyword rank (amount of results per page + position on current page)
					$item->rank = $item->searchOffset + $this->parse->position; 
												
					// Set the matching url that was found ranking
					$item->found = $this->parse->found;  
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
				$this->items->updated[$key] = $item;

				// Remove keyword from keyword id array
				unset($this->items->{$this->model}->$key);  
			    
				// Decrease keywords remaining by one
				$this->items->total--; 
			}
			// Domain was not found ranking
			else
			{ 
				// Increase search results page for next scrape
				$item->searchPage++; 						
			} 
		}
		// No scraped content returned
		else
		{
			// Remove proxy used for this item
			unset($item->proxy);
		}	
	}

	private function parseDomains($key, &$item)
	{
		// If valid output is available
		$content = $this->getContent($item, $this->scrape->results[$item->url]);

		// If a valid search results page can be loaded (new scrape or saved file)
		if($content || $item->bad == 10)
		{  	
			if($item->bad != 10)
			{					
				if($this->task == "backlinks")
				{
					// Find the keyword's domain in one of the ranking urls
					$this->parse->findElements(PARSE_PATTERN_BACKLINKS, $content); 
					
					// Set backlinks for domain
					$item->backlinks =  str_replace(",","",$this->parse->elements[0]); 
				}
				elseif($this->task == "pr")
				{    
					// Set the pagerank for domain
					$item->pr = $this->parse->pageRank($content); 
					
					echo "pr: ".$item->pr."\n";
				} 
				elseif($this->task == "alexa")
				{    
					// Set the alexa rank for domain
					$item->alexa = $this->parse->alexa($content); 
							
					echo "alexa: ".$item->alexa."\n";						
				}
			}

			// Add keyword to completed list
			$this->items->updated[$key] = $item;

			// Remove keyword from keyword id array
			unset($this->items->{$this->model}->$key); 						

			// Decrease total domains remaining
			$this->items->total--; 

			echo  "\ndomains remaining: ". $this->items->total."\n";
		}	
		else
		{
			$item->bad++;
		}
	}

	// ===========================================================================// 
	// ! Supporting methods                                                       //
	// ===========================================================================//	
	
	// Loop through keywords and return array of urls to scrape
	public function getUrls($items)
	{    
		// Get proxies
		$this->getProxies(count($items));

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
					// Add the keyword's search page url to scraping list
					$urls[$item->searchHash] = $item->url; 

					// If no proxy set for this keyword/url yet
					if(!$item->proxy)
					{
						$item->proxy = current($this->proxies->proxies);

						// Add keywords proxy to list to be used for scraping	
						$proxies[$item->searchHash] = $item->proxy;	

						// Move to next proxy
						next($this->proxies->proxies);	
						
						echo "proxy added to keyword: ".$item->proxy."\n";	
					}		
					
					// This is a new search
					$item->searchType = "new";
				} 
			}	 	
		} 
				
		// Return the url and proxy arrays
		return array('urls' => $urls, 'proxies'=> $proxies);				
	}

	public function getProxies($count)
	{
		// Instantiate new proxies object
		$this->proxies = new proxies($this->engine);

		// Select proxies for use
		$this->proxies->selectProxies($count);		
	}

	public function updateProxies()
	{
		// Transfer proxy statuses from scraper class to proxy model
		$this->proxies->proxiesBlocked = $this->scrape->proxiesBlocked;
		$this->proxies->proxiesDenied = $this->scrape->proxiesDenied;
		$this->proxies->proxiesTimeout = $this->scrape->proxiesTimeout;
		$this->proxies->proxiesDead = $this->scrape->proxiesDead;
		$this->proxies->proxiesGood = $this->scrape->proxiesGood;

		// Update proxy database
		$this->proxies->updateProxyUse();
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
					//$this->searchSave($item, $content);
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
			//$search = file_get_contents($item->searchFile);
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
		file_put_contents($item->searchFile, $save);		
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
			$this->items->updateKeywords();                
		}
		// If updating domains
		elseif($this->model == "domains")
		{
			// Update finished domains in DB
			$this->items->updateDomains();  			
		}				
	}	
}	    






