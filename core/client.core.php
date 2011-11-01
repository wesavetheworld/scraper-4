<?php  if(!defined('HUB')) exit('No direct script access allowed\n');

// ******************************* INFORMATION ******************************//

// **************************************************************************//
//  
// ** CLIENT - Acts like cron. Fires off actions based on the current time.
// ** 
// ** @author	Joshua Heiland <thezenman@gmail.com>
// ** @date	 2011-07-12
// ** @access	private
// ** @param	
// ** @return	Loops indefinitely and executes new processes when needed     
//  	
// ***************************************************************************//

// ********************************** START **********************************// 

class clientCore 
{    
	// Bing instance status
	private $bingStatus = false;

	private $googleJobs = false;
	
	private $googleJobsLast = false;

	// Include dependencies on instantiation
	function __construct()
	{
		// Include gearman class for job status updates
	 	require_once('classes/gearman.class.php');

	 	// Include redis class
		require_once('classes/redis.php');

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
		$jobs = array( 'google:hourly',
					   'google:daily',
					   'google:new', 
					   'bing:daily',	
					   'bing:new', 
					   'pr:daily', 
					   'pr:new', 
					   'alexa:daily', 
					   'alexa:new', 
					   'backlinks:daily', 
					   'backlinks:new');

		// Loop forever if not development client
		while(TRUE && defined("DEV"))
		{
			echo "loop\n";

			// Get the current job queue list
			$this->checkJobQueue();

			// Loop through each job type
			foreach($jobs as $job)
			{			
			 	// If job not registered with gearman or fewer jobs than workers
				if(!$this->status[$job] || $this->status[$job]['total'] <= $this->status[$job]['connectedWorkers'])
				{
					// Check for potential jobs
					$items = $this->checkForJobs($job);

					// If items are found that need updating
					if($items)
					{

						echo "$job items found: \n";
						count($items);
						
						// Create new jobs
						//$this->createJobs($items);
					}
					else
					{
						echo "nothing to update for $job\n";
					}
				}
			}

			// Wait a minute then start over
			sleep(5);
		}		
	}

	private function checkJobQueue()
	{
		// Instantiate new gearman call
		$jobServer = new jobServerStatus(JOB_SERVER_IP);

		// Retrieve list of current jobs in queue
		$this->status = $jobServer->getStatus();	
		$this->status = $this->status['operations'];	
	}
	
	private function checkForJobs($key)
	{
		// Get the score range to search based on key name
		$scoreLimit = $this->getScoreRange($key);

		// Select a range of proxies ordered by last block 
		$items = $this->redis->ZRANGEBYSCORE($key, 0, $scoreLimit);

		// If items were found in the db that need updating
		if($items)
		{
			// Remove all proxies just selected
			//$this->redis->ZREMRANGEBYRANK($key, 0, $scoreLimit);
	
			return $items;
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

	private function createJobs()
	{
		
	}
	
}	