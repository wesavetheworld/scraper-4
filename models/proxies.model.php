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

	function __construct($engine = false)
	{  	
		// Set the engine for proxies
		$this->engine = $engine;

		// Establish DB connection
		$this->db = utilities::databaseConnect(PROXY_HOST, PROXY_USER, PROXY_PASS, PROXY_DB);

		if(defined("DEV"))
		{
			// use redis
			$this->redisConnect();

			//$this->migrateToRedis();die();
		}	
	} 

	// Establish connection to Redis server
	private function redisConnect()
	{
		// Include redis class
		require_once('classes/redis.php');

		// Instantiate new redis object
		$this->redis = new redis(REDIS_PROXY_IP, REDIS_PROXY_PORT);	
	}

	public function select($totalProxies = 2, $key = "proxiesGoogle")
	{ 		
		$totalProxies = 2;

		// Loop until proxies are returned
		while(!$this->proxies)
		{
			echo "proxy select loop:\n";

			// Monitor proxy set for changes during selection
	 		//$this->redis->watch($key);

	 		$micro = microtime();

	 		// If there are enough proxies to select for the job
	 		if($this->redis->zCount($key, "0", $micro) >= $totalProxies)
	 		{
	 			// Start a redis transaction
	 			//$this->redis->multi();

				// Select a range of proxies ordered by last block 
				$this->proxies = $this->redis->ZRANGEBYSCORE($key, 0, microtime(), false, array(0, $totalProxies));

		 		echo "proxies: ";
		 		print_r($this->proxies);
		 		die();				
				
				// Checkout the top proxies from the set (proxies with oldest last used time)
				//$this->redis->ZREMRANGEBYSCORE($key, 0, microtime(), false, array(0, $totalProxies));

				// Set proxies from redis multi exec returned data
				//$this->proxies = $this->redis->exec(); 				
	 		}
	 		// Not enough proxies to select
	 		else
	 		{
	 			// Wait and try again
	 			echo "not enough, sleeping for 5\n";
				sleep(5);	 			
	 		}

	 		// Stop monitoring proxy list for changes
	 		//$this->redis->unwatch($key);	
	 	}	

	 	// Loop through each proxy json data
	 	foreach($this->proxies as &$proxy)
	 	{
	 		// Create array from json data
	 		$proxy = $this->redis->hgetall("p:".$proxy);
	 	}
	}
    
    // Select proxies for use
    public function selectProxies($totalProxies = 1, $blockedProxies = false)
    {

    	if(defined("DEV"))
    	{
    		// Use Redis instead
    		$this->select($totalProxies);
    	}

    	// Until there are proxies to return
    	while(!$success && !defined("DEV"))
    	{
			// Grab proxies with lowest 24 hour use counts and have not been blocked within the hour
			$sql = "SELECT 
						* 
					FROM 
						proxies 
					WHERE 
						status='active'
					AND 
						(
							blocked_".$this->engine." <= DATE_ADD(NOW(),INTERVAL -1 HOUR) 
						OR
							blocked_".$this->engine." = '0000-00-00 00:00:00' 
						)	
						{$excludeIpAuth} 
				   	ORDER BY 
						hr_use, 
						RAND()
					LIMIT 
						{$totalProxies}";

			$result = mysql_query($sql, $this->db) or utilities::reportErrors("ERROR ON proxy select: ".mysql_error());

			// If enough proxies were selected
			if($this->minCheck(mysql_num_rows($result), $totalProxies))
			{	
				// Build proxy and SQL array
				while($proxy = mysql_fetch_array($result, MYSQL_ASSOC))
				{
					// for SQL
					$this->currentProxies[] = $proxy['proxy'];

					// Proxy array
					$this->proxies[] = $proxy;
				}

				// Proxies selected successfully
				$success = TRUE;
			}
			// No proxies to select (db is empty or all proxies are blocked)
			else
			{
				echo "sleeping...\n";
				sleep(120);
			}
		}	
    }

    // Make sure that the minimum required proxies are returned
    public function minCheck($total, $requested)
    {
		if($total == 0 || $total < $requested)
		{    
			// Send any error notifications
		 	//utilities::reportErrors("Not enough proxies to select");			

			// No proxies found, so stop 
		  	//utilities::complete();			
		}   
		else
		{
			return true;
		} 	
    }
	
	// Add proxies back to redis sets based on status
    public function update()
    {
    	
    }

	// Update poxies' status based on response (blocked, timeout etc)
	public function updateProxyUse()
	{  
    	if(defined("DEV"))
    	{
    		// Use Redis instead
    		$this->update();
    	}
    			
		// Update blocked proxies
		if(count($this->proxiesBlocked) > 0)
		{
			$query = "UPDATE proxies SET blocked_".$this->engine." = NOW(), hr_use = hr_use + 1 WHERE proxy IN('".implode("','", $this->proxiesBlocked)."')";
			mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());

	 		// Log current state
			utilities::notate("Proxies blocked: ".count($this->proxiesBlocked), "scrape.log");			
		}
		
		// Update blocked proxies
		if(count($this->proxiesDenied) > 0)
		{
			$query = "UPDATE proxies SET status = 'disabled', blocked_".$this->engine." = 0, hr_use = hr_use + 1 WHERE proxy IN('".implode("','", $this->proxiesDenied)."')";
			mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());

	 		// Log current state
			utilities::notate("Proxies denied: ".count($this->proxiesDenied), "scrape.log");				
		}
		
		// Update timed out proxies
		if(count($this->proxiesTimeout) > 0)
		{
			$query = "UPDATE proxies SET timeouts = timeouts + 1, blocked_".$this->engine." = '0000-00-00 00:00:00', hr_use = hr_use + 1 WHERE proxy IN('".implode("','", $this->proxiesTimeout)."')";
			mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());

	 		// Log current state
			utilities::notate("Proxies timedout: ".count($this->proxiesTimeout), "scrape.log");				
		}	
		
		// Update timed out proxies
		if(count($this->proxiesDead) > 0)
		{
			$query = "UPDATE proxies SET dead = dead + 1, blocked_".$this->engine." = '0000-00-00 00:00:00', hr_use = hr_use + 1 WHERE proxy IN('".implode("','", $this->proxiesDead)."')";
			mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());

	 		// Log current state
			utilities::notate("Proxies dead: ".count($this->proxiesDead), "scrape.log");				
		}	
		
		// Update proxy use for all non error proxies
		if(count($this->proxiesGood) > 0)
		{
			$query = "UPDATE proxies SET blocked_".$this->engine." = '0000-00-00 00:00:00', hr_use = hr_use + 1 WHERE proxy IN('".implode("','", $this->proxiesGood)."')";
			mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON proxy update: ".mysql_error());				
		}									
	}  
	
	// Rest all proxy stats
	public function reset()
	{
		$query = "	UPDATE 
						proxies 
					SET 
						status = 'active', 
						blocked_google = '0', 
						blocked_bing = '0', 
						blocked_yahoo = '0', 
						timeouts = '0', 
						dead = '0',
						hr_use = '0'";
						
		mysql_query($query, $this->db) or utilities::reportErrors("ERROR ON PROXY RESET: ".mysql_error());
	}	
	
	// Select all proxies in the MySQL database and add them to a redis set
	public function migrateToRedis()
	{
		// Grab proxies with lowest 24 hour use counts and have not been blocked within the hour
		$sql = "SELECT 
					proxy,
					port,
					username,
					password,
					tunnel
				FROM 
					proxies
				ORDER BY 
					RAND()";

		$result = mysql_query($sql, $this->db) or utilities::reportErrors("ERROR ON proxy select: ".mysql_error());	
		
		// Build proxy and SQL array
		while($proxy = mysql_fetch_array($result, MYSQL_ASSOC))
		{			
			// Calculate member score (current time minus an hour)
			//$score = time() - (60 * 60);
			$score = microtime(true) - (60 * 60);

			// Add proxy to redis set		
			$this->redis->zadd('proxiesGoogle', $score, $proxy['proxy']);	
			
			// Create proxy hash		
			$this->redis->hmset('p:'.$proxy['proxy'], $proxy);			
		}			
		
	}
}	