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
		$this->engine = $this->setEngine($engine);

		// use redis
		$this->redisConnect();

		//$this->migrateToRedis();die();
	}

	// Set the correct engine (used for proxy key)
	private function setEngine($engine)
	{
		if($engine == "pr")
		{
			return "google";
		}
		else
		{
			return $engine;
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

	// Select the requested amount of proxies from redis
	public function select($totalProxies = 1, $key = "proxies:google")
	{ 		
		// Reduce total by 1 to account for redis 0 index
		$totalProxies = $totalProxies - 1;

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
	 			echo "not enough proxies...\n";
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

	// Add proxies back into sorted set with new timestamp score (1 hour for blocked, now for non blocked)
	public function update($blocked = false, $proxy)
	{
		// If these are blocked proxies
		if($blocked)
		{
			// Micro time in one hour (when the proxy can be used next) 
			$score = microtime(true) + (60 * 60);
		}
		else
		{	
			// Current micro time
			$score = microtime(true);
		}			

		// Add proxy back into sorted set with new score (timestamp)
		$this->redis->zadd('proxies:google', $score, $proxy);
	}

	// ===========================================================================// 
	// ! Redis proxy DB stats                                                     //
	// ===========================================================================//	

	public function checkTotal($key = "proxies:google")
	{
		return $this->redis->zCard($key);		
	}

	public function checkWorking($key = "proxies:google")
	{
	 	$score = microtime(true);

		return $this->redis->zCount($key, 0, $score);		
	}
	
	public function checkBlocked($key = "proxies:google")
	{
	 	$now = microtime(true);

	 	$future = microtime(true) + (60 * 60);

		return $this->redis->zCount($key,  $score, $future);		
	}		

	// ===========================================================================// 
	// ! Legacy MySQL related stuff                                               //
	// ===========================================================================//	

	// Select all proxies in the MySQL database and add them to a redis set
	public function migrateToRedis()
	{
		// Establish DB connection
		$this->db = utilities::databaseConnect(PROXY_HOST, PROXY_USER, PROXY_PASS, PROXY_DB);
				
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
			// Add proxy to redis master set (to compare to others for missing proxies)
			$this->redis->zadd('proxies:master', microtime(true), $proxy['proxy']);	
						
			// Add proxy to redis google set		
			$this->redis->zadd('proxies:google', microtime(true), $proxy['proxy']);	

			// Add proxy to redis google set		
			$this->redis->zadd('proxies:bing', microtime(true), $proxy['proxy']);	
			
			// Add proxy to redis google set		
			$this->redis->zadd('proxies:alexa', microtime(true), $proxy['proxy']);	
			
			// Add proxy to redis google set		
			$this->redis->zadd('proxies:backlinks', microtime(true), $proxy['proxy']);								
			
			// Create proxy hash		
			$this->redis->hmset('p:'.$proxy['proxy'], $proxy);			
		}			
	}
}	