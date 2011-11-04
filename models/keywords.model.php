<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** KEYWORDS - Accepts an array of keyword ids in the construct and creates  
// ** a collection of individual keyword objects within.
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-22
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//


// ===========================================================================// 
// ! Keyword collection                                                       //
// ===========================================================================//

class keywords
{
	function __construct($keywords)
	{
		// Instantiate new redis object
		$this->redis = new redis(REDIS_SERPS_IP, REDIS_SERPS_PORT);	
		
		// Loop through items		
		foreach($keywords as $keyword)
		{
 			// Select keyword hash from redis		
			$hash = $this->redis->hGetAll("k:$keyword");
					
			// Create new keyword object from redis hash
			$this->keywords->$keyword = new keyword($hash);

			// Echo count how many keywords are in the object
			$this->total++;
		}
	}

	// Update a keywords hash in redis
	public function update($keyword_id, $key)
	{
		// Update keyword hash
		$this->redis->hmset("k:$keyword_id", array('lastRankGoogle'=>$this->keywords->$keyword_id->rank,
												   'found'=>$this->keywords->$keyword_id->found));

		// Update job list score
		$this->redis->zAdd($key, time(), $keyword_id);		
	}
}

// ===========================================================================// 
// ! Individual keyword objects                                               //
// ===========================================================================//

class keyword 
{     
	
	function __construct($fields)
	{ 
		// Loop through keyword hash and build keyword object
		foreach($fields as $field => $value)
		{
			// Assign field to keyword object
			$this->$field = $value;
		}	
		
		// URL encode the keyword
		$this->urlSafeKeyword();		
   	} 

	// ===========================================================================// 
	// ! Keyword methods                                                          //
	// ===========================================================================// 
	
	// Test a keyword array for keywords needed data
	public function keywordTest()
	{    
  		// The required keys in the keyword array
		$required = array('user_id','keyword','domain_id','domain','country');
				
		// Loop through each required key
		foreach($required as $key)
		{  
			//If the required value are not found
			if(empty($this->$key))
			{   
				// Log bad keyword for review
				//file_put_contents(KEYWORD_ERROR_FILE, var_export($this, TRUE)."\n\n", FILE_APPEND);

				// Do not continue				   
				return false;   		
			} 
		}   
		
		// Keyword object is complete
		return TRUE;          
	} 
	
	// Create a url that can be scraped
	public function urlSafeKeyword() 
	{
		// Encode keyword to be used as part of ur to scrape
		$this->urlSafe = htmlspecialchars(htmlentities(urlencode($this->keyword)));
	}
	
	// Determine whether to grab 10 or 100 results per search 
	public function setResultsCount()
	{    
		// If last ranking was below the 10/100 switch
		if($this->lastRank < NUM_SWITCH_THRESHHOLD && $this->lastRank != 0 || $this->source == 'bing')
		{  
			// Search by 10 results
			$num = 10;
		}
		// Last ranking was over threshhold
		else
		{
			// Search by 100 results
			$num = 100;
		} 	
						 
		// Set search result total
		$this->resultCount = $num;
	}
	
	// Determine which page of search results to scrape 
	public function setSearchOffset()
	{                                             
	 	// If the keyword has a search page offset
		if($this->searchPage)
		{
			// Combine search page offset with result count (ie 200)
			$this->searchOffset = $this->searchPage.substr($this->resultCount, 1, 2);
		}
	}
	
	// Build the search engine results page for the keyword
	public function setSearchUrl($source)
	{   
		// Set the data source (google,bing,pr etc)
		$this->source = $source;

		// Get the current search hash 
		$this->setSearchHash(); 
		
		// Check for a search page offset 
		$this->setSearchOffset();
		                          
		// Set the location of the keyword's saved search file
		$this->searchFile = SAVED_SEARCH_DIR.$this->searchHash.".html";
		
		if($this->source == "google")
		{
			// Build the google search results page url
			$this->url  = "http://www.google".$this->country;
			$this->url .= "/search?q=".$this->urlSafe;
			$this->url .= "&num=".$this->resultCount;  		

			// If search results page offset is present
			if($this->searchOffset)
			{  
			 	// Add the ofset to the url
				$this->url .= "&start=".$this->searchOffset;	   
			}	
	    }
		elseif($this->source == "bing")
		{                			
			// Build the bing search results page url
			$this->url  = "http://www.bing.com";
			$this->url .= "/search?q=".$this->urlSafe;
			
				// Check for a search page offset 
			$this->setSearchOffset();			
			
			// If search results page offset is present
			if($this->searchOffset)
			{
				$this->url .= "&first=".$this->searchOffset;	   
			}			
		}
	}
	
