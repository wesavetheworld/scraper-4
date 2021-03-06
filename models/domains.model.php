<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");
 
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

class domains 
{   
	// Contains keywords of the keywords selected
	public $domains;
	                  
	// Contains keywords of the keyword ids selected
	public $domainIds;
	
	// Contains the count(int) of keywords in the main object
	public $total;	

	public $task = TASK;

	function __construct($domains)
	{  					
		// Connect to serps db
		$this->serps = new redis(REDIS_SERPS_IP, REDIS_SERPS_PORT, REDIS_SERPS_DB);	
		
		// Connect to boss db
		$this->boss = new redis(BOSS_IP, BOSS_PORT, BOSS_DB);		
		
		// Loop through items		
		foreach($domains as $domain_id)
		{
 			// Select domain hash from redis		
			$hash = $this->serps->hGetAll("d:$domain_id");
					
			// Create new domain object from redis hash
			$domain = new domain($hash);

			// If domain object is correct
			if($domain->domain_id)
			{
				// Add keyword to keywords list
				$this->domains->$domain_id = $domain;

				// Echo count how many keywords are in the object
				$this->total++;
			}
		}
	} 
    
	// Called when script execution has ended
	function __destruct() 
	{	

	}  
	
	// ===========================================================================// 
	// ! Updating redis keyword data                                              //
	// ===========================================================================//	

	// Update a keywords hash in redis
	public function update($domain_id, $key)
	{
		echo "domain_id: $domain_id\n";
		// Update mysql serp data
		$this->updateMySQL($this->domains->$domain_id);
		
		$this->domains->$domain_id->updateCount = intval($this->domains->$domain_id->updateCount) + 1;	
		
		$stat = $this->domains->$domain_id->stat;		
		$statKey = substr($stat, 0, 1).":".date("Y-m-d");	
		$value = $this->domains->$domain_id->$stat;

		// Update keyword hash
		// $this->serps->hmset("d:$domain_id", array($statKey => $value,
		// 										  'updateCount' => $this->domains->$domain_id->updateCount));

		$this->serps->hmset("d:$domain_id:".date("Y-m-d"), array(substr($stat, 0, 1) => $value));												  

		// Update job list score
		$this->boss->zAdd($key, time(), $domain_id);	
		
		echo "zAdd $key ".time()." id:$domain_id pr:$value \n";	
	}		

	// ===========================================================================// 
	// ! Updating MySQL keyword data                                              //
	// ===========================================================================//
	
	// Update keywords table with new keyword info
	public function updateMySQL(&$domain)
	{
		// If no connection to the database yet(worker)
		if(!$this->db)
		{
			// Establish DB connection
			$this->db = utilities::databaseConnect(DB_HOST, DB_SERP_USER, DB_SERPS_PASS, DB_NAME_SERPS);		
		}

		// If domains's tracking data was updated successfully
		if($this->updateStat($domain))
		{
			// Update domain table with update time
			$query = "	UPDATE 
							domains 
						SET 
				  		 	".$domain->stat."_status = NOW(), 
							check_out = '0',
				 			updated = NOW()
					  	WHERE 
					  		domain_id = ".$domain->domain_id;  
										  
			// If keyword update successful
			mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON UPDATING KEYWORDS: ".mysql_error());		
		}	
	} 	

	// Update existing row in tracking table with new rankings
	private function updateStat($domain)
	{	 		     	
		// Build update query
		$query = "	INSERT INTO 
						domain_stats 
						(domain_id,
						".$domain->stat.",
						date) 
			      	VALUES (
						'".$domain->domain_id."',
						'".$domain->{$domain->stat}."',
						NOW()
			            )
    			    ON DUPLICATE KEY UPDATE 
    			    	".$domain->stat." = '".$domain->{$domain->stat}."'";	
							                                            
		// Execute update query
		return mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON stats update: ".mysql_error());				
	} 	

	// ===========================================================================// 
	// ! Functions for creating domain objects                                   //
	// ===========================================================================//	
	
	// Return the keywords object
	private function getDomains()
	{                                             
		// Select keyword data
		$this->selectDomains();
				
		// If domains are selected
		if($this->domains)
		{ 	
		 	utilities::benchmark('domains f: ');
			 
			// Get the total number of keywords selected
			$this->total = count($this->domainIds);		
			
			// If gathering new domains
			if(ONLY_NEW)
			{
				// Update the keywords select as checked out
				$this->setCheckOut('1');    	
			}	
			
			return $domains;
		}	
	} 
	
