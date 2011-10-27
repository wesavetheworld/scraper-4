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

	// The amount of proxies selected, total
	public $selected = 0;

	// Set to true when finished
	public $finished = false;

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

	function __destruct()
	{
		$this->update(true);
		
	}

	// Establish connection to Redis server
	private function redisConnect()
	{
		// Include redis class
		require_once('classes/redis.php');

		// Instantiate new redis object
		$this->redis = new redis(REDIS_PROXY_IP, REDIS_PROXY_PORT);	
	}

	// Reduce a number unless it's irreducible
	public function irreducible($num, $subtract)
	{
		// If number isn't the same to subtract (irreducible)
		if($num != $subtract)
		{
			// Reduce the number
			$num = $num - $subtract;
		}	

		return $num;
	}

	public function selectSingle($totalProxies = 1, $key = "proxiesGoogle")
	{ 		
		// Loop until proxies are returned
		while(!$response)
		{
			// Monitor proxy set for changes during selection
	 		$this->redis->watch($key);

	 		// If there are enough proxies to select for the job
	 		if($this->redis->zCount($key, 0, microtime(true)) >= $totalProxies)
	 		{
	 			// Start a redis transaction
	 			$this->redis->multi();

				// Select a range of proxies ordered by last block 
				$this->redis->ZRANGE($key, 0, $totalProxies);

				// Remove all proxies just selected
				$this->redis->ZREMRANGEBYRANK($key, 0, $totalProxies);	
				
				// Get response from redis
				$response = $this->redis->exec(); 
	 		}
	 		// Not enough proxies to select
	 		else
	 		{
	 			echo "not enough proxies";
	 			// Wait and try again
				sleep(5);	 			
	 		}

	 		// Stop monitoring proxy list for changes
	 		$this->redis->unwatch($key);	
	 	}	 	

	 	// Loop through each proxy in the redis response
	 	foreach($response[0] as $proxy)
	 	{
	 		$this->selected++;

	 		// Create array from json data
	 		return $this->redis->hgetall("p:".$proxy);
	 	} 	
	}	

	public function select($totalProxies = 1, $key = "proxiesGoogle")
	{ 		
		// Reduce total by 1 to account for redis 0 index
		$totalProxies = $this->irreducible($totalProxies, 1);

		// Loop until proxies are returned
		while(!$response)
		{
			// Monitor proxy set for changes during selection
	 		$this->redis->watch($key);

	 		// Set microtime here to avoid discrepancies in the next few redis calls
	 		$score = microtime(true);

	 		// If there are enough proxies to select for the job
	 		if($this->redis->zCount($key, 0, $score) >= $totalProxies)
	 		{
	 			// Start a redis transaction
	 			$this->redis->multi();

				// Select a range of proxies ordered by last block 
				$this->redis->ZRANGE($key, 0, $totalProxies);

				// Remove all proxies just selected
				$this->redis->ZREMRANGEBYRANK($key, 0, $totalProxies);	
				
				// Get response from redis
				$response = $this->redis->exec(); 
	 		}
	 		// Not enough proxies to select
	 		else
	 		{
	 			// Wait and try again
				sleep(5);	 			
	 		}

	 		// Stop monitoring proxy list for changes
	 		$this->redis->unwatch($key);	
	 	}	 	

	 	// Loop through each proxy in the redis response
	 	foreach($response[0] as $proxy)
	 	{
	 		// Create array from json data
	 		$this->proxies[] = $this->redis->hgetall("p:".$proxy);
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

    public function addSortedSetMembers($array, $block = false)
    {
		// Loop through items to be added
		foreach($array as $proxy)
		{
			// If these are blocked proxies
			if($block)
			{
				// Micro time in one hour (when the proxy can be used next) 
				$score = microtime(true) + (60 * 60);
				$type = "future: ";
			}
			else
			{	
				// Current micro time
				$score = microtime(true);
				$type = "now: ";

			}	

			// Add proxy to redis set		
			$this->redis->zadd('proxiesGoogle', $score, $proxy);
		}
    }
	
	// Add proxies back to redis sets based on status
    public function update($final = false)
    {
		// Start a redis transaction			
		$this->redis->multi();
		    	
		// Update blocked proxies
		if(count($this->blocked) > 0)
		{	
			echo "proxies blocked: ".count($this->blocked)."\n";

			// Add proxies back to sorted set
			$this->addSortedSetMembers($this->blocked, TRUE);		
		}    	
		
		// Update blocked proxies
		if(count($this->denied) > 0)
		{
			echo "proxies denied: ".count($this->denied)."\n";

			// Add proxies back to sorted set
			$this->addSortedSetMembers($this->denied, FALSE);				
		}
		
		// Update timed out proxies
		if(count($this->timeout) > 0)
		{
			echo "proxies timedout: ".count($this->timeout)."\n";

			// Add proxies back to sorted set
			$this->addSortedSetMembers($this->timeout, FALSE);			
		}	
		
		// Update timed out proxies
		if(count($this->dead) > 0)
		{
			echo "proxies dead: ".count($this->dead)."\n";

			// Add proxies back to sorted set
			$this->addSortedSetMembers($this->dead, FALSE);			
		}	

		// Update proxy use for all non error proxies
		if(count($this->other) > 0)
		{
			echo "proxies good: ".count($this->other)."\n";

			// Add proxies back to sorted set
			$this->addSortedSetMembers($this->other, FALSE);	
		}			

		// Update proxy use for all non error proxies
		if(count($this->good) > 0 && $final)
		{
			echo "proxies good: ".count($this->good)."\n";

			// Add proxies back to sorted set
			$this->addSortedSetMembers($this->good, FALSE);			
		}	
		
		// Execute the queued commands
		$this->redis->exec();
		
		$returned += count($this->other) + count($this->dead) + count($this->timeout) + count($this->denied) + count($this->blocked);

		if($final)
		{
			$this->returned += count($this->good);
		}

		// Empty proxy status arrays
		unset($this->blocked, $this->denied, $this->timeout, $this->dead, $this->other);			

		//echo "Total selected: $this->selected\n";
		echo "Returned: $returned ($this->returned)\n";	

    }

	// Update poxies' status based on response (blocked, timeout etc)
	public function updateProxyUse()
	{  		
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
			// Add proxy to redis set		
			$this->redis->zadd('proxiesGoogle', microtime(true), $proxy['proxy']);	
			
			// Create proxy hash		
			$this->redis->hmset('p:'.$proxy['proxy'], $proxy);			
		}			
		
	}
}	