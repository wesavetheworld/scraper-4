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

class mongotest 
{  
	
	function __construct()
	{
		// connect
		$m = new Mongo();
		
		// select a database
		$this->db = $m->serps;       
	}
	// ***************************************************************************//
	// DB STRUCTURE - Collections
	// ***************************************************************************//
     
	/*
	  
	 users
	 domainStats
	 keywordRankings
	 proxies
	
	*/

	// ***************************************************************************//
	// Collection structure
	// ***************************************************************************//  
	// 
	/*  
		Users
			 ->user
				   ->groups
				   		   ->domains
									->domain_ids

														
	    keywords
	   			->keyword_id	   		    	                        
	                        ->keyword 
									 ->googleCountry
									 ->googleTraffic
									 ->rankings 
												->date 
												      ->ranks
												
	    domains
	  		   ->domain_id 
						  ->domain
						  ->stats
						  ->keywords
						    		->keyword_id 
						
		
		
	   users
	 	user_id | groups | domains | keywords
	                |
	                L_domains
	  */   					
	//
	// ***************************************************************************//
	       
    

	public function mongotest()
	{

		//$this->insertRecord();
		//$this->selectRecord();
		
		//$this->insertUser();
		//$this->selectUser();  
		
		//$this->selectSerps(); 
		
		$this->mongoTransfer();
	} 
	
	// Create a new user object
	public function insertUser()
	{                            
		// $keywords = array("keyword_id" => '15', "test keyword");
		// 
		// $domains = array("domain_id" => '10', "test.com", "keywords" => $keywords);
		// 
		// $groups = array( "group_id" => '5', "group" => 'test group', "domains" => $domains);
		// 
		// $user = array("user_id" => '1', "groups" => $groups);  
		
		$keywords->keyword_id = '16';
		$keywords->keyword = 'test keyword';
		
		$domains->domain_id = '11';
		$domains->domain = 'test.com';
		$domains->keywords = $keywords;
		
		$groups->group_id = '6';
		$groups->group = 'test group';
		$groups->domains = $domains;
		
		$user->user_id = '2';
		$user->groups =  $groups;
				
		// $domains = array("domain_id" => '10', "test.com", "keywords" => $keywords);
		// 
		// $groups = array( "group_id" => '5', "group" => 'test group', "domains" => $domains);
		// 
		// $user = array("user_id" => '1', "groups" => $groups);		
		// 
		// Add the new keyword record
		$this->db->users->insert($user);
		
		echo "inserted\n";   	
	} 
	
	// Select a user object
	public function selectUser()
	{ 
		// find everything in the collection
		$user = $this->db->users->findone(array('user_id' => '2',));
		
		//print_r($user);	 
		
		print $user['groups']['group_id'];   
	}
	
	public function insertRecord()
	{ 
		// Build object to insert
		$obj = array( "keyword" => "serp tracker");

		// Add the new keyword record
		$this->db->groups->insert($obj);
		
	   	// Build object to insert
		$obj = array( "keyword" => "serp tracker"); 
		
		// Add the new keyword record
		$this->db->keywords->insert($obj); 
		
		echo "inserted\n";   	
	} 
	
	public function selectRecord()
	{    
		// find everything in the collection
		$cursor = $this->db->keywords->find()->limit(2);
		
		// iterate through the results
		foreach ($cursor as $obj) 
		{
		    print_r($obj);
		//["keyword"] . "\n";
		}		
	}
	
	public function selectSerps()
	{    
		// Connect to mongo
		$db = new Mongo();
		
		$keyword = array( "_id" => "24");
		
		//$db->serps->rankings->drop();
		
		// find everything in the collection
		$cursor = $db->serps->rankings->find()->count();
		
	    echo "total keywords: $cursor\n";
		die();
		
		
		// iterate through the results
		foreach ($cursor as $obj) 
		{
		    print_r($obj);
		}
		
		echo "finished\n";		
	}  
	
	// ===========================================================================// 
	// ! MySql to mongoDB migration functions                                     //
	// ===========================================================================//	
	
	// Transfer MySQL data to mongo
	public function mongoTransfer()
	{  
		// Connect to the database
		utilities::databaseConnect();

	   	// Get all groups
	   	$this->selectGroups(); 
	
		//print_r($this->groups); die();
	    
		// Add domains to corresponding groups
		$this->selectDomains();  
		
		// Add keywords to corresponding domains
		$this->selectKeywords();
		 
		// Combine all of the arrays
		$this->combine();
		
		$this->insertMongo(); 
				   	
		$merge = $this->groups;
		
		//$merge = array_pop($merge); 
		
		                   
		
	}
	 