	// Select the keywords from the database
	private function selectDomains()
	{  
		if(ONLY_NEW)
		{
			$date = TASK."_status = '0000-00-00'";
		}
		else
		{
			$date = TASK."_status != '".date("Y-m-d")."'";
		}

		// If a single user is provided
		if(ONLY_USER)
		{   
			// Select data for only a single user
			$user = "user_id = ".ONLY_USER;
			$date = '';
		} 		
		
		// Construct query
		$query =   "SELECT 
						* 
					FROM 
						domains 
					WHERE 
						check_out = 0
					AND	
						{$date}		
						{$user}"; 

					echo $query;
																								
		// Execute query and return results			
	    $result = mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON SELECTING: ".mysql_error());
        
		// If keywords are returned
		if(mysql_num_rows($result) > 0)
		{
			// Loop through results
			while($domain = mysql_fetch_object($result, 'domain'))
			{   
				// Test keyword for all required fields
				if($domain->domainTest())
				{			     			
					// Set a unique keyword reference (fix for serializing objects)
					$domain->uniqueId = "id_".$domain->domain_id;

					// Set the engine to use for scraping this keyword
					$domain->stat = $this->task;				
				 
					// Add keyword object to keyword array
					$this->domains->{$domain->domain_id} = $domain;   

					// Add keywords id to checkout list
					$this->domainIds[$domain->domain_id] = $domain->domain_id;	
				}									
			} 
   		}	  		
   	}
	
	// Check in and out keywords  
	private function setCheckOut($status = '1')
	{
		// Update keyword's check_out status
		$query = "	UPDATE 
						domains 
					SET 
 						check_out = {$status}
				  	WHERE 
				  		domain_id IN (".implode(",", $this->domainIds).")";
													  
		// Execute update query
		mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON CHECKING OUT: ".mysql_error()); 
	}                                                                                                 	 
	                                      
	
	// ===========================================================================// 
	// ! Updating keyword objects                                                 //
	// ===========================================================================//
	
	// // Update keywords table with new keyword info
	// public function updateDomains()
	// {
	// 	// If no connection to the database yet(worker)
	// 	if(!$this->db)
	// 	{
	// 		echo "no connection found... could be the problem?";
	// 		$this->dbConnect();
	// 	}

	// 	// Loop through finished keywords object
	// 	foreach($this->updated as $key => &$domain)
	// 	{	 
	// 		// If this keyword has no ranking yet
	// 		if(!isset($domain->{$domain->stat}))
	// 		{   
	// 			// Skip keyword
	// 			continue;
	// 		} 
			
	// 		// If domains's tracking data was updated successfully
	// 		if($this->updateStat($domain))
	// 		{
	// 			// Update keywords table with update time and notifications
	// 			$query = "	UPDATE 
	// 							domains 
	// 						SET 
	// 				  		 	".$domain->stat."_status = NOW(), 
	// 							check_out = '0',
	// 				 			updated = NOW()
	// 					  	WHERE 
	// 					  		domain_id = ".$domain->domain_id; 
											  
	// 			// If domain update successful
	// 			if(mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON UPDATING KEYWORDS: ".mysql_error()))
	// 			{
	// 				// Remove domain from domain id array
	// 				unset($this->domainIds[$key]);        
					
	// 				// Remove domain from domain array
	// 				unset($this->domains->$key);        
	// 			}	
	// 			// Keyword update failed	
	// 			else
	// 			{
	// 				// Log status
	// 				//utilities::notate("Could not update domain", "rankings.log");		  		   	 			
	// 			}			
	// 		}	
			
 //   		}
	// } 
	
	// // Update existing row in tracking table with new rankings
	// private function updateStat($domain)
	// {	 		     	
	// 	// Build update query
	// 	$query = "	INSERT INTO 
	// 					domain_stats 
	// 					(domain_id,
	// 					".$domain->stat.",
	// 					date) 
	// 		      	VALUES (
	// 					'".$domain->domain_id."',
	// 					'".$domain->{$domain->stat}."',
	// 					NOW()
	// 		            )
 //    			    ON DUPLICATE KEY UPDATE 
 //    			    	".$domain->stat." = '".$domain->{$domain->stat}."'";	
							                                            
	// 	// Execute update query
	// 	return mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON stats update: ".mysql_error());				
	// } 
}

// ===========================================================================// 
// ! Create individual keyword objects                                        //
// ===========================================================================//

class domain 
{     
	
	function __construct($fields)
	{ 
		// Loop through domain hash and build domain object
		foreach($fields as $field => $value)
		{
			// Assign field to domain object
			$this->$field = $value;
		}			
   	} 

	// ===========================================================================// 
	// ! Keyword methods                                                          //
	// ===========================================================================// 
	
	// Test a keyword array for keywords needed data
	public function domainTest()
	{    
  		// The required keys in the keyword array
		$required = array('domain_id','domain','user_id');
				
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
			elseif($key == "domain")
			{
				$domain = explode(".", $this->$key);
				
				if(count($domain) < 2)
				{
					return false;
				}
			}
		}   
		
