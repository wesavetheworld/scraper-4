<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");
 
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

	// The proxy queue key
	public $key;

	// All sources the proxies are used for
	public $sources;

	// Amount of time to set the proxy to wait before next use
	public $waitUse = PROXY_WAIT_USE;
	
	// Amount of time to set the proxy to wait when blocked
	public $waitBlocked = PROXY_WAIT_BLOCKED;

	// Contains the detailed proxy arrays
	public $proxies = array();

	// ===========================================================================// 
	// ! Opening and closing functions                                            //
	// ===========================================================================//

	// Run on class instantiation
	function __construct($engine = false)
	{  	
		// Instantiate new redis object
		$this->redis = new redis(REDIS_PROXY_IP, REDIS_PROXY_PORT, REDIS_PROXY_DB);	

		// Get the array of sources/queue types		
		$this->sources = json_decode(QUEUE_SOURCES);		
				
		// Set the engine for proxies
		$this->engine = $engine;

		// Set redis proxy queue key
		$this->key = "proxies:".$this->engine;	
		
		// Check for updated proxy settings in redis
		$this->getProxySettings();		
	}

	// Run when script ends
	function __destruct()
	{
			
	}
	
	// Check for updated proxy settings in redis
	public function getProxySettings()
	{
		// $this->redis->send_command("hset", "settings", "waitUse", "30");
		// $this->redis->send_command("hset", "settings", "waitBlocked", "600");

		// Get proxy wait settings from database
		$settings = $this->redis->hgetall("settings");	

		// If proxy settings found in database
		if($settings)
		{
			// Amount of time to set the proxy to wait before next use
			$this->waitUse = $settings['waitUse'];
		
			// Amount of time to set the proxy to wait when blocked
			$this->waitBlocked = $settings['waitBlocked'];	
		}	
	}

	// ===========================================================================// 
	// ! Redis proxy select and update                                            //
	// ===========================================================================//

	// Select the requested amount of proxies from redis
	public function select($totalProxies = 1)
	{ 		 	
		// Checkout keywords
		$this->checkOut($totalProxies);	

	 	// Add proxies back in with new use time (score)
	 	$this->update($this->checkedOut);		

		// Select the credentials for each of the proxies checked out
		$this->credentials();
	}	

	// Checkout proxies to use
	private function checkOut($totalProxies)
	{
		// Reduce total by 1 to account for redis 0 index
		$totalProxies = $totalProxies - 1;

		// Loop until proxies are returned
		while(!$response)
		{
			// Monitor proxy set for changes during selection
	 		$this->redis->watch($this->key);

	 		// If there are enough proxies to select for the job
	 		if($this->redis->zCount($this->key, 0, time()) >= $totalProxies)
	 		{
	 			// Start a redis transaction
	 			$this->redis->multi();

				// Select a range of proxies ordered by last block 
				$this->redis->ZRANGE($this->key, 0, $totalProxies);

				// Remove all proxies just selected
				$this->redis->ZREMRANGEBYRANK($this->key, 0, $totalProxies);	
				
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
	 		$this->redis->unwatch($this->key);	
	 	}	

	 	// Set checked out proxies
	 	$this->checkedOut = $response[0];	
	}

	// Select the full proxy array from redis
	private function credentials()
	{
	 	// Loop through each proxy in the redis response
	 	foreach($this->checkedOut as $proxy)
	 	{
	 		// Create array from json data
	 		$this->proxies[] = $this->redis->hgetall("p:".$proxy);
	 	} 			
	}

	// Add proxies back into sorted set with new timestamp score (1 hour for blocked, now for non blocked)
	public function update($proxy, $blocked = false, $new = false)
	{
		// If these are blocked proxies
		if($blocked)
		{
			// Micro time in one hour (when the proxy can be used next) 
			$score = time() + $this->waitBlocked;
		}
		// If adding a new proxy to db
		elseif($new)
		{
			echo "good\n";
			// Create a random score to break up sequential adding
			$score = rand(0, time());		
			echo "$score : $proxy\n";	
		}
		// Normal use
		else
		{	
			// Time in 1 minute (when the proxy can be used next)
			$score = time() + $this->waitUse;
		}	
		
		// If an array of proxies was provided
		if(is_array($proxy))
		{
			// Test array to keep track of proxy use order
			$used = array("log:used");

		 	// Loop through each proxy to be updated
		 	foreach($proxy as $p)
		 	{
		 		// Build array for bulk sorted set update
		 		$update[] = $score;
		 		$update[] = $p;

		 		$used[] = $p; 
		 	} 

	 		// Add proxies back with new score
			$this->redis->zAddBulk($this->key, $update);			
			
			// Add proxies used to used log			
			//$this->redis->__call("lPush", $used);
		}
		// Just a single proxy was provided
		else
		{
			// Add proxy back into sorted set with new score (timestamp)
			$this->redis->zadd($this->key, $score, $proxy);			
		}		
	}

	// Update proxy usage for this hour
	public function usage($type, $inc)
	{
		$field = date("H").":$type";
		 
		// Increment overall proxy usage hash for the current hour
		$this->redis->send_command("HINCRBY", "usage:$this->engine", $field, $inc);
	}

	// Add a new proxy to the db
	public function add($proxy)
	{
		// Create proxy hash		
		$this->redis->hmset('p:'.$proxy['ip'], $proxy);	

		// Loop through sources
		foreach($this->sources as $source)
		{	
			$this->key = 'proxies:'.$source;

			// Add proxy to the proxy queue
			$this->update($proxy['ip'], false, true);			
		}	
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
	 	$score = time();

		$this->working = $this->redis->zCount("proxies:".$engine, 0, $score);		

		return $this->working;
	}
	
	// Count proxies currently blocked 
	public function checkBlocked($engine = "google")
	{
	 	$now = time() + ($this->waitUse + 1);

	 	$future = time() + $this->waitBlocked;

		$this->blocked = $this->redis->zCount("proxies:".$engine,  $now, $future);		

		return $this->blocked;
	}

	// Count proxies currently resting(forced delay between uses)
	public function checkResting($engine = "google")
	{
	 	$now = time() + 1;

	 	$future = time() + $this->waitUse;

		$this->resting = $this->redis->zCount("proxies:".$engine,  $now, $future);		

		return $this->resting;
	}				

	// Check the unblock time on the newest blocked proxy to determine whan all proxies will be unblocked
	public function checkBlockTime($engine = "google")
	{
		$last = $this->redis->zrevRange("proxies:".$engine, 0 , 0, TRUE);

		// If none are blocked
		if($last[1] <= time())
		{
			return 0;
		}

		// Amount of mins until all proxies are unblocked
		return round(($last[1] - time()) / 60);
	}	
}	