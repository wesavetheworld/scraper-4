<?php  if(!defined('HUB')) exit('No direct script access keywordsowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** KEYWORDS - Selects keywords from db and creates an object of individiual 
// ** keyword objects.
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-22
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class keywords 
{   
	// Contains keywords of the keywords selected
	public $keywords;
	                  
	// Contains keywords of the keyword ids selected
	public $keywordIds;
	
	// Contains the count(int) of keywords in the main object
	public $total;	
	
	function __construct($empty = false, $dbConnect = false)
	{  		
		// If a db connection is requested
		if(!$empty || $dbConnect)
		{
			// Connect to database
			$this->dbConnect();	
		}
		
		// If a keyword object should be built		
		if(!$empty)
		{
		    // Select keywords
			$this->getKeywords();   
		}	
	} 
    
	// Called when script execution has ended
	function __destruct() 
	{	
		// // If any keywords failed to update
		// if(count($this->keywordIds))
		// {    
		// 	// Check any remaining keywords back in
		//$this->setCheckOut('0');
		// }  
	}  
	
	private function dbConnect()
	{
		echo "connecting to db";

		// Establish DB connection
		$this->db = utilities::databaseConnect(DB_HOST, DB_SERP_USER, DB_SERPS_PASS, DB_NAME_SERPS);
	}  
	
	// ===========================================================================// 
	// ! Functions for creating keyword objects                                   //
	// ===========================================================================//	
	
	// Return the keywords object
	private function getKeywords()
	{                                             
		// Select keyword data
		$this->selectKeywords();
				
		// If keywords are selected
		if($this->keywords)
		{
			// Select past ranking data for keywords
			$this->selectRankings();   

			// Loop through keywords
			foreach($this->keywords as $keyword)
			{
				// Determine what type of results page to scrape for a keyword (10/100)
				$keyword->setResultsCount();
			}	

		 	utilities::benchmark('keywords f: ');
			 
			// Get the total number of keywords selected
			$this->total = count($this->keywordIds);			
			
			// If selecting new or keywords needing calibration
			if(TASK == "rankingsNew")
			{
				// Update the keywords select as checked out
				$this->setCheckOut('1');    	
			}	
			
			return $keywords;
		}	
	} 
	
	// Select the keywords from the database
	private function selectKeywords()
	{  
		// If updating daily keywords
       	if(SCHEDULE == "daily" || ENGINE == 'bing')
		{   
			// Today
			$time = date("Y-m-d");

			$schedule = "'daily'";
		} 
		// Update only hourly keywords
		else
		{    
			// This hour
			$time = date("Y-m-d H");
			
			$schedule = "'hourly'";
		}

		if(ENGINE == 'bing')
		{
			$schedule = "'hourly','daily'";
		}

		// Build base select statement
		$select = "SELECT 
							keywords.keyword_id,
							keywords.keyword,
							keywords.user_id,
							keywords.g_country,
							keywords.notifications,
							keywords.calibrate,
							keywords.date,							
							domains.domain_id,
							domains.domain							
						FROM 
							keywords
						JOIN 
							domains ON keywords.domain_id = domains.domain_id ";
           
		// If single user argument present
		if(ONLY_USER)
		{   
			// Construct query
			$query =   "$select 
						WHERE 
							 keywords.user_id IN ('".ONLY_USER."') 
						ORDER BY
							keywords.".ENGINE."_status,
							keywords.keyword";				  					
        } 
		// If selecting only new keywords
		elseif(TASK == "rankingsNew")
		{
		 	$query =   "$select
						WHERE 
							keywords.check_out != 1							    				
						AND
							keywords.".ENGINE."_status = '0000-00-00 00:00:00'							
						ORDER BY
						 	keywords.".ENGINE."_status DESC,
							keywords.keyword,
						 	domains.user_id";		
		}	
		// Normal select statement
		else
        {
			// Construct query
			$query =   "$select 
						WHERE 
							keywords.status !='suspended'
						AND
							keywords.check_out != 1
						AND	
							keywords.".ENGINE."_status != '0000-00-00 00:00:00'	    				
						AND
	                    	keywords.schedule IN ($schedule)
						AND
							keywords.".ENGINE."_status < '{$time}'
						ORDER BY
							keywords.".ENGINE."_status DESC,
							keywords.keyword,
						 	domains.user_id"; 
		}   			
																								
		// Execute query and return results			
	    $result = mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON SELECTING: ".mysql_error());
        
		// If keywords are returned
		if(mysql_num_rows($result) > 0)
		{
			// Loop through results
			while($keyword = mysql_fetch_object($result, 'keyword'))
			{   
				// Test keyword for all required fields
				if($keyword->keywordTest())
				{
					// Make the keyword save to be used in the url	
					$keyword->urlSafeKeyword();				     				

					// Set a unique keyword reference (fix for serializing objects)
					$keyword->uniqueId();	
					
					// Set the engine to use for scraping this keyword
					$keyword->engine = ENGINE;				
				 
					// Add keyword object to keyword array
					$this->keywords->{$keyword->keyword_id} = $keyword;   

					// Add keywords id to checkout list
					$this->keywordIds[$keyword->keyword_id] = $keyword->keyword_id;										
				}
			} 
   		}	  		
   	}
	
	// Check in and out keywords  
	public function setCheckOut($status = '1', $all = false)
	{
		// If not checking in all keywords
		if(!$all)
		{
			$where = "WHERE 
				  		keyword_id IN (".implode(",", $this->keywordIds).")";
		}

		// Update keyword's check_out status
		$query = "	UPDATE 
						keywords 
					SET 
 						check_out = {$status}
				  	$where";
													  
		// Execute update query
		mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON CHECKING OUT: ".mysql_error()); 
	}                                                                                                 	 
	
	// Select keyword's ranking positions
	private function selectRankings()
	{
		// Glue keyword array together
		$ids = implode(",", array_keys($this->keywordIds));  
		
		// Db column containing the ranking
		$position = ENGINE;
		
		// Construct query
		$query = "	SELECT 
						* 
					FROM 
						tracking 
					WHERE 
						keyword_id IN($ids) 
				    AND 
						date IN ('".date("Y-m-d")."','".date("Y-m-d", time()-86400)."')
					ORDER BY
						date";
		
		// Perform query				
	    $result = mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON TRACKING SELECTION: ".mysql_error());				
		
		// Add keyword tracking info to data array
		while($row = mysql_fetch_object($result))
		{   
			// If there is a row for today
			if($row->date == date("Y-m-d"))
			{ 
				// Add ranking object to rankings array
				$this->keywords->{$row->keyword_id}->lastRank = $row->$position;
			} 
			// If there was no rank for today and there is one for yesterday
			elseif(!$lastRank)
			{
			 	// Add ranking object to rankings array
				$this->keywords->{$row->keyword_id}->lastRank = $row->$position;   
			} 
		}
	}                                          
	
	// ===========================================================================// 
	// ! Updating keyword objects                                                 //
	// ===========================================================================//
	
	// Update keywords table with new keyword info
	public function updateKeywords()
	{
		// If no connection to the database yet(worker)
		if(!$this->db)
		{
			$this->dbConnect();
		}

		// Loop through finished keywords object
		foreach($this->updated as $key => &$keyword)
		{	 
			// If this keyword has no ranking yet
			if(!isset($keyword->rank) && $keyword->rank != '0')
			{   
				// Skip keyword
				continue;
			}
	
			// If keyword's tracking data was updated successfully
			if($this->updateRanking($keyword))
			{
				// If updating google
				if($this->engine == "google")
				{
					// Save any notifications for keyword
					$setNotify = " notify = '".$keyword->notify."',";
				}

				// Update keywords table with update time and notifications
				$query = "	UPDATE 
								keywords 
							SET 
						  		$setNotify 
						  		".$keyword->engine."_status = NOW(),  
						  		".$keyword->engine."_searches = '".serialize(array_keys($keyword->savedSearches))."',
								calibrate = '".$keyword->calibrate."',
								check_out = 0,
						  		time = NOW(), 
						  		date = '".date("Y-m-d")."' 
						  WHERE 
						  	keyword_id='".$keyword->keyword_id."'";  
											  
				// If keyword update successful
				if(mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON UPDATING KEYWORDS: ".mysql_error()))
				{
					// Remove keyword from keyword id array
					unset($this->keywordIds[$key]);        
					
					// Remove keyword from keyword array
					unset($this->keywords->$key);        
				}
				// Keyword update failed	
				else
				{
					// Log status
					//utilities::notate("Could not update keyword", "rankings.log");		  		   	 			
				}				
			}	
   		}
	} 
	
	// Update existing row in tracking table with new rankings
	private function updateRanking($keyword)
	{	      		
		$query = "	INSERT INTO 
						tracking 
						(keyword_id,".$keyword->engine.",
						".$keyword->engine."_match,
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
			      		".$keyword->engine." = '".$keyword->rank."', 
					 	".$keyword->engine."_match = '".$keyword->found."'";			          				 	
		
		// Execute update query
		return mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON TRACKING: ".mysql_error());
	}  

	// Check the status of keywords in the db
	public function checkSchedules()
	{
		// Construct query
		$query =   "SELECT 
						schedule,
						google_status,
						date
					FROM 
						keywords
					WHERE
						status != 'suspended'";  
																										
		// Execute query and return results			
	    $result = mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON SELECTING: ".mysql_error());
        
		// If keywords are returned
		if(mysql_num_rows($result) > 0)
		{   
			// Loop through results
			while($keyword = mysql_fetch_object($result))
			{  
				if($keyword->schedule == "hourly")
				{
					$hourly++;
					
					// Get time for last hour (current time -2 minutes)
					if($keyword->google_status < date("Y-m-d H", time() - 3600))
					{
						$hourlyLate++;
					}
				}
				elseif($keyword->schedule == "daily")
				{
					$daily++; 
					
					if($keyword->google_status < date("Y-m-d")) 
					{
						$dailyLate++;
					}
				}				
				
				$total++;
			}
			
			if($hourlyLate > 0)
			{
				utilities::reportErrors("$hourlyLate of $hourly keywords not updated last hour");
			}
			
			if($dailyLate > 0)
			{
				utilities::reportErrors("$dailyLate of $daily daily keywords not updated today");
			}			 
   		}		
	} 
}

// ===========================================================================// 
// ! Create individual keyword objects                                        //
// ===========================================================================//

class keyword 
{     
	
	function __construct()
	{    

   	} 

	// ===========================================================================// 
	// ! Keyword methods                                                          //
	// ===========================================================================// 
	
	// Test a keyword array for keywords needed data
	public function keywordTest()
	{    
  		// The required keys in the keyword array
		$required = array('user_id','keyword','domain_id','domain','g_country');
				
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
		if($this->lastRank < NUM_SWITCH_THRESHHOLD && $this->lastRank != 0 || $this->engine == 'bing')
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
	public function setSearchUrl()
	{   
		// Get the current search hash 
		$this->setSearchHash(); 
		
		// Check for a search page offset 
		$this->setSearchOffset();
		                          
		// Set the location of the keyword's saved search file
		$this->searchFile = SAVED_SEARCH_DIR.$this->searchHash.".html";
		
		if($this->engine == "google")
		{
			// Build the google search results page url
			$this->url  = "http://www.google".$this->g_country;
			$this->url .= "/search?q=".$this->urlSafe;
			$this->url .= "&num=".$this->resultCount;  		

			// If search results page offset is present
			if($this->searchOffset)
			{  
			 	// Add the ofset to the url
				$this->url .= "&start=".$this->searchOffset;	   
			}	
	    }
		elseif($this->engine == "bing")
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
		$searchHash .= $this->engine;
		$searchHash .= $this->g_country;
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
	
}