		// Keyword object is complete
		return TRUE;          
	} 

// Build the search engine results page for the keyword
	public function setSearchUrl($source)
	{   
		// Set the data source (google,bing,pr etc)
		$this->stat = $source;

		if($this->stat == "backlinks")
		{
		 	// Build the yahoo backlinks search url
		 	$this->url = "http://siteexplorer.search.yahoo.com/search?p=".urlencode($this->domain); 
		}
		elseif($this->stat == "pr")
		{   
			// Build the google pagerank url
	   		$this->url = "http://toolbarqueries.google.com/tbr?client=navclient-auto&ch=".$this->CheckHash($this->HashURL($this->domain)). "&features=Rank&q=info:".$this->domain; 
		}
		elseif($this->stat == "alexa")
		{   
			// Build the alexa url
	   		$this->url = "http://data.alexa.com/data/hmyq81hNHng1MD?cli=10&dat=ns&ref=&url=".urlencode($this->domain); 
		}

		// Get the current search hash 
		$this->setSearchHash(); 		
	}

	// Create a hash for the keyword's saved search file name
	public function setSearchHash()
	{
		// Naming convention for the file
		$searchHash = $this->www.$this->domain;

		// Calculate hash for filename
		$searchHash = crc32($searchHash);

		// Format hash
		$searchHash = vsprintf("%u", $searchHash);

		// Set keyword's saved search file hash
		$this->searchHash = $searchHash; 
	}	

    // ===========================================================================// 
	// ! PageRank methods                                                         //
	// ===========================================================================//	

	//Genearate a hash for a url
	private function HashURL($String)
	{
	    $Check1 = $this->StrToNum($String, 0x1505, 0x21);
	    $Check2 = $this->StrToNum($String, 0, 0x1003F);

	    $Check1 >>= 2;     
	    $Check1 = (($Check1 >> 4) & 0x3FFFFC0 ) | ($Check1 & 0x3F);
	    $Check1 = (($Check1 >> 4) & 0x3FFC00 ) | ($Check1 & 0x3FF);
	    $Check1 = (($Check1 >> 4) & 0x3C000 ) | ($Check1 & 0x3FFF);   

	    $T1 = (((($Check1 & 0x3C0) << 4) | ($Check1 & 0x3C)) <<2 ) | ($Check2 & 0xF0F );
	    $T2 = (((($Check1 & 0xFFFFC000) << 4) | ($Check1 & 0x3C00)) << 0xA) | ($Check2 & 0xF0F0000 );

	    return ($T1 | $T2);
	}


	//genearate a checksum for the hash string
	private function CheckHash($Hashnum)
	{
	    $CheckByte = 0;
	    $Flag = 0;

	    $HashStr = sprintf('%u', $Hashnum) ;
	    $length = strlen($HashStr);

	    for ($i = $length - 1;  $i >= 0;  $i --) 
		{
	        $Re = $HashStr{$i};
	        if (1 === ($Flag % 2)) 
			{             
	            $Re += $Re;     
	            $Re = (int)($Re / 10) + ($Re % 10);
	        }
	        $CheckByte += $Re;
	        $Flag ++;   
	    }

	    $CheckByte %= 10;
	    if (0 !== $CheckByte) 
		{
	        $CheckByte = 10 - $CheckByte;
	        if (1 === ($Flag % 2) ) 
			{
	            if (1 === ($CheckByte % 2)) 
				{
	                $CheckByte += 9;
	            }
	            $CheckByte >>= 1;
	        }
	    }

	    return '7'.$CheckByte.$HashStr;
	}

	function StrToNum($Str, $Check, $Magic)
	{
	    $Int32Unit = 4294967296;  // 2^32

	    $length = strlen($Str);
	    for ($i = 0; $i < $length; $i++) 
		{
	        $Check *= $Magic;     
	        //If the float is beyond the boundaries of integer (usually +/- 2.15e+9 = 2^31),
	        //  the result of converting to integer is undefined
	        //  refer to http://www.php.net/manual/en/language.types.integer.php
	        if ($Check >= $Int32Unit) 
			{
	            $Check = ($Check - $Int32Unit * (int) ($Check / $Int32Unit));
	            //if the check less than -2^31
	            $Check = ($Check < -2147483648) ? ($Check + $Int32Unit) : $Check;
	        }
	        $Check += ord($Str{$i});
	    }
	    return $Check;
	}

	public function Rank(){
		$Xml = $this->loadXml();
		if(!is_object($Xml) || !isset($Xml->SD[1]) || !is_object($Xml->SD[1])) return 0;
		return trim($Xml->SD[1]->REACH['RANK']);
	}		

		
}