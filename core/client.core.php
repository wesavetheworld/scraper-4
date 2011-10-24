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
	}	

	// ===========================================================================// 
	// ! Infinite daemon loop                                                     //
	// ===========================================================================//
	
	// The main loop that acts as a daemon
	public function daemon()
	{	
		// If this is the dev client
		if(defined("DEV"))
		{
			// Dont run cron, die please
			exit("DEV is done!\n");
		}

		// Loop forever 
		while(!defined("DEV") && TRUE)
		{
			// Log time for current task loop
			utilities::notate("Actions at: ".date("H:i:s"), "clientd.log");
						
			// Check system status
			utilities::checkStatus();

			// Every 10 minutes
			if(intval(ltrim(date("i"), "0")) % 10 == 0)
			{
				// Run all 10 minute tasks
				$this->minuteEveryTen();
			}				

			// If first hour of the day
			if(date("H:i") == "00:00")
			{
				// Run all daily tasks
				$this->hourOne();			
			}

			// The first min of every hour
			if(date("i") == "00")
			{
				// Run all hourly tasks
				$this->hourAll();
			}		

			// Every 2 minutes
			if(intval(ltrim(date("i"), "0")) % 2 == 0)
			{
				// Run all every other minute tasks
				$this->minuteEveryTwo();			
			}

			// Run all minute tasks 
			$this->minuteAll();			  		   	 				

			// Wait for next loop
			$this->meditate();	
		}
	}
	
	// ===========================================================================// 
	// ! Time based functions                                                     //
	// ===========================================================================//
	
	// Tasks that should be run daily
	private function hourOne()
	{
		// Remove old saved search files
		$this->run("tasks", "cleanSearchDirectory");	
		
		// Clean up the server logs
		$this->run("tasks", "cleanLogs");
				
		// Update all daily keywords for google
		$this->run("client", "rankings 100 google daily");	
		
		// Update all daily keywords for bing
		$this->run("client", "rankings 100 bing daily");	
		
		// Turn on bing instances		
		$this->run("tasks", "bing start");
		$this->bingStatus = true;					
		
		// Update domain stats
		$this->domainStats();			
	}	
	
	// Tasks that should be run hourly
	private function hourAll()
	{
		// Reset proxy stats in db
		//$this->run("tasks", "proxyReset");	
		
		// Check that all keywords are following their schedules
		$this->run("tasks", "checkSchedules");	
		
		// Check in any old keywords		
		$this->run("tasks", "keywordCheckIn");
		
		// Send notifications for any rank changes last hour
		$this->run("notifications");	

		// If job queue has fewer than 10 jobs
		if($this->googleJobs < 10)
		{						
			// Update hourly keyword rankings for google
			$this->run("client", "rankings 100 google hourly");														
		}	
		
		// If bing instances are on
		if($this->bingStatus && date("H:i") != "00:00" )	
		{
			// Get number of bing jobs
			$queue = $this->checkJobQueue('rankingsBing');			
			
			// If job queue is empty
			if(!$queue)
			{				
				// Turn off bing instances		
				$this->run("tasks", "bing stop");
				$this->bingStatus = false;																			
			}	
		}				
	}
	
	// Taks that should be run every 10 minutes
	private function minuteEveryTen()
	{
		// Check the current job queue for changes
		$this->checkStaleJobs();
	}	
	
	// Tasks that should be run every 2 minutes
	private function minuteEveryTwo()
	{
		// Update domain stats
		$this->domainStats('new');	

		// Check for new keywords to update for google
		$this->run("client", "rankingsNew 100 google");
		
		// Check for new keywords to update for bing
		$this->run("client", "rankingsNew 100 bing");			
	}
	
	// Tasks that should be run every minute
	private function minuteAll()
	{
		
	}		

	// ===========================================================================// 
	// ! Supporting functions                                                     //
	// ===========================================================================//	

	// Check for oustanding jobs stilled queued
	private function checkJobQueue($type)
	{
		// Instantiate new gearman call
		$jobServer = new jobServerStatus(JOB_SERVER_IP);	

		// Retrieve list of current jobs in queue
		$status = $jobServer->getStatus();	
		
		// Return specified job type job queue total
		return $status['operations'][$type]['total'];	
	}

	// Check if there are any stale jobs
	private function checkStaleJobs()
	{
		// Get current job Queue total
		$this->googleJobs = $this->checkJobQueue('rankingsGoogle');

		// If the amount of jobs has not changed for the last 10 minutes or it's the first min of the hour
		if($this->googleJobs && $this->googleJobs == $this->googleJobsLast || date("i") == "00")
		{
			// Restart all workers		
			$this->run("tasks", "system reset_worker");
			
			// Reset google job count
			$this->googleJobsLast = false;						
		}
		// No stale jobs found		
		else
		{
			// Set current job count for future last job count
			$this->googleJobsLast = $this->googleJobs;						
		}	
	}

	// Update all domain's stats
	private function domainStats($new = false)
	{
		// Update all domain's pagerank
		$this->run("client", "pr 100 $new");
		
		// Update all domain's backlinks			
		$this->run("client", "backlinks 100 $new");

		// Update all domain's alexa rankings
		$this->run("client", "alexa 100 $new");		
	}	
	
	// ===========================================================================// 
	// ! Main daemon functions                                                    //
	// ===========================================================================//	

	// Execute bash command that detaches from daemon
	private function run($controller, $options = false)
	{
		// Build the command to execute
		$command = "php hub.php $controller $options > /dev/null 2>/dev/null &";

		// Execute command given
		exec($command);

		file_put_contents("data/clientCore.log", "command: $controller $options ".date("r"));		

		// Log current command
		utilities::notate("command: $controller $options", "clientd.log");		  		   	 				
	}

	// Determine amount of time to wait before daemon loops again
	private function meditate()
	{	
		// Get remaining seconds in current minute
		$sleep = intval(60 - intval(ltrim(date("s"), "0")));
		
		// Sleep until next minute
		sleep($sleep);		  		   	 					
	}	
}	