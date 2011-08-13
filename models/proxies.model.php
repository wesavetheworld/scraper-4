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

class proxies 
{   
	// The engine used for the proxies
	public $engine;

	function __construct($engine)
	{  	
		// Set the engine for proxies
		$this->engine = $engine;

		// Establish DB connection
		utilities::databaseConnect(PROXY_HOST, PROXY_USER, PROXY_PASS, PROXY_DB);		
	} 
    
    // Select proxies for use
    public function selectProxies($totalProxies = 1, $blockedProxies = false)
    {
		// Grab proxies with lowest 24 hour use counts
		$sql = "SELECT 
					* 
				FROM 
					proxies 
				WHERE 
					status='active'
				AND 
					blocked_".$this->engine." = 0 
					{$excludeIpAuth} 
			   	ORDER BY 
					hr_use, 
					RAND()
				LIMIT 
					{$totalProxies}";
					
		$result = mysql_query($sql) or utilities::reportErrors("ERROR ON proxy select: ".mysql_error());

		// Check if proxies exist
		if(mysql_num_rows($result) == 0)
		{    
			// Send any error notifications
		 	utilities::reportErrors("No proxies to select");			

			// No proxies found, so stop 
		  	utilities::complete();			
		}	

		// Build proxy and SQL array
		while($proxy = mysql_fetch_array($result, MYSQL_ASSOC))
		{
			// for SQL
			$this->currentProxies[] = $proxy['proxy'];

			// Proxy array
			$this->proxies[] = $proxy;
		}
  
  		// Update proxies use count
  		$this->updateProxyUse();
    }

	// Update poxies' status based on response (blocked, timeout etc)
	public function updateProxyUse()
	{  
		utilities::notate("updating proxy section", "scrape.log");			

		// Update blocked proxies
		if(count($this->proxiesBlocked) > 0)
		{
			$query = "UPDATE proxies SET blocked_".$this->engine." = 1, hr_use = hr_use + 1 WHERE proxy IN('".implode("','", $this->proxiesBlocked)."')";
			mysql_query($query) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());

	 		// Log current state
			utilities::notate("Proxies blocked: ".count($this->proxiesBlocked), "scrape.log");			
		}
		
		// Update blocked proxies
		if(count($this->proxiesDenied) > 0)
		{
			$query = "UPDATE proxies SET status = 'disabled', hr_use = hr_use + 1 WHERE proxy IN('".implode("','", $this->proxiesDenied)."')";
			mysql_query($query) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());

	 		// Log current state
			utilities::notate("Proxies denied: ".count($this->proxiesDenied), "scrape.log");				
		}
		
		// Update timed out proxies
		if(count($this->proxiesTimeout) > 0)
		{
			$query = "UPDATE proxies SET timeouts = timeouts + 1, hr_use = hr_use + 1 WHERE proxy IN('".implode("','", $this->proxiesTimeout)."')";
			mysql_query($query) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());

	 		// Log current state
			utilities::notate("Proxies timedout: ".count($this->proxiesTimeout), "scrape.log");				
		}	
		
		// Update timed out proxies
		if(count($this->proxiesDead) > 0)
		{
			$query = "UPDATE proxies SET dead = dead + 1, hr_use = hr_use + 1 WHERE proxy IN('".implode("','", $this->proxiesDead)."')";
			mysql_query($query) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());

	 		// Log current state
			utilities::notate("Proxies dead: ".count($this->proxiesDead), "scrape.log");				
		}	
		
		// Update proxy use for all non error proxies
		if(count($this->proxiesGood) > 0)
		{
			$query = "UPDATE proxies SET hr_use = hr_use + 1 WHERE proxy IN('".implode("','", $this->proxiesGood)."')";
			mysql_query($query) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());				
		}								
	}    
}	