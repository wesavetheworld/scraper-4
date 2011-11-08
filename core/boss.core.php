<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** BOSS - Manages all jobs required to run SEscout
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-07-12
// ** @access	private
// ** @param	
// ** @return	Loops indefinitely and executes new processes when needed     
//  	
// ***************************************************************************//

// ********************************** START **********************************// 

class bossCore 
{    
	// Bing instance status
	private $bingStatus = false;

	private $googleJobs = false;
	
	private $googleJobsLast = false;

	// Include dependencies on instantiation
	function __construct()
	{
		// Instantiate new redis object
		$this->redis = new redis(REDIS_SERPS_IP, REDIS_SERPS_PORT);			
	}	

	// ===========================================================================// 
	// ! Infinite daemon loop                                                     //
	// ===========================================================================//
	
	// The main loop that acts as a daemon
	public function daemon()
	{	
		// Declare job types explicitly to avoid issues where workers are off line (like bing)
		$workerTypes = array('google', 'bing', 'pr', 'backlinks', 'alexa');

		// Loop forever if not development client
		while(TRUE)
		{	
			// Loop through each worker type		
			foreach($workerTypes as $source)
			{
				// Loop for as long as this type of worker is available and there are jobs
				while(($worker = $this->hire($source)) && ($job = $this->checkForJobs($source)))
				{
					// Assign the job to the worker
					$this->assignWork($source, $worker, $job);
				}	
			}

			echo "check complete\n";
			sleep(3);
		}
	}

	// Select a worker if available
	private function hire($source)
	{
		$worker = $this->redis->zRangeByScore("workers:$source", 0, 0, false, array(0,1));

		return $worker[0];
	}
	
	private function checkForJobs($source)
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
			$items = $this->redis->zRangeByScore($key, 0, $scoreLimit, false, array(0, 100));

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

	private function getScoreRange($key)
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

	private function getSchedules($source)
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
	private function assignWork($source, $worker, $job)
	{
		// If the worker received the job successfully 
		if($this->redis->publish("worker:$worker", json_encode($job)))
		{
			// Worker received work, change worker status to busy 
			$this->redis->zAdd("workers:$source", 1, "$worker") ."\n";
			
			// Update the scores of the items in the job sent
			$this->checkOutItems($job);	

			echo "sent job to $worker\n";
		}
		// Worker didn't receive job (it could have died unexpectedly)
		else
		{
			// Remove worker from worker list as it's MIA
			$this->redis->zRem("workers:$source", "$worker");

			echo "$worker is MIA, removed\n";
		}
	}

	// Update the scores (timestamps) of the items sent in the job
	private function checkOutItems($job)
	{
		foreach($job['items'] as $item)
		{
			// Build array for bulk sorted set update
			$update[] = time() + (60 * 30);
			$update[] = $item;
		}

		// Checkout the items
		$this->redis->zAddBulk($job['key'], $update) ."\n";				
	}

	
}	