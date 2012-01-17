<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");
 
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
	function __construct($keywords, $source)
	{
		// Connect to serps db
		$this->serps = new redis(REDIS_SERPS_IP, REDIS_SERPS_PORT, REDIS_SERPS_DB);	
		
		// Connect to boss db
		$this->boss = new redis(BOSS_IP, BOSS_PORT, BOSS_DB);		
		
		// Loop through items		
		foreach($keywords as $keyword)
		{
			// The first letter of the source used for the ranking hash key
			$this->sourceKey = substr($source, 0, 1);

			// The keyword's hash field for today's ranking
			$this->rankKey = $this->sourceKey.":".date("Y-m-d");

			// The keyword's hash field for yesterday's ranking			
			$yesterday = $this->sourceKey.":".date("Y-m-d", time()-(86400));

			// The keyword's hash field for the matching url found ranking			
			$this->matchKey = $k."m:".date("Y-m-d");

			// Hash fields to select
			$fields =  array('keyword', 
						    'keyword_id',
                            'domain', 
                            'country',
                            $this->rankKey,
                            $yesterday
                            );

 			// Select keyword hash from redis		
			$hash = $this->serps->hMGet("k:$keyword", $fields);                                         
					
			// Create new keyword object from redis hash
			$this->keywords->$keyword = new keyword($hash, $fields);

			// Echo count how many keywords are in the object
			$this->total++;
		}
	}

	// ===========================================================================// 
	// ! Updating redis keyword data                                              //
	// ===========================================================================//	

	// Update a keywords hash in redis
	public function update($keyword_id, $key)
	{
		// Update mysql serp data
		$this->updateMySQL($this->keywords->$keyword_id);

		// why am I doing this?		
		$this->keywords->$keyword_id->updateCount = intval($this->keywords->$keyword_id->updateCount) + 1;	

		// Update keyword hash
		$this->serps->hmset("k:$keyword_id", array( $this->rankKey => $this->keywords->$keyword_id->rank,
													$this->matchKey => $this->keywords->$keyword_id->found,
													'updateCount' => $this->keywords->$keyword_id->updateCount	
													));

		// Update job list score
		$this->boss->zAdd($key, time(), $keyword_id);		
	}

	// ===========================================================================// 
	// ! Updating MySQL keyword data                                              //
	// ===========================================================================//
	
	// Update keywords table with new keyword info
	public function updateMySQL($keyword)
	{
		// If no connection to the database yet(worker)
		if(!$this->db)
		{
			// Establish DB connection
			$this->db = utilities::databaseConnect(DB_HOST, DB_SERP_USER, DB_SERPS_PASS, DB_NAME_SERPS);		
		}
 
		// If this keyword has no ranking yet
		if(!isset($keyword->rank) && $keyword->rank != '0')
		{   
			// Skip keyword
			continue;
		}

		// If keyword's tracking data was updated successfully
		if($this->updateRanking($keyword))
		{
			// If updating google and the keyword
			if($keyword->source == "google")
			{
				// Save any notifications for keyword
				$setNotify = " notify = '".$keyword->notify."',";
			}

			//echo "SAVE NOTIFY for $keyword->keyword_id: $keyword->notify \n";

			// Update keywords table with update time and notifications
			$query = "	UPDATE 
							keywords 
						SET 
					  		$setNotify 
					  		".$keyword->source."_status = NOW(),  
							calibrate = '".$keyword->calibrate."',
							check_out = 0,
					  		time = NOW(), 
					  		date = '".date("Y-m-d")."' 
					  WHERE 
					  	keyword_id='".$keyword->keyword_id."'";  
										  
			// If keyword update successful
			mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON UPDATING KEYWORDS: ".mysql_error());		
		}	
	} 
	
	// Update existing row in tracking table with new rankings
	private function updateRanking($keyword)
	{	      		
		$query = "	INSERT INTO 
						tracking 
						(keyword_id,".$keyword->source.",
						".$keyword->source."_match,
						dupecount,
						date) 
			      VALUES (
						'".$keyword->keyword_id."',
						'".$keyword->rank."',
						'".mysql_real_escape_string($keyword->found)."',
						'0',
						'".date("Y-m-d")."'
			            )
			      ON DUPLICATE KEY UPDATE 
			      		".$keyword->source." = '".$keyword->rank."', 
					 	".$keyword->source."_match = '".$keyword->found."'";			          				 	
		
		// Execute update query
		return mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON TRACKING: ".mysql_error());
	}  	
}

// ===========================================================================// 
// ! Individual keyword objects                                               //
// ===========================================================================//

class keyword 
{     
	
	function __construct($hash, $fields)
	{ 
		$key = 0;

		// Loop through keyword hash and build keyword object
		foreach($hash as $value)
		{
			// Assign field to keyword object
			$this->$fields[$key++] = $value;
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

	// Set the last rank based on the current source
	public function setLastRank()
	{
		// The first letter of the source used for the ranking hash key
		$sourceKey = substr($this->source, 0, 1);

		// The keyword's hash field for today's ranking
		$today = $sourceKey.":".date("Y-m-d");

		// The keyword's hash field for yesterday's ranking			
		$yesterday = $sourceKey.":".date("Y-m-d", time()-(86400));		

		// If there is a ranking field for today
		if($this->$today)
		{
			// Use today's rank as the last rank
			$this->lastRank = $this->$today;	
		}	
		elseif($this->$yesterday)
		{
			// Use yesterday's rank as the last rank
			$this->lastRank = $this->$yesterday;				
		}
	}
	
	// Build the search engine results page for the keyword
	public function setSearchUrl($source)
	{   
		// Set the data source (google,bing,pr etc)
		$this->source = $source;

		// Set the last rank
		$this->setLastRank();

		// Check the results count to get for the keyword
		$this->setResultsCount();

		// Get the current search hash 
		$this->setSearchHash(); 
		
		// Check for a search page offset 
		$this->setSearchOffset();
		
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