	// Create a hash for the keyword's saved search file name
	public function setSearchHash()
	{
		// Naming convention for the file
		$searchHash  = $this->keyword;
		$searchHash .= $this->source;
		$searchHash .= $this->country;
		$searchHash .= $this->resultCount;
		$searchHash .= $this->searchPage; 
		
		// Calculate hash for filename
		$searchHash = crc32($searchHash);
		
		// Format hash
		$searchHash = vsprintf("%u", $searchHash);
		
		// Set keyword's saved search file hash
		$this->searchHash = $searchHash; 
        		
		// Add hash to saved searches array (for final database update)
		$this->savedSearches[$searchHash] = $searchHash;
	}
	
	public function uniqueId()
	{
		$this->uniqueId = "id_".$this->keyword_id;
	}	
	
	// ===========================================================================// 
	// ! Rank change notifications                                                //
	// ===========================================================================// 		

	// Set notifications for keyword
	public function setNotification()
	{
		// If notifications settings set for keyword
		if($this->notifications)
		{
			// Check for a rank change
			$change = $this->rankChange();
			
			// If there is a rank change
			if($change)
			{
				// Check if a notification is triggered based on keyword notification settings
				if($this->triggerNotification($change))
				{
					// Build notification array
					$notify['current'] =  intval($this->rank);
					$notify['last'] = intval($this->lastRank);
					$notify['change'] = $change;

					// Set notificaton to send for keyword
					$this->notify = serialize($notify);					
				}
			}	
		}	
	}

	// Determine keyword ranking position change
	private function rankChange()
	{
		$current = $this->noZeros($this->rank, 500);
		$last = $this->noZeros($this->lastRank, 500);

		// If there was a rank change
		if($current!= $last)
		{
			// Get difference between rankings		
			$changeAmount = abs($current - $last);

			// Negative change
			if($current > $last)
			{
				// Change to negative int
				$changeAmount = -$changeAmount;			
			}
			// Positive change
			else
			{
				// Change to negative int
				$changeAmount = "+$changeAmount";					
			}

			return $changeAmount;
		}
	}

	// If number equals 0 change to max number
	public function noZeros($num, $max = 500)
	{		
		// Unset a 0 ranking
		if($num == 0)
		{
			$num = $max;
		}	
		
		return $num;
	}		

	// Check if a rank change triggers a notification
	private function triggerNotification($change)
	{
		//echo "testing trigger for: $this->keyword_id \n";
		// Get keywords notification settings
		$n = unserialize($this->notifications);
							
		// If any rank change should should trigger an alert
		if($n['any_notification'])
		{
			return true;	
		}	
		echo "failed any check\n";	
		
		// Monitor #1 position
		if($n['google_1'])
		{
			// If the number one ranking position is gained or lost
			if($this->rank == 1 || $this->rank != 1 && $this->lastRank == 1)
			{
				return true;
			}
		}

		// Monitor top 10 poistions
		if($n['google_top_ten'])
		{
			// If a top 10 ranking position is gained or lost
			if($this->rank <= 10 && $this->lastRank > 10 || $this->rank > 10 && $this->lastRank <= 10)
			{
				return true;
			}
		}	
		
		// Monitor predefined change amount
		if($n['change_amount'])
		{
			// If change amount is greater or equal to user defined change amount to watch
			if($change >= $n['change_amount'])
			{
				return true;
			}
		}	
		
		// Monitor predefined change amount
		if($n['change_range_1'] && $n['change_range_2'])
		{
			// Determine which number 
			if($n['change_range_1'] > $n['change_range_2'])
			{
				// i.e 1
				$rankHigh = $n['change_range_2'];
				// i.e 10
				$rankLow = $n['change_range_1'];
			}
			// switch the values
			else
			{
				// i.e 1
				$rankHigh = $n['change_range_1'];
				// i.e 10
				$rankLow = $n['change_range_2'];			
			}

			if($this->rank >= $rankLow && $this->rank <= $rankHigh)
			{
				return true;
			}
		}						
	}
}