<?php if(!defined('CORE')) exit("Go away. Pass commands through router.php\n");
 
// ******************************* INFORMATION *******************************//

// ***************************************************************************//
//  
// ** QUEUE - Manage the job queue
// ** 
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-11-04
// ** @access	private
// ** @param	
// ** @return	constants for application 
//  	
// ***************************************************************************//

// ********************************** START **********************************//

class queue 
{   
	// The redis key for worker types
	public $key;

	// The name of the current worker
	public $name;

	// The channel to listen to work on
	public $channel;
		
	// The group the worker belongs too	
	public $workerGroup;

	// Sources/queue types
	public $sources;

	// ===========================================================================// 
	// ! Opening and closing functions                                            //
	// ===========================================================================//

	// Run on class instantiation
	function __construct()
	{  	
		// Connect to the boss server
		$this->bossDB = new redis(BOSS_IP, BOSS_PORT);	

		// Connect to the serps server
		//$this->serpsDB = new redis(REDIS_SERPS_IP, REDIS_SERPS_PORT);
		
		// Get the array of sources/queue types		
		$this->sources = json_decode(QUEUE_SOURCES);	
	}

	// Run when script ends
	function __destruct()
	{
			
	}

	// ===========================================================================// 
	// ! Job queue functions for the Boss                                         //
	// ===========================================================================//	

	// Select a worker for work if available
	public function hire($source)
	{
		$worker = $this->bossDB->zRangeByScore("workers:$source", 0, 0, false, array(0,1));

		return $worker[0];
	}	

	// Check if any jobs need to be created
	public function checkForJobs($source)
	{
		// Get schedule list for provided source
		$schedules = $this->getSchedules($source);

		// Loop through available schedules for the item (hourly, daily)
		foreach($schedules as $schedule)
		{
			$key = "$source:$schedule";

			// Get the score range to search based on key name
			$scoreLimit = $this->getScoreRange($key);

			// Select a range of proxies ordered by last block 
			$items = $this->bossDB->zRangeByScore($key, 0, $scoreLimit, false, array(0, 100));

			// If items were found in the db that need updating
			if($items)
			{
				// Build job array
				$job['key'] = $key;
				$job['source'] = $source;
				$job['schedule'] = $schedule;		
				$job['items'] =  $items;

				return $job;
			}	
		}	
	}	

	public function checkUpdateSchedules()
	{
		$mins = array('1', '0');

		foreach($mins as $min)
		{
			// Loop through all source queues
			foreach($this->sources as $source)
			{	
				// Get schedule list for provided source
				$schedules = $this->getSchedules($source);
						
				// Loop through available schedules for the item (hourly, daily)
				foreach($schedules as $schedule)
				{
					$key = "$source:$schedule";

					if($min == '0')
					{
						$max = '0';	
						$type = "new";
						$tab = "\t";			
					}
					else
					{
						// Get the score range to search based on key name
						$max = $this->getScoreRange($key);	
						$type = "behind";
						$tab = "";												
					}

					// Count how many items (excluding new) are behind schedule
					$count = $this->bossDB->zCount($key, $min, $max);
					$total = $this->bossDB->zCard($key);

					// If found behind schedule
					if($count)
					{
						// Notify admin
						$behind .= "$tab$count/$total $type for $key\n";
					}					
				}				
			}	
		}	
			
		return $behind;		
	}

	// Check if any jobs need to be created
	public function checkForJobsNew($source)
	{
		// Get schedule list for provided source
		$schedules = $this->getSchedules($source);

		// Loop through available schedules for the item (hourly, daily)
		foreach($schedules as $schedule)
		{
			$key = "$source:$schedule";

			echo "key: $key\n";

			// Get the score range to search based on key name
			$scoreLimit = $this->getScoreRange($key);

			echo "current time: ".date("h:i")." \n";

			echo "next update at: ".date("h:i", $scoreLimit + (60 * 60) + 60)."\n";

			$scoreLimit = 10000000000000000;

			// Select a range of proxies ordered by last block 
			$items = $this->bossDB->zRangeByScore($key, 0, $scoreLimit, TRUE, array(0, 100));
		
			$i = 0;

			// Loop through worker type retured
			foreach($items as $item)
			{
				// Worker name
				if($i % 2 == 0)
				{
					//$worker = "$worker | p";
					$keyword = "\t$item update at: ";
				}
				// Worker status
				else
				{
					$keyword .= date("h:i", $item);

					// Add worker stat to workers array
					$results[] = $keyword;
				}
				$i++;
			}

			print_r($results);


			die();

			// If items were found in the db that need updating
			if($items)
			{
				// Build job array
				$job['key'] = $key;
				$job['source'] = $source;
				$job['schedule'] = $schedule;
				$job['items'] =  $items;

				return $job;
			}	
		}	
	}	

	// Based on the data source, determine the available update schedules
	public function getSchedules($source)
	{
		// If source is google
		if($source == "google")
		{	
			return array("hourly", "daily");
		}
		// All other sources
		else
		{
			return array("daily");
		}
	}	

	// Send work to a worker
	public function assignWork($source, $worker, $job)
	{
		// If the worker received the job successfully 
		if($this->bossDB->publish("worker:$worker", json_encode($job)))
		{
			// Worker received work, change worker status to busy 
			$this->bossDB->zAdd("workers:$source", 1, "$worker") ."\n";
			
			// Update the scores of the items in the job sent
			$this->checkOutItems($job);	

			echo "sent job to $worker\n";
		}
		// Worker didn't receive job (it could have died unexpectedly)
		else
		{
			// Remove worker from worker list as it's MIA
			$this->bossDB->zRem("workers:$source", "$worker");

			echo "$worker is MIA, removed\n";
		}
	}

