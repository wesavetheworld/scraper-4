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
		
		// Connect to serps redis
		$this->predis = utilities::connect(REDIS_SERPS_IP, REDIS_SERPS_PORT, REDIS_SERPS_DB);		
		
		// Loop through items		
		foreach($keywords as $keyword_id)
		{
			// The first letter of the source used for the ranking hash key
			$this->sourceKey = substr($source, 0, 1);

			// The 2 keys to update in redis for the keyword
			$this->keys[$keyword_id] = array( "hash" => "k:$keyword_id",
						 		              "statsToday" => "k:$keyword_id:".date("Y-m-d"),
						 		              "statsYesterday" => "k:$keyword_id:".date("Y-m-d", time()-(86400)));

			// The keyword's hash field for yesterday's ranking			
			$yesterday = $this->sourceKey.":".date("Y-m-d", time()-(86400));

			// The keyword's hash field for the matching url found ranking			
			$this->matchKey = $this->sourceKey."m:".date("Y-m-d");

			$keys = $this->keys[$keyword_id]; 
			$data['source'] =  $this->sourceKey;

			// Create redis pipeline
			$response = $this->predis->pipeline(function($pipe) use ($keys, $data) 
			{
				// Select keyword hash
				$pipe->hgetall($keys['hash']);

				// Select keyword stats for today
				$pipe->hmget($keys['statsToday'], array($data['source']));

				// Select keyword stats for today
				$pipe->hmget($keys['statsYesterday'], array($data['source']));				
			});	

			// Creat the keyword object.
			$keyword = new keyword($response, $source);

			// If keyword object is intact
			if($keyword->keyword_id)
			{
				// Create new keyword object from redis hash
				$this->keywords->$keyword_id = $keyword;

			 	// Echo count how many keywords are in the object
				$this->total++;
			}
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
		
		// The redis keys to be updated
		$keys = $this->keys[$keyword_id];	
		
		// the data array to pass to the redis pipeline function
		$data['source'] = $this->sourceKey; 
		$data['rank'] = $this->keywords->$keyword_id->rank;
		$data['found'] = $this->keywords->$keyword_id->found;
		$data['time'] = time();

		// The key should expire in 31 days
		$data['expire'] = 31 * (24 * (60 * 60));

		// Create redis pipeline
		$response = $this->predis->pipeline(function($pipe) use ($keys, $data) 
		{
			// Update keyword hash
			$pipe->hmset($keys['hash'], array("u" => $data['time']));

			// Update keyword stats
			$pipe->hmset($keys['statsToday'], array($data['source'] => $data['rank'], 
											   $data['source']."m" => $data['found']));
											  
			// Set key to expire in 31 days
			$pipe->expire($keys['statsToday'], $data['expire']);									  
		});	

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
					  		$setNotify x
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

class keyword extends keywords 
{     
	// Build the keyword object
	function __construct($response, $source)
	{ 
		// Loop through keyword hash and build keyword object
		foreach($response[0] as $field => $value)
		{
			// Assign field to keyword object
			$this->$field = $value;
		}
		
		// URL encode the keyword
		$this->urlSafeKeyword();

		// Set the data source (google,bing,pr etc)
		$this->source = $source;

		// Set today's rank
		$this->today = $response[1][0];

		// Set yesterdays rank
		$this->yesterday = $response[2][0];		

		// Set the last rank
		$this->setLastRank();

		// Check the results count to get for the keyword
		$this->setResultsCount();	
   	} 

	// ===========================================================================// 
	// ! Keyword methods                                                          //
	// ===========================================================================// 
	
	// Test a keyword array for keywords needed data
	public function keywordTest($hash)
	{    
				print_r($hash);

  		// The required keys in the keyword array
		$required = array('keyword_id','keyword','domain','country');
				
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
		// If there is a ranking field for today
		if($this->today)
		{
			// Use today's rank as the last rank
			$this->lastRank = $this->today;	
		}	
		elseif($this->yesterday)
		{
			// Use yesterday's rank as the last rank
			$this->lastRank = $this->yesterday;				
		}
	}
	
	// Build the search engine results page for the keyword
	public function setSearchUrl($source)
	{   
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