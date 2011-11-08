<?php  if(!defined('HUB')) exit('No direct script access allowed\n');
 
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
	// ===========================================================================// 
	// ! Opening and closing functions                                            //
	// ===========================================================================//

	// Run on class instantiation
	function __construct()
	{  	
		// Connect to the boss server
		$this->bossDB = new redis(BOSS_IP, BOSS_PORT);	

		// Connect to the serps server
		$this->serpsDB = new redis(REDIS_SERPS_IP, REDIS_SERPS_PORT);	
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
			$items = $this->serpsDB->zRangeByScore($key, 0, $scoreLimit, false, array(0, 100));

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

	public function getScoreRange($key)
	{
		// If it's an hourly key
		if(strpos($key, "hourly"))
		{
			// Timestamp for the last second of last hour			
			$endRange = new DateTime('last hour'); 
			$endRange = strtotime($endRange->format('Y-m-d h').":59:59"); 			
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
			$update[] = time() + (60 * 30);
			$update[] = $item;
		}

		// Checkout the items
		$this->serpsDB->zAddBulk($job['key'], $update) ."\n";				
	}	

	// ===========================================================================// 
	// ! Job queue functions for workers                                          //
	// ===========================================================================//
	
	public function getWork($channel)
	{		
		// Listen on this worker's channel for work
		$this->bossDB->subscribe($channel);

		// Wait for a job to be published
		$work = $this->bossDB->read_reply();

		// Redis commands are ignored if still subscribed to a channel
		$this->bossDB->unsubscribe($channel);
		
		return $work;				
	}		
	
	// Set worker status (0 = ready, 1 = working, quit = offline)
	public function status($name, $workerList, $status)
	{	
		// If worker is shutting down
		if($status == "quit")
		{
			// Remove this worker from the worker list
			$this->bossDB->zRem($workerList, "$name");
		}
		else
		{
			// Update the worker's status
			$this->bossDB->zAdd($workerList, $status, "$name");	
		}	
	}		

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
	public function getWorkerTypes()
	{
		return $this->bossDB->send_command("keys", "workers:*");
	}

	// Get the status of each worker from a worker type
	public function checkWorkers($type)
	{
		// Get the total amount of workers of this type
		$total = $this->bossDB->zCard($type);

		// Get the list of workers of this type
		$results = $this->bossDB->zRangeByScore($type, "0", "1", "WITHSCORES");

		// Redis returns key=>value as "key","value","key"... so have to keep track of loop
		$i = 0;

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
}	