	// Update the scores (timestamps) of the items sent in the job
	public function checkOutItems($job)
	{
		foreach($job['items'] as $item)
		{
			// Build array for bulk sorted set update
			$update[] = $this->setTime('checkout');
			$update[] = $item;
		}

		// Checkout the items
		$this->bossDB->zAddBulk($job['key'], $update) ."\n";				
	}	

	// ===========================================================================// 
	// ! Job queue functions for workers                                          //
	// ===========================================================================//
	
	public function getWork()
	{		
		// Listen on this worker's channel for work
		$this->bossDB->subscribe($this->channel);

		// Wait for a job to be published
		$work = $this->bossDB->read_reply();

		// Redis commands are ignored if still subscribed to a channel
		$this->bossDB->unsubscribe($this->channel);
		
		return $work;				
	}		
	
	// Set worker status (0 = ready, 1 = working, quit = offline)
	public function status($status)
	{	
		// If worker is shutting down
		if($status == "quit")
		{
			// Remove this worker from the worker list
			$this->bossDB->zRem($this->workerGroup, "$this->name");
		}
		else
		{
			// Update the worker's status
			$this->bossDB->zAdd($this->workerGroup, $status, "$this->name");	
		}	
	}	
	
	// ===========================================================================// 
	// ! Data source management                                                   //
	// ===========================================================================//		

	// // Add a new data source to the job queue system
	// public function addSource($source)
	// {
	// 	// Add source to db
	// 	$this->bossDB->sAdd("sources", $source);
	// }

	// // List all data sources used by the job queue
	// public function listSources()
	// {
	// 	return $this->bossDB->sMembers("sources");
	// }

	// ===========================================================================// 
	// ! Message system (pub/sub)                                                 //
	// ===========================================================================//
	
	// Monitor system message channels
	public function monitor()
	{
		// Subscribe to all worker channels
		$this->bossDB->subscribe("monitor:all");
		// Subscribe to only worker type channel
		$this->bossDB->subscribe("monitor:".INSTANCE_TYPE);
		// Subscribe to specific worker channel
		$this->bossDB->subscribe("monitor:".INSTANCE_NAME);

		// Wait for instructions
		return $this->bossDB->read_reply();	
	}	

	// ===========================================================================// 
	// ! Worker stats                                                             //
	// ===========================================================================//

	// Return every worker type checked in
	// public function getWorkerTypes()
	// {
	// 	// Get all of the keys for the workers
	// 	$keys = $this->bossDB->send_command("keys", "workers:*");

	// 	// Loop through each worker type key
	// 	foreach($keys as $key)
	// 	{
	// 		// Remove the  "workers:" part of the key to leave just the type (i.e google,bing etc)
	// 		$types[] = str_replace("workers:", "", $key);
	// 	}

	// 	return $types;
	// }

	// Get the status of each worker from a worker type
	public function checkWorkers($type)
	{
		// Get the total amount of workers of this type
		$total = $this->bossDB->zCard($type);

		// Get the list of workers of this type
		$results = $this->bossDB->zRangeByScore($type, "0", "1", "WITHSCORES");

		// Redis returns key=>value as "key","value","key"... so have to keep track of loop
		$i = 0;

		// Declare as array first incase no results are found
		$workers = array();

		// Loop through worker type retured
		foreach($results as $result)
		{
			// Worker name
			if($i % 2 == 0)
			{
				//$worker = "$worker | p";
				$worker = "$result | ";
			}
			// Worker status
			else
			{
				if($result == "0")
				{
					$worker .= "available";
				}
				else
				{
					$worker .= "working";
				}
		
				// Add worker stat to workers array
				$workers[] = $worker;
			}
			$i++;
		}

		return array('total' => $total, 'workers' => $workers);
	}

	// ===========================================================================// 
	// ! Score timestamp function                                                 //
	// ===========================================================================//

	public function fakeAdd()
	{
		$this->bossDB->zadd('google:hourly', 0, 123);	
	}

	public function fakeCheckOut()
	{
		$this->bossDB->zadd('google:hourly', $this->setTime('checkout'), 123);	
	}	
	
	public function fakeUpdate()
	{
		$this->bossDB->zadd('google:hourly', time(), 123);	
	}	

	public function fakeCheck()
	{
		echo $this->getScoreRange("google:hourly")."\n";
	}

	public function fakeOld()
	{
		$this->bossDB->zadd('google:hourly', time() - (60 * 60), 123);	
	}		

	public function getScoreRange($key)
	{
		// If it's an hourly key
		if(strpos($key, "hourly"))
		{
			// Timestamp for the last second of last hour			
			$endRange = new DateTime('last hour'); 
			$endRange = strtotime($endRange->format('Y-m-d H').":59:59"); 			
		}
		// Else it's a daily key
		else
		{
			// Timestamp for the last second of yesterday
			$endRange = new DateTime('yesterday'); 
			$endRange = strtotime($endRange->format('Y-m-d')." 23:59:59");   	
		}
		
		// Search for scores between 0 and the last minute of last hour
		return $endRange;
	}	
	
	// Calculate a time to return for a redis score
	public function setTime($type)
	{
		if($type == 'checkout')
		{
			return time() + (60 * 30);
		}
		
	}		
}	