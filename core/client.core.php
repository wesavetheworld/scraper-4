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
		// Declare job types explicitly to avoid issues where workers are ofline (link bing)
		$types = array('rankingsGoogle', 
					   'rankingsBing',	
					   'rankingsNewGoogle', 
					   'rankingsNewBing', 
					   'pr', 
					   'alexa', 
					   'backlinks');

		// Loop forever if not development client
		while(TRUE && !defined("DEV"))
		{
			// Get the current job queue list
			$this->checkJobQueue();

			// Loop through each job type
			foreach($types as $type)
			{			
			 	// If job not registered with gearman or fewer jobs than workers
				if(!$this->status[$type] || $this->status[$type]['total'] <= $this->status[$type]['connectedWorkers'])
				{
					// Check for potential jobs
					$this->checkForJobs($type, $model, $schedule);
				}
			}

			// Wait a minute then start over
			sleep(60);
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
	
	private function checkForJobs($type, $model, $schedule)
	{
		// Select a range of proxies ordered by last block 
		$items = $this->redis->ZRANGEBYSCORE("keyword, 0, $totalProxies);

		// Remove all proxies just selected
		$this->redis->ZREMRANGEBYRANK($key, 0, $totalProxies);

	}

	private function createJobs()
	{
		
	}
	
}	