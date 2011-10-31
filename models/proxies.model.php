<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** PROXIES - Manage the use of proxies for the application
// ** 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-10-30
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

	// Set to true when finished
	public $finished = false;

	// Will contain all proxies selected
	public $selected = array();

	// ===========================================================================// 
	// ! Opening and closing functions                                            //
	// ===========================================================================//

	// Run on class instantiation
	function __construct($engine = false)
	{  	
		// Include redis class
		require_once('classes/redis.php');

		// Instantiate new redis object
		$this->redis = new redis(REDIS_PROXY_IP, REDIS_PROXY_PORT);	
				
		// Set the engine for proxies
		$this->engine = $this->setEngine($engine);
	}

	// Run when script ends
	function __destruct()
	{
		// If any unreturned proxies at the end of execution 
		if(count($this->selected) > 0)
		{
			// Loop through unreturned proxies
			foreach($this->selected as $proxy)
			{ 
				// Update proxy
				$this->update($proxy);
			}
		}				
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

	// ===========================================================================// 
	// ! Redis proxy select and update                                            //
	// ===========================================================================//

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
	 		$save .= $proxy."\n";

	 		// Create array from json data
	 		$this->proxies[] = $this->redis->hgetall("p:".$proxy);

	 		// Keep track of proxies checked out out of database (final check on desctruct)
	 		$this->selected[$proxy] = $proxy;
	 	} 	
	}	

	// Add proxies back into sorted set with new timestamp score (1 hour for blocked, now for non blocked)
	public function update($proxy, $blocked = false)
	{
		// If these are blocked proxies
		if($blocked)
		{
			// Micro time in one hour (when the proxy can be used next) 
			$score = microtime(true) + PROXY_WAIT_BLOCKED;
		}
		else
		{	
			// Time in 1 minute (when the proxy can be used next)
			$score = microtime(true) + PROXY_WAIT_USE;
		}			

		// Add proxy back into sorted set with new score (timestamp)
		$this->redis->zadd('proxies:'.$this->engine, $score, $proxy);

		// Remove proxy from selected array
		unset($this->selected[$proxy]);
	}

	// ===========================================================================// 
	// ! Redis proxy DB stats                                                     //
	// ===========================================================================//	

	// Select total number of proxies in db
	public function checkTotal($engine = "master")
	{
		$this->total = $this->redis->zCard("proxies:".$engine);		

		return $this->total;
	}

	// Count proxies currently available for use
	public function checkAvailable($engine = "google")
	{
	 	$score = microtime(true);

		$this->working = $this->redis->zCount("proxies:".$engine, 0, $score);		

		return $this->working;
	}
	
	// Count proxies currently blocked 
	public function checkBlocked($engine = "google")
	{
	 	$now = microtime(true) + PROXY_WAIT_USE;

	 	$future = microtime(true) + PROXY_WAIT_BLOCKED;

		$this->blocked = $this->redis->zCount("proxies:".$engine,  $now, $future);		

		return $this->blocked;
	}

	// Count proxies currently resting(forced delay between uses)
	public function checkResting($engine = "google")
	{
	 	$now = microtime(true);

	 	$future = microtime(true) + PROXY_WAIT_USE;

		$this->resting = $this->redis->zCount("proxies:".$engine,  $now, $future);		

		return $this->resting;
	}	
	
	// Count proxies currently checked out
	public function checkInUse()
	{
		$this->inUse = $this->total - ($this->working + $this->blocked + $this->resting);

		return $this->inUse;		
	}				

	// Check the unblock time on the newest blocked proxy to determine whan all proxies will be unblocked
	public function checkBlockTime($engine = "google")
	{
		$last = $this->redis->zrevRange("proxies:".$engine, 0 , 0, TRUE);

		return date("h:i", $last[1]);
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
		
		// All proxy sources needed to be set
		$sources = array('master', 'google', 'bing', 'alexa', 'backlinks');

		// Build proxy and SQL array
		while($proxy = mysql_fetch_array($result, MYSQL_ASSOC))
		{	
			// Loop through sources
			foreach($sources as $source)
			{
				// Add proxy to redis set (once for each source to keep track of blocks and use)
				$this->redis->zadd('proxies:'.$source, microtime(true), $proxy['proxy']);	
			}								
			
			// Create proxy hash		
			$this->redis->hmset('p:'.$proxy['proxy'], $proxy);			
		}			
	}
}	