	// Build array of groups in db
	public function selectGroups()
	{ 
		// Construct query
		$query =   "SELECT 
						* 						
					FROM 
						groups
					GROUP BY
						user_id
					LIMIT 
						1000000"; 
																												
		// Execute query and return results			
	    $result = mysql_query($query) or utilities::reportErrors("ERROR ON SELECTING: ".mysql_error());
        
		// If keywords are returned
		if(mysql_num_rows($result) > 0)
		{
			// Loop through results
			while($row = mysql_fetch_object($result))
			{    
				// Add group object to group array
				$this->groups[$row->group_id]['name'] = utf8_encode($row->grouping);
				$this->groups[$row->group_id]['group_id'] = $row->group_id;
				$this->groups[$row->group_id]['domains'] = array();
			}
   		}
	}
	
	// Build domains to group array
	public function selectDomains()
	{ 
		// Construct query
		$query =   "SELECT 
						* 						
					FROM 
						domains
					GROUP BY
						group_id 
					LIMIT
						1000000	"; 
																												
		// Execute query and return results			
	    $result = mysql_query($query) or utilities::reportErrors("ERROR ON SELECTING: ".mysql_error());
        
		// If keywords are returned
		if(mysql_num_rows($result) > 0)
		{    
			// Loop through results
			while($row = mysql_fetch_object($result))
			{   
				// Build domain array                                      
				$domain['group_id'] = $row->group_id;
				$domain['domain'] = utf8_encode($row->domain);
				$domain['www'] = $row->www;
				
				// Check if domain's group exists
				if(array_key_exists($row->group_id, $this->groups))
				{
					// Add domain array to group array
					$this->domains[$row->domain_id] = $domain;
				}
				// No group found for domain
				else
				{
					$noParent++;
				}	
			} 

			echo "$noParent homeless domains\n";
   		}
	} 
	
	// Build domains to group array
	public function selectKeywords()
	{ 
		// Construct query
		$query =   "SELECT 
						* 						
					FROM 
						keywords
					GROUP BY
						domain_id 
					LIMIT
						1000000	"; 
																												
		// Execute query and return results			
	    $result = mysql_query($query) or utilities::reportErrors("ERROR ON SELECTING: ".mysql_error());
        
		// If keywords are returned
		if(mysql_num_rows($result) > 0)
		{    
			// Loop through results
			while($row = mysql_fetch_object($result))
			{   
				// Build keyword array                                      
				$keyword['domain_id'] = $row->domain_id;
				$keyword['keyword'] = utf8_encode($row->keyword);
				
				// Check if keyword's domain exists
				if(array_key_exists($row->domain_id, $this->domains))
				{
					// Add domain array to group array
					$this->keywords[$row->keyword_id] = $keyword;
				}
				// No group found for domain
				else
				{ 
					$noParent++;
				}	
			} 

			echo "$noParent homeless keywords\n";
   		}
	} 
	
	public function combine()
	{   
		// Loop through keywords array
		foreach($this->keywords as $keyword_id => &$keyword)
		{    
			$domain_id = $keyword['domain_id'];
			
			unset($keyword['domain_id']);
			
			// Add keyword to it's parent domain
			$this->domains[$domain_id]['keywords'][$keyword_id] = $keyword;
		} 
		
		// Loop through domains array
		foreach($this->domains as $key => &$domain)
		{    
			$group_id = $domain['group_id'];
				
			unset($domain['group_id']);	
			 
			// Add keyword to it's parent domain
			$this->groups[$group_id]['domains'][$key] = $domain;
		}
		
		// Loop through groups array
		foreach($this->groups as $key => &$group)
		{    
			
			$final['name'] = $group['name'];
			$final['domains'] = $group['domains'];
			 
			// Add keyword to it's parent domain
			$this->insert[$group['name']][] = $final;
		}   			
		
	} 
	
	public function insertMongo()
	{ 
		// Connect to mongoDB
		$this->db = new Mongo();    
	 
		// Batch insert all new ranking data
		$this->db->serps->users->batchInsert($this->insert);
	}        
     


}