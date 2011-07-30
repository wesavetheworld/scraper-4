<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** DOMAINS - Selects domains from db and creates an object of individiual 
// ** domain objects.
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-06-25
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class domains 
{            
	
	// Contains the keywords selected
	public $domains;
	                  
	// Contains array of the domain ids selected
	public $domainIds;
	
	// Contains the count(int) of domains in the main object
	public $total;	
	
	function __construct($empty = false)
	{
		// Establish MySQL connection & select database
		mysql_connect(DB_HOST, DB_SERP_USER, DB_SERPS_PASS) or die ('Error connecting to mysql');
		mysql_select_db(DB_NAME_SERPS);	
	  	
		if(!$empty)
		{                             
			// Select domains
			$this->getDomains();   
		}	
	} 	
	
	// Called when script execution has ended
	function __destruct() 
	{	
		// If any domains failed to update
		if(count($this->domainIds))
		{    
			// Check any remaining domains back in
			$this->setCheckOut('0');
		}  
	}	
	
	// ===========================================================================// 
	// ! Functions for creating keyword objects                                   //
	// ===========================================================================//	
	
	// Return the keywords object
	private function getDomains()
	{                                             
		// Select keyword data
		$domains = $this->selectDomains(); 
		
		// If keywords are selected
		if($domains)
		{
			// Loop through keyword list 
			foreach($domains as $key => &$domain)		
			{   
	 			// Build the domain into a working object
				$this->domains->{$key}  = new domain($domain);

				// If keyword is missing required values
				if(!$domain)
				{			 
					// Remove bad keyword
					unset($this->domains->{$key} );
				}
				// Domain is formed correctly
				else
				{    
					// Add keywords id to checkout list
					$this->domainIds[$key] = $key;
				} 
			} 
			
			// Get the total number of keywords selected
			$this->total = count($this->domainIds);			
			
			// Update the keywords select as checked out
			$this->setCheckOut('1');
		}	
	} 
	
	// Select the keywords from the database
	private function selectDomains()
	{  
		// If a single user is provided
		if(ONLY_USER)
		{   
			// Select data for only a single user
			$where = "AND domains.user_id = ".ONLY_USER;
		} 
		
		// Construct query
		$query =   "SELECT 
						* 
					FROM 
						domains 
					WHERE 
						".STAT."_status != '".date("Y-m-d")."'

						 {$where}"; 
					   																				
		// Execute query and return results			
	    $result = mysql_query($query) or utilities::reportErrors("ERROR ON SELECTING DOMAINS: ".mysql_error());
        
		// If keywords are returned
		if(mysql_num_rows($result) > 0)
		{
			// Loop through results
			while($row = mysql_fetch_object($result))
			{   
				// Add domain object to domains array
				$domains[$row->domain_id] = $row;
			} 
		   						
			// Return keyword array
			return $domains;
		}    
	} 
	
	// Update domains table with new domain info
	public function updateDomains()
	{
		$stat = STAT;

		// Loop through finished domains object
		foreach($this->domains as $key => &$domain)
		{   	  
			// If this domain has no stats yet
			if(!isset($domain->$stat))
			{   
				// Skip domain
				continue;
			}
			
			// If keyword has not been updated today
			if($domain->updated != DATE_TODAY)
			{
				// Insert a new stat row
				$this->insertStat($domain);
			}
			else
			{
				// Update an existing stat row
				$this->updateStat($domain);
			}			
			
			// Update keywords table with update time and notifications
			$query = "	UPDATE 
							domains 
						SET 
				  		 	".STAT."_status = NOW(), 
							check_out = '0',
				 			updated = NOW()
					  	WHERE 
					  		domain_id = ".$domain->domain_id; 
															  
		    // Execute update query
			$result = mysql_query($query) or utilities::reportErrors("ERROR ON UPDATING DOMAINS: ".mysql_error()); 
			
			// If keyword update successful
			if($result)
			{
				// Remove domain from domain id array
				unset($this->domainIds[$key]);        
				
				// Remove domain from domain array
				unset($this->domains->$key);        
			}			
		}
	}  
	
	// Update existing row in tracking table with new rankings
	private function updateStat($domain)
	{	 
		$stat = STAT;
		     		
		// Build update query
		$query = "	UPDATE 
						domain_stats 
					SET 
						".STAT." = '".$domain->$stat."'
					WHERE 
					 	domain_id = ".$domain->domain_id." 
					AND 
					 	date = '".DATE_TODAY."'";
							                                            
		// Execute update query
		mysql_query($query) or utilities::reportErrors("ERROR ON stats update: ".mysql_error());				
	}

	// Insert a new row into tracking table with new rankings
	private function insertStat($domain)
	{ 
		$stat = STAT;
		              		
		// Build insert query
		$query = "	INSERT INTO 
						domain_stats 
						(domain_id,
						".STAT.",
						date) 
			      	VALUES (
						'".$domain->domain_id."',
						'".$domain->$stat."',
						NOW()
			          )";
		
		// Execute insert query 
		mysql_query($query) or utilities::reportErrors("ERROR ON stats insert: ".mysql_error());		
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
		mysql_query($query) or utilities::reportErrors("ERROR ON CHECKING OUT: ".mysql_error()); 
	}
	 
} 

// ===========================================================================// 
// ! Create individual domain objects                                         //
// ===========================================================================//

class domain
{ 
	function __construct($domainValues)
	{
	 	// Create domain object
		$this->domainObject($domainValues);   
	}  
	
	// ===========================================================================// 
	// ! Domain methods                                                           //
	// ===========================================================================//   

 	// Create the keyword object from array of values provided
	private function domainObject($domainValues)
	{
		// Convert keyword fields into keyword object
		foreach($domainValues as $key => $value)
		{
			$this->{$key} = $value;
		}		
	}  
	
	// Build the search engine results page for the keyword
	public function setSearchUrl()
	{     
		if(STAT == "backlinks")
		{
		 	// Build the yahoo backlinks search url
		 	$this->url = "https://siteexplorer.search.yahoo.com/search?p=".urlencode($this->domain); 
		}
		elseif(STAT == "pr")
		{   
			// Build the google pagerank url
	   		$this->url = "http://toolbarqueries.google.com/search?client=navclient-auto&ch=".$this->CheckHash($this->HashURL($this->domain)). "&features=Rank&q=info:".$this->domain."&num=100&filter=0"; 
		}
		elseif(STAT == "alexa")
		{   
			// Build the alexa url
	   		$this->url = "http://data.alexa.com/data/hmyq81hNHng1MD?cli=10&dat=ns&ref=&url=".urlencode($this->domain); 
		}		
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
 