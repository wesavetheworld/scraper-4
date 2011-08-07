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
		$model = $data['model'];

		// Include items data model
	 	require_once("models/".$model.".model.php"); 		
				
		// Get the keywords from the job data				
		$jobData = unserialize($data['jobData']);	

   		// Remove "s" from object for singular item class
		$class = substr($model, 0, -1); 		

		echo "model: ".$model;

		echo "\nclass: ".$class;

		echo "\ninclude: models/$model.model.php\n";

		echo "\n\n";	
		
		die();				

		// Set the search engine to use
		$this->engine = $jobData['engine'];				

		// Get the items from the job data				
		${$model} = $jobData[$model];

		print_r($jobData);
		//print_r(${$model});

		die("the end");
	 		   	
		// Call processing time
		utilities::benchmark('items selected: ', "rankings.log"); 		
		        		        
		// Loop for as long as there are keywords left
		while(${$model}->total > 0)
		{    
			// Check killswitch
			utilities::checkStatus();
			 		
			// Connect to database
			utilities::databaseConnect();		
					 		
			// Create new scraping instance
			$scrape = new scraper; 

			// Set search engine to scrape
			$scrape->engine = $this->engine;

			// Build an array of search engine urls to scrape
			$scrape->urls = $this->getUrls(${$model}->${$class}); 
									
			// Execute the scraping
			$scrape->curlExecute();
			
			// Call processing time
			utilities::benchmark('scraping content: ', "rankings.log");
			
			// Loop through each keyword
			foreach(${$model}->${$class} as $key => &$item)
			{
				// If a valid search results page can be loaded (new scrape or saved file)
				if($content = $this->getContent($item, $scrape->results[$keyword->searchHash]))
				{  			
					// Parse scraped content
					$this->parse($content);		
 	   			}	
			} 

			// Call processing time
			utilities::benchmark('Parse all content: ', "rankings.log");  
			
			echo "\nkeywords left: ".$keywords->total."\n";
		}

		// Connect to database
		utilities::databaseConnect();

		echo "keywords updated: ".count($keywords->updated);

		// Update DB with new data
		$this->updateItems();
		
		// Call processing time
		utilities::benchmark('update keywords: ', "rankings.log"); 		

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
		foreach($items as $key => &$item)
		{  
			// Generate the search page url 
			${$item}->setSearchUrl();			  		
			 			                     			
			// If keyword's search hash is unique
			if(!$urls[${$item}->searchHash])
			{    				
				// If no saved search or saved search is from another hour
				if(!file_exists(${$item}->searchFile) || 
					date("Y-m-d-G", filemtime(${$item}->searchFile)) != date("Y-m-d-G") || 
					filesize(${$item}->searchFile) < 500)
				{      
					// Add the keyword's search page url to scraping list
					$urls[${$item}->searchHash] = ${$item}->url; 
					
					// This is a new search
					${$item}->searchType = "new";
				}
			}  	
		} 
				
		// Return the url array
		return $urls;				
	}
    
	// Load the correct source for the keyword's search results
	public function getContent($keyword, $scrapedContent = false)
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

		//$content = "No at symbols here bitch! ";
		
		// Save search results to a file
		file_put_contents($keyword->searchFile, $content, LOCK_EX);		
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
		if(MODEL == "keywords")
		{
			// Update finished keywords in DB
			$keywords->updateKeywords();                
		}
		// If updating domains
		elseif(MODEL == "domains")
		{
			// Update finished domains in DB
			$domains->updateDomains();  			
		}			
	}

	private function parse()
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
		else
		{
			// Find the keyword's domain in one of the ranking urls
			$parse->findElements($this->parsePattern(), $searchResults)->findInElements($keyword->domain);			 
						   				
			// If domain was found or keyword on last search page
			if($parse->found || ${$item}->searchPage == SEARCH_DEPTH - 1)
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

				// Add keyword to completed list
				$keywords->updated[$key] = $keyword;

				// Remove keyword from keyword id array
				unset($keywords->keywords->$key);  
			    
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

	
	
	 
}	    






