<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** WORKER - Checks out jobs from the jobServer and performs the work
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
	}
	
	// ===========================================================================// 
	// ! Main rankings method                                                     //
	// ===========================================================================//	 
	
	public function work($job)
	{  	
		// Construct job object
		$this->buildJobNew($job);			
		        
		// Loop for as long as there are keywords left
		while($this->items->total > 0)
		{    		
			// Scrape content for items
			$this->scrapeContent();

			// Parse the scraped content
			$this->parseContent();
		}

		// Update DB with new data
		//$this->updateItems();

		echo "\njob complete\n\n\n";

		// Job has been completed
		$this->complete = TRUE;
	} 
	
	// ===========================================================================// 
	// ! Core worker functions                                                    //
	// ===========================================================================//

	private function buildJobNew($job)
	{				
		// Decode json array				
		$job = json_decode($job);

		// Get the items model
		$this->model = $this->setModel($job->source);

		// Items parent class
		$class = $this->model;		

		// Source being scraped
		$this->source = $job->source;						
		
		// Source schedule (hourly, daily)
		$this->schedule = $job->schedule;						

		// Include items data model
	 	require_once("models/$class.model.php"); 
	 	
		// Instantiate new object
		$this->items = new $class($job->items);	 			
	}

	private function setModel($source)
	{
		if(in_array($source, array("google", "bing")))
		{
			return "keywords";
		}
		else
		{
			return "domains";
		}
	}

	private function scrapeContent()
	{
		// Create new scraping instance
		$this->scrape = new scraper; 

		// If a domain stats connection
		$this->scrape->task = $this->source;    	

		// Build an array of search engine urls to scrape
		$this->scrape->urls = $this->getUrls($this->items->{$this->model}, $this->items->total); 				
		
		// Build an array of proxies to use for scraping
		$this->scrape->proxies = $this->getProxies($this->scrape->urls, $this->items->{$this->model});
		
		// echo "urls: \n";
		// print_r($this->scrape->urls);
		// echo "\n";

		// echo "urls: \n";
		// print_r($this->scrape->proxies);
		// echo "\n";
		
		// die();		
					
								
		// Execute the scraping
		$this->scrape->curlExecute();		
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
					echo "good scrape!\n";
				
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

				// If scraping google
				if($this->source == 'google')
				{
					//echo "checking notifications PT 1\n";
					// See if a rank change notification should be sent
					$item->setNotification();
					//echo "notification for ".$item->keyword_id." : ".$item->notify."\n";
				}	
										
				// Calibrate keyword ranking (10/100 results)
				$this->calibration($item);   

				// Add keyword to completed list
				//$this->items->updated[$key] = $item;

				// If this item has it's own proxy
				if($item->proxy)
				{
					// Check proxy back in.
					$this->proxies->update($item->proxy['proxy']);
				}	

				// Update and checkin keyword to redis
				$this->items->update($item->keyword_id, "$this->source:$this->schedule");

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
			echo "bad scrape!\n";

			// If this item has it's own proxy
			if($item->proxy)
			{		
				// Check proxy back in. Set status to block if scraper code says
				$this->proxies->update($item->proxy['proxy'], $this->scrape->results[$item->searchHash]['blocked']);

				// Remove proxy used for this item so that a new one will be selected for in the next loop
				unset($item->proxy);
			}	
		}	
	}

	private function parseDomains($key, &$item)
	{
		// If valid output is available
		$content = $this->getContent($item, $this->scrape->results[$item->url]);

		// If a valid search results page can be loaded (new scrape or saved file)
		if($content || $item->bad == 10)
		//if($content)
		{  	
			echo "good scrape \n";

			if($item->bad != 10)
			{					
				if($this->source == "backlinks")
				{
					// Find the keyword's domain in one of the ranking urls
					$this->parse->findElements(PARSE_PATTERN_BACKLINKS, $content); 
					
					// Set backlinks for domain
					$item->backlinks =  str_replace(",","",$this->parse->elements[0]); 
				}
				elseif($this->source == "pr")
				{    
					// Set the pagerank for domain
					$item->pr = $this->parse->pageRank($content); 

					echo "pr:$item->pr\n";
				} 
				elseif($this->source == "alexa")
				{    
					// Set the alexa rank for domain
					$item->alexa = $this->parse->alexa($content); 
				}
			}
			// If all bad searches(something must be wrong with the domain)
			else
			{
				if($this->source == "backlinks")
				{	
					// Set backlinks for domain
					$item->backlinks =  NULL; 
				}
				elseif($this->source == "pr")
				{    
					// Set the pagerank for domain
					$item->pr = NULL; 
				} 
				elseif($this->source == "alexa")
				{    
					// Set the alexa rank for domain
					$item->alexa = NULL; 
				}				
			}

			// Add keyword to completed list
			$this->items->updated[$key] = $item;

			// If this item has it's own proxy
			if($item->proxy)
			{
				// Check proxy back in.
				$this->proxies->update($item->proxy['proxy']);
			}				

			// Remove keyword from keyword id array
			unset($this->items->{$this->model}->$key); 						

			// Decrease total domains remaining
			$this->items->total--; 
		}	
		else
		{
			// If this item has it's own proxy
			if($item->proxy)
			{			
				// Check proxy back in. Set status to block if scraper code says
				$this->proxies->update($item->proxy['proxy'], $this->scrape->results[$item->url]['blocked']);				

				// Remove proxy used for this item so that a new one will be selected for in the next loop
				unset($item->proxy);
			}	

			echo "bad scrape \n";

			$item->bad++;			
		}
	}

	// ===========================================================================// 
	// ! Supporting methods                                                       //
	// ===========================================================================//
	
	// Loop through keywords and return array of urls to scrape
	public function getUrls(&$items, $total)
	{    	
		// Reset proxy list from any previous loops
		$this->proxyList = array();

		// Loop through each keyword
		foreach($items as $key => &$item)
		{  
			// Generate the search page url 
			$item->setSearchUrl($this->source);	

			// print_r($item);
			// die();

			// If getting domain urls
			if($this->model == "domains")
			{ 			                     	
				// If keyword's search hash is unique
				if(!$urls[$item->url])
				{    		
					// If proxy set for this domain/url already
					if($item->proxy)
					{
    					$this->proxyList[$item->url] = $item->proxy;
					}						
						
					// Add the keyword's search page url to scraping list
					$urls[$item->url] = $item->url;  
					
					$proxies[$item->url] = $item->proxy;						
				}
			}
			// If getting keyword urls
			else
			{	  			                     			
				// If keyword's search hash is unique
				if(!$urls[$item->searchHash])
				{    				
					// If proxy set for this keyword/url already
					if($item->proxy)
					{
    					$this->proxyList[$item->searchHash] = $item->proxy;
					}						
								
					// Add the keyword's search page url to scraping list
					$urls[$item->searchHash] = $item->url; 					
					
					// This is a new search
					$item->searchType = "new";
				}
			}	 
		} 
								
		// Return the url array
		return $urls;				
	}	
	
	// Loop through keywords and return array of urls to scrape
	public function getProxies($urls, &$items)
	{  					
		$need = count($urls) - count($this->proxyList);
			
		echo "keywords:".$this->items->total." | urls to scrape: ".count($urls)." | keywords with proxies already: ".count($this->proxyList)."\n";	
		echo "total: $need\n";

		if($need != 0)
		{		
			// Instantiate new proxies object
			$this->proxies = new proxies($this->source);
					
			// Select proxies for urls with no proxies attached yet
			$this->proxies->select($need);	
			
			echo "proxies selected: ".count($this->proxies->proxies)."\n";	

			// Loop through urls
			foreach($items as $key => &$item)
			{
				// If getting domain urls
				if($this->model == "domains")
				{ 		
					// If url has no proxy
					if(!$this->proxyList[$item->url])
					{
						$item->proxy = array_pop($this->proxies->proxies);
						$this->proxyList[$item->url] = $item->proxy;
					}
				}
				else
				{
					// If url has no proxy
					if(!$this->proxyList[$item->searchHash])
					{
						$item->proxy = array_pop($this->proxies->proxies);
						$this->proxyList[$item->searchHash] = $item->proxy;
					}			
				}	
			}

			if(count($this->proxies->proxies) > 0)
			{
				echo "FUCK! popping left: ".count($this->proxies->proxies)."\n";
			}
		}	

		// Returned the proxy array
		return $this->proxyList;		
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

				if($this->source == "pr" && empty($search))
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
		if($this->source == "google")
		{
			return PARSE_PATTERN_GOOGLE;
		}
		// Search engine is bing
		elseif($this->source == "bing